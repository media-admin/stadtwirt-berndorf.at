( function () {
	'use strict';

	/**
	 * Apply honeypot refill data to a CF7 form.
	 *
	 * @param {HTMLFormElement} form
	 * @param {Object} honeypotData Keys are CF7 field names.
	 */
	function applyHoneypotRefill( form, honeypotData ) {
		if ( ! form || ! honeypotData || typeof honeypotData !== 'object' ) {
			return;
		}

		Object.keys( honeypotData ).forEach( function ( fieldName ) {
			const token = honeypotData[ fieldName ];

			if ( ! token || ! token.random_hash || ! token.field_name ) {
				return;
			}

			const wrapper =
				form.querySelector( '[data-cf7apps-honeypot="' + fieldName + '"]' ) ||
				form.querySelector( '.wpcf7-form-control-wrap.' + fieldName + '-wrap' );

			if ( ! wrapper ) {
				return;
			}

			const hashInput = form.querySelector(
				'input[name="' + fieldName + '-random-hash"]'
			);

			if ( hashInput ) {
				hashInput.value = token.random_hash;
			}

			const honeypotInput = wrapper.querySelector(
				'input.wpcf7-form-control[type="text"]'
			);

			if ( honeypotInput ) {
				honeypotInput.setAttribute( 'name', token.field_name );
				honeypotInput.value = '';
			}
		} );
	}

	/**
	 * Sync honeypot input values before CF7 builds FormData.
	 *
	 * @param {HTMLFormElement} form
	 */
	function syncHoneypotInputValues( form ) {
		if ( ! form ) {
			return;
		}

		form.querySelectorAll( '[data-cf7apps-honeypot]' ).forEach( function ( wrapper ) {
			const honeypotInput = wrapper.querySelector(
				'input.wpcf7-form-control[type="text"]'
			);

			if ( ! honeypotInput ) {
				return;
			}

			const attributeValue = honeypotInput.getAttribute( 'value' );

			if ( attributeValue && ! honeypotInput.value ) {
				honeypotInput.value = attributeValue;
			}
		} );
	}

	/**
	 * Bind refill listeners to a single CF7 form.
	 *
	 * @param {HTMLFormElement} form
	 */
	function bindHoneypotRefillEvents( form ) {
		if ( ! form || form.dataset.cf7appsHoneypotBound ) {
			return;
		}

		if ( ! form.querySelector( '[data-cf7apps-honeypot]' ) ) {
			return;
		}

		form.dataset.cf7appsHoneypotBound = '1';

		form.addEventListener( 'submit', function () {
			syncHoneypotInputValues( form );
		}, true );

		form.addEventListener( 'wpcf7reset', function ( event ) {
			if ( event.detail && event.detail.apiResponse && event.detail.apiResponse.honeypot ) {
				applyHoneypotRefill( form, event.detail.apiResponse.honeypot );
			}
		} );

		form.addEventListener( 'wpcf7submit', function ( event ) {
			if ( event.detail && event.detail.apiResponse && event.detail.apiResponse.honeypot ) {
				applyHoneypotRefill( form, event.detail.apiResponse.honeypot );
			}
		} );
	}

	/**
	 * Initialize honeypot refill for all CF7 forms on the page.
	 */
	function initHoneypotRefill() {
		if ( typeof wpcf7 === 'undefined' ) {
			return;
		}

		if ( typeof wpcf7.submit === 'function' && ! wpcf7.__cf7appsHoneypotSubmitWrapped ) {
			const originalSubmit = wpcf7.submit;

			wpcf7.submit = function ( form, options ) {
				if ( form instanceof HTMLFormElement ) {
					syncHoneypotInputValues( form );
				}

				return originalSubmit.call( this, form, options );
			};

			wpcf7.__cf7appsHoneypotSubmitWrapped = true;
		}

		const forms = document.querySelectorAll( '.wpcf7 > form' );

		forms.forEach( function ( form ) {
			bindHoneypotRefillEvents( form );

			if ( ! form.querySelector( '[data-cf7apps-honeypot]' ) ) {
				return;
			}

			const forceRefill =
				( typeof wpcf7 !== 'undefined' && wpcf7.cached ) ||
				( typeof cf7appsHoneypotRefill !== 'undefined' &&
					cf7appsHoneypotRefill.forceRefillOnInit );

			if ( forceRefill && typeof wpcf7.reset === 'function' ) {
				wpcf7.reset( form );
			}
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initHoneypotRefill );
	} else {
		initHoneypotRefill();
	}
} )();
