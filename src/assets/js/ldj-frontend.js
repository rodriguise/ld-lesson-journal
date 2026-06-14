( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		initCharCounters();
		initGroupSaveButtons();
		initEditButtons();
		initDeleteButtons();
		initJournalPagination();
		initJournalPrint();
	} );

	function initCharCounters() {
		document.querySelectorAll( '.ldj-textarea-wrap' ).forEach( function ( wrap ) {
			var textarea = wrap.querySelector( '.ldj-textarea' );
			var counter  = wrap.querySelector( '.ldj-current-chars' );
			if ( ! textarea || ! counter ) return;

			var minChars = parseInt( wrap.dataset.minChars, 10 ) || 0;

			function update() {
				var len = textarea.value.length;
				counter.textContent = len;

				if ( minChars > 0 && len > 0 && len < minChars ) {
					counter.classList.add( 'ldj-chars-below-min' );
				} else {
					counter.classList.remove( 'ldj-chars-below-min' );
				}
			}

			textarea.addEventListener( 'input', update );
			update();
		} );
	}

	function initGroupSaveButtons() {
		document.querySelectorAll( '.ldj-save-group' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var group = btn.closest( '.ldj-group' );
				if ( ! group ) return;
				saveGroup( group, btn );
			} );
		} );
	}

	function enterEditMode( wrap ) {
		var completed    = wrap.querySelector( '.ldj-completed-entry' );
		var textareaWrap = wrap.querySelector( '.ldj-textarea-wrap' );
		var textarea     = wrap.querySelector( '.ldj-textarea' );
		var group        = wrap.closest( '.ldj-group' );

		if ( completed ) completed.style.display = 'none';
		if ( textareaWrap ) textareaWrap.style.display = '';

		var savedValue = textarea ? textarea.value : '';

		if ( group ) {
			var saveBtn = group.querySelector( '.ldj-save-group' );
			if ( saveBtn ) saveBtn.textContent = 'Save';

			var existing = group.querySelector( '.ldj-cancel-edit' );
			if ( ! existing ) {
				var cancelBtn = document.createElement( 'button' );
				cancelBtn.type      = 'button';
				cancelBtn.className = 'ldj-cancel-edit';
				cancelBtn.textContent = 'Cancel';
				saveBtn.parentNode.insertBefore( cancelBtn, saveBtn.nextSibling );

				cancelBtn.addEventListener( 'click', function () {
					exitEditMode( group, savedValue ? wrap : null, savedValue );
				} );
			}
		}
	}

	function exitEditMode( group, restoreWrap, savedValue ) {
		if ( restoreWrap ) {
			var completed    = restoreWrap.querySelector( '.ldj-completed-entry' );
			var textareaWrap = restoreWrap.querySelector( '.ldj-textarea-wrap' );
			var textarea     = restoreWrap.querySelector( '.ldj-textarea' );

			if ( completed ) completed.style.display = '';
			if ( textareaWrap ) textareaWrap.style.display = 'none';
			if ( textarea && savedValue !== undefined ) textarea.value = savedValue;
		}

		var saveBtn   = group.querySelector( '.ldj-save-group' );
		var cancelBtn = group.querySelector( '.ldj-cancel-edit' );

		if ( saveBtn ) saveBtn.textContent = 'Save';
		if ( cancelBtn ) cancelBtn.remove();
	}

	function initEditButtons() {
		document.querySelectorAll( '.ldj-edit-entry' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var wrap = btn.closest( '.ldj-prompt-wrap' );
				if ( ! wrap ) return;
				enterEditMode( wrap );
			} );
		} );
	}

	function initDeleteButtons() {
		document.querySelectorAll( '.ldj-delete-entry' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				if ( ! confirm( ldjData.i18n.confirm ) ) return;

				var wrap     = btn.closest( '.ldj-prompt-wrap' );
				var group    = btn.closest( '.ldj-group' );
				var promptId = wrap?.dataset.promptId;
				var lessonId = group?.dataset.lessonId;

				if ( ! promptId || ! lessonId ) return;

				deleteEntry( promptId, lessonId, wrap, group );
			} );
		} );
	}

	function saveGroup( group, btn ) {
		var lessonId = group.dataset.lessonId;
		var required = group.dataset.required === '1';
		var prompts  = group.querySelectorAll( '.ldj-prompt-wrap' );
		var feedback = group.querySelector( '.ldj-feedback' );
		var entries  = [];

		prompts.forEach( function ( wrap ) {
			var textarea = wrap.querySelector( '.ldj-textarea' );
			if ( ! textarea ) return;

			entries.push( {
				prompt_id:  wrap.dataset.promptId,
				entry_text: textarea.value,
			} );
		} );

		if ( required ) {
			var empty = entries.some( function ( e ) {
				return ! e.entry_text.trim();
			} );

			if ( empty ) {
				showFeedback( feedback, ldjData.i18n.required, 'error' );
				return;
			}
		}

		btn.disabled    = true;
		btn.textContent = ldjData.i18n.saving;

		var formData = new FormData();
		formData.append( 'action', 'ldj_save_group' );
		formData.append( 'nonce', ldjData.nonce );
		formData.append( 'lesson_id', lessonId );

		entries.forEach( function ( entry, i ) {
			formData.append( 'entries[' + i + '][prompt_id]', entry.prompt_id );
			formData.append( 'entries[' + i + '][entry_text]', entry.entry_text );
		} );

		fetch( ldjData.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData,
		} )
			.then( function ( res ) {
				return res.json();
			} )
			.then( function ( data ) {
				if ( data.success ) {
					showFeedback( feedback, ldjData.i18n.saved, 'success' );
					switchToCompletedDisplay( group );
					updateMarkComplete( required, true );
				} else {
					showFeedback( feedback, data.data?.message || ldjData.i18n.error, 'error' );
				}
			} )
			.catch( function () {
				showFeedback( feedback, ldjData.i18n.error, 'error' );
			} )
			.finally( function () {
				btn.disabled    = false;
				btn.textContent = 'Save';

				var cancelBtn = group.querySelector( '.ldj-cancel-edit' );
				if ( cancelBtn ) cancelBtn.remove();
			} );
	}

	function deleteEntry( promptId, lessonId, wrap, group ) {
		var formData = new FormData();
		formData.append( 'action', 'ldj_delete_entry' );
		formData.append( 'nonce', ldjData.nonce );
		formData.append( 'prompt_id', promptId );
		formData.append( 'lesson_id', lessonId );

		fetch( ldjData.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData,
		} )
			.then( function ( res ) {
				return res.json();
			} )
			.then( function ( data ) {
				if ( data.success ) {
					var completed    = wrap.querySelector( '.ldj-completed-entry' );
					var textareaWrap = wrap.querySelector( '.ldj-textarea-wrap' );
					var textarea     = wrap.querySelector( '.ldj-textarea' );

					if ( completed ) completed.remove();
					if ( textareaWrap ) textareaWrap.style.display = '';
					if ( textarea ) textarea.value = '';

					var counter = wrap.querySelector( '.ldj-current-chars' );
					if ( counter ) counter.textContent = '0';

					var feedback = group.querySelector( '.ldj-feedback' );
					showFeedback( feedback, ldjData.i18n.deleted, 'success' );

					var required = group.dataset.required === '1';
					if ( required ) {
						updateMarkComplete( required, false );
					}
				}
			} )
			.catch( function () {
				var feedback = group.querySelector( '.ldj-feedback' );
				showFeedback( feedback, ldjData.i18n.error, 'error' );
			} );
	}

	function switchToCompletedDisplay( group ) {
		group.querySelectorAll( '.ldj-prompt-wrap' ).forEach( function ( wrap ) {
			var textarea     = wrap.querySelector( '.ldj-textarea' );
			var textareaWrap = wrap.querySelector( '.ldj-textarea-wrap' );
			var existing     = wrap.querySelector( '.ldj-completed-entry' );

			if ( ! textarea || ! textarea.value.trim() ) return;

			if ( existing ) {
				existing.querySelector( '.ldj-entry-display' ).innerHTML =
					textarea.value.replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' ).replace( /\n/g, '<br>' );
				existing.style.display = '';
			} else {
				var div = document.createElement( 'div' );
				div.className = 'ldj-completed-entry';
				div.innerHTML =
					'<div class="ldj-entry-display">' +
					textarea.value.replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' ).replace( /\n/g, '<br>' ) +
					'</div>' +
					'<button type="button" class="ldj-edit-entry">Edit</button>' +
					'<button type="button" class="ldj-delete-entry">Delete</button>';

				wrap.insertBefore( div, textareaWrap );

				div.querySelector( '.ldj-edit-entry' ).addEventListener( 'click', function () {
					enterEditMode( wrap );
				} );

				div.querySelector( '.ldj-delete-entry' ).addEventListener( 'click', function () {
					if ( ! confirm( ldjData.i18n.confirm ) ) return;
					var group = wrap.closest( '.ldj-group' );
					deleteEntry( wrap.dataset.promptId, group.dataset.lessonId, wrap, group );
				} );
			}

			if ( textareaWrap ) textareaWrap.style.display = 'none';
		} );

		exitEditMode( group );
	}

	function updateMarkComplete( required, allCompleted ) {
		if ( ! required ) return;

		var form = document.querySelector( '.sfwd-mark-complete' );
		if ( ! form ) return;

		var btn = form.querySelector( 'input[type="submit"], button[type="submit"]' );
		if ( ! btn ) return;

		if ( allCompleted ) {
			btn.disabled = false;
			btn.classList.remove( 'ldj-disabled' );
			var msg = form.parentElement?.querySelector( '.ldj-completion-message' );
			if ( msg ) msg.remove();
		} else {
			btn.disabled = true;
			btn.classList.add( 'ldj-disabled' );
		}
	}

	function showFeedback( el, message, type ) {
		if ( ! el ) return;

		el.textContent = message;
		el.className   = 'ldj-feedback ldj-feedback--' + type;

		setTimeout( function () {
			el.textContent = '';
			el.className   = 'ldj-feedback';
		}, 4000 );
	}

	function initJournalPagination() {
		var wrap = document.querySelector( '.ldj-journal-wrap' );
		if ( ! wrap ) return;

		var entries  = wrap.querySelectorAll( '.ldj-journal-entry' );
		var sections = wrap.querySelectorAll( '.ldj-journal-section' );
		var prevBtn  = wrap.querySelector( '.ldj-journal-prev' );
		var nextBtn  = wrap.querySelector( '.ldj-journal-next' );
		var pageInfo = wrap.querySelector( '.ldj-journal-page-info' );

		if ( ! entries.length || ! prevBtn || ! nextBtn ) return;

		var total   = entries.length;
		var current = 0;

		function showEntry( index ) {
			current = index;

			entries.forEach( function ( entry ) {
				entry.style.display = 'none';
			} );
			sections.forEach( function ( section ) {
				section.style.display = 'none';
			} );

			var active  = entries[ current ];
			var section = active.closest( '.ldj-journal-section' );

			active.style.display  = '';
			section.style.display = '';

			prevBtn.disabled = current === 0;
			nextBtn.disabled = current === total - 1;
			pageInfo.textContent = ( current + 1 ) + ' / ' + total;
		}

		prevBtn.addEventListener( 'click', function () {
			if ( current > 0 ) showEntry( current - 1 );
		} );

		nextBtn.addEventListener( 'click', function () {
			if ( current < total - 1 ) showEntry( current + 1 );
		} );

		showEntry( 0 );
	}

	function generatePdf( wrap, btn ) {
		if ( typeof html2pdf === 'undefined' ) {
			window.print();
			return;
		}

		btn.disabled = true;
		var icon = btn.querySelector( '.dashicons' );
		if ( icon ) {
			icon.className = 'dashicons dashicons-update ldj-spin';
		}

		var clone = wrap.cloneNode( true );

		clone.querySelector( '.ldj-journal-toolbar' )?.remove();
		clone.querySelector( '.ldj-journal-header' )?.remove();

		var printHeader = clone.querySelector( '.ldj-journal-print-header' );
		if ( printHeader ) printHeader.style.display = 'flex';

		clone.querySelectorAll( '.ldj-journal-section' ).forEach( function ( s ) {
			s.style.display = '';
		} );
		clone.querySelectorAll( '.ldj-journal-entry' ).forEach( function ( e ) {
			e.style.display = '';
		} );

		clone.style.maxWidth  = 'none';
		clone.style.width     = '100%';
		clone.style.margin    = '0';
		clone.style.padding   = '0';
		clone.style.fontFamily = 'system-ui, -apple-system, sans-serif';

		var filename = wrap.dataset.pdfFilename || 'journal';

		html2pdf()
			.set( {
				margin:      [10, 10, 10, 10],
				filename:    filename + '.pdf',
				image:       { type: 'jpeg', quality: 0.95 },
				html2canvas: { scale: 2, useCORS: true },
				jsPDF:       { unit: 'mm', format: 'letter', orientation: 'portrait' },
				pagebreak:   { mode: ['avoid-all', 'css', 'legacy'] },
			} )
			.from( clone )
			.save()
			.then( function () {
				btn.disabled = false;
				if ( icon ) icon.className = 'dashicons dashicons-media-default';
			} )
			.catch( function () {
				btn.disabled = false;
				if ( icon ) icon.className = 'dashicons dashicons-media-default';
				window.print();
			} );
	}

	function initJournalPrint() {
		var wrap = document.querySelector( '.ldj-journal-wrap' );
		if ( ! wrap ) return;

		var printBtn = wrap.querySelector( '.ldj-print-btn' );
		if ( printBtn ) {
			printBtn.addEventListener( 'click', function () {
				window.print();
			} );
		}

		var saveBtn = wrap.querySelector( '.ldj-journal-save-btn' );
		if ( saveBtn ) {
			saveBtn.addEventListener( 'click', function () {
				generatePdf( wrap, saveBtn );
			} );
		}

		var originalParent, originalNext;

		window.addEventListener( 'beforeprint', function () {
			if ( ! wrap.parentNode ) return;
			originalParent = wrap.parentNode;
			originalNext   = wrap.nextSibling;
			document.body.appendChild( wrap );
			document.body.classList.add( 'ldj-printing' );
		} );

		window.addEventListener( 'afterprint', function () {
			document.body.classList.remove( 'ldj-printing' );
			if ( originalParent ) {
				if ( originalNext ) {
					originalParent.insertBefore( wrap, originalNext );
				} else {
					originalParent.appendChild( wrap );
				}
			}
		} );
	}
} )();
