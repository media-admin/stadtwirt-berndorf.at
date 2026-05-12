( function ( $ ) {
	'use strict';

	var cfg = window.cf7AiFormGen;
	if ( ! cfg || ! cfg.ajaxUrl ) {
		return;
	}

	function templateIconImg( src ) {
		return (
			'<img src="' +
			escAttr( src ) +
			'" alt="" class="cf7apps-ai-fg-template__img cf7apps-ai-fg-template__img--sparkle" width="24" height="24" decoding="async" />'
		);
	}

	function templateIconInnerHtml( templateId ) {
		var base = ( cfg.assetsUrl || '' ).replace( /\/?$/, '/' );
		switch ( templateId ) {
			case 'job_application':
				return templateIconImg( base + 'job-application.svg' );
			case 'appointment_booking':
				return (
					'<img src="' +
					escAttr( base + 'application-booking.svg' ) +
					'" alt="" class="cf7apps-ai-fg-template__img" width="24" height="24" decoding="async" />'
				);
			case 'feedback_rating':
				return (
					'<img src="' +
					escAttr( base + 'feedback-rating.svg' ) +
					'" alt="" class="cf7apps-ai-fg-template__img" width="24" height="24" decoding="async" />'
				);
			case 'real_estate_inquiry':
				return (
					'<img src="' +
					escAttr( base + 'contact-inquiry.svg' ) +
					'" alt="" class="cf7apps-ai-fg-template__img" width="24" height="24" decoding="async" />'
				);
			default:
				return templateIconImg( base + 'ai-sparkle.svg' );
		}
	}

	function sparkleClusterSvg( light ) {
		var fill = light ? 'currentColor' : '#2271b1';
		return (
			'<svg class="cf7apps-ai-fg-sparkle-cluster" width="26" height="22" viewBox="0 0 26 22" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">' +
			'<path fill="' +
			fill +
			'" d="M11 .5l1.15 4 3.85 1.45-3.85 1.45L11 11.5l-1.15-4.1L6 7.95l3.85-1.45z"/>' +
			'<path fill="' +
			fill +
			'" d="M21.5 6.5l.65 2.25 2.35.85-2.35.85-.65 2.25-.65-2.25-2.35-.85 2.35-.85z" opacity=".92"/>' +
			'<path fill="' +
			fill +
			'" d="M6.5 13l.55 1.9 2 .65-2 .65L6.5 18l-.55-1.9-2-.65 2-.65z" opacity=".88"/>' +
			'</svg>'
		);
	}

	function escAttr( s ) {
		return String( s )
			.replace( /&/g, '&amp;' )
			.replace( /"/g, '&quot;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' );
	}

	function modalTitleAiSparkleImg() {
		var base = ( cfg.assetsUrl || '' ).replace( /\/?$/, '/' );
		return (
			'<img src="' +
			escAttr( base + 'ai-sparkle.svg' ) +
			'" alt="" class="cf7apps-ai-fg-modal__title-sparkle" width="22" height="26" decoding="async" />'
		);
	}

	function openButtonAiIconImg() {
		var base = ( cfg.assetsUrl || '' ).replace( /\/?$/, '/' );
		return (
			'<img src="' +
			escAttr( base + 'ai-button.svg' ) +
			'" alt="" class="cf7apps-ai-fg-open__icon" width="15" height="18" decoding="async" />'
		);
	}

	function getTemplatePromptById( templateId ) {
		var list = cfg.templates || [];
		for ( var i = 0; i < list.length; i++ ) {
			if ( list[ i ].id === templateId ) {
				return list[ i ].prompt || '';
			}
		}
		return '';
	}

	function getFormTextarea() {
		return document.getElementById( 'wpcf7-form' );
	}

	function insertIntoEditor( text ) {
		var ta = getFormTextarea();
		if ( ! ta ) {
			return;
		}
		ta.value = text;
		var len = text.length;
		if ( typeof ta.setSelectionRange === 'function' ) {
			ta.setSelectionRange( len, len );
		}
		ta.focus();
		$( ta ).trigger( 'input' ).trigger( 'change' );
	}

	function iconCopySvg() {
		return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="cf7apps-ai-fg-btn-icon"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>';
	}

	function iconInsertSvg() {
		return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="cf7apps-ai-fg-btn-icon"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>';
	}

	function iconResetSvg() {
		return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="cf7apps-ai-fg-btn-icon"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path><path d="M3 3v5h5"></path></svg>';
	}

	function buildModal() {
		var sparkHeader = modalTitleAiSparkleImg();
		var sparkBtn = sparkleClusterSvg( true );

		var templatesHtml = ( cfg.templates || [] )
			.map( function ( t ) {
				var iconInner = templateIconInnerHtml( t.id );
				return (
					'<button type="button" class="cf7apps-ai-fg-template" data-template-id="' +
					escAttr( t.id ) +
					'">' +
					'<span class="cf7apps-ai-fg-template__icon" aria-hidden="true">' +
					'<span class="cf7apps-ai-fg-template__icon-box">' +
					iconInner +
					'</span></span>' +
					'<span class="cf7apps-ai-fg-template__content"><span class="cf7apps-ai-fg-template__title">' +
					escAttr( t.title ) +
					'</span><span class="cf7apps-ai-fg-template__desc">' +
					escAttr( t.description ) +
					'</span></span></button>'
				);
			} )
			.join( '' );

		return (
			'<div class="cf7apps-ai-fg-backdrop" id="cf7apps-ai-fg-backdrop" role="dialog" aria-modal="true" aria-labelledby="cf7apps-ai-fg-modal-title">' +
			'<div class="cf7apps-ai-fg-modal">' +
			'<div class="cf7apps-ai-fg-modal__head">' +
			'<h2 class="cf7apps-ai-fg-modal__title" id="cf7apps-ai-fg-modal-title">' +
			sparkHeader +
			'<span class="cf7apps-ai-fg-modal__title-text">' +
			escAttr( cfg.i18n.modalTitle ) +
			'</span></h2>' +
			'<button type="button" class="cf7apps-ai-fg-modal__close" aria-label="' +
			escAttr( cfg.i18n.close ) +
			'">&times;</button></div>' +
			'<div class="cf7apps-ai-fg-modal__body">' +
			'<label class="cf7apps-ai-fg-modal__label" for="cf7apps-ai-fg-prompt">' +
			escAttr( cfg.i18n.promptLabel ) +
			'</label>' +
			'<textarea id="cf7apps-ai-fg-prompt" class="cf7apps-ai-fg-modal__prompt" placeholder="' +
			escAttr( cfg.i18n.promptPlaceholder ) +
			'" rows="3" maxlength="350"></textarea>' +
			'<div class="cf7apps-ai-fg-prompt-meta">' +
			'<span class="cf7apps-ai-fg-prompt-count" id="cf7apps-ai-fg-prompt-count">0/350</span>' +
			'</div>' +
			'<div class="cf7apps-ai-fg-templates" id="cf7apps-ai-fg-templates">' +
			templatesHtml +
			'</div>' +
			'<div class="cf7apps-ai-fg-error" id="cf7apps-ai-fg-error" role="alert"></div>' +
			'<button type="button" class="cf7apps-ai-fg-generate" id="cf7apps-ai-fg-generate" disabled="disabled">' +
			sparkBtn +
			'<span class="cf7apps-ai-fg-generate__spinner" aria-hidden="true"></span>' +
			'<span class="cf7apps-ai-fg-generate__text">' +
			escAttr( cfg.i18n.generate ) +
			'</span></button>' +
			'<div class="cf7apps-ai-fg-output" id="cf7apps-ai-fg-output">' +
			'<div class="cf7apps-ai-fg-output__head">' +
			'<p class="cf7apps-ai-fg-output__title">' +
			escAttr( cfg.i18n.outputTitle ) +
			'</p>' +
			'<div class="cf7apps-ai-fg-output__actions">' +
			'<button type="button" class="cf7apps-ai-fg-action-btn" id="cf7apps-ai-fg-copy">' +
			iconCopySvg() +
			'<span>' + escAttr( cfg.i18n.copy ) + '</span>' +
			'</button>' +
			'<button type="button" class="cf7apps-ai-fg-action-btn" id="cf7apps-ai-fg-insert">' +
			iconInsertSvg() +
			'<span>' + escAttr( cfg.i18n.insert ) + '</span>' +
			'</button>' +
			'<button type="button" class="cf7apps-ai-fg-action-btn" id="cf7apps-ai-fg-refresh">' +
			iconResetSvg() +
			'<span>' + escAttr( cfg.i18n.refresh ) + '</span>' +
			'</button></div></div>' +
			'<pre class="cf7apps-ai-fg-code" id="cf7apps-ai-fg-code"></pre></div></div></div></div>'
		);
	}

	var selectedTemplate = '';
	var lastOutput = '';
	var copyStatusTimer = null;
	var PROMPT_MAX = 350;

	function enforcePromptLimit( $backdrop ) {
		var $ta = $backdrop.find( '#cf7apps-ai-fg-prompt' );
		var raw = String( $ta.val() || '' );
		if ( raw.length > PROMPT_MAX ) {
			raw = raw.substring( 0, PROMPT_MAX );
			$ta.val( raw );
		}
		return raw;
	}

	function updatePromptUi( $backdrop ) {
		var promptVal = enforcePromptLimit( $backdrop );
		var len = promptVal.length;
		$backdrop.find( '#cf7apps-ai-fg-prompt-count' ).text( len + '/' + PROMPT_MAX );
		var canGenerate = $.trim( promptVal ) !== '';
		$backdrop.find( '#cf7apps-ai-fg-generate' ).prop( 'disabled', ! canGenerate );
		if ( canGenerate ) {
			$backdrop.find( '#cf7apps-ai-fg-error' ).removeClass( 'is-visible' ).text( '' );
		}
	}

	function openModal( $backdrop ) {
		$backdrop.addClass( 'is-open' );
		updatePromptUi( $backdrop );
		$backdrop.find( '#cf7apps-ai-fg-prompt' ).trigger( 'focus' );
	}

	function closeModal( $backdrop ) {
		$backdrop.removeClass( 'is-open' );
	}

	function resetFormState( $backdrop ) {
		var $modal = $backdrop.find( '.cf7apps-ai-fg-modal' );
		$backdrop.find( '#cf7apps-ai-fg-prompt' ).val( '' );
		$backdrop.find( '.cf7apps-ai-fg-template' ).removeClass( 'is-selected' );
		selectedTemplate = '';
		$backdrop.find( '#cf7apps-ai-fg-output' ).removeClass( 'is-visible' );
		$backdrop.find( '#cf7apps-ai-fg-code' ).text( '' );
		$backdrop.find( '#cf7apps-ai-fg-error' ).removeClass( 'is-visible' ).text( '' );
		lastOutput = '';
		setGeneratingState( $modal, false );
		updatePromptUi( $backdrop );
	}

	function showError( $backdrop, msg ) {
		$backdrop.find( '#cf7apps-ai-fg-error' ).text( msg ).addClass( 'is-visible' );
	}

	function setGeneratingState( $modal, isBusy ) {
		var $generateBtn = $modal.find( '#cf7apps-ai-fg-generate' );
		var generatingLabel = cfg.i18n.generating || 'Generating...';
		$generateBtn
			.find( '.cf7apps-ai-fg-generate__text' )
			.text( isBusy ? generatingLabel : cfg.i18n.generate );
		$generateBtn.attr( 'aria-busy', isBusy ? 'true' : 'false' );
		$modal.toggleClass( 'cf7apps-ai-fg-busy', !! isBusy );
	}

	function showCopyStatus( $button, message, timeoutMs ) {
		var $label = $button.find( 'span' );
		var original = $button.data( 'originalLabel' );
		if ( ! original ) {
			original = $label.text();
			$button.data( 'originalLabel', original );
		}

		$label.text( message );

		if ( copyStatusTimer ) {
			window.clearTimeout( copyStatusTimer );
		}

		copyStatusTimer = window.setTimeout( function () {
			$label.text( original );
			copyStatusTimer = null;
		}, timeoutMs || 1500 );
	}

	$( function () {
		var $editor = $( '#wpcf7-contact-form-editor' );
		var $headerEnd = $editor.find( '.wp-header-end' ).first();
		if ( ! $editor.length || ! $headerEnd.length ) {
			return;
		}

		var openSpark = openButtonAiIconImg();

		var $wrap = $(
			'<div class="cf7apps-ai-fg-wrap cf7apps-ai-fg-wrap--page-title">' +
				'<button type="button" class="button button-primary cf7apps-ai-fg-open" id="cf7apps-ai-fg-open">' +
				openSpark +
				'<span class="cf7apps-ai-fg-open__label">' +
				escAttr( cfg.i18n.button ) +
				'</span></button></div>'
		);

		$headerEnd.before( $wrap );
		$( 'body' ).append( buildModal() );

		var $backdrop = $( '#cf7apps-ai-fg-backdrop' );
		var $modal = $backdrop.find( '.cf7apps-ai-fg-modal' );
		updatePromptUi( $backdrop );

		$wrap.on( 'click', '#cf7apps-ai-fg-open', function () {
			openModal( $backdrop );
		} );

		$backdrop.on( 'click', '.cf7apps-ai-fg-modal__close', function ( e ) {
			e.preventDefault();
			closeModal( $backdrop );
		} );

		$backdrop.on( 'click', function ( e ) {
			if ( e.target === $backdrop[ 0 ] ) {
				closeModal( $backdrop );
			}
		} );

		$backdrop.on( 'click', '.cf7apps-ai-fg-template', function () {
			var id = String( $( this ).data( 'template-id' ) || '' );
			$backdrop.find( '.cf7apps-ai-fg-template' ).removeClass( 'is-selected' );
			$( this ).addClass( 'is-selected' );
			selectedTemplate = id;
			var sample = getTemplatePromptById( id );
			var $ta = $backdrop.find( '#cf7apps-ai-fg-prompt' );
			if ( sample ) {
				$ta.val( sample ).trigger( 'input' );
			}
			$ta.trigger( 'focus' );
		} );

		$backdrop.on( 'input', '#cf7apps-ai-fg-prompt', function () {
			updatePromptUi( $backdrop );
		} );

		$backdrop.on( 'click', '#cf7apps-ai-fg-generate', function () {
			var promptVal = $.trim( $backdrop.find( '#cf7apps-ai-fg-prompt' ).val() || '' );
			$backdrop.find( '#cf7apps-ai-fg-error' ).removeClass( 'is-visible' ).text( '' );

			if ( ! promptVal ) {
				showError( $backdrop, cfg.i18n.validationError );
				updatePromptUi( $backdrop );
				return;
			}

			setGeneratingState( $modal, true );

			$.ajax( {
				url: cfg.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'cf7_ai_generate_form',
					nonce: cfg.nonce,
					prompt: promptVal,
					template: selectedTemplate,
				},
			} )
				.done( function ( res ) {
					if ( ! res || ! res.success ) {
						var err =
							res && res.data && res.data.message
								? res.data.message
								: 'Request failed.';
						showError( $backdrop, err );
						return;
					}
					var data = res.data || {};
					lastOutput = data.form_tags || '';
					$backdrop.find( '#cf7apps-ai-fg-code' ).text( lastOutput );
					$backdrop.find( '#cf7apps-ai-fg-output' ).addClass( 'is-visible' );
				} )
				.fail( function ( xhr ) {
					var msg = 'Request failed.';
					if ( xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ) {
						msg = xhr.responseJSON.data.message;
					}
					showError( $backdrop, msg );
				} )
				.always( function () {
					setGeneratingState( $modal, false );
				} );
		} );

		$backdrop.on( 'click', '#cf7apps-ai-fg-copy', function () {
			var $copyButton = $( this );
			if ( ! lastOutput ) {
				return;
			}
			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( lastOutput ).then(
					function () {
						showCopyStatus( $copyButton, cfg.i18n.copied, 1500 );
					},
					function () {
						showCopyStatus( $copyButton, cfg.i18n.copyFailed, 2000 );
					}
				);
			} else {
				var ta = document.createElement( 'textarea' );
				ta.value = lastOutput;
				document.body.appendChild( ta );
				ta.select();
				try {
					document.execCommand( 'copy' );
					showCopyStatus( $copyButton, cfg.i18n.copied, 1500 );
				} catch ( e ) {
					showCopyStatus( $copyButton, cfg.i18n.copyFailed, 2000 );
				}
				document.body.removeChild( ta );
			}
		} );

		$backdrop.on( 'click', '#cf7apps-ai-fg-insert', function () {
			if ( ! lastOutput ) {
				return;
			}
			insertIntoEditor( lastOutput );
			closeModal( $backdrop );
		} );

		$backdrop.on( 'click', '#cf7apps-ai-fg-refresh', function () {
			resetFormState( $backdrop );
		} );
	} );
} )( jQuery );
