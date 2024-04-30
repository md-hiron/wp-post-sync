<?php
/**
 * Plugin Name: Unitek Sync Data
 * Description: This is the Sync Data Plugin!
 * Plugin URI: https://dynamicweblab.com/
 * Author: Maidul
 * Version: 1.0.0
 * Author URI: https://dynamicweblab.com/
 * Text Domain: ubf
 * Domain Path: /languages
 * 
 * @package UnitekDivisions
 */

defined('ABSPATH') || die();

/**
 * Defining plugin constants.
 *
 * @since 1.0.0
 */

 define( 'USD_FILE', __FILE__ );
 define( 'USD_PATH', __DIR__ );
 define( 'USD_URL', plugins_url( '', USD_FILE ) );
 define( 'USD_ASSETS', USD_URL . '/assets' );

/**
 * Plugin Main class
 *
 * @package UnitekSyncData
 */
final class Unitek_Sync_Data{

	/**
	 * Plugin version
	 *
	 * @var string
	 */
	const version = '1.0';

	public function __construct() {
		if ( ! defined( 'FS_METHOD' ) ) define( 'FS_METHOD', 'direct' );

		add_action( 'init', [ $this, 'load_textdomain' ] );
		add_action( 'plugins_loaded', [ $this, 'und_file_include' ] );
		add_action( 'wp_loaded', [ $this, 'load' ] );
		add_action('add_meta_boxes', [$this, 'add_sync_site_links'], 5 );
	}

    /**
	 * Load Textdomain
	 *
	 * Load plugin localization files.
	 *
	 * @access public
     * 
     * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'unitek_divisions', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );
	}

	/**
	 * Inisialize Plugin
	 *
	 * @access public
	 *
	 * @return void
	 */
	public function und_file_include() {

		require_once USD_PATH . '/includes/admin/class.log.php';
		require_once USD_PATH . '/includes/admin/class.cache-clear-api.php';
		require_once USD_PATH . '/includes/admin/Api.php';
		require_once USD_PATH . '/includes/admin/class.sync.php';
		require_once USD_PATH . '/includes/admin/class.sync.eg.php';
		
	}

	public function add_sync_site_links() {
		add_meta_box( 
			'unitek-sync-divisions', 
			__( 'Unitek sync divisions', 'unitek-sync-divisions' ), 
			[$this, 'unitek_sync_divisions'], 
			'news',
			'normal',
			'high'
		);
	}

	public function unitek_sync_divisions( $post ) {
		$current_id             = $post->ID;
		$post_slug 	            = $post->post_name;
		$brookline_publish      = get_post_meta( $current_id, 'brookline_post_id', true) ?? '';
		$eaglegate_publish      = get_post_meta( $current_id, 'eaglegate_post_id', true) ?? '';
		$provo_college_publish  = get_post_meta( $current_id, 'provo-college_post_id', true) ?? '';
		$unitek_college_publish = get_post_meta( $current_id, 'unitek-college_post_id', true) ?? '';
		$unitekemt_college_publish = get_post_meta( $current_id, 'unitekemt_post_id', true) ?? '';
		
		if(  $brookline_publish ) {
			$post_link = 'https://brooklinecollege.edu/blog/news/' . $post_slug;
			echo "<div><a href='{$post_link}' target='_blank'>Post on brookline >></a></div>";
		}

		if( $eaglegate_publish ) {
			$post_link = 'https://eaglegatecollege.edu/blog/news/' . $post_slug;
			echo "<div><a href='{$post_link}' target='_blank'>Post on eaglegate >></a></div>";
		}

		if( $provo_college_publish ){
			$post_link = 'https://provocollege.edu/blog/news/' . $post_slug;
			echo "<div><a href='{$post_link}' target='_blank'>Post on provo college >></a></div>";
		}

		if( $unitek_college_publish ){
			$post_link = 'https://unitekcollege.edu/blog/news/' . $post_slug;
			echo "<div><a href='{$post_link}' target='_blank'>Post on unitek college >></a></div>";
		}

		if( $unitekemt_college_publish ) {
			$post_link = 'https://www.unitekemt.com/blog/news/' . $post_slug;
			echo "<div><a href='{$post_link}' target='_blank'>Post on unitekemt college >></a></div>";
		}

	}

	 /**
	 * Perform various environment checks/initializations on wp_loaded
	 *
	 * @since 1.8
	 */
	public function load() {
		global $wp_query;

		$wp_upload_dir = wp_upload_dir();

		$debug = new Unitek_Log();
	
		$debug->init( $wp_upload_dir['basedir'] );

	}


}

new Unitek_Sync_Data();