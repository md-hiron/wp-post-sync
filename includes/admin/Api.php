<?php
namespace UnitekSyncData;
/**
 * API
 *
 * @since  1.0.0
 * @package UnitekSyncData
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Und_Api{
	
	/**
	 *  constructor
	 *
	 * @return void
	 */
	public function __construct(){
        add_action( 'rest_api_init', [ $this, 'register_api' ] );
	}

    public function register_api(){

		/**
		 * Register api for get video by ID
		 */
		 register_rest_route(
			'unitek_divisions-core/v1', '/postby-division', array(
				'method'   => 'GET',
				'callback' => array( $this, 'get_post_by_division' ),
				'args'     => array(
					'division' => array(
						'required' => true,
						'validate_callback' => function( $param, $request, $key ){
							return is_string( $param );
						}
					)
				),
				'permission_callback' => function() {
					return true;
				}
			)
		);

    }


	/**
	 * get video oby ID
	 * 
	 * @param object
	 * 
	 * @since 1.0.0
	 * 
	 * @return JSON for REST API
	 */
	function get_post_by_divisions( $data ){

		$id = ! empty( $data->get_param('id') ) ? $data->get_param('id') : false;

		if( ! $id ) return false;

		$video_id = $id;
		$video_meta = get_post_meta( $video_id , 'video_meta', true );

		$vide_data = [
			'title' => get_the_title( $video_id ) ?? '',
			'link' => get_post_meta( $video_id, 'video_link', true ) ?? '',
			'description' => $video_meta['schema_markup']['description'] ?? '',
			'image' => get_post_meta( $video_id , 'video_thumb_link', true ) ?? '',
		];

		return new WP_REST_Response( array('response' => $vide_data ), 200 );
	}

	
}

new Und_Api();