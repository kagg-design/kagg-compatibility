/* global jQuery, KAGGCompatibilityGeneralObject */

/**
 * @param KAGGCompatibilityGeneralObject.ajaxUrl
 * @param KAGGCompatibilityGeneralObject.resetAction
 * @param KAGGCompatibilityGeneralObject.nonce
 * @param KAGGCompatibilityGeneralObject.resetConfirmation
 */

/**
 * General settings page logic.
 *
 * @param {Object} $ jQuery instance.
 */
const general = function( $ ) {
	$( '[name="kagg-compatibility-reset-button"]' ).on( 'click', function( event ) {
		event.preventDefault();

		// eslint-disable-next-line no-alert
		if ( ! window.confirm( KAGGCompatibilityGeneralObject.resetConfirmation ) ) {
			return;
		}

		const data = {
			action: KAGGCompatibilityGeneralObject.resetAction,
			nonce: KAGGCompatibilityGeneralObject.nonce,
		};

		$.post( {
			url: KAGGCompatibilityGeneralObject.ajaxUrl,
			data,
		} )
			.always( function() {
				location.reload();
			} );
	} );
};

jQuery( document ).ready( general );
