<?php

global $wp_filesystem;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

/**
 * Class SearchWPDebug is responsible for various debugging operations
 */
class Unitek_Log{

	public $active;

	private $logfile;

    public function __construct() // or any other method
    {
		include_once ABSPATH . 'wp-admin/includes/file.php';
    }
	/**
	 * Return singleton instance of class
	 *
	 * @return MeritDSDebug
	 * @since 0.1.0
	 */
	public static function getInstance() {
		static $instance = false;

		if ( ! $instance  ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * @param $dir
	 */
	public function init( $dir ) {
		global $wp_filesystem;

		$this->active = true;
		$this->logfile = trailingslashit( $dir ) . 'unitek-debug.txt';

		// init environment
		if ( ! file_exists( $this->logfile ) ) {
			WP_Filesystem();
			if ( method_exists( $wp_filesystem, 'put_contents' ) ) {
				if ( ! $wp_filesystem->put_contents( $this->logfile, '' ) ) {
					$this->active = false;
				}
			}
		}

		// after determining whether we can write to the logfile, add our action
		if ( $this->active ) {
			add_action( 'unitek_log', array( $this, 'log' ), 10, 2 );
		}

		//add_action( 'sync_db_log', array( $this, 'sync_db_log' ), 10, 5 );
	}

	/**
	 * @param string $message
	 * @param string $type
	 *
	 * @return bool
	 */
	public function log( $message = '', $type = 'notice' ) {
		global $wp_filesystem;
		WP_Filesystem();

		// if we're not active, don't do anything
		if ( ! $this->active || ! file_exists( $this->logfile ) ) {
			return false;
		}

		if ( ! method_exists( $wp_filesystem, 'get_contents' ) ) {
			return false;
		}

		if ( ! method_exists( $wp_filesystem, 'put_contents' ) ) {
			return false;
		}

		// get the existing log
		$existing = $wp_filesystem->get_contents( $this->logfile );

		// format our entry
		$entry = '[' . date( 'Y-d-m G:i:s', current_time( 'timestamp' ) ) . ']';

		$entry .= '[' . sanitize_text_field( $type ) . ']';


		// flag it with the process ID
		// $pid = MeritDataSync::instance()->get_pid();
		// $entry .= '[' . substr( $pid, strlen( $pid ) - 5, strlen( $pid ) ) . ']';

		// sanitize the message
		$db_log = sanitize_textarea_field( esc_html( $message ) );
		$message = sanitize_textarea_field( esc_html( $message ) );
		$message = str_replace( '=&gt;', '=>', $message ); // put back array identifiers
		$message = str_replace( '-&gt;', '->', $message ); // put back property identifiers
		$message = str_replace( '&#039;', "'", $message ); // put back apostrophe's

		// finally append the message
		$entry .= ' ' . $message;

		// append the entry
		$log = $existing . "\n" . $entry;

		// write log
		$wp_filesystem->put_contents( $this->logfile, $log );

		return true;
	}

/**
 * Add log to db
 * @param  [type] $status     [Log status notice,error etc]
 * @param  [type] $type       [type of db log]
 * @param  [type] $start_time [script start time]
 * @param  [type] $end_time   [script end time]
 * @param  [type] $message    [Any additional info]
 * @return [type]             [description]
 */
public function sync_db_log($status, $type, $start_time = null, $end_time = null, $message = '')
{
	global $wpdb;
	//insert on Db
	$table_name = $wpdb->prefix . 'sync_log';

	if (null === $start_time) {
		$start_time = time();
	 }

	 if (null === $end_time) {
			 $end_time = time();
	 }

	// status: 200,500
	// type: aws_sync,aws_lookups,aws_ifu,aws_feed,aws_product,aws_catalog,product_summaries,merit_wp,mmxaws
	$wpdb->insert(
		$table_name,
		array(
			'entry_time' => current_time( 'mysql' ),
			'status' => $status,
			'type' => $type,
			'message' => $message,
			'start_time' => $start_time,
			'end_time' => $end_time,
		)
	);

	if(!$wpdb->insert_id){
		//error
	}

}

	/*
	 * Generates a readable, chronological call trace at this point in time
	 *
	 * @since 2.9.8
	 */
	function get_call_trace() {
		$e = new Exception();
		$trace = explode( "\n", $e->getTraceAsString() );

		// Reverse array to make steps line up chronologically
		$trace = array_reverse( $trace );
		array_shift( $trace ); // remove {main}
		array_pop( $trace ); // remove call to this method
		$length = count( $trace );
		$result = array();

		for ( $i = 0; $i < $length; $i++ ) {
			$result[] = substr( $trace[ $i ], strpos( $trace[ $i ], ' ' ) );
		}

		return $result;
	}

}
