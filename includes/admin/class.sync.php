<?php
/**
 * Sync All division post
 *
 * @version 1.0.0
 * @package Unitek sync data/ Sync class
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Sync post
 *
 * Sync WordPress news post to all devision
 *
 * @version 1.0.0
 */

class Sync_Post{
	
	public $credentials = [];
	public $division = [];

	public $pretend_loop;

	public $sync_running = false;

	public function __construct(){

		$this->pretend_loop = false;
		
		//remote post will be created using this hook
		add_action( 'transition_post_status', [$this,'create_post'], 12, 3 );
		 
		// Remote post update using this hook
		add_action( 'save_post_news', [ $this, 'unitek_update_post' ], 10, 3 );
		
		//remote post will be trashed using this hook
		add_action( 'trash_news', [$this,'unitek_delete_post'], 12, 3 );
		
		//remote meta will be updated using this hook
		add_action('wp_after_insert_post', [ $this, 'publish_post_meta' ], 10, 4);
		
		//remote attachment will be permanent delete with this hook
		add_action('delete_attachment', [$this, 'unitek_delete_attachment'], 10, 2);
		

		//admin notices
		add_action( 'admin_notices', [$this, 'publish_error'] );
		add_action( 'admin_notices', [$this, 'publish_success'] );
		
		add_action( 'admin_notices', [$this, 'media_error'] );
		add_action( 'admin_notices', [$this, 'media_success'] );
		
		add_action( 'admin_notices', [$this, 'update_post_error'] );
		add_action( 'admin_notices', [$this, 'update_post_success'] );
		
		add_action( 'admin_notices', [$this, 'delete_post_error'] );
		add_action( 'admin_notices', [$this, 'delete_post_success'] );
		
		add_action( 'admin_notices', [$this, 'delete_media_error'] );
		add_action( 'admin_notices', [$this, 'delete_media_success'] );

		$this->credentials = [
			'wp-test' => [
				'user' => 'hiron',
				'pass' => 'juuz kKU3 r2wQ 3CdB d9mt gBjZ',
				'host' => 'http://localhost/wp-test/'
			],
		];

		$this->divisions = [
			'wp-test'
		];

		
	}
	
	/*
	 * Create post for the first time
	 * 
	 * @param string  $new post status
	 * @param string $old post status
	 * @param object post object
	 * 
	 * @since 1.0.0
	 * 
	 * @return void
	 * */
	public function create_post($new_status, $old_status, $post){

		if ( ( $new_status == 'publish' ) && ( $old_status != 'publish' ) && ( $post->post_type == 'news' ) ) {

			$usd_credentials = $this->credentials;
			$usd_divisions = $this->divisions;

			if( is_array( $usd_divisions ) ){
				foreach( $usd_divisions as $division ){
					$division_sync = new Sync_EG_Post($usd_credentials[$division]['user'], $usd_credentials[$division]['pass'], $usd_credentials[$division]['host'], $usd_credentials[$division]['cl_zone']);
					$division_sync->add_post($post, $division);
				}
			}

			$this->sync_running = true;
			
		}


		
	}
	
	/*
	 * publish post meta
	 * 
	 * @param int $post_id
	 * @param object $post
	 * @param bool $update if the post is created for the first time or not
	 * @param object $post_before
	 * 
	 * @since 1.0.0
	 * 
	 * @return void
	 * 
	 * update post meta after inserted post
	 * */
	public function publish_post_meta( $post_id, $post, $update, $post_before ){
		$usd_credentials = $this->credentials;
		$usd_divisions = $this->divisions;

		if( is_array( $usd_divisions ) ){
			foreach( $usd_divisions as $division ){
				$division_sync = new Sync_EG_Post($usd_credentials[$division]['user'], $usd_credentials[$division]['pass'], $usd_credentials[$division]['host'], $usd_credentials[$division]['cl_zone']);
				$division_sync->update_post_meta( $post, $division );
			}
		}
		
	}
	

	/*
	 * Update post
	 * 
	 * @param int $post_id of updated post
	 * @param object $post
	 * @param bool $update check if post is created for the first time or not
	 * 
	 * @since 1.0.0
	 * 
	 * @return void
	 * 
	 * update post if the post is already exist
	 * */
	public function unitek_update_post($post_id, $post, $update){

		//prevent update while post is being creating
		if( $this->sync_running == true ){
			return false;
		}
		
		//check if $update is false
		if( $update == false ){
			return false;
		}
		
		// prevent infinite loop
		if( false == $this->pretend_loop ){

			$usd_credentials = $this->credentials;
			$usd_divisions = $this->divisions;
	
			if( is_array( $usd_divisions ) ){
				foreach( $usd_divisions as $division ){
					$division_sync = new Sync_EG_Post($usd_credentials[$division]['user'], $usd_credentials[$division]['pass'], $usd_credentials[$division]['host'], $usd_credentials[$division]['cl_zone']);
					$division_sync->update_post( $post_id, $post, $update, $division );

					//store the thumbnail ID that is created after uploading image on Eaglegate
					$current_thumbnail_id = get_post_meta($post_id, $division.'_current_thumbnail_id', true) ?? '';
					
					//store the latest feature image ID of post
					$updated_thumnail_id = get_post_meta($post_id, '_thumbnail_id', true) ?? '';

					// Upoload image only on eaglegate if new featured image is uploaded
					if( $current_thumbnail_id !== $updated_thumnail_id ){
						//test upload image
						$division_sync->upload_image($post, $division);
					}
				}
			}
			
		}
		
		//track after a loop finished
		$this->pretend_loop = true;
		
		
	}

	/*
	 * Delete post
	 * 
	 * @param int $post_id of the deleted post
	 * @param object $post
	 * @param string $old_status of the deleted post
	 * 
	 * @since 1.0.0
	 * 
	 * @return void
	 * 
	 * Delete remote post after trashed local post
	 * */
	public function unitek_delete_post($post_id, $post, $old_status){
		// check if post ID is exist
		if( empty( $post_id ) ){
			return;
		}

		$usd_credentials = $this->credentials;
		$usd_divisions = $this->divisions;

		if( is_array( $usd_divisions ) ){
			foreach( $usd_divisions as $division ){
				$division_sync = new Sync_EG_Post( $usd_credentials[$division]['user'], $usd_credentials[$division]['pass'], $usd_credentials[$division]['host'], $usd_credentials[$division]['cl_zone']);
				$division_sync->delete_post( $post, $division );
			}
		}
		
	}
	
	/*
	 * Delete attachment
	 * 
	 * @param int $attachment_id that will be deleted
	 * @param object $post object that will be deleted
	 * 
	 * @since 1.0.0
	 * @return void
	 * 
	 * Delete remote attachment after deleting local attachment
	 * */
	public function unitek_delete_attachment( $attachment_id, $post ){
		//check if attachment ID is not empty
		if( empty( $attachment_id ) ){
			return;
		}

		$usd_credentials = $this->credentials;
		$usd_divisions = $this->divisions;

		if( is_array( $usd_divisions ) ){
			foreach( $usd_divisions as $division ){
				$division_sync = new Sync_EG_Post($usd_credentials[$division]['user'], $usd_credentials[$division]['pass'], $usd_credentials[$division]['host'], $usd_credentials[$division]['cl_zone']);
				$division_sync->delete_attachment( $attachment_id, $division );
			}
		}
		
		
	}

	public function show_notice( $transient_key, $message ){
		$publish_notice  = get_transient( $transient_key );

		if ( $publish_notice ) {
		?>
			<div class="notice notice-success is-dismissible">
				<p>
					<?php 
						_e($message, 'ubf');
						
						$last_division = end( $publish_notice );
						foreach( $publish_notice as $division ){
							
							if( $division != $last_division ){
								_e( $division.', ', 'ubf');
							}else{
								_e( $division.'.', 'ubf');
							}
						}
					?>
				</p>
			</div>
		<?php
			delete_transient( $transient_key );
		}
	}
	

	/*
	 * Show notice if sync success after deleting post
	 * */
	function delete_post_success(){
		
		$this->show_notice('delete_news_success', 'News deleted successfully on: ');
		
	}
	
	/*
	 * Show notice if sync has error during publishing post
	 * */
	public function publish_error(){

		$this->show_notice('publish_error', 'News publish failed on: ');

	}

	/*
	 * Show notice if sync success after publishing post
	 * */
	public function publish_success(){

		$this->show_notice('publish_success', 'News published successfully on: ');
		
	}

	/*
	 * Show notice if sync has error during media upload
	 * */
	
	public function media_error(){

		$this->show_notice('media_error', 'Media uploading failed on: ');
		
	}

	/*
	 * Show notice if sync success after media upload
	 * */
	public function media_success(){

		$this->show_notice('media_success', 'Media uploaded successfully on: ');
		
	}
	
	/*
	 * Show notice if sync has error during updating
	 * */
	function update_post_error(){
		
		$this->show_notice('update_error', 'News update error on: ');
		
	}

	/*
	 * Show notice if sync success after updating post
	 * */
	function update_post_success(){

		$this->show_notice('update_success', 'News updated successfully on: ');
	}

	/*
	 * Show notice if sync has error during deleting post
	 * */
	function delete_post_error(){

		$this->show_notice('delete_news_error', 'News delete failed on: ');
	}

	
	
	/*
	 * Show notice if sync has error during deleting media
	 * */
	function delete_media_error(){

		$this->show_notice('delete_media_error', 'Media delete failed on: ');
	}
	
	/*
	 * Show notice if sync success after deleting media
	 * */
	function delete_media_success(){

		$this->show_notice('delete_media_success', 'Media deleted successfully on: ');
	}
}

new Sync_Post();