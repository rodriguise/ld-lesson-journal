(function( $ ) {
	'use strict';

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

		if ( gradeType === 'pass_fail' ) {
			data.grade_status = $btn.data( 'grade-status' );
		} else if ( gradeType === 'score' ) {
			data.grade_score = $entry.find( '.ldj-score-input' ).val();
			data.grade_max   = $entry.find( '.ldj-max-input' ).val();

			if ( ! data.grade_score || ! data.grade_max ) {
				$fb.text( 'Please enter both score and max values.' ).addClass( 'ldj-grade-error' );
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
