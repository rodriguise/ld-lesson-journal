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
$description = get_post_meta( $prompt_id, '_ldj_description', true );
$required    = (bool) get_post_meta( $prompt_id, '_ldj_required', true );
$min_chars   = (int) get_post_meta( $prompt_id, '_ldj_min_chars', true );
$max_chars   = (int) get_post_meta( $prompt_id, '_ldj_max_chars', true );

if ( $required && $min_chars < 1 ) {
	$min_chars = 1;
}

$existing   = $user_id ? LDJ_Entry::get( $prompt_id, $user_id, $lesson_id ) : null;
$entry_text = $existing ? $existing->entry_text : '';
$has_entry  = ! empty( $entry_text );

?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'ldj-prompt-wrap' ) ); ?>
	data-prompt-id="<?php echo esc_attr( $prompt_id ); ?>"
	data-prompt-title="<?php echo esc_attr( $prompt->post_title ); ?>">

	<div class="ldj-prompt-text">
		<?php echo wp_kses_post( wpautop( do_blocks( $prompt->post_content ) ) ); ?>
	</div>

	<?php if ( $has_entry ) :
		$is_locked   = $existing && LDJ_Entry::is_locked_grade( $existing->grade_status ?? null );
		$is_reopened = $existing && ! empty( $existing->reopened );
	?>
		<div class="ldj-completed-entry">
			<div class="ldj-entry-display"><?php echo wp_kses_post( nl2br( esc_html( $entry_text ) ) ); ?></div>
			<?php if ( ! $is_locked || $is_reopened ) : ?>
				<button type="button" class="ldj-edit-entry"><?php esc_html_e( 'Edit', 'lesson-journal' ); ?></button>
				<button type="button" class="ldj-delete-entry"><?php esc_html_e( 'Delete', 'lesson-journal' ); ?></button>
			<?php else : ?>
				<?php
				$prompt_value = (int) get_post_meta( $prompt_id, '_ldj_prompt_value', true ) ?: 10;
				$rubric_text  = get_post_meta( $prompt_id, '_ldj_rubric', true );
				?>
				<span class="ldj-prompt-badge ldj-prompt-badge--graded ldj-has-popup">
					<?php esc_html_e( '(Graded)', 'lesson-journal' ); ?>
					<span class="ldj-badge-popup">
						<strong><?php printf( esc_html__( 'Possible Points: %d', 'lesson-journal' ), $prompt_value ); ?></strong>
						<?php if ( $rubric_text ) : ?>
							<?php echo wp_kses_post( wpautop( $rubric_text ) ); ?>
						<?php else : ?>
							<em><?php esc_html_e( 'No rubric provided.', 'lesson-journal' ); ?></em>
						<?php endif; ?>
					</span>
				</span>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<div class="ldj-textarea-wrap"<?php echo $has_entry ? ' style="display:none"' : ''; ?>
		<?php if ( $min_chars > 0 ) : ?> data-min-chars="<?php echo esc_attr( $min_chars ); ?>"<?php endif; ?>
		<?php if ( $max_chars > 0 ) : ?> data-max-chars="<?php echo esc_attr( $max_chars ); ?>"<?php endif; ?>>
		<textarea
			class="ldj-textarea"
			rows="<?php echo esc_attr( $rows ); ?>"
			<?php if ( $placeholder ) : ?>placeholder="<?php echo esc_attr( $placeholder ); ?>"<?php endif; ?>
			<?php if ( $max_chars > 0 ) : ?>maxlength="<?php echo esc_attr( $max_chars ); ?>"<?php endif; ?>
		><?php echo esc_textarea( $entry_text ); ?></textarea>

		<div class="ldj-char-count">
			<span class="ldj-current-chars"><?php echo mb_strlen( $entry_text ); ?></span><?php if ( $max_chars > 0 ) : ?> / <?php echo esc_html( $max_chars ); ?><?php endif; ?>
			<?php if ( $min_chars > 0 ) : ?>
				<span class="ldj-min-chars-label"><?php printf( esc_html__( '(min %d)', 'lesson-journal' ), $min_chars ); ?></span>
			<?php endif; ?>
		</div>

	</div>

	<?php if ( $description ) : ?>
		<p class="ldj-prompt-description"><?php echo esc_html( ucfirst( $description ) ); ?></p>
	<?php endif; ?>

	<?php
	$is_private = (bool) get_post_meta( $prompt_id, '_ldj_private', true );
	if ( $required || $is_private ) : ?>
		<div class="ldj-prompt-meta">
			<?php if ( $required ) : ?>
				<span class="ldj-prompt-badge ldj-prompt-badge--required"><?php esc_html_e( 'Required', 'lesson-journal' ); ?></span>
			<?php endif; ?>
			<?php if ( $is_private ) : ?>
				<span class="ldj-prompt-badge ldj-prompt-badge--private" title="<?php esc_attr_e( 'Instructors and admins cannot see this response — it is for your use only.', 'lesson-journal' ); ?>"><?php esc_html_e( 'Private', 'lesson-journal' ); ?></span>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
