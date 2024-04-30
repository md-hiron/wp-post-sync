<?php
/**
 * Sync Division Post
 *
 * @version 1.0.0
 * @package Unitek sync data\Division post
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


/**
 * Sync Remote Post
 *
 * Sync WordPress news post to remote website
 *
 * @version 1.0.0
 */

class Sync_EG_Post{
    /**
	 * Username of Remote API
	 *
	 * @var string
	 */
	protected $username;
	
	/**
	 * Password of Remote API
	 *
	 * @var string
	 */
	protected $application_password;

	/**
	 * encoding of Remote API credentials
	 *
	 * @var string
	 */
	protected $encoded;

	/**
	 * Base url of Remote API
	 *
	 * @var string
	 */
	protected $base_url;
	
	 /**
	 * Cloudflare Cache zones
	 *
	 * @var string
	 */
	protected $cl_zones;

	

	/**
	 * constructor
	 */
	public function __construct($username, $password, $base_url, $cl_zone = ''){
		
		$this->username = $username;

		$this->application_password = $password;

		$this->encoded = base64_encode($this->username.':'.$this->application_password);

		$this->base_url = $base_url;
		
		$this->cl_zones = $cl_zone;
		
	}

	/**
	 * check division
	 * 
	 * @param object $post
	 * @param string $division_type
	 * 
	 * @since 1.0.0
	 * @return false
	 */

	public function check_division($post, $division_type){
		//check if $post object is not empty
		if( empty( $post ) ){
			return false;
		}

		//check if division type is not empty
		if( empty( $division_type ) ){
			return false;
		}

		//geting divition terms
		$divisions = get_the_terms( $post , 'divisions' );
		
		// only division slug array
		$division_slug = [];
		
		if( $divisions == null ){
			return false;
		}
		
		//checking is division has terms item
		if( count( $divisions ) > 0 ){
			// geting only division slug from the division term object and store on $division_slug
			foreach( $divisions as $division ){
				array_push( $division_slug, $division->slug );
			}
		}
		
		//checking if division type is exist
		if( ! in_array( $division_type, $division_slug )  ){
			return false;
		}

		return true;

	}

	/**
	 * Division Notice
	 */
	public function division_notice( $division_type, $action ){

		if( empty( $division_type ) && empty( $action ) ){
			return false;
		}

		$sync_notice = [];

		if( get_transient( $action ) ){
			$sync_notice = get_transient( $action );
		}

		if(! in_array( $division_type, $sync_notice )){
			$sync_notice[] = $division_type;
		}

		set_transient( $action, $sync_notice, 30 );
	}
	
	/*
	 * Add post on Remote website after creating a post on base server
	 * 
	 * @param object $post will be created by this post object
	 * 
	 * @since 1.0.0
	 * 
	 * @return void
	 * */

	public function add_post( $post, $division_type ){
		//check post object
		if( empty( $post ) ){
			return false;
		}

		$post_ID = $post->ID;
		$post_author = $post->post_author;
		$cache_obj = new Und_Cache_Api();
		//endpoint for create news post
		$endpoint = $this->base_url ."wp-json/wp/v2/news";
		

		//store remote post ID
		$remote_post_id = get_post_meta( $post_ID, $division_type .'_post_id', true);
		
		//check if remote post ID is not exist
		if( ! empty( $remote_post_id ) ){
			return false;
		}

		//check correct division is selected
		$check_division = $this->check_division($post, $division_type);

		if( $check_division == false ){
			return false;
		}
		
		//sending request to Division API
		$request = wp_remote_post(
			$endpoint,
			array(
				'method'      => 'POST',
				'headers' => array(
					'Authorization' => "BASIC ". $this->encoded
				),
				'body' => array(
					'title'   => $post->post_title,
 					'content' => $post->post_content,
					'status'  => 'draft',
					'slug' => $post->post_name,
					'excerpt' => $post->post_excerpt,
					'date'    => $post->post_date,
					'date_gmt' => $post->post_date_gmt,
				)
			)
		);
		
		
		
		//error checking
		if ( is_wp_error( $request ) ) {

			$error_message = 'Something went wrong: '.$request->get_error_message();
				
			// set transient for division publish error
			$this->division_notice($division_type, 'publish_error' );

			update_post_meta($post_ID, $division_type.'_publish_post_status', $error_message);

			//error log
			do_action( 'unitek_log', 'Publish post error on ' . $division_type . ': '. $error_message .'. Post ID: ' . $post_ID . ', author: '.get_the_author_meta('display_name', $post_author), 'alert' );
			
			return false;
			
		}

		//geting response from request
		$response = wp_remote_retrieve_response_message( $request );

		//if request has response
		if( 'Created' == $response ){
			//request body data
			$created_post = json_decode( wp_remote_retrieve_body( $request ) );
			$created_id = $created_post->id;

			
			//check if requst created post on division
			if( $created_id ){
				//save remote post ID
				update_post_meta($post_ID, $division_type .'_post_id', $created_id);

				update_post_meta($post_ID, $division_type.'_publish_post_status', 'Post created successfully on '.$division_type);
				
				//save transient to show notification on admin dashboard
				set_transient($division_type .'_publish_success', 'Post created successfully on '.$division_type , 30);

				//error log
				do_action( 'unitek_log', 'Post created successfully on '.$division_type.'. Post ID: ' . $post_ID . ', created post ID: '. $created_id .', author: '.get_the_author_meta('display_name', $post_author), 'notice' );
				
				//upload feature image after creating post
				$this->upload_image( $post, $division_type );

				//udpate post status after creating post
				$this->update_post_status( $post, $created_id, $division_type );

				$this->division_notice( $division_type, 'publish_success' );
				
				//Cloudflare clear cache
				$cache_obj->usd_cloudflare_clear_cache( $this->cl_zones, $division_type );
				//Nitropack clear cache
				$cache_obj->usd_nitropack_clear_cache( $division_type );
				// WP Rest clear cache
				$cache_obj->usd_wp_rest_clear_cache( $division_type );


			}else{
				// set transient for division publish error
				set_transient($division_type .'_publish_error', $division_type .' publish post error: '.$response, 30);

				update_post_meta($post_ID, $division_type.'_publish_post_status', $response);

				//error log
				do_action( 'unitek_log', 'Publish post error on '.$division_type.': '.$response.'. Post ID: '. $post_ID .', author: '.get_the_author_meta('display_name', $post_author), 'alert' );

				$this->division_notice( $division_type, 'publish_error' );
			}
		}
		
	}
	

	/**
	 * Update post status on Remote website
	 * 
	 * @param int $created_post_Id of remote news post
	 * 
	 * @since 1.0.0
	 * 
	 * @return void
	 * 
	 * Remote news status should be publish after creating news 
	 */
	public function update_post_status($post, $created_post_id, $division_type){
		//check if $post object is not empty
		if( empty( $post ) ){
			return false;
		}

		//check if remote post ID is exist
		if( empty( $created_post_id ) && is ){
			return false;
		}

		//check if division type is exist
		if( empty( $division_type ) ){
			return false;
		}

		$post_ID = $post->ID;
		$post_author = $post->post_author;
		
		//endpoint for update post status after creating the post on remote website
		$endpoint = $this->base_url ."wp-json/wp/v2/news/".$created_post_id;
		$cache_obj = new Und_Cache_Api();
		$body_data = [
                	'status' => $post->post_status,
                ];
		
		//convert array to JSON
       $body_json = json_encode( $body_data);
		
		//request for update post status
		$request = wp_remote_post(
			$endpoint,
			array(
				'method'      => 'POST',
				'headers' => array(
					'Content-Type' => 'application/json',
					'Authorization' => "BASIC ". $this->encoded
				),
				'body' => $body_json
			)
		);
		
		//check if request gives error on udpate post status
		if ( is_wp_error( $request ) ) {
			
			$error_message = 'Something went wrong: '.$request->get_error_message();

			update_post_meta($post_ID, $division_type.'_publish_post_status', $error_message);

			$this->division_notice( $division_type, 'publish_error' );

			do_action( 'unitek_log', 'Update post status error on '.$division_type.': '.$error_message.'. Post ID: '.$post_ID.', author: '.get_the_author_meta('display_name', $post_author), 'alert' );
			
			return false;
		}

		//geting response from request
		$response = wp_remote_retrieve_response_message( $request );

		if( 'OK' ===  $response){
			do_action( 'unitek_log', ' Updte post status successfully on '.$division_type.'. Post ID: '.$post_ID.', created post ID: '.$created_post_id.', author: '.get_the_author_meta('display_name', $post_author), 'notice' );
			//set transient
			
			update_post_meta($post_ID, $division_type.'_publish_post_status', 'Post published on'.$division_type);
			
			//Cloudflare clear cache
			$cache_obj->usd_cloudflare_clear_cache( $this->cl_zones, $division_type );
			//Nitropack clear cache
			$cache_obj->usd_nitropack_clear_cache( $division_type );
			// WP Rest clear cache
			$cache_obj->usd_wp_rest_clear_cache( $division_type );
			
		}else{
			update_post_meta($post_ID, $division_type.'_publish_post_status', $response );


			do_action( 'unitek_log', 'Update post status error on '.$division_type.': '.$response.'. Post ID: '.$post_ID.', author: '.get_the_author_meta('display_name', $post_author), 'alert' );
		}
		
		
	}

	/**
	 * Upload image on divisions by REST API
	 * 
	 * @param int $post_id of created post of local server 
	 * 
	 * @since 1.0.0
	 * 
	 * @return void
	 * 
	 * Upload image on Divisions after created / updated the post
	 */
	public function upload_image( $post, $division_type ){
		//check if post object exist
		if( empty( $post  ) ){
			return false;
		}

		$post_ID = $post->ID;
		$post_author = $post->post_author;

		$check_division = $this->check_division($post, $division_type);

		if( $check_division == false ){
			return false;
		}
		
		// Post ID of remote news post that was created by REST API 
		$remote_post_id = get_post_meta( $post_ID, $division_type.'_post_id', true ) ?? '';
		
		//check if remote post ID is exist
		if( empty( $remote_post_id ) ){
			return false;
		}
		
		// store thumbnail of local server post
		$thumbnail_id = get_post_thumbnail_id( $post_ID ) ?? '';
		
		//check if thumbnail ID is exist
		if( empty( $thumbnail_id ) ){
			return false;
		}
		
		// store image absolute path
		$image_path = wp_get_original_image_path( $thumbnail_id );
		
		//check if image path is exist
		if( empty( $image_path ) ){
			return false;
		}
		
		// store endpoint for upload thumbnail image by REST API
		$endpoint = $this->base_url ."wp-json/wp/v2/media";
		$cache_obj = new Und_Cache_Api();
		// Thumbnail image upload request
		$request = wp_remote_post(
			$endpoint,
			array(
				'method'      => 'POST',
				'headers' => array(
					'Content-Type:' . wp_get_image_mime( $image_path ),
					'Content-Disposition' => 'attachment; filename="' . basename( $image_path ) . '"',
					'Authorization' => "BASIC ". $this->encoded
				),
				'body' => file_get_contents( $image_path )
			)
		);
		
		// check if request has error
		if ( is_wp_error( $request ) ) {
			
			$error_message = 'Error: '. $request->get_error_message();
			
			set_transient( $division_type.'_media_error', 'Media upload error on '.$division_type.': '.$error_message, 30 );

			update_post_meta( $post_ID, $division_type.'_thumbnail_status', $error_message);

			$this->division_notice( $division_type, 'media_error' );

			//error log
			do_action( 'unitek_log', 'Media upload error on '.$division_type.': '.$error_message.'. Post ID: '. $post_ID .', thumbnail ID: '. $thumbnail_id .', author: '.get_the_author_meta( 'display_name', $post_author ), 'notice' );


			return false;
			
		}
		
		//store response
		$response = wp_remote_retrieve_response_message( $request );

		//check if response is exist
		if( 'Created' == $response ){
			
			$created_media = json_decode( wp_remote_retrieve_body( $request ) );
			$created_media_id = $created_media->id;

			if( $created_media_id ){
				//save remote thumbnail ID
				update_post_meta( $post_ID, $division_type.'_thumbnail_id', $created_media_id );
				
				//save remote thumbnail ID on media 
				update_post_meta( $thumbnail_id, $division_type.'_media_id', $created_media_id );
				
				//save current thumbnail id only on upload image
				update_post_meta( $post_ID, $division_type.'_current_thumbnail_id', $thumbnail_id);
				
				//show notification
				$this->division_notice( $division_type, 'media_success' );
				
				//Cloudflare clear cache
				$cache_obj->usd_cloudflare_clear_cache( $this->cl_zones, $division_type );
				
				//Nitropack clear cache
				$cache_obj->usd_nitropack_clear_cache( $division_type );

				// WP Rest clear cache
				$cache_obj->usd_wp_rest_clear_cache( $division_type );
				//error log
				do_action( 'unitek_log', 'Media uploaded successfully on '. $division_type .'. Post ID: '. $post_ID .', thumbnail ID: '. $thumbnail_id .', Created Thumbnail ID: '. $created_media_id .', author: '.get_the_author_meta('display_name', $post_author ), 'notice' );
			}
		}else{
			set_transient( $division_type.'_media_error', 'Media upload error on '.$division_type.': '.$response, 30 );
			//error log
			update_post_meta( $post_ID, $division_type.'_thumbnail_status', $response);
			do_action( 'unitek_log', 'Media upload error on '.$division_type.': '.$error_message.'. Post ID: '.$post_ID.', thumbnail ID: '.$thumbnail_id.', author: '.get_the_author_meta('display_name', $post_author ), 'alert' );

			$this->division_notice( $division_type, 'media_error' );
		}
		
	}
	
	/**
	 * Update post meta on remote website by REST API
	 * 
	 * @param int $post_id of created post of local server 
	 * 
	 * @since 1.0.0
	 * 
	 * @return void
	 * 
	 * updated post meta after creating the post
	 */
	public function update_post_meta( $post, $division_type ){
		//check if post boject is not empty
		if( empty( $post ) ){
			return;
		}

		$post_ID = $post->ID;
		$post_author = $post->post_author;
		$cache_obj = new Und_Cache_Api();
		
		// Post ID of remote news post that was created by REST API 
		$created_post_id = get_post_meta($post_ID, $division_type."_post_id", true );
		
		//check if remote post ID exist
		if( empty( $created_post_id ) ){
			return false;
		}

		$check_division = $this->check_division($post, $division_type);

		if( $check_division == false ){
			return false;
		}

		
		//post meta data
		$featured_media_id = get_post_meta($post_ID, $division_type."_thumbnail_id", true );
		$thumbnail_id = get_post_meta($post_ID, "_thumbnail_id", true);
		$post_read_time = get_post_meta($post_ID, "post_read_time", true );
		$featured_news = get_post_meta($post_ID, "featured_news", true );
		
		//endpoint for update meta
		$endpoint = $this->base_url ."wp-json/wp/v2/news/".$created_post_id;
		
		//update meta data array
		$body_data = [
			'meta' => [
				'_yoast_wpseo_canonical' => get_the_permalink($post_id)
			]
		];

		
		//check if meta exist
		if( ! empty( $thumbnail_id ) &&  ! empty( $featured_media_id )){
			$body_data['featured_media'] = $featured_media_id;
		}else{
			//update featured media meta if featured media is removed
			$body_data['featured_media'] = 0;
		}
		
		if( isset( $post_read_time ) ){
			$body_data['acf']['post_read_time'] = $post_read_time;
		}
		
		if( isset( $featured_news ) ){
			$body_data['acf']['featured_news'] = $featured_news;
		}


		//convert array to json
        $body_json = json_encode( $body_data);
		
		// send request to update meta
		$request = wp_remote_post(
			$endpoint,
			array(
				'method'      => 'POST',
				'headers' => array(
					'Content-Type' => 'application/json',
					'Authorization' => "BASIC ". $this->encoded
				),
				'body' => $body_json
			)
		);
		
		// check if request has error
		if ( is_wp_error( $request ) ) {
			
			$error_message = 'Something went wrong: '.$request->get_error_message();
			
			//save error on post meta
			update_post_meta( $post_ID, $division_type.'_post_meta_status', $error_message);
			
			//show notification on admin if it's error
			set_transient($division_type.'_update_meta_error', 'Post meta udpate error on '.$division_type.': '.$error_message, 30);

			//error log
			do_action( 'unitek_log', 'Post meta udpate error on '.$division_type.': '.$error_message.'. Post ID: '.$post_ID.', author: '.get_the_author_meta( 'display_name', $post_author ), 'notice' );
			
			return false;
		}
		
		// response of request
		$response = wp_remote_retrieve_response_message( $request );
		
		//check if meta updated on remote successfully
		if( 'OK' == $response ){
			//save meta with success message
			update_post_meta($post->ID, $division_type.'_post_meta_status', 'Meta updated successfully on '.$division_type);

			do_action( 'unitek_log', 'Post meta Updated successfully on '. $division_type .'. Post ID: ' . $post_ID . ', author: '.get_the_author_meta('display_name', $post->author), 'notice' );
			
			//Cloudflare clear cache
			$cache_obj->usd_cloudflare_clear_cache( $this->cl_zones, $division_type );
			//Nitropack clear cache
			$cache_obj->usd_nitropack_clear_cache( $division_type );

			// WP Rest clear cache
			$cache_obj->usd_wp_rest_clear_cache( $division_type );

		}elseif('Not Found' === $response ){
			//save error message to meta
			update_post_meta( $post_ID, $division_type.'_post_meta_status', 'The post is deleted from the '. $division_type);

			do_action( 'unitek_log', 'Update meta error on '. $division_type .': The post is permanently deleted from '. $division_type .'. Post ID: '. $post_ID.', author: '.get_the_author_meta('display_name', $post_author), 'alert' );

		}elseif('Forbidden' === $response ){
			//save error message to meta
			update_post_meta( $post_ID, $division_type.'_post_meta_status', 'Meta key is not registered to REST API on '.$division_type);

			do_action( 'unitek_log', 'Update meta error on '.$division_type.': Meta key is not registered to REST API on '.$division_type.'. Post ID: '.$post_ID.', author: '.get_the_author_meta('display_name', $post_author ), 'alert' );
		}else{
			// save meta with error response
			update_post_meta( $post_ID, $division_type .'_post_meta_status', $response);

			do_action( 'unitek_log', 'Meta update error on '. $division_type .': '.$response.'. Post ID: '. $post_author .', author: '.get_the_author_meta('display_name', $post_author), 'alert' );
		}
		
	}
	
	
	/**
	 * Update post on remote website by REST API
	 * 
	 * @param int $post_id of created post of local server 
	 * @param object $post local post object that is updated
	 * @param bool $update check if the post is not created for the first time
	 * 
	 * @since 1.0.0
	 * 
	 * @return void
	 * 
	 */
	public function update_post($post_id, $post, $update, $division_type){
		//check if post ID exist
		if( empty( $post_id ) ) return false;

		//check if post object is not empty
		if( empty( $post ) ) return false;

		//check if the post is existing post
		if( $update == false ) return false;

		//check if division type is exist
		if( ! $division_type ) return false;

		$post_author = $post->post_author;
		
		//remote post ID
		$remote_post_id = get_post_meta( $post_id, $division_type.'_post_id', true );

		$cache_obj = new Und_Cache_Api();

		// disable autosave and check if post ID is exist
		if ( wp_is_post_autosave( $post_id ) || ! $post_id ) {
			return false;
		}
		
		//get post division
		$post_division = get_the_terms( $post_id, 'divisions' );


		$division_slug = [];
		if( is_array( $post_division ) ){
			// geting only division slug from the division term object and store on $division_slug
			foreach( $post_division as $division ){
				array_push( $division_slug, $division->slug );
			}
		}

		// init post status
		//$post_status = $post->post_status;

		
		// store udpate data
		$body_data = [
			'title'   => $post->post_title,
			'slug' => $post->post_name,
			'content' => $post->post_content,
			'status'  => $post->post_status,
			'excerpt' => $post->post_excerpt,
			'date'    => $post->post_date,
			'date_gmt' => $post->post_date_gmt,
		];

		
		if( !empty( $remote_post_id ) ){

			if( ! in_array( $division_type, $division_slug ) ){
				$body_data['status'] = 'draft';
			}

			$endpoint = $this->base_url ."wp-json/wp/v2/news/".$remote_post_id;
			
		}else{
			if( in_array( $division_type, $division_slug ) ){
				$endpoint = $this->base_url ."wp-json/wp/v2/news";
				$body_data['status'] = 'draft';
			}else{
				return false;
			}
		}

		//convert data to json format
		$body_json = json_encode( $body_data);

		//Send request for udpate post
		$request = wp_remote_post(
			$endpoint,
			array(
				'method'      => 'POST',
				'headers' => array(
					'Content-Type' => 'application/json',
					'Authorization' => "BASIC ". $this->encoded
				),
				'body' => $body_json
			)
		);

	
		//if request has error
		if ( is_wp_error( $request ) ) {

			$error_message = 'Something went wrong: '.$request->get_error_message();
			
			//save error message to meta
			update_post_meta($post_id, $division_type.'_update_post_error', $error_message);
			
			//save transient for admin notice
			$this->division_notice( $division_type, 'update_error' );
			
			//error log
			do_action( 'unitek_log', 'Update post error on '.$division_type.': '.$error_message.'. Post ID: '.$post_id.', author: '.get_the_author_meta('display_name', $post_author), 'alert' );

			return false;
			
		} 
		//store response from request
		$response = wp_remote_retrieve_response_message( $request );
		
		//if request is success
		if( 'OK' === $response ){
			$updated_post = json_decode( wp_remote_retrieve_body( $request ) );
			
			update_post_meta($post_id, $division_type.'_sync_status', 'Post updated successfully on '.$division_type);
			
			$this->division_notice( $division_type, 'update_success' );

			//error log
			do_action( 'unitek_log', 'Post updated successfully on '.$division_type.'. Post ID: '.$post_id.', author: '.get_the_author_meta('display_name', $post_author), 'notice' );
			
			//Cloudflare clear cache
			$cache_obj->usd_cloudflare_clear_cache( $this->cl_zones,  $division_type );
			//Nitropack clear cache
			$cache_obj->usd_nitropack_clear_cache( $division_type );

			// WP Rest clear cache
			$cache_obj->usd_wp_rest_clear_cache( $division_type );

		}elseif('Not Found' === $response ){
			//save error message to meta
			update_post_meta($post_id, $division_type.'_update_post_error', 'The post is deleted from the '.$division_type);
			
			$this->division_notice( $division_type, 'udpate_error' );

			//error log
			do_action( 'unitek_log', 'Update post error on '.$division_type.': The post is permanently deleted from '.$division_type.'. Post ID: '.$post_id.', author: '.get_the_author_meta('display_name', $post_author), 'alert' );

		}elseif('Created' === $response ){

			$created_post = json_decode( wp_remote_retrieve_body( $request ) );
			$created_id = $created_post->id;
			//check if requst created post on division
			if( $created_id ){
				//save remote post ID
				update_post_meta( $post_id, $division_type .'_post_id', $created_id);
				
				//save transient to show notification on admin dashboard
				$this->division_notice( $division_type, 'publish_success' );

				//error log
				do_action( 'unitek_log', 'Post created successfully on '.$division_type.'. Post ID: '. $post_id .', created post ID: '. $created_id .', author: '.get_the_author_meta('display_name', $post_author), 'notice' );
				
				//udpate post status after creating post
				$this->update_post_status( $post, $created_id, $division_type );
				
				//upload feature image after creating post
				$this->upload_image( $post, $division_type );
				
				//Cloudflare clear cache
				$cache_obj->usd_cloudflare_clear_cache( $this->cl_zones,  $division_type );
				//Nitropack clear cache
				$cache_obj->usd_nitropack_clear_cache( $division_type );
				// WP Rest clear cache
				$cache_obj->usd_wp_rest_clear_cache( $division_type );

			}

		}else{
			//save error message to meta
			update_post_meta($post_id, $division_type.'_update_post_error', $response);
			
			$this->division_notice( $division_type, 'update_error' );

			//error log
			do_action( 'unitek_log', 'Update post error on '.$division_type.': '.$response.'. Post ID: '.$post_id.', author: '.get_the_author_meta('display_name', $post_author), 'alert' );
		}

	}

	/*
	 * Delete post
	 * 
	 * @param object $post
	 * 
	 * @since 1.0.0
	 * 
	 * return void
	 * 
	 * delete local and remote post at the same time
	 * */
	public function delete_post( $post, $division_type ){
		//check if post object is exist
		if( empty( $post ) ) return false;

		//check if division type is exist
		if( empty( $division_type ) ) return false;

		$post_ID = $post->ID;
		$post_author = $post->post_author;

		//remote post ID
		$remote_post_id = get_post_meta( $post_ID, $division_type.'_post_id', true );
		
		// check if remote post ID exist
		if( empty( $remote_post_id ) ){
			return false;
		}
		
		//endpoin for delete remote post
		$endpoint = $this->base_url ."wp-json/wp/v2/news/".$remote_post_id;
		$cache_obj = new Und_Cache_Api();
		//remote delete request
		$request = wp_remote_post(
			$endpoint,
			array(
				'method'      => 'DELETE',
				'headers' => array(
					'Authorization' => "BASIC ". $this->encoded
				)
			)
		);
		
		//check if requst has error
		if ( is_wp_error( $request ) ) {
			$error_message = 'Something went wrong: '.$request->get_error_message();
	
			//show notice on dashboard after deleting news
			$this->division_notice( $division_type, 'delete_news_error' );

			//error log
			do_action( 'unitek_log', 'Error in deleting post on '.$division_type.': '.$error_message.'. Post ID: '. $post_ID .', author: '.get_the_author_meta('display_name', $post_author), 'alert' );
			
			return false;
			
		}
		
		//show notice on dashboard after deleting news
		$this->division_notice( $division_type, 'delete_news_success' );
		
		//Cloudflare clear cache
		$cache_obj->usd_cloudflare_clear_cache( $this->cl_zones, $division_type );

		//Nitropack clear cache

		$cache_obj->usd_nitropack_clear_cache( $division_type );

		// WP Rest clear cache
		$cache_obj->usd_wp_rest_clear_cache( $division_type );

		//error log
		do_action( 'unitek_log', '_delete_success', 'Delete news on '.$division_type.' successfully. Post ID: '. $post_ID .', author: '.get_the_author_meta('display_name', $post_author), 'alert' );
	}
	
	/*
	 * Delete attachment
	 * 
	 * @param object $post 
	 * 
	 * @since 1.0.0
	 * 
	 * @return void
	 * 
	 * */
	public function delete_attachment($post_id, $division_type){
		//check if post ID exist
		if( empty( $post_id ) ){
			return false;
		}

		//check if division type is exist
		if( empty( $division_type ) ){
			return false;
		}
		
		//remote media ID
		$remote_media_id = get_post_meta( $post_id, $division_type.'_media_id', true );
		
		if( empty( $remote_media_id ) ){
			return false;
		}

		//author ID
		$author_id = get_post_field( 'post_author', $post_id );
		
		//Endpoint for delete attachment
		$endpoint = $this->base_url ."wp-json/wp/v2/media/".$remote_media_id;
		$cache_obj = new Und_Cache_Api();
		// Request for delete attachment on remote
		$request = wp_remote_post(
			$endpoint,
			array(
				'method'      => 'DELETE',
				'headers' => array(
					'Content-Type' => 'application/json',
					'Authorization' => "BASIC ". $this->encoded
				),
				'body' => json_encode(['force' => true])
			)
		);
		
		//check if request error
		if ( is_wp_error( $request ) ) {

			$error_message = 'Something went wrong: '.$request->get_error_message();
			
			//show notice on dashboard after deleting attachment
			$this->division_notice( $division_type, 'delete_media_error' );
			//error log
			do_action( 'unitek_log', 'Error in deleting media on '.$division_type.': '.$error_message.'. Attachment ID: '.$post_id.', author: '.get_the_author_meta('display_name', $author_id), 'alert' );
			
			return false;
			
		}
		
		//show notice on dashboard after deleting attachment
		$this->division_notice( $division_type, 'delete_media_success' );
		
		//Cloudflare clear cache
		$cache_obj->usd_cloudflare_clear_cache( $this->cl_zones,  $division_type );

		// Nitropack clear cache
		$cache_obj->usd_nitropack_clear_cache( $division_type );

		// WP Rest clear cache
		$cache_obj->usd_wp_rest_clear_cache( $division_type );

		//error log
		do_action( 'unitek_log', 'Error in deleting media on Deleted media on '.$division_type.' successfully. Attachment ID: '.$post_id.', author: '.get_the_author_meta('display_name', $author_id), 'notice' );
	}
	
}
