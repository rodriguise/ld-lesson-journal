import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

registerBlockType( 'ldj/screen-only', {
	edit() {
		const blockProps = useBlockProps( {
			className: 'ldj-screen-only ldj-screen-only--editor',
			style: {
				border: '1px dashed #9ca3af',
				borderRadius: '6px',
				padding: '12px',
				position: 'relative',
			},
		} );

		return (
			<div { ...blockProps }>
				<span style={ {
					position: 'absolute',
					top: '-10px',
					left: '8px',
					background: '#f9fafb',
					padding: '0 6px',
					fontSize: '11px',
					color: '#6b7280',
					fontStyle: 'italic',
				} }>
					{ __( 'Screen only — excluded from print/PDF', 'lesson-journal' ) }
				</span>
				<InnerBlocks />
			</div>
		);
	},

	save() {
		return <InnerBlocks.Content />;
	},
} );
