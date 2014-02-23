<?php
/*
Plugin Name: RSS Image Feed 
Plugin URI: http://wasistlos.waldemarstoffel.com/plugins-fur-wordpress/image-feed
Description: RSS Image Feed is not literally producing a feed of images but it adds the first image of the post to the normal feeds of your blog. Those images display even if you have the summary in the feed and not the content.
Version: 3.2
Author: Waldemar Stoffel
Author URI: http://www.waldemarstoffel.com
License: GPL3
Text Domain: rss-image-feed
*/

/*  Copyright 2011 - 2014 Waldemar Stoffel  (email : stoffel@atelier-fuenf.de)

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.

*/


/* Stop direct call */

if(preg_match('#' . basename(__FILE__) . '#', $_SERVER['PHP_SELF'])) die('Sorry, you don\'t have direct access to this page.');

define( 'RIF_PATH', plugin_dir_path(__FILE__) );

if (!class_exists('A5_Excerpt')) require_once RIF_PATH.'class-lib/A5_ExcerptClass.php';
if (!class_exists('A5_Image')) require_once RIF_PATH.'class-lib/A5_ImageClass.php';
if (!class_exists('A5_FormField')) require_once RIF_PATH.'class-lib/A5_FormFieldClass.php';


class Rss_Image_Feed {
	
	const language_file = 'rss-image-feed';
	
	private static $options;
	
	function __construct(){
	
		/* hooking into the feed for content and excerpt */
	
		add_filter('the_excerpt_rss', array($this, 'add_image_excerpt'));
		add_filter('the_content_feed', array($this, 'add_image_content'));
		
		//Additional links on the plugin page
	
		add_action('admin_init', array($this, 'init'));
		
		load_plugin_textdomain(self::language_file, false , basename(dirname(__FILE__)).'/languages');
		
		register_activation_hook(  __FILE__, array($this, 'install') );
		register_deactivation_hook(  __FILE__, array($this, 'uninstall') );
		
		if (is_multisite()) :
		
			$plugins = get_site_option('active_sitewide_plugins');
			
			if (isset($plugins[plugin_basename(__FILE__)])) :
		
				add_action('network_admin_menu', array($this, 'add_site_admin_menu'));
				
				self::$options = get_site_option('rss_options');
				
			else :
			
				add_action('admin_menu', array($this, 'add_admin_menu'));
			
				self::$options = get_option('rss_options');
				
			endif;
			
		else:
			
			add_action('admin_menu', array($this, 'add_admin_menu'));
			
			self::$options = get_option('rss_options');
		
		endif;	
		
	}
	
	function register_links($links, $file) {
		
		$base = plugin_basename(__FILE__);
		
		if ($file == $base) :
		
			$links[] = '<a href="http://wordpress.org/extend/plugins/rss-image-feed/faq/" target="_blank">'.__('FAQ', self::language_file).'</a>';
			$links[] = '<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=LLUFQDHG33XCE" target="_blank">'.__('Donate', self::language_file).'</a>';
		
		endif;
		
		return $links;
	
	}
	
	function register_action_links( $links, $file ) {
		
		$base = plugin_basename(__FILE__);
		
		if ($file == $base) array_unshift($links, '<a href="'.admin_url('plugins.php?page=set-feed-imgage-size').'">'.__('Settings', self::language_file).'</a>');
	
		return $links;
	
	}
	
	
	/**
	 *
	 * init
	 *
	 */
	function init() {
		
		register_setting('rss_options', 'rss_options', array($this, 'validate_input'));
		
		add_settings_section('image_rss_settings', __('RSS Settings', self::language_file), array($this, 'display_section'), 'new_image_settings');
		
		add_settings_field('image_size', __('Imagesize:', self::language_file), array($this, 'display_imgsize'), 'new_image_settings', 'image_rss_settings');
		
		add_settings_field('force_excerpt', __('Force Excerpt:', self::language_file), array($this, 'display_force'), 'new_image_settings', 'image_rss_settings');
		
		add_settings_field('excerpt_size', __('Limit Excerpt:', self::language_file), array($this, 'display_excptsize'), 'new_image_settings', 'image_rss_settings');
	
	}
	
	function display_section() {
		
		echo '<p>'.__('Change the size of the image and the excerpt here.', self::language_file).'</p>';
	
	}
	
	function display_imgsize() {
		
		a5_number_field('image_size', 'rss_options[image_size]', self::$options['image_size'], __('Give here only the longest side of the image. The smaller side will be counted on displaying the image. There will be no cropping.', self::language_file), array('step' => 1));
		
	}
	
	function display_force() {
		
		a5_checkbox('force_excerpt', 'rss_options[force_excerpt]', self::$options['force_excerpt'], __('Click, to limit the post content to a summary if the post doesn&#39;t have an excerpt.', self::language_file));
		
	}
	
	function display_excptsize() {
		
		a5_number_field('excerpt_size', 'rss_options[excerpt_size]', self::$options['excerpt_size'], __('How long should the summary of the article be? Enter the number of sentences here.', self::language_file), array('step' => 1));
		
	}
	
	// Setting some default values
	
	function install() {
		
		$screen = get_current_screen();
		
		self::$options = array(
			'tags' => array(),
			'sizes' => array(),
			'image_size' => 200,
			'force_excerpt' => false,
			'excerpt_size' => 3
		);
		
		if (is_multisite() && $screen->is_network) :
		
			self::$options['sitewide'] = true; 
		
			add_site_option('rss_options', self::$options);
		
		else : 
		
			self::$options['sitewide'] = false;
		
			add_option('rss_options', self::$options);
		
		endif;
		
	}
	
	// Deleting the option
	
	function uninstall() {
		
		$screen = get_current_screen();
		
		if (is_multisite() && $screen->is_network) :
		
			delete_site_option('rss_options');
		
		else :
		
			delete_option('rss_options');
		
		endif;
		
	}
	
	// Installing options page
	
	function add_admin_menu() {
		
		add_plugins_page('RSS Image Feed', '<img alt="" src="'.plugins_url('rss-image-feed/img/a5-icon-11.png').'"> RSS Image Feed', 'administrator', 'set-feed-imgage-size', array($this, 'rif_options_page'));
		
	}
	
	/**
	 *
	 * Add menu page for multisite
	 *
	 */
	function add_site_admin_menu() {
		
		add_menu_page('RSS Image Feed', 'RSS Image Feed', 'administrator', 'set-feed-imgage-size', array($this, 'rif_options_page'), plugins_url('rss-image-feed/img/a5-icon-16.png'));
		
	}
	
	// Calling the options page
	
	function rif_options_page() {
		
		?>
		
		<div class="wrap">
        <a href="<?php _e('http://wasistlos.waldemarstoffel.com/plugins-fur-wordpress/image-feed', self::language_file); ?>"><div id="a5-logo" class="icon32" style="background: url('<?php echo plugins_url('rss-image-feed/img/a5-icon-34.png');?>');"></div></a>
		<h2>Feed Images</h2>
        <?php _e('Define the size of the images and summary in your feed.', self::language_file); ?>
		<?php settings_errors(); ?>
		
		<form action="options.php" method="post">
		
		<?php
        
		settings_fields('rss_options');
		do_settings_sections('new_image_settings');
		
		submit_button(); ?>
		</form></div>
		
		<?php
	}
	
	function validate_input($input) {
		
		$newinput['image_size'] = trim($input['image_size']);
		$newinput['force_excerpt'] = (isset($input['force_excerpt'])) ? true : false;
		$newinput['excerpt_size'] = trim($input['excerpt_size']);
		
			if(!is_numeric($newinput['image_size'])) :
			
				add_settings_error('rss_options', 'not-numeric-image-size', __('Please enter a numeric value for the image size.', self::language_file), 'error');
				
				$newinput['image_size'] = 200;
				
			endif;
			
			$newinput['image_size'] = intval($newinput['image_size']);
			
			if(!is_numeric($newinput['excerpt_size'])) :
			
				add_settings_error('rss_options', 'not-numeric-excerpt-size', __('Please enter a numeric value for the excerpt length.', self::language_file), 'error');
				
				$newinput['excerpt_size'] = 3;
				
			endif;
			
			$newinput['excerpt_size'] = intval($newinput['excerpt_size']);
				
			if($newinput['image_size'] > 999) :
			
				add_settings_error('rss_options', 'too-large-image-size', __('Imagesize too large. Please choose a value smaller than 1000.', self::language_file), 'error');
				
				$newinput['image_size'] = 200;
				
			endif;
			
		self::$options['image_size'] = $newinput['image_size'];
		self::$options['force_excerpt'] = $newinput['force_excerpt'];
		self::$options['excerpt_size'] = $newinput['excerpt_size'];
	
		return self::$options;
	
	}
	
	function add_image_excerpt($output){
		
		$rif_text = strip_tags(strip_shortcodes(get_the_content()));
		
		if ($rif_text != $output && true === self::$options['force_excerpt']) :
		
			$args = array(
				'content' => $rif_text,
				'count' => self::$options['excerpt_size']
			);
		
			$output = A5_Excerpt::text($args);
		
		endif;
		
		$output = $this->get_feed_image().$output;
		
		return $output;
	
	}
	
	function add_image_content($content){
		
		$rif_text = strip_shortcodes(get_the_content());
		
		$imagetag = $this->get_feed_image();
		
		if (true === self::$options['force_excerpt']) :
		
			$args = array(
				'content' => $rif_text,
				'count' => self::$options['excerpt_size']
			);
		
			$rif_text = A5_Excerpt::text($args);
			
		endif;
			
		$content = $imagetag.$rif_text;
			
		return $content;
	
	}
	
	// extracting the first image of the post
	
	function get_feed_image() {
		
		$rif_max = self::$options['image_size'];
		
		global $post;
		
		$img_container = '';
		
		$rif_tags = A5_Image::tags($post, 'rss_options', self::language_file, self::$options['sitewide']);
	
		$rif_image_alt = $rif_tags['image_alt'];
		$rif_image_title = $rif_tags['image_title'];
		$rif_title_tag = $rif_tags['title_tag'];
		
		$args = array (
			'content' => get_the_content(),
			'width' => $rif_max,
			'height' => $rif_max,
			'option' => 'rss_options',
			'sitewide' => self::$options['sitewide']
		);
		   
		$rif_image_info = A5_Image::thumbnail($args);
		
		$rif_thumb = $rif_image_info['thumb'];
		
		$rif_width = $rif_image_info['thumb_width'];
	
		$rif_height = $rif_image_info['thumb_height'];
		
		if ($rif_thumb) :
		
			$eol = "\r\n";
			$tab = "\t";
		
			if ($rif_width) $rif_img_tag = '<a href="'.get_permalink().'" title="'.$rif_image_title.'"><img title="'.$rif_image_title.'" src="'.$rif_thumb.'" alt="'.$rif_image_alt.'" width="'.$rif_width.'" height="'.$rif_height.'" /></a>';
				
			else $rif_img_tag = '<a href="'.get_permalink().'" title="'.$rif_image_title.'"><img title="'.$rif_image_title.'" src="'.$rif_thumb.'" alt="'.$rif_image_alt.'" style="maxwidth: '.$rif_max.'; maxheight: '.$rif_max.';" /></a>';
			
			$img_container=$eol.$tab.'<div>'.$eol.$tab.$rif_img_tag.$eol.$tab.'</div>'.$eol.$tab.'<br/>'.$eol.$tab;
			
		endif;
		
		return $img_container;
		
	}
	
}

$rss_image_feed = new Rss_Image_Feed;

?>