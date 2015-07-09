<?php
/**
 * Executed when plugin is uninstalled.
 *
 * @since      1.1.0
 * @package    noreferrer
 */

/* Exit if not called from WordPress */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'noreferrer_options' );
