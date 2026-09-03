<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

// This file is a template/view loaded only via include() inside methods of the
// plugin's own classes, never standalone; so its local variables never actually
// enter the real global namespace, and there is no risk of collision with
// another plugin/theme. Forcing a prefix on the dozens of local variables in
// this file would only reduce readability.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
// All POST requests in this file go through the $this->check() helper method,
// called at the start of every action, which performs both nonce verification
// (check_admin_referer) and a capability check (current_user_can); because this
// check happens in a shared method rather than directly in this file, static
// analysis tools cannot trace that connection. $_GET values that are only read
// to determine display content (not to perform an action) also do not need a
// nonce, since a nonce verifies *intent to perform an action*, not merely
// viewing a page.
// phpcs:disable WordPress.Security.NonceVerification.Recommended
// phpcs:disable WordPress.Security.NonceVerification.Missing

$settings = Ravanix_Settings::get();
?>
<div class="wrap rs-wrap" dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>">
	<h1><?php esc_html_e( 'Ravanix Settings', 'ravanix' ); ?></h1>

	<?php if ( isset( $_GET['saved'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'ravanix' ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'ravanix_save_settings' ); ?>
		<input type="hidden" name="action" value="ravanix_save_settings">

		<h2><?php esc_html_e( 'Display questionnaires as a custom post type', 'ravanix' ); ?></h2>
		<p class="description"><?php esc_html_e( 'If enabled, every test — in addition to the shortcode (which still works) — will also be automatically viewable as a dedicated custom post type. This helps give it an independent URL, an English slug, and the ability to use tags to display "Related questionnaires".', 'ravanix' ); ?></p>

		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Activation', 'ravanix' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="enable_cpt" value="1" <?php checked( $settings['enable_cpt'], 1 ); ?>>
						<?php esc_html_e( 'Display questionnaires as a custom post type', 'ravanix' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th><label for="cpt_slug"><?php esc_html_e( 'Post type slug', 'ravanix' ); ?></label></th>
				<td>
					<input type="text" id="cpt_slug" name="cpt_slug" class="regular-text" dir="ltr"
						value="<?php echo esc_attr( $settings['cpt_slug'] ); ?>" placeholder="<?php echo esc_attr__( 'questionnaire', 'ravanix' ); ?>">
					<p class="description"><?php esc_html_e( 'Only English letters, numbers, and hyphens. Example: questionnaire', 'ravanix' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="cpt_singular"><?php esc_html_e( 'Singular name', 'ravanix' ); ?></label></th>
				<td><input type="text" id="cpt_singular" name="cpt_singular" class="regular-text" value="<?php echo esc_attr( $settings['cpt_singular'] ); ?>" placeholder="<?php esc_attr_e( 'Questionnaire', 'ravanix' ); ?>"></td>
			</tr>
			<tr>
				<th><label for="cpt_plural"><?php esc_html_e( 'Plural name', 'ravanix' ); ?></label></th>
				<td><input type="text" id="cpt_plural" name="cpt_plural" class="regular-text" value="<?php echo esc_attr( $settings['cpt_plural'] ); ?>" placeholder="<?php esc_attr_e( 'Questionnaires', 'ravanix' ); ?>"></td>
			</tr>
		</table>

		<p class="description">
			<?php esc_html_e( 'Site permalinks are refreshed automatically after saving these settings; there is no need to visit the "Permalinks" page manually.', 'ravanix' ); ?>
		</p>

		<hr>

		<h2><?php esc_html_e( 'Participant info fields', 'ravanix' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'If enabled, each of these fields is collected from the user along with every test form and will be visible in the "Participant Results" panel.', 'ravanix' ); ?>
		</p>
		<table class="wp-list-table widefat striped" style="max-width:600px;">
			<thead>
				<tr>
					<th class="column-primary"><?php esc_html_e( 'Field', 'ravanix' ); ?></th>
					<th><?php esc_html_e( 'Active', 'ravanix' ); ?></th>
					<th><?php esc_html_e( 'Required', 'ravanix' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $settings['participant_fields'] as $key => $field ) : ?>
					<tr>
						<td class="column-primary">
							<?php echo esc_html( $field['label'] ); ?>
							<button type="button" class="toggle-row"><span class="screen-reader-text"><?php esc_html_e( 'Show more details', 'ravanix' ); ?></span></button>
						</td>
						<td data-colname="<?php esc_attr_e( 'Active', 'ravanix' ); ?>"><input type="checkbox" name="participant_fields[<?php echo esc_attr( $key ); ?>][enabled]" value="1" <?php checked( $field['enabled'], 1 ); ?>></td>
						<td data-colname="<?php esc_attr_e( 'Required', 'ravanix' ); ?>"><input type="checkbox" name="participant_fields[<?php echo esc_attr( $key ); ?>][required]" value="1" <?php checked( $field['required'], 1 ); ?>></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<hr>

		<h2><?php esc_html_e( 'Informed consent', 'ravanix' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'This default notice is shown, collapsed, before "Start Test" on every test; the participant must expand and agree to it before starting. A specific test can use its own text instead, or turn this off entirely, from that test\'s own settings. Leave this empty to not show a consent step by default.', 'ravanix' ); ?>
		</p>
		<?php
		wp_editor(
			$settings['consent_text'],
			'ravanix_consent_text',
			array(
				'textarea_name' => 'consent_text',
				'textarea_rows' => 8,
				'media_buttons' => false,
			)
		);
		?>

		<hr>

		<h2><?php esc_html_e( 'Branding', 'ravanix' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Brand color', 'ravanix' ); ?></th>
				<td>
					<input type="text" name="brand_color" value="<?php echo esc_attr( $settings['brand_color'] ); ?>" class="rs-color-field">
					<p class="description"><?php esc_html_e( 'The main accent color used on the front-end test and results pages (buttons, progress bar, selected answers, etc). If a chosen color is too light to keep text readable on it (accessibility), it is automatically darkened just enough on the front end; your saved choice here is never changed.', 'ravanix' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Show branding', 'ravanix' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="show_branding" value="1" <?php checked( $settings['show_branding'], 1 ); ?>>
						<?php esc_html_e( 'Show a small "Powered by Ravanix" link on the results page', 'ravanix' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Off by default. This is entirely optional and has no effect on how the plugin works either way.', 'ravanix' ); ?></p>
				</td>
			</tr>
		</table>

		<hr>

		<h2 style="color:#d9534f;"><?php esc_html_e( 'Danger zone', 'ravanix' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Delete data on uninstall', 'ravanix' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="delete_data_on_uninstall" value="1" <?php checked( $settings['delete_data_on_uninstall'], 1 ); ?>>
						<?php esc_html_e( 'Permanently delete all Ravanix data when this plugin is deleted', 'ravanix' ); ?>
					</label>
					<p class="description" style="max-width:700px;color:#d9534f;">
						<?php esc_html_e( 'This option only takes effect when the plugin is removed via "Delete" on the Plugins screen (not when it is merely deactivated). If enabled at that time, every questionnaire, every participant\'s results, and all plugin settings will be permanently deleted from the database. This cannot be undone. Uploaded images (featured images) in the Media Library are never deleted by this option.', 'ravanix' ); ?>
					</p>
					<p class="description" style="max-width:700px;">
						<?php esc_html_e( 'If this option is left unchecked (the default), deleting the plugin leaves all of its data in the database untouched, so it will be available again if the plugin is reinstalled.', 'ravanix' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Save Settings', 'ravanix' ) ); ?>
	</form>

	<hr>

	<h2><?php esc_html_e( 'Plugin shortcodes', 'ravanix' ); ?></h2>
	<table class="wp-list-table widefat striped" style="max-width:700px;">
		<tbody>
			<tr>
				<td><code>[ravanix_test id="X"]</code></td>
				<td><?php esc_html_e( 'Display a specific test for the user to take', 'ravanix' ); ?></td>
			</tr>
			<tr>
				<td><code>[ravanix_test_list]</code></td>
				<td><?php esc_html_e( 'List of all published tests', 'ravanix' ); ?></td>
			</tr>
			<tr>
				<td><code>[ravanix_my_results]</code></td>
				<td><?php esc_html_e( 'A "My Results" dashboard for the logged-in user; includes a list of completed tests and a timeline chart for each test across repeated attempts (e.g. pre/post treatment)', 'ravanix' ); ?></td>
			</tr>
		</tbody>
	</table>
</div>
