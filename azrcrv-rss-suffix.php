<?php
/**
 * ------------------------------------------------------------------------------
 * Plugin Name: RSS Suffix
 * Description: Provides opposite rss feed to that configured in ClassicPress
 * Version: 1.2.1
 * Author: azurecurve
 * Author URI: https://development.azurecurve.co.uk/classicpress-plugins/
 * Plugin URI: https://development.azurecurve.co.uk/classicpress-plugins/rss-suffix/
 * Text Domain: rss-suffix
 * Domain Path: /languages
 * ------------------------------------------------------------------------------
 * This is free software released under the terms of the General Public License,
 * version 2, or later. It is distributed WITHOUT ANY WARRANTY; without even the
 * implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. Full
 * text of the license is available at https://www.gnu.org/licenses/gpl-2.0.html.
 * ------------------------------------------------------------------------------
 */

// Prevent direct access.
if (!defined('ABSPATH')){
	die();
}

// include plugin menu
require_once(dirname( __FILE__).'/pluginmenu/menu.php');
add_action('admin_init', 'azrcrv_create_plugin_menu_rsss');

// include update client
require_once(dirname(__FILE__).'/libraries/updateclient/UpdateClient.class.php');

/**
 * Setup actions and filters.
 *
 * @since 1.0.0
 *
 */
// add actions
add_action('admin_post_save_options', 'azrcrv_rsss_process_options');
add_action('network_admin_edit_save_network_options', 'azrcrv_rsss_process_network_options');
add_action('admin_menu', 'azrcrv_rsss_create_admin_menu');
add_action('network_admin_menu', 'azrcrv_rsss_create_network_admin_menu');
add_action('plugins_loaded', 'azrcrv_rsss_load_languages');

// add filters
add_filter('the_excerpt_rss', 'azrcrv_rsss_append_rss_suffix');
add_filter('the_content', 'azrcrv_rsss_append_rss_suffix');
add_filter('plugin_action_links', 'azrcrv_rsss_add_plugin_action_link', 10, 2);
add_filter('codepotent_update_manager_image_path', 'azrcrv_rsss_custom_image_path');
add_filter('codepotent_update_manager_image_url', 'azrcrv_rsss_custom_image_url');

/**
 * Load language files.
 *
 * @since 1.0.0
 *
 */
function azrcrv_rsss_load_languages() {
    $plugin_rel_path = basename(dirname(__FILE__)).'/languages';
    load_plugin_textdomain('rss-suffix', false, $plugin_rel_path);
}

/**
 * initialise rss feed
 *
 * @since 1.0.0
 *
 */
function azrcrv_rsss_append_rss_suffix($content){
	global $post;
	
	if(is_feed()){
		$options = azrcrv_rsss_get_option('azrcrv-rss');
		
		$rss_suffix = '';
		if (strlen($options['rss_suffix']) > 0){
			$rss_suffix = stripslashes($options['rss_suffix']);
		}else{
			$network_options = get_site_option('azrcrv-rss');
			if (strlen($network_options['rss_suffix']) > 0){
				$rss_suffix = stripslashes($network_options['rss_suffix']);
			}
		}
		
		if (strlen($rss_suffix) > 0){
			$rss_suffix = str_replace('$site_url', get_site_url(), $rss_suffix);
			$rss_suffix = str_replace('$site_title', get_bloginfo('name'), $rss_suffix);
			$rss_suffix = str_replace('$site_tagline', get_bloginfo('description'), $rss_suffix);
			$rss_suffix = str_replace('$post_url', get_permalink($post->ID), $rss_suffix);
			$rss_suffix = str_replace('$post_title', $post->post_title, $rss_suffix);
			$content = $content.'<p>'.$rss_suffix.'</p>';
		}
	}
	return $content;
	
}

/**
 * Custom plugin image path.
 *
 * @since 1.2.0
 *
 */
function azrcrv_rsss_custom_image_path($path){
    if (strpos($path, 'azrcrv-rss-suffix') !== false){
        $path = plugin_dir_path(__FILE__).'assets/pluginimages';
    }
    return $path;
}

/**
 * Custom plugin image url.
 *
 * @since 1.2.0
 *
 */
function azrcrv_rsss_custom_image_url($url){
    if (strpos($url, 'azrcrv-rss-suffix') !== false){
        $url = plugin_dir_url(__FILE__).'assets/pluginimages';
    }
    return $url;
}

/**
 * Get options including defaults.
 *
 * @since 1.2.0
 *
 */
function azrcrv_rsss_get_option($option_name){
 
	$defaults = array(
						'rss_suffix' => '<p>Read original post <a href=\'$post_url\'>$post_title</a> at <a href=\'$site_url\'>$site_title|$site_tagline</a></p>',
					);

	$options = get_option($option_name, $defaults);

	$options = wp_parse_args($options, $defaults);

	return $options;

}

/**
 * Add RSS Suffix action link on plugins page.
 *
 * @since 1.0.0
 *
 */
function azrcrv_rsss_add_plugin_action_link($links, $file){
	static $this_plugin;

	if (!$this_plugin){
		$this_plugin = plugin_basename(__FILE__);
	}

	if ($file == $this_plugin){
		$settings_link = '<a href="'.admin_url('admin.php?page=azrcrv-rsss').'"><img src="'.plugins_url('/pluginmenu/images/logo.svg', __FILE__).'" style="padding-top: 2px; margin-right: -5px; height: 16px; width: 16px;" alt="azurecurve" />'.esc_html__('Settings' ,'rss-suffix').'</a>';
		array_unshift($links, $settings_link);
	}

	return $links;
}

/**
 * Add RSS Suffix menu to plugin menu.
 *
 * @since 1.0.0
 *
 */
function azrcrv_rsss_create_admin_menu(){
	//global $admin_page_hooks;
	
	add_submenu_page("azrcrv-plugin-menu"
						,esc_html__("RSS Suffix Settings", "rss-suffix")
						,esc_html__("RSS Suffix", "rss-suffix")
						,'manage_options'
						,'azrcrv-rsss'
						,'azrcrv_rsss_settings');
}

/**
 * Display Settings page.
 *
 * @since 1.0.0
 *
 */
function azrcrv_rsss_settings(){
	if (!current_user_can('manage_options')){
		wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'azrcrv-rsss'));
	}
	
	// Retrieve plugin configuration options from database
	$options = azrcrv_rsss_get_option('azrcrv-rss');
	?>
	<div id="azrcrv-rss-general" class="wrap">
		<fieldset>
			<h1>
				<?php
					echo '<a href="https://development.azurecurve.co.uk/classicpress-plugins/"><img src="'.plugins_url('/pluginmenu/images/logo.svg', __FILE__).'" style="padding-right: 6px; height: 20px; width: 20px;" alt="azurecurve" /></a>';
					esc_html_e(get_admin_page_title());
				?>
			</h1>
			<?php if(isset($_GET['settings-updated'])){ ?>
				<div class="notice notice-success is-dismissible">
					<p><strong><?php esc_html_e('Settings have been saved.', 'azrcrv-rsss'); ?></strong></p>
				</div>
			<?php } ?>
			<form method="post" action="admin-post.php">
				<input type="hidden" name="action" value="save_options" />
				<input name="page_options" type="hidden" value="rss_suffix" />
				
				<!-- Adding security through hidden referrer field -->
				<?php wp_nonce_field('azrcrv-rsss'); ?>
				<table class="form-table">
				<tr><td colspan=2>
					<p><?php esc_html_e('Set the suffix to be added to all items in the RSS feed. If multisite being used leave this suffix blank to get multisite default.', 'rss-suffix'); ?></p>
				</td></tr>
				<tr><th scope="row"><label for="width"><?php esc_html_e('RSS Suffix', 'rss-suffix'); ?></label></th><td>
					<textarea name="rss_suffix" rows="4" cols="50" id="rss_suffix" class="regular-text code"><?php echo esc_html(stripslashes($options['rss_suffix'])) ?></textarea>
					<p class="description"><?php esc_html_e('Set the default suffix for RSS. The following variables can be used;', 'rss-suffix'); ?>
					<ol><li>$site_title</li>
					<li>$site_tagline</li>
					<li>$site_url</li>
					<li>$post_url</li>
					<li>$post_title</li></ol>
					<?php printf(esc_html('For example: %sRead original post %s at %s%s'), '<em>', '&lt;a href="$post_url"&gt;$post_title&lt;/a&gt;', '&lt;a href="$site_url"&gt;$site_title|$site_tagline&lt;/a&gt;', '</em>', 'rss-suffix'); ?></em>
					</p>
				</td></tr>
				</table>
				<input type="submit" value="Save Changes" class="button-primary"/>
			</form>
		</fieldset>
	</div>
<?php }

/**
 * Save Settings.
 *
 * @since 1.0.0
 *
 */
function azrcrv_rsss_process_options(){
	// Check that user has proper security level
	if (!current_user_can('manage_options')){
		wp_die(esc_html__('You do not have permissions for this action', 'rss-suffix'));
	}
	
	// Check that nonce field created in configuration form is present
	check_admin_referer('azrcrv-rsss');
	settings_fields('azrcrv_rss');
	
	// Retrieve original plugin options array
	$options = get_option('azr_rss_options');
	
	$option_name = 'rss_suffix';
	if (isset($_POST[$option_name])){
		$options[$option_name] = implode("\n", array_map('sanitize_text_field', explode("\n", $_POST[$option_name])));
	}
	
	// Store updated options array to database
	update_option('azrcrv-rss', $options);
	
	// Redirect the page to the configuration form that was processed
	wp_redirect(add_query_arg('page', 'azrcrv-rsss&settings-updated', admin_url('admin.php')));
	exit;
}

/**
 * Add RSS Suffix menu to network plugin menu.
 *
 * @since 1.0.0
 *
 */
function azrcrv_rsss_add_azc_rss_network_settings_page(){
	if (function_exists('is_multisite') && is_multisite()){
		add_submenu_page(
						'settings.php'
						,esc_html__("RSS Suffix Settings", "rss-suffix")
						,esc_html__("RSS Suffix", "rss-suffix")
						,'manage_network_options'
						,'azrcrv-rsss'
						,'azrcrv_rsss_network_settings'
						);
	}
}

/**
 * Display Network Settings page.
 *
 * @since 1.0.0
 *
 */
function azrcrv_rsss_network_settings(){
	$options = get_site_option('azrcrv-rss');

	?>
	<div id="azrcrv-rss-general" class="wrap">
		
		<fieldset>
			<h1>
				<?php
					echo '<a href="https://development.azurecurve.co.uk/classicpress-plugins/"><img src="'.plugins_url('/pluginmenu/images/logo.svg', __FILE__).'" style="padding-right: 6px; height: 20px; width: 20px;" alt="azurecurve" /></a>';
					esc_html_e(get_admin_page_title());
				?>
			</h1>
			
			<form method="post" action="admin-post.php">
				<input type="hidden" name="action" value="save_network_options" />
				<input name="page_options" type="hidden" value="suffix" />
				
				<!-- Adding security through hidden referrer field -->
				<?php wp_nonce_field('azrcrv-rsss'); ?>
				<table class="form-table">
				<tr><td colspan=2>
					<p><?php esc_html_e('Set the suffix to be added to all items in the RSS feed. If multisite being used leave this suffix blank to get multisite default.', 'rss-suffix'); ?></p>
				</td></tr>
				<tr><th scope="row"><label for="width"><?php esc_html_e('Default RSS Suffix', 'rss-suffix'); ?></label></th><td>
					<textarea name="rss_suffix" rows="4" cols="50" id="rss_suffix" class="regular-text code"><?php echo esc_html(stripslashes($options['rss_suffix'])) ?></textarea>
					<p class="description"><?php esc_html_e('Set the default suffix for RSS. The following variables can be used;', 'rss-suffix'); ?>
					<ol><li>$site_title</li>
					<li>$site_tagline</li>
					<li>$site_url</li></ol>
					</p>
				</td></tr>
				</table>
				<input type="submit" value="<?php esc_html_e('Submit', 'rss-suffix'); ?>" class="button-primary"/>
			</form>
		</fieldset>
		
	</div>
	<?php
}

/**
 * Save Network Settings.
 *
 * @since 1.0.0
 *
 */
function azrcrv_rsss_process_network_options(){     
	if(!current_user_can('manage_network_options')){
		wp_die('');
	}		check_admin_referer('azrcrv-rsss');
	
	// Retrieve original plugin options array
	$options = get_site_option('azrcrv-rss');

	$option_name = 'rss_suffix';
	if (isset($_POST[$option_name])){
		$options[$option_name] = implode("\n", array_map('sanitize_text_field', explode("\n", $_POST[$option_name])));
	}
	
	update_site_option('azrcrv-rss', $options);

	wp_redirect(network_admin_url('settings.php?page=azrcrv-rsss'));
	exit;  
}


?>