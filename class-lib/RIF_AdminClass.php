<?php

/**
 *
 * Class RSS Image Feed Admin
 *
 * @ RSS Image Feed
 *
 * building admin page
 *
 */
class RIF_Admin extends A5_OptionPage {

	static $options;

	function __construct($multisite) {

		add_action('admin_init', array($this, 'initialize_settings'));

		if (WP_DEBUG == true) add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));

		if ($multisite) :

			add_action('network_admin_menu', array($this, 'add_site_admin_menu'));

			self::$options = get_network_option(NULL, 'rss_options');

		else :

			add_action('admin_menu', array($this, 'add_admin_menu'));

			self::$options = get_option('rss_options');

		endif;

	}

	/**
	 *
	 * Make debug info collapsable
	 *
	 */
	function enqueue_scripts($hook){

		if ($hook != 'plugins_page_rss-image-feed') return;

		wp_enqueue_script('dashboard');

		if (wp_is_mobile()) wp_enqueue_script('jquery-touch-punch');

	}

	/**
	 *
	 * Add options-page for single site
	 *
	 */
	function add_admin_menu() {

		add_plugins_page('RSS Image Feed', '<img alt="" src="'.plugins_url('rss-image-feed/img/a5-icon-11.png').'"> RSS Image Feed', 'administrator', 'rss-image-feed', array($this, 'build_options_page'));

	}

	/**
	 *
	 * Add menu page for multisite
	 *
	 */
	function add_site_admin_menu() {

		add_menu_page('RSS Image Feed', 'RSS Image Feed', 'administrator', 'rss-image-feed', array($this, 'build_options_page'), plugins_url('rss-image-feed/img/a5-icon-16.png'));

	}

	/**
	 *
	 * Actually build the option pages
	 *
	 */
	function build_options_page() {

		// this is only necessary if the plugin is activated for network

		if (@$_GET['action'] == 'update') :

			$input = $_POST['rss_options'];

			self::$options = $this->validate_options($input);

			update_network_option(NULL, 'rss_options', self::$options);

			$this->initialize_settings();

		endif;

		// the main options page begins here

		$eol = "\n";

		parent::open_page('Feed Images', __('http://wasistlos.waldemarstoffel.com/plugins-fur-wordpress/rss-image-feed', 'rss-image-feed'), 'rss-image-feed', __('Plugin Support', 'rss-image-feed'));

		_e('Define the size of the images and summary in your feed.', 'rss-image-feed');

		settings_errors();

		$action = (is_plugin_active_for_network(RIF_BASE)) ? '?page=rss-image-feed&action=update' : 'options.php';

		parent::open_form($action);

		settings_fields('rss_options');
		do_settings_sections('new_image_settings');

		submit_button();

		if (WP_DEBUG === true) :

			self::open_tab();

			self::sortable('deep-down', self::debug_info(self::$options, __('Debug Info', 'rss-image-feed')));

			if (count(self::$options['cache']) > 0) self::sortable('cache-info', self::cache_info(self::$options['cache'], __('Cache', 'rss-image-feed')));

			if (true == WP_DEBUG_LOG) self::sortable('errorlog-info', self::debug_log_info(__('Error Log', 'rss-image-feed'), 'rss_debug_settings'));

			self::close_tab();

		endif;

		parent::close_page();

	}

	/**
	 *
	 * Initialize the admin screen of the plugin
	 *
	 */
	function initialize_settings() {

		register_setting('rss_options', 'rss_options', array($this, 'validate_options'));

		add_settings_section('image_rss_settings', __('RSS Settings', 'rss-image-feed'), array($this, 'display_section'), 'new_image_settings');

		add_settings_field('image_size', __('Image Size:', 'rss-image-feed'), array($this, 'display_imgsize'), 'new_image_settings', 'image_rss_settings');

		add_settings_field('responsive', __('Make the size relative:', 'rss-image-feed'), array($this, 'display_responsive'), 'new_image_settings', 'image_rss_settings');

		add_settings_field('image_number', __('Image Number:', 'rss-image-feed'), array($this, 'display_imgnmbr'), 'new_image_settings', 'image_rss_settings');

		add_settings_field('media_tag', __('Add the &#34;media:content&#34; tag:', 'rss-image-feed'), array($this, 'display_media_content'), 'new_image_settings', 'image_rss_settings');

		add_settings_field('enclosure_tag', __('Add the &#34;enclosure&#34; tag:', 'rss-image-feed'), array($this, 'display_enclosure'), 'new_image_settings', 'image_rss_settings');

		add_settings_field('notext', __('Don&#39t show content:', 'rss-image-feed'), array($this, 'display_notext'), 'new_image_settings', 'image_rss_settings');

		add_settings_field('force_excerpt', __('Force Excerpt:', 'rss-image-feed'), array($this, 'display_force'), 'new_image_settings', 'image_rss_settings');

		add_settings_field('excerpt_size', __('Limit Excerpt:', 'rss-image-feed'), array($this, 'display_excptsize'), 'new_image_settings', 'image_rss_settings');

		$cachesize = count(self::$options['cache']);

		$entry = ($cachesize > 1) ? __('entries', 'rss-image-feed') : __('entry', 'rss-image-feed');

		if ($cachesize > 0) add_settings_field('reset_cache', sprintf(__('Empty cache (%d %s):', 'rss-image-feed'), count(self::$options['cache']), $entry), array($this, 'reset_field'), 'new_image_settings', 'image_rss_settings', array(__('You can empty the plugin&#39;s cache here, if necessary.', 'rss-image-feed')));

		if (true == WP_DEBUG && true == WP_DEBUG_LOG) :

			$filename = WP_CONTENT_DIR.'/debug.log';

			$errorlog = file($filename);

			$logsize = count($errorlog);

			$entry = ($logsize > 1) ? __('entries', 'rss-image-feed') : __('entry', 'rss-image-feed');

			if ($logsize > 0) :

				add_settings_section('image_rss_settings', sprintf(__('Empty debug log (%d %s):', 'rss-image-feed'), count($errorlog), $entry), array($this, 'display_reset_section'), 'rss_debug_settings');

				add_settings_field('reset_debug_log', __('You can empty the debug log here, if necessary.', 'rss-image-feed'), array($this, 'reset_debug_field'), 'rss_debug_settings', 'image_rss_settings');

			endif;

		endif;

	}

	function display_section() {

		self::tag_it(__('Change the size of the image and the excerpt here.', 'rss-image-feed'), 'p');

	}

	function display_imgsize() {

		a5_number_field('image_size', 'rss_options[image_size]', self::$options['image_size'], __('Give here only the longest side of the image. The smaller side will be counted on displaying the image. There will be no cropping.', 'rss-image-feed'), array('step' => 1));

	}

	function display_responsive() {

		a5_checkbox('responsive', 'rss_options[responsive]', @self::$options['responsive'], __('Click, to make image size relativ and not static. This might make the feed more responsive.', 'rss-image-feed'));

	}

	function display_media_content() {

		a5_checkbox('media_content', 'rss_options[media_content]', @self::$options['media_content'], __('Click, to include the &#34;media:content&#34; tag in the feed.', 'rss-image-feed'));

	}

	function display_enclosure() {

		a5_checkbox('enclosure', 'rss_options[enclosure]', @self::$options['enclosure'], __('Click, to include the &#34;enclosure&#34; tag in the feed.', 'rss-image-feed'));

	}

	function display_imgnmbr() {

		a5_text_field('image_number', 'rss_options[image_number]', self::$options['image_number'], sprintf(__('To use an image of the post instead of the post thumbnail, enter the number of that image. The word %s will bring the last image of the post.', 'rss-image-feed'), '&#39;last&#39;'));

	}

	function display_notext() {

		a5_checkbox('notext', 'rss_options[notext]', @self::$options['notext'], __('Click, to not show post content.', 'rss-image-feed'));

	}

	function display_force() {

		a5_checkbox('force_excerpt', 'rss_options[force_excerpt]', self::$options['force_excerpt'], __('Click, to limit the post content to a summary if the post doesn&#39;t have an excerpt.', 'rss-image-feed'));

	}

	function display_excptsize() {

		a5_number_field('excerpt_size', 'rss_options[excerpt_size]', self::$options['excerpt_size'], __('How long should the summary of the article be? Enter the number of sentences here.', 'rss-image-feed'), array('step' => 1));

	}

	function reset_field($labels) {

		a5_checkbox('reset_cache', 'rss_options[reset_cache]', @self::$options['reset_cache'], $labels[0]);

	}

	function display_reset_section() {

		self::tag_it(__('Empty the debug log.', 'rss-image-feed'), 'p');

	}

	function reset_debug_field() {

		submit_button(__('OK', 'rss-image-feed'), 'secondary', 'rss_options[reset_debug_log]', true, array('id' => 'reset_debug_log'));

	}

	function validate_options($input) {

		$newinput['image_size'] = trim($input['image_size']);
		$newinput['responsive'] = (isset($input['responsive'])) ? true : false;
		$newinput['media_content'] = (isset($input['media_content'])) ? true : false;
		$newinput['enclosure'] = (isset($input['enclosure'])) ? true : false;
		$newinput['image_number'] = trim($input['image_number']);
		$newinput['notext'] = (isset($input['notext'])) ? true : false;
		$newinput['force_excerpt'] = (isset($input['force_excerpt'])) ? true : false;
		$newinput['excerpt_size'] = trim($input['excerpt_size']);

		if(!is_numeric($newinput['image_size'])) :

			add_settings_error('rss_options', 'not-numeric-image-size', __('Please enter a numeric value for the image size.', 'rss-image-feed'), 'error');

			$newinput['image_size'] = 300;

		endif;

		if(!is_numeric($newinput['excerpt_size'])) :

			add_settings_error('rss_options', 'not-numeric-excerpt-size', __('Please enter a numeric value for the excerpt length.', 'rss-image-feed'), 'error');

			$newinput['excerpt_size'] = 3;

		endif;

		$newinput['excerpt_size'] = intval($newinput['excerpt_size']);

		if($newinput['image_size'] > 9999) :

			add_settings_error('rss_options', 'too-large-image-size', __('Imagesize too large. Please choose a value smaller than 10000.', 'rss-image-feed'), 'error');

			$newinput['image_size'] = 300;

		endif;

		$newinput['image_size'] = intval($newinput['image_size']);

		if ($newinput['image_size'] != self::$options['image_size']) add_image_size( 'rss-image', $newinput['image_size'], $newinput['image_size'] );

		if(!empty($newinput['image_number']) && !is_numeric($newinput['image_number'])) :

			$newinput['image_number'] = 'last';

		endif;

		self::$options['image_size'] = $newinput['image_size'];
		self::$options['responsive'] = $newinput['responsive'];
		self::$options['media_content'] = $newinput['media_content'];
		self::$options['enclosure'] = $newinput['enclosure'];
		self::$options['notext'] = $newinput['notext'];
		self::$options['force_excerpt'] = $newinput['force_excerpt'];
		self::$options['excerpt_size'] = $newinput['excerpt_size'];
		self::$options['image_number'] = $newinput['image_number'];

		if (isset($input['reset_cache'])) add_settings_error('rss_options', 'empty-cache', __('Cache emptied.', 'rss-image-feed'), 'updated');

		self::$options['cache'] = array();

		if (isset($input['reset_debug_log'])) :

			$filename = WP_CONTENT_DIR.'/debug.log';

			file_put_contents($filename, '');

			add_settings_error('rss_options', 'empty-debug', __('Debug Log emptied.', 'rss-image-feed'), 'updated');

		endif;

		return self::$options;

	}

} // end of class
