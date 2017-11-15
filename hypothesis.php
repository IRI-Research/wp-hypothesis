<?php
/*
 * Plugin Name: Hypothesis
 * Plugin URI: http://hypothes.is/
 * Description: Hypothesis is an open platform for the collaborative evaluation of knowledge. This plugin embeds the necessary scripts in your Wordpress site to enable any user to use Hypothesis without installing any extensions.
 * Author: The Hypothesis Project and contributors
 * Version: 0.5.0
 * Author URI: http://hypothes.is/
 * Text Domain:     hypothesis
 * Domain Path:     /languages
 */

// Exit if called directly.
defined( 'ABSPATH' ) or die( 'Cannot access pages directly.' );

add_action( 'plugins_loaded', array( 'HypothesisSettingsPage', 'init' ) );

/**
 * Create settings page (see https://codex.wordpress.org/Creating_Options_Pages)
 */
class HypothesisSettingsPage {

	protected static $instance;
	public static function init()
	{
			is_null( self::$instance ) AND self::$instance = new self;
			return self::$instance;
	}


	/**
	 * Holds the values to be used in the fields callbacks
	 *
	 * @var array
	 */
	private $options;

	/**
	 * Holds the posttypes to be used in the fields callbacks
	 *
	 * @var array
	 */
	private $posttypes;

	/**
	 * Start up
	 */
	public function __construct() {
		$this->properties();
		$this->textdomain();

		add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
		add_action( 'admin_init', array( $this, 'page_init' ) );
		add_action( 'admin_print_footer_scripts', array( $this, 'add_quicktags' ) );
		add_shortcode( 'pdfviewer', array( $this, 'add_shortcode' ) );
	}

	/**
	 *	Load Class Values
	 */
	public function properties() {
		$this->options = get_option( 'wp_hypothesis_options' );
	}

	/**
	 *	Load Plugin Textdomain
	 */
	public function textdomain() {
		load_plugin_textdomain( 'hypothesis', FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );
	}


	/**
	 * Add options page
	 */
	public function add_plugin_page() {
		add_options_page(
			__( 'Hypothesis Settings', 'hypothesis' ),
			__( 'Hypothesis', 'hypothesis' ),
			'manage_options',
			'hypothesis-setting-admin',
			array( $this, 'create_admin_page' )
		);
	}

	/**
	 * Return an array of post type slugs and corresponding plural display names for options page.
	 *
	 * @returns array
	 */
	public static function get_posttypes() {
		return apply_filters('hypothesis_supported_posttypes', array(
			'post' => _x( 'posts', 'plural post type', 'hypothesis' ),
			'page' => _x( 'pages', 'plural post type', 'hypothesis' ),
		) );
	}

	/**
	 * Options page callback
	 */
	public function create_admin_page() {
		// Set class property.
		?>
		<div class="wrap">
		<form method="post" action="options.php">
		<?php
				settings_fields( 'hypothesis_option_group' );
				do_settings_sections( 'hypothesis-setting-admin' );
				submit_button();
			?>
		</form>
		</div>
	<?php }

	/**
	 * Register and add settings
	 */
	public function page_init() {
		$posttypes = $this->get_posttypes();

		register_setting(
			'hypothesis_option_group', // Option group.
			'wp_hypothesis_options', // Option name.
			array( $this, 'sanitize' ) // Sanitize callback.
		);

		/**
		 * Hypothesis Settings
		 */
		add_settings_section(
			'hypothesis_settings_section', // ID.
			__( 'Hypothesis Settings', 'hypothesis' ), // Title.
			array( $this, 'settings_section_info' ), // Callback.
			'hypothesis-setting-admin' // Page.
		);

		add_settings_field(
			'hypothesis-base-url',
			__( 'Hypothesis base URL', 'hypothesis' ),
			array( $this, 'hypothesis_base_url_callback' ),
			'hypothesis-setting-admin',
			'hypothesis_settings_section'
		);

		add_settings_field(
			'via-base-url',
			__( 'Via base URL', 'hypothesis' ),
			array( $this, 'via_base_url_callback' ),
			'hypothesis-setting-admin',
			'hypothesis_settings_section'
		);


		add_settings_field(
			'highlights-on-by-default',
			__( 'Highlights on by default', 'hypothesis' ),
			array( $this, 'highlights_on_by_default_callback' ),
			'hypothesis-setting-admin',
			'hypothesis_settings_section'
		);

		add_settings_field(
			'sidebar-open-by-default',
			__( 'Sidebar open by default', 'hypothesis' ),
			array( $this, 'sidebar_open_by_default_callback' ),
			'hypothesis-setting-admin',
			'hypothesis_settings_section'
		);

		add_settings_field(
			'serve-pdfs-with-via',
			__( 'Enable annotation for PDFs in Media Library', 'hypothesis' ),
			array( $this, 'serve_pdfs_with_via_default_callback' ),
			'hypothesis-setting-admin',
			'hypothesis_settings_section'
		);

		/**
		 * Content Settings
		 * Control which pages / posts / custom post types Hypothesis is loaded on.
		 */
		add_settings_section(
			'hypothesis_content_section', // ID.
			__( 'Content Settings', 'hypothesis' ), // Title.
			array( $this, 'content_section_info' ), // Callback.
			'hypothesis-setting-admin' // Page.
		);

		add_settings_field(
			'allow-on-front-page',
			__( 'Allow on front page', 'hypothesis' ),
			array( $this, 'allow_on_front_page_callback' ),
			'hypothesis-setting-admin',
			'hypothesis_content_section'
		);

		add_settings_field(
			'allow-on-blog-page',
			__( 'Allow on blog page', 'hypothesis' ),
			array( $this, 'allow_on_blog_page_callback' ),
			'hypothesis-setting-admin',
			'hypothesis_content_section'
		);

		foreach ( $posttypes as $slug => $name ) {
			if ( 'post' === $slug ) {
				$slug = 'posts';
			} elseif ( 'page' === $slug ) {
				$slug = 'pages';
			}

			add_settings_field(
				"allow-on-$slug",
				sprintf( __( 'Allow on %s', 'hypothesis' ), $name ),
				array( $this, 'allow_on_posttype_callback' ),
				'hypothesis-setting-admin',
				'hypothesis_content_section',
				array(
					$slug,
					$name,
				)
			);
		}

		foreach ( $posttypes as $slug => $name ) {
			add_settings_field(
				$slug . '_ids_show_h', // ID.
				sprintf(
					__( 'Allow on specific %1$s (list of comma-separated %1$s IDs, no spaces)', 'hypothesis' ),
					$name,
					$slug
				), // Title.
				array( $this, 'posttype_ids_show_h_callback' ), // Callback.
				'hypothesis-setting-admin', // Page.
				'hypothesis_content_section', // Section.
				array(
					$slug,
					$name,
				)
			);
		}

		foreach ( $posttypes as $slug => $name ) {
			add_settings_field(
				$slug . '_ids_override', // ID.
				sprintf(
					__( 'Disallow on specific %1$s (list of comma-separated %1$s IDs, no spaces)', 'hypothesis' ),
					$name,
					$slug
				), // Title.
				array( $this, 'posttype_ids_override_callback' ), // Callback.
				'hypothesis-setting-admin', // Page.
				'hypothesis_content_section', // Section.
				array(
					$slug,
					$name,
				)
			);
		}

		add_settings_field(
			'post_cats_override', // ID.
			__( 'Disallow on specific post (list of comma-separated categories IDs or names, no spaces)', 'hypothesis' ),
			array( $this, 'post_cats_override_callback' ), // Callback.
			'hypothesis-setting-admin', // Page.
			'hypothesis_content_section'
		);

		/**
		 * pdf viewer Settings
		 */
		add_settings_section(
			'hypothesis_pdfviewer_section', // ID.
			__( 'PDFViewer Settings', 'hypothesis' ), // Title.
			array( $this, 'settings_section_info' ), // Callback.
			'hypothesis-setting-admin' // Page.
		);

		add_settings_field(
			'tx_width',
			'Default Width',
			array( $this, 'text_callback' ),
			'hypothesis-setting-admin',
			'hypothesis_pdfviewer_section',
			array( 'field' => 'tx_width', 'desc' => 'Viewer will use this width if not specified in the shortcode. Accept px or %.' )
		);

add_settings_field(
			'tx_height',
			'Default Height',
			array( $this, 'text_callback' ),
			'hypothesis-setting-admin',
			'hypothesis_pdfviewer_section',
			array( 'field' => 'tx_height', 'desc' => 'Viewer will use this height if not specified in the shortcode. Accept px or %.' )
		);

	}

	/**
	 * Sanitize each setting field as needed
	 *
	 * @param array $input Contains all settings fields as array keys.
	 */
	public function sanitize( $input ) {
		$posttypes = $this->get_posttypes();
		$new_input = array();

		//print_r($input);

		if ( isset( $input['hypothesis-base-url'] ) ) {
			$new_input['hypothesis-base-url'] = $input['hypothesis-base-url']; // TODO ? validate ???
		}

		if ( isset( $input['via-base-url'] ) ) {
			$new_input['via-base-url'] = $input['via-base-url']; // TODO ? validate ???
		}

		if ( isset( $input['highlights-on-by-default'] ) ) {
			$new_input['highlights-on-by-default'] = absint( $input['highlights-on-by-default'] );
		}

		if ( isset( $input['sidebar-open-by-default'] ) ) {
			$new_input['sidebar-open-by-default'] = absint( $input['sidebar-open-by-default'] );
		}

		if ( isset( $input['serve-pdfs-with-via'] ) ) {
			$new_input['serve-pdfs-with-via'] = absint( $input['serve-pdfs-with-via'] );
		}

		if ( isset( $input['allow-on-blog-page'] ) ) {
			$new_input['allow-on-blog-page'] = absint( $input['allow-on-blog-page'] );
		}

		if ( isset( $input['allow-on-front-page'] ) ) {
			$new_input['allow-on-front-page'] = absint( $input['allow-on-front-page'] );
		}

		foreach ( $posttypes as $slug => $name ) {
			if ( 'post' === $slug ) { // Adjust for backwards compatibility.
				$slug = 'posts';
			} elseif ( 'page' === $slug ) {
				$slug = 'pages';
			}

			if ( isset( $input[ "allow-on-$slug" ] ) ) {
				$new_input[ "allow-on-$slug" ] = absint( $input[ "allow-on-$slug" ] );
			}

			if ( 'posts' === $slug ) { // Adjust for backwards compatibility.
				$slug = 'post';
			} elseif ( 'pages' === $slug ) {
				$slug = 'page';
			}

			if ( isset( $input[ $slug . '_ids_show_h' ] ) && '' != $input[ $slug . '_ids_show_h' ] ) {
				$new_input[ $slug . '_ids_show_h' ] = explode( ',', esc_attr( $input[ $slug . '_ids_show_h' ] ) );
			}

			if ( isset( $input[ $slug . '_ids_override' ] ) && '' != $input[ $slug . '_ids_override' ] ) {
				$new_input[ $slug . '_ids_override' ] = explode( ',', esc_attr( $input[ $slug . '_ids_override' ] ) );
			}
		}

		if ( isset( $input[ 'post_cats_override' ] ) && '' != $input[ 'post_cats_override' ] ) {
			$new_input[ 'post_cats_override' ] = explode( ',', esc_attr( $input[ 'post_cats_override' ] ) );
		}

		if ( isset( $input['tx_width'] ) ) {
			$new_input['tx_width'] = sanitize_text_field($input['tx_width']);
		}

		if ( isset( $input['tx_height'] ) ) {
			$new_input['tx_height'] = sanitize_text_field($input['tx_height']);
		}

		return $new_input;
	}

	/**
	 * Print the Hypothesis Settings section text
	 */
	public function settings_section_info() {
	?>
		<p><?php esc_attr_e( 'Customize Hypothesis defaults and behavior.', 'hypothesis' ); ?></p>
	<?php }

	/**
	 * Print the Content Settings section text
	 */
	public function content_section_info() {
	?>
		<p><?php esc_attr_e( 'Control where Hypothesis is loaded.', 'hypothesis' ); ?></p>
	<?php }

	/**
	 * Callback for 'hypothesis_base_url'.
	 */
	public function hypothesis_base_url_callback() {
		$val = isset( $this->options['hypothesis-base-url'] ) ? esc_attr( $this->options['hypothesis-base-url'] ) : 'https://hypothes.is';

		printf(
			'<input type="text" id="hypothesis-base-url" name="wp_hypothesis_options[hypothesis-base-url]" value="%s" />',
			$val
		);

	}

	/**
	 * Callback for 'via_base_url'.
	 */
	public function via_base_url_callback() {
		$val = isset( $this->options['via-base-url'] ) ? esc_attr( $this->options['via-base-url'] ) : 'https://via.hypothes.is';

		printf(
			'<input type="text" id="via-base-url" name="wp_hypothesis_options[via-base-url]" value="%s" />',
			$val
		);

	}


	/**
	 * Callback for 'highlights-on-by-default'.
	 */
	public function highlights_on_by_default_callback() {
		$val = isset( $this->options['highlights-on-by-default'] ) ? esc_attr( $this->options['highlights-on-by-default'] ) : 0;

		printf(
			'<input type="checkbox" id="highlights-on-by-default" name="wp_hypothesis_options[highlights-on-by-default]" value="1" %s/>',
			checked( $val, 1, false )
		);
	}

	/**
	 * Callback for 'sidebar-open-by-default'.
	 */
	public function sidebar_open_by_default_callback() {
		$val = isset( $this->options['sidebar-open-by-default'] ) ? esc_attr( $this->options['sidebar-open-by-default'] ) : 0;
		printf(
			'<input type="checkbox" id="sidebar-open-by-default" name="wp_hypothesis_options[sidebar-open-by-default]" value="1" %s/>',
			checked( $val, 1, false )
		);
	}

	/**
	 * Callback for 'serve-pdfs-with-via'.
	 */
	public function serve_pdfs_with_via_default_callback() {
		$val = isset( $this->options['serve-pdfs-with-via'] ) ? esc_attr( $this->options['serve-pdfs-with-via'] ) : 0;
		printf(
			'<input type="checkbox" id="serve-pdfs-with-via" name="wp_hypothesis_options[serve-pdfs-with-via]" value="1" %s/>',
			checked( $val, 1, false )
		);
	}

	/**
	 * Callback for 'allow_on_blog_page'.
	 */
	public function allow_on_blog_page_callback() {
		$val = isset( $this->options['allow-on-blog-page'] ) ? esc_attr( $this->options['allow-on-blog-page'] ) : 0;
		printf(
			'<input type="checkbox" id="allow-on-blog-page" name="wp_hypothesis_options[allow-on-blog-page]" value="1" %s/>',
			checked( $val, 1, false )
		);
	}

	/**
	 * Callback for 'allow-on-front-page'.
	 */
	public function allow_on_front_page_callback() {
		$val = isset( $this->options['allow-on-front-page'] ) ? esc_attr( $this->options['allow-on-front-page'] ) : 0;
		printf(
			'<input type="checkbox" id="allow-on-front-page" name="wp_hypothesis_options[allow-on-front-page]" value="1" %s/>',
			checked( $val, 1, false )
		);
	}

	/**
	 * Callback for 'allow-on-<posttype>'.
	 */
	public function allow_on_posttype_callback( $args ) {
		$slug = $args[0];
		$val = isset( $this->options[ "allow-on-$slug" ] ) ? esc_attr( $this->options[ "allow-on-$slug" ] ) : 0;

		printf(
			'<input type="checkbox" id="allow-on-%s" name="wp_hypothesis_options[allow-on-%s]" value="1" %s/>',
			esc_attr( $slug ),
			esc_attr( $slug ),
			checked( $val, 1, false )
		);
	}

	/**
	 * Callback for '<posttype>_ids_show_h'.
	 *
	 * @param array $args An arry containing the post type slug and the post type name (plural).
	 */
	public function posttype_ids_show_h_callback( $args ) {
		$slug = $args[0];
		$val = isset( $this->options[ $slug . '_ids_show_h' ] ) ? esc_attr( implode( ',', $this->options[ $slug . '_ids_show_h' ] ) ) : '';

		printf(
			'<input type="text" id="%s_ids_show_h" name="wp_hypothesis_options[%s_ids_show_h]" value="%s" />',
			esc_attr( $slug ),
			esc_attr( $slug ),
			esc_attr( $val )
		);
	}

	/**
	 * Callback for '<posttype>_ids_override'.
	 *
	 * @param array $args An arry containing the post type slug and the post type name (plural).
	 */
	public function posttype_ids_override_callback( $args ) {
		$slug = $args[0];
		$val = isset( $this->options[ $slug . '_ids_override' ] ) ? esc_attr( implode( ',', $this->options[ $slug . '_ids_override' ] ) ) : '';

		printf(
			'<input type="text" id="%s_ids_override" name="wp_hypothesis_options[%s_ids_override]" value="%s" />',
			esc_attr( $slug ),
			esc_attr( $slug ),
			esc_attr( $val )
		);
	}

	/**
	 * Callback for 'post_cats_override'.
	 */
	public function post_cats_override_callback() {

		$val = isset( $this->options[ 'post_cats_override' ] ) ? esc_attr( implode( ',', $this->options[ 'post_cats_override' ] ) ) : '';
		printf(
			'<input type="text" id="post_cats_override" name="wp_hypothesis_options[post_cats_override]" value="%s" />',
			esc_attr( $val )
		);


	}

	public function text_callback($args) {
		$field = $args['field'];
		$desc = $args['desc'];

		$width = !empty($this->options['tx_width']) ? $this->options['tx_width'] : '600';
		$val = [
			'tx_width' => $width,
			'tx_height'=> !empty($this->options['tx_height']) ? $this->options['tx_height'] : ceil($width * 1.414)
		];

		printf(
				'<input type="text" name="wp_hypothesis_options[%1$s]" class="regular-text" value="%2$s" /><span class="description"> %3$s</span>',
				$field, $val[$field], $desc
		);
	}

	/**
	 * Add hypothesis pdf Shortcode Button to Post Editor
	 */
	public function add_quicktags() {
		if ( wp_script_is('quicktags') ){
			$width = $this->options['tx_width'];
			$height = $this->options['tx_height'];
		?>
		<script type="text/javascript">
		QTags.addButton( 'pdfviewer', 'PDF Viewer', '[pdfviewer width="<?php echo $width; ?>" height="<?php echo $height; ?>" url="" ]', '[/pdfviewer]' );
		</script>
		<?php
		}
	}

	/*
	 * Add [pdfviewer] shortcode
	 */
	public function add_shortcode( $atts, $content = "" ) {

		$atts = shortcode_atts(
			array(
				'width' => $this->options['tx_width'],
				'height' => $this->options['tx_height'],
				'url' => ''
			),
			$atts,
			'pdfviewer'
		);

		$url = isset($atts['url'])?$atts['url']:'';

		if ( !empty($url) && filter_var($url, FILTER_VALIDATE_URL) ) {

			$via_base_url = isset($this->options['via-base-url'])?$this->options['via-base-url']:"https://via.hypothes.is";
			$pdfjs_url = rtrim($via_base_url, "/") . "/" .$url;

			$pdfjs_iframe = '<iframe class="pdfjs-viewer" width="'.$atts['width'].'" height="'.$atts['height'].'" src="'.$pdfjs_url.'"></iframe> ';

			if(!empty($content)) {
				$pdfjs_iframe = "<a href=\"$url\" target=\"_blank\" rel=\"noreferrer,noopener\" >$content</a><br><br>$pdfjs_iframe";
			}

			return $pdfjs_iframe;

		} else {
			return 'Invalid URL for PDF Viewer';
		}
	}

}

// if ( is_admin() ) {
// 	$hypothesis_settings_page = new HypothesisSettingsPage();
// }

/**
 * Add Hypothesis based on conditions set in the plugin settings.
 */
add_action( 'wp', 'add_hypothesis' );

/**
 * Wrapper for the primary Hypothesis wp_enqueue call.
 */
function enqueue_hypothesis() {
	$options = get_option( 'wp_hypothesis_options' );
	$h_base_url = isset($options['hypothesis-base-url'])?$options['hypothesis-base-url']:"https://hypothes.is";
	wp_enqueue_script( 'hypothesis', "$h_base_url/embed.js", array(), false, true );
}

/**
 * Add Hypothesis script(s) to front end.
 */
function add_hypothesis() {
	$options = get_option( 'wp_hypothesis_options' );
	$posttypes = HypothesisSettingsPage::get_posttypes();

		// Set defaults if we $options is not set yet.
	if ( empty( $options ) ) :
		$default_width = !empty($content_width) ? $content_width : '600';
		$default_height = ceil($default_width * 1.414);
		$defaults = array(
			'highlights-on-by-default' => 1,
			'tx_width' => $default_width.'px',
			'tx_height' => $default_height.'px',
		);
		add_option( 'wp_hypothesis_options', $defaults );
	endif;

	// Otherwise highlighting is on by default.
	wp_enqueue_script( 'nohighlights', plugins_url( 'js/nohighlights.js', __FILE__ ), array(), false, true );

		// Embed options.
	if ( isset( $options['highlights-on-by-default'] ) ) :
		wp_enqueue_script( 'showhighlights', plugins_url( 'js/showhighlights.js', __FILE__ ), array(), false, true );
	endif;

	if ( isset( $options['sidebar-open-by-default'] ) ) :
		wp_enqueue_script( 'sidebaropen', plugins_url( 'js/sidebaropen.js', __FILE__ ), array(), false, true );
	endif;

	if ( isset( $options['serve-pdfs-with-via'] ) ) :
		$via_base_url = isset($options['via-base-url'])?$options['via-base-url']:"https://via.hypothes.is";
		wp_enqueue_script( 'pdfs-with-via', plugins_url( 'js/via-pdf.js', __FILE__ ), array(), false, true );
		wp_add_inline_script('pdfs-with-via', 'var via_base_url="'.$via_base_url.'";', 'before');

		$uploads = wp_upload_dir();
		wp_localize_script( 'pdfs-with-via', 'HypothesisPDF', array(
			'uploadsBase' => trailingslashit( $uploads['baseurl'] ),
		) );
	endif;

	// Content settings.
	$enqueue = false;

	if ( is_front_page() && isset( $options['allow-on-front-page'] ) ) {
		enqueue_hypothesis();
	} elseif ( is_home() && isset( $options['allow-on-blog-page'] ) ) {
		enqueue_hypothesis();
	}

	foreach ( $posttypes as $slug => $name ) {
		if ( 'page' !== $slug ) {
			$posttype = $slug;
			if ( 'post' === $slug ) {
				$slug = 'posts'; // Backwards compatibility.
			}
			$do_enqueue_hypothesis = false;
			if ( isset( $options[ "allow-on-$slug" ] ) && is_singular( $posttype ) ) { // Check if Hypothesis is allowed on this post type.
				if ( isset( $options[ $posttype . '_ids_override' ] ) && ! is_single( $options[ $posttype . '_ids_override' ] ) ) { // Make sure this post isn't in the override list if it exists.
					$do_enqueue_hypothesis = true;
				} elseif ( ! isset( $options[ $posttype . '_ids_override' ] ) ) {
					$do_enqueue_hypothesis = true;
				}
			} elseif ( ! isset( $options[ "allow-on-$slug" ] ) && isset( $options[ $posttype . '_ids_show_h' ] ) && is_single( $options[ $posttype . '_ids_show_h' ] ) ) { // Check if Hypothesis is allowed on this specific post.
				$do_enqueue_hypothesis = true;
			}
			if($do_enqueue_hypothesis && 'posts' === $slug && isset( $options[ 'post_cats_override' ] ) && has_category( $options[ 'post_cats_override' ] ) ) {
				$do_enqueue_hypothesis = false;
			}
			if($do_enqueue_hypothesis) {
				enqueue_hypothesis();
			}
		} elseif ( 'page' === $slug ) {
			if ( isset( $options['allow-on-pages'] ) && is_page() && ! is_front_page() && ! is_home() ) { // Check if Hypothesis is allowed on pages (and that we aren't on a special page).
				if ( isset( $options['page_ids_override'] ) && ! is_page( $options['page_ids_override'] ) ) { // Make sure this page isn't in the override list if it exists.
					enqueue_hypothesis();
				} elseif ( ! isset( $options['page_ids_override'] ) ) {
					enqueue_hypothesis();
				}
			} elseif ( ! isset( $options['allow-on-pages'] ) && isset( $options['page_ids_show_h'] ) && is_page( $options['page_ids_show_h'] ) ) { // Check if Hypothesis is allowed on this specific page.
				enqueue_hypothesis();
			}
		}
	}
}
