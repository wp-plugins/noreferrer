<?php
/**
 * Administration options.
 *
 * @since 2.0.0
 * @package noreferrer
 */

/* Exit if accessed directly */
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Administration class.
 *
 * Provides administration UI and sets default options.
 *
 * @package noreferrer
 * @since 2.0.0
 */
class Noreferrer_Plugin_Admin {
	/**
	 * Already loaded in class Noreferrer_Plugin.
	 *
	 * @var array $options Contains plugin options.
	 */
	protected $options;

	/**
	 * Load options; register actions with WordPress.
	 *
	 * @since 2.0.0
	 * @param array $options Plugin options from WP get_option().
	 */
	public function __construct( $options ) {
		$this->options = $options;

		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'init_hooks' ) );
	}

	/**
	 * Adds options page.
	 *
	 * @since 2.0.0
	 */
	public function add_menu() {
		add_options_page( 'Noreferrer settings', 'Noreferrer', 'manage_options', 'noreferrer-settings', array( $this, 'noreferrer_options' ) );
	}

	/**
	 * Callback that outputs the options page.
	 *
	 * @since 2.0.0
	 */
	public function noreferrer_options() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.' ) );
		}
		?>
		<div class="wrap">
			<h2><?php esc_html_e( 'Noreferrer Settings', 'noreferrer' ); ?></h2>
			<?php _e( '<p><em>Referrer Policy in meta tag</em> offers the strongest privacy-protection and is all you need in <a href="http://caniuse.com/#feat=referrer-policy">browsers that support it</a>. For maximum compatibility, however, <code><a href="http://www.w3.org/TR/html5/links.html#link-type-noreferrer">rel="noreferrer"</a></code> is also turned on by default.</p>', 'noreferrer' ); // WPCS: XSS OK. ?>
			<form action="options.php" method="post">
				<?php settings_fields( 'noreferrer_options' ); ?>
				<?php do_settings_sections( 'noreferrer_options' ); ?>
				<?php submit_button(); ?>
			</form>

			<h3><?php esc_html_e( 'Effect of current settings', 'noreferrer' ); ?></h3>
				<?php $this->test_settings(); ?>
		</div>
	<?php
	}

	/**
	 * Adds theme options and registers sections, fields and settings.
	 *
	 * @since 2.0.0
	 */
	public function init_hooks() {
		if ( false === get_option( 'noreferrer_options' ) ) {
			$defaults = array(
				'rel_noreferrer' => '1',
				'whitelist_internal' => '1',
				'rel_whitelist' => '',
				'use_meta' => '1',
			);

			add_option( 'noreferrer_options', $defaults );
			$this->options = $defaults;
		}

		add_settings_section(
			'noreferrer_options_section_rel',
			esc_html__( 'Element level', 'noreferrer' ),
			array( $this, 'options_section_rel_callback' ),
			'noreferrer_options'
		);

		add_settings_field(
			'noreferrer_field_rel',
			esc_html__( 'Add rel="noreferrer" or referrer="no-referrer" to', 'noreferrer' ),
			array( $this, 'field_rel_callback' ),
			'noreferrer_options',
			'noreferrer_options_section_rel',
			array(
				'label_text' => esc_html__( 'Links, images and iframes in posts/pages/comments', 'noreferrer' ),
				'label_screen_reader' => esc_html__( 'Add rel="noreferrer" or referrer="no-referrer" to', 'noreferrer' ),
			)
		);

		add_settings_section(
			'noreferrer_options_section_meta',
			esc_html__( 'Referrer Policy in meta tag', 'noreferrer' ),
			array( $this, 'options_section_meta_callback' ),
			'noreferrer_options'
		);

		add_settings_field(
			'noreferrer_field_meta',
			esc_html__( 'Meta tag', 'noreferrer' ),
			array( $this, 'field_meta_callback' ),
			'noreferrer_options',
			'noreferrer_options_section_meta',
			array(
				'label_screen_reader' => esc_html__( 'Meta tag', 'noreferrer' ),
				'label_text' => esc_html__( 'Set referrer meta tag to never', 'noreferrer' ),
				'description' => esc_html__( 'A.k.a. no-referrer.', 'noreferrer' ),
			)
		);

		add_settings_section(
			'noreferrer_options_section_whitelist',
			esc_html__( 'Whitelist', 'noreferrer' ),
			array( $this, 'options_section_whitelist_callback' ),
			'noreferrer_options'
		);

		add_settings_field(
			'noreferrer_field_rel_whitelist_internal',
			esc_html__( 'Internal links', 'noreferrer' ),
			array( $this, 'field_rel_whitelist_internal_callback' ),
			'noreferrer_options',
			'noreferrer_options_section_whitelist',
			array(
				'label_screen_reader' => esc_html__( 'Internal links', 'noreferrer' ),
				'label_text' => esc_html__( 'Whitelist internal links', 'noreferrer' ),
				'description' => esc_html__( 'Links that are relative (/) or with the same domain as your site. ', 'noreferrer' ),
			)
		);

		add_settings_field(
			'noreferrer_field_rel_whitelist',
			esc_html__( 'Domains', 'noreferrer' ),
			array( $this, 'field_rel_whitelist_callback' ),
			'noreferrer_options',
			'noreferrer_options_section_whitelist',
			array(
				'label_for' => 'noreferrer_field_rel_whitelist',
				'description' => esc_html__( 'A space-separated list of domains to which rel="noreferrer" should not be applied.', 'noreferrer' ),
			)
		);

		register_setting(
			'noreferrer_options',
			'noreferrer_options',
			array( $this, 'validate' )
		);
	}

	/**
	 * Run each option value through intval() to make sure we store nothing but numbers.
	 *
	 * @since 2.0.0
	 * @param array $input Options array.
	 */
	public function validate( $input ) {
		foreach ( $input as $k => $v ) {
			if ( 'rel_whitelist' === $k ) {
				$output[ $k ] = sanitize_text_field( $v );
			} else {
				$output[ $k ] = intval( $v );
			}
		}

		return apply_filters( 'noreferrer_validate', $output, $input );
	}

	/**
	 * Callback with help text for the rel="noreferrer" options.
	 *
	 * @since 2.0.0
	 */
	public function options_section_rel_callback() {
		printf( __( 'This adds <code><a href="%s">rel="noreferrer"</a></code> to external links and <code><a href="%s">referrer="no-referrer"</a></code> to images and iframes. Everything is modified on output; nothing will be changed in the database.', 'noreferrer' ), 'http://www.w3.org/TR/html5/links.html#link-type-noreferrer', 'https://w3c.github.io/webappsec/specs/referrer-policy/#referrer-policy-delivery-referrer-attribute' ); // WPCS: XSS OK.
	}

	/**
	 * Callback that outputs the rel="noreferrer" options.
	 *
	 * @since 2.0.0
	 * @param array $args Text to use in field output.
	 */
	public function field_rel_callback( $args ) {
		$html = sprintf( '<legend class="screen-reader-text"><span>%s</span></legend>', $args['label_screen_reader'] );
		$html .= sprintf(
			'<label for="noreferrer_field_rel"><input type="checkbox" id="noreferrer_field_rel" name="noreferrer_options[rel_noreferrer]" value="1" %s /> %s</label>',
			checked( 1, isset( $this->options['rel_noreferrer'] ) ? 1 : 0, false ),
			$args['label_text']
		);

		echo $html; // WPCS: XSS OK.
	}

	/**
	 * Callback with help text for the meta referrer option.
	 *
	 * @since 2.0.0
	 */
	public function options_section_meta_callback() {
		$html = sprintf( __( '<p>This sets the <a href="%s">Referrer Policy</a> meta tag to <code>never</code>, meaning the browser &ndash; if it supports it &ndash; will never send referrer information. This applies to <em>all</em> links everywhere on the page as well as all requests generated by the page (e.g., images and external CSS and JavaScript), and is added to both front-end and back-end pages.</p>', 'noreferrer' ), 'https://w3c.github.io/webappsec/specs/referrer-policy/' );
		$html .= __( '<p><em>Please note</em> that if you enable this, <code>rel="noreferrer"</code> is meaningless, except in older browser versions that don\'t support Referrer Policy.</p>', 'noreferrer' );
		$html .= __( '<p>Additionally, if you enable whitelisting below and have meta referrer enabled, <code>referrer="unsafe-url"</code> will be added to whitelisted links, meaning referrer information should still be sent, as this takes precedence over the meta element. However, this is not yet supported by any browser (July 2015).</p>', 'noreferrer' );

		echo $html; // WPCS: XSS OK.
	}

	/**
	 * Callback that outputs the meta referrer option.
	 *
	 * @since 2.0.0
	 * @param array $args Text to use in field output.
	 */
	public function field_meta_callback( $args ) {
		$html = sprintf( '<legend class="screen-reader-text"><span>%s</span></legend>', $args['label_screen_reader'] );
		$html .= sprintf(
			'<label for="noreferrer_field_meta"><input type="checkbox" id="noreferrer_field_meta" name="noreferrer_options[use_meta]" value="1" %s /> %s</label>',
			checked( 1, isset( $this->options['use_meta'] ) ? 1 : 0, false ),
			$args['label_text']
		);
		$html .= sprintf( '<p class="description">%s</p>', $args['description'] );

		echo $html; // WPCS: XSS OK.
	}

	/**
	 * Callback with help text for the whitelist option.
	 *
	 * @since 2.0.0
	 */
	public function options_section_whitelist_callback() {
		$html = __( '<p>Here you can specify domains that you <em>do</em> want to send referrer information to. Note that this only applies to elements in the content of post/pages/comments. (Note that referer information will <em>never</em> be sent when going from HTTPS to HTTP, even if the site is whitelisted.)</p>', 'noreferrer' );

		echo $html; // WPCS: XSS OK.
	}

	/**
	 * Callback that outputs the option for whitelisting internal links.
	 *
	 * @since 2.0.0
	 * @param array $args Text to use in field output.
	 */
	public function field_rel_whitelist_internal_callback( $args ) {
		$html = sprintf( '<legend class="screen-reader-text"><span>%s</span></legend>', $args['label_screen_reader'] );
		$html .= sprintf(
			'<label for="noreferrer_field_rel_whitelist_internal"><input type="checkbox" id="noreferrer_field_rel_whitelist_internal" name="noreferrer_options[whitelist_internal]" value="1" %s /> %s</label>',
			checked( 1, isset( $this->options['whitelist_internal'] ) ? 1 : 0, false ),
			$args['label_text']
		);
		$html .= sprintf( '<p class="description">%s</p>', $args['description'] );

		echo $html; // WPCS: XSS OK.
	}

	/**
	 * Callback that outputs the text field for whitelisted domains.
	 *
	 * @since 2.0.0
	 * @param array $args Text to use in field output.
	 */
	public function field_rel_whitelist_callback( $args ) {
		$html = sprintf(
			'<input type="text" id="noreferrer_field_rel_whitelist" name="noreferrer_options[rel_whitelist]" value="%s" size="40" placeholder="example.com example.org" /> ',
			isset( $this->options['rel_whitelist'] ) ? esc_attr( $this->options['rel_whitelist'] ) : ''
		);
		$html .= sprintf( '<p class="description">%s</p>', $args['description'] );

		echo $html; // WPCS: XSS OK.
	}

	/**
	 * Show some HTML before and after to demonstrate what effect the user's settings have.
	 *
	 * @since 2.0.0
	 */
	protected function test_settings() {
		/* The following is to demonstrate for the user how content is transformed by the plugin */

		$sample = new Noreferrer_Plugin();
		$current_settings = '';
		$sample_input = '';
		$sample_output = '';

		if ( isset( $this->options['use_meta'] ) ) {
			$current_settings .= __( '<p>The following is added to the <code>&lt;head&gt;</code> of every page:</p>', 'noreferrer' );
			$current_settings .= sprintf( '<pre>%s</pre>', esc_html( $sample->add_meta_referrer( false ) ) );
			$current_settings .= __( '<p>This means that in browsers that support it, no referrer information will be sent at all, except when overridden in tags with <code>referrer="unsafe-url"</code>.</p>', 'noreferrer' );
		}

		if ( ( isset( $this->options['rel_noreferrer'] ) ) || ( isset( $this->options['use_meta'] ) && isset( $this->options['whitelist_internal'] ) ) || ( isset( $this->options['use_meta'] ) && ! empty( $this->options['rel_whitelist'] ) ) ) {
			$current_settings .= __( '<p>Some HTML elements will be modified. Example:</p>', 'noreferrer' );

			$sample_input = "<a href=\"http://www.anything-not-in-whitelist.com/\">Not whitelisted</a>\n";
			$sample_input .= sprintf( "<a href=\"%s\">site_url()</a>\n", site_url() );
			$sample_input .= sprintf( "<a href=\"/foo\">%s</a>\n", esc_html__( 'Internal, relative', 'noreferrer' ) );
			$sample_input .= "<area href=\"http://www.anything-not-in-whitelist.com/\" />\n";
			$sample_input .= "<img src=\"http://www.anything-not-in-whitelist.com/foo.jpg\" />\n";
			$sample_input .= sprintf( "<img src=\"%s/foo.jpg\" />\n", site_url() );
			$sample_input .= "<img src=\"/foo/bar.jpg\" />\n";
			$sample_input .= '<iframe src="http://www.anything-not-in-whitelist.com/"></iframe>';

			$sample_output = $sample->add_referrer( $sample_input );

			$current_settings .= sprintf( '<pre>%s</pre>', esc_html( $sample_input ) );
			$current_settings .= __( '<em>Turns into</em>', 'noreferrer' );
			$current_settings .= sprintf( '<pre>%s</pre>', esc_html( $sample_output ) );
		}

		echo $current_settings; // WPCS: XSS OK.
	}
}
