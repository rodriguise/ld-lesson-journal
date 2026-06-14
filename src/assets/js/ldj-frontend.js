( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		initCharCounters();
		initGroupSaveButtons();
		initEditButtons();
		initDeleteButtons();
		initGroupPagination();
		initJournalViews();
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
			if ( saveBtn ) saveBtn.textContent = 'Submit';

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

		if ( saveBtn ) saveBtn.textContent = 'Submit';
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

	/* === Group Pagination === */

	function initGroupPagination() {
		document.querySelectorAll( '.ldj-group[data-per-page]' ).forEach( function ( group ) {
			initSingleGroupPagination( group );
		} );
	}

	function initSingleGroupPagination( group ) {
		var perPage  = parseInt( group.dataset.perPage, 10 ) || 0;
		if ( perPage <= 0 ) return;

		var prompts  = group.querySelectorAll( '.ldj-prompt-wrap' );
		var prevBtn  = group.querySelector( '.ldj-group-prev' );
		var nextBtn  = group.querySelector( '.ldj-group-next' );
		var pageInfo = group.querySelector( '.ldj-group-page-info' );

		if ( ! prompts.length || ! prevBtn || ! nextBtn ) return;

		var totalPages = Math.ceil( prompts.length / perPage );
		var current    = 0;

		function showPage( page ) {
			current = page;

			prompts.forEach( function ( wrap, i ) {
				var start = current * perPage;
				var end   = start + perPage;
				wrap.style.display = ( i >= start && i < end ) ? '' : 'none';
			} );

			prevBtn.disabled = current === 0;
			nextBtn.disabled = current >= totalPages - 1;
			if ( pageInfo ) {
				pageInfo.textContent = ( current + 1 ) + ' / ' + totalPages;
			}
		}

		prevBtn.addEventListener( 'click', function () {
			if ( current > 0 ) showPage( current - 1 );
		} );

		nextBtn.addEventListener( 'click', function () {
			if ( current < totalPages - 1 ) showPage( current + 1 );
		} );

		showPage( 0 );
	}

	/* === Save / Delete === */

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
					document.dispatchEvent( new CustomEvent( 'ldj:entries-changed' ) );
				} else {
					showFeedback( feedback, data.data?.message || ldjData.i18n.error, 'error' );
				}
			} )
			.catch( function () {
				showFeedback( feedback, ldjData.i18n.error, 'error' );
			} )
			.finally( function () {
				btn.disabled    = false;
				btn.textContent = 'Submit';

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

					document.dispatchEvent( new CustomEvent( 'ldj:entries-changed' ) );
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

	/* === Journal View: refresh, pagination, print, PDF === */

	function initJournalViews() {
		document.querySelectorAll( '.ldj-journal-wrap' ).forEach( function ( wrap ) {
			initSingleJournalView( wrap );
		} );

		document.addEventListener( 'ldj:entries-changed', function () {
			document.querySelectorAll( '.ldj-journal-wrap[data-course-id]' ).forEach( function ( wrap ) {
				refreshSingleJournal( wrap );
			} );
		} );
	}

	function refreshSingleJournal( wrap ) {
		var formData = new FormData();
		formData.append( 'action', 'ldj_refresh_journal' );
		formData.append( 'nonce', ldjData.nonce );
		formData.append( 'course_id', wrap.dataset.courseId || '0' );
		formData.append( 'lesson_id', wrap.dataset.lessonId || '0' );
		formData.append( 'show_title', wrap.dataset.showTitle || '0' );
		formData.append( 'show_student', wrap.dataset.showStudent || '0' );
		formData.append( 'show_print', wrap.dataset.showPrint || '1' );
		formData.append( 'show_save', wrap.dataset.showSave || '1' );
		formData.append( 'show_refresh', wrap.dataset.showRefresh || '1' );
		formData.append( 'heading', wrap.dataset.heading || '' );

		var refreshBtn = wrap.querySelector( '.ldj-journal-refresh-btn' );
		if ( refreshBtn ) {
			refreshBtn.disabled = true;
			var icon = refreshBtn.querySelector( '.dashicons' );
			if ( icon ) icon.classList.add( 'ldj-spin' );
		}

		fetch( ldjData.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData,
		} )
			.then( function ( res ) { return res.json(); } )
			.then( function ( data ) {
				if ( data.success && data.data.html ) {
					var temp = document.createElement( 'div' );
					temp.innerHTML = data.data.html;
					var newWrap = temp.querySelector( '.ldj-journal-wrap' );
					if ( newWrap ) {
						wrap.innerHTML = newWrap.innerHTML;
						for ( var i = 0; i < newWrap.attributes.length; i++ ) {
							var attr = newWrap.attributes[ i ];
							if ( attr.name.startsWith( 'data-' ) ) {
								wrap.setAttribute( attr.name, attr.value );
							}
						}
						initSingleJournalView( wrap );
					}
				}
			} )
			.catch( function () {} )
			.finally( function () {
				if ( refreshBtn ) {
					refreshBtn.disabled = false;
					var ic = refreshBtn.querySelector( '.dashicons' );
					if ( ic ) ic.classList.remove( 'ldj-spin' );
				}
			} );
	}

	function initSingleJournalView( wrap ) {
		initJournalPagination( wrap );
		initJournalButtons( wrap );
	}

	function initJournalPagination( wrap ) {
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

	function initJournalButtons( wrap ) {
		var refreshBtn = wrap.querySelector( '.ldj-journal-refresh-btn' );
		if ( refreshBtn ) {
			refreshBtn.addEventListener( 'click', function () {
				refreshSingleJournal( wrap );
			} );
		}

		var saveBtn = wrap.querySelector( '.ldj-journal-save-btn' );
		if ( saveBtn ) {
			saveBtn.addEventListener( 'click', function () {
				generatePdf( wrap, saveBtn );
			} );
		}

		var printBtn = wrap.querySelector( '.ldj-print-btn' );
		if ( printBtn ) {
			printBtn.addEventListener( 'click', function () {
				window.print();
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
} )();
