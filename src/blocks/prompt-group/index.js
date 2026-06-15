import './editor.css';
import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks, InspectorControls, useBlockProps, RichText } from '@wordpress/block-editor';
import { PanelBody, ToggleControl, RangeControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

registerBlockType( 'ldj/prompt-group', {
	edit( { attributes, setAttributes } ) {
		const blockProps = useBlockProps( {
			className: 'ldj-group ldj-group--editor' + ( attributes.showNumbers ? ' ldj-group--numbered' : '' ),
		} );

		const hasHeader = attributes.heading || attributes.instructions;

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
						<ToggleControl
							label={ __( 'Show View Journal button', 'lesson-journal' ) }
							checked={ attributes.showViewJournal }
							onChange={ ( val ) => setAttributes( { showViewJournal: val } ) }
						/>
						<ToggleControl
							label={ __( 'Number prompts', 'lesson-journal' ) }
							help={ __( 'Show 1., 2., 3. before each prompt.', 'lesson-journal' ) }
							checked={ attributes.showNumbers }
							onChange={ ( val ) => setAttributes( { showNumbers: val } ) }
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
					<RichText
						tagName="h3"
						className="ldj-group-heading"
						placeholder={ __( 'Add a heading (optional)…', 'lesson-journal' ) }
						value={ attributes.heading }
						onChange={ ( val ) => setAttributes( { heading: val } ) }
						allowedFormats={ [ 'core/bold', 'core/italic' ] }
					/>
					<RichText
						tagName="p"
						className="ldj-group-instructions"
						placeholder={ __( 'Add instructions (optional)…', 'lesson-journal' ) }
						value={ attributes.instructions }
						onChange={ ( val ) => setAttributes( { instructions: val } ) }
						allowedFormats={ [ 'core/bold', 'core/italic' ] }
					/>
					{ hasHeader && <hr className="ldj-group-divider" /> }
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
