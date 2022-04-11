<?php
/**
 * Pluginbazar SDK Client
 */

namespace Pluginbazar;

use WP_Error;

/**
 * Class Settings
 *
 * @package Pluginbazar
 */
class Settings {

	private $data = array();
	private $options = array();


	/**
	 * @var Client null
	 */
	private $client = null;


	/**
	 * Settings constructor.
	 */
	function __construct( Client $client ) {
		$this->client = $client;

		require_once __DIR__ . '/class-settings-option.php';

		add_action( 'admin_enqueue_scripts', array( $this, 'register_scripts' ) );
	}


	/**
	 * Create settings menu
	 *
	 * @param $args
	 *
	 * @return void
	 */
	public function create_menu( $args = array() ) {

		$this->data = &$args;

		if ( $this->add_in_menu() ) {
			add_action( 'admin_menu', array( $this, 'add_menu_in_admin_menu' ), 12 );
		}

		$this->set_options();

		add_filter( 'allowed_options', array( $this, 'allowed_options' ), 99, 1 );
		add_action( 'admin_init', array( $this, 'display_fields' ), 12 );
	}


	/**
	 * Generate Field - Gallery
	 *
	 * @param Option $option
	 */
	function generate_gallery( $option ) {

		wp_enqueue_media();
		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'jquery-ui-sortable' );

		$html = "";

		foreach ( (array) $option->get_value( array() ) as $attachment_id ) {

			$media_url = wp_get_attachment_url( $attachment_id );

			if ( $media_url ) {
				$html .= "<div><span onclick='this.parentElement.remove()' class='dashicons dashicons-trash'></span><img src='{$media_url}' />";
				$html .= "<input type='hidden' name='{$option->id}[]' value='{$attachment_id}'/>";
				$html .= "</div>";
			}
		}

		printf( '<div id="media_preview_%s">%s</div>', $option->field_id, $html );
		printf( '<div class="button" %s id="media_upload_%s">%s</div>', $option->get_is_disabled(), $option->field_id, esc_html__( 'Select Images' ) );

		?>
        <script>
            jQuery(document).ready(function ($) {

                $('#media_upload_<?php echo esc_attr( $option->field_id ); ?>').click(function () {
                    let send_attachment_bkp = wp.media.editor.send.attachment;
                    wp.media.editor.send.attachment = function (props, attachment) {
                        let html;

                        html = "<div><span onclick='this.parentElement.remove()' class='dashicons dashicons-trash'></span><img src='" + attachment.url + "' />";
                        html += "<input type='hidden' name='<?php echo esc_attr( $option->field_id ); ?>[]' value='" + attachment.id + "'/>";
                        html += "</div>";

                        $('#media_preview_<?php echo esc_attr( $option->field_id ); ?>').append(html);
                    }
                    wp.media.editor.open($(this));
                    wp.media.multiple = false;
                    return false;
                });

                $(function () {
                    $('#media_preview_<?php echo esc_attr( $option->field_id ); ?>').sortable({
                        handle: 'img',
                        revert: false,
                        axis: "x",
                    });
                });
            });
        </script>
        <style>
            #media_preview_<?php echo esc_attr( $option->field_id ); ?> > div {
                display: inline-block;
                vertical-align: top;
                width: 180px;
                border: 1px solid #ddd;
                padding: 12px;
                margin: 0 10px 10px 0;
                border-radius: 4px;
                position: relative;
            }

            #media_preview_<?php echo esc_attr( $option->field_id ); ?> > div:hover span {
                display: block;
            }

            #media_preview_<?php echo esc_attr( $option->field_id ); ?> > div > span {
                display: none;
                cursor: pointer;
                background: #ddd;
                padding: 2px;
                position: absolute;
                top: 0px;
                left: 0;
                font-size: 16px;
                border-bottom-right-radius: 4px;
                color: #f443369c;
            }

            #media_preview_<?php echo esc_attr( $option->field_id ); ?> > div > img {
                width: 100%;
                cursor: move;
            }
        </style>
		<?php
	}


	/**
	 * Generate Field - Media
	 *
	 * @param Option $option
	 */
	function generate_media( $option ) {

		$value       = $option->get_value();
		$media_title = get_the_title( $value );
		$media_url   = wp_get_attachment_url( $value );
		$media_ext   = pathinfo( $media_url, PATHINFO_EXTENSION );

		wp_enqueue_media();

		?>
        <div class="media_preview" style="width: 150px;margin-bottom: 10px;background: #d2d2d2;padding: 15px 5px;text-align: center;border-radius: 5px;">

			<?php if ( in_array( $media_ext, array( 'mp3', 'wav' ) ) ) : ?>

                <div id="media_preview_<?php echo esc_attr( $option->field_id ); ?>"
                     class="dashicons dashicons-format-audio" style="font-size: 70px;display: inline;"></div>

			<?php else : ?>
                <img id="media_preview_<?php echo esc_attr( $option->field_id ); ?>"
                     src="<?php echo esc_url( $media_url ); ?>" style="width:100%" alt="<?php echo esc_attr( $media_title ); ?>"/>
			<?php endif; ?>

        </div>
        <input type="hidden" name="<?php echo esc_attr( $option->id ); ?>"
               id="media_input_<?php echo esc_attr( $option->field_id ); ?>" value="<?php echo esc_attr( $value ); ?>"/>
        <div class="button" <?php echo esc_attr( $option->get_is_disabled() ); ?>
             id="media_upload_<?php echo esc_attr( $option->field_id ); ?>"><?php esc_html_e( 'Upload' ); ?></div>

		<?php if ( ! empty( $value ) ) : ?>
            <div class="button button-primary"
                 id="media_upload_<?php echo esc_attr( $option->field_id ); ?>_remove"><?php esc_html_e( 'Remove' ); ?></div>
		<?php endif; ?>

        <script>
            jQuery(document).ready(function ($) {

                $(document).on('click', '#media_upload_<?php echo esc_attr( $option->field_id ); ?>', function () {
                    var send_attachment_bkp = wp.media.editor.send.attachment;
                    wp.media.editor.send.attachment = function (props, attachment) {
                        $("#media_preview_<?php echo esc_attr( $option->field_id ); ?>").attr('src', attachment.url);
                        $("#media_input_<?php echo esc_attr( $option->field_id ); ?>").val(attachment.id);
                        wp.media.editor.send.attachment = send_attachment_bkp;

                        $(document.body).trigger('wpsettings-attachment-loaded', [attachment, props]);
                    };
                    wp.media.editor.open($(this));

                    return false;
                });

                $(document).on('click', '#media_upload_<?php echo esc_attr( $option->field_id ); ?>_remove', function () {

                    $(this).parent().find('.media_preview img').attr('src', '');
                    $(this).parent().find('#media_input_<?php echo esc_attr( $option->field_id ); ?>').val('');

                    $(document.body).trigger('wpsettings-attachment-removed', [$(this)]);
                });
            });
        </script>
		<?php
	}


	/**
	 * Generate Field - Range
	 *
	 * @param Option $option
	 */
	function generate_range( $option ) {

		// Range field
		printf( '<input class="pb-range" %1$s %2$s type="range" name="%3$s" id="%4$s" placeholder="%5$s" value="%6$s" min="%7$s" max="%8$s"/>',
			$option->get_is_disabled(), $option->get_is_required(), $option->id, $option->field_id, $option->placeholder, $option->get_value(), $option->min, $option->max
		);

		// Range value
		printf( '<span class="pb-show-value" id="%s-show-value">%s</span>', $option->field_id, $option->get_value() );

		// Range Style
		printf( '<style>
            .pb-range {
                -webkit-appearance: none;
                width: 280px;
                height: 7px;
                background: #9a9a9a;
                outline: none;
            }

            .pb-show-value {
                font-size: 17px;
                margin-left: 8px;
            }

            .pb-range::-webkit-slider-thumb {
                -webkit-appearance: none;
                appearance: none;
                width: 25px;
                height: 25px;
                border-radius: 50px;
                background: #138E77;
                cursor: pointer;
            }

            .pb-range::-moz-range-thumb {
                width: 25px;
                height: 25px;
                border-radius: 50px;
                background: #138E77;
                cursor: pointer;
            }
        </style>' );

		// Range script
		printf( '<script>
                jQuery(document).ready(function ($) {
                    $("#%1$s").on("input", function() {
                        $("#%1$s-show-value").html( $("#%1$s").val() );
                    });
                });
            </script>',
			$option->field_id
		);
	}


	/**
	 * Generate Field - Datepicker
	 *
	 * @param Option $option
	 */
	function generate_datepicker( $option ) {

		wp_enqueue_style( 'jquery-ui' );
		wp_enqueue_script( 'jquery-ui-datepicker' );

		printf( '<input %1$s %2$s type="text" name="%3$s" id="%4$s" placeholder="%5$s" value="%6$s"/>',
			$option->get_is_disabled(), $option->get_is_required(), $option->field_name, $option->field_id, $option->placeholder, $option->get_value()
		);

		printf( '<script>
                jQuery(document).ready(function ($) {
                    $("#%s").datepicker(%s)
                });
            </script>',
			$option->field_id, $option->field_options
		);
	}


	/**
	 * Generate Field - TimePicker
	 *
	 * @param Option $option
	 */
	function generate_timepicker( $option ) {

		wp_enqueue_style( 'jquery-ui-timepicker' );
		wp_enqueue_script( 'jquery-ui-timepicker' );

		printf( '<input %1$s %2$s type="text" name="%3$s" id="%4$s" placeholder="%5$s" value="%6$s"/>',
			$option->get_is_disabled(), $option->get_is_required(), $option->field_name, $option->field_id, $option->placeholder, $option->get_value()
		);

		printf( '<script>
                jQuery(document).ready(function ($) {
                    $("#%s").timepicker(%s)
                });
            </script>',
			$option->field_id, $option->field_options
		);
	}


	/**
	 * Generate Field - wp_editor
	 *
	 * @param Option $option
	 */
	function generate_wp_editor( $option ) {

		wp_editor( $option->get_value(), $option->id, $option->field_options );

		printf( '<style>
               #wp-content-editor-tools {
                background-color: #fff;
                padding-top: 0;
            }
        </style>' );
	}


	/**
	 * Generate Field - Color Picker
	 *
	 * @param Option $option
	 */
	function generate_colorpicker( $option ) {

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );

		printf( '<input %1$s %2$s type="text" name="%3$s" id="%4$s" placeholder="%5$s" value="%6$s"/>',
			$option->get_is_disabled(), $option->get_is_required(), $option->field_name, $option->field_id, $option->placeholder, $option->get_value()
		);

		?>
        <style>
            .wp-picker-container .iris-picker {
                width: 240px !important;
                height: 228px !important;
                border-radius: 5px;
            }

            .iris-picker .iris-square {
                margin-right: 3%;
            }
        </style>
        <script>
            jQuery(document).ready(function ($) {
                $('#<?php echo esc_attr( $option->field_id ); ?>').wpColorPicker();

				<?php if( $option->disabled ) : ?>
                $('#<?php echo esc_attr( $option->field_id ); ?>').parent().parent().parent().find('button.wp-color-result').prop('disabled', true);
				<?php endif; ?>
            });
        </script>
		<?php
	}


	/**
	 * Generate Field - Text
	 *
	 * @param Option $option
	 */
	function generate_text( $option ) {

		printf( '<input %1$s %2$s type="text" name="%3$s" id="%4$s" placeholder="%5$s" value="%6$s"/>',
			$option->get_is_disabled(), $option->get_is_required(), $option->field_name, $option->field_id, $option->placeholder, $option->get_value()
		);
	}


	/**
	 * Generate Field - Email
	 *
	 * @param Option $option
	 */
	function generate_email( $option ) {

		printf( '<input %1$s %2$s type="email" name="%3$s" id="%4$s" placeholder="%5$s" value="%6$s"/>',
			$option->get_is_disabled(), $option->get_is_required(), $option->field_name, $option->field_id, $option->placeholder, $option->get_value()
		);
	}


	/**
	 * Generate Field - Number
	 *
	 * @param Option $option
	 */
	function generate_number( $option ) {

		printf( '<input %1$s %2$s type="number" name="%3$s" id="%4$s" placeholder="%5$s" value="%6$s"/>',
			$option->get_is_disabled(), $option->get_is_required(), $option->field_name, $option->field_id, $option->placeholder, $option->get_value()
		);
	}


	/**
	 * Generate Field - Textarea
	 *
	 * @param Option $option
	 */
	function generate_textarea( $option ) {

		printf( '<textarea cols="%1$s" rows="%2$s" %3$s %4$s name="%5$s" id="%6$s" placeholder="%7$s">%8$s</textarea>',
			$option->cols, $option->rows, $option->get_is_disabled(), $option->get_is_required(), $option->field_name, $option->field_id, $option->placeholder, $option->get_value()
		);
	}


	/**
	 * Generate Field - Select
	 *
	 * @param Option $option
	 */
	function generate_select( $option ) {

		$value   = $option->get_value();
		$items[] = sprintf( '<option value="">%s</option>', $this->client->__trans( 'Select your choice' ) );

		foreach ( $option->args as $key => $name ) {

			if ( is_array( $value ) ) {
				$selected = in_array( $key, $value ) ? 'selected' : '';
			} else {
				$selected = $value == $key ? 'selected' : '';
			}

			$items[] = sprintf( '<option %s value="%s">%s</option>', $selected, $key, $name );
		}

		printf( '<select name="%1$s" id="%2$s" %3$s %4$s %5$s>%6$s</select>',
			$option->field_name, $option->field_id, $option->get_is_disabled(), $option->get_is_required(), $option->get_is_multiple(), implode( ' ', $items )
		);
	}


	/**
	 * Generate Select 2
	 *
	 * @param Option $option
	 */
	function generate_select2( $option ) {

		wp_enqueue_style( 'select2' );
		wp_enqueue_script( 'select2' );

		$value   = $option->get_value();
		$items[] = sprintf( '<option value="">%s</option>', $this->client->__trans( 'Select your choice' ) );

		foreach ( $option->args as $key => $name ) {

			if ( is_array( $value ) ) {
				$selected = in_array( $key, $value ) ? 'selected' : '';
			} else {
				$selected = $value == $key ? 'selected' : '';
			}

			$items[] = sprintf( '<option %s value="%s">%s</option>', $selected, $key, $name );
		}

		printf( '<select name="%1$s" id="%2$s" %3$s %4$s %5$s>%6$s</select>',
			$option->field_name, $option->field_id, $option->get_is_disabled(), $option->get_is_required(), $option->get_is_multiple(), implode( '<br>', $items )
		);

		printf( '<script>
                jQuery(document).ready(function ($) {
                    $("#%s").select2(%s)
                });
            </script>',
			$option->field_id, $option->field_options
		);
	}


	/**
	 * Generate Field - Checkbox
	 *
	 * @param Option $option
	 */
	function generate_checkbox( $option ) {

		$items = array();
		$value = $option->get_value( array() );

		foreach ( $option->args as $key => $label ) {

			$checked = is_array( $value ) && in_array( $key, $value ) ? "checked" : "";
			$items[] = sprintf( '<label for="%2$s-%3$s"><input %4$s %5$s type="checkbox" id="%2$s-%3$s" name="%1$s[]" value="%3$s">%6$s</label>',
				$option->id, $option->field_id, $key, $option->get_is_disabled(), $checked, $label
			);
		}

		printf( '<fieldset>%s</fieldset>', implode( '<br>', $items ) );
	}


	/**
	 * Generate Field - Radio
	 *
	 * @param Option $option
	 */
	function generate_radio( $option ) {

		$items = array();
		$value = $option->get_value( array() );

		foreach ( $option->args as $key => $label ) {

			$checked = is_array( $value ) && in_array( $key, $value ) ? "checked" : "";
			$items[] = sprintf( '<label for="%2$s-%3$s"><input %4$s %5$s type="radio" id="%2$s-%3$s" name="%1$s[]" value="%2$s">%6$s</label>',
				$option->id, $option->field_id, $key, $option->get_is_disabled(), $checked, $label
			);
		}

		printf( '<fieldset>%s</fieldset>', implode( '<br>', $items ) );
	}


	/**
	 * Generate Image Select Field
	 *
	 * @param Option $option
	 */
	function generate_image_select( $option ) {

		$input_type = $option->multiple ? 'checkbox' : 'radio';
		$value      = $option->get_value( array() );

		?>
        <div class="image-select">
			<?php
			foreach ( $option->args as $key => $val ) {
				$checked = is_array( $value ) && in_array( $key, $value ) ? "checked" : "";
				printf( '<label class="%2$s"><input %1$s %2$s type="%6$s" name="%3$s[]" value="%4$s"><img src="%5$s" /></label>',
					$option->disabled, $checked, $option->id, $key, $val, $input_type
				);
			}
			?>
        </div>

		<?php if ( ! in_array( 'image_select', $this->checked ) ) : ?>
            <style>
                .image-select > label {
                    display: inline-block;
                    width: 120px;
                    margin: 0 15px 15px 0;
                    position: relative;
                    border: 1px solid #d1d1d1;
                    border-radius: 5px;
                }

                .image-select > label.checked:after {
                    content: '✔';
                    position: absolute;
                    width: 30px;
                    height: 30px;
                    background: #4CAF50;
                    color: #fff;
                    top: -10px;
                    right: -10px;
                    border-radius: 50%;
                    text-align: center;
                    line-height: 30px;
                }

                .image-select > label > input[type="radio"],
                .image-select > label > input[type="checkbox"] {
                    display: none;
                }

                .image-select > label > img {
                    width: 100%;
                    transition: 0.3s;
                    border-radius: 5px;
                }

                .image-select > label.checked > img {
                    opacity: 0.7;
                    border-radius: 5px;
                }
            </style>
            <script>
                jQuery(document).ready(function ($) {
                    $('.image-select > label > input').on('change', function () {
                        if ($(this).attr('type') === 'radio') {
                            $(this).parent().parent().find('> label').removeClass('checked');
                        }

                        if ($(this).is(":checked")) {
                            $(this).parent().addClass('checked');
                        } else {
                            $(this).parent().removeClass('checked');
                        }
                    });
                });
            </script>
		<?php
		endif;

		$this->checked[] = 'image_select';
	}


	/**
	 * Generate Settings Fields
	 *
	 * @param array $field_options
	 * @param bool|\WP_Post $post
	 * @param array $args
	 *
	 * @return void
	 */
	function generate_fields( $field_options = array(), $post = false, $args = array() ) {

		$this_post = get_post( $post );

		if ( ! is_array( $field_options ) || ( $post && ! $this_post instanceof \WP_Post ) ) {
			return;
		}

		foreach ( $field_options as $field_option ) {

			$option_id = Utils::get_args_option( 'id', $field_option );

			if ( $this_post ) {
				$field_option['value']   = isset( $this_post->$option_id ) ? $this_post->$option_id : $this->client->utils()->get_meta( $option_id, $this_post->ID );
				$field_option['post_id'] = $this_post->ID;
			}

			$this->field_generator( $field_option );
		}
	}


	/**
	 * Generate field automatically from $option
	 *
	 * @param $option_args
	 */
	function field_generator( $option_args ) {

		$option = new Option( $option_args );

		do_action( 'Pluginbazar/Settings/before_' . $option->id, $option );

		if ( method_exists( $this, 'generate_' . $option->type ) && is_callable( array( $this, 'generate_' . $option->type ) ) ) {

			if ( $option->is_external ) {

				ob_start();
				printf( '<label for="%s">%s</label>', $option->field_id, $option->title );
				printf( '<div class="pb-sdk-field-inline pb-sdk-field-inputs">%s</div>', call_user_func( array( $this, 'generate_' . $option->type ), $option ) );

				printf( '<div class="%s">%s</div>', $option->option_classes(), ob_get_clean() );
			} else {
				call_user_func( array( $this, 'generate_' . $option->type ), $option );
			}
		}

		if ( $option->disabled ) {
			printf( '<span class="disabled-notice" style="background: #ffe390eb;margin-left: 10px;padding: 5px 12px;font-size: 12px;border-radius: 3px;color: #717171;">%s</span>', $this->get_disabled_notice() );
		}

		do_action( 'Pluginbazar/Settings/before_option', $option );

		do_action( 'Pluginbazar/Settings/settings_' . $option->id, $option );

		if ( ! empty( $option->details ) ) {
			printf( '<p class="description">%s</p>', $option->details );
		}

		do_action( 'Pluginbazar/Settings/after_option', $option );

		do_action( 'Pluginbazar/Settings/after_' . $option->id, $option );
	}


	/**
	 * Display Settings Fields
	 */
	function display_fields() {

		foreach ( $this->get_settings_fields() as $section_key => $setting ) {

			add_settings_section( $section_key, isset( $setting['title'] ) ? $setting['title'] : "", array( $this, 'get_section_callback' ), $this->get_current_page() );

			foreach ( $setting['options'] as $option_args ) {

				$option_id    = Utils::get_args_option( 'id', $option_args );
				$option_title = Utils::get_args_option( 'title', $option_args );

				if ( ! empty( $option_id ) && ! empty( $option_title ) ) {
					add_settings_field( $option_id, $option_title, array( $this, 'field_generator' ), $this->get_current_page(), $section_key, $option_args );
				}
			}
		}
	}


	/**
	 * Section Callback
	 *
	 * @param $section
	 */
	function get_section_callback( $section ) {

		$data        = isset( $section['callback'][0]->data ) ? $section['callback'][0]->data : array();
		$description = isset( $data['pages'][ $this->get_current_page() ]['page_settings'][ $section['id'] ]['description'] ) ? $data['pages'][ $this->get_current_page() ]['page_settings'][ $section['id'] ]['description'] : "";

		echo $description;
	}


	/**
	 * Return settings fields for current page
	 *
	 * @param $settings_for_page
	 *
	 * @return array
	 */
	private function get_settings_fields( $settings_for_page = '' ) {

		$settings_for_page = empty( $settings_for_page ) ? $this->get_current_page() : $settings_for_page;

		return Utils::get_args_option( 'page_settings', $this->get_pages()[ $settings_for_page ], array() );
	}


	/**
	 * Return Current Page
	 *
	 * @param $ret
	 * @param $default
	 *
	 * @return false|int|string
	 */
	function get_current_page( $ret = '', $default = '' ) {

		$all_pages         = $this->get_pages();
		$page_keys         = array_keys( $all_pages );
		$default_tab       = ! empty( $all_pages ) ? reset( $page_keys ) : "";
		$current_page_name = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : $default_tab;

		if ( ! empty( $ret ) ) {
			$current_page = Utils::get_args_option( $current_page_name, $all_pages );

			return isset( $current_page[ $ret ] ) ? $current_page[ $ret ] : $default;
		}

		return $current_page_name;
	}


	/**
	 * Add new options to $allowed_options
	 *
	 * @param $allowed_options
	 *
	 * @return array
	 */
	function allowed_options( $allowed_options ) {

		foreach ( $this->get_pages() as $page_id => $page ) :
			foreach ( Utils::get_args_option( 'page_settings', $page, array() ) as $section ):
				foreach ( Utils::get_args_option( 'options', $section, array() ) as $option ):
					$option_id = Utils::get_args_option( 'id', $option );
					if ( ! empty( $option_id ) ) {
						$allowed_options[ $page_id ][] = $option_id;
					}
				endforeach;
			endforeach;
		endforeach;

		return $allowed_options;
	}


	/**
	 * Return All Settings HTML
	 *
	 * @return false|string
	 */
	function get_setting_fields_html() {

		ob_start();

		settings_fields( $this->get_current_page() );

		do_settings_sections( $this->get_current_page() );

		return ob_get_clean();
	}


	/**
	 * Return settings navigation tabs
	 */
	function get_settings_nav_tabs() {

		global $pagenow;

		parse_str( sanitize_text_field( $_SERVER['QUERY_STRING'] ), $nav_url_args );

		?>
        <nav class="nav-tab-wrapper">
			<?php
			foreach ( $this->get_pages() as $page_id => $page ) {

				$active              = $this->get_current_page() == $page_id ? 'nav-tab-active' : '';
				$nav_url_args['tab'] = $page_id;
				$nav_menu_url        = http_build_query( $nav_url_args );
				$page_nav            = isset( $page['page_nav'] ) ? $page['page_nav'] : '';

				printf( '<a href="%s?%s" class="nav-tab %s">%s</a>', $pagenow, $nav_menu_url, $active, $page_nav );
			}

			do_action( 'Pluginbazar/Settings/after_nav_tabs' );
			?>
        </nav>
		<?php
	}


	/**
	 * Display Settings Tab Page
	 */
	function display_function() {

		ob_start();

		printf( '<h2>%s - %s</h2><br>', $this->get( 'page_title', $this->client->plugin_name ), $this->get_current_page( 'page_nav' ) );

		settings_errors();

		do_action( 'Pluginbazar/Settings/before_setting_page_' . $this->get_current_page(), $this );

		$this->get_settings_nav_tabs();

		if ( $this->get_current_page( 'show_submit', true ) ) {
			printf( '<form class="pb-settings-form" action="options.php" method="post">%s%s</form>', $this->get_setting_fields_html(), get_submit_button() );
		} else {
			print( $this->get_setting_fields_html() );
		}

		do_action( 'Pluginbazar/Settings/after_setting_page_' . $this->get_current_page(), $this );

		printf( '<div class="wrap">%s</div>', ob_get_clean() );
	}


	/**
	 * Add Menu in WordPress Admin Menu
	 */
	function add_menu_in_admin_menu() {

		$menu_added = false;
		$menu_title = $this->get( 'menu_title' );

		if ( 'main_menu' == $this->get( 'menu_type', 'main_menu' ) ) {
			$menu_added = add_menu_page( $this->get( 'menu_name', $menu_title ), $menu_title, $this->get( 'capability' ), $this->get( 'menu_slug' ), array( $this, 'display_function' ), $this->get( 'menu_icon' ), $this->get( 'position' ) );
		}

		if ( 'sub_menu' == $this->get( 'menu_type', 'main_menu' ) ) {
			$menu_added = add_submenu_page( $this->get( 'parent_slug' ), $this->get( 'menu_name', $menu_title ), $menu_title, $this->get( 'capability' ), $this->get( 'menu_slug' ), array( $this, 'display_function' ) );
		}

		do_action( 'Pluginbazar/Settings/menu_added_' . $this->get( 'menu_slug' ), $menu_added );
	}


	/**
	 * Return Pages
	 *
	 * @return array|mixed
	 */
	private function get_pages() {

		$pages     = Utils::get_args_option( 'pages', $this->data, array() );
		$sorted    = array();
		$increment = 0;

		foreach ( $pages as $page_key => $page ) {

			$increment += 5;
			$priority  = isset( $page['priority'] ) ? $page['priority'] : $increment;

			$sorted[ $page_key ] = $priority;
		}
		array_multisort( $sorted, SORT_ASC, $pages );

		return $pages;
	}


	/**
	 * Set options from Data object
	 */
	private function set_options() {

		foreach ( $this->get_pages() as $page ):
			$setting_sections = isset( $page['page_settings'] ) ? $page['page_settings'] : array();
			foreach ( $setting_sections as $setting_section ):
				if ( isset( $setting_section['options'] ) ) {
					$this->options = array_merge( $this->options, $setting_section['options'] );
				}
			endforeach;
		endforeach;
	}


	/**
	 * Check whether to add in WordPress Admin menu or not
	 *
	 * @return bool
	 */
	private function add_in_menu() {
		return Utils::get_args_option( 'add_in_menu', $this->data, true );
	}


	/**
	 * Return disabled notice
	 *
	 * @return mixed|string
	 */
	function get_disabled_notice() {
		return Utils::get_args_option( 'disabled_notice', $this->data, $this->client->__trans( 'This option is disabled' ) );
	}


	/**
	 * Return WP Timezones as Array
	 *
	 * @return mixed
	 */
	static function get_user_roles_array() {

		if ( ! function_exists( 'get_editable_roles' ) ) {
			include_once ABSPATH . '/wp-admin/includes/user.php';
		}

		$user_roles = array();

		foreach ( get_editable_roles() as $role_key => $role ) {
			$user_roles[ $role_key ] = isset( $role['name'] ) ? $role['name'] : '';
		}

		return apply_filters( 'Pluginbazar/Settings/user_roles', $user_roles );
	}


	/**
	 * Return WP Timezones as Array
	 *
	 * @return mixed
	 */
	static function get_timezones_array() {

		$timezones = array();

		foreach ( timezone_identifiers_list() as $time_zone ) {
			$timezones[ $time_zone ] = str_replace( '/', ' > ', $time_zone );
		}

		return apply_filters( 'Pluginbazar/Settings/timezones', $timezones );
	}


	/**
	 * Return Posts as Array
	 *
	 * @param $string
	 * @param Option $option
	 *
	 * @return array | WP_Error
	 */
	static function get_posts_array( $string, $option ) {

		$arr_posts = array();

		preg_match_all( "/\%([^\]]*)\%/", $string, $matches );

		if ( isset( $matches[1][0] ) ) {
			$post_type = $matches[1][0];
		} else {
			$post_type = 'post';
		}

		if ( ! post_type_exists( $post_type ) ) {
			return new WP_Error( 'not_found', sprintf( 'Post type <strong>%s</strong> does not exists !', $post_type ) );
		}

		$wp_query       = $option->wp_query;
		$posts_per_page = isset( $wp_query['posts_per_page'] ) ? $option['posts_per_page'] : - 1;
		$wp_query       = array_merge( $wp_query, array( 'post_type' => $post_type, 'posts_per_page' => $posts_per_page, 'fields' => 'ids' ) );

		foreach ( get_posts( $wp_query ) as $post_id ) {
			$arr_posts[ $post_id ] = get_the_title( $post_id );
		}

		return apply_filters( 'Pluginbazar/Settings/posts', $arr_posts );
	}


	/**
	 * Get taxonomies as Array
	 *
	 * @param $string
	 * @param Option $option
	 *
	 * @return array|WP_Error
	 */
	static function get_taxonomies_array( $string, $option ) {

		$taxonomies = array();

		preg_match_all( "/\%([^\]]*)\%/", $string, $matches );

		if ( isset( $matches[1][0] ) ) {
			$taxonomy = $matches[1][0];
		} else {
			return new WP_Error( 'invalid_declaration', 'Invalid taxonomy declaration !' );
		}

		if ( ! taxonomy_exists( $taxonomy ) ) {
			return new WP_Error( 'not_found', sprintf( 'Taxonomy <strong>%s</strong> does not exists !', $taxonomy ) );
		}

		$terms = get_terms( $taxonomy, array(
			'hide_empty' => $option->hide_empty,
		) );

		foreach ( $terms as $term ) {
			$taxonomies[ $term->term_id ] = $term->name;
		}

		return apply_filters( 'Pluginbazar/Settings/taxonomies', $taxonomies );
	}


	/**
	 * Get users as Array
	 *
	 * @return mixed|void
	 */
	static function get_users_array() {

		$user_array = array();

		foreach ( get_users() as $user ) {
			$user_array[ $user->ID ] = $user->display_name;
		}

		return apply_filters( 'Pluginbazar/Settings/users', $user_array );
	}


	/**
	 * Get pages as Array
	 *
	 * @return mixed|void
	 */
	static function get_pages_array() {

		$pages_array = array();

		foreach ( get_pages() as $page ) {
			$pages_array[ $page->ID ] = $page->post_title;
		}

		return apply_filters( 'Pluginbazar/Settings/pages', $pages_array );
	}


	/**
	 * Generate and return arguments from string
	 *
	 * @param $string
	 * @param Option $option
	 *
	 * @return array|mixed|void
	 */
	static function generate_args_from_string( $string, $option ) {

		if ( strpos( $string, 'PAGES' ) !== false ) {
			return self::get_pages_array();
		}

		if ( strpos( $string, 'USERS' ) !== false ) {
			return self::get_users_array();
		}

		if ( strpos( $string, 'TAX_' ) !== false ) {
			$taxonomies = self::get_taxonomies_array( $string, $option );

			return is_wp_error( $taxonomies ) ? array() : $taxonomies;
		}

		if ( strpos( $string, 'POSTS_' ) !== false ) {
			$posts = self::get_posts_array( $string, $option );

			return is_wp_error( $posts ) ? array() : $posts;
		}

		if ( strpos( $string, 'TIME_ZONES' ) !== false ) {
			return self::get_timezones_array();
		}

		if ( strpos( $string, 'USER_ROLES' ) !== false ) {
			return self::get_user_roles_array();
		}

		return array();
	}


	/**
	 * Register styles and scripts
	 *
	 * @return void
	 */
	function register_scripts() {

		// jQuery UI
		wp_register_style( 'jquery-ui', plugins_url( '/assets/css/jquery-ui.min.css', __DIR__ ) );

		// Timepicker
		wp_register_style( 'jquery-ui-timepicker', plugins_url( '/assets/css/jquery.timepicker.min.css', __DIR__ ) );
		wp_register_script( 'jquery-ui-timepicker', plugins_url( '/assets/js/jquery.timepicker.min.js', __DIR__ ), array( 'jquery' ) );

		// Select2
		wp_register_style( 'select2', plugins_url( '/assets/css/select2.min.css', __DIR__ ) );
		wp_register_script( 'select2', plugins_url( '/assets/js/select2.min.js', __DIR__ ), array( 'jquery' ) );
	}


	/**
	 * Return data from $data array
	 *
	 * @param $key
	 * @param $default
	 *
	 * @return array|mixed|string
	 */
	protected function get( $key = '', $default = '' ) {
		return Utils::get_args_option( $key, $this->data, $default );
	}
}