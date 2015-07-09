<?php
/**
 * Adds rel="noreferrer" to external links.
 *
 * @since 1.0.0
 * @package noreferrer
 *
 * @wordpress-plugin
 * Plugin Name:       Noreferrer
 * Plugin URI:        https://anders.unix.se/wordpress-plugin-noreferrer/
 * Description:       Adds rel="noreferrer" to external links in posts/pages/comments.
 * Version:           2.0.0
 * Author:            Anders Jensen-Urstad
 * Author URI:        https://anders.unix.se/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       noreferrer
 * Domain Path:       /languages
 */

/* Exit if accessed directly */
if ( ! defined( 'WPINC' ) ) {
	die;
}

require plugin_dir_path( __FILE__ ) . 'class-noreferrer.php';

$noreferrer = new Noreferrer_Plugin();
$noreferrer->run();
