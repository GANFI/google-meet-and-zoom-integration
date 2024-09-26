<?php
/**
 * Plugin Name:       Google Meet and Zoom Integration
 * Description:       Plugin for integration google meet and zoom
 * Version:           1.0.0
 * Author:            GANFI
 * Author URI:        https://t.me/GANFI/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       google-meet-and-zoom-integration
 */

require_once __DIR__. '/vendor/autoload.php';

use Ganfi\GoogleMeetAndZoomIntegration\Admin\GZ_Meet_Integration_Admin;
use Ganfi\GoogleMeetAndZoomIntegration\Meet\Google\GoogleMeeting;
use Ganfi\GoogleMeetAndZoomIntegration\Meet\Zoom\ZoomMeeting;

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

	private string $meets_table = 'gz_meets';
	private string $participants_table = 'gz_meet_participants';

	private ZoomMeeting $zoom_meeting;
	private GoogleMeeting $google_meeting;

	const NEW_STATUS = 'new';
	const PASSED_STATUS = 'passed';
	const CANCELED_STATUS = 'canceled';

	public function __construct(wpdb $wpdb)
	{
		$this->wpdb = $wpdb;

		add_action('plugins_loaded', [ $this, 'load_text_domain' ]);

		if (is_admin()) {
			new GZ_Meet_Integration_Admin();
		}

		$this->create_meets_page();
		$this->create_meeting_providers();

		add_shortcode($this->plugin_name, [$this, 'shortcode']);
		add_action('rest_api_init', [$this, 'register_rest_endpoints']);

		register_activation_hook(__FILE__, function () {
			$this->create_tables();
			add_rewrite_endpoint('my-meets', EP_ROOT | EP_PAGES);
			flush_rewrite_rules();
		});

		$this->cron_job();
		add_action('google_meet_and_zoom_integration_cron_job', [$this, 'change_status']);
	}

	public function load_text_domain()
	{
		load_plugin_textdomain('google-meet-and-zoom-integration', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	private function create_tables() {
		$charset_collate = $this->wpdb->get_charset_collate();

		$meets_table        = $this->wpdb->prefix . $this->meets_table;
		$participants_table = $this->wpdb->prefix . $this->participants_table;

		$sql_meets = "CREATE TABLE IF NOT EXISTS $meets_table (
            id INT(11) NOT NULL AUTO_INCREMENT,
            meet_id VARCHAR(255) NOT NULL,
            provider VARCHAR(255) NOT NULL,
            creator INT(11) NOT NULL,
            link VARCHAR(255) NOT NULL,
            date DATETIME NOT NULL,
            status VARCHAR(255) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

		$sql_participants = "CREATE TABLE IF NOT EXISTS $participants_table (
            meet_id INT(11) NOT NULL,
            user_id INT(11) NOT NULL,
            plan_id INT(11) NOT NULL,
            FOREIGN KEY (meet_id) REFERENCES $meets_table(id) ON DELETE CASCADE
        ) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql_meets );
		dbDelta( $sql_participants );
	}

	public function enqueue_scripts(): void
	{
		wp_enqueue_style($this->plugin_name . '-style', plugin_dir_url(__FILE__) . 'build/index.css', [], self::VERSION);
		wp_enqueue_script($this->plugin_name . '-script', plugin_dir_url(__FILE__) . 'build/index.js', ['wp-element', 'wp-i18n'], self::VERSION, true);
		wp_set_script_translations(
			$this->plugin_name . '-script',
			'google-meet-and-zoom-integration',
			plugin_dir_path( __FILE__ ) . 'languages'
		);
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

		register_rest_route($this->plugin_name . '/v1', '/permissions/', array(
			'methods' => 'GET',
			'callback' => [$this, 'get_permissions'],
			'permission_callback' => [$this, 'check_auth'],
		));

		register_rest_route($this->plugin_name . '/v1', '/doctors/', array(
			'methods' => 'GET',
			'callback' => [$this, 'get_doctors'],
			'permission_callback' => [$this, 'check_auth'],
		));

		register_rest_route($this->plugin_name . '/v1', '/patients/', array(
			'methods' => 'GET',
			'callback' => [$this, 'get_patients'],
			'permission_callback' => [$this, 'check_auth'],
		));

		register_rest_route($this->plugin_name . '/v1', '/create-meet/', array(
			'methods' => 'POST',
			'callback' => [$this, 'create_meet'],
			'permission_callback' => [$this, 'check_auth'],
		));

		register_rest_route($this->plugin_name . '/v1', '/cancel-meet/', array(
			'methods' => 'POST',
			'callback' => [$this, 'cancel_meet'],
			'permission_callback' => [$this, 'check_auth'],
		));

		register_rest_route($this->plugin_name . '/v1', '/meetings/', array(
			'methods' => 'GET',
			'callback' => [$this, 'get_meetings'],
			'permission_callback' => [$this, 'check_auth'],
		));

		register_rest_route($this->plugin_name . '/v1', '/download/', array(
			'methods' => 'POST',
			'callback' => [$this, 'download_meeting'],
			'permission_callback' => [$this, 'check_auth'],
		));

	}

	public function check_auth($request)
	{
		return wp_get_current_user()->ID !== 0;
	}

	private function create_meeting_providers(): void
	{
		$this->zoom_meeting = new ZoomMeeting(
			get_option('gz_meet_zoom_api_key', ''),
			get_option('gz_meet_zoom_api_secret', ''),
			get_option( 'gz_meet_zoom_api_redirect', '' )
		);
		$this->google_meeting = new GoogleMeeting(
			get_option('gz_meet_google_client_id', ''),
			get_option('gz_meet_google_client_secret', ''),
			get_option( 'gz_meet_google_redirect_url', '' )
		);
	}

	private function get_meeting_provider(string $type): GoogleMeeting|ZoomMeeting|null
	{
		return match ($type) {
			'google' => $this->google_meeting,
			'zoom' => $this->zoom_meeting,
			default => null,
		};
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
					$new_items['my-meets'] = __('My Meets', 'google-meet-and-zoom-integration');
					$new_items[$key] = $value;
				}
			}
			return $new_items;
		}, 0);

		wp_enqueue_style($this->plugin_name . '-woocommerce-style',
			plugin_dir_url( __FILE__ ) . 'assets/css/woocommerce.css', [], self::VERSION);

		add_action('woocommerce_account_my-meets_endpoint', function () {
			echo do_shortcode('[google-meet-and-zoom-integration]');
		});

	}

	public function save_token($data)
	{
		if (isset($data['token']) && isset($data['type'])) {

			if(!empty($provider = $this->get_meeting_provider($data['type']))) {
				$provider->auth([
					'code' => $data['token'],
					'user_id' => wp_get_current_user()->ID,
				]);
			}

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

	public function set_permissions($request)
	{
		$permissions = [
			'filters' => [
				'doctor' => false,
				'users' => false,
				'users_all' => false,
			],
			'meet' => [
				'create' => false,
				'edit' => false,
				'cancel' => false,
				'download' => false,
			]
		];

		$user = wp_get_current_user();
		$plan = (int)is_plan_armmemeber_user($user->ID);

		if ($plan === 3 || $plan === 4) {
			$permissions = [
				'filters' => [
					'doctor' => true,
					'users' => true,
				],
				'meet' => [
					'create' => true,
					'edit' => true,
					'cancel' => true,
					'download' => false,
				]
			];
		}

		$user_permissions = get_user_meta($user->ID, 'gz_meet_permission', true);
		if ($user_permissions === 'all') {
			$permissions['filters']['users_all'] = true;
		}

		if (current_user_can('manage_options')) {
			$permissions = [
				'filters' => [
					'doctor' => true,
					'users' => true,
					'users_all' => true,
				],
				'meet' => [
					'create' => true,
					'edit' => true,
					'cancel' => true,
					'download' => true,
				]
			];
		}

		return $permissions;
	}

	public function get_permissions($request)
	{
		return rest_ensure_response([
			'status' => 'ok',
			'data' => [
				'permissions' => $this->set_permissions($request)
			]
		]);
	}

	public function get_doctors($request) {
		$args = [
			'meta_key'   => 'arm_user_last_plan',
			'meta_value' => 3,
			'meta_compare' => '=',
		];

		if ((int)is_plan_armmemeber_user(wp_get_current_user()->ID) === 3) {
			$args['include'] = wp_get_current_user()->ID;
		}

		$users = get_users($args);
		$response = [];

		foreach ($users as $user) {
			$google_token = get_user_meta($user->ID, 'google_token', true);
			$zoom_token   = get_user_meta($user->ID, 'zoom_token', true);

			$response[] = [
				'user_id'   => $user->ID,
				'name'      => $user->display_name,
				'google'    => !empty($google_token),
				'zoom'      => !empty($zoom_token),
			];
		}

		return rest_ensure_response([
			'status' => 'ok',
			'data' => [
				'doctors' => $response
			]
		]);
	}

	public function get_patients($request)
	{
		$permissions = $this->set_permissions($request);

		$args = [
			'meta_query' => [
				'relation' => 'AND',
				[
					'key'     => 'arm_user_last_plan',
					'value'   => 2,
					'compare' => '='
				]
			]
		];

		if (!isset($permissions['filters']['users_all'])) {
			$args['meta_query'][] = [
				'key'     => 'mydoctor',
				'value'   => wp_get_current_user()->ID,
				'compare' => '='
			];
		}

		$users = get_users($args);
		$response = [];

		foreach ($users as $user) {
			$doctor_id = get_user_meta($user->ID, 'mydoctor', true);

			$response[] = [
				'user_id'   => $user->ID,
				'name'      => $user->display_name,
				'doctor_id' => $doctor_id,
			];
		}

		return rest_ensure_response([
			'status' => 'ok',
			'data' => [
				'patients' => $response
			]
		]);
	}

	private function get_status(string $status)
	{
		return match ($status) {
			self::NEW_STATUS => __( 'Active', 'google-meet-and-zoom-integration' ),
			self::PASSED_STATUS => __( 'Passed', 'google-meet-and-zoom-integration' ),
			self::CANCELED_STATUS => __( 'Canceled', 'google-meet-and-zoom-integration' )
		};
	}

	public function get_meetings($request)
	{
		$meets_table = $this->wpdb->prefix . 'gz_meets';
		$participants_table = $this->wpdb->prefix . 'gz_meet_participants';
		$users_table = $this->wpdb->prefix . 'users';

		$permissions = $this->set_permissions($request);

		$page = max(1, (int)$request->get_param('page'));
		$per_page = max(10, (int)$request->get_param('per_page'));
		$date_from = $request->get_param('date_from');
		$date_to = $request->get_param('date_to');
		$status = $request->get_param('status');
		$user_ids = $request->get_param('user_id');
		$doctor_ids = $request->get_param('doctor_id');

		$base_query = "SELECT m.id, m.status, m.date, m.provider, m.link,
                         u1.display_name AS doctor_name, u1.ID AS doctor_id, u1.user_email AS doctor_email,
                         u2.display_name AS user_name, u2.ID AS user_id, u2.user_email AS user_email 
                  FROM $meets_table m 
                  INNER JOIN $participants_table p1 ON p1.meet_id = m.id AND p1.plan_id = 3
                  INNER JOIN $users_table u1 ON u1.ID = p1.user_id
                  INNER JOIN $participants_table p2 ON p2.meet_id = m.id AND p2.plan_id = 2
                  INNER JOIN $users_table u2 ON u2.ID = p2.user_id
                  WHERE 1=1";

		if ($date_from) {
			$base_query .= $this->wpdb->prepare(" AND m.date >= %s", date('Y-m-d H:i:s', strtotime($date_from)));
		}
		if ($date_to) {
			$base_query .= $this->wpdb->prepare(" AND m.date <= %s", date('Y-m-d H:i:s', strtotime($date_to)));
		}
		if ($status) {
			$base_query .= $this->wpdb->prepare(" AND m.status = %s", $status);
		}

		if ($permissions['meet']['create'] === false) {
			$user_ids = (string)wp_get_current_user()->ID;
		}

		if (empty($user_ids) && $permissions['meet']['create'] && (!isset($permissions['filters']['users_all']) || $permissions['filters']['users_all'] === false)) {
			$args = [
				'meta_query' => [
					'relation' => 'AND',
					[
						'key'     => 'arm_user_last_plan',
						'value'   => 2,
						'compare' => '='
					],
					[
						'key'     => 'mydoctor',
						'value'   => wp_get_current_user()->ID,
						'compare' => '='
					]
				],
				'fields' => 'ID'
			];

			$users = get_users($args);

			$user_ids = implode(',', $users);
		}

		if (!empty($user_ids)) {
			$base_query .= " AND p2.user_id IN ($user_ids)";
		}
		if (!empty($doctor_ids)) {
			$base_query .= " AND p1.user_id IN ($doctor_ids)";
		}

		$count_query = "SELECT COUNT(*) FROM ($base_query) AS total";
		$total_records = $this->wpdb->get_var($count_query);

		$offset = ($page - 1) * $per_page;
		$query_with_pagination = $base_query . $this->wpdb->prepare(" LIMIT %d OFFSET %d", $per_page, $offset);

		$results = $this->wpdb->get_results($query_with_pagination);

		$data = array();
		foreach ($results as $result) {
			$data[] = array(
				'id'      => $result->id,
				'status'  => $result->status,
				'status_name' => $this->get_status($result->status),
				'date'    => $result->date,
				'user'    => array(
					'id'    => $result->user_id,
					'name'  => $result->user_name,
					'email' => $result->user_email,
				),
				'doctor'  => array(
					'id'    => $result->doctor_id,
					'name'  => $result->doctor_name,
					'email' => $result->doctor_email,
				),
				'provider' => $result->provider,
				'link'     => $result->link,
			);
		}

		$total_pages = ceil($total_records / $per_page);
		$pagination_info = array(
			'current_page' => $page,
			'total_pages'  => $total_pages,
			'per_page'     => $per_page,
			'total_records'=> (int) $total_records,
		);

		return new WP_REST_Response(array(
			'status' => 'ok',
			'data' => [
				'meetings' => $data,
				'pagination' => $pagination_info,
			],
		), 200);
	}

	public function create_meet($request)
	{
		$doctor = get_user_by('id', $request['doctor_id']);
		$client = get_user_by('id', $request['client_id']);
		$type = $request['type'];
		$date = $request['date'];

		$meet_id = $request['meet_id'];

		if (!empty($date) && !empty($doctor) && !empty($client) && !empty($type)) {
			$provider = $this->get_meeting_provider($type);
			if (!empty($provider)) {

				$current_user = wp_get_current_user();

				$is_meet_connected_for_doctor = get_user_meta($doctor->ID, $type . '_token', true);
				$is_meet_connected_for_current_user = get_user_meta($current_user->ID, $type . '_token', true);

				$user_id = null;

				if ($is_meet_connected_for_current_user) $user_id = $current_user->ID;
				if ($is_meet_connected_for_doctor) $user_id = $doctor->ID;
				if (empty($user_id)) throw new \Exception('Meet provide isn\'t connected.');

				$mock_emails = ['ganfi1998@gmail.com', 'godesq17@gmail.com'];
				$emails = [
					$doctor->user_email,
					$client->user_email,
				];

				$end_date = new DateTime($date, new DateTimeZone('UTC'));

				$meet_info = $provider->create([
					'user_id' => $user_id,
					'topic' => __('Consultation'),
					'start_time' => $date,
					'end_time' => $end_date->modify('+40 minutes')->format('Y-m-d\TH:i:sP'),
					'timezone' => 'Europe/Kiev',
					'duration' => 40,
					'participants' => $mock_emails
				]);

				$participants = [
					[
						'user_id' => $doctor->ID,
						'plan_id' => 3
					],
					[
						'user_id' => $client->ID,
						'plan_id' => 2
					],
				];
				$dateTime = new DateTime($date, new DateTimeZone('UTC'));
				$dateTime->setTimezone(new DateTimeZone('Europe/Kiev'));
				$this->save_meet([
					'meeting_id' => $meet_info['meeting_id'],
					'provider' => $type,
					'creator' => $user_id,
					'link'   => $meet_info['meeting_link'],
					'date' => $dateTime->format('Y-m-d H:i:s'),
					'status' => self::NEW_STATUS,
					'participants' => $participants
				]);

				return rest_ensure_response([
					'status' => 'ok',
					'data' => []
				]);
			}
		}

		if (!empty($meet_id) && !empty($date) && !empty($type)) {
			$meet = $this->get_meet((int)$meet_id, $type);
			if (!empty($meet)) {
				$provider = $this->get_meeting_provider($type);
				if (!empty($provider)) {
					$end_date = new DateTime($date, new DateTimeZone('UTC'));
					$provider->updateMeetingDate([
						'user_id' => $meet->creator,
						'meeting_id' => $meet->meet_id,
						'start_time' => $date,
						'end_time' => $end_date->modify('+40 minutes')->format('Y-m-d\TH:i:sP'),
						'timezone' => 'Europe/Kiev',
						'duration' => 40,
					]);
					$dateTime = new DateTime($date, new DateTimeZone('UTC'));
					$dateTime->setTimezone(new DateTimeZone('Europe/Kiev'));
					$this->update_meet($meet->id, [
						'date' => $dateTime->format('Y-m-d H:i:s'),
					]);

					return rest_ensure_response([
						'status' => 'ok',
						'data' => []
					]);
				}
			}
		}
	}

	public function cancel_meet($request)
	{
		$meet_id = $request['meet_id'];
		$type = $request['type'];
		if (!empty($meet_id)) {
			$meet = $this->get_meet($meet_id, $type);
			if (!empty($meet)) {
				$provider = $this->get_meeting_provider($type);
				if (!empty($provider)) {
					$provider->cancel([
						'user_id' => $meet->creator,
						'meeting_id' => $meet->meet_id,
					]);
					$this->update_meet($meet->id, [
						'status' => self::CANCELED_STATUS,
					]);

					return rest_ensure_response([
						'status' => 'ok',
						'data' => []
					]);
				}
			}
		}
	}

	public function save_meet($data)
	{
		$meets_table = $this->wpdb->prefix . $this->meets_table;
		$meet_data = array(
			'meet_id' => $data['meeting_id'],
			'provider'=> $data['provider'],
			'creator' => $data['creator'],
			'link'   => $data['link'],
			'date' => $data['date'],
			'status' => $data['status']
		);
		$meet = $this->wpdb->insert($meets_table, $meet_data);

		if ($meet === false) {
			return new WP_Error('db_insert_error', 'Could not insert meeting data', $this->wpdb->last_error);
		}

		$meet_id = $this->wpdb->insert_id;

		$participants_table = $this->wpdb->prefix . $this->participants_table;
		foreach ($data['participants'] as $participant) {
			$inserted = $this->wpdb->insert($participants_table, [
				'meet_id' => $meet_id,
				'user_id' => $participant['user_id'],
				'plan_id' => $participant['plan_id']
			]);
			if ($inserted === false) {
				return new WP_Error('db_insert_error', 'Could not insert meeting data', $this->wpdb->last_error);
			}
		}

	}

	public function get_meet($meet_id, $provider)
	{
		$meets_table = $this->wpdb->prefix . $this->meets_table;
		return $this->wpdb->get_row("SELECT * FROM $meets_table WHERE id = $meet_id AND provider = '$provider'");
	}

	public function update_meet($id, $data)
	{
		$meets_table = $this->wpdb->prefix . $this->meets_table;
		$this->wpdb->update($meets_table, $data, ['id' => $id]);
	}

	public function change_status()
	{
		$meets_table = $this->wpdb->prefix . $this->meets_table;

		$current_time = current_time('mysql');

		$new_status = self::NEW_STATUS;
		$passed_status = self::PASSED_STATUS;

		$this->wpdb->query(
			$this->wpdb->prepare(
				"UPDATE $meets_table 
                 SET status = '{$passed_status}'
                 WHERE date < %s AND status = '{$new_status}'",
				$current_time
			)
		);
	}

	public function cron_job()
	{
		if (!wp_next_scheduled('google_meet_and_zoom_integration_cron_job')) {
			wp_schedule_event(time(), 'hourly', 'google_meet_and_zoom_integration_cron_job');
		}
	}

	public function download_meeting($request)
	{
		$type = $request['type'];
		$meet_id = 15;
		if (!empty($type) &&!empty($meet_id)) {
			$provider = $this->get_meeting_provider($type);
			if (!empty($provider)) {
				$meet = $this->get_meet($meet_id, $type);
				if (!empty($meet)) {
					$provider->download([
						'meeting_id' => $meet->meet_id,
						'user_id' => $meet->creator
					]);

					return rest_ensure_response([
						'status' => 'ok',
						'data' => []
					]);
				}
			}
		}
	}

}

global $wpdb;

new Google_Meet_And_Zoom_Integration($wpdb);
