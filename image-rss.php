<?php
/*
Plugin Name: RSS Image Feed 
Plugin URI: http://wasistlos.waldemarstoffel.com/plugins-fur-wordpress/image-feed
Description: RSS Image Feed is not literally producing a feed of images but it adds the first image of the post to the normal feeds of your blog. Those images display even if you have the summary in the feed and not the content.
Version: 3.3
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

# loading the framework
if (!class_exists('A5_Excerpt')) require_once RIF_PATH.'class-lib/A5_ExcerptClass.php';
if (!class_exists('A5_Image')) require_once RIF_PATH.'class-lib/A5_ImageClass.php';
if (!class_exists('A5_FormField')) require_once RIF_PATH.'class-lib/A5_FormFieldClass.php';
if (!class_exists('A5_OptionPage')) require_once RIF_PATH.'class-lib/A5_OptionPageClass.php';

#loading plugin specific class
if (!class_exists('RIF_Admin')) require_once RIF_PATH.'class-lib/RIF_AdminClass.php';

class Rss_Image_Feed {
	
	const language_file = 'rss-image-feed', version = '3.3';
	
	private static $options;
	
	function __construct(){
	
		/* hooking into the feed for content and excerpt */
	
		add_filter('the_excerpt_rss', array(&$this, 'add_image_excerpt'));
		add_filter('the_content_feed', array(&$this, 'add_image_content'));
		
		//Additional links on the plugin page
		add_filter('plugin_row_meta', array(&$this, 'register_links'), 10, 2);	
		add_filter('plugin_action_links', array(&$this, 'register_action_links'), 10, 2);
	
		load_plugin_textdomain(self::language_file, false , basename(dirname(__FILE__)).'/languages');
		
		add_action('init', array(&$this, 'speedy'), 9999);
		add_action('admin_head', array(&$this, 'speedy'), 9999);
		add_action('wp_head', array(&$this, 'speedy'), 9999);
		
		register_activation_hook(  __FILE__, array(&$this, 'install') );
		register_deactivation_hook(  __FILE__, array(&$this, 'uninstall') );
		
		if (is_multisite()) :
		
			$plugins = get_site_option('active_sitewide_plugins');
			
			if (isset($plugins[plugin_basename(__FILE__)])) :
		
				self::$options = get_site_option('rss_options');
				
				if (self::version != self::$options['version']) :
				
					self::$options['version'] = self::version;
					
					unset(self::$options['tags'], self::$options['sizes']);
					
					self::$options['cache'] = array();
					
					update_site_option('rss_options', self::$options);
				
				endif;
				
			else :
			
				self::$options = get_option('rss_options');
				
				if (self::version != self::$options['version']) :
				
					self::$options['version'] = self::version;
					
					unset(self::$options['tags'], self::$options['sizes']);
					
					self::$options['cache'] = array();
					
					update_option('rss_options', self::$options);
				
				endif;
				
			endif;
			
		else:
			
			self::$options = get_option('rss_options');
			
			if (self::version != self::$options['version']) :
				
				self::$options['version'] = self::version;
				
				unset(self::$options['tags'], self::$options['sizes']);
				
				self::$options['cache'] = array();
				
				update_option('rss_options', self::$options);
			
			endif;
		
		endif;
		
		add_image_size('rss-image', self::$options['image_size'], self::$options['image_size']);
		
		$RIF_Admin = new RIF_Admin(self::$options['sitewide']);
		
	}
	
	/**
	 * 
	 * Trying to make things faster by flushing. (Not sure whether it works)
	 *
	 */
	function speedy() {
		
		flush();
	
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
	
	// Setting some default values
	
	function install() {
		
		$screen = get_current_screen();
		
		self::$options = array(
			'image_size' => 200,
			'force_excerpt' => false,
			'excerpt_size' => 3,
			'version' => self::version,
			'sitewide' => false,
			'cache' => array()
		);
		
		if (is_multisite() && $screen->is_network) :
		
			self::$options['sitewide'] = true; 
		
			add_site_option('rss_options', self::$options);
		
		else : 
		
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
	
	function add_image_excerpt($output){
		
		$rif_text = strip_tags(strip_shortcodes(get_the_content()));
		
		if ($rif_text != $output && true === self::$options['force_excerpt']) :
		
			$output = $this->get_feed_excerpt($rif_text);
		
		endif;
		
		$output = $this->get_feed_image().$output;
		
		return $output;
	
	}
	
	function add_image_content($content){
		
		$rif_text = strip_shortcodes(get_the_content());
		
		$imagetag = $this->get_feed_image();
		
		if (true === self::$options['force_excerpt']) :
		
			$rif_text = $this->get_feed_excerpt($rif_text);
			
		endif;
			
		$content = $imagetag.$rif_text;
			
		return $content;
	
	}
	
	// extracting the first image of the post
	
	function get_feed_image() {
		
		$id = get_the_ID();
		
		$img_container = '';
		
		$rif_tags = A5_Image::tags(self::language_file);
	
		$rif_image_alt = $rif_tags['image_alt'];
		$rif_image_title = $rif_tags['image_title'];
		$rif_title_tag = $rif_tags['title_tag'];
		
		$args = array (
			'id' => $id,
			'option' => 'rss_options',
			'image_size' => 'rss-image',
			'multisite' => self::$options['sitewide']
		);
		   
		$rif_image_info = A5_Image::thumbnail($args);
		
		if ($rif_image_info) :
		
			$rif_thumb = $rif_image_info[0];
			
			$rif_width = $rif_image_info[1];
		
			$rif_height = $rif_image_info[2];
		
			$eol = "\r\n";
			$tab = "\t";
		
			if ($rif_width) $rif_img_tag = '<a href="'.get_permalink().'" title="'.$rif_image_title.'"><img title="'.$rif_image_title.'" src="'.$rif_thumb.'" alt="'.$rif_image_alt.'" width="'.$rif_width.'" height="'.$rif_height.'" /></a>';
				
			else $rif_img_tag = '<a href="'.get_permalink().'" title="'.$rif_image_title.'"><img title="'.$rif_image_title.'" src="'.$rif_thumb.'" alt="'.$rif_image_alt.'" style="maxwidth: '.$rif_max.'; maxheight: '.$rif_max.';" /></a>';
			
			$img_container=$eol.$tab.'<div>'.$eol.$tab.$rif_img_tag.$eol.$tab.'</div>'.$eol.$tab.'<br/>'.$eol.$tab;
			
		endif;
		
		return $img_container;
		
	}
	
	// getting excerpt if forced
	
	function get_feed_excerpt($text) {
		
		$cache = array();
	
		$args = array(
			'content' => $text,
			'count' => self::$options['excerpt_size']
		);
		
		return A5_Excerpt::text($args);
		
	}
	
}

$rss_image_feed = new Rss_Image_Feed;

?>