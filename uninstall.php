<?php 
if( !defined('ABSPATH') && !defined('WP_UNINSTALL_PLUGIN') ) 
	exit;

delete_option( 'disable_feeds_redirect' );
delete_option( 'disable_feeds_allow_main' );