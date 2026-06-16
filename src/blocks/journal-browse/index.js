import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, ToggleControl, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

registerBlockType( 'ldj/journal-browse', {
	edit: function ( { attributes, setAttributes } ) {
		const { showCourseFilter, showLessonFilter, showPrint, showSave, buttonStyle } = attributes;

		const blockProps = useBlockProps( {
			style: {
				padding: '20px',
				background: '#f9f9f9',
				border: '1px dashed #999',
				textAlign: 'center',
			},
		} );

		return (
			<>
				<InspectorControls>
					<PanelBody title={ __( 'Display Options', 'lesson-journal' ) }>
						<ToggleControl
							label={ __( 'Show Course Filter', 'lesson-journal' ) }
							checked={ showCourseFilter }
							onChange={ ( val ) => setAttributes( { showCourseFilter: val } ) }
						/>
						<ToggleControl
							label={ __( 'Show Lesson Filter', 'lesson-journal' ) }
							checked={ showLessonFilter }
							onChange={ ( val ) => setAttributes( { showLessonFilter: val } ) }
						/>
						<ToggleControl
							label={ __( 'Show Print Button', 'lesson-journal' ) }
							checked={ showPrint }
							onChange={ ( val ) => setAttributes( { showPrint: val } ) }
						/>
						<ToggleControl
							label={ __( 'Show Download Button', 'lesson-journal' ) }
							checked={ showSave }
							onChange={ ( val ) => setAttributes( { showSave: val } ) }
						/>
						<SelectControl
							label={ __( 'Button Style', 'lesson-journal' ) }
							value={ buttonStyle }
							options={ [
								{ label: __( 'Icons', 'lesson-journal' ), value: 'icons' },
								{ label: __( 'Text', 'lesson-journal' ), value: 'text' },
							] }
							onChange={ ( val ) => setAttributes( { buttonStyle: val } ) }
						/>
					</PanelBody>
				</InspectorControls>
				<div { ...blockProps }>
					<p style={ { fontSize: '16px', fontWeight: 'bold' } }>
						{ __( 'Journal Browser', 'lesson-journal' ) }
					</p>
					<p style={ { color: '#666', fontSize: '13px' } }>
						{ __( 'Students can browse their journal by course and lesson. Configure options in the sidebar.', 'lesson-journal' ) }
					</p>
				</div>
			</>
		);
	},
	save: function () {
		return null;
	},
} );
