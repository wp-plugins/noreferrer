<?php
/**
 * Holds the core plugin class.
 *
 * @since 2.0.0
 * @package noreferrer
 */

/**
 * Core plugin class.
 *
 * Registers filters/actions, modifies links, loads admin, etc.
 *
 * @since 2.0.0
 * @package noreferrer
 */
class Noreferrer_Plugin {
	/**
	 * Options used by Noreferrer_Plugin and Noreferrer_Plugin_Admin.
	 *
	 * @var array $options Contains plugin options.
	 */
	protected $options;

	/**
	 * Load options and, if the user is an admin, the admin class.
	 *
	 * @since 2.0.0
	 */
	public function __construct() {
		$this->options = get_option( 'noreferrer_options' );
	}

	/**
	 * Register filters and actions with WordPress.
	 *
	 * @since 2.0.0
	 */
	public function run() {
		if ( is_admin() && ( ! defined( 'DOING_AJAX' || ! DOING_AJAX ) ) ) {
			require_once plugin_dir_path( __FILE__ ) . 'class-noreferrer-admin.php';
			$admin = new Noreferrer_Plugin_Admin( $this->options );
		}

		/* If any HTML elements need to be modified, add filters on content and comment output */
		if ( ( isset( $this->options['rel_noreferrer'] ) ) || ( isset( $this->options['use_meta'] ) && isset( $this->options['whitelist_internal'] ) ) || ( isset( $this->options['use_meta'] ) && ! empty( $this->options['rel_whitelist'] ) ) ) {
			add_filter( 'the_content', array( $this, 'add_referrer' ), 99 );
			add_filter( 'comment_text', array( $this, 'add_referrer' ), 99 );
		}

		/* If "Referrer Policy in meta tag" is enabled, add it to the page's <head> */
		if ( isset( $this->options['use_meta'] ) ) {
			add_action( 'wp_head', array( $this, 'add_meta_referrer' ) );
			add_action( 'admin_head', array( $this, 'add_meta_referrer' ) );
		}
	}

	/**
	 * Adds meta referrer to the <head> of every page.
	 *
	 * @since 2.0.0
	 * @param bool $echo Whether to echo or return the string. Default is echo.
	 * @return string|null Null if echoing, string if not echoing.
	 */
	public function add_meta_referrer( $echo = null ) {
		/* "no-referrer" is actually the preferred keyword, but MS Edge only supports "never" as of July 2015. */
		$html = '<meta name="referrer" content="never" />';

		if ( false === $echo ) {
			return $html;
		} else {
			echo $html; // WPCS: XSS OK.
		}
	}

	/**
	 * Adds rel="noreferrer" to HTML elements of type a and area, or referrer attribute to elements of type img and iframe.
	 *
	 * Regex modified from wp_rel_nofollow() in wp-includes/formatting.php.
	 *
	 * @since  2.0.0
	 * @param  string $text Content from WP post/page/comment.
	 * @return string       Content, possibly with links modified.
	 */
	public function add_referrer( $text ) {
		$text = preg_replace_callback( '/<(area|a|img|iframe) (.+?)>/i', array( $this, 'add_referrer_callback' ), $text );
		return $text;
	}

	/**
	 * Callback to add rel="noreferrer" to HTML A element if it points to an external URL,
	 * or noreferrer="unsafe-url" for any whitelisted/local URLs if meta referrer is enabled.
	 *
	 * Preserves all existing attributes, including existing 'rel' attributes.
	 *
	 * @since  2.0.0
	 * @param  array $matches An HTML element of type area, a, img or iframe.
	 * @return string         An HTML element of type area, a, img or iframe; possibly modified.
	 */
	protected function add_referrer_callback( $matches ) {
		$original = $matches[0];      // E.g.: <a href="http://foo.bar/">.
		$element_type = $matches[1];          // E.g.: a, area, ..
		$rel_elements = array( 'a', 'area' );
		$use_meta = isset( $this->options['use_meta'] ) ? true : false;
		$add_rel_noreferrer = isset( $this->options['rel_noreferrer'] ) ? true : false;
		$whitelist_internal = isset( $this->options['whitelist_internal'] ) ? true : false;
		$link_is_internal = false;
		$link_url = '';
		$site_url = '';

		/* SimpleXML needs a closing tag, so let's add one. But first, make sure the tag not already self-closing. */
		$link = preg_replace( '/\/\s*?>$/', '>', $original );
		$link = sprintf( '%s</%s>', $link, $element_type );

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

		/* If the string isn't well-formed, do nothing */
		if ( ! $link_xml ) {
			return $original;
		}

		/* Get host and scheme component of link and site URLs */
		if ( in_array( $element_type, $rel_elements ) ) {
			$link_url = parse_url( $link_xml['href'] );
		} else {
			$link_url = parse_url( $link_xml['src'] );
		}
		$site_url = parse_url( site_url() );

		/*
		 * If the link has no host component, or if the link host is the same as the site URL host,
		 * it's an internal link.
		 */
		if ( empty( $link_url['host'] ) || strcasecmp( $link_url['host'], $site_url['host'] ) === 0 ) {
			$link_is_internal = true;
		}

		/* If we have an internal link and whitelisting of internal links is enabled... */
		if ( $whitelist_internal && $link_is_internal ) {
			/* If meta referrer is enabled, add referrer attribute, unless going from HTTPS to HTTP */
			if ( $use_meta && ( empty( $link_url['host'] ) || is_ssl() || ( ! is_ssl() && 'http' === $link_url['scheme'] ) ) ) {
				$link_xml = $this->add_link_attribute( $link_xml, 'referrer-unsafe-url' );
				return $this->cleanup_xml( $link_xml, $element_type );
			} else {
				return $original;
			}
		} elseif ( ! $link_is_internal && ! empty( $this->options['rel_whitelist'] ) && $add_rel_noreferrer ) {
			/* If the link host has been whitelisted, do nothing (or add referrer attribute). */
			$whitelist_domains = explode( ' ', $this->options['rel_whitelist'] );

			foreach ( $whitelist_domains as $whitelist_domain ) {
				/* Treat www.example.com and example.com as the same */
				if ( strcasecmp( $this->strip_www( $link_url['host'] ), $this->strip_www( $whitelist_domain ) ) === 0 ) {
					/* If meta referrer is used, possibly add referrer attribute */
					if ( $use_meta && ( is_ssl() || ( ! is_ssl() && 'http' === $link_url['scheme'] ) ) ) {
							$link_xml = $this->add_link_attribute( $link_xml, 'referrer-unsafe-url' );
							return $this->cleanup_xml( $link_xml, $element_type );
					} else {
						return $original;
					}
				}
			}
		}

		/* At this point we have a link that should have the rel="noreferrer" attribute */
		if ( $add_rel_noreferrer ) {
			if ( in_array( $element_type, $rel_elements ) ) { // A and area elements.
				$link_xml = $this->add_link_attribute( $link_xml, 'rel-noreferrer' );
			} elseif ( ! $use_meta ) { // Elements of type img or iframe, unless meta referrer has been set (in which case it's superfluous).
				$link_xml = $this->add_link_attribute( $link_xml, 'attribute-no-referrer' );
			}
		}

		return $this->cleanup_xml( $link_xml, $element_type ); // Return the element, properly formatted.
	}

	/**
	 * Strip the "www." part from hostnames for comparison.
	 *
	 * @since  2.0.0
	 * @param  string $host Hostname to strip 'www.'' from.
	 * @return string       Hostname, possibly modified.
	 */
	protected function strip_www( $host ) {
		if ( stripos( $host, 'www.' ) === 0 ) {
			return substr( $host, 4 );
		}

		return $host;
	}

	/**
	 * Add noreferrer link type or referrer attribute to SimpleXMLElement
	 *
	 * @since  2.0.0
	 * @param  SimpleXMLElement $link_xml        XML element to modify.
	 * @param  string           $type	         Type of attribute to add.
	 * @return SimpleXMLElement                  XML element, possibly modified.
	 */
	protected function add_link_attribute( $link_xml, $type ) {
		if ( 'rel-noreferrer' === $type ) {
			if ( isset( $link_xml['rel'] ) ) { // 'rel' attribute already exists
				if ( false !== stripos( ( (string) $link_xml['rel'] ), 'noreferrer' ) ) {
					return $original; // 'noreferrer' already set, do nothing
				} else {
					$link_xml['rel'] .= ' noreferrer'; // 'noreferrer' not set, so append it
				}
			} else { // 'rel' attribute doesn't exist, so set it to 'noreferrer'
				$link_xml['rel'] = 'noreferrer';
			}
		} elseif ( 'referrer-unsafe-url' === $type ) {
			$link_xml['referrer'] = 'unsafe-url';
		} elseif ( 'attribute-no-referrer' === $type ) {
			$link_xml['referrer'] = 'no-referrer';
		}

		return $link_xml;
	}

	/**
	 * Get rid of XML declaration and return properly formatted element.
	 *
	 * @since 2.0.0
	 * @param SimpleXMLElement $link_xml      XML element to modify.
	 * @param string           $element_type  Type of element.
	 * @return string
	 */
	protected function cleanup_xml( $link_xml, $element_type ) {
		/*
		 * Because of https://bugs.php.net/bug.php?id=67387
		 * ...we do it this way to get rid of the XML declaration.
		 */
		$dom = dom_import_simplexml( $link_xml );
		$str_html = $dom->ownerDocument->saveXML( $dom->ownerDocument->documentElement );

		/* At this point $str_html is a self-closing tag. Let's fix that (unless the element is img or area). */
		if ( 'img' !== $element_type && 'area' !== $element_type ) {
			$str_html = preg_replace( '/\/>$/', '>', $str_html );
		}

		return $str_html;
	}
}
