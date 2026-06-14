import './editor.css';
import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks, InspectorControls, useBlockProps, RichText } from '@wordpress/block-editor';
import { PanelBody, ToggleControl, RangeControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

registerBlockType( 'ldj/prompt-group', {
	edit( { attributes, setAttributes } ) {
		const blockProps = useBlockProps( {
			className: 'ldj-group ldj-group--editor',
		} );

		return (
			<>
				<InspectorControls>
					<PanelBody title={ __( 'Journal Settings', 'lesson-journal' ) }>
						<ToggleControl
							label={ __( 'Required for lesson completion', 'lesson-journal' ) }
							help={ attributes.required
								? __( 'Students must complete all prompts before marking the lesson complete.', 'lesson-journal' )
								: __( 'Journal entries are optional.', 'lesson-journal' )
							}
							checked={ attributes.required }
							onChange={ ( val ) => setAttributes( { required: val } ) }
						/>
						<RangeControl
							label={ __( 'Prompts per page', 'lesson-journal' ) }
							help={ attributes.perPage > 0
								? __( 'Shows pagination controls.', 'lesson-journal' )
								: __( 'All prompts shown at once.', 'lesson-journal' )
							}
							value={ attributes.perPage }
							onChange={ ( val ) => setAttributes( { perPage: val } ) }
							min={ 0 }
							max={ 20 }
							allowReset
							resetFallbackValue={ 0 }
						/>
					</PanelBody>
				</InspectorControls>
				<div { ...blockProps }>
					<div className="ldj-group-label">
						{ __( 'Journal Prompt Group', 'lesson-journal' ) }
						{ attributes.required && (
							<span className="ldj-required-badge">
								{ __( 'Required', 'lesson-journal' ) }
							</span>
						) }
					</div>
					<RichText
						tagName="h3"
						className="ldj-group-heading"
						placeholder={ __( 'Add a heading (optional)…', 'lesson-journal' ) }
						value={ attributes.heading }
						onChange={ ( val ) => setAttributes( { heading: val } ) }
						allowedFormats={ [ 'core/bold', 'core/italic' ] }
					/>
					<InnerBlocks
						allowedBlocks={ [ 'ldj/prompt' ] }
						renderAppender={ InnerBlocks.ButtonBlockAppender }
					/>
				</div>
			</>
		);
	},

	save() {
		return <InnerBlocks.Content />;
	},
} );
