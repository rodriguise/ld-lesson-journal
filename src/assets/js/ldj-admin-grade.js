(function( $ ) {
	'use strict';

	$( document ).on( 'click', '.ldj-collapse-toggle', function () {
		var $toggle = $( this );
		var $body   = $toggle.closest( '.ldj-collapsible' ).find( '.ldj-collapsible-body' );

		$body.slideToggle( 200 );
		$toggle.toggleClass( 'ldj-collapsed' );
	} );

	$( document ).on( 'click', '.ldj-comment-save-btn', function () {
		var $btn   = $( this );
		var $entry = $btn.closest( '.ldj-grade-entry' );
		var $fb    = $entry.find( '.ldj-grade-feedback' );

		var entryId = $entry.data( 'entry-id' );
		var comment = $entry.find( '.ldj-comment-input' ).val();

		$fb.text( ldjGrade.i18n.saving ).removeClass( 'ldj-grade-error ldj-grade-success' );
		$btn.prop( 'disabled', true );

		$.post( ldjGrade.ajaxUrl, {
			action:   'ldj_save_comment',
			nonce:    ldjGrade.nonce,
			entry_id: entryId,
			comment:  comment
		} )
			.done( function ( response ) {
				if ( response.success ) {
					$fb.text( ldjGrade.i18n.commentSaved || 'Comment saved.' ).addClass( 'ldj-grade-success' );
				} else {
					$fb.text( response.data.message || ldjGrade.i18n.error ).addClass( 'ldj-grade-error' );
				}
				$btn.prop( 'disabled', false );
			} )
			.fail( function () {
				$fb.text( ldjGrade.i18n.error ).addClass( 'ldj-grade-error' );
				$btn.prop( 'disabled', false );
			} );
	} );

	$( document ).on( 'click', '.ldj-reopen-btn', function () {
		var $btn   = $( this );
		var $entry = $btn.closest( '.ldj-grade-entry' );
		var $fb    = $entry.find( '.ldj-grade-feedback' );

		var entryId = $entry.data( 'entry-id' );

		$fb.text( ldjGrade.i18n.saving ).removeClass( 'ldj-grade-error ldj-grade-success' );
		$btn.prop( 'disabled', true );

		$.post( ldjGrade.ajaxUrl, {
			action:   'ldj_reopen_entry',
			nonce:    ldjGrade.nonce,
			entry_id: entryId
		} )
			.done( function ( response ) {
				if ( response.success ) {
					$fb.text( response.data.message ).addClass( 'ldj-grade-success' );
					setTimeout( function () { location.reload(); }, 600 );
				} else {
					$fb.text( response.data.message || ldjGrade.i18n.error ).addClass( 'ldj-grade-error' );
					$btn.prop( 'disabled', false );
				}
			} )
			.fail( function () {
				$fb.text( ldjGrade.i18n.error ).addClass( 'ldj-grade-error' );
				$btn.prop( 'disabled', false );
			} );
	} );

	$( document ).on( 'click', '.ldj-grade-btn', function () {
		var $btn   = $( this );
		var $entry = $btn.closest( '.ldj-grade-entry' );
		var $fb    = $entry.find( '.ldj-grade-feedback' );

		var entryId   = $entry.data( 'entry-id' );
		var gradeType = $btn.data( 'grade-type' );

		var data = {
			action:     'ldj_grade_entry',
			nonce:      ldjGrade.nonce,
			entry_id:   entryId,
			grade_type: gradeType
		};

		if ( gradeType === 'score' ) {
			data.grade_score = $entry.find( '.ldj-score-input' ).val();

			if ( data.grade_score === '' ) {
				$fb.text( 'Please enter a score.' ).addClass( 'ldj-grade-error' );
				return;
			}
		}

		$fb.text( ldjGrade.i18n.saving ).removeClass( 'ldj-grade-error ldj-grade-success' );
		$btn.prop( 'disabled', true );

		$.post( ldjGrade.ajaxUrl, data )
			.done( function ( response ) {
				if ( response.success ) {
					var msg = gradeType === 'clear' ? ldjGrade.i18n.cleared : ldjGrade.i18n.saved;
					$fb.text( msg ).addClass( 'ldj-grade-success' );
					setTimeout( function () { location.reload(); }, 600 );
				} else {
					$fb.text( response.data.message || ldjGrade.i18n.error ).addClass( 'ldj-grade-error' );
					$btn.prop( 'disabled', false );
				}
			} )
			.fail( function () {
				$fb.text( ldjGrade.i18n.error ).addClass( 'ldj-grade-error' );
				$btn.prop( 'disabled', false );
			} );
	} );

})( jQuery );
