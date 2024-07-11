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

final class Google_Meet_And_Zoom_Integration
{
	const VERSION = '1.0.0';

	private string $plugin_name = 'google-meet-and-zoom-integration';

	protected $wpdb;

	public function __construct(wpdb $wpdb)
	{
		$this->wpdb = $wpdb;
		add_shortcode($this->plugin_name, [$this, 'shortcode']);
		add_action('rest_api_init', [$this, 'register_rest_endpoints']);
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
}

global $wpdb;

new Google_Meet_And_Zoom_Integration($wpdb);
