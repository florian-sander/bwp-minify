<?php
/**
 * Copyright (c) 2011 Khang Minh <betterwp.net>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * This is a wrapper function to help you convert a normal source to a minified source.
 *
 * Please do not use this function before WordPress has been initialized. Otherwise, you might get a fatal error.
 *
 * @param string $src The source file you would like to convert
 * @see http://betterwp.net/wordpress-plugins/bwp-minify/ for more information
 */
function bwp_minify($src)
{
	global $bwp_minify;

	return $bwp_minify->get_minify_src($bwp_minify->process_media_source($src));
}

if (!class_exists('BWP_FRAMEWORK'))
	require_once('class-bwp-framework.php');

class BWP_MINIFY extends BWP_FRAMEWORK {

	/**
	 * Hold all scripts to be printed in <head> section
	 */
	var $header_scripts = array(array()), $header_l10n = array();

	/**
	 * Hold all scripts to be printed just before </body>
	 */
	var $footer_scripts = array(array()), $footer_l10n = array();
	
	/**
	 * Determine positions to manually put scripts in
	 */
	var $print_positions = array('header' => array(), 'footer' => array(), 'direct' => array(), 'ignore' => array());
	
	/**
	 * Are scripts still queueable?
	 */
	var $queueable = true;
	
	/**
	 * Queued styles to be printed
	 */
	var $styles = array(array()), $media_styles = array('print' => array());
	 
	/**
	 * Are we still able to print styles?
	 */
	var $printable = true;
	 
	/**
	 * Other options
	 */
	 var $ver = '', $base = '', $cache_time = 1800, $buster = '';

	/**
	 * Constructor
	 */	
	function __construct($version = '1.0.1')
	{
		// Plugin's title
		$this->plugin_title = 'BetterWP Minify';
		// Plugin's version
		$this->set_version($version);
		$this->set_version('5.1.6', 'php');
		// Basic version checking
		if (!$this->check_required_versions())
			return;

		// The default options
		$options = array(
			'input_minurl' => '',
			'input_cache_dir' => '',
			'input_maxfiles' => 10,
			'input_maxage' => 30,
			'input_ignore' => '',
			'input_header' => '',
			'input_direct' => 'admin-bar',
			'input_footer' => '',
			'input_custom_buster' => '',
			'enable_auto' => 'yes',
			'enable_bloginfo' => 'yes',
			'select_buster_type' => 'none',
			'select_time_type' => 60
		);
		// Super admin only options
		$this->site_options = array('input_minurl', 'input_cache_dir');

		$this->build_properties('BWP_MINIFY', 'bwp-minify', $options, 'BetterWP Minify', dirname(dirname(__FILE__)) . '/bwp-minify.php', 'http://betterwp.net/wordpress-plugins/bwp-minify/', false);
		$this->options_default['input_minurl'] = plugin_dir_url($this->plugin_file) . 'min/';

		$this->add_option_key('BWP_MINIFY_OPTION_GENERAL', 'bwp_minify_general', __('Better WordPress Minify Settings', 'bwp-minify'));
		$this->init();
	}

	function init_properties()
	{
		$this->parse_positions();
		$this->get_base();
		$this->ver = get_bloginfo('version');
		$this->cache = (int) $this->options['input_maxage'] * (int) $this->options['select_time_type'];
		$this->options['input_cache_dir'] = $this->get_cache_dir();
		$this->buster = $this->get_buster($this->options['select_buster_type']);
	}

	function add_hooks()
	{
		if (!is_admin() && 'yes' == $this->options['enable_auto'])
		{
			add_filter('print_styles_array', array($this, 'minify_styles'));
			add_filter('print_scripts_array', array($this, 'minify_scripts'));
			add_action('wp_head', array($this, 'print_styles'), 8);
			add_action('wp_head', array($this, 'print_media_styles'), 8);
			add_action('login_head', array($this, 'print_styles'));
			add_action('wp_head', array($this, 'print_head_scripts'), 9);
			/*add_action('login_enqueue_scripts', array($this, 'print_head_scripts'));*/
			add_action('wp_footer', array($this, 'print_footer_scripts'));
			add_action('bwp_minify_before_header_scripts', array($this, 'print_header_scripts_l10n'));
			add_action('bwp_minify_before_footer_scripts', array($this, 'print_footer_scripts_l10n'));
		}

		if ('yes' == $this->options['enable_bloginfo'])
		{
			add_filter('stylesheet_uri', array($this, 'minify_item'));
			add_filter('locale_stylesheet_uri', array($this, 'minify_item'));
		}
	}
	
	/**
	 * Build the Menus
	 */
	function build_menus()
	{
		add_options_page(__('Better WordPress Minify', 'bwp-minify'), 'BWP Minify', BWP_MINIFY_CAPABILITY, BWP_MINIFY_OPTION_GENERAL, array($this, 'build_option_pages'));		
	}
	
	/**
	 * Build the option pages
	 *
	 * Utilizes BWP Option Page Builder (@see BWP_OPTION_PAGE)
	 */	
	function build_option_pages()
	{
		if (!current_user_can(BWP_MINIFY_CAPABILITY))
			wp_die(__('You do not have sufficient permissions to access this page.'));

		// Init the class
		$page = $_GET['page'];		
		$bwp_option_page = new BWP_OPTION_PAGE($page, $this->site_options);
		
		$options = array();
		
if (!empty($page))
{	
	if ($page == BWP_MINIFY_OPTION_GENERAL)
	{
		$form = array(
			'items'			=> array('heading', 'checkbox', 'checkbox', 'heading', 'input', 'input', 'input', 'select', 'heading', 'textarea', 'textarea', 'textarea', 'textarea'),
			'item_labels'	=> array
			(
				__('General Options', 'bwp-minify'),
				__('Minify CSS, JS files automatically?', 'bwp-minify'),				
				__('Minify <code>bloginfo()</code> stylesheets?', 'bwp-minify'),
				__('Minifying Options', 'bwp-minify'),
				__('Minify URL (double-click to edit)', 'bwp-minify'),
				__('Cache will be stored in (by default)', 'bwp-minify'),
				__('One minify string will contain', 'bwp-minify'),
				__('Append the minify string with', 'bwp-minify'),
				__('Minifying Scripts Options', 'bwp-minify'),
				__('Scripts to be minified in header', 'bwp-minify'),
				__('Scripts to be minified in footer', 'bwp-minify'),
				__('Scripts to be minified and then printed separately', 'bwp-minify'),
				__('Scripts to be ignored (not minified)', 'bwp-minify')
			),
			'item_names'	=> array('h1', 'cb1', 'cb2', 'h2', 'input_minurl', 'input_cache_dir', 'input_maxfiles', 'select_buster_type', 'h3', 'input_header', 'input_footer', 'input_direct', 'input_ignore'),
			'heading'			=> array(
				'h1'	=> '',
				'h2'	=> __('<em>Options that affect both your stylesheets and scripts.</em>', 'bwp-minify'),
				'h3'	=> sprintf(__('<em>You can force the position of each script using those inputs below (e.g. you have a script registered in the header but you want to minify it in the footer instead). If you are still unsure, please read more <a href="%s#positioning-your-scripts">here</a>. Type in one script handle (<strong>NOT filename</strong>) per line.</em>', 'bwp-minify'), $this->plugin_url)
			),
			'select' => array(
				'select_time_type' => array(
					__('second(s)', 'bwp-minify') => 1,
					__('minute(s)', 'bwp-minify') => 60,
					__('hour(s)', 'bwp-minify') => 3600,
					__('day(s)', 'bwp-minify') => 86400
				),
				'select_buster_type' => array(
					__('Do not append anything', 'bwp-minify') => 'none',
					__('Cache folder&#8217;s last modified time', 'bwp-minify') => 'mtime',
					__('Your WordPress&#8217;s current version', 'bwp-minify') => 'wpver',
					__('A custom number', 'bwp-minify') => 'custom'
				)
			),
			'checkbox'	=> array(
				'cb1' => array(__('you can still use the template function <code>bwp_minify()</code> if you disable this.', 'bwp-minify') => 'enable_auto'),
				'cb2' => array(__('most themes (e.g. Twenty Ten) use <code>bloginfo()</code> to print the main stylesheet (i.e. <code>style.css</code>) and BWP Minify will not be able to add it to the main minify string. If you want to minify <code>style.css</code> with the rest of your css files, you must enqueue it.', 'bwp-minify') => 'enable_bloginfo')
			),
			'input'	=> array(
				'input_minurl' => array('size' => 91, 'disabled' => ' readonly="readonly"', 'label' => sprintf(__('This should be set automatically. If you think the URL is too long, please read <a href="%s#customization">here</a> to know how to properly modify this.', 'bwp-minify'), $this->plugin_url)),
				'input_cache_dir' => array('size' => 91, 'disabled' => ' disabled="disabled"', 'label' => __('The cache directory must be writable (i.e. CHMOD to 755 or 777).', 'bwp-minify')),
				'input_maxfiles' => array('size' => 3, 'label' => __('file(s) at most.', 'bwp-minify')),
				'input_maxage' => array('size' => 5, 'label' => __('&mdash;', 'bwp-minify')),
				'input_custom_buster' => array('pre' => __('<em>&rarr; /min/?f=file.js&amp;ver=</em> ', 'bwp-minify'), 'size' => 12, 'label' => '.', 'disabled' => ' disabled="disabled"')
			),
			'textarea' => array
			(
				'input_header' => array('cols' => 40, 'rows' => 3),
				'input_footer' => array('cols' => 40, 'rows' => 3),
				'input_direct' => array('cols' => 40, 'rows' => 3),
				'input_ignore' => array('cols' => 40, 'rows' => 3)
			),
			'container'	=> array(
				'select_buster_type' => __('<em><strong>Note:</strong> When you append one of the things above you are basically telling browsers to clear their cached version of your CSS and JS files, which is very useful when you change source files. Use this feature wisely :).</em>', 'bwp-minify')
			),
			'inline_fields' => array(
				'input_maxage' => array('select_time_type' => 'select'),
				'select_buster_type' => array('input_custom_buster' => 'input')
			)
		);

		// Get the default options
		$options = $bwp_option_page->get_options(array('input_minurl', 'input_cache_dir', 'input_maxfiles', 'input_header', 'input_footer', 'input_direct', 'input_ignore', 'input_custom_buster', 'select_buster_type', 'enable_auto', 'enable_bloginfo'), $this->options);

		// Get option from the database
		$options = $bwp_option_page->get_db_options($page, $options);

		$option_formats = array('input_maxfiles' => 'int', 'input_maxage' => 'int', 'select_time_type' => 'int');
		$option_super_admin = $this->site_options;
	}
}
		// Get option from user input
		if (isset($_POST['submit_' . $bwp_option_page->get_form_name()]) && isset($options) && is_array($options))
		{
			check_admin_referer($page);
			foreach ($options as $key => &$option)
			{
				// [WPMS Compatible]
				if ($this->is_normal_admin() && in_array($key, $option_super_admin))
				{
				}
				else
				{
					if (isset($_POST[$key]))
						$bwp_option_page->format_field($key, $option_formats);
					if (!isset($_POST[$key]) && !isset($form['input'][$key]['disabled']))
						$option = '';
					else if (isset($option_formats[$key]) && 0 == $_POST[$key] && 'int' == $option_formats[$key])
						$option = 0;
					else if (isset($option_formats[$key]) && empty($_POST[$key]) && 'int' == $option_formats[$key])
						$option = $this->options_default[$key];
					else if (!empty($_POST[$key]))
						$option = trim(stripslashes($_POST[$key]));
					else
						$option = '';
				}
			}
			update_option($page, $options);
			// [WPMS Compatible]
			if (!$this->is_normal_admin())
				update_site_option($page, $options);
		}

		// Guessing the cache directory
		$options['input_cache_dir'] = $this->get_cache_dir($options['input_minurl']);
		// [WPMS Compatible]
		if ($this->is_normal_admin())
			$bwp_option_page->kill_html_fields($form, array(4,5));
		// Cache buster system
		$this->options = array_merge($this->options, $options);
		$options['input_custom_buster'] = $this->get_buster($options['select_buster_type']);
		if ('custom' == $options['select_buster_type'])
			unset($form['input']['input_custom_buster']['disabled']);

		if (!file_exists($options['input_cache_dir']) || !is_writable($options['input_cache_dir']))
			$this->add_notice('<strong>' . __('Warning') . ':</strong> ' . __("Cache directory does not exist or is not writable. Please try CHMOD your cache directory to 755. If you still see this warning, CHMOD to 777.", 'bwp-minify'));

?>
	<script type="text/javascript">
		jQuery(document).ready(function(){
			jQuery('.bwp-option-page :input[readonly]').dblclick(function(){
				jQuery(this).removeAttr('readonly');
			});
		})
	</script>
<?php

		// Assign the form and option array		
		$bwp_option_page->init($form, $options, $this->form_tabs);

		// Build the option page	
		echo $bwp_option_page->generate_html_form();
	}

	function get_cache_dir($minurl = '')
	{
		if (empty($minurl))
			$minurl = $this->options['input_minurl'];
		$temp = @parse_url($minurl);
		$site_url = $temp['scheme'] . '://' . $temp['host'];
		$guess_cache = str_replace($site_url, '', $minurl);
		// @since 1.0.1
		$guess_cache = substr($guess_cache, 0, -4);
		$guess_cache = $_SERVER['DOCUMENT_ROOT'] . trim($guess_cache, '/') . '/cache/';
		return apply_filters('bwp_minify_cache_dir', str_replace('\\', '/', $guess_cache));
	}

	function parse_positions()
	{
		$positions = array(
			'header' => $this->options['input_header'],
			'footer' => $this->options['input_footer'],
			'direct'	=> $this->options['input_direct'],
			'ignore'	=> $this->options['input_ignore']
		);

		foreach ($positions as &$position)
		{
			if (!empty($position))
			{
				$position = explode("\n", $position);
				$position = array_map('trim', $position);
			}
		}				
		
		$this->print_positions = $positions;
	}

	function get_base()
	{
		$temp = @parse_url(get_site_option('siteurl'));
		$site_url = $temp['scheme'] . '://' . $temp['host'];
		$raw_base = trim(str_replace($site_url, '', get_site_option('siteurl')), '/');
		/* More filtering will ba added in future */
		$this->base = $raw_base;
	}

	function get_buster($type)
	{
		switch ($type)
		{
			case 'mtime':
				if (file_exists($this->options['input_cache_dir']))
					return filemtime($this->options['input_cache_dir']);
			break;

			case 'wpver':
				return $this->ver;
			break;

			case 'custom':
				return $this->options['input_custom_buster'];
			break;

			case 'none':
			default:
				if (is_admin())
					return __('empty', 'bwp-minify');
				else
					return '';
			break;
		}
	}
	
	function is_in($handle, $position = 'header')
	{
		if (!isset($this->print_positions[$position]) || !is_array($this->print_positions[$position]))
			return false;
		if (in_array($handle, $this->print_positions[$position]))
			return true;
	}

	/**
	 * Check if a sciprt has been localized using wp_localize_script()
	 *
	 * @see wp-includes/functions.wp-scripts.php
	 */
	function is_l10n($handle)
	{
		global $wp_scripts;
		if (isset($wp_scripts->registered[$handle]->extra['l10n']))
			return true;
		return false;
	}

	/**
	 * Check if media source is local
	 */
	function is_local($src = '')
	{
		$url = @parse_url($src);
		$blog_url = @parse_url(get_option('home'));
		if (false === $url)
			return false;
		// If scheme is set
		if (isset($url['scheme']))
		{
			if (false === strpos($url['host'], $blog_url['host']))
				return false;
			return true;
		}
		else
			// Probably a relative link
			return true;
	}

	/**
	 * Make sure the media file is in expected format before being added to our minify string
	 */
	function process_media_source($src = '')
	{
		$src = strtolower($src);
		// remove siteurl in the source if necessary
		$src = str_replace(get_option('siteurl'), '', $src);
		$src = ltrim($src, '/');
		return $src;
	}

	function append_minify_string(&$media = array(), &$count = 0, $src = '', $done = false, $parent = false)
	{
		$current_pointer = sizeof($media) - 1;
		// Don't append if already added
		if (!in_array($src, $media[$current_pointer]))
			$media[$current_pointer][] = $src;
		if (false == $parent && $this->options['input_maxfiles'] <= $count || true == $done)
		{
			$current_pointer = sizeof($media) - 1;
			$count = 0;
			if (!$done)
				$media[] = array();
		}
	}

	function get_minify_src($string)
	{
		if (empty($string))
			return '';

		$base = (!empty($this->base)) ? '&amp;b=' . $this->base : '';
		$buster = (!empty($this->buster)) ? '&amp;ver=' . $this->buster : '';

		return trailingslashit($this->options['input_minurl']) . '?f=' . $string . $base . $buster;
	}

	function get_minify_tag($string, $type, $media = '')
	{
		if (empty($string))
			return '';

		switch ($type)
		{
			case 'script':
				return "<script type='text/javascript' src='" . $this->get_minify_src($string) . "'></script>\n";
			break;
			
			case 'style':
			default:			
				return "<link rel='stylesheet' type='text/css' media='all' href='" . $this->get_minify_src($string) . "'></link>\n";
			break;

			case 'media':
				return "<link rel='stylesheet' type='text/css' media='$media' href='" . $this->get_minify_src($string) . "'></link>\n";
			break;
		}
	}

	/**
	 * Get the correct href for rtl css.
	 *
	 * This is actually borrowed from wp-includes/class.wp-styles.php:70
	 */
	function rtl_css_href($handle)
	{
		global $wp_styles;

		if (is_bool($wp_styles->registered[$handle]->extra['rtl']))
		{
			$suffix = isset($wp_styles->registered[$handle]->extra['suffix'] ) ? $wp_styles->registered[$handle]->extra['suffix'] : '';
			$rtl_href = str_replace("{$suffix}.css", "-rtl{$suffix}.css", $wp_styles->registered[$handle]->src);
		}
		else
			$rtl_href = $wp_styles->registered[$handle]->extra['rtl'];
		
		return $this->process_media_source($rtl_href);
	}

	function minify_item($src)
	{
		return $this->get_minify_src($this->process_media_source($src));
	}

	/**
	 * Loop through current todo array to build our minify string (min/?f=style1.css,style2.css)
	 *
	 * If the number of stylesheets reaches a limit (by default the limit is 10) this plugin will split the minify string 
	 * into an appropriate number of <link> tags. This is only to beautify your minify strings (they might get too long).
	 * If you don't like such behaviour, change the limit to something higher, e.g. 50.
	 */
	function minify_styles($todo)
	{
		global $wp_styles;
				
		$total = sizeof($todo);
		$count = 0;
		$queued = 0;
		$temp = array();
		$this->print_positions['style_ignore'] = apply_filters('bwp_minify_style_ignore', array());
		$this->print_positions['style_direct'] = apply_filters('bwp_minify_style_direct', array('admin-bar'));

		foreach ($todo as $key => $handle)
		{
			$count++;
			// Take the src from registred stylesheets, do not proceed if the src is external
			// Also do not proceed if we can not print any more (except for login page)
			$the_style = $wp_styles->registered[$handle];
			$src = $the_style->src;
			if ($this->is_in($handle, 'style_ignore'))
				$temp[] = $handle;
			else if ($this->is_in($handle, 'style_direct'))
			{
				$src = $this->process_media_source($src);
				$the_style->src = $this->get_minify_src($src);
				$the_style->ver = NULL;
				$temp[] = $handle;
			}
			else if ((has_action('login_head') || true == $this->printable) && !empty($src) && $this->is_local($src))
			{
				$src = $this->process_media_source($src);
				// If this style has a different media type rather than 'all' (or not set),
				// we will have to add it to append it in other strings
				if (isset($the_style->args) && 'all' != $the_style->args)
				{
					$media = $the_style->args;
					if (!isset($this->media_styles[$media]))
						$this->media_styles[$media] = array();
					$this->media_styles[$media][] = $src;
				}
				// If this style needs conditional statement (e.g. IE-specific stylesheets) 
				// or is an alternate stylesheet (@see http://www.w3.org/TR/REC-html40/present/styles.html#h-14.3.1), 
				// we will not enqueue it (but still minify it).
				else if ((isset($the_style->extra['conditional']) && $the_style->extra['conditional']) || (isset($the_style->extra['alt']) && $the_style->extra['alt']))
				{
					$the_style->src = $this->get_minify_src($src);
					$the_style->ver = NULL;
					$temp[] = $handle;
				}
				else
				{
					$queued++;
					$this->append_minify_string($this->styles, $queued, $src, $count == $total, true);
					// If this style has support for rtl language and the locale is rtl, 
					// we will have to append the rtl stylesheet also
					if ('rtl' === $wp_styles->text_direction && isset($the_style->extra['rtl']) && $the_style->extra['rtl'])
						$this->append_minify_string($this->styles, $queued, $this->rtl_css_href($handle), $count == $total);
				}
			}
			else
				$temp[] = $handle;
		}
		
		$this->printable = false;

		return $temp;
	}

	/**
	 * Main function to print out our stylesheets
	 *
	 * Use actions provided to add other things before or after the output.
	 */	
	function print_styles()
	{
		do_action('bwp_minify_before_styles');

		// Print <link> tags
		$styles = (array) $this->styles;
		foreach ($styles as $style_array)
		{
			if (0 < sizeof($style_array))
				echo $this->get_minify_tag(implode(',', $style_array), 'style');
		}

		do_action('bwp_minify_after_styles');
	}
	
	function print_media_styles()
	{
		do_action('bwp_minify_before_media_styles');

		// Print <link> tags
		$styles = (array) $this->media_styles;
		foreach ($styles as $key => $style_array)
		{
			if (0 < sizeof($style_array))
				echo $this->get_minify_tag(implode(',', $style_array), 'media', $key);
		}

		do_action('bwp_minify_after_media_styles');
	}

	/**
	 * Loop through current todo array to build our minify string (min/?f=script1.js,script2.js)
	 *
	 * If the number of scripts reaches a limit (by default the limit is 10) this plugin will split the minify string 
	 * into an appropriate number of <script> tags. This is only to beautify your minify strings (or else they might get too long).
	 * If you don't like such behaviour, change the limit to something higher, e.g. 50.
	 */
	function minify_scripts($todo)
	{
		global $wp_scripts;

		// Avoid conflict with WordPress 3.1
		if (1 == sizeof($todo) && 'l10n' == $todo[0])
			return array();

		$header_count = 0;
		$footer_count = 0;
		$count_f = 0; $count_h = 0;
				
		$total = sizeof($todo);
		$total_footer = 0;
		foreach ($wp_scripts->groups as $count)
			if (0 < $count)
				$total_footer++;
		// Get the correct total
		foreach ($todo as $script_handle)
		{
			if ($this->is_in($script_handle, 'ignore') || $this->is_in($script_handle, 'direct'))
			{
				$total--;
				if (isset($wp_scripts->groups[$script_handle]) && 1 == $wp_scripts->groups[$script_handle])
					$total_footer--;
			}
		}

		$temp = array();

		foreach ($todo as $key => $script_handle)
		{
			// Take the src from registred scripts, do not proceed if the src is external
			$src = $wp_scripts->registered[$script_handle]->src;
			if (!empty($src) && $this->is_local($src))
			{
				$src = $this->process_media_source($src);
				// If this script does not belong to 'direct' or 'ignore' list
				if (!$this->is_in($script_handle, 'ignore') && !$this->is_in($script_handle, 'direct'))
				{
					// If this script belongs to footer (logically or 'intentionally') and is not 'forced' to be in header
					if (!$this->is_in($script_handle, 'header') && ($this->is_in($script_handle, 'footer') || (isset($wp_scripts->groups[$script_handle]) && 0 < $wp_scripts->groups[$script_handle])))
					{
						$count_f++; $footer_count++;
						$this->append_minify_string($this->footer_scripts, $footer_count, $src, $count_f == $total_footer);
						if (true == $this->is_l10n($script_handle))
							$this->footer_l10n[] = $script_handle;
					}
					else if (true == $this->queueable)
					{
						$count_h++; $header_count++;
						$this->append_minify_string($this->header_scripts, $header_count, $src, $count_h == $total - $total_footer);
						if (true == $this->is_l10n($script_handle))
							$this->header_l10n[] = $script_handle;							
					}
					else
						$temp[] = $script_handle;
					
				}
				else
				{
					// If belongs to 'direct', minify it and let WordPress print it the normal way
					if ($this->is_in($script_handle, 'direct'))
					{
						$wp_scripts->registered[$script_handle]->src = $this->get_minify_src($src);
						$wp_scripts->registered[$script_handle]->ver = NULL;
					}
					$temp[] = $script_handle;
				}
			}
			else
				$temp[] = $script_handle;
		}
	
		$this->queueable = false;

		return $temp;
	}

	/**
	 * Main function to print out our scripts
	 *
	 * Use actions provided to add other things before or after the output.
	 */	
	function print_scripts($scripts, $action)
	{
		do_action('bwp_minify_before_' . $action . '_scripts');

		// Print script tags
		$scripts = (array) $scripts;
		foreach ($scripts as $script_array)
		{
			if (0 < sizeof($script_array))
				echo $this->get_minify_tag(implode(',', $script_array), 'script');
		}

		do_action('bwp_minify_after_' . $action . '_scripts');
	}

	function print_head_scripts()
	{
		$this->print_scripts($this->header_scripts, 'header');
	}

	function print_footer_scripts()
	{
		$this->print_scripts($this->footer_scripts, 'footer');
	}

	function print_scripts_l10n($scripts)
	{
		global $wp_scripts;

		foreach ($scripts as $handle)
		{
			$wp_scripts->print_scripts_l10n($handle);
		}
	}

	function print_header_scripts_l10n()
	{
		$this->print_scripts_l10n($this->header_l10n);
	}

	function print_footer_scripts_l10n()
	{
		$this->print_scripts_l10n($this->footer_l10n);
	}
}
?>