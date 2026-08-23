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
					<th><?php esc_html_e( 'Field', 'ravanix' ); ?></th>
					<th><?php esc_html_e( 'Active', 'ravanix' ); ?></th>
					<th><?php esc_html_e( 'Required', 'ravanix' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $settings['participant_fields'] as $key => $field ) : ?>
					<tr>
						<td><?php echo esc_html( $field['label'] ); ?></td>
						<td><input type="checkbox" name="participant_fields[<?php echo esc_attr( $key ); ?>][enabled]" value="1" <?php checked( $field['enabled'], 1 ); ?>></td>
						<td><input type="checkbox" name="participant_fields[<?php echo esc_attr( $key ); ?>][required]" value="1" <?php checked( $field['required'], 1 ); ?>></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
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

	<hr>

	<h2><?php esc_html_e( 'Ravanix-related products and posts (psykey.ir)', 'ravanix' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'This section is updated automatically and periodically from psykey.ir, and has no effect on your plugin\'s or site\'s performance.', 'ravanix' ); ?>
	</p>

	<?php
	if ( ! function_exists( 'fetch_feed' ) ) {
		require_once ABSPATH . WPINC . '/feed.php';
	}

	/**
	 * Fetches and renders an RSS feed as a list of clickable links, with error
	 * handling (if the feed is unavailable, instead of an error or a blank page,
	 * just a direct link to the site is shown).
	 */
	$ravanix_render_feed_column = function ( $feed_url, $fallback_url, $count = 5 ) {
		$feed = fetch_feed( $feed_url );

		if ( is_wp_error( $feed ) || ! $feed ) {
			echo '<p class="description">' . esc_html__( 'This list cannot be retrieved at the moment.', 'ravanix' ) . ' <a href="' . esc_url( $fallback_url ) . '" target="_blank" rel="noopener">' . esc_html__( 'View directly on psykey.ir', 'ravanix' ) . '</a></p>';
			return;
		}

		$feed->set_item_limit( $count );
		$items = $feed->get_items( 0, $count );

		if ( empty( $items ) ) {
			echo '<p class="description">' . esc_html__( 'There is nothing to display at the moment.', 'ravanix' ) . '</p>';
			return;
		}

		echo '<ul class="rs-feed-list">';
		foreach ( $items as $item ) {
			echo '<li><a href="' . esc_url( $item->get_permalink() ) . '" target="_blank" rel="noopener">' . esc_html( $item->get_title() ) . '</a></li>';
		}
		echo '</ul>';
	};
	?>

	<div class="rs-feed-columns">
		<div class="rs-feed-column">
			<h3><?php esc_html_e( 'Ready-made questionnaire files (available for purchase)', 'ravanix' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Ready-made questionnaires that can be imported directly into Ravanix, available for purchase from the psykey.ir shop.', 'ravanix' ); ?></p>
			<?php $ravanix_render_feed_column( 'https://psykey.ir/shop-category/ravanix/feed/', 'https://psykey.ir/shop-category/ravanix/', 6 ); ?>
		</div>
		<div class="rs-feed-column">
			<h3><?php esc_html_e( 'Latest posts about Ravanix', 'ravanix' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Tutorials, guides, and news about the Ravanix plugin from the psykey.ir blog.', 'ravanix' ); ?></p>
			<?php $ravanix_render_feed_column( 'https://psykey.ir/category/ravanix/feed/', 'https://psykey.ir/category/ravanix/', 6 ); ?>
		</div>
	</div>
</div>
