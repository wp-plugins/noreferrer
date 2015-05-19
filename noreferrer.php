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
 * Version:           1.0.0
 * Author:            Anders Jensen-Urstad
 * Author URI:        https://anders.unix.se/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

/* Exit if accessed directly */
if ( ! defined( 'WPINC' ) ) {
	die;
}

/* Filter content output and comment output */
add_filter( 'the_content', 'ndrsj_add_rel_noreferrer', 99 );
add_filter( 'comment_text', 'ndrsj_add_rel_noreferrer', 99 );
/**
 * Adds rel="noreferrer" to all HTML A elements in content.
 *
 * Regex from wp_rel_nofollow() in wp-includes/formatting.php.
 *
 * @since 1.0.0
 * @param string
 * @return string
 */
function ndrsj_add_rel_noreferrer( $text ) {
	$text = preg_replace_callback( '|<a (.+?)>|i', 'ndrsj_add_rel_noreferrer_callback', $text );
	return $text;
}

/**
 * Callback to add rel="noreferrer" to HTML A element if it points to an external URL.
 *
 * Preserves existing 'rel' attributes.
 *
 * @since 1.0.0
 * @param array
 * @return string
 */
function ndrsj_add_rel_noreferrer_callback( $matches ) {
	$original = $matches[0];      // E.g.: <a href="http://foo.bar/">
	$link = $matches[0] . '</a>'; // Add closing tag so that it becomes valid for SimpleXML

	/**
	 * It could happen that $link is not a well-formed XML string due to 
	 * it having been messed up by something else. If so, rather than
	 * generating PHP warnings, suppress them and just return the original
	 * string. (i.e.: This plugin won't touch badly formed strings.)
	 */

	/* Enable user error handling while saving the previous use_errors value */
	$previous_use_errors = libxml_use_internal_errors( true );

	/* Turn string into an SimpleXMLElement object */
	$link_xml = simplexml_load_string( $link );

	/* Clear libxml error buffer */
	libxml_clear_errors();	
	
	/* Set error handling to whatever it was before */
	libxml_use_internal_errors( $previous_use_errors );
	
	/* If the string wasn't well-formed, do nothing */
	if ( ! $link_xml ) {
		return $original;
	}

	/* Get host component of link and site URLs */
	$link_url_host = parse_url( $link_xml['href'], PHP_URL_HOST );
	$site_url_host = parse_url( site_url(), PHP_URL_HOST );

	/* If the link has no host component it's a relative link, so do nothing */
	if ( empty( $link_url_host ) ) {
		return $original;
	}

	/* If the link host is the same as the site url host it's an internal link, so do nothing */
	if ( strcasecmp( $link_url_host, $site_url_host ) === 0 ) {
		return $original;
	}

	if ( isset( $link_xml['rel'] ) ) { // 'rel' attribute already exists
		if ( false !== stripos( ( (string) $link_xml['rel'] ), 'noreferrer' ) ) {
			return $original; // 'noreferrer' already set, do nothing
		} else {
			$link_xml['rel'] .= ' noreferrer'; // 'noreferrer' not set, so append it
		}
	} else { // 'rel' attribute doesn't exist, so set it to 'noreferrer'
		$link_xml['rel'] = 'noreferrer';
	}

	/* Because of https://bugs.php.net/bug.php?id=67387
	   ...we do it this way to get rid of the XML declaration. */
	$dom = dom_import_simplexml( $link_xml );
	$str_html = $dom->ownerDocument->saveXML( $dom->ownerDocument->documentElement );

	/* At this point $str_html is a self-closing tag. Let's fix that. */
	$str_html = preg_replace( '/\/>$/', '>', $str_html );

	return $str_html;
}