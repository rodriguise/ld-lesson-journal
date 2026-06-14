<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$prompt_id = absint( $attributes['promptId'] ?? 0 );

if ( ! $prompt_id ) {
	return;
}

$prompt = get_post( $prompt_id );

if ( ! $prompt || $prompt->post_type !== 'ldj_prompt' || $prompt->post_status !== 'publish' ) {
	if ( current_user_can( 'edit_posts' ) ) {
		echo '<p class="ldj-error">' . esc_html__( 'Journal prompt not found or not published.', 'lesson-journal' ) . '</p>';
	}
	return;
}

$lesson_id = get_the_ID();
$user_id   = get_current_user_id();

$rows        = (int) get_post_meta( $prompt_id, '_ldj_rows', true ) ?: 5;
$placeholder = get_post_meta( $prompt_id, '_ldj_placeholder', true );
$max_chars   = (int) get_post_meta( $prompt_id, '_ldj_max_chars', true );

$existing   = $user_id ? LDJ_Entry::get( $prompt_id, $user_id, $lesson_id ) : null;
$entry_text = $existing ? $existing->entry_text : '';
$has_entry  = ! empty( $entry_text );

?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'ldj-prompt-wrap' ) ); ?>
	data-prompt-id="<?php echo esc_attr( $prompt_id ); ?>">

	<div class="ldj-prompt-text">
		<?php echo wp_kses_post( wpautop( $prompt->post_content ) ); ?>
	</div>

	<?php if ( $has_entry ) : ?>
		<div class="ldj-completed-entry">
			<div class="ldj-entry-display"><?php echo wp_kses_post( nl2br( esc_html( $entry_text ) ) ); ?></div>
			<button type="button" class="ldj-edit-entry"><?php esc_html_e( 'Edit', 'lesson-journal' ); ?></button>
			<button type="button" class="ldj-delete-entry"><?php esc_html_e( 'Delete', 'lesson-journal' ); ?></button>
		</div>
	<?php endif; ?>

	<div class="ldj-textarea-wrap"<?php echo $has_entry ? ' style="display:none"' : ''; ?>>
		<textarea
			class="ldj-textarea"
			rows="<?php echo esc_attr( $rows ); ?>"
			<?php if ( $placeholder ) : ?>placeholder="<?php echo esc_attr( $placeholder ); ?>"<?php endif; ?>
			<?php if ( $max_chars > 0 ) : ?>maxlength="<?php echo esc_attr( $max_chars ); ?>"<?php endif; ?>
		><?php echo esc_textarea( $entry_text ); ?></textarea>

		<?php if ( $max_chars > 0 ) : ?>
			<div class="ldj-char-count">
				<span class="ldj-current-chars"><?php echo mb_strlen( $entry_text ); ?></span> / <?php echo esc_html( $max_chars ); ?>
			</div>
		<?php endif; ?>
	</div>
</div>
