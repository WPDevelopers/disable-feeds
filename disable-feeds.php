<?php
/*
Plugin Name: Disable Feeds
Plugin URI: http://wordpress.org/extend/plugins/disable-feeds/
Description: Disable all RSS/Atom feeds on your WordPress site.
Version: 1.4.2
Author: Samir Shah, BBpress support by Matthew Sigley
Author URI: http://rayofsolaris.net/
License: GPLv2 or later
*/

if( !defined( 'ABSPATH' ) )
	exit;

class Disable_Feeds {
	private static $instance = null;

	public static function get_instance() {
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	private function __construct() {
		if( is_admin() ) {
			add_action( 'admin_init', array( $this, 'admin_setup' ) );
		}
		else {
			add_action( 'wp_loaded', array( $this, 'remove_links' ) );
			add_action( 'template_redirect', array( $this, 'filter_feeds' ), 1 );
			add_filter( 'bbp_request', array( $this, 'filter_bbp_feeds' ), 9 );
		}
	}

	function admin_setup() {
		add_settings_field( 'disable_feeds_redirect', 'Disable Feeds Plugin', array( $this, 'settings_field' ), 'reading' );
		register_setting( 'reading', 'disable_feeds_redirect' );
		register_setting( 'reading', 'disable_feeds_allow_main' );
	}

	function settings_field() {
		$redirect = $this->redirect_status();
		echo '<p>The <em>Disable Feeds</em> plugin is active, By default, all feeds are disabled, and all requests for feeds are redirected to the corresponding HTML content. You can tweak this behaviour below.</p>';
		echo '<p><input type="radio" name="disable_feeds_redirect" value="on" id="disable_feeds_redirect_yes" class="radio" ' . checked( $redirect, 'on', false ) . '/><label for="disable_feeds_redirect_yes"> Redirect feed requests to corresponding HTML content</label><br /><input type="radio" name="disable_feeds_redirect" value="off" id="disable_feeds_redirect_no" class="radio" ' . checked( $redirect, 'off', false ) . '/><label for="disable_feeds_redirect_no"> Issue a Page Not Found (404) error for feed requests</label></p>';
		echo '<p><input type="checkbox" name="disable_feeds_allow_main" value="on" id="disable_feeds_allow_main" ' . checked( $this->allow_main(), true, false ) . '/><label for="disable_feeds_allow_main"> Do not disable the <strong>global post feed</strong> and <strong>global comment feed</strong></label></p>';
	}

	function remove_links() {
		remove_action( 'wp_head', 'feed_links', 2 );
		remove_action( 'wp_head', 'feed_links_extra', 3 );
	}

	function filter_feeds() {
		if( !is_feed() || is_404() )
			return;

		if( $this->allow_main()
			&& ! ( is_singular() || is_archive() || is_date() || is_author() || is_category() || is_tag() || is_tax() || is_search() ) )
			return;

		$this->redirect_feed();
	}

	//BBPress feed detection sourced from bbp_request_feed_trap() in BBPress Core.
	function filter_bbp_feeds( $query_vars ) {
		// Looking at a feed
		if ( isset( $query_vars['feed'] ) ) {

			// Forum/Topic/Reply Feed
			if ( isset( $query_vars['post_type'] ) ) {

				// Matched post type
				$post_type = false;

				// Post types to check
				$post_types = array(
					bbp_get_forum_post_type(),
					bbp_get_topic_post_type(),
					bbp_get_reply_post_type()
				);

				// Cast query vars as array outside of foreach loop
				$qv_array = (array) $query_vars['post_type'];

				// Check if this query is for a bbPress post type
				foreach ( $post_types as $bbp_pt ) {
				    if ( in_array( $bbp_pt, $qv_array, true ) ) {
					    $post_type = $bbp_pt;
					    break;
				    }
				}

				// Looking at a bbPress post type
				if ( ! empty( $post_type ) ) {
					$this->redirect_feed();
				}
			}
			// @todo User profile feeds
		}

		// No feed so continue on
		return $query_vars;
	}

	private function redirect_feed() {
		global $wp_rewrite, $wp_query;

		if( $this->redirect_status() == 'on' ) {
			if( isset( $_GET['feed'] ) ) {
				wp_redirect( esc_url_raw( remove_query_arg( 'feed' ) ), 301 );
				exit;
			}

			if( get_query_var( 'feed' ) !== 'old' )	// WP redirects these anyway, and removing the query var will confuse it thoroughly
				set_query_var( 'feed', '' );

			redirect_canonical();	// Let WP figure out the appropriate redirect URL.

			// Still here? redirect_canonical failed to redirect, probably because of a filter. Try the hard way.
			$struct = ( !is_singular() && is_comment_feed() ) ? $wp_rewrite->get_comment_feed_permastruct() : $wp_rewrite->get_feed_permastruct();
			$struct = preg_quote( $struct, '#' );
			$struct = str_replace( '%feed%', '(\w+)?', $struct );
			$struct = preg_replace( '#/+#', '/', $struct );
			$requested_url = ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
			$new_url = preg_replace( '#' . $struct . '/?$#', '', $requested_url );

			if( $new_url != $requested_url ) {
				wp_redirect( $new_url, 301 );
				exit;
			}
		}
		else {
			$wp_query->is_feed = false;
			$wp_query->set_404();
			status_header( 404 );
		}
	}

	private function redirect_status() {
		$r = get_option( 'disable_feeds_redirect', 'on' );
		// back compat
		if( is_bool( $r ) ) {
			$r = $r ? 'on' : 'off';
			update_option( 'disable_feeds_redirect', $r );
		}
		return $r;
	}

	private function allow_main() {
		return ( get_option( 'disable_feeds_allow_main', 'off' ) == 'on' );
	}
}

Disable_Feeds::get_instance();