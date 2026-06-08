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

/**
 * Register admin menu.
 */
function admin_register_menu() {
	add_menu_page(
		__( 'Jejak Journal', 'jejak-journal' ),
		__( 'Jejak Journal', 'jejak-journal' ),
		ADMIN_CAPABILITY,
		ADMIN_SLUG,
		__NAMESPACE__ . '\\admin_page_settings',
		'dashicons-book-alt',
		35
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
 * Render settings page.
 */
function admin_page_settings() {
	if ( ! current_user_can( ADMIN_CAPABILITY ) ) {
		return;
	}

	$saved_roles = DB::get_allowed_roles();
	$all_roles   = wp_roles()->get_names();
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
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}
