<?php
/**
 * Plugin Name:       Google Meet and Zoom Integration
 * Description:       Plugin for integration google meet and zoom
 * Version:           1.0.0
 * Author:            GANFI
 * Author URI:        https://t.me/GANFI/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       custom-table
 */

if (!defined('WPINC')) {
	die;
}

register_deactivation_hook(__FILE__, function () {
	flush_rewrite_rules();
});

final class Google_Meet_And_Zoom_Integration
{
	const VERSION = '1.0.0';

	private string $plugin_name = 'google-meet-and-zoom-integration';

	protected $wpdb;

	public function __construct(wpdb $wpdb)
	{
		$this->wpdb = $wpdb;

		$this->create_meets_page();

		add_shortcode($this->plugin_name, [$this, 'shortcode']);
		add_action('rest_api_init', [$this, 'register_rest_endpoints']);

		register_activation_hook(__FILE__, function () {
			add_rewrite_endpoint('my-meets', EP_ROOT | EP_PAGES);
			flush_rewrite_rules();
		});
	}

	public function enqueue_scripts(): void
	{
		wp_enqueue_style($this->plugin_name . '-style', plugin_dir_url(__FILE__) . 'build/index.css', [], self::VERSION);
		wp_enqueue_script($this->plugin_name . '-script', plugin_dir_url(__FILE__) . 'build/index.js', ['wp-element'], self::VERSION, true);
	}

	public function shortcode($atts)
	{
		$atts = shortcode_atts( [
			'userid' => null
		], $atts, $this->plugin_name);

		$this->enqueue_scripts();

		ob_start();

		$user_id = $atts['userid'] ?? get_current_user_id();

		require_once plugin_dir_path(__FILE__) . 'templates/app.php';

		return ob_get_clean();
	}

	public function register_rest_endpoints()
	{
//		register_rest_route('custom-table/v1', '/get-info/', array(
//			'methods' => 'GET',
//			'callback' => [$this, 'get_info'],
//			'permission_callback' => [$this, 'check_auth'],
//		));
	}

	public function create_meets_page()
	{
		add_action('init', function () {
			add_rewrite_endpoint('my-meets', EP_ROOT | EP_PAGES);
		});

		add_filter('query_vars', function ($vars) {
			$vars[] = 'my-meets';
			return $vars;
		}, 0);

		add_filter('woocommerce_account_menu_items', function($items) {
			$new_items = array();
			foreach($items as $key => $value){
				if($key != 'customer-logout'){
					$new_items[$key] = $value;
				}else{
					$new_items['my-meets'] = __('My Meets');
					$new_items[$key] = $value;
				}
			}
			return $new_items;
		}, 0);

		wp_enqueue_style($this->plugin_name . '-woocommerce-style', plugin_dir_url(__FILE__) . 'build/woocommerce.css', [], self::VERSION);


		add_action('woocommerce_account_my-meets_endpoint', function () {
			echo do_shortcode('[google-meet-and-zoom-integration]');
		});

	}
}

global $wpdb;

new Google_Meet_And_Zoom_Integration($wpdb);
