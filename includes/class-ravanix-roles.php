<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Defines Ravanix's custom capabilities, keeps them granted to Administrator
 * automatically (so a plugin update can never lock an existing site admin out
 * of their own data), provides a ready-made "Ravanix Manager" role for
 * clinicians/counsellors who need access to tests and results but not full
 * site administration, and applies the Settings → Roles & Permissions matrix
 * that lets a site admin grant any of these capabilities to any other role.
 *
 * Six real, independently-grantable capabilities, plus one auto-managed
 * umbrella capability (ravanix_access) that controls only whether the
 * top-level "Ravanix" admin menu is visible at all: WordPress's
 * add_menu_page() takes a single capability, but a user might reasonably
 * have only one of the six (e.g. just ravanix_view_results); ravanix_access
 * is granted to a role automatically whenever save_matrix() gives it any of
 * the six, and removed automatically when it has none of them (see
 * save_matrix() below) -- an admin never sets it directly.
 */
class Ravanix_Roles {

	const CAP_ACCESS               = 'ravanix_access';
	const CAP_MANAGE_TESTS         = 'ravanix_manage_tests';
	const CAP_VIEW_RESULTS         = 'ravanix_view_results';
	const CAP_DELETE_RESULTS       = 'ravanix_delete_results';
	const CAP_EXPORT_RESULTS       = 'ravanix_export_results';
	const CAP_IMPORT_EXPORT_TESTS  = 'ravanix_import_export_tests';
	const CAP_MANAGE_SETTINGS      = 'ravanix_manage_settings';

	const MANAGER_ROLE = 'ravanix_manager';

	public static function init() {
		/**
		 * Guarantees every Ravanix capability check anywhere -- including
		 * WordPress's own internal current_user_can() calls when deciding
		 * whether to show an admin menu/submenu, which this class has no way
		 * to wrap itself -- resolves to true for anyone who already has
		 * manage_options, even if ensure_roles() somehow never ran for this
		 * site (e.g. activation through a path that skips
		 * register_activation_hook). This is what actually makes the "can
		 * never turn into a lockout" guarantee hold everywhere, not just in
		 * this plugin's own admin_post handlers.
		 */
		add_filter( 'user_has_cap', array( __CLASS__, 'grant_via_manage_options' ), 10, 4 );
	}

	public static function grant_via_manage_options( $allcaps, $caps, $args, $user ) {
		if ( empty( $allcaps['manage_options'] ) ) {
			return $allcaps;
		}
		$allcaps[ self::CAP_ACCESS ] = true;
		foreach ( array_keys( self::assignable_caps() ) as $cap ) {
			$allcaps[ $cap ] = true;
		}
		return $allcaps;
	}

	/**
	 * The six capabilities an admin can assign to any role via the Roles &
	 * Permissions matrix, in display order, each with a human-readable label.
	 * Deliberately excludes CAP_ACCESS, which is never shown/set directly.
	 */
	public static function assignable_caps() {
		return array(
			self::CAP_MANAGE_TESTS        => __( 'Manage tests (create/edit/delete questionnaires, dimensions, questions, interpretation ranges, and Ravanix Pro\'s norms/composite factors)', 'ravanix' ),
			self::CAP_VIEW_RESULTS        => __( 'View participant results', 'ravanix' ),
			self::CAP_DELETE_RESULTS      => __( 'Delete participant results', 'ravanix' ),
			self::CAP_EXPORT_RESULTS      => __( 'Export results (Ravanix Pro: CSV / SPSS / PDF, including the clinician report)', 'ravanix' ),
			self::CAP_IMPORT_EXPORT_TESTS => __( 'Import / export test structure as JSON (Ravanix Pro)', 'ravanix' ),
			self::CAP_MANAGE_SETTINGS     => __( 'Manage Ravanix settings, including this permissions table', 'ravanix' ),
		);
	}

	/**
	 * A thin, semantic wrapper over current_user_can() -- since the
	 * 'user_has_cap' filter registered in init() already guarantees a
	 * manage_options user passes any Ravanix capability check, this doesn't
	 * need to (and shouldn't) duplicate that OR logic itself; its purpose is
	 * just to make every Ravanix authorization gate easy to find in one grep.
	 */
	public static function current_user_can( $capability ) {
		return current_user_can( $capability );
	}

	/**
	 * Ensures the administrator role has every Ravanix capability, and that
	 * the ravanix_manager role exists with a sensible starting bundle.
	 * Idempotent -- safe to call on every activation and version upgrade
	 * (Ravanix_Activator::activate() does both), not just the very first
	 * install, so a future Ravanix version that adds a new capability
	 * automatically grants it to Administrator on existing sites too.
	 */
	public static function ensure_roles() {
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->add_cap( self::CAP_ACCESS );
			foreach ( array_keys( self::assignable_caps() ) as $cap ) {
				$admin->add_cap( $cap );
			}
		}

		if ( ! get_role( self::MANAGER_ROLE ) ) {
			add_role( self::MANAGER_ROLE, __( 'Ravanix Manager', 'ravanix' ), array( 'read' => true ) );
		}

		// Applied every time (not just on first creation above), so a site
		// whose ravanix_manager role was already created by an earlier
		// version of this method still gets any capability added here later
		// -- exactly the same "idempotent, reaches existing sites on upgrade"
		// reasoning as the Administrator loop above.
		$manager = get_role( self::MANAGER_ROLE );
		if ( $manager ) {
			$manager->add_cap( 'read' );
			// edit_posts: WordPress core's own long-standing baseline for "this
			// is a staff account, not a mere site visitor/customer" --
			// Contributor has this exact capability and nothing more, for the
			// same reason. Without it, WooCommerce's own admin-access guard
			// (which checks current_user_can( 'edit_posts' ), not anything
			// Ravanix-specific) redirects this role away from wp-admin
			// entirely to the frontend My Account page on any site where
			// WooCommerce is active -- this alone doesn't let the role
			// publish anything or edit another user's content.
			$manager->add_cap( 'edit_posts' );
			$manager->add_cap( self::CAP_ACCESS );
			$manager->add_cap( self::CAP_MANAGE_TESTS );
			$manager->add_cap( self::CAP_VIEW_RESULTS );
		}
	}

	/**
	 * Reads which roles currently hold a given assignable capability, for
	 * pre-checking the matrix's checkboxes. Administrator is intentionally
	 * left out of the returned list -- see render_matrix() in
	 * Ravanix_Admin_Handlers, which always shows it as checked and disabled
	 * rather than reading its real (but irrelevant, since it can't be
	 * unchecked) state here.
	 */
	public static function roles_with_cap( $capability ) {
		$roles = array();
		foreach ( wp_roles()->roles as $slug => $role_data ) {
			if ( 'administrator' === $slug ) {
				continue;
			}
			if ( ! empty( $role_data['capabilities'][ $capability ] ) ) {
				$roles[] = $slug;
			}
		}
		return $roles;
	}

	/**
	 * Applies an admin-submitted capability => [role slugs] mapping (the
	 * Roles & Permissions matrix) to every real WP_Role object, then
	 * recomputes each affected role's ravanix_access based on whether it
	 * ended up with at least one of the six real capabilities.
	 *
	 * Administrator is deliberately skipped entirely here, even if somehow
	 * present in $matrix -- its capabilities are only ever managed by
	 * ensure_roles() above, so this matrix can never be used to reduce an
	 * administrator's own access.
	 *
	 * @param array $matrix [ capability => [ role_slug => '1', ... ] ], i.e.
	 *                       PHP's own shape for a submitted 2D checkbox grid.
	 */
	public static function save_matrix( $matrix ) {
		$all_caps = array_keys( self::assignable_caps() );

		foreach ( wp_roles()->role_names as $role_slug => $label ) {
			if ( 'administrator' === $role_slug ) {
				continue;
			}
			$role = get_role( $role_slug );
			if ( ! $role ) {
				continue;
			}

			$has_any = false;
			foreach ( $all_caps as $cap ) {
				$should_have = ! empty( $matrix[ $cap ][ $role_slug ] );
				if ( $should_have ) {
					$role->add_cap( $cap );
					$has_any = true;
				} else {
					$role->remove_cap( $cap );
				}
			}

			if ( $has_any ) {
				$role->add_cap( self::CAP_ACCESS );
			} else {
				$role->remove_cap( self::CAP_ACCESS );
			}
		}
	}

	/**
	 * Removes every Ravanix capability from every role, and deletes the
	 * ravanix_manager role entirely. Called from uninstall.php.
	 */
	public static function remove_all() {
		$caps = array_merge( array( self::CAP_ACCESS ), array_keys( self::assignable_caps() ) );
		foreach ( wp_roles()->role_names as $role_slug => $label ) {
			$role = get_role( $role_slug );
			if ( ! $role ) {
				continue;
			}
			foreach ( $caps as $cap ) {
				$role->remove_cap( $cap );
			}
		}
		remove_role( self::MANAGER_ROLE );
	}
}
