/**
 * Followup admin settings: progressive enhancement only. Without this script the
 * page is fully usable (help text shows as inline fallback spans, toggles and
 * fields work natively). With it we get popover tooltips and live enable/disable
 * styling.
 */
(function () {
	'use strict';

	var root = document.querySelector( '.followup-admin' );
	if ( ! root ) {
		return;
	}

	root.classList.add( 'followup-js' );

	// Popover tooltips on the "?" help buttons (falls back to native popover
	// where supported; otherwise toggles a class).
	var supportsPopover =
		typeof HTMLElement !== 'undefined' &&
		HTMLElement.prototype.hasOwnProperty( 'popover' );

	root.querySelectorAll( '.followup-help' ).forEach( function ( btn ) {
		var id = btn.getAttribute( 'aria-describedby' );
		var tip = id ? document.getElementById( id ) : null;
		if ( ! tip ) {
			return;
		}

		btn.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			if ( supportsPopover && typeof tip.togglePopover === 'function' ) {
				tip.togglePopover();
			} else {
				tip.classList.toggle( 'is-open' );
			}
		} );
	} );

	// Reflect each follow-up's enabled state on its card for the accent border.
	root.querySelectorAll( '.followup-email__toggle' ).forEach( function ( cb ) {
		var card = cb.closest( '.followup-email' );
		if ( ! card ) {
			return;
		}
		cb.addEventListener( 'change', function () {
			card.classList.toggle( 'is-enabled', cb.checked );
		} );
	} );
})();
