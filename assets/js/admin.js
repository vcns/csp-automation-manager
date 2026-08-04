/**
 * CSP Automation Manager admin JavaScript.
 * Handles AJAX interactions on the CSP Manager admin pages.
 */
/* global wpCspAdmin, jQuery */
( function ( $ ) {
	'use strict';

	$( '#wp-csp-manual-scan' ).on( 'click', function () {
		const $btn    = $( this );
		const $status = $( '#wp-csp-scan-status' );

		$btn.prop( 'disabled', true );
		$status.text( wpCspAdmin.i18n.scanning ).show();

		$.post( wpCspAdmin.ajaxUrl, {
			action: 'wp_csp_manual_scan',
			nonce:  wpCspAdmin.nonce,
		} )
		.done( function ( res ) {
			if ( res.success ) {
				$status.text( wpCspAdmin.i18n.scanDone );
				setTimeout( function () { location.reload(); }, 1500 );
			} else {
				$status.text( res.data.message || wpCspAdmin.i18n.scanError );
			}
		} )
		.fail( function () {
			$status.text( wpCspAdmin.i18n.scanError );
		} )
		.always( function () {
			$btn.prop( 'disabled', false );
		} );
	} );

	$( '.wp-csp-toggle-mode' ).on( 'click', function () {
		const $btn    = $( this );
		const surface = $btn.data( 'surface' );
		const mode    = $btn.data( 'mode' );

		$btn.prop( 'disabled', true );

		$.post( wpCspAdmin.ajaxUrl, {
			action:  'wp_csp_toggle_mode',
			nonce:   wpCspAdmin.nonce,
			surface: surface,
			mode:    mode,
		} )
		.done( function ( res ) {
			if ( res.success ) {
				location.reload();
			} else {
				// eslint-disable-next-line no-alert
				alert( res.data.message || 'Failed to switch mode.' );
				$btn.prop( 'disabled', false );
			}
		} )
		.fail( function () {
			$btn.prop( 'disabled', false );
		} );
	} );

	$( '.wp-csp-automation-mode' ).on( 'change', function () {
		const $select = $( this );
		const surface = $select.data( 'surface' );
		const mode    = $select.val();
		const previous = $select.data( 'previous' ) || '';

		$select.prop( 'disabled', true );

		$.post( wpCspAdmin.ajaxUrl, {
			action:  'wp_csp_set_automation_mode',
			nonce:   wpCspAdmin.nonce,
			surface: surface,
			mode:    mode,
		} )
		.done( function ( res ) {
			if ( res.success ) {
				$select.data( 'previous', mode );
				return;
			}

			if ( previous !== '' ) {
				$select.val( previous );
			}
			// eslint-disable-next-line no-alert
			alert( res.data.message || 'Failed to switch automation mode.' );
		} )
		.fail( function () {
			if ( previous !== '' ) {
				$select.val( previous );
			}
			// eslint-disable-next-line no-alert
			alert( 'Failed to switch automation mode.' );
		} )
		.always( function () {
			$select.prop( 'disabled', false );
		} );
	} ).each( function () {
		$( this ).data( 'previous', $( this ).val() );
	} );

	function requiredReason( promptText ) {
		const reason = window.prompt( promptText, '' );
		if ( reason === null ) {
			return null;
		}
		if ( reason.trim() === '' ) {
			// eslint-disable-next-line no-alert
			alert( wpCspAdmin.i18n.reasonRequired || 'A decision reason is required.' );
			return null;
		}
		return reason.trim();
	}

	function sourceActionsHtml( id, state, lastDecision ) {
		const buttons = [];
		if ( state === 'pending' || state === 'denied' ) {
			buttons.push( '<button type="button" class="button button-small wp-csp-approve-source" data-id="' + id + '">Approve</button>' );
		}
		if ( state === 'pending' || state === 'approved' ) {
			buttons.push( '<button type="button" class="button button-small wp-csp-deny-source" data-id="' + id + '">Reject</button>' );
		}
		if ( state === 'approved' ) {
			buttons.push( '<button type="button" class="button button-small wp-csp-revert-source" data-id="' + id + '">Revert</button>' );
		}
		if ( lastDecision === 'approved' || lastDecision === 'rejected' ) {
			buttons.push( '<button type="button" class="button button-small wp-csp-undo-source-decision" data-id="' + id + '">Undo</button>' );
		}
		return buttons.join( ' ' );
	}

	function setSourceRowState( $row, id, state, label, lastDecision ) {
		$row.find( '.wp-csp-state-badge' )
			.removeClass( 'state-pending state-approved state-denied' )
			.addClass( 'state-' + state )
			.text( label );
		$row.find( '.wp-csp-source-actions' ).html( sourceActionsHtml( id, state, lastDecision ) );
	}

	function postSourceDecision( $btn, action, promptText, nextState, nextLabel, lastDecision ) {
		const id     = $btn.data( 'id' );
		const reason = requiredReason( promptText );
		if ( reason === null ) {
			return;
		}

		$btn.prop( 'disabled', true );

		$.post( wpCspAdmin.ajaxUrl, {
			action:    action,
			nonce:     wpCspAdmin.nonce,
			source_id: id,
			reason:    reason,
		} )
		.done( function ( res ) {
			if ( res.success ) {
				setSourceRowState( $btn.closest( 'tr' ), id, nextState, nextLabel, lastDecision );
			} else {
				// eslint-disable-next-line no-alert
				alert( res.data.message || 'Could not record policy decision.' );
			}
		} )
		.fail( function () {
			// eslint-disable-next-line no-alert
			alert( 'Could not record policy decision.' );
		} )
		.always( function () { $btn.prop( 'disabled', false ); } );
	}

	$( document ).on( 'click', '.wp-csp-approve-source', function () {
		postSourceDecision( $( this ), 'wp_csp_approve_source', 'Why should this source be approved?', 'approved', 'Approved', 'approved' );
	} );

	$( document ).on( 'click', '.wp-csp-deny-source', function () {
		postSourceDecision( $( this ), 'wp_csp_deny_source', 'Why should this source be rejected and suppressed?', 'denied', 'Denied', 'rejected' );
	} );

	$( document ).on( 'click', '.wp-csp-revert-source', function () {
		postSourceDecision( $( this ), 'wp_csp_revert_source', 'Why should this approved source be reverted and suppressed?', 'denied', 'Denied', 'reverted' );
	} );

	$( document ).on( 'click', '.wp-csp-undo-source-decision', function () {
		postSourceDecision( $( this ), 'wp_csp_undo_source_decision', 'Why should this decision be undone?', 'pending', 'Pending', 'undone' );
	} );

	$( document ).on( 'click', '.wp-csp-use-current-report-endpoint', function () {
		$( '#wp_csp_report_endpoint_url' )
			.val( $( this ).data( 'report-endpoint' ) || '' )
			.trigger( 'change' );
	} );
} )( jQuery );
