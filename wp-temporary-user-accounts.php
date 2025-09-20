<?php
/**
 * Plugin Name:     WordPress Temporary User Accounts
 * Plugin URI:      https://github.com/soyunomas/wp-temporary-user-accounts
 * Description:     Permite configurar usuarios (excepto administradores) para que cambien su rol automáticamente después de un tiempo o en una fecha específica.
 * Version:         1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Soyunomas
 * Author URI:        https://github.com/soyunomas/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-temporary-user-accounts
 * Domain Path:       /languages
 *
 * @package         TemporaryUserAccounts
 */

// Exit if accessed directly for security.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Temporary_User_Accounts {

	private const VERSION = '1.0.0';
	private const EXPIRY_META_KEY = '_tua_expiry_timestamp';
	private const TARGET_ROLE_META_KEY = '_tua_target_role';
	private const SETTING_DISPLAY_META_KEY = '_tua_setting_display';
	private const CRON_HOOK = 'tua_change_user_role_event';
	private const NONCE_ACTION = 'tua_save_user_expiry_settings';

	private static $instance = null;

	/**
	 * Singleton pattern. Ensures only one instance of the class is loaded.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor. Sets up all the hooks.
	 */
	private function __construct() {
		add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
		
		// Display fields on user forms.
		add_action( 'user_new_form', [ $this, 'display_fields_on_form' ] );
		add_action( 'show_user_profile', [ $this, 'display_fields_on_form' ] );
		add_action( 'edit_user_profile', [ $this, 'display_fields_on_form' ] );

		// Save user meta and schedule cron.
		add_action( 'user_register', [ $this, 'save_expiry_settings' ], 10, 1 );
		add_action( 'profile_update', [ $this, 'save_expiry_settings' ], 10, 1 );

		// Cron callback.
		add_action( self::CRON_HOOK, [ $this, 'run_user_role_change' ], 10, 1 );

		// Admin columns.
		add_filter( 'manage_users_columns', [ $this, 'add_users_table_column' ] );
		add_filter( 'manage_users_custom_column', [ $this, 'render_users_table_column' ], 10, 3 );
		
		// Deactivation hook for cleanup.
		register_deactivation_hook( __FILE__, [ $this, 'deactivate' ] );
	}
	
	/**
	 * Loads the plugin text domain for internationalization.
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'wp-temporary-user-accounts',
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages'
		);
	}

	/**
	 * Enqueues admin scripts and styles.
	 *
	 * @param string $hook The current admin page hook.
	 */
	public function enqueue_admin_assets( string $hook ) {
		$allowed_hooks = [ 'user-new.php', 'profile.php', 'user-edit.php' ];
		if ( ! in_array( $hook, $allowed_hooks, true ) ) {
			return;
		}

		wp_enqueue_style(
			'tua-admin-styles',
			plugin_dir_url( __FILE__ ) . 'assets/css/tua-admin.css',
			[],
			self::VERSION
		);
		
		wp_enqueue_script(
			'tua-admin-script',
			plugin_dir_url( __FILE__ ) . 'assets/js/tua-admin.js',
			[ 'jquery', 'jquery-ui-datepicker' ],
			self::VERSION,
			true // Load in footer.
		);
		
		// Add datepicker styles if not already present.
		wp_enqueue_style( 'jquery-ui-datepicker-style', '//ajax.googleapis.com/ajax/libs/jqueryui/1.10.4/themes/smoothness/jquery-ui.css' );
	}

	/**
	 * Displays the expiry configuration fields on user profile and new user pages.
	 *
	 * @param WP_User|string $user_or_context WP_User object on profile pages, string context on new user page.
	 */
	public function display_fields_on_form( $user_or_context ) {
		$is_profile_page = ( $user_or_context instanceof WP_User );
		$user            = $is_profile_page ? $user_or_context : null;

		// Security Check: Only show fields if user has permission.
		if ( $is_profile_page ) {
			if ( ! current_user_can( 'edit_user', $user->ID ) ) {
				return;
			}
		} elseif ( ! current_user_can( 'create_users' ) ) {
			return;
		}

		// Don't show for administrators.
		if ( $user && user_can( $user, 'administrator' ) ) {
			printf(
				'<p><em>%s</em></p>',
				esc_html__( 'La configuración de expiración no se aplica a los administradores.', 'wp-temporary-user-accounts' )
			);
			return;
		}

		$current_expiry_ts     = $user ? (int) get_user_meta( $user->ID, self::EXPIRY_META_KEY, true ) : 0;
		$current_target_role   = $user ? get_user_meta( $user->ID, self::TARGET_ROLE_META_KEY, true ) : 'subscriber';
		$current_setting_display = $user ? get_user_meta( $user->ID, self::SETTING_DISPLAY_META_KEY, true ) : '';
		$current_target_role   = $current_target_role ?: 'subscriber';

		// Determine current expiry type from saved data.
		$current_expiry_type = 'none';
		if ( $current_expiry_ts > 0 && $current_expiry_ts > time() ) {
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $current_setting_display ) ) {
				$current_expiry_type = 'specific';
			} else {
				$current_expiry_type = 'relative';
			}
		}

		$relative_duration_value = ( 'relative' === $current_expiry_type && !empty( $current_setting_display ) ) ? array_search( $current_setting_display, $this->get_relative_duration_options(), true ) : '';
		$specific_date_value     = ( 'specific' === $current_expiry_type ) ? $current_setting_display : '';

		?>
		<h2><?php esc_html_e( 'Configuración de Cuenta Temporal', 'wp-temporary-user-accounts' ); ?></h2>
		<table class="form-table tua-settings-table" role="presentation">
			<tr class="form-field">
				<th scope="row"><label><?php esc_html_e( 'Expiración Automática', 'wp-temporary-user-accounts' ); ?></label></th>
				<td>
					<fieldset>
						<legend class="screen-reader-text"><span><?php esc_html_e( 'Expiración Automática', 'wp-temporary-user-accounts' ); ?></span></legend>
						
						<p><label><input type="radio" name="tua_expiry_type" value="none" <?php checked( $current_expiry_type, 'none' ); ?> class="tua-expiry-radio"> <?php esc_html_e( 'Nunca (Cuenta Permanente)', 'wp-temporary-user-accounts' ); ?></label></p>
						
						<p><label><input type="radio" name="tua_expiry_type" value="relative" <?php checked( $current_expiry_type, 'relative' ); ?> class="tua-expiry-radio"> <?php esc_html_e( 'Cambiar Rol después de:', 'wp-temporary-user-accounts' ); ?></label></p>
						<div class="tua_options_wrapper tua_relative_options">
							<select name="tua_relative_duration" id="tua_relative_duration" style="min-width: 180px;">
								<option value=""><?php esc_html_e( '-- Seleccionar Duración --', 'wp-temporary-user-accounts' ); ?></option>
								<?php foreach ( $this->get_relative_duration_options() as $seconds => $label ) : ?>
									<option value="<?php echo esc_attr( $seconds ); ?>" <?php selected( $relative_duration_value, $seconds ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>

						<p><label><input type="radio" name="tua_expiry_type" value="specific" <?php checked( $current_expiry_type, 'specific' ); ?> class="tua-expiry-radio"> <?php esc_html_e( 'Cambiar Rol en la fecha:', 'wp-temporary-user-accounts' ); ?></label></p>
						<div class="tua_options_wrapper tua_specific_options">
							<input type="text" name="tua_specific_date" id="tua_specific_date" class="tua-datepicker" value="<?php echo esc_attr( $specific_date_value ); ?>" size="15" placeholder="YYYY-MM-DD" autocomplete="off">
							<p class="description" style="margin-top: 2px;"><?php esc_html_e( 'El rol cambiará al final del día seleccionado (zona horaria del sitio).', 'wp-temporary-user-accounts' ); ?></p>
						</div>
					</fieldset>
				</td>
			</tr>
			<tr class="form-field tua_target_role_wrapper">
				<th scope="row"><label for="tua_target_role"><?php esc_html_e( 'Rol Después de Expirar', 'wp-temporary-user-accounts' ); ?></label></th>
				<td>
					<?php 
					$user_roles = $user ? $user->roles : [];
					$target_role_options = $this->get_target_role_options( $user_roles );
					if ( ! empty( $target_role_options ) ) : ?>
						<select name="tua_target_role" id="tua_target_role">
							<?php foreach ( $target_role_options as $role_key => $role_name ) : ?>
								<option value="<?php echo esc_attr( $role_key ); ?>" <?php selected( $current_target_role, $role_key ); ?>><?php echo esc_html( $role_name ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Selecciona el rol que tendrá el usuario cuando la cuenta expire.', 'wp-temporary-user-accounts' ); ?></p>
					<?php else : ?>
						<p><em><?php esc_html_e( 'No hay roles inferiores disponibles para seleccionar.', 'wp-temporary-user-accounts' ); ?></em></p>
						<input type="hidden" name="tua_target_role" value="subscriber">
					<?php endif; ?>
				</td>
			</tr>
		</table>
		<?php
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_ACTION . '_nonce' );
	}

	/**
	 * Saves the expiry settings meta for a user and schedules/clears the cron job.
	 *
	 * @param int $user_id The ID of the user being saved.
	 */
	public function save_expiry_settings( int $user_id ) {
		// --- 1. Security First: Verify Nonce and Permissions ---
		if ( ! isset( $_POST[ self::NONCE_ACTION . '_nonce' ] ) || ! wp_verify_nonce( sanitize_key( $_POST[ self::NONCE_ACTION . '_nonce' ] ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		// --- 2. Prevent saving for Administrators ---
		$user_data = get_userdata( $user_id );
		if ( $user_data && in_array( 'administrator', $user_data->roles, true ) ) {
			$this->clear_user_expiry_data( $user_id );
			return;
		}

		// --- 3. Sanitize and Validate all inputs ---
		$expiry_type       = isset( $_POST['tua_expiry_type'] ) ? sanitize_key( $_POST['tua_expiry_type'] ) : 'none';
		$relative_duration = isset( $_POST['tua_relative_duration'] ) ? absint( $_POST['tua_relative_duration'] ) : 0;
		$specific_date_str = isset( $_POST['tua_specific_date'] ) ? sanitize_text_field( wp_strip_all_tags( $_POST['tua_specific_date'] ) ) : '';
		$target_role_input = isset( $_POST['tua_target_role'] ) ? sanitize_key( $_POST['tua_target_role'] ) : 'subscriber';

		// Validate that the submitted target role is a valid choice.
		$available_roles = $this->get_target_role_options( $user_data->roles );
		$target_role     = array_key_exists( $target_role_input, $available_roles ) ? $target_role_input : 'subscriber';

		// --- 4. Calculate Expiry Timestamp ---
		$expiry_timestamp    = 0;
		$setting_description = '';
		$allowed_durations = $this->get_relative_duration_options();

		if ( 'relative' === $expiry_type && $relative_duration > 0 && isset( $allowed_durations[ $relative_duration ] ) ) {
			$expiry_timestamp    = time() + $relative_duration;
			$setting_description = $allowed_durations[ $relative_duration ];
		} elseif ( 'specific' === $expiry_type && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $specific_date_str ) ) {
			try {
				// Set expiry to the end of the selected day in the site's timezone.
				$datetime = new DateTime( $specific_date_str . ' 23:59:59', wp_timezone() );
				$timestamp = $datetime->getTimestamp();
				if ( $timestamp > time() ) {
					$expiry_timestamp    = $timestamp;
					$setting_description = $specific_date_str;
				}
			} catch ( Exception $e ) {
				// Invalid date format, do nothing.
			}
		}

		// --- 5. Always clear the old cron job first for consistency ---
		$this->clear_user_cron( $user_id );
		
		// --- 6. Update Meta and Schedule New Cron Job ---
		if ( $expiry_timestamp > 0 ) {
			update_user_meta( $user_id, self::EXPIRY_META_KEY, $expiry_timestamp );
			update_user_meta( $user_id, self::TARGET_ROLE_META_KEY, $target_role );
			update_user_meta( $user_id, self::SETTING_DISPLAY_META_KEY, $setting_description );

			$cron_args = [ 'user_id' => $user_id, 'target_role' => $target_role ];
			wp_schedule_single_event( $expiry_timestamp, self::CRON_HOOK, $cron_args );
		} else {
			// If expiry is set to 'none' or is invalid, delete all meta.
			$this->clear_user_expiry_data( $user_id );
		}
	}
	
	/**
	 * Callback for the WP-Cron event to change the user's role.
	 *
	 * @param array $args The arguments passed from the cron job. Must contain 'user_id' and 'target_role'.
	 */
	public function run_user_role_change( array $args ) {
		// --- 1. Validate Arguments ---
		$user_id = isset( $args['user_id'] ) ? absint( $args['user_id'] ) : 0;
		$target_role = isset( $args['target_role'] ) ? sanitize_key( $args['target_role'] ) : '';

		if ( ! $user_id || empty( $target_role ) ) {
			error_log( 'TUA Cron Error: Invalid arguments received. Args: ' . print_r( $args, true ) );
			return;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			error_log( 'TUA Cron Error: User with ID ' . $user_id . ' not found.' );
			return;
		}

		// --- 2. Security & Sanity Checks ---
		// Final check: Never downgrade an administrator.
		if ( user_can( $user, 'administrator' ) ) {
			error_log( 'TUA Cron Info: Role change for user ' . $user_id . ' skipped. User is an administrator.' );
			$this->clear_user_expiry_data( $user_id );
			return;
		}

		// Final check: Ensure target role is valid and not 'administrator'.
		if ( ! get_role( $target_role ) || 'administrator' === $target_role ) {
			$target_role = 'subscriber';
			error_log( 'TUA Cron Warning: Invalid target role provided. Falling back to "subscriber" for user ID: ' . $user_id );
		}

		// Final check: Make sure the event hasn't fired prematurely or been cancelled.
		$expiry_meta = (int) get_user_meta( $user_id, self::EXPIRY_META_KEY, true );
		if ( ! $expiry_meta || $expiry_meta > time() ) {
			error_log( 'TUA Cron Info: Role change for user ' . $user_id . ' aborted. Event fired prematurely or meta was cleared.' );
			return;
		}

		// --- 3. Perform the Role Change ---
		$user->set_role( $target_role );

		// --- 4. Post-Change Actions ---
		error_log( 'TUA Cron Success: Role changed for user ' . $user_id . ' to ' . $target_role );
		
		// Destroy all user sessions to force re-login with new permissions.
		$sessions = WP_Session_Tokens::get_instance( $user_id );
		$sessions->destroy_all();
		
		// Clean up meta after successful change.
		$this->clear_user_expiry_data( $user_id );

		// Action hook for developers to tap into.
		do_action( 'tua_after_user_role_changed', $user_id, $target_role );
	}

	/**
	 * Adds the 'Expiry Status' column to the users table.
	 */
	public function add_users_table_column( array $columns ): array {
		$columns['tua_expiry_status'] = esc_html__( 'Expiración de Cuenta', 'wp-temporary-user-accounts' );
		return $columns;
	}

	/**
	 * Renders the content for the custom column in the users table.
	 */
	public function render_users_table_column( $output, string $column_name, int $user_id ): string {
		if ( 'tua_expiry_status' !== $column_name ) {
			return $output;
		}

		$expiry_timestamp = (int) get_user_meta( $user_id, self::EXPIRY_META_KEY, true );
		if ( ! $expiry_timestamp ) {
			return '<span style="color: #46b450;">' . esc_html__( 'Permanente', 'wp-temporary-user-accounts' ) . '</span>';
		}
		
		$target_role_key = get_user_meta( $user_id, self::TARGET_ROLE_META_KEY, true ) ?: 'subscriber';
		$target_role = get_role( $target_role_key );
		$target_role_name = $target_role ? $target_role->name : ucfirst($target_role_key);

		if ( $expiry_timestamp < time() ) {
			// Expired but cron hasn't run yet.
			$html = '<span style="color: #ffb900;">' . sprintf( esc_html__( 'Expirado (Pendiente de cambiar a %s)', 'wp-temporary-user-accounts' ), '<strong>' . esc_html( $target_role_name ) . '</strong>' ) . '</span>';
			$html .= '<br><small>' . sprintf( esc_html__( 'Fecha: %s', 'wp-temporary-user-accounts' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $expiry_timestamp ) ) . '</small>';
			return $html;
		} else {
			// Active temporary account.
			$html = '<span style="color: #0073aa;">' . esc_html__( 'Temporal', 'wp-temporary-user-accounts' ) . '</span>';
			$html .= '<br><small>' . sprintf( esc_html__( 'Expira: %s', 'wp-temporary-user-accounts' ), '<strong>' . wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $expiry_timestamp ) . '</strong>' ) . '</small>';
			$html .= '<br><small>' . sprintf( esc_html__( 'Rol destino: %s', 'wp-temporary-user-accounts' ), '<strong>' . esc_html( $target_role_name ) . '</strong>' ) . '</small>';
			return $html;
		}
	}
	
	/**
	 * Plugin deactivation hook. Clears all scheduled cron jobs.
	 */
	public function deactivate() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	// --- Helper Functions ---

	private function clear_user_expiry_data( int $user_id ) {
		delete_user_meta( $user_id, self::EXPIRY_META_KEY );
		delete_user_meta( $user_id, self::TARGET_ROLE_META_KEY );
		delete_user_meta( $user_id, self::SETTING_DISPLAY_META_KEY );
	}

	private function clear_user_cron( int $user_id ) {
		// To clear a cron, we need the exact arguments it was scheduled with.
		// Since 'target_role' can change, it's safer to get all scheduled events
		// for our hook and find the one for our user_id.
		$crons = get_option( 'cron' );
		if ( empty( $crons ) ) {
			return;
		}

		foreach ( $crons as $timestamp => $cron_hooks ) {
			if ( isset( $cron_hooks[ self::CRON_HOOK ] ) ) {
				foreach ( $cron_hooks[ self::CRON_HOOK ] as $key => $details ) {
					if ( isset( $details['args'][0]['user_id'] ) && $user_id === $details['args'][0]['user_id'] ) {
						wp_unschedule_event( $timestamp, self::CRON_HOOK, $details['args'] );
					}
				}
			}
		}
	}

	private function get_relative_duration_options(): array {
		return [
			'3600'    => __( '1 Hora', 'wp-temporary-user-accounts' ),
			'86400'   => __( '1 Día', 'wp-temporary-user-accounts' ),
			'604800'  => __( '1 Semana', 'wp-temporary-user-accounts' ),
			'2592000' => __( '1 Mes (30 días)', 'wp-temporary-user-accounts' ),
		];
	}

	private function get_target_role_options( array $current_user_roles ): array {
		$editable_roles = get_editable_roles();
		$filtered_roles = [];

		if ( isset( $editable_roles['subscriber'] ) ) {
			$filtered_roles['subscriber'] = $editable_roles['subscriber']['name'];
		}
		
		foreach ( $editable_roles as $role_key => $role_details ) {
			if ( 'administrator' === $role_key ) {
				continue;
			}
			if ( in_array( $role_key, $current_user_roles, true ) ) {
				continue;
			}
			if ( ! isset( $filtered_roles[ $role_key ] ) ) {
				$filtered_roles[ $role_key ] = $role_details['name'];
			}
		}
		uasort( $filtered_roles, 'strcasecmp' );
		return $filtered_roles;
	}
}

/**
 * Initializes the plugin.
 */
function tua_run_plugin() {
	Temporary_User_Accounts::get_instance();
}
tua_run_plugin();
