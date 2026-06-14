<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$required  = ! empty( $attributes['required'] );
$lesson_id = get_the_ID();
$post_type = get_post_type( $lesson_id );
$user_id   = get_current_user_id();

if ( ! in_array( $post_type, array( 'sfwd-lessons', 'sfwd-topic' ), true ) ) {
	if ( current_user_can( 'edit_posts' ) ) {
		echo '<p class="ldj-error">' . esc_html__( 'Journal prompts can only be used on LearnDash lessons or topics.', 'lesson-journal' ) . '</p>';
	}
	return;
}

if ( ! $user_id ) {
	echo '<p class="ldj-login-prompt">' . esc_html__( 'Please log in to use the journal.', 'lesson-journal' ) . '</p>';
	return;
}

wp_enqueue_style( 'ldj-frontend' );
wp_enqueue_script( 'ldj-frontend' );

wp_localize_script( 'ldj-frontend', 'ldjData', array(
	'ajaxUrl' => admin_url( 'admin-ajax.php' ),
	'nonce'   => wp_create_nonce( 'ldj_entry_nonce' ),
	'i18n'    => array(
		'saving'   => __( 'Saving…', 'lesson-journal' ),
		'saved'    => __( 'Journal entries saved.', 'lesson-journal' ),
		'deleted'  => __( 'Entry deleted.', 'lesson-journal' ),
		'error'    => __( 'An error occurred. Please try again.', 'lesson-journal' ),
		'confirm'  => __( 'Are you sure you want to delete this entry?', 'lesson-journal' ),
		'required' => __( 'Please complete all required entries.', 'lesson-journal' ),
	),
) );

?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'ldj-group' ) ); ?>
	data-lesson-id="<?php echo esc_attr( $lesson_id ); ?>"
	data-required="<?php echo esc_attr( $required ? '1' : '0' ); ?>">

	<?php if ( ! empty( $attributes['heading'] ) ) : ?>
		<h3 class="ldj-group-heading"><?php echo wp_kses_post( $attributes['heading'] ); ?></h3>
	<?php endif; ?>

	<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

	<div class="ldj-group-actions">
		<button type="button" class="ldj-save-group"><?php esc_html_e( 'Save Journal', 'lesson-journal' ); ?></button>
	</div>
	<div class="ldj-feedback" aria-live="polite"></div>
</div>
