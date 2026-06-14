import './editor.css';
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls, RichText } from '@wordpress/block-editor';
import { useState, useEffect } from '@wordpress/element';
import { PanelBody, ComboboxControl, ToggleControl } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

registerBlockType( 'ldj/journal-view', {
	edit( { attributes, setAttributes } ) {
		const blockProps = useBlockProps( { className: 'ldj-journal-view--editor' } );
		const { courseId, lessonId, showTitle, showStudent, showPrint, heading } = attributes;

		const [ courses, setCourses ] = useState( [] );
		const [ lessons, setLessons ] = useState( [] );
		const [ courseName, setCourseName ] = useState( '' );
		const [ lessonName, setLessonName ] = useState( '' );

		useEffect( () => {
			apiFetch( { path: '/ldj/v1/courses' } )
				.then( ( data ) => {
					setCourses( data.map( ( p ) => ( { value: p.id, label: p.title } ) ) );
					if ( courseId ) {
						const match = data.find( ( p ) => p.id === courseId );
						if ( match ) setCourseName( match.title );
					}
				} )
				.catch( () => {} );
		}, [] );

		useEffect( () => {
			if ( courseId > 0 ) {
				apiFetch( { path: `/ldj/v1/lessons?course_id=${ courseId }` } )
					.then( ( data ) => {
						setLessons( data.map( ( p ) => ( { value: p.id, label: p.title } ) ) );
						if ( lessonId ) {
							const match = data.find( ( p ) => p.id === lessonId );
							if ( match ) setLessonName( match.title );
						}
					} )
					.catch( ( err ) => {
							console.error( 'LDJ lessons fetch error:', err );
						} );
			} else {
				setLessons( [] );
				setLessonName( '' );
			}
		}, [ courseId ] );

		let description = __( 'Select a course to display journal entries.', 'lesson-journal' );
		if ( courseId && courseName ) {
			description = lessonName
				? courseName + ' → ' + lessonName
				: courseName + ' — ' + __( 'all lessons', 'lesson-journal' );
		}

		return (
			<>
				<InspectorControls>
					<PanelBody title={ __( 'Journal Source', 'lesson-journal' ) }>
						<ComboboxControl
							label={ __( 'Course', 'lesson-journal' ) }
							options={ courses }
							value={ courseId || null }
							onChange={ ( val ) => {
								const numVal = Number( val );
								setAttributes( { courseId: numVal, lessonId: 0 } );
								setLessonName( '' );
								const match = courses.find( ( c ) => c.value === numVal );
								setCourseName( match ? match.label : '' );
							} }
							__nextHasNoMarginBottom
						/>
						{ courseId > 0 && (
							<ComboboxControl
								label={ __( 'Lesson (optional)', 'lesson-journal' ) }
								options={ lessons }
								value={ lessonId || null }
								onChange={ ( val ) => {
									const numVal = Number( val ) || 0;
									setAttributes( { lessonId: numVal } );
									const match = lessons.find( ( l ) => l.value === numVal );
									setLessonName( match ? match.label : '' );
								} }
								__nextHasNoMarginBottom
							/>
						) }
					</PanelBody>
					<PanelBody title={ __( 'Display', 'lesson-journal' ) }>
						<ToggleControl
							label={ __( 'Show course title', 'lesson-journal' ) }
							checked={ showTitle }
							onChange={ ( val ) => setAttributes( { showTitle: val } ) }
						/>
						<ToggleControl
							label={ __( 'Show student name', 'lesson-journal' ) }
							checked={ showStudent }
							onChange={ ( val ) => setAttributes( { showStudent: val } ) }
						/>
						<ToggleControl
							label={ __( 'Show print button', 'lesson-journal' ) }
							checked={ showPrint }
							onChange={ ( val ) => setAttributes( { showPrint: val } ) }
						/>
					</PanelBody>
				</InspectorControls>
				<div { ...blockProps }>
					<RichText
						tagName="h3"
						className="ldj-journal-heading"
						placeholder={ __( 'Add a heading (optional)…', 'lesson-journal' ) }
						value={ heading }
						onChange={ ( val ) => setAttributes( { heading: val } ) }
						allowedFormats={ [ 'core/bold', 'core/italic' ] }
					/>
					<div className="ldj-journal-view-placeholder">
						<span className="dashicons dashicons-media-text"></span>
						<h4>{ __( 'Journal View', 'lesson-journal' ) }</h4>
						<p>{ description }</p>
					</div>
				</div>
			</>
		);
	},

	save() {
		return null;
	},
} );
