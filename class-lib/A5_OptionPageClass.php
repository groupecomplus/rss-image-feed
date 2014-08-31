<?php

/**
 *
 * Class A5 Option Page
 *
 * @ A5 Plugin Framework
 * Version: 1.0 beta
 *
 * Gets all sort of containers for the flexible A5 settings pages
 *
 */

class A5_OptionPage {
	
	/**
	 *
	 * Opening and closing the option page / form (automatically closed with page)
	 *
	 */
	static function open_page($plugin_name, $url = false, $plugin_slug = false, $title = false) {
		
		$eol = "\r\n";
		
		$tab = "\t";
		
		echo $eol.'<div class="wrap">';
		
		if ($url) echo $eol.$tab.'<a href="'.$url.'" title="'.$title.'"><div id="a5-logo" style="background: url(\''.plugins_url($plugin_slug.'/img/a5-icon-34.png').'\'); float: left; width: 32px; height: 32px; margin: 5px;"></div></a>';
		
		echo $eol.$tab.'<h2>'.$plugin_name.' '.__('Settings').'</h2>'.$eol;
	
	}
	 
	static function open_form($action) {
		
		$eol = "\r\n";
		
		echo $eol.'<form action="'.$action.'" method="post">'.$eol;
		
	} 
	 
	static function close_page() {
		
		$eol = "\r\n";
		
		$tab = "\t";
		
		echo $eol.$tab.'</form>'.$eol.'</div>'.$eol;
		
	}
	
	/**
	 *
	 * Building the menu for the tabs
	 *
	 */
	static function nav_menu($args) {
		
		$eol = "\r\n";
		
		$tab = "\t";
		
		extract ($args);
		
		echo '<h2 class="nav-tab-wrapper">';
		
		foreach ($menu_items as $menu_item => $args) :
		
			echo $eol.$tab.'<a href="?page='.$page.'&tab='.$menu_item.'" class="nav-tab'.$args['class'].'">'.$args['text'].'</a>';
		
		endforeach;
		
		echo $eol.'</h2>'.$eol;
	
	}
	
	/**
	 *
	 * Opening and closing the tabs
	 *
	 */
	static function open_tab($plugin_name, $tab_name) {
		
		$eol = "\r\n";
		
		echo $eol.'<div id="'.$plugin_name.'-admin" class="metabox-holder">'.$eol.'<div id="'.$plugin_name.'-'.$tab_name.'-sortable" class="meta-box-sortables ui-sortable">'.$eol;
		
	} 
	 
	static function close_tab() {
		
		$eol = "\r\n";
		
		echo $eol.'</div>'.$eol.'</div>'.$eol;
		
	}
	
	/**
	 *
	 * Opening and closing the draggable boxes
	 *
	 */
	static function open_draggable($label, $id) {
		
		$eol = "\r\n";
		
		$tab = "\t";
		
		$dtab = $tab.$tab;
	
		echo $eol.'<div id="'.$id.'" class="postbox ">'.$eol.$tab.'<div class="handlediv" title="'.__('Click to toggle').'">'.$eol.$dtab.'<br />'.$eol.$tab.'</div>'.$eol.$tab;
			
		echo $eol.'<h3 class="hndle">'.$eol.$dtab.'<span>'.$label.'</span>'.$eol.$tab.'</h3>'.$eol.$tab.'<div class="inside">'.$eol.$tab;	
		
	}
	
	static function close_draggable() {
		
		self::close_tab();
		
	}
	
	/**
	 *
	 * Wrapping sections in containers
	 *
	 */
	static function wrap_section($section_id, $atts = false) {
		
		$eol = "\r\n";
		
		$tab = "\t";
		
		$attributes = '';
		
		if (false !== $atts) :
		
			foreach ($atts as $attribute => $value) $attributes .= ' '.$attribute.'="'.$value.'"';
			
		endif;
	
		echo $eol.'<div'.$attributes.'>'.$eol.$tab;
		
		do_settings_sections($section_id);
		
		echo $eol.'</div>'.$eol;
		
	}
	
	/**
	 *
	 * Wrapping elements into html tags
	 *
	 */
	static function tag_it($element, $tag, $indent = false, $atts = false, $echo = false) {
	
		$eol = "\r\n";
		
		$tab = "\t";
		
		$attributes = '';
		
		if (false !== $atts) :
			
			foreach ($atts as $attribute => $value) $attributes .= ' '.$attribute.'="'.$value.'"';
		
		endif;
		
		$item = $eol;
		
		if (false != $indent) :
		
			for ($i = 0; $i <= $indent; $i++) $item .= $tab;
			
		endif;
		
		$item .= '<'.$tag.$attributes.'>'.$element.'</'.$tag.'>';
		
		if (false === $echo) return $item;
		
		echo $item; 
	
	}
	
	/**
	 *
	 * Wrapping fields in unordered lists
	 *
	 * uses tag_it function
	 *
	 */
	static function list_it($fields, $header = false, $atts = false, $list_atts = false, $echo = true) {
	
		$eol = "\r\n";
		
		$list = '';
		
		$list_items = '';
		
		if (false != $header) $list .= $header.$eol;
		
		foreach ($fields as $field) $list_items .= self::tag_it($field, 'li', 1, $list_atts);
		
		$list .= self::tag_it($list_items, 'ul', $atts);
		
		if (false === $echo) return $list;
		
		echo $list;
	
	}
	
	/**
	 *
	 * Putting the clear both div
	 *
	 * uses tag_it function
	 *
	 */
	static function clear_it($echo = true) {
	
		$clear_both = self::tag_it('', 'div', false, array('style' => 'clear: both;'));
		
		if (false === $echo) return $clear_both;
		
		echo $clear_both;
	
	}
	
} // A5_OptionPage

?>