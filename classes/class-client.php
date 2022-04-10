<?php
/**
 * Pluginbazar SDK Client
 *
 * @version 1.0.7
 * @author Pluginbazar
 */

namespace Pluginbazar;

/**
 * Class Client
 *
 * @package Pluginbazar
 */
class Client {

	public $integration_server = 'https://pluginbazar.com';
	public $plugin_name = null;
	public $text_domain = null;
	public $plugin_reference = null;
	public $plugin_version = null;

	/**
	 * @var \Pluginbazar\Settings
	 */
	private static $settings;


	/**
	 * @var \Pluginbazar\Notifications
	 */
	private static $notifications;


	/**
	 * Client constructor.
	 *
	 * @param $plugin_name
	 * @param $text_domain
	 * @param $plugin_reference
	 * @param $plugin_file
	 */
	function __construct( $plugin_name, $text_domain, $plugin_reference, $plugin_file ) {

		// Initialize variables
		$this->plugin_name      = $plugin_name;
		$this->text_domain      = $text_domain;
		$this->plugin_reference = $plugin_reference;
		$plugin_data            = get_plugin_data( $plugin_file );
		$this->plugin_version   = isset( $plugin_data['Version'] ) ? $plugin_data['Version'] : '';

		add_action( 'admin_init', array( $this, 'manage_permanent_dismissible' ) );
	}


	/**
	 * Return Settings class
	 *
	 * @return Settings
	 */
	public function settings() {

		if ( ! class_exists( __NAMESPACE__ . '\Settings' ) ) {
			require_once __DIR__ . '/class-settings.php';
		}

		if ( ! self::$settings ) {
			self::$settings = new Settings( $this );
		}

		return self::$settings;
	}


	/**
	 * Return Notifications class
	 *
	 * @return \Pluginbazar\Notifications
	 */
	public function notifications() {

		if ( ! class_exists( __NAMESPACE__ . '\Notifications' ) ) {
			require_once __DIR__ . '/class-notifications.php';
		}

		if ( ! self::$notifications ) {
			self::$notifications = new Notifications( $this );
		}

		return self::$notifications;
	}


	/**
	 * Manage permanent dismissible of any notice
	 */
	function manage_permanent_dismissible() {

		$query_args = wp_unslash( $_GET );

		if ( $this->get_args_option( 'pb_action', $query_args ) == 'permanent_dismissible' && ! empty( $id = $this->get_args_option( 'id', $query_args ) ) ) {

			// update value
			update_option( $this->get_notices_id( $id ), time() );

			// Removing query args
			unset( $query_args['pb_action'] );
			unset( $query_args['id'] );

			$redirect = parse_url( esc_url_raw( add_query_arg( $query_args, $this->get_website_url( sanitize_text_field( $_SERVER['REQUEST_URI'] ) ) ) ) );

			// Redirect
			wp_safe_redirect( esc_url_raw( add_query_arg( $query_args, $this->get_website_url( $redirect['path'] ) ) ) );
			exit;
		}
	}


	/**
	 * Send request to remote endpoint
	 *
	 * @param $route
	 * @param array $params
	 * @param false $is_post
	 * @param false $blocking
	 * @param false $return_associative
	 *
	 * @return array|mixed|\WP_Error
	 */
	public function send_request( $route, $params = array(), $is_post = false, $blocking = false, $return_associative = true ) {

		$url = trailingslashit( $this->integration_server ) . 'wp-json/data/' . $route;

		if ( $is_post ) {
			$response = wp_remote_post( $url, array(
				'timeout'     => 30,
				'redirection' => 5,
				'httpversion' => '1.0',
				'blocking'    => $blocking,
				'headers'     => array(
					'user-agent' => 'Pluginbazar/' . md5( esc_url( $this->get_website_url() ) ) . ';',
					'Accept'     => 'application/json',
				),
				'body'        => array_merge( $params, array( 'version' => $this->plugin_version ) ),
				'cookies'     => array(),
				'sslverify'   => false,
			) );
		} else {
			$response = wp_remote_get( $url, array( 'timeout' => 30, 'sslverify' => false ) );
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return json_decode( wp_remote_retrieve_body( $response ), $return_associative );
	}


	/**
	 * Print notices
	 *
	 * @param string $message
	 * @param string $type
	 * @param bool $is_dismissible
	 * @param bool $permanent_dismiss
	 */
	public function print_notice( $message = '', $type = 'success', $is_dismissible = true, $permanent_dismiss = false ) {

		if ( $permanent_dismiss && ! empty( get_option( $this->get_notices_id( $permanent_dismiss ) ) ) ) {
			return;
		}

		$is_dismissible = $is_dismissible ? 'is-dismissible' : '';
		$pb_dismissible = '';

		// Manage permanent dismissible
		if ( $permanent_dismiss ) {
			$is_dismissible = 'pb-is-dismissible';
			$pb_dismissible = sprintf( '<a href="%s" class="notice-dismiss"><span class="screen-reader-text">%s</span></a>',
				esc_url_raw( add_query_arg(
					array(
						'pb_action' => 'permanent_dismissible',
						'id'        => $permanent_dismiss
					), $this->get_website_url( sanitize_text_field( $_SERVER['REQUEST_URI'] ) )
				) ),
				esc_html__( 'Dismiss', $this->text_domain )
			);
		}

		if ( ! empty( $message ) ) {
			printf( '<div class="notice notice-%s %s">%s%s</div>', $type, $is_dismissible, $message, $pb_dismissible );
			?>
            <style>
                .pb-is-dismissible {
                    position: relative;
                }

                .notice-dismiss, .notice-dismiss:active, .notice-dismiss:focus {
                    top: 50%;
                    transform: translateY(-50%);
                    text-decoration: none;
                    outline: none;
                    box-shadow: none;
                }
            </style>
			<?php
		}
	}

	/**
	 * Register Shortcode
	 *
	 * @param string $shortcode
	 * @param string $callable_func
	 */
	function register_shortcode( $shortcode = '', $callable_func = '' ) {

		if ( empty( $shortcode ) || empty( $callable_func ) ) {
			return;
		}

		add_shortcode( $shortcode, $callable_func );
	}

	/**
	 * Register Taxonomy
	 *
	 * @param $tax_name
	 * @param $obj_type
	 * @param array $args
	 */
	function register_taxonomy( $tax_name, $obj_type, $args = array() ) {

		if ( taxonomy_exists( $tax_name ) ) {
			return;
		}

		$singular = $this->get_args_option( 'singular', $args, '' );
		$plural   = $this->get_args_option( 'plural', $args, '' );
		$labels   = $this->get_args_option( 'labels', $args, array() );

		$args = wp_parse_args( $args,
			array(
				'description'         => sprintf( $this->__trans( 'This is where you can create and manage %s.' ), $plural ),
				'public'              => true,
				'show_ui'             => true,
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'publicly_queryable'  => true,
				'exclude_from_search' => false,
				'hierarchical'        => false,
				'rewrite'             => true,
				'query_var'           => true,
				'show_in_nav_menus'   => true,
				'show_in_menu'        => true,
			)
		);

		$args['labels'] = wp_parse_args( $labels,
			array(
				'name'               => sprintf( $this->__trans( '%s' ), $plural ),
				'singular_name'      => $singular,
				'menu_name'          => $this->__trans( $singular ),
				'all_items'          => sprintf( $this->__trans( '%s' ), $plural ),
				'add_new'            => sprintf( $this->__trans( 'Add %s' ), $singular ),
				'add_new_item'       => sprintf( $this->__trans( 'Add %s' ), $singular ),
				'edit'               => $this->__trans( 'Edit' ),
				'edit_item'          => sprintf( $this->__trans( '%s Details' ), $singular ),
				'new_item'           => sprintf( $this->__trans( 'New %s' ), $singular ),
				'view'               => sprintf( $this->__trans( 'View %s' ), $singular ),
				'view_item'          => sprintf( $this->__trans( 'View %s' ), $singular ),
				'search_items'       => sprintf( $this->__trans( 'Search %s' ), $plural ),
				'not_found'          => sprintf( $this->__trans( 'No %s found' ), $plural ),
				'not_found_in_trash' => sprintf( $this->__trans( 'No %s found in trash' ), $plural ),
				'parent'             => sprintf( $this->__trans( 'Parent %s' ), $singular ),
			)
		);

		register_taxonomy( $tax_name, $obj_type, apply_filters( "pb_register_taxonomy_$tax_name", $args, $obj_type ) );
	}

	/**
	 * Register Post Type
	 *
	 * @param $post_type
	 * @param array $args
	 */
	function register_post_type( $post_type, $args = array() ) {

		if ( post_type_exists( $post_type ) ) {
			return;
		}

		$singular = $this->get_args_option( 'singular', $args, '' );
		$plural   = $this->get_args_option( 'plural', $args, '' );
		$labels   = $this->get_args_option( 'labels', $args, array() );

		$args = wp_parse_args( $args,
			array(
				'description'         => sprintf( $this->__trans( 'This is where you can create and manage %s.' ), $plural ),
				'public'              => true,
				'show_ui'             => true,
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'publicly_queryable'  => true,
				'exclude_from_search' => false,
				'hierarchical'        => false,
				'rewrite'             => true,
				'query_var'           => true,
				'supports'            => array( 'title', 'thumbnail', 'editor', 'author' ),
				'show_in_nav_menus'   => true,
				'show_in_menu'        => true,
				'menu_icon'           => '',
			)
		);

		$args['labels'] = wp_parse_args( $labels,
			array(
				'name'               => sprintf( $this->__trans( '%s' ), $plural ),
				'singular_name'      => $singular,
				'menu_name'          => $this->__trans( $singular ),
				'all_items'          => sprintf( $this->__trans( '%s' ), $plural ),
				'add_new'            => sprintf( $this->__trans( 'Add %s' ), $singular ),
				'add_new_item'       => sprintf( $this->__trans( 'Add %s' ), $singular ),
				'edit'               => $this->__trans( 'Edit' ),
				'edit_item'          => sprintf( $this->__trans( 'Edit %s' ), $singular ),
				'new_item'           => sprintf( $this->__trans( 'New %s' ), $singular ),
				'view'               => sprintf( $this->__trans( 'View %s' ), $singular ),
				'view_item'          => sprintf( $this->__trans( 'View %s' ), $singular ),
				'search_items'       => sprintf( $this->__trans( 'Search %s' ), $plural ),
				'not_found'          => sprintf( $this->__trans( 'No %s found' ), $plural ),
				'not_found_in_trash' => sprintf( $this->__trans( 'No %s found in trash' ), $plural ),
				'parent'             => sprintf( $this->__trans( 'Parent %s' ), $singular ),
			)
		);

		register_post_type( $post_type, apply_filters( "pb_register_post_type_$post_type", $args ) );
	}


	/**
	 * Return Arguments Value
	 *
	 * @param string $key
	 * @param string $default
	 * @param array $args
	 *
	 * @return mixed|string
	 */
	public function get_args_option( $key = '', $args = array(), $default = '' ) {

		$default = is_array( $default ) && empty( $default ) ? array() : $default;
		$default = ! is_array( $default ) && empty( $default ) ? '' : $default;
		$key     = empty( $key ) ? '' : $key;

		if ( isset( $args[ $key ] ) && ! empty( $args[ $key ] ) ) {
			return $args[ $key ];
		}

		return $default;
	}


	/**
	 * Return notices id with prefix
	 *
	 * @param $id
	 *
	 * @return string
	 */
	public function get_notices_id( $id ) {
		return $this->integration_server . $id;
	}


	/**
	 * Parsed string
	 *
	 * @param $string
	 *
	 * @return mixed|string
	 */
	public function get_parsed_string( $string ) {

		preg_match_all( '#\{(.*?)\}#', $string, $matches, PREG_SET_ORDER, 0 );

		foreach ( $matches as $match ) {

			$match_object = explode( '.', $match[1] );

			if ( isset( $match_object[0] ) ) {
				switch ( $match_object[0] ) {
					case 'user':
						global $current_user;
						$string = str_replace( $match[0], $current_user->{$match_object[1]}, $string );
						break;
				}
			}
		}

		return $string;
	}


	/**
	 * Return url of client website
	 *
	 * @param $path
	 *
	 * @return string|void
	 */
	public function get_website_url( $path = '' ) {

		if ( is_multisite() && isset( $_SERVER['SERVER_NAME'] ) ) {
			return sanitize_text_field( $_SERVER['SERVER_NAME'] ) . '/' . $path;
		}

		return site_url( $path );
	}


	/**
	 * Translate function _e()
	 */
	public function _etrans( $text ) {
		call_user_func( '_e', $text, $this->text_domain );
	}


	/**
	 * Translate function __()
	 */
	public function __trans( $text ) {
		return call_user_func( '__', $text, $this->text_domain );
	}


	/**
	 * Return Plugin Basename
	 *
	 * @return string
	 */
	public function basename() {
		return sprintf( '%1$s/%1$s.php', $this->text_domain );
	}
}