import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

registerBlockType( 'ldj/grader', {
	edit: function () {
		const blockProps = useBlockProps( {
			style: {
				padding: '20px',
				background: '#f0f6fc',
				border: '1px dashed #2271b1',
				textAlign: 'center',
			},
		} );

		return (
			<div { ...blockProps }>
				<p style={ { fontSize: '16px', fontWeight: 'bold' } }>
					{ __( 'Journal Grader', 'lesson-journal' ) }
				</p>
				<p style={ { color: '#666', fontSize: '13px' } }>
					{ __( 'This block displays the frontend grading interface for instructors. It renders on the frontend only.', 'lesson-journal' ) }
				</p>
			</div>
		);
	},
	save: function () {
		return null;
	},
} );
