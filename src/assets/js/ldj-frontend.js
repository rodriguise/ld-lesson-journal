( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		initCharCounters();
		initGroupSaveButtons();
		initEditButtons();
		initDeleteButtons();
		initGroupPagination();
		initAccordions();
		initJournalViews();
		initJournalEntryActions();
		initSubmitStates();
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

	function initSubmitStates() {
		document.querySelectorAll( '.ldj-group' ).forEach( function ( group ) {
			updateSubmitState( group );
		} );
	}

	function updateSubmitState( group ) {
		var btn = group.querySelector( '.ldj-save-group' );
		if ( ! btn ) return;

		var anyNeedsSubmit = false;

		group.querySelectorAll( '.ldj-prompt-wrap' ).forEach( function ( wrap ) {
			var textareaWrap = wrap.querySelector( '.ldj-textarea-wrap' );
			if ( textareaWrap && textareaWrap.style.display !== 'none' ) {
				anyNeedsSubmit = true;
			}
		} );

		btn.disabled = ! anyNeedsSubmit;
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
			if ( saveBtn ) {
				saveBtn.textContent = 'Submit';
				saveBtn.disabled = false;
			}

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

		updateSubmitState( group );
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

	/* === Accordion === */

	function initAccordions() {
		document.querySelectorAll( '.ldj-group[data-display="accordion"]' ).forEach( function ( group ) {
			initSingleAccordion( group );
		} );
	}

	function initSingleAccordion( group ) {
		var prompts = group.querySelectorAll( '.ldj-prompt-wrap' );
		if ( ! prompts.length ) return;

		group.classList.add( 'ldj-group--accordion' );

		prompts.forEach( function ( wrap, i ) {
			var descEl = wrap.querySelector( '.ldj-prompt-description' );
			var label  = descEl ? descEl.textContent : ( wrap.dataset.promptTitle || '' );
			var hasEntry = !! wrap.querySelector( '.ldj-completed-entry' );

			var item = document.createElement( 'div' );
			item.className = 'ldj-accordion-item';

			var header = document.createElement( 'button' );
			header.type      = 'button';
			header.className = 'ldj-accordion-header';

			var labelSpan = document.createElement( 'span' );
			labelSpan.textContent = label;

			var rightWrap = document.createElement( 'span' );
			rightWrap.style.display = 'flex';
			rightWrap.style.alignItems = 'center';

			var status = document.createElement( 'span' );
			status.className = 'ldj-accordion-status ' + ( hasEntry ? 'ldj-accordion-status--done' : 'ldj-accordion-status--pending' );
			status.textContent = hasEntry ? '✓' : '•';

			var chevron = document.createElement( 'span' );
			chevron.className = 'ldj-accordion-chevron';
			chevron.textContent = '▼';

			rightWrap.appendChild( status );
			rightWrap.appendChild( chevron );
			header.appendChild( labelSpan );
			header.appendChild( rightWrap );

			var body = document.createElement( 'div' );
			body.className = 'ldj-accordion-body';

			wrap.style.display = '';
			item.appendChild( header );
			body.appendChild( wrap );
			item.appendChild( body );

			group.insertBefore( item, group.querySelector( '.ldj-group-actions' ) );

			header.addEventListener( 'click', function () {
				var wasOpen = item.classList.contains( 'ldj-accordion-item--open' );
				group.querySelectorAll( '.ldj-accordion-item--open' ).forEach( function ( openItem ) {
					openItem.classList.remove( 'ldj-accordion-item--open' );
				} );
				if ( ! wasOpen ) {
					item.classList.add( 'ldj-accordion-item--open' );
					requestAnimationFrame( function () {
						requestAnimationFrame( function () {
							var rect = item.getBoundingClientRect();
							if ( rect.top < 0 || rect.top > window.innerHeight * 0.3 ) {
								item.scrollIntoView( { behavior: 'smooth', block: 'start' } );
							}
						} );
					} );
				}
			} );
		} );
	}

	function updateAccordionStatus( group ) {
		group.querySelectorAll( '.ldj-accordion-item' ).forEach( function ( item ) {
			var wrap     = item.querySelector( '.ldj-prompt-wrap' );
			var status   = item.querySelector( '.ldj-accordion-status' );
			if ( ! wrap || ! status ) return;
			var hasEntry = wrap.querySelector( '.ldj-textarea-wrap' )?.style.display === 'none';
			status.className = 'ldj-accordion-status ' + ( hasEntry ? 'ldj-accordion-status--done' : 'ldj-accordion-status--pending' );
			status.textContent = hasEntry ? '✓' : '•';
		} );
	}

	/* === Save / Delete === */

	function saveGroup( group, btn ) {
		var lessonId = group.dataset.lessonId;
		var prompts  = group.querySelectorAll( '.ldj-prompt-wrap' );
		var feedback = group.querySelector( '.ldj-feedback' );
		var entries  = [];
		var errors   = [];

		prompts.forEach( function ( wrap ) {
			var textarea     = wrap.querySelector( '.ldj-textarea' );
			var textareaWrap = wrap.querySelector( '.ldj-textarea-wrap' );
			if ( ! textarea ) return;

			var text     = textarea.value;
			var minChars = textareaWrap ? parseInt( textareaWrap.dataset.minChars, 10 ) || 0 : 0;

			if ( minChars > 0 && text.length < minChars ) {
				errors.push( minChars === 1
					? ldjData.i18n.promptRequired
					: ldjData.i18n.promptMinChars.replace( '%d', minChars )
				);
			}

			entries.push( {
				prompt_id:  wrap.dataset.promptId,
				entry_text: text,
			} );
		} );

		if ( errors.length > 0 ) {
			showFeedback( feedback, errors[0], 'error' );
			return;
		}

		btn.disabled    = true;
		btn.textContent = ldjData.i18n.saving;

		var formData = new FormData();
		formData.append( 'action', 'ldj_save_group' );
		formData.append( 'nonce', ldjData.nonce );
		formData.append( 'lesson_id', lessonId );

		var groupTitle = group.dataset.groupTitle || '';
		if ( groupTitle ) {
			formData.append( 'group_title', groupTitle );
		}

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
				btn.textContent = 'Submit';
				updateSubmitState( group );

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

					updateSubmitState( group );
					updateAccordionStatus( group );
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
		updateSubmitState( group );
		updateAccordionStatus( group );
	}

	var savedTooltipHtml = '';

	function updateMarkComplete( required, allCompleted ) {
		if ( ! required ) return;

		var form = document.querySelector( '.sfwd-mark-complete' );
		if ( ! form ) return;

		var btn = form.querySelector( 'input[type="submit"], button[type="submit"]' );
		if ( ! btn ) return;

		var wrap = form.closest( '.ldj-mark-complete-wrap' );

		if ( allCompleted ) {
			btn.disabled = false;
			btn.classList.remove( 'ldj-disabled' );
			if ( wrap ) {
				var tooltip = wrap.querySelector( '[data-ldj-notice]' );
				if ( tooltip ) {
					if ( ! savedTooltipHtml ) {
						savedTooltipHtml = tooltip.outerHTML;
					}
					tooltip.style.display = 'none';
				}
			}
		} else {
			btn.disabled = true;
			btn.classList.add( 'ldj-disabled' );
			if ( wrap ) {
				var existing = wrap.querySelector( '[data-ldj-notice]' );
				if ( existing ) {
					existing.style.display = '';
				} else if ( savedTooltipHtml ) {
					wrap.insertAdjacentHTML( 'beforeend', savedTooltipHtml );
				}
			}
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

	function initJournalEntryActions() {
		document.querySelectorAll( '.ldj-journal-edit-btn' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var entry = btn.closest( '.ldj-journal-entry' );
				if ( ! entry ) return;
				var answer  = entry.querySelector( '.ldj-journal-answer' );
				var actions = entry.querySelector( '.ldj-journal-entry-actions' );
				var form    = entry.querySelector( '.ldj-journal-edit-form' );
				if ( answer ) answer.style.display = 'none';
				if ( actions ) actions.style.display = 'none';
				if ( form ) form.style.display = '';
			} );
		} );

		document.querySelectorAll( '.ldj-journal-edit-cancel' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var entry = btn.closest( '.ldj-journal-entry' );
				if ( ! entry ) return;
				var answer  = entry.querySelector( '.ldj-journal-answer' );
				var actions = entry.querySelector( '.ldj-journal-entry-actions' );
				var form    = entry.querySelector( '.ldj-journal-edit-form' );
				if ( answer ) answer.style.display = '';
				if ( actions ) actions.style.display = '';
				if ( form ) form.style.display = 'none';
			} );
		} );

		document.querySelectorAll( '.ldj-journal-edit-save' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var entry    = btn.closest( '.ldj-journal-entry' );
				var textarea = entry.querySelector( '.ldj-journal-edit-textarea' );
				if ( ! entry || ! textarea ) return;

				var formData = new FormData();
				formData.append( 'action', 'ldj_update_entry' );
				formData.append( 'nonce', ldjData.nonce );
				formData.append( 'prompt_id', entry.dataset.promptId );
				formData.append( 'lesson_id', entry.dataset.lessonId );
				formData.append( 'entry_text', textarea.value );

				btn.disabled = true;
				btn.textContent = ldjData.i18n.saving;

				fetch( ldjData.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: formData } )
					.then( function ( r ) { return r.json(); } )
					.then( function ( data ) {
						if ( data.success ) {
							var answer = entry.querySelector( '.ldj-journal-answer' );
							if ( answer ) {
								answer.innerHTML = textarea.value
									.replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' ).replace( /\n/g, '<br>' );
							}
							var actions = entry.querySelector( '.ldj-journal-entry-actions' );
							var form    = entry.querySelector( '.ldj-journal-edit-form' );
							if ( answer ) answer.style.display = '';
							if ( actions ) actions.style.display = '';
							if ( form ) form.style.display = 'none';
						}
					} )
					.finally( function () {
						btn.disabled = false;
						btn.textContent = ldjData.i18n.saved ? 'Save' : 'Save';
					} );
			} );
		} );

		document.querySelectorAll( '.ldj-journal-delete-btn' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				if ( ! confirm( ldjData.i18n.confirm ) ) return;

				var entry = btn.closest( '.ldj-journal-entry' );
				if ( ! entry ) return;

				var formData = new FormData();
				formData.append( 'action', 'ldj_delete_entry' );
				formData.append( 'nonce', ldjData.nonce );
				formData.append( 'prompt_id', entry.dataset.promptId );
				formData.append( 'lesson_id', entry.dataset.lessonId );

				fetch( ldjData.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: formData } )
					.then( function ( r ) { return r.json(); } )
					.then( function ( data ) {
						if ( data.success ) {
							entry.remove();
							document.dispatchEvent( new CustomEvent( 'ldj:entries-changed' ) );
						}
					} );
			} );
		} );
	}

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
		formData.append( 'instructions', wrap.dataset.instructions || '' );
		formData.append( 'show_content', wrap.dataset.showContent || '1' );
		formData.append( 'button_style', wrap.dataset.buttonStyle || 'icons' );
		formData.append( 'show_filter', wrap.dataset.showFilter || '0' );

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
		initJournalFilter( wrap );
		initJournalEntryActions();
	}

	function initJournalFilter( wrap ) {
		var filter = wrap.querySelector( '.ldj-lesson-filter' );
		if ( ! filter ) return;

		filter.addEventListener( 'change', function () {
			wrap.dataset.lessonId = filter.value;
			refreshSingleJournal( wrap );
		} );
	}

	function initJournalPagination( wrap ) {
		if ( wrap.dataset.showContent === '0' ) return;

		var sections = wrap.querySelectorAll( '.ldj-journal-section' );
		var prevBtn  = wrap.querySelector( '.ldj-journal-prev' );
		var nextBtn  = wrap.querySelector( '.ldj-journal-next' );
		var pageInfo = wrap.querySelector( '.ldj-journal-page-info' );
		var paginationWrap = wrap.querySelector( '.ldj-journal-pagination' );

		if ( ! prevBtn || ! nextBtn ) return;

		if ( ! sections.length ) {
			prevBtn.disabled = true;
			nextBtn.disabled = true;
			if ( pageInfo ) pageInfo.textContent = '';
			if ( paginationWrap ) paginationWrap.style.display = 'none';
			return;
		}

		var lessonId = wrap.dataset.lessonId || '0';
		var pages = [];

		if ( lessonId === '0' ) {
			var lessonGroups = {};
			var lessonOrder  = [];
			sections.forEach( function ( s ) {
				var parentId = s.dataset.parentLessonId || s.dataset.stepId || '0';
				if ( ! lessonGroups[ parentId ] ) {
					lessonGroups[ parentId ] = [];
					lessonOrder.push( parentId );
				}
				lessonGroups[ parentId ].push( s );
			} );
			lessonOrder.forEach( function ( id ) {
				pages.push( lessonGroups[ id ] );
			} );
		} else {
			var lessonSections = [];
			var topicSections  = [];
			sections.forEach( function ( s ) {
				var stepId   = s.dataset.stepId || '0';
				var parentId = s.dataset.parentLessonId || '0';
				if ( stepId === parentId ) {
					lessonSections.push( s );
				} else {
					topicSections.push( s );
				}
			} );
			lessonSections.concat( topicSections ).forEach( function ( s ) {
				pages.push( [ s ] );
			} );
		}

		if ( pages.length <= 1 ) {
			sections.forEach( function ( s ) {
				s.style.display = '';
				s.querySelectorAll( '.ldj-journal-entry' ).forEach( function ( e ) {
					e.style.display = '';
				} );
			} );
			if ( paginationWrap ) paginationWrap.style.display = 'none';
			return;
		}

		if ( paginationWrap ) paginationWrap.style.display = '';

		var total   = pages.length;
		var current = 0;

		function showPage( index ) {
			current = index;

			sections.forEach( function ( s ) {
				s.style.display = 'none';
			} );

			pages[ current ].forEach( function ( s ) {
				s.style.display = '';
				s.querySelectorAll( '.ldj-journal-entry' ).forEach( function ( e ) {
					e.style.display = '';
				} );
			} );

			prevBtn.disabled = current === 0;
			nextBtn.disabled = current === total - 1;
			pageInfo.textContent = ( current + 1 ) + ' / ' + total;
		}

		prevBtn.addEventListener( 'click', function () {
			if ( current > 0 ) showPage( current - 1 );
		} );

		nextBtn.addEventListener( 'click', function () {
			if ( current < total - 1 ) showPage( current + 1 );
		} );

		showPage( 0 );
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
			var cb = wrap.querySelector( '.ldj-include-private-cb' );
			if ( ! cb || ! cb.checked ) {
				document.body.classList.add( 'ldj-hide-private' );
			}
		} );

		window.addEventListener( 'afterprint', function () {
			document.body.classList.remove( 'ldj-printing' );
			document.body.classList.remove( 'ldj-hide-private' );
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

		clone.querySelectorAll( '.ldj-screen-only' ).forEach( function ( el ) {
			el.remove();
		} );

		var cb = wrap.querySelector( '.ldj-include-private-cb' );
		if ( ! cb || ! cb.checked ) {
			clone.querySelectorAll( '.ldj-journal-entry--private' ).forEach( function ( el ) {
				el.remove();
			} );
		}

		var printHeader = clone.querySelector( '.ldj-journal-print-header' );
		if ( printHeader ) printHeader.style.display = 'flex';

		clone.querySelectorAll( '.ldj-journal-section' ).forEach( function ( s ) {
			s.style.display = '';
		} );
		clone.querySelectorAll( '.ldj-journal-entry' ).forEach( function ( e ) {
			e.style.display = '';
		} );

		clone.style.maxWidth   = 'none';
		clone.style.width      = '100%';
		clone.style.margin     = '0';
		clone.style.padding    = '0';
		clone.style.fontFamily = 'system-ui, -apple-system, sans-serif';
		clone.style.fontSize   = '11pt';

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
