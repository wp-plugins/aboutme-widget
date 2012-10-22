<?php
/*
Plugin Name: About.me Widget
Plugin URI: http://wordpress.org/extend/plugins/aboutme-widget/
Description: Display your about.me profile on your WordPress blog
Author: about.me
Version: 1.0.2
Author URI: https://about.me/?ncid=aboutmewpwidget
Text Domain: aboutme-widget
*/

/**
 * Adds Aboutme_Widget widget.
 */
class Aboutme_Widget extends WP_Widget {

	const API_KEY = '8200bb086a407093faffc6ed21db003074db380a';
	const CACHE_TIME = 3600;
	const ERROR_NO_USER = 1;
	const ERROR_EMPTY_USER = 2;
	const ERROR_NO_CLIENT = 3;
	const API_SERVER_ERROR = 4;


	/**
	 * Register widget with WordPress.
	 */
	public function __construct() {
		$widget_ops = array( 'classname' => 'aboutme_widget', 'description' => __( 'Display your about.me profile with thumbnail', 'aboutme-widget' ) );
		parent::__construct( 'aboutme_widget', __( 'About.me Widget', 'aboutme-widget' ), $widget_ops );
	}

	/**
	 * Front-end display of widget.
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Saved values from database.
	 */
	public function widget( $args, $instance ) {
		//If there is no client_id alotted yet or some error, return
		if ( empty($instance['client_id']) || 0 != $instance['error'] )
			return;
		extract( $args, EXTR_SKIP );
		$title = empty( $instance['title'] ) ? '' : apply_filters( 'widget_title', $instance['title'] );
		$fontsize = empty( $instance['fontsize'] ) ? 'large' : $instance['fontsize'];
		$username = empty( $instance['username'] ) ? '' : $instance['username'];
		//We need to check the key existence as this option was absent in initial release, otherwise widget might break
		$display_image = array_key_exists('display_image', $instance )? $instance['display_image'] : "1" ;
		//If no username is there, return
		if ( empty( $username ) )
			return;
		$data = get_transient( 'am_' . $username . '_data' );
		//if transient data got expired create new data
		if ( false === $data ) {
			$url = 'https://api.about.me/api/v2/json/user/view/' . $username . '?client_id=' . $instance['client_id'] . '&extended=true&on_match=true&strip_html=false';
			$data = $this->get_api_content( $url );
			if (false !== $data) {
				$data = $this->extract_api_data( $data );
				if ( !empty( $data ) ) {
					//Store this profile data in database
					set_transient( 'am_' . $username . '_data', $data, self::CACHE_TIME );
				} else {
					//If empty profile data, return
					return;
				}
			} else {
				//Some wrong happen in getting profle response from aboutme server, so return
				return;
			}
		}

		//Display the profile:
		// if any key value is not present in stored data, delete the data as it is not in proper format
		$keys = array( 'service_icons', 'profile_url', 'thumbnail', 'first_name', 'last_name', 'header', 'bio' );
		foreach ( $keys as $k => $val ) {
			if ( !array_key_exists( $val, $data ) ) {
				delete_transient( 'am_' . $username . '_data' );
				return;
			}
		}
		//Check the non emptyness of $data
		if ( is_array( $data ) && !empty( $data ) ) {
?>
<style type="text/css">
#am_thumbnail a {
text-decoration: none;
border: none;
}
#am_thumbnail img {
text-decoration: none;
border: 1px solid #999;
max-width: 99%;
}
#am_name {
margin-top: 5px;
margin-bottom: 3px;
}
#am_headline {
margin-bottom: 5px;
}
#am_bio {
margin-bottom: 15px;
}
#am_bio p {
margin-bottom: 5px;
}
#am_bio p:last-child {
margin-bottom: 0px;
}
#am_services {
margin-right: -5px;
}
#am_services a.am_service_icon {
margin-right: 4px;
text-decoration: none;
border: none;
}
#am_services a.am_service_icon:hover {
text-decoration: none;
border: none;
}
#am_services a.am_service_icon img {
border: none;
margin-bottom: 4px;
}
</style>
<?php
			// html markup for widget display
			echo $before_widget;
			if ( !empty( $title ) )
				echo $before_title . $title . $after_title;
			if ( $display_image == '1' && $data['thumbnail'] != '') {
				echo '<div id="am_thumbnail"><a href="' . esc_url( $data['profile_url'] ) . '" target="_blank" rel="me"><img src="' . esc_url( $data['thumbnail'] ) . '" alt="' . esc_attr( $data['first_name'] ) . ' ' . esc_attr( $data['last_name'] ) . '"></a></div>';
			}
			echo '<h2 id="am_name"><a href="' . $data['profile_url'] . '" style="font-size:' . $fontsize . ';" target="_blank" rel="me">' . esc_attr( $data['first_name'] ) . ' ' . esc_attr( $data['last_name'] ) . '</a></h2>';
			if ( !empty( $data['header'] ) ) echo '<h3 id="am_headline">' . esc_attr( $data['header'] ) . '</h3>';
			if ( !empty( $data['bio'] ) ) {
				$biostr = '<p>' . str_replace( "\n", '</p><p>', wp_kses_data( $data['bio'] ) ) . '</p>';
				echo '<div id="am_bio">' . $biostr . '</div>';
			}
			if ( count( $data['service_icons'] ) > 0 ) {
				echo '<div id="am_services">';
				foreach ( $data['service_icons'] as $v ) {
					echo '<a href="' . esc_url( $v['url'] ) . '" target="_blank" class="am_service_icon" rel="me"><img src="' . esc_url( $v['icon'] ) . '"></a>';
				}
				echo '</div>';
			}
			echo $after_widget;
		}
	}


	/**
	 * Sanitize widget form values as they are saved.
	 *
	 * @param array $new_instance Values just sent to be saved.
	 * @param array $old_instance Previously saved values from database.
	 *
	 * @return array Updated safe values to be saved.
	 */
	public function update( $new_instance, $old_instance ) {
		$discard = array( 'https://about.me/', 'http://about.me/', 'about.me/' );
		$username = empty( $new_instance['username'] ) ? '' : $new_instance['username'];
		$new_instance['username'] = strip_tags( stripslashes( str_replace( $discard, '', trim( $username ) ) ) );
		
		$src_url = empty( $new_instance['src_url'] ) ? get_site_url() : $new_instance['src_url'];
		$new_instance['src_url'] = str_ireplace( array('https://','http://'), '' , $src_url );
		$new_instance['display_image'] = $new_instance['display_image'] ? '1' : '0';
		$registration_flag = true; //This determines if we need to call registration api or not.
		//Process only if username has been entered
		if ( empty( $username ) ) {
			$new_instance['error'] = self::ERROR_EMPTY_USER;
		} else {
			//If no client_id has been alloted, call for aboutme registration
			//If username has been changed, call for aboutme registration
			//If src_url or wordpress site url got changed, call for aboutme registration
			if ( empty( $new_instance['client_id'] ) ) {
				$registration_flag = false;
			} elseif ( $username != $old_instance['username'] ) {
				delete_transient( 'am_' . $old_instance['username'] . '_data' );
				$registration_flag = false;
			} elseif ( !array_key_exists( 'src_url', $old_instance ) || $src_url != $old_instance['src_url']) {
				$registration_flag = false;
			}
			if ( !$registration_flag ) {
				$url = 'https://api.about.me/api/v2/json/user/register/' . $username . '?apikey=' . self::API_KEY . '&src_url=' . $src_url . '&src=wordpress&verify=true';
				$data = $this->get_api_content( $url );
				if (false === $data) {
					$new_instance['error'] = self::API_SERVER_ERROR;
				} else {
					if ( !empty( $data ) ) {
						if ( 200 == $data->status ) {
							//store this apikey as persistence object
							$new_instance['client_id'] = $data->apikey;
							$new_instance['error'] = 0;
						} elseif (401 == $data->status) {
							$new_instance['error'] = self::ERROR_NO_CLIENT;
							$new_instance['client_id'] = '';
						} elseif (404 == $data->status) {
							$new_instance['error'] = self::ERROR_NO_USER;
							$new_instance['client_id'] = '';
						}
					} else {
						$new_instance['error'] = self::API_SERVER_ERROR;
					}
				}
			}
			// If client_id is available call profile api to get profile data
			if ( ! empty( $new_instance['client_id'] ) ){
				$dataurl = "https://api.about.me/api/v2/json/user/view/$username?client_id={$new_instance['client_id']}&extended=true&on_match=true&strip_html=false";
				$userdata = $this->get_api_content( $dataurl );
				if (false === $userdata) {
					$new_instance['error'] = self::API_SERVER_ERROR;
				} else {
					if ( !empty( $userdata ) ){
						if ( 200 == $userdata->status ) {
							// Reset any previous error that might have been set
							$new_instance['error'] = 0;
							$data = $this->extract_api_data( $userdata );
							set_transient( 'am_' . $username . '_data', $data, self::CACHE_TIME );
						} elseif (401 == $data->status) {
							$new_instance['error'] = self::ERROR_NO_CLIENT;
							$new_instance['client_id'] = '';
						} elseif (404 == $data->status) {
							$new_instance['error'] = self::ERROR_NO_USER;
							$new_instance['client_id'] = '';
						}
					} else {
						$new_instance['error'] = self::API_SERVER_ERROR;
					}
				}
			}
		}
		return $new_instance;
	}

	/**
	 * To read the response from aboutme api call
	 *
	 * @params string $url api url
	 *
	 * @retun mixed json class or false
	 */
	private function get_api_content( $url ) {
		$response = wp_remote_get( $url, array( 'sslverify'=>0, 'User-Agent' => 'WordPress.com About.me Widget' ) );
		if ( is_wp_error( $response ) ) {
			return false;
		} else {
			return json_decode( $response['body'] );
		}
	}
	/**
	 * Only extract required keys from json data of api profile call
	 * @param class $data json content of profile
	 *
	 * @return array
	 */
	private function extract_api_data( $data ) {
		$retarr = array();
		if ( !empty( $data ) && 200 == $data->status ) {
			$icons = array();
			$i=0;
			foreach ( $data->websites as $c ) {
				if ( 'link' == $c->platform || 'default' == $c->platform )
					continue; //we want to show only service icons
				if ( !empty( $c->icon42_url ) ) {
					$icon_url = $c->icon42_url;
					$icon_url = str_replace( '42x42', '32x32', $icon_url );
					if ( $c->site_url ) {
						$url = $c->site_url;
					} else if( $c->modal_url ) {
						$url = $c->modal_url;
					} else {
						$url = 'http://about.me/' . $data->user_name . '/#!/service/' . $c->platform;
					}
					$icons[$i++] = array( 'icon'=>$icon_url, 'url'=>$url );
				}
			}
			$retarr['service_icons'] = $icons;
			$retarr['profile_url'] = $data->profile;
			$retarr['thumbnail'] = $data->background;
			$retarr['first_name'] = $data->first_name;
			$retarr['last_name'] = $data->last_name;
			$retarr['header'] = $data->header;
			$retarr['bio'] = $data->bio;
		}
		return $retarr;
	}




	/**
	 * Back-end widget form.
	 *
	 * @param array $instance Previously saved values from database.
	 */
	public function form( $instance ) {
		$instance = wp_parse_args( ( array ) $instance, array( 'title' => 'about.me', 'fontsize' =>'large', 'client_id' => '', 'error' => 0, 'src_url'  => str_ireplace( array('https://','http://'), '' , get_site_url() ), 'username' => '', 'display_image' => '1' ) );
		$title = $instance['title'];
		$fontsize = $instance['fontsize'];
		$username = array_key_exists( 'username', $instance )? $instance['username'] : '';
		$display_image = array_key_exists( 'display_image', $instance )? $instance['display_image'] : '1';
		if ( empty($username) ) {
?>
			<p>
				<a href="https://about.me/?ncid=aboutmewpwidget" target="_blank"><?php _e( 'About.me', 'aboutme-widget');?></a> <?php _e( 'is a free service that lets you create a beautiful one-page website all about you.', 'aboutme-widget' );?>
			</p>
			<p>
				<?php _e( 'Current users simply add your username below. Or,', 'aboutme-widget');?> <a href="https://about.me/?ncid=aboutmewpwidget" target="_blank"><?php _e( 'sign up', 'aboutme-widget' );?></a><?php _e( ', create a page then add your username here.', 'aboutme-widget' );?>
			</p>
<?php 		}?>
			<p>
				<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Widget title', 'aboutme-widget' );?>:</label>
				<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
			</p>
			<p>
			<label for="<?php echo $this->get_field_id( 'username' ); ?>"><?php _e( 'Your about.me username', 'aboutme-widget' );?>:</label>
			<input id="<?php echo $this->get_field_id( 'username' ); ?>" name="<?php echo $this->get_field_name( 'username' ); ?>" value="<?php echo $username; ?>" style="width: 100%;" type="text" />

			<?php if ( array_key_exists( 'error', $instance ) ) {
				if ( self::ERROR_NO_USER == $instance['error'] ) { ?>
					<span style="font-size:80%;color:red"><?php _e( "There isn't an about.me page by that name. Please check your username and try again.", 'aboutme-widget' ) ?></span>
				<?php } else if ( self::ERROR_EMPTY_USER == $instance['error'] ) { ?>
					<span style="font-size:80%"><?php _e( "Don't have an about.me page?", 'aboutme-widget' ) ?> <a href="https://about.me/?ncid=aboutmewpwidget" target="_blank"><?php _e( 'Sign up now!', 'aboutme-widget' );?></a></span>
				<?php } else if ( self::ERROR_NO_CLIENT == $instance['error'] ) { ?>
					<span style="font-size:80%;color:red"><?php _e( 'We encountered an error while communicating with the about.me server.  Please try again later.', 'aboutme-widget' ) ?></span>
				<?php } else if ( self::API_SERVER_ERROR == $instance['error'] ) { ?>
					<span style="font-size:80%;color:red"><?php _e( 'We encountered an error while communicating with the about.me server.  Please try again later.', 'aboutme-widget' ) ?></span>
				<?php } ?>
			<?php } ?>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'fontsize' ); ?>"><?php _e( 'Name size', 'aboutme-widget' );?>:</label>
			<select id="<?php echo $this->get_field_id( 'fontsize' ); ?>" name="<?php echo $this->get_field_name( 'fontsize' ); ?>">
				<option value='x-large' <?php selected( $fontsize, 'x-large' ); ?>><?php _e( 'X-Large', 'aboutme-widget' ) ?></option>
				<option value='large' <?php selected( $fontsize, 'large' ); ?>><?php _e( 'Large', 'aboutme-widget' ) ?></option>
				<option value='medium' <?php selected( $fontsize, 'medium' ); ?>><?php _e( 'Medium', 'aboutme-widget' ) ?></option>
				<option value='small' <?php selected( $fontsize, 'small' ); ?>><?php _e( 'Small', 'aboutme-widget' ) ?></option>
			</select>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'display_image' ); ?>"><?php _e( 'Display Image', 'aboutme-widget' );?>:</label>
			<input type="checkbox" id="<?php echo $this->get_field_id( 'display_image' ); ?>" name="<?php echo $this->get_field_name( 'display_image' ); ?>" value="1" <?php checked( $display_image, '1' ); ?> /> 
			<input type="hidden" id="<?php echo $this->get_field_id( 'client_id' ); ?>" name="<?php echo $this->get_field_name( 'client_id' ); ?>" value="<?php echo $instance['client_id']; ?>">
			<input type="hidden" id="<?php echo $this->get_field_id( 'error' ); ?>" name="<?php echo $this->get_field_name( 'error' ); ?>" value="<?php echo $instance['error']; ?>">
			<input type="hidden" id="<?php echo $this->get_field_id( 'src_url' ); ?>" name="<?php echo $this->get_field_name( 'src_url' ); ?>" value="<?php echo $instance['src_url']; ?>">
		</p>
<?php
	}


}
//register Aboutme_Widget widget
function aboutme_widget_init() {
	register_widget( 'Aboutme_Widget' );
}

add_action( 'widgets_init', 'aboutme_widget_init' );
