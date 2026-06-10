<?php
/**
 * Admin settings for Jejak Journal.
 *
 * @package JejakJournal
 */

namespace JejakJournal;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const ADMIN_SLUG       = 'jejak-journal';
const ADMIN_CAPABILITY = 'manage_options';

add_action( 'admin_menu', __NAMESPACE__ . '\\admin_register_menu' );
add_action( 'admin_init', __NAMESPACE__ . '\\admin_register_settings' );
add_action( 'admin_post_jejak_export', __NAMESPACE__ . '\\handle_export' );
add_action( 'admin_post_jejak_import', __NAMESPACE__ . '\\handle_import' );

/**
 * Register admin menu.
 */
function admin_register_menu() {
	add_options_page(
		__( 'Jejak Journal', 'jejak-journal' ),
		__( 'Jejak Journal', 'jejak-journal' ),
		ADMIN_CAPABILITY,
		ADMIN_SLUG,
		__NAMESPACE__ . '\\admin_page_settings'
	);
}

/**
 * Register settings.
 */
function admin_register_settings() {
	register_setting(
		'jejak_journal_settings',
		'jejak_journal_roles',
		array(
			'type'              => 'array',
			'default'           => array( 'administrator' ),
			'sanitize_callback' => __NAMESPACE__ . '\\sanitize_roles',
		)
	);

	register_setting(
		'jejak_journal_settings',
		'jejak_journal_features',
		array(
			'type'              => 'array',
			'default'           => array( 'highlights', 'todos', 'journal' ),
			'sanitize_callback' => __NAMESPACE__ . '\\sanitize_features',
		)
	);
	register_setting(
		'jejak_journal_settings',
		'jejak_pwa_enabled',
		array(
			'type'              => 'boolean',
			'default'           => false,
			'sanitize_callback' => 'rest_sanitize_boolean',
		)
	);

	register_setting(
		'jejak_journal_settings',
		'jejak_pwa_app_name',
		array(
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => 'sanitize_text_field',
		)
	);

	register_setting(
		'jejak_journal_settings',
		'jejak_pwa_short_name',
		array(
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => 'sanitize_text_field',
		)
	);

	register_setting(
		'jejak_journal_settings',
		'jejak_pwa_theme_color',
		array(
			'type'              => 'string',
			'default'           => '#1a1a1b',
			'sanitize_callback' => 'sanitize_text_field',
		)
	);

}

/**
 * Sanitize roles setting.
 *
 * @param array|mixed $input Input.
 * @return array
 */
function sanitize_roles( $input ) {
	if ( ! is_array( $input ) ) {
		return array( 'administrator' );
	}
	$all_roles = wp_roles()->get_names();
	$allowed   = array_keys( $all_roles );
	return array_values( array_intersect( $input, $allowed ) );
}

/**
 * Sanitize features setting.
 *
 * @param array|mixed $input Input.
 * @return array
 */
function sanitize_features( $input ) {
	if ( ! is_array( $input ) ) {
		return array( 'highlights', 'todos', 'journal' );
	}
	$allowed = array( 'highlights', 'todos', 'journal' );
	return array_values( array_intersect( $input, $allowed ) );
}

/**
 * Render settings page.
 */
function admin_page_settings() {
	if ( ! current_user_can( ADMIN_CAPABILITY ) ) {
		return;
	}

	// Show import success notice.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$imported = isset( $_GET['jejak_imported'] ) ? absint( $_GET['jejak_imported'] ) : 0;
	if ( $imported > 0 ) {
		echo '<div class="notice notice-success is-dismissible"><p>';
		printf(
			/* translators: %d: number of imported entries */
			esc_html( _n( 'Successfully imported %d entry.', 'Successfully imported %d entries.', $imported, 'jejak-journal' ) ),
			esc_html( (string) $imported )
		);
		echo '</p></div>';
	}

	$saved_roles    = DB::get_allowed_roles();
	$all_roles      = wp_roles()->get_names();
	$saved_features = get_option( 'jejak_journal_features', array( 'highlights', 'todos', 'journal' ) );
	$all_features   = array(
		'highlights' => __( 'Highlights', 'jejak-journal' ),
		'todos'      => __( 'To-Dos', 'jejak-journal' ),
		'journal'    => __( 'Journal', 'jejak-journal' ),
	);
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Jejak Journal Settings', 'jejak-journal' ); ?></h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'jejak_journal_settings' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Allowed Roles', 'jejak-journal' ); ?></th>
					<td>
						<fieldset>
							<legend class="screen-reader-text"><?php esc_html_e( 'Select which roles can manage journals', 'jejak-journal' ); ?></legend>
							<?php foreach ( $all_roles as $role_key => $role_name ) : ?>
								<label style="display:block;margin-bottom:4px;">
									<input
										type="checkbox"
										name="jejak_journal_roles[]"
										value="<?php echo esc_attr( $role_key ); ?>"
										<?php checked( in_array( $role_key, $saved_roles, true ) ); ?>
									/>
									<?php echo esc_html( $role_name ); ?>
								</label>
							<?php endforeach; ?>
						</fieldset>
						<p class="description">
							<?php esc_html_e( 'Administrators always have access. Select additional roles that can create and edit journals.', 'jejak-journal' ); ?>
						</p>
					</td>
				</tr>
								<tr>
					<th scope="row"><?php esc_html_e( 'PWA', 'jejak-journal' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="jejak_pwa_enabled" value="1" <?php checked( get_option( 'jejak_pwa_enabled', false ) ); ?> />
							<?php esc_html_e( 'Enable Progressive Web App support', 'jejak-journal' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="jejak_pwa_app_name"><?php esc_html_e( 'App name', 'jejak-journal' ); ?></label></th>
					<td>
						<input type="text" id="jejak_pwa_app_name" name="jejak_pwa_app_name" value="<?php echo esc_attr( get_option( 'jejak_pwa_app_name', '' ) ); ?>" class="regular-text" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="jejak_pwa_short_name"><?php esc_html_e( 'Short name', 'jejak-journal' ); ?></label></th>
					<td>
						<input type="text" id="jejak_pwa_short_name" name="jejak_pwa_short_name" value="<?php echo esc_attr( get_option( 'jejak_pwa_short_name', '' ) ); ?>" class="regular-text" maxlength="12" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="jejak_pwa_theme_color"><?php esc_html_e( 'Theme color', 'jejak-journal' ); ?></label></th>
					<td>
						<input type="text" id="jejak_pwa_theme_color" name="jejak_pwa_theme_color" value="<?php echo esc_attr( get_option( 'jejak_pwa_theme_color', '#1a1a1b' ) ); ?>" class="medium-text" />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Enabled Features', 'jejak-journal' ); ?></th>
					<td>
						<fieldset>
							<legend class="screen-reader-text"><?php esc_html_e( 'Select which features to enable', 'jejak-journal' ); ?></legend>
							<?php foreach ( $all_features as $feature_key => $feature_name ) : ?>
								<label style="display:block;margin-bottom:4px;">
									<input
										type="checkbox"
										name="jejak_journal_features[]"
										value="<?php echo esc_attr( $feature_key ); ?>"
										<?php checked( in_array( $feature_key, $saved_features, true ) ); ?>
									/>
									<?php echo esc_html( $feature_name ); ?>
								</label>
							<?php endforeach; ?>
						</fieldset>
						<p class="description">
							<?php esc_html_e( 'Enable or disable journal sections. Disabled sections are hidden from the frontend.', 'jejak-journal' ); ?>
						</p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>

		<hr style="margin:32px 0 16px;">

		<h2><?php esc_html_e( 'Export / Import', 'jejak-journal' ); ?></h2>
		<p><?php esc_html_e( 'Export journal entries as JSON, or import from a previously exported file.', 'jejak-journal' ); ?></p>

		<?php
		$users = get_users( array( 'fields' => array( 'ID', 'display_name' ) ) );
		?>

		<!-- Export form -->
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:24px;">
			<?php wp_nonce_field( 'jejak_export', 'jejak_export_nonce' ); ?>
			<input type="hidden" name="action" value="jejak_export">
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Export entries for user', 'jejak-journal' ); ?></th>
					<td>
						<select name="user_id" style="min-width:200px;">
							<?php foreach ( $users as $u ) : ?>
								<option value="<?php echo esc_attr( (string) $u->ID ); ?>">
									<?php echo esc_html( $u->display_name . ' (ID: ' . $u->ID . ')' ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<?php submit_button( __( 'Download JSON', 'jejak-journal' ), 'secondary', 'export_submit', false ); ?>
					</td>
				</tr>
			</table>
		</form>

		<!-- Import form -->
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
			<?php wp_nonce_field( 'jejak_import', 'jejak_import_nonce' ); ?>
			<input type="hidden" name="action" value="jejak_import">
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Import entries for user', 'jejak-journal' ); ?></th>
					<td>
						<select name="user_id" style="min-width:200px;">
							<?php foreach ( $users as $u ) : ?>
								<option value="<?php echo esc_attr( (string) $u->ID ); ?>">
									<?php echo esc_html( $u->display_name . ' (ID: ' . $u->ID . ')' ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'JSON file', 'jejak-journal' ); ?></th>
					<td>
						<input type="file" name="jejak_import_file" accept=".json" required>
						<p class="description"><?php esc_html_e( 'Upload a previously exported .json file. Entries will be created or updated.', 'jejak-journal' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Import JSON', 'jejak-journal' ), 'secondary', 'import_submit', false ); ?>
		</form>
	</div>
	<?php
}

/**
 * Handle JSON export.
 */
function handle_export() {
	if ( ! current_user_can( ADMIN_CAPABILITY ) ) {
		wp_die( esc_html__( 'You do not have permission.', 'jejak-journal' ) );
	}
	check_admin_referer( 'jejak_export', 'jejak_export_nonce' );

	$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
	if ( ! $user_id ) {
		wp_die( esc_html__( 'Invalid user.', 'jejak-journal' ) );
	}

	$entries = DB::get_all_entries( $user_id );
	$export  = array(
		'version'  => 1,
		'exported' => gmdate( 'c' ),
		'user_id'  => $user_id,
		'entries'  => $entries,
	);

	$json     = wp_json_encode( $export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
	$filename = 'jejak-journal-export-user-' . $user_id . '-' . gmdate( 'Y-m-d' ) . '.json';

	header( 'Content-Type: application/json' );
	header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
	header( 'Content-Length: ' . strlen( $json ) );
	echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit;
}

/**
 * Handle JSON import.
 */
function handle_import() {
	if ( ! current_user_can( ADMIN_CAPABILITY ) ) {
		wp_die( esc_html__( 'You do not have permission.', 'jejak-journal' ) );
	}
	check_admin_referer( 'jejak_import', 'jejak_import_nonce' );

	$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
	if ( ! $user_id ) {
		wp_die( esc_html__( 'Invalid user.', 'jejak-journal' ) );
	}

	if ( empty( $_FILES['jejak_import_file'] ) || ! isset( $_FILES['jejak_import_file']['error'] ) || UPLOAD_ERR_OK !== $_FILES['jejak_import_file']['error'] ) {
		wp_die( esc_html__( 'File upload failed.', 'jejak-journal' ) );
	}

	$tmp_name = isset( $_FILES['jejak_import_file']['tmp_name'] ) ? sanitize_text_field( wp_unslash( $_FILES['jejak_import_file']['tmp_name'] ) ) : '';
	if ( ! $tmp_name || ! file_exists( $tmp_name ) ) {
		wp_die( esc_html__( 'File upload failed.', 'jejak-journal' ) );
	}

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	$json = file_get_contents( $tmp_name );
	$data = json_decode( $json, true );

	if ( ! $data || empty( $data['entries'] ) || ! is_array( $data['entries'] ) ) {
		wp_die( esc_html__( 'Invalid JSON file.', 'jejak-journal' ) );
	}

	$imported = 0;
	foreach ( $data['entries'] as $entry ) {
		if ( empty( $entry['year'] ) || empty( $entry['month'] ) ) {
			continue;
		}
		DB::upsert_entry(
			$user_id,
			(int) $entry['year'],
			(int) $entry['month'],
			array(
				'highlights'    => isset( $entry['highlights'] ) ? $entry['highlights'] : array(),
				'todos'         => isset( $entry['todos'] ) ? $entry['todos'] : array(),
				'journal_notes' => isset( $entry['journal_notes'] ) ? $entry['journal_notes'] : array(),
			)
		);
		++$imported;
	}

	wp_safe_redirect(
		add_query_arg(
			array(
				'page'           => ADMIN_SLUG,
				'jejak_imported' => $imported,
			),
			admin_url( 'admin.php' )
		)
	);
	exit;
}
