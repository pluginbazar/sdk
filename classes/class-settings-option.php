<?php

namespace Pluginbazar;

class Option {


	/**
	 * @type string
	 * @var $id
	 */
	public $id;

	/**
	 * @type string
	 * @var $field_id
	 */
	public $field_id;

	/**
	 * @type string
	 * @var $field_name
	 */
	public $field_name;

	/**
	 * @type string
	 * @var $title
	 */
	public $title;

	/**
	 * @type string
	 * @var $details
	 */
	public $details;

	/**
	 * @type bool
	 * @var $multiple
	 */
	public $multiple;

	/**
	 * @type bool
	 * @var $disabled
	 */
	public $disabled;

	/**
	 * @type string
	 * @var $type
	 */
	public $type;

	/**
	 * @type array
	 * @var $args
	 */
	public $args;

	/**
	 * @type mixed
	 * @var $value
	 */
	public $value;

	/**
	 * @type mixed
	 * @var $default
	 */
	public $default;

	/**
	 * @type string
	 * @var $hide_empty
	 */
	public $hide_empty;

	/**
	 * @type array
	 * @var $wp_query
	 */
	public $wp_query = array();

	/**
	 * @type string
	 * @var $rows
	 */
	public $rows = 5;

	/**
	 * @type string
	 * @var $cols
	 */
	public $cols = 40;

	/**
	 * @type array
	 * @var $placeholder
	 */
	public $placeholder;

	/**
	 * @type array
	 * @var $field_options
	 */
	public $field_options;

	/**
	 * @type string
	 * @var $min
	 */
	public $min = 0;

	/**
	 * @type string
	 * @var $max
	 */
	public $max = 100;

	/**
	 * @type bool
	 * @var $is_external
	 */
	public $is_external = false;


	/**
	 * @param $option_data
	 */
	function __construct( $option_data = array() ) {

		if ( ! is_array( $option_data ) || empty( $option_data ) ) {
			return;
		}

		foreach ( $option_data as $key => $val ) {
			$this->$key = $val;
		}

		$this->args          = is_string( $this->args ) ? Settings::generate_args_from_string( $this->args, $this ) : $this->args;
		$this->field_id      = str_replace( array( '[', ']' ), '_', $this->id );
		$this->field_name    = $this->multiple ? $this->id . '[]' : $this->id;
		$this->field_options = preg_replace( '/"([^"]+)"\s*:\s*/', '$1:', json_encode( $this->field_options ) );
	}


	/**
	 * Print option field classes
	 *
	 * @param $classes
	 *
	 * @return string
	 */
	function option_classes( $classes = '' ) {

		// Added general class
		$classes[] = 'pb-sdk-field';

		// Added type to the class
		$classes[] = $this->type;

		$classes = apply_filters( 'Pluginbazar/Settings/Option/option_classes_arr', $classes );

		return apply_filters( 'Pluginbazar/Settings/Option/option_classes', implode( ' ', array_unique( $classes ) ) );
	}


	/**
	 * Get required attribute
	 *
	 * @return string
	 */
	function get_is_required() {
		return $this->disabled ? 'disabled="disabled"' : '';
	}


	/**
	 * Get disabled attribute
	 *
	 * @return string
	 */
	function get_is_disabled() {
		return $this->disabled ? 'disabled="disabled"' : '';
	}

	/**
	 * Get multiple attribute
	 *
	 * @return string
	 */
	function get_is_multiple() {
		return $this->multiple ? 'multiple' : '';
	}


	/**
	 * Return Option Value for Given Option ID
	 *
	 * @return bool|mixed|void
	 */
	function get_value( $default = '' ) {

		$option_value = get_option( $this->id, $this->default );
		$option_value = empty( $option_value ) ? $this->value : $option_value;
		$option_value = empty( $option_value ) ? $this->default : $option_value;
		$option_value = empty( $option_value ) ? $default : $option_value;

		return apply_filters( 'Pluginbazar/Settings/Option/value', $option_value, $this );
	}
}