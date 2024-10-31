<?php
/*
Plugin Name: PluginBuddy YOURLS
Plugin URI: http://pluginbuddy.com/free-wordpress-plugins/yourls/
Description: Allows you to insert a YOURL link into a post or page
Author: Ronald Huereca
Version: 1.0.0
Requires at least: 3.0
Author URI: http://www.ronalfy.com/
*/ 

if (!class_exists('pluginbuddy_yourls')) {
    class pluginbuddy_yourls	{	
		//private
		private $admin_options = array();
		private $plugin_url = '';
		private $plugin_dir = '';
		private $plugin_info = array();
		
		/**
		* __construct()
		* 
		* Class constructor
		*
		*/
		function __construct(){
			
			//Initialize plugin information
			$this->plugin_info = array(
				'slug' => 'pluginbuddy_yourls',
				'version' => '0.1.0',
				'name' => 'PluginBuddy YOURLS',
				'url' => 'http://pluginbuddy.com/free-wordpress-plugins/yourls/',
				'locale' => 'pb_yourls',
				'path' => plugin_basename( __FILE__ )
			);
			
			$this->admin_options = $this->get_admin_options();
			$this->plugin_url = rtrim( plugin_dir_url(__FILE__), '/' );
			$this->plugin_dir = rtrim( plugin_dir_path(__FILE__), '/' );
			
			add_action( 'init', array( &$this, 'init' ) );
			add_action( 'wp_ajax_pb_yourls_add', array( &$this, 'ajax_yourls' ) );
			add_action( 'wp_ajax_pb_yourls_get_link', array( &$this, 'ajax_get_link' ) );
			
		} //end constructor
		
		public function ajax_get_link() {
			check_ajax_referer( 'pb-yourls_get-link' );
			//Sanitize form data
			parse_str( $_POST['form_data'], $form_data );
			$url = trim( sanitize_text_field( $form_data[ 'url' ] ) );
			$text = trim( sanitize_text_field( $form_data[ 'text' ] ) );
			$keyword = trim( sanitize_text_field( $form_data[ 'keyword' ] ) );
			
			if ( empty( $url ) ) die( json_encode( array( 'error' => __( 'URL cannot be blank', $this->get_plugin_info( 'locale' ) ) ) ) );
			if ( empty( $text ) ) die( json_encode( array( 'error' => __( 'Text cannot be blank', $this->get_plugin_info( 'locale' ) ) ) ) );
			
			//Prepare the remote request
			$body = array(
				'format'   => 'json',
				'username' => $this->get_admin_option( 'username' ),
				'password' => $this->get_admin_option( 'password' ),
				'action'   => 'shorturl',
				'url' => $url
			);
			if ( !empty( $keyword ) ) {
				$body[ 'keyword' ] = $keyword;
			}
			//Get the short link			
			$params = array( 
				'body' => $body,
				'user-agent' => 'YOURLS http://yourls.org/'
			);
			
			$request = wp_remote_post( $this->get_admin_option( 'api_url' ), $params );
			if ( is_wp_error( $request ) ) {
				die( json_encode( array( 'error' => $request->get_error_message() ) ) );
			}				
			$body = json_decode( wp_remote_retrieve_body( $request ) );
			if ( !is_object( $body ) ) {
				die( json_encode( array( 'error' => __( 'Unable to retrieve short URL', $this->get_plugin_info( 'locale' ) ) ) ) );
			}
			if ( $body->status == 'fail' && !isset( $body->shorturl ) ) {
				die( json_encode( array( 'error' => $body->message ) ) );
			}
			
			if ( isset( $body->shorturl ) ) {
				//yay
				$return = sprintf( "<a href='%s'>%s</a>", $body->shorturl, $text );
				die( json_encode( array( 'link' => $return ) ) );
			} else {
				die( json_encode( array( 'error' => __( 'Unable to retrieve short URL', $this->get_plugin_info( 'locale' ) ) ) ) );
			}			
			exit;
		} //end ajax_get_link
		public function ajax_yourls() {
			?>
			<html>
				<head>
				<title><?php _e( 'Insert YOURLS Link', $this->get_plugin_info( 'locale' ) ); ?></title>
				<?php
				wp_enqueue_script( 'jquery' );
				wp_admin_css( 'global' );
				wp_admin_css();
				wp_admin_css( 'colors' );
				do_action('admin_print_styles');
				do_action('admin_print_scripts');
				do_action('admin_head');
				
				?>
				<script type="text/javascript">
				var ajaxurl = "<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>";
				jQuery( document ).ready(
					function( $ ) {
						//Get into Ajax format
						$( "#pb_yourls_form" ).bind( "submit", function() {
							var form_data = $( this ).serializeArray();
							var action = $( "#action" ).val();
							var nonce = $( "#_wpnonce" ).val();
							form_data = $.param(form_data);
							$.post( ajaxurl, { action: action, form_data: form_data, _ajax_nonce: nonce },
								function( response ){
									if ( typeof response.error != 'undefined' )  {
										$( "#form_error p" ).html( response.error );
										$( "#form_error" ).removeClass( "hidden" );
										return;
									} //end if error
									var win = parent.window.dialogArguments || parent.opener || parent.parent || parent.top;
									win.send_to_editor( response.link );		
								} //end ajax response
							, 'json' ); //end ajax
							return false;	
						} );
					}
				);
				</script>
				</head>
				<body>
				<?php
				if ( $this->get_admin_option( 'verified' ) != true ) {
					?>
					<div class='error'><p><strong><?php _e( 'Your YOURLS API credentials have not been verified', $this->get_plugin_info( 'locale' ) ); ?></strong></p></div>
					<?php
				} else {
				?>
				<div class='wrap'>
					<form id="pb_yourls_form" method='post' action='<?php esc_url( admin_url( 'admin-ajax.php' ) ); ?>'>
					<?php wp_nonce_field( 'pb-yourls_get-link' ) ?>
					<table class="form-table">
                                <tbody>
                                    <tr valign="top">
                                        <th scope="row"><label for='url'><?php _e( 'Long URL (Required)', $this->get_plugin_info( 'locale' ) ); ?></label></th>
                                        <td>
                                        <input type='text' size='30' name='url' id='url' value='' />                                       
                                        </td>
                                    </tr>
                                    <tr valign="top">
                                        <th scope="row"><label for='text'><?php _e( 'Text (Required)', $this->get_plugin_info( 'locale' ) ); ?></label></th>
                                        <td>
                                        <input type='text' size='30' name='text' id='text' value='' />                                       
                                        </td>
                                    </tr>
                                    <tr valign="top">
                                        <th scope="row"><label for='keyword'><?php _e( 'Custom Keyword (Optional)', $this->get_plugin_info( 'locale' ) ); ?></label></th>
                                        <td>
                                        <input type='text' size='30' name='keyword' id='keyword' value='' />                                       
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <div class="submit">
		                      <input class='button-primary' type="submit" name="update" value="<?php esc_attr_e('Insert Link', $this->get_plugin_info( 'locale' ) ) ?>" />
		                    </div><!--/.submit-->
		                    <input type='hidden' name='action' id='action' value='pb_yourls_get_link' />
		                    <div class='error hidden' id='form_error'><p></p></div>
		                    </form>

				</div><!--/wrap-->
				<?php
				} //end if verified
				?>
				</body>
			</html>
			<?php
			exit;
		} //end ajax_yourls
		
		/**
		* add_media_button()
		* 
		* Displays a thumbsup icon in the media bar when editing a post or page
		*
		* @param		string    $context	Media bar string
		* @return		string    Updated context variable with shortcode button added
		*/
		public function add_media_button( $context ) {
			if ( $this->get_admin_option( 'verified' ) != true ) return $context;
			
			$image_btn = $this->get_plugin_url( 'yourls.png' );
			
			$out = '<a href="' . esc_url( admin_url( 'admin-ajax.php' ) ) . '?action=pb_yourls_add&width=450&height=300&TB_iframe=true" class="thickbox" title="' . __("Add YOURS Link", $this->get_plugin_info( 'locale' ) ) . '"><img src="'.$image_btn.'" alt="' . __("Add YOURS Link", $this->get_plugin_info( 'locale' ) ) . '" /></a>';
			return $context . $out;
		} //end add_media_button
		
		/**
		* add_settings_page()
		*
		* Adds an options page to the admin panel area 
		*
		**/
		public function add_settings_page() {
			add_options_page( 'PluginBuddy YOURLS Settings', 'PluginBuddy YOURLS', 'manage_options', 'pb_yourls', array( &$this, 'output_settings' ) );
		} //end add_settings_page
		
		/**
		* get_admin_option()
		* 
		* Returns a localized admin option
		*
		* @param   string    $key    Admin Option Key to Retrieve
		* @return   mixed                The results of the admin key.  False if not present.
		*/
		public function get_admin_option( $key = '' ) {			
			$admin_options = $this->get_admin_options();
			if ( array_key_exists( $key, $admin_options ) ) {
				return $admin_options[ $key ];
			}
			return false;
		}
		
		/**
		* get_admin_options()
		* 
		* Initialize and return an array of all admin options
		*
		* @return   array					All Admin Options
		*/
		public function get_admin_options( ) {
			
			if (empty($this->admin_options)) {
				$admin_options = $this->get_plugin_defaults();
				
				$options = get_option( $this->get_plugin_info( 'slug' ) );
				if (!empty($options)) {
					foreach ($options as $key => $option) {
						if (array_key_exists($key, $admin_options)) {
							$admin_options[$key] = $option;
						}
					}
				}
				
				//Save the options
				$this->admin_options = $admin_options;
				$this->save_admin_options();								
			}
			return $this->admin_options;
		} //end get_admin_options
		
		/**
		* get_all_admin_options()
		* 
		* Returns an array of all admin options
		*
		* @return   array					All Admin Options
		*/
		public function get_all_admin_options() {
			return $this->admin_options;
		}
		
		/**
		* get_plugin_defaults()
		* 
		* Returns an array of default plugin options (to be stored in the options table)
		*
		* @return		array               Default plugin keys
		*/
		public function get_plugin_defaults() {
			if ( isset( $this->defaults ) ) {
				return $this->defaults;
			} else {
				$this->defaults = array(
					'api_url' => false,
					'username' => false,
					'password' => false,
					'verified' => false
				);
				return $this->defaults;
			}
		} //end get_plugin_defaults
		
		/**
		* get_plugin_dir()
		* 
		* Returns an absolute path to a plugin item
		*
		* @param		string    $path	Relative path to make absolute (e.g., /css/image.png)
		* @return		string               An absolute path (e.g., /htdocs/ithemes/wp-content/.../css/image.png)
		*/
		public function get_plugin_dir( $path = '' ) {
			$dir = $this->plugin_dir;
			if ( !empty( $path ) && is_string( $path) )
				$dir .= '/' . ltrim( $path, '/' );
			return $dir;		
		} //end get_plugin_dir
			
		/**
		* get_plugin_info()
		* 
		* Returns a localized plugin key
		*
		* @param   string    $key    Plugin Key to Retrieve
		* @return   mixed                The results of the plugin key.  False if not present.
		*/
		public function get_plugin_info( $key = '' ) {	
			if ( array_key_exists( $key, $this->plugin_info ) ) {
				return $this->plugin_info[ $key ];
			}
			return false;
		} //end get_plugin_info
		
		
		/**
		* get_plugin_url()
		* 
		* Returns an absolute url to a plugin item
		*
		* @param		string    $path	Relative path to plugin (e.g., /css/image.png)
		* @return		string               An absolute url (e.g., http://www.domain.com/plugin_url/.../css/image.png)
		*/
		public function get_plugin_url( $path = '' ) {
			$dir = $this->plugin_url;
			if ( !empty( $path ) && is_string( $path) )
				$dir .= '/' . ltrim( $path, '/' );
			return $dir;	
		} //get_plugin_url
		
		
		/**
		* init()
		* 
		* Initializes plugin localization, post types, updaters, plugin info, and adds actions/filters
		*
		*/
		function init() {		
			
			//* Localization Code */
			load_plugin_textdomain( $this->get_plugin_info( 'locale' ), false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );
			
			//Add plugin info
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
			$this->plugin_info = wp_parse_args( array_change_key_case( get_plugin_data( __FILE__, false, false ), CASE_LOWER ), $this->plugin_info );
			
			//Media bar
			add_action('media_buttons_context', array( &$this, 'add_media_button') );
			
			//Admin menu
			add_action( 'admin_menu', array( &$this, 'add_settings_page' ) );
			
			
		}//end function init
		
		public function output_settings() {
			if ( isset( $_POST[ 'update' ] ) ) {
				check_admin_referer( 'pb-yourls_save-settings' );
				$options = array();
				$options[ 'username' ] = sanitize_text_field( $_POST[ 'username' ] );
				$options[ 'password' ] = sanitize_text_field( $_POST[ 'password' ] );
				$options[ 'api_url' ] = sanitize_text_field( $_POST[ 'api_url' ] );
				$this->save_admin_options( $options );
				//Check YOURLS				
				$params = array( 
					'body' => array(
						'format'   => 'json',
						'username' => $options[ 'username' ],
						'password' => $options[ 'password' ],
						'action'   => 'stats'
					),
					'user-agent' => 'YOURLS http://yourls.org/'
				);
				$request = wp_remote_post( $options[ 'api_url' ], $params );
				$error = false;
				if ( is_wp_error( $request ) ) {
					$error = true;
				} else {
					$body = wp_remote_retrieve_body( $request );
					$body = json_decode( $body );
					if ( is_object( $body ) ) {
						if ( $body->statusCode != '200' ) $error = true;
					} else {
						$error = true;
					}
				}
				if ( $error ) {
					$this->save_admin_option( 'verified', false );
					?>
					<div class='error'><p><strong><?php _e( 'Unable to connect to your YOURLS API.  Please check your settings or try again later.', $this->get_plugin_info( 'locale' ) ); ?></strong></p></div>	
					<?php
				} else {
					$this->save_admin_option( 'verified', true );
					?>
					<div class='updated'><p><strong><?php _e( 'Settings Saved', $this->get_plugin_info( 'locale' ) ); ?></strong></p></div>	
					<?php
				}		
				
			} //end if $_POST['update']
			?>
			<div class="wrap">
				<h3><?php _e( "PluginBuddy YOURLS Settings", $this->get_plugin_info( 'locale' ) ); ?></h3>
                	 <form method="post" action="<?php echo esc_attr( $_SERVER["REQUEST_URI"] ); ?>">
					<?php wp_nonce_field( 'pb-yourls_save-settings' ) ?>
                	<table class="form-table">
                        <tbody>
                            <tr valign='top'>
                                <th scope="row"><label for='api_url'><?php _e( 'API URL', $this->get_plugin_info( 'locale' ) ); ?></label></th>
                                <td  valign='middle'>
                                	<p><?php _e( 'This is the URL to your YOURS API (example: http://site.com/yourls-api.php)', $this->get_plugin_info( 'locale' ) ); ?></p>
                                	<input type='text' size='30' id='api_url' name='api_url' value='<?php echo esc_attr( $this->get_admin_option( 'api_url' ) ); ?>' />
                                </td>
                            </tr>
                            <tr valign='top'>
                                <th scope="row"><label for='username'><?php _e( 'YOURLS Login (Username)', $this->get_plugin_info( 'locale' ) ); ?></label></th>
                                <td  valign='middle'>
                                	<input type='text' size='30' id='username' name='username' value='<?php echo esc_attr( $this->get_admin_option( 'username' ) ); ?>' />
                                </td>
                            </tr>
                            <tr valign='top'>
                                <th scope="row"><label for='password'><?php _e( 'YOURLS Password (Username)', $this->get_plugin_info( 'locale' ) ); ?></label></th>
                                <td  valign='middle'>
                                	<input type='password' size='30' id='password' name='password' value='<?php echo esc_attr( $this->get_admin_option( 'password' ) ); ?>' />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="submit">
                      <input class='button-primary' type="submit" name="update" value="<?php esc_attr_e('Check and Save Settings', $this->get_plugin_info( 'locale' ) ) ?>" />
                    </div><!--/.submit-->
                  </form>
			</div><!--/.wrap-->
			<?php
		} //end output_settings
		
		
		/**
		* save_admin_option()
		* 
		* Saves an individual option to the options array
		* @param		string    	$key		Option key to save
		* @param		mixed		$value	Value to save in the option	
		*/
		public function save_admin_option( $key = '', $value = '' ) {
			$this->admin_options[ $key ] = $value;
			$this->save_admin_options();
			return $value;
		} //end save_admin_option
		
		/**
		* save_admin_options()
		* 
		* Saves a group of admin options to the options table
		* @param		array    	$admin_options		Optional array of options to save (are merged with existing options)
		*/
		public function save_admin_options( $admin_options = false ){
			if (!empty($this->admin_options)) {
				if ( is_array( $admin_options ) ) {
					$this->admin_options = wp_parse_args( $admin_options, $this->admin_options );
				}
				update_option( $this->get_plugin_info( 'slug' ), $this->admin_options);
			}
		} //end save_admin_options
		
    } //end class
}
//instantiate the class
global $pb_yourls;
if (class_exists('pluginbuddy_yourls')) {
	if (get_bloginfo('version') >= "3.0") {
		add_action( 'plugins_loaded', 'pb_yourls_instantiate' );
	}
}
function pb_yourls_instantiate() {
	global $pb_yourls;
	$pb_yourls = new pluginbuddy_yourls();
}
?>