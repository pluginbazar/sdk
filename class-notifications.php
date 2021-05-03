<?php
/**
 * Pluginbazar SDK Client
 */

namespace Pluginbazar;

/**
 * Class Notifications
 *
 * @package Pluginbazar
 */
class Notifications {

	protected $cache_key;
	protected $data;


	/**
	 * Notifications constructor.
	 */
	function __construct() {

		$this->cache_key = sprintf( '_%s_notifications_data', md5( Client::$_text_domain ) );
		$this->data      = $this->get_notification_data();

		add_action( 'admin_notices', array( $this, 'render_admin_notices' ) );
	}


	/**
	 * Render notification as notices
	 */
	function render_admin_notices() {
		Client::print_notice( $this->get_message(), 'info', false, $this->get_id() );
	}


	/**
	 * Return notification content
	 *
	 * @return mixed|string
	 */
	private function get_message() {
		return Client::get_parsed_string( Client::get_args_option( 'message', $this->data ) );
	}


	/**
	 * Return notification unique ID
	 *
	 * @return array|mixed|string
	 */
	private function get_id() {
		return Client::get_args_option( 'id', $this->data );
	}


	/**
	 * Get version information
	 */
	private function get_notification_data() {

		$notification_data = $this->get_cached_notification_data();

		if ( false === $notification_data ) {
			$notification_data = $this->get_latest_notification_data();
			$this->set_cached_notification_data( $notification_data );
		}

		if (
			( isset( $notification_data['version'] ) && empty( $notification_data['version'] ) ) ||
			( isset( $notification_data['version'] ) && version_compare( Client::$_plugin_version, $notification_data['version'], '=' ) )
		) {
			return $notification_data;
		}

		return array();
	}


	/**
	 * Get new data from server
	 *
	 * @return false|mixed
	 */
	private function get_latest_notification_data() {

		if ( ! is_wp_error( $data = Client::send_request( 'notifications/wp-poll' ) ) ) {
			return $data;
		}

		return false;
	}


	/**
	 * Set cached data
	 *
	 * @param $value
	 */
	private function set_cached_notification_data( $value ) {
		if ( $value ) {
			set_transient( $this->cache_key, $value, 24 * HOUR_IN_SECONDS );
		}
	}


	/**
	 * Get cached data
	 *
	 * @return mixed
	 */
	private function get_cached_notification_data() {
		return get_transient( $this->cache_key );
	}
}