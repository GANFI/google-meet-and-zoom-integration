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
		register_rest_route($this->plugin_name . '/v1', '/save-token/', array(
			'methods' => 'POST',
			'callback' => [$this, 'save_token'],
			'permission_callback' => [$this, 'check_auth'],
		));

		register_rest_route($this->plugin_name . '/v1', '/info/', array(
			'methods' => 'GET',
			'callback' => [$this, 'get_info'],
			'permission_callback' => [$this, 'check_auth'],
		));
	}

	public function check_auth($request)
	{
		return wp_get_current_user()->ID !== 0;
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

		wp_enqueue_style($this->plugin_name . '-woocommerce-style', plugin_dir_url(__FILE__) . 'assets/woocommerce.css', [], self::VERSION);

		add_action('woocommerce_account_my-meets_endpoint', function () {
			echo do_shortcode('[google-meet-and-zoom-integration]');
		});

	}

	public function save_token($data)
	{
		if (isset($data['token']) && isset($data['type']) && isset($data['expire'])) {
			$current_time = current_time('mysql');
			$token_info = [
				'token' => $data['token'],
				'expire' => date('Y-m-d H:i:s', strtotime($current_time . ' + ' . $data['expire'] . 'seconds')),
			];

			update_user_meta(wp_get_current_user()->ID, $data['type'] . '_token', json_encode($token_info));

			return [
				'status' => 'ok'
			];
		}
	}

	public function get_info($data)
	{
		$user_id = wp_get_current_user()->ID;

		if (isset($data['user_id'])) {
			$user_id = $data['user_id'];
		}

		$google_token = get_user_meta($user_id, 'google_token', true);
		$zoom_token = get_user_meta($user_id, 'zoom_token', true);

		return [
			'status' => 'ok',
			'data' => [
				'google_connected' => !empty($google_token),
				'zoom_connected' => !empty($zoom_token)
			]
		];
	}
}

global $wpdb;

new Google_Meet_And_Zoom_Integration($wpdb);
