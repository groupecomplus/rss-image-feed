<?php
/*
Plugin Name: RSS Image Feed
Plugin URI: http://wasistlos.waldemarstoffel.com/plugins-fur-wordpress/image-feed
Description: RSS Image Feed is not literally producing a feed of images but it adds the first image of the post to the normal feeds of your blog. Those images display even if you have the summary in the feed and not the content.
Version: 4.2.5
Author: Stefan Crämer
Author URI: http://www.stefan-craemer.com
License: GPL3
Text Domain: rss-image-feed
Domain Path: /languages
*/

/*  Copyright 2011 - 2016 Stefan Crämer (email : support@atelier-fuenf.de)

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

defined('ABSPATH') OR exit;

if (!defined('RIF_PATH')) define( 'RIF_PATH', plugin_dir_path(__FILE__) );
if (!defined('RIF_BASE')) define( 'RIF_BASE', plugin_basename(__FILE__) );


# loading the framework
if (!class_exists('A5_Excerpt')) require_once RIF_PATH.'class-lib/A5_ExcerptClass.php';
if (!class_exists('A5_Image')) require_once RIF_PATH.'class-lib/A5_ImageClass.php';
if (!class_exists('A5_FormField')) require_once RIF_PATH.'class-lib/A5_FormFieldClass.php';
if (!class_exists('A5_OptionPage')) require_once RIF_PATH.'class-lib/A5_OptionPageClass.php';

#loading plugin specific class
if (!class_exists('RIF_Admin')) require_once RIF_PATH.'class-lib/RIF_AdminClass.php';

class Rss_Image_Feed {

	const version = '4.2';

	private static $options;

	function __construct(){

		/* hooking into the feed for content and excerpt */

		add_filter('the_excerpt_rss', array($this, 'add_image_excerpt'));
		add_filter('the_content_feed', array($this, 'add_image_content'));

		//Additional links on the plugin page
		add_filter('plugin_row_meta', array($this, 'register_links'), 10, 2);
		add_filter('plugin_action_links', array($this, 'register_action_links'), 10, 2);

		add_filter('image_size_names_choose', array($this, 'rss_image_to_menu'));

		add_action('save_post', array($this, 'flush_widget_cache'));
		add_action('deleted_post', array($this, 'flush_widget_cache'));
		add_action('switch_theme', array($this, 'flush_widget_cache'));

		load_plugin_textdomain('rss-image-feed', false , basename(dirname(__FILE__)).'/languages');

		register_activation_hook(  __FILE__, array($this, '_install') );
		register_deactivation_hook(  __FILE__, array($this, '_uninstall') );

		if (is_multisite()) :

			$plugins = get_network_option(NULL, 'active_sitewide_plugins');

			if (isset($plugins[RIF_BASE])) :

				self::$options = get_network_option(NULL, 'rss_options');

				if (self::version != self::$options['version']) :

					self::$options['version'] = self::version;

					update_network_option(NULL, 'rss_options', self::$options);

				endif;

			else :

				$plugins = get_option('active_plugins');

				if (in_array(RIF_BASE, $plugins)) :

					self::$options = get_option('rss_options');

					if (self::version != self::$options['version']) :

						self::$options['version'] = self::version;

						update_option('rss_options', self::$options);

					endif;

				endif;

			endif;

		else:

			$plugins = get_option('active_plugins');

			if (in_array(RIF_BASE, $plugins)) :

				self::$options = get_option('rss_options');

				if (self::version != self::$options['version']) :

					self::$options['version'] = self::version;

					update_option('rss_options', self::$options);

				endif;

			endif;

		endif;

		add_image_size('rss-image', self::$options['image_size'], self::$options['image_size']);

		if (true == WP_DEBUG):

			add_action('wp_before_admin_bar_render', array($this, 'admin_bar_menu'));

		endif;

		if (@self::$options['media_content'] || @self::$options['enclosure']) :

			add_action( "rss2_ns", array( $this, 'set_feed_NameSpace') );
			add_action( "rss_item", array( $this, 'add_media_enclosure' ), 5, 1 );
			add_action( "rss2_item", array( $this, 'add_media_enclosure' ), 5, 1 );

		endif;

		add_action( "rss_head", array( $this, 'add_fav_icon' ) );
		add_action( "rss2_head", array( $this, 'add_fav_icon' ) );

		$RIF_Admin = new RIF_Admin(self::$options['sitewide']);

	}

	function register_links($links, $file) {

		if ($file == RIF_BASE) :

			$links[] = '<a href="http://wordpress.org/extend/plugins/rss-image-feed/faq/" target="_blank">'.__('FAQ', 'rss-image-feed').'</a>';
			$links[] = '<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=LLUFQDHG33XCE" target="_blank">'.__('Donate', 'rss-image-feed').'</a>';

		endif;

		return $links;

	}

	function register_action_links($links, $file) {

		if ($file == RIF_BASE) array_unshift($links, '<a href="'.admin_url('plugins.php?page=rss-image-feed').'">'.__('Settings', 'rss-image-feed').'</a>');

		return $links;

	}

	function rss_image_to_menu($sizes) {

		return array_merge($sizes, array('rss-image' => __('RSS Image', 'rss-image-feed')));

	}

	// Setting some default values

	function _install() {

		$screen = get_current_screen();

		$defaults = array(
			'image_size' => 300,
			'force_excerpt' => false,
			'excerpt_size' => 3,
			'version' => self::version,
			'sitewide' => false,
			'responsive' => false,
			'enclosure' => false,
			'media_content' => false,
			'notext' => false,
			'cache' => array(),
			'css' => false,
			'image_number' => false
		);

		if (is_multisite() && $screen->is_network) :

			$defaults['sitewide'] = true;

			add_network_option(NULL, 'rss_options', $defaults);

		else :

			add_option('rss_options', $defaults);

		endif;

	}

	// Deleting the option

	function _uninstall() {

		$screen = get_current_screen();

		if (is_multisite() && $screen->is_network) :

			delete_network_option(NULL, 'rss_options');

		else :

			delete_option('rss_options');

		endif;

	}

	function add_image_excerpt($output){

		if (!is_feed()) return $output;

		if (isset(self::$options['notext']) && self::$options['notext']) $output = '';

		if (true === self::$options['force_excerpt']) :

			$args = array(
				'content' => $output,
				'count' => self::$options['excerpt_size']
			);

			$output = A5_Excerpt::text($args);

		endif;

		$imagetag = $this->get_feed_image();

		return $imagetag.$output;

	}

	function add_image_content($content){

		if (!is_feed()) return $content;

		if (isset(self::$options['notext']) && self::$options['notext']) $content = '';

		$args = array(
			'content' => $content,
			'count' => 9999,
			'shortcode' => true,
			'format' => true,
			'links' => true
		);

		$imagetag = $this->get_feed_image();

		if (true === self::$options['force_excerpt']) $args['count'] = self::$options['excerpt_size'];

		$content = A5_Excerpt::text($args);

		return $imagetag.$content;

	}

	// extracting the first image of the post

	function get_feed_image() {

		$id = get_the_ID();

		$img_container = @self::$options['cache'][$id]['image'];

		if (empty($img_container) && false !== $img_container) :

			$img_container = false;

			$rif_tags = A5_Image::tags();

			$rif_image_alt = $rif_tags['image_alt'];
			$rif_image_title = $rif_tags['image_title'];
			$rif_title_tag = $rif_tags['title_tag'];

			$args = array (
				'id' => $id,
				'image_size' => 'rss-image'
			);

			if (self::$options['image_number']) $args['number'] = self::$options['image_number'];

			$rif_image_info = A5_Image::thumbnail($args);

			if ($rif_image_info) :

				$rif_thumb = $rif_image_info[0];

				$rif_width = $rif_image_info[1];

				$rif_height = ($rif_image_info[2]) ? ' height="'.$rif_image_info[2].'"' :'';

				$rif_image_size = '" width="'.$rif_width.'"'.$rif_height;

				if (isset(self::$options['responsive']) && self::$options['responsive']) $rif_image_size .= ' style="max-width: 100%; height: auto;"';

				$eol = "\n";
				$tab = "\t";

				$rif_img_tag = '<a href="'.get_permalink().'"><img title="'.$rif_image_title.'" src="'.$rif_thumb.'" alt="'.$rif_image_alt.$rif_image_size.' /></a>';

				$img_container=$eol.$tab.'<div>'.$eol.$tab.$rif_img_tag.$eol.$tab.'</div>'.$eol.$tab;

			endif;

			self::$options['cache'][$id]['image'] = $img_container;

			(self::$options['sitewide']) ? update_network_option(NULL, 'rss_options', self::$options) : update_option('rss_options', self::$options);

		endif;

		return $img_container;

	}

	function set_feed_NameSpace() {

		echo 'xmlns:media="http://search.yahoo.com/mrss/"';

	}

	function add_media_enclosure($for_comments) {

		global $post;

		if (!$for_comments) :

			$eol = "\n";

			if (self::$options['enclosure']) :

				$enclosure = @self::$options['cache'][$post-ID]['enclosure'];

				if (!$enclosure) :

					$enclosure = false;

					$length = false;

					$upload_dir = wp_upload_dir();

					$full_image = A5_Image::thumbnail(array('id' => $post->ID, 'image_size' => 'full'));

					if (strstr($full_image[0], $upload_dir['baseurl'])) :

						$image_src = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $full_image[0] );

						$length = @filesize($image_src);

					endif;

					if ($full_image) $enclosure = '<enclosure url="'.$full_image[0].'" length="'.$length.'" type="image/jpg" />'.$eol;
					self::$options['cache'][$post->ID]['enclosure'] = $enclosure;

			(self::$options['sitewide']) ? update_network_option(NULL, 'rss_options', self::$options) : update_option('rss_options', self::$options);

				endif;

				echo $enclosure;

			endif;

			if (self::$options['media_content']) :

				$media_content = @self::$options['cache'][$post-ID]['media_content'];

				if (!$media_content) :

					$media_content = false;

					$rss_image = A5_Image::thumbnail(array('id' => $post->ID, 'image_size' => 'rss-image'));

					if ($rss_image) $media_content = '<media:content url="'.$rss_image[0].'" width="'.$rss_image[1].'" height="'.$rss_image[2].'" medium="image" type="image/jpeg" />'.$eol;

					self::$options['cache'][$post->ID]['media_content'] = $media_content;

				(self::$options['sitewide']) ? update_network_option(NULL, 'rss_options', self::$options) : update_option('rss_options', self::$options);

				endif;

				echo $media_content;

			endif;

		endif;

	}

	function add_fav_icon() {return;

		$eol = "\n";

		$tab = "\t";

		$dtab = "\t\t";

		echo $tab.'<image>'.$eol.$dtab.'<url>http://yritys-test.waldemarstoffel.com/ty678uui/wp-content/plugins/rss-image-feed/img/a5-icon-34.png</url>'.$eol.$dtab.'<title>'.get_bloginfo().'</title>'.$eol.$dtab.'<link>'.get_bloginfo('url').'</link>'.$eol.$dtab.'<width>32</width>'.$eol.$dtab.'<height>32</height>'.$eol.$tab.'</image>'.$eol;

	}

	function flush_widget_cache() {

		if (count(self::$options['cache']) < 100) return;

		global $wpdb;

		self::$options['cache'] = array();

		if (!self::$options['sitewide']) :

			$update_args = array('option_value' => serialize(self::$options));

			$result = $wpdb->update( $wpdb->options, $update_args, array( 'option_name' => 'rss_options' ) );

		else :

			global $current_site;

			$result = $wpdb->update( $wpdb->sitemeta, array( 'meta_value' => serialize(self::$options) ), array( 'site_id' => $current_site->id, 'meta_key' => 'rss_options' ) );

		endif;

	}

	/**
	 *
	 * Adds a link to the settings to the admin bar in case WP_DEBUG is true
	 *
	 */
	function admin_bar_menu() {

		global $wp_admin_bar;

		if (!is_super_admin() || !is_admin_bar_showing()) return;

		$wp_admin_bar->add_node(array('parent' => '', 'id' => 'a5-framework', 'title' => 'A5 Framework'));

		$wp_admin_bar->add_node(array('parent' => 'a5-framework', 'id' => 'rss-image', 'title' => 'RSS Image Feed', 'href' => admin_url('plugins.php?page=rss-image-feed')));

	}

}

$Rss_Image_Feed = new Rss_Image_Feed;
