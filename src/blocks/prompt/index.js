import './editor.css';
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { useState, useEffect, useCallback, useRef } from '@wordpress/element';
import {
	ComboboxControl,
	Button,
	TextControl,
	TextareaControl,
	ToggleControl,
	PanelBody,
	Notice,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

function PromptSettings( { prompt, onUpdated } ) {
	const meta = prompt.meta || {};
	const [ rows, setRows ] = useState( meta._ldj_rows || 5 );
	const [ placeholder, setPlaceholder ] = useState( meta._ldj_placeholder || '' );
	const [ required, setRequired ] = useState( !! meta._ldj_required );
	const [ minChars, setMinChars ] = useState( meta._ldj_min_chars || 0 );
	const [ maxChars, setMaxChars ] = useState( meta._ldj_max_chars || 0 );
	const [ saving, setSaving ] = useState( false );
	const [ dirty, setDirty ] = useState( false );
	const [ notice, setNotice ] = useState( '' );
	const mountedMeta = useRef( JSON.stringify( meta ) );
	const dirtyRef = useRef( false );
	const savingRef = useRef( false );

	const isPostSaving = useSelect( ( select ) => {
		return select( 'core/editor' ).isSavingPost() && ! select( 'core/editor' ).isAutosavingPost();
	} );
	const wasSaving = useRef( false );

	useEffect( () => {
		const fresh = JSON.stringify( prompt.meta || {} );
		if ( fresh !== mountedMeta.current ) {
			mountedMeta.current = fresh;
			const m = prompt.meta || {};
			setRows( m._ldj_rows || 5 );
			setPlaceholder( m._ldj_placeholder || '' );
			setRequired( !! m._ldj_required );
			setMinChars( m._ldj_min_chars || 0 );
			setMaxChars( m._ldj_max_chars || 0 );
			setDirty( false );
			dirtyRef.current = false;
		}
	}, [ prompt ] );

	useEffect( () => {
		if ( isPostSaving && ! wasSaving.current && dirtyRef.current && ! savingRef.current ) {
			doSave();
		}
		wasSaving.current = isPostSaving;
	}, [ isPostSaving ] );

	function change( setter, value ) {
		setter( value );
		setDirty( true );
		dirtyRef.current = true;
		setNotice( '' );
	}

	function doSave() {
		setSaving( true );
		savingRef.current = true;
		setNotice( '' );
		const finalMinChars = required ? Math.max( 1, minChars ) : 0;
		apiFetch( {
			path: `/wp/v2/ldj-prompts/${ prompt.id }`,
			method: 'POST',
			data: {
				meta: {
					_ldj_rows: rows,
					_ldj_placeholder: placeholder,
					_ldj_required: required,
					_ldj_min_chars: finalMinChars,
					_ldj_max_chars: maxChars,
				},
			},
		} )
			.then( ( updated ) => {
				setDirty( false );
				dirtyRef.current = false;
				setNotice( __( 'Saved.', 'lesson-journal' ) );
				if ( onUpdated ) onUpdated( updated );
				setTimeout( () => setNotice( '' ), 2000 );
			} )
			.catch( () => setNotice( __( 'Save failed.', 'lesson-journal' ) ) )
			.finally( () => {
				setSaving( false );
				savingRef.current = false;
			} );
	}

	function saveSettings() {
		doSave();
	}

	return (
		<PanelBody title={ __( 'Prompt Settings', 'lesson-journal' ) } initialOpen={ false }>
			<TextControl
				label={ __( 'Number of lines', 'lesson-journal' ) }
				type="number"
				value={ rows }
				onChange={ ( val ) => change( setRows, parseInt( val, 10 ) || 5 ) }
				min={ 1 }
				max={ 10 }
				__nextHasNoMarginBottom
			/>
			<TextControl
				label={ __( 'Placeholder text', 'lesson-journal' ) }
				value={ placeholder }
				onChange={ ( val ) => change( setPlaceholder, val ) }
				__nextHasNoMarginBottom
			/>
			<ToggleControl
				label={ __( 'Required', 'lesson-journal' ) }
				help={ required
					? __( 'Students must write at least the minimum characters.', 'lesson-journal' )
					: __( 'Response is optional.', 'lesson-journal' )
				}
				checked={ required }
				onChange={ ( val ) => {
					change( setRequired, val );
					if ( val && minChars < 1 ) change( setMinChars, 1 );
					if ( ! val ) change( setMinChars, 0 );
				} }
			/>
			{ required && (
				<TextControl
					label={ __( 'Min characters', 'lesson-journal' ) }
					type="number"
					value={ minChars }
					onChange={ ( val ) => change( setMinChars, Math.max( 1, parseInt( val, 10 ) || 1 ) ) }
					min={ 1 }
					__nextHasNoMarginBottom
				/>
			) }
			<TextControl
				label={ __( 'Max characters', 'lesson-journal' ) }
				help={ __( '0 = unlimited', 'lesson-journal' ) }
				type="number"
				value={ maxChars }
				onChange={ ( val ) => change( setMaxChars, parseInt( val, 10 ) || 0 ) }
				min={ 0 }
				__nextHasNoMarginBottom
			/>
			{ dirty && (
				<Button
					variant="primary"
					onClick={ saveSettings }
					disabled={ saving }
					isBusy={ saving }
					style={ { marginTop: '8px' } }
				>
					{ saving ? __( 'Saving…', 'lesson-journal' ) : __( 'Save Settings', 'lesson-journal' ) }
				</Button>
			) }
			{ notice && <p style={ { marginTop: '8px', color: notice === __( 'Saved.', 'lesson-journal' ) ? '#059669' : '#dc2626' } }>{ notice }</p> }
		</PanelBody>
	);
}

function PromptForm( { title, content, rows, placeholder, required, minChars, maxChars, onChange, onSave, saving, saveLabel, error, onError } ) {
	return (
		<>
			{ error && (
				<Notice status="error" isDismissible onDismiss={ () => onError( '' ) }>
					{ error }
				</Notice>
			) }

			<TextControl
				label={ __( 'Title', 'lesson-journal' ) }
				help={ __( 'Internal name for organizing prompts — not shown to students.', 'lesson-journal' ) }
				value={ title }
				onChange={ ( val ) => onChange( { title: val } ) }
				__nextHasNoMarginBottom
			/>

			<TextareaControl
				label={ __( 'Prompt Text', 'lesson-journal' ) }
				help={ __( 'Plain text for quick creation. Use the Full Editor for headings, font sizes, and rich formatting.', 'lesson-journal' ) }
				value={ content }
				onChange={ ( val ) => onChange( { content: val } ) }
				rows={ 4 }
				__nextHasNoMarginBottom
			/>

			<TextControl
				label={ __( 'Number of lines', 'lesson-journal' ) }
				help={ __( 'Sets the height of the text area students write in (rows). More lines = taller input.', 'lesson-journal' ) }
				type="number"
				value={ rows }
				onChange={ ( val ) => onChange( { rows: parseInt( val, 10 ) || 5 } ) }
				min={ 1 }
				max={ 10 }
				__nextHasNoMarginBottom
			/>

			<TextControl
				label={ __( 'Placeholder text', 'lesson-journal' ) }
				help={ __( 'Faint text inside the empty text area — disappears when the student starts typing.', 'lesson-journal' ) }
				value={ placeholder }
				onChange={ ( val ) => onChange( { placeholder: val } ) }
				__nextHasNoMarginBottom
			/>

			<ToggleControl
				label={ __( 'Required', 'lesson-journal' ) }
				help={ required
					? __( 'Students must write at least the minimum characters.', 'lesson-journal' )
					: __( 'Response is optional.', 'lesson-journal' )
				}
				checked={ required }
				onChange={ ( val ) => {
					onChange( { required: val } );
					if ( val && minChars < 1 ) {
						onChange( { minChars: 1 } );
					}
					if ( ! val ) {
						onChange( { minChars: 0 } );
					}
				} }
			/>

			{ required && (
				<TextControl
					label={ __( 'Min characters', 'lesson-journal' ) }
					type="number"
					value={ minChars }
					onChange={ ( val ) => onChange( { minChars: Math.max( 1, parseInt( val, 10 ) || 1 ) } ) }
					min={ 1 }
					__nextHasNoMarginBottom
				/>
			) }

			<TextControl
				label={ __( 'Max characters', 'lesson-journal' ) }
				help={ __( 'Limits how much the student can write. Set to 0 for no limit.', 'lesson-journal' ) }
				type="number"
				value={ maxChars }
				onChange={ ( val ) => onChange( { maxChars: parseInt( val, 10 ) || 0 } ) }
				min={ 0 }
				__nextHasNoMarginBottom
			/>

			<div className="ldj-create-actions">
				<Button
					variant="primary"
					onClick={ onSave }
					disabled={ saving || ! title.trim() || ! content.trim() }
					isBusy={ saving }
				>
					{ saving ? __( 'Saving…', 'lesson-journal' ) : saveLabel }
				</Button>
			</div>
		</>
	);
}

registerBlockType( 'ldj/prompt', {
	edit( { attributes, setAttributes } ) {
		const blockProps = useBlockProps( { className: 'ldj-prompt-wrap ldj-prompt--editor' } );
		const { promptId } = attributes;

		const [ mode, setMode ] = useState( promptId ? 'selected' : 'pick' );
		const [ prompts, setPrompts ] = useState( [] );
		const [ selectedPrompt, setSelectedPrompt ] = useState( null );
		const [ error, setError ] = useState( '' );
		const [ saving, setSaving ] = useState( false );

		const [ formTitle, setFormTitle ] = useState( '' );
		const [ formContent, setFormContent ] = useState( '' );
		const [ formRows, setFormRows ] = useState( 5 );
		const [ formPlaceholder, setFormPlaceholder ] = useState( '' );
		const [ formRequired, setFormRequired ] = useState( false );
		const [ formMinChars, setFormMinChars ] = useState( 0 );
		const [ formMaxChars, setFormMaxChars ] = useState( 0 );

		useEffect( () => {
			apiFetch( { path: '/wp/v2/ldj-prompts?per_page=100&status=publish' } )
				.then( ( data ) => {
					setPrompts( data.map( ( p ) => ( {
						value: p.id,
						label: p.title.rendered || `#${ p.id }`,
						post: p,
					} ) ) );
				} )
				.catch( () => {} );
		}, [] );

		useEffect( () => {
			if ( promptId && ! selectedPrompt ) {
				apiFetch( { path: `/wp/v2/ldj-prompts/${ promptId }` } )
					.then( ( p ) => setSelectedPrompt( p ) )
					.catch( () => setSelectedPrompt( null ) );
			}
		}, [ promptId ] );

		const selectPrompt = useCallback( ( id ) => {
			const numId = Number( id );
			setAttributes( { promptId: numId } );
			const found = prompts.find( ( p ) => p.value === numId );
			if ( found ) {
				setSelectedPrompt( found.post );
			}
			setMode( 'selected' );
			setError( '' );
		}, [ prompts, setAttributes ] );

		function resetForm() {
			setFormTitle( '' );
			setFormContent( '' );
			setFormRows( 5 );
			setFormPlaceholder( '' );
			setFormRequired( false );
			setFormMinChars( 0 );
			setFormMaxChars( 0 );
		}

		function handleFormChange( updates ) {
			if ( 'title' in updates ) setFormTitle( updates.title );
			if ( 'content' in updates ) setFormContent( updates.content );
			if ( 'rows' in updates ) setFormRows( updates.rows );
			if ( 'placeholder' in updates ) setFormPlaceholder( updates.placeholder );
			if ( 'required' in updates ) setFormRequired( updates.required );
			if ( 'minChars' in updates ) setFormMinChars( updates.minChars );
			if ( 'maxChars' in updates ) setFormMaxChars( updates.maxChars );
		}

		function createPrompt() {
			if ( ! formTitle.trim() || ! formContent.trim() ) return;

			setSaving( true );
			setError( '' );

			apiFetch( {
				path: '/wp/v2/ldj-prompts',
				method: 'POST',
				data: {
					title: formTitle,
					content: formContent,
					status: 'publish',
					meta: {
						_ldj_rows: formRows,
						_ldj_placeholder: formPlaceholder,
						_ldj_required: formRequired,
						_ldj_min_chars: formRequired ? Math.max( 1, formMinChars ) : 0,
						_ldj_max_chars: formMaxChars,
					},
				},
			} )
				.then( ( p ) => {
					setAttributes( { promptId: p.id } );
					setSelectedPrompt( p );
					setMode( 'selected' );
					setPrompts( ( prev ) => [
						...prev,
						{ value: p.id, label: p.title.rendered, post: p },
					] );
					resetForm();
				} )
				.catch( ( err ) => {
					const msg = err?.message || err?.data?.message || __( 'Failed to create prompt.', 'lesson-journal' );
					setError( msg );
				} )
				.finally( () => setSaving( false ) );
		}

		function openFullEditor() {
			if ( ! promptId ) return;
			const adminUrl = window.ldjAdmin?.adminUrl || '/wp-admin/';
			window.open( adminUrl + 'post.php?post=' + promptId + '&action=edit', '_blank' );
		}

		function refreshPrompt() {
			if ( ! promptId ) return;
			apiFetch( { path: `/wp/v2/ldj-prompts/${ promptId }` } )
				.then( ( p ) => {
					setSelectedPrompt( p );
					setPrompts( ( prev ) =>
						prev.map( ( item ) =>
							item.value === p.id
								? { value: p.id, label: p.title.rendered, post: p }
								: item
						)
					);
				} )
				.catch( () => {} );
		}

		if ( mode === 'selected' && selectedPrompt ) {
			return (
				<>
					<InspectorControls>
						<PanelBody title={ __( 'Prompt', 'lesson-journal' ) }>
							<p><strong>{ selectedPrompt.title.rendered }</strong></p>
							<Button
								variant="link"
								onClick={ () => {
									setMode( 'pick' );
									setSelectedPrompt( null );
									setAttributes( { promptId: 0 } );
								} }
							>
								{ __( 'Change Prompt', 'lesson-journal' ) }
							</Button>
						</PanelBody>
						<PromptSettings
							prompt={ selectedPrompt }
							onUpdated={ ( updated ) => {
								setSelectedPrompt( updated );
								setPrompts( ( prev ) =>
									prev.map( ( item ) =>
										item.value === updated.id
											? { value: updated.id, label: updated.title.rendered, post: updated }
											: item
									)
								);
							} }
						/>
					</InspectorControls>
					<div { ...blockProps }>
						<div
							className="ldj-prompt-text"
							dangerouslySetInnerHTML={ { __html: selectedPrompt.content.rendered } }
						/>
						<div className="ldj-prompt-actions">
							<Button
								variant="secondary"
								size="small"
								onClick={ openFullEditor }
							>
								{ __( 'Edit Prompt', 'lesson-journal' ) }
							</Button>
							<Button
								variant="tertiary"
								size="small"
								onClick={ refreshPrompt }
							>
								{ __( 'Refresh', 'lesson-journal' ) }
							</Button>
							<Button
								variant="tertiary"
								size="small"
								isDestructive
								onClick={ () => {
									setMode( 'pick' );
									setSelectedPrompt( null );
									setAttributes( { promptId: 0 } );
								} }
							>
								{ __( 'Remove', 'lesson-journal' ) }
							</Button>
						</div>
					</div>
				</>
			);
		}

		return (
			<div { ...blockProps }>
				<div className="ldj-prompt-picker">
					<h4>{ __( 'Journal Prompt', 'lesson-journal' ) }</h4>

					{ error && (
						<Notice status="error" isDismissible onDismiss={ () => setError( '' ) }>
							{ error }
						</Notice>
					) }

					<ComboboxControl
						label={ __( 'Search existing prompts', 'lesson-journal' ) }
						help={ __( 'Pick from prompts you\'ve already created.', 'lesson-journal' ) }
						options={ prompts }
						onChange={ ( val ) => val && selectPrompt( val ) }
						__nextHasNoMarginBottom
					/>

					<div className="ldj-prompt-divider">
						<span>{ __( 'or create a new prompt', 'lesson-journal' ) }</span>
					</div>

					<PromptForm
						title={ formTitle }
						content={ formContent }
						rows={ formRows }
						placeholder={ formPlaceholder }
						required={ formRequired }
						minChars={ formMinChars }
						maxChars={ formMaxChars }
						onChange={ handleFormChange }
						onSave={ createPrompt }
						saving={ saving }
						saveLabel={ __( 'Create Prompt', 'lesson-journal' ) }
						error={ null }
						onError={ setError }
					/>
				</div>
			</div>
		);
	},

	save() {
		return null;
	},
} );
