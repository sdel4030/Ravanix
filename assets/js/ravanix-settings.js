/**
 * Ravanix admin — Settings page tabs.
 *
 * Every rich-text field on this page (Informed Consent, the two email
 * message bodies) is rendered as a plain <textarea> in PHP, not through
 * wp_editor() -- TinyMCE does not reliably measure/size itself the first
 * time it initializes inside a container that's display:none, which every
 * tab other than the currently-active one is. Instead, this file calls
 * wp.editor.initialize() itself, exactly once, at the moment a tab
 * containing an editor actually becomes visible (including the default tab
 * on page load) -- by then the container has real dimensions, so TinyMCE
 * sizes correctly every time, regardless of which tab that happens to be.
 *
 * Tab switching itself must never depend on that editor call succeeding --
 * see the try/catch in initEditorsForTab() below. On at least one real site,
 * a host/security-plugin Content Security Policy that blocks eval() broke
 * TinyMCE specifically under an RTL admin locale (its directionality
 * language pack, loaded only there) while leaving everything else on the
 * page working -- without this try/catch, that one third-party failure was
 * enough to disrupt this file's own initialization too.
 */
( function () {
	'use strict';

	// tabId => array of textarea element IDs that need a rich-text editor.
	var EDITORS_BY_TAB = {
		general: [ 'ravanix_consent_text' ],
		notifications: [ 'notify_admin_body', 'notify_participant_body' ]
	};

	var initializedEditors = {};

	function initEditorsForTab( tabId ) {
		var ids = EDITORS_BY_TAB[ tabId ] || [];
		ids.forEach( function ( id ) {
			// Marked as "handled" whether it succeeds or fails below, so a
			// failure (e.g. a CSP blocking TinyMCE, see the comment further
			// down) is only ever attempted -- and only ever logged -- once per
			// field per page load, not every time its tab is revisited.
			if ( initializedEditors[ id ] ) {
				return;
			}
			initializedEditors[ id ] = true;

			var el = document.getElementById( id );
			if ( ! el || typeof wp === 'undefined' || ! wp.editor ) {
				return;
			}
			// TinyMCE's own initialization is out of this file's control, and on
			// some sites can throw -- e.g. a host/security-plugin Content
			// Security Policy that blocks eval() can break TinyMCE's
			// RTL/directionality language pack specifically (loaded only for an
			// RTL admin locale, which is why this could work perfectly on an
			// LTR site and still fail here). Tab switching itself has nothing
			// to do with TinyMCE, so it must keep working regardless -- the
			// plain <textarea> already rendered by PHP is a perfectly usable
			// fallback if the rich editor never appears.
			try {
				wp.editor.initialize( id, {
					tinymce: true,
					quicktags: true,
					mediaButtons: true
				} );
			} catch ( e ) {
				if ( window.console && window.console.error ) {
					window.console.error( 'Ravanix: could not load the rich-text editor for #' + id + ' (falling back to a plain text field):', e );
				}
			}
		} );
	}

	function activateTab( tabId ) {
		var tabs = document.querySelectorAll( '.rs-settings-tab' );
		var panels = document.querySelectorAll( '.rs-settings-panel' );
		var found = false;

		tabs.forEach( function ( tab ) {
			var isMatch = tab.getAttribute( 'data-tab' ) === tabId;
			tab.classList.toggle( 'is-active', isMatch );
			tab.setAttribute( 'aria-selected', isMatch ? 'true' : 'false' );
			if ( isMatch ) {
				found = true;
			}
		} );

		if ( ! found ) {
			return false;
		}

		panels.forEach( function ( panel ) {
			panel.classList.toggle( 'is-active', panel.id === 'rs-settings-panel-' + tabId );
		} );

		// A field that just became visible for the first time needs its editor
		// initialized now, not before -- see this file's own docblock above.
		initEditorsForTab( tabId );

		var activeTabField = document.getElementById( 'rs_settings_active_tab_field' );
		if ( activeTabField ) {
			activeTabField.value = tabId;
		}

		try {
			window.localStorage.setItem( 'ravanixSettingsTab', tabId );
		} catch ( e ) {
			// Private-browsing/storage-disabled: tab memory is a nicety, not a
			// requirement, so a failure here is silently ignored.
		}

		return true;
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var tabs = document.querySelectorAll( '.rs-settings-tab' );
		if ( ! tabs.length ) {
			return;
		}

		// Registered first and kept in their own try/catch, deliberately ahead
		// of anything else in this handler: whatever else in this file might
		// go wrong (restoring the last-open tab, initializing an editor), a
		// click on a tab button should still switch tabs.
		tabs.forEach( function ( tab ) {
			tab.addEventListener( 'click', function () {
				try {
					activateTab( tab.getAttribute( 'data-tab' ) );
				} catch ( e ) {
					if ( window.console && window.console.error ) {
						window.console.error( 'Ravanix: error switching tabs:', e );
					}
				}
			} );
		} );

		try {
			var restored = null;
			try {
				restored = window.localStorage.getItem( 'ravanixSettingsTab' );
			} catch ( e ) {
				restored = null;
			}

			// If the save-and-redirect just happened while a specific tab was
			// open (see the hidden "active_tab" field in the form), prefer that
			// over whatever was previously remembered, so the admin lands back
			// where they were rather than wherever they were before that.
			var params = new URLSearchParams( window.location.search );
			var fromSave = params.get( 'active_tab' );

			if ( fromSave && activateTab( fromSave ) ) {
				// already activated above
			} else if ( restored && activateTab( restored ) ) {
				// already activated above
			} else {
				activateTab( 'general' );
			}
		} catch ( e ) {
			if ( window.console && window.console.error ) {
				window.console.error( 'Ravanix: error restoring the active tab, defaulting to General:', e );
			}
		}

		try {
			// "Copy shortcode" buttons in the Tools tab.
			document.querySelectorAll( '[data-rs-copy]' ).forEach( function ( button ) {
				button.addEventListener( 'click', function () {
					var text = button.getAttribute( 'data-rs-copy' );
					if ( navigator.clipboard && navigator.clipboard.writeText ) {
						navigator.clipboard.writeText( text );
					}
					var original = button.textContent;
					button.textContent = button.getAttribute( 'data-rs-copied-label' ) || original;
					window.setTimeout( function () {
						button.textContent = original;
					}, 1500 );
				} );
			} );
		} catch ( e ) {
			if ( window.console && window.console.error ) {
				window.console.error( 'Ravanix: error setting up "copy shortcode" buttons:', e );
			}
		}
	} );
} )();
