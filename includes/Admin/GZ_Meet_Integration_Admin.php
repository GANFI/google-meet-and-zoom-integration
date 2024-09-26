<?php
/**
 * Text Domain: google-meet-and-zoom-integration
 */

namespace Ganfi\GoogleMeetAndZoomIntegration\Admin;

final class GZ_Meet_Integration_Admin
{
	private string $plugin_name = 'google-meet-and-zoom-integration';

	public function __construct()
    {
		add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_select2' ] );
		add_action( 'admin_init', [ $this, 'save_permissions' ] );
		add_action('plugins_loaded', [ $this, 'load_text_domain' ]);
	}

	public function load_text_domain()
	{
		load_plugin_textdomain('google-meet-and-zoom-integration', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	public function add_settings_page()
    {
		add_menu_page(
			__( 'Meet Permissions', 'google-meet-and-zoom-integration' ),
			__( 'Meet Permissions', 'google-meet-and-zoom-integration' ),
			'manage_options',
			'gz_meet_permissions',
			[ $this, 'render_settings_page' ],
			'',
			20
		);
	}

	public function enqueue_select2( $hook )
    {
		if ( $hook !== 'toplevel_page_gz_meet_permissions' ) {
			return;
		}

		wp_enqueue_script( 'select2',
			'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
			[ 'jquery' ], null, true );
		wp_enqueue_style( 'select2-style',
			'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css' );
		wp_enqueue_script( 'gz_meet_script', plugin_dir_url( __FILE__ )
		                                     . '../../assets/js/gz_meet_admin_script.js',
			[ 'jquery', 'select2' ], null, true );
	}

	public function render_settings_page()
    {
		$users = get_users( [
			'meta_query' => [
				'relation' => 'OR',
				[
					'key'     => 'arm_user_last_plan',
					'value'   => 1,
					'compare' => '!=',
				],
				[
					'key'     => 'arm_user_last_plan',
					'compare' => 'NOT EXISTS',
				],
			],
		] );

		// Fetch current permissions for "all" and "my" roles
		$permissions_all = [];
		$permissions_my  = [];

		foreach ( $users as $user ) {
			$permissions = get_user_meta( $user->ID, 'gz_meet_permission',
				true );
			if ( isset( $permissions['all'] )
			     && $permissions['all'] === true
			) {
				$permissions_all[] = $user->ID;
			}
			if ( isset( $permissions['my'] ) && $permissions['my'] === true ) {
				$permissions_my[] = $user->ID;
			}
		}

		$zoom_api_key         = get_option( 'gz_meet_zoom_api_key', '' );
		$zoom_api_secret      = get_option( 'gz_meet_zoom_api_secret', '' );
		$zoom_api_redirect    = get_option( 'gz_meet_zoom_api_redirect', '' );
		$google_client_id     = get_option( 'gz_meet_google_client_id', '' );
		$google_client_secret = get_option( 'gz_meet_google_client_secret', '');
		$google_redirect_url  = get_option( 'gz_meet_google_redirect_url', '' );

		?>
        <div class="wrap">
            <h1><?php _e( 'Meet Permissions', 'google-meet-and-zoom-integration' ); ?></h1>
            <form method="post">
				<?php wp_nonce_field( 'gz_meet_permissions_nonce' ); ?>

                <!-- Permissions Section -->
                <h3><?php _e( 'Can View/Edit/Create for All Clients',
						'google-meet-and-zoom-integration' ); ?></h3>
                <select name="gz_meet_permission_all[]" multiple="multiple"
                        class="gz-meet-select2" style="width: 100%;">
					<?php foreach ( $users as $user ) : ?>
                        <option value="<?php echo esc_attr( $user->ID ); ?>"
							<?php echo in_array( $user->ID, $permissions_all )
								? 'selected' : ''; ?>
                        >
							<?php echo esc_html( $user->display_name ); ?>
                        </option>
					<?php endforeach; ?>
                </select>

                <h3><?php _e( 'Can View/Edit/Create Only for Their Clients',
						'google-meet-and-zoom-integration' ); ?></h3>
                <select name="gz_meet_permission_my[]" multiple="multiple"
                        class="gz-meet-select2" style="width: 100%;">
					<?php foreach ( $users as $user ) : ?>
                        <option value="<?php echo esc_attr( $user->ID ); ?>"
							<?php echo in_array( $user->ID, $permissions_my )
								? 'selected' : ''; ?>
                        >
							<?php echo esc_html( $user->display_name ); ?>
                        </option>
					<?php endforeach; ?>
                </select>

                <!-- Zoom API Credentials -->
                <h3><?php _e( 'Zoom API Credentials',
						'google-meet-and-zoom-integration' ); ?></h3>
                <label for="zoom_api_key"><?php _e( 'Zoom API Key',
						'google-meet-and-zoom-integration' ); ?></label>
                <input type="text" id="zoom_api_key" name="zoom_api_key"
                       value="<?php echo esc_attr( $zoom_api_key ); ?>"
                       style="width: 100%;"/>

                <label for="zoom_api_secret"><?php _e( 'Zoom API Secret',
						'google-meet-and-zoom-integration' ); ?></label>
                <input type="text" id="zoom_api_secret" name="zoom_api_secret"
                       value="<?php echo esc_attr( $zoom_api_secret ); ?>"
                       style="width: 100%;"/>

                <label for="zoom_api_redirect"><?php _e( 'Zoom API Redirect URL',
						'google-meet-and-zoom-integration' ); ?></label>
                <input type="text" id="zoom_api_redirect"
                       name="zoom_api_redirect"
                       value="<?php echo esc_attr( $zoom_api_redirect ); ?>"
                       style="width: 100%;"/>

                <!-- Google API Credentials -->
                <h3><?php _e( 'Google API Credentials',
						'google-meet-and-zoom-integration' ); ?></h3>
                <label for="google_client_id"><?php _e( 'Google Client ID',
						'google-meet-and-zoom-integration' ); ?></label>
                <input type="text" id="google_client_id" name="google_client_id"
                       value="<?php echo esc_attr( $google_client_id ); ?>"
                       style="width: 100%;"/>

                <label for="google_client_secret"><?php _e( 'Google Client Secret',
						'google-meet-and-zoom-integration' ); ?></label>
                <input type="text" id="google_client_secret"
                       name="google_client_secret"
                       value="<?php echo esc_attr( $google_client_secret ); ?>"
                       style="width: 100%;"/>

                <label for="google_redirect_url"><?php _e( 'Google Redirect URL',
						'google-meet-and-zoom-integration' ); ?></label>
                <input type="text" id="google_redirect_url"
                       name="google_redirect_url"
                       value="<?php echo esc_attr( $google_redirect_url ); ?>"
                       style="width: 100%;"/>

                <!-- Submit Button -->
                <p>
                    <input type="submit" class="button-primary"
                           value="<?php esc_attr_e( 'Save Settings',
						       'google-meet-and-zoom-integration' ); ?>"
                    />
                </p>
            </form>
        </div>
		<?php
	}

	public function save_permissions() {
		if ( ! current_user_can( 'manage_options' )
		     || ! isset( $_POST['_wpnonce'] )
		     || ! wp_verify_nonce( $_POST['_wpnonce'],
				'gz_meet_permissions_nonce' )
		) {
			return;
		}

		$selected_all = isset( $_POST['gz_meet_permission_all'] )
			? array_map( 'intval', $_POST['gz_meet_permission_all'] ) : [];
		$selected_my  = isset( $_POST['gz_meet_permission_my'] )
			? array_map( 'intval', $_POST['gz_meet_permission_my'] ) : [];

		$users = get_users( [
			'meta_query' => [
				'relation' => 'OR',
				[
					'key'     => 'arm_user_last_plan',
					'value'   => 1,
					'compare' => '!=',
				],
				[
					'key'     => 'arm_user_last_plan',
					'value'   => 2,
					'compare' => '!=',
				],
				[
					'key'     => 'arm_user_last_plan',
					'compare' => 'NOT EXISTS',
				],
			],
		] );

		foreach ( $users as $user ) {
			$permission_data = [
				'all' => in_array( $user->ID, $selected_all ),
				'my'  => in_array( $user->ID, $selected_my ),
			];

            $permission = null;

            if ($permission_data['all']) $permission = 'all';
            if ($permission_data['my']) $permission = 'my';

			update_user_meta( $user->ID, 'gz_meet_permission', $permission );
		}

		update_option( 'gz_meet_zoom_api_key',
			sanitize_text_field( $_POST['zoom_api_key'] ) );
		update_option( 'gz_meet_zoom_api_secret',
			sanitize_text_field( $_POST['zoom_api_secret'] ) );
		update_option( 'gz_meet_zoom_api_redirect',
			sanitize_text_field( $_POST['zoom_api_redirect'] ) );

		update_option( 'gz_meet_google_client_id',
			sanitize_text_field( $_POST['google_client_id'] ) );
		update_option( 'gz_meet_google_client_secret',
			sanitize_text_field( $_POST['google_client_secret'] ) );
		update_option( 'gz_meet_google_redirect_url',
			sanitize_text_field( $_POST['google_redirect_url'] ) );
	}
}
