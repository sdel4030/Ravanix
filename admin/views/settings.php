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
// (check_admin_referer) and a capability check (current_user_can, against
// Ravanix_Roles::CAP_MANAGE_SETTINGS -- see Ravanix_Roles for the full list
// and its "never a lockout" guarantee for existing site administrators);
// because this check happens in a shared method rather than directly in this
// file, static analysis tools cannot trace that connection. $_GET values that
// are only read to determine display content (not to perform an action) also
// do not need a nonce, since a nonce verifies *intent to perform an action*,
// not merely viewing a page.
// phpcs:disable WordPress.Security.NonceVerification.Recommended
// phpcs:disable WordPress.Security.NonceVerification.Missing

$settings = Ravanix_Settings::get();

/**
 * Every rich-text field below (Informed Consent, the two email message
 * bodies) is rendered as a plain <textarea>, not through wp_editor(): this
 * page's tabs are pure CSS/JS show-hide, and TinyMCE does not reliably
 * measure/size itself the first time it initializes inside a container
 * that's display:none -- every tab other than whichever one happens to be
 * active. assets/js/ravanix-settings.js calls wp.editor.initialize() itself,
 * exactly when a tab containing an editor actually becomes visible (see that
 * file's own docblock), which sidesteps the problem entirely rather than
 * trying to work around TinyMCE's sizing quirks with CSS. wp_enqueue_editor()
 * (called from Ravanix_Admin::enqueue_assets()) loads the JS this needs.
 */
?>
<div class="wrap rs-wrap rs-settings-wrap" dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>">
	<h1><?php esc_html_e( 'Ravanix Settings', 'ravanix' ); ?></h1>

	<?php if ( isset( $_GET['saved'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'ravanix' ); ?></p></div>
	<?php endif; ?>

	<div class="rs-settings-layout">
	<div>

	<nav class="rs-settings-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Settings sections', 'ravanix' ); ?>">
		<button type="button" class="rs-settings-tab is-active" data-tab="general" role="tab" aria-selected="true">
			<span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>
			<?php esc_html_e( 'General', 'ravanix' ); ?>
		</button>
		<button type="button" class="rs-settings-tab" data-tab="notifications" role="tab" aria-selected="false">
			<span class="dashicons dashicons-email-alt" aria-hidden="true"></span>
			<?php esc_html_e( 'Notifications', 'ravanix' ); ?>
		</button>
		<button type="button" class="rs-settings-tab" data-tab="users" role="tab" aria-selected="false">
			<span class="dashicons dashicons-groups" aria-hidden="true"></span>
			<?php esc_html_e( 'Roles & Permissions', 'ravanix' ); ?>
		</button>
		<button type="button" class="rs-settings-tab" data-tab="tools" role="tab" aria-selected="false">
			<span class="dashicons dashicons-admin-tools" aria-hidden="true"></span>
			<?php esc_html_e( 'Tools', 'ravanix' ); ?>
		</button>
	</nav>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'ravanix_save_settings' ); ?>
		<input type="hidden" name="action" value="ravanix_save_settings">
		<input type="hidden" name="active_tab" id="rs_settings_active_tab_field" value="general">

		<!-- ==================== TAB: GENERAL ==================== -->
		<div class="rs-settings-panel is-active" id="rs-settings-panel-general">

			<div class="rs-settings-card">
				<div class="rs-settings-card-header">
					<div class="rs-settings-card-icon"><span class="dashicons dashicons-admin-post" aria-hidden="true"></span></div>
					<div>
						<div class="rs-settings-card-title"><?php esc_html_e( 'Display questionnaires as a custom post type', 'ravanix' ); ?></div>
					</div>
				</div>
				<div class="rs-settings-card-body">
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
				</div>
			</div>

			<div class="rs-settings-card">
				<div class="rs-settings-card-header">
					<div class="rs-settings-card-icon"><span class="dashicons dashicons-id-alt" aria-hidden="true"></span></div>
					<div>
						<div class="rs-settings-card-title"><?php esc_html_e( 'Participant info fields', 'ravanix' ); ?></div>
					</div>
				</div>
				<div class="rs-settings-card-body">
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
				</div>
			</div>

			<div class="rs-settings-card">
				<div class="rs-settings-card-header">
					<div class="rs-settings-card-icon"><span class="dashicons dashicons-privacy" aria-hidden="true"></span></div>
					<div>
						<div class="rs-settings-card-title"><?php esc_html_e( 'Informed consent', 'ravanix' ); ?></div>
					</div>
				</div>
				<div class="rs-settings-card-body">
					<p class="description">
						<?php esc_html_e( 'This default notice is shown, collapsed, before "Start Test" on every test; the participant must expand and agree to it before starting. A specific test can use its own text instead, or turn this off entirely, from that test\'s own settings. Leave this empty to not show a consent step by default.', 'ravanix' ); ?>
					</p>
					<textarea id="ravanix_consent_text" name="consent_text" rows="8" class="large-text"><?php echo esc_textarea( $settings['consent_text'] ); ?></textarea>
				</div>
			</div>

			<div class="rs-settings-card">
				<div class="rs-settings-card-header">
					<div class="rs-settings-card-icon"><span class="dashicons dashicons-art" aria-hidden="true"></span></div>
					<div>
						<div class="rs-settings-card-title"><?php esc_html_e( 'Branding', 'ravanix' ); ?></div>
					</div>
				</div>
				<div class="rs-settings-card-body">
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
				</div>
			</div>

		</div>

		<!-- ==================== TAB: NOTIFICATIONS ==================== -->
		<div class="rs-settings-panel" id="rs-settings-panel-notifications">

			<div class="rs-settings-card">
				<div class="rs-settings-card-header">
					<div class="rs-settings-card-icon"><span class="dashicons dashicons-email-alt" aria-hidden="true"></span></div>
					<div>
						<div class="rs-settings-card-title"><?php esc_html_e( 'Email notifications', 'ravanix' ); ?></div>
					</div>
				</div>
				<div class="rs-settings-card-body">
					<div class="rs-settings-info">
						<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
						<div>
							<?php esc_html_e( 'Sent right after a participant submits a test. For privacy, neither email ever includes scores or answers (this data is treated as sensitive, and email is not encrypted) -- the admin email only links to the result inside wp-admin, which still requires logging in to view.', 'ravanix' ); ?>
							<br><br>
							<?php esc_html_e( 'Available placeholders: {test_title}, {site_name}, {participant_name}, {submitted_at}, {admin_result_link} (the last three are only replaced in the admin email; the participant email only has {test_title} and {site_name} available, since it deliberately doesn\'t reference the admin-only result page).', 'ravanix' ); ?>
							<br><br>
							<?php esc_html_e( 'Messages are sent as formatted (HTML) email, using whatever bold/links/paragraphs you set below.', 'ravanix' ); ?>
						</div>
					</div>

					<div class="rs-settings-subsection">
						<div class="rs-settings-subsection-header">
							<span class="dashicons dashicons-admin-users" aria-hidden="true"></span>
							<?php esc_html_e( 'To the site admin', 'ravanix' ); ?>
						</div>

						<div class="rs-settings-toggle-row">
							<span class="rs-settings-toggle-row-label"><?php esc_html_e( 'Email the admin when a new response is submitted', 'ravanix' ); ?></span>
							<label class="rs-settings-toggle">
								<input type="checkbox" name="notify_admin_enabled" value="1" <?php checked( $settings['notify_admin_enabled'], 1 ); ?>>
								<span class="rs-settings-toggle-track" aria-hidden="true"></span>
							</label>
						</div>

						<div id="rs-notify-admin-fields">
							<p class="description"><?php esc_html_e( 'The fields below are only used while the toggle above is on.', 'ravanix' ); ?></p>
							<table class="form-table">
								<tr>
									<th><label for="notify_admin_emails"><?php esc_html_e( 'Recipient(s)', 'ravanix' ); ?></label></th>
									<td>
										<input type="text" id="notify_admin_emails" name="notify_admin_emails" class="regular-text" dir="ltr"
											value="<?php echo esc_attr( $settings['notify_admin_emails'] ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
										<p class="description"><?php esc_html_e( 'One or more email addresses, separated by commas. Leave empty to use this site\'s own admin email.', 'ravanix' ); ?></p>
									</td>
								</tr>
								<tr>
									<th><label for="notify_admin_subject"><?php esc_html_e( 'Subject', 'ravanix' ); ?></label></th>
									<td><input type="text" id="notify_admin_subject" name="notify_admin_subject" class="large-text"
										value="<?php echo esc_attr( $settings['notify_admin_subject'] ?: Ravanix_Notifications::default_admin_subject() ); ?>"></td>
								</tr>
								<tr>
									<th><label for="notify_admin_body"><?php esc_html_e( 'Message', 'ravanix' ); ?></label></th>
									<td><textarea id="notify_admin_body" name="notify_admin_body" rows="8" class="large-text"><?php echo esc_textarea( $settings['notify_admin_body'] ?: Ravanix_Notifications::default_admin_body() ); ?></textarea></td>
								</tr>
							</table>
						</div>
					</div>

					<div class="rs-settings-subsection">
						<div class="rs-settings-subsection-header">
							<span class="dashicons dashicons-businessman" aria-hidden="true"></span>
							<?php esc_html_e( 'To the participant', 'ravanix' ); ?>
						</div>
						<p class="description">
							<?php esc_html_e( 'Only sent if the "Email address" participant field (above) is turned on and was actually filled in for that submission.', 'ravanix' ); ?>
						</p>

						<div class="rs-settings-toggle-row">
							<span class="rs-settings-toggle-row-label"><?php esc_html_e( 'Email the participant a confirmation after they submit a test', 'ravanix' ); ?></span>
							<label class="rs-settings-toggle">
								<input type="checkbox" name="notify_participant_enabled" value="1" <?php checked( $settings['notify_participant_enabled'], 1 ); ?>>
								<span class="rs-settings-toggle-track" aria-hidden="true"></span>
							</label>
						</div>

						<div id="rs-notify-participant-fields">
							<p class="description"><?php esc_html_e( 'The fields below are only used while the toggle above is on.', 'ravanix' ); ?></p>
							<table class="form-table">
								<tr>
									<th><label for="notify_participant_subject"><?php esc_html_e( 'Subject', 'ravanix' ); ?></label></th>
									<td><input type="text" id="notify_participant_subject" name="notify_participant_subject" class="large-text"
										value="<?php echo esc_attr( $settings['notify_participant_subject'] ?: Ravanix_Notifications::default_participant_subject() ); ?>"></td>
								</tr>
								<tr>
									<th><label for="notify_participant_body"><?php esc_html_e( 'Message', 'ravanix' ); ?></label></th>
									<td><textarea id="notify_participant_body" name="notify_participant_body" rows="6" class="large-text"><?php echo esc_textarea( $settings['notify_participant_body'] ?: Ravanix_Notifications::default_participant_body() ); ?></textarea></td>
								</tr>
							</table>
						</div>
					</div>
				</div>
			</div>

		</div>

		<!-- ==================== TAB: ROLES & PERMISSIONS ==================== -->
		<div class="rs-settings-panel" id="rs-settings-panel-users">

			<div class="rs-settings-card">
				<div class="rs-settings-card-header">
					<div class="rs-settings-card-icon"><span class="dashicons dashicons-groups" aria-hidden="true"></span></div>
					<div>
						<div class="rs-settings-card-title"><?php esc_html_e( 'Roles & permissions', 'ravanix' ); ?></div>
					</div>
				</div>
				<div class="rs-settings-card-body">
					<p class="description">
						<?php esc_html_e( 'Grant any of these to any other role -- for example, to let a counselor or psychologist manage tests and view results without giving them full site administration. A site administrator always has every capability below, regardless of what is checked here.', 'ravanix' ); ?>
					</p>
					<p class="description">
						<?php
						printf(
							/* translators: %s: the name of the built-in "Ravanix Manager" role */
							esc_html__( 'A ready-made %s role (with "Manage tests" and "View participant results" already granted) is available under Users → Add New, for a quick start.', 'ravanix' ),
							'<strong>' . esc_html__( 'Ravanix Manager', 'ravanix' ) . '</strong>'
						);
						?>
					</p>
					<div class="rs-table-scroll-x">
					<table class="wp-list-table widefat striped rs-role-matrix">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Capability', 'ravanix' ); ?></th>
								<th>
									<?php esc_html_e( 'Administrator', 'ravanix' ); ?>
									<br><span class="description">(<?php esc_html_e( 'always on', 'ravanix' ); ?>)</span>
								</th>
								<?php foreach ( wp_roles()->role_names as $role_slug => $role_label ) : ?>
									<?php if ( 'administrator' === $role_slug ) { continue; } ?>
									<th><?php echo esc_html( translate_user_role( $role_label ) ); ?></th>
								<?php endforeach; ?>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( Ravanix_Roles::assignable_caps() as $cap => $cap_label ) : ?>
								<?php $roles_with_it = Ravanix_Roles::roles_with_cap( $cap ); ?>
								<tr>
									<td><?php echo esc_html( $cap_label ); ?></td>
									<td><input type="checkbox" checked disabled></td>
									<?php foreach ( wp_roles()->role_names as $role_slug => $role_label ) : ?>
										<?php if ( 'administrator' === $role_slug ) { continue; } ?>
										<td>
											<input type="checkbox"
												name="ravanix_role_matrix[<?php echo esc_attr( $cap ); ?>][<?php echo esc_attr( $role_slug ); ?>]"
												value="1" <?php checked( in_array( $role_slug, $roles_with_it, true ) ); ?>>
										</td>
									<?php endforeach; ?>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					</div>
				</div>
			</div>

		</div>

		<!-- ==================== TAB: TOOLS ==================== -->
		<div class="rs-settings-panel" id="rs-settings-panel-tools">

			<div class="rs-settings-card">
				<div class="rs-settings-card-header">
					<div class="rs-settings-card-icon is-danger"><span class="dashicons dashicons-warning" aria-hidden="true"></span></div>
					<div>
						<div class="rs-settings-card-title" style="color:#d63638;"><?php esc_html_e( 'Danger zone', 'ravanix' ); ?></div>
					</div>
				</div>
				<div class="rs-settings-card-body">
					<table class="form-table">
						<tr>
							<th><?php esc_html_e( 'Delete data on uninstall', 'ravanix' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="delete_data_on_uninstall" value="1" <?php checked( $settings['delete_data_on_uninstall'], 1 ); ?>>
									<?php esc_html_e( 'Permanently delete all Ravanix data when this plugin is deleted', 'ravanix' ); ?>
								</label>
								<div class="rs-settings-info is-danger" style="margin-block-start:10px;">
									<span class="dashicons dashicons-warning" aria-hidden="true"></span>
									<div><?php esc_html_e( 'This option only takes effect when the plugin is removed via "Delete" on the Plugins screen (not when it is merely deactivated). If enabled at that time, every questionnaire, every participant\'s results, and all plugin settings will be permanently deleted from the database. This cannot be undone. Uploaded images (featured images) in the Media Library are never deleted by this option.', 'ravanix' ); ?></div>
								</div>
								<p class="description">
									<?php esc_html_e( 'If this option is left unchecked (the default), deleting the plugin leaves all of its data in the database untouched, so it will be available again if the plugin is reinstalled.', 'ravanix' ); ?>
								</p>
							</td>
						</tr>
					</table>
				</div>
			</div>

			<div class="rs-settings-card">
				<div class="rs-settings-card-header">
					<div class="rs-settings-card-icon"><span class="dashicons dashicons-editor-code" aria-hidden="true"></span></div>
					<div>
						<div class="rs-settings-card-title"><?php esc_html_e( 'Plugin shortcodes', 'ravanix' ); ?></div>
					</div>
				</div>
				<div class="rs-settings-card-body">
					<table class="wp-list-table widefat striped">
						<tbody>
							<tr>
								<td style="white-space:nowrap;">
									<code>[ravanix_test id="X"]</code>
									<button type="button" class="button button-small" data-rs-copy="[ravanix_test id=&quot;X&quot;]" data-rs-copied-label="<?php echo esc_attr__( 'Copied!', 'ravanix' ); ?>"><?php esc_html_e( 'Copy', 'ravanix' ); ?></button>
								</td>
								<td><?php esc_html_e( 'Display a specific test for the user to take', 'ravanix' ); ?></td>
							</tr>
							<tr>
								<td style="white-space:nowrap;">
									<code>[ravanix_test_list]</code>
									<button type="button" class="button button-small" data-rs-copy="[ravanix_test_list]" data-rs-copied-label="<?php echo esc_attr__( 'Copied!', 'ravanix' ); ?>"><?php esc_html_e( 'Copy', 'ravanix' ); ?></button>
								</td>
								<td><?php esc_html_e( 'List of all published tests', 'ravanix' ); ?></td>
							</tr>
							<tr>
								<td style="white-space:nowrap;">
									<code>[ravanix_my_results]</code>
									<button type="button" class="button button-small" data-rs-copy="[ravanix_my_results]" data-rs-copied-label="<?php echo esc_attr__( 'Copied!', 'ravanix' ); ?>"><?php esc_html_e( 'Copy', 'ravanix' ); ?></button>
								</td>
								<td><?php esc_html_e( 'A "My Results" dashboard for the logged-in user; includes a list of completed tests and a timeline chart for each test across repeated attempts (e.g. pre/post treatment)', 'ravanix' ); ?></td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>

		</div>

		<?php submit_button( __( 'Save Settings', 'ravanix' ) ); ?>
	</form>

	</div>

	<aside>
		<div class="rs-settings-sidebar-box">
			<div class="rs-settings-sidebar-box-header">
				<span class="dashicons dashicons-editor-help" aria-hidden="true"></span>
				<?php esc_html_e( 'Need help?', 'ravanix' ); ?>
			</div>
			<div class="rs-settings-sidebar-box-body">
				<a class="rs-settings-sidebar-link" href="https://psykey.ir" target="_blank" rel="noopener noreferrer">
					<span class="dashicons dashicons-admin-site-alt3" aria-hidden="true"></span>
					<?php esc_html_e( 'Ravanix homepage', 'ravanix' ); ?>
				</a>
				<a class="rs-settings-sidebar-link" href="https://wordpress.org/support/plugin/ravanix/" target="_blank" rel="noopener noreferrer">
					<span class="dashicons dashicons-sos" aria-hidden="true"></span>
					<?php esc_html_e( 'Support forum', 'ravanix' ); ?>
				</a>
				<a class="rs-settings-sidebar-link" href="https://wordpress.org/support/plugin/ravanix/reviews/#new-post" target="_blank" rel="noopener noreferrer">
					<span class="dashicons dashicons-star-filled" aria-hidden="true"></span>
					<?php esc_html_e( 'Leave a review', 'ravanix' ); ?>
				</a>
			</div>
		</div>
	</aside>

	</div>
</div>
