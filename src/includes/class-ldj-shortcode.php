<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LDJ_Shortcode {

	private static $enqueued = false;

	public static function register() {
		add_shortcode( 'ldj_group', array( __CLASS__, 'render_group' ) );
		add_shortcode( 'ldj', array( __CLASS__, 'render_prompt' ) );
	}

	private static function enqueue_assets() {
		if ( self::$enqueued ) {
			return;
		}

		self::$enqueued = true;

		wp_enqueue_style(
			'ldj-frontend',
			LESSON_JOURNAL_URL . 'assets/css/ldj-frontend.css',
			array(),
			LESSON_JOURNAL_VERSION
		);

		wp_enqueue_script(
			'ldj-frontend',
			LESSON_JOURNAL_URL . 'assets/js/ldj-frontend.js',
			array(),
			LESSON_JOURNAL_VERSION,
			true
		);

		wp_localize_script( 'ldj-frontend', 'ldjData', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'ldj_entry_nonce' ),
			'i18n'    => array(
				'saving'   => __( 'Saving…', 'lesson-journal' ),
				'saved'    => __( 'Journal entries saved.', 'lesson-journal' ),
				'deleted'  => __( 'Entry deleted.', 'lesson-journal' ),
				'error'    => __( 'An error occurred. Please try again.', 'lesson-journal' ),
				'confirm'  => __( 'Are you sure you want to delete this entry?', 'lesson-journal' ),
				'required'       => __( 'Please complete all required entries.', 'lesson-journal' ),
				'promptRequired' => __( 'This prompt requires a response.', 'lesson-journal' ),
				'promptMinChars' => __( 'This prompt requires at least %d characters.', 'lesson-journal' ),
			),
		) );
	}

	public static function render_group( $atts, $content = '' ) {
		$atts = shortcode_atts( array(
			'required'     => '0',
			'heading'      => '',
			'title'        => '',
			'instructions' => '',
			'display'      => 'standard',
			'per_page'     => '0',
			'numbers'      => '0',
		), $atts, 'ldj_group' );

		$required      = filter_var( $atts['required'], FILTER_VALIDATE_BOOLEAN );
		$per_page_raw  = absint( $atts['per_page'] );
		$display       = in_array( $atts['display'], array( 'standard', 'paginated', 'accordion' ), true ) ? $atts['display'] : ( $per_page_raw > 0 ? 'paginated' : 'standard' );
		$per_page      = $display === 'paginated' ? $per_page_raw : 0;
		$show_numbers = filter_var( $atts['numbers'], FILTER_VALIDATE_BOOLEAN );
		$group_title  = sanitize_text_field( $atts['title'] );
		$lesson_id = get_the_ID();
		$post_type = get_post_type( $lesson_id );

		if ( ! in_array( $post_type, array( 'sfwd-lessons', 'sfwd-topic' ), true ) ) {
			if ( current_user_can( 'edit_posts' ) ) {
				return '<p class="ldj-error">' . esc_html__( 'Journal prompts can only be used on LearnDash lessons or topics.', 'lesson-journal' ) . '</p>';
			}
			return '';
		}

		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			return '<p class="ldj-login-prompt">' . esc_html__( 'Please log in to use the journal.', 'lesson-journal' ) . '</p>';
		}

		self::enqueue_assets();

		$inner = do_shortcode( $content );

		$group_class = 'ldj-group';
		if ( $show_numbers ) {
			$group_class .= ' ldj-group--numbered';
		}

		$output  = '<div class="' . esc_attr( $group_class ) . '" data-lesson-id="' . esc_attr( $lesson_id ) . '" data-required="' . esc_attr( $required ? '1' : '0' ) . '" data-display="' . esc_attr( $display ) . '"';
		if ( $group_title ) {
			$output .= ' data-group-title="' . esc_attr( $group_title ) . '"';
		}
		if ( $per_page > 0 ) {
			$output .= ' data-per-page="' . esc_attr( $per_page ) . '"';
		}
		$output .= '>';

		if ( ! empty( $atts['heading'] ) ) {
			$output .= '<h3 class="ldj-group-heading">' . wp_kses_post( $atts['heading'] ) . '</h3>';
		}

		if ( ! empty( $atts['instructions'] ) ) {
			$output .= '<p class="ldj-group-instructions">' . wp_kses_post( $atts['instructions'] ) . '</p>';
		}

		if ( ! empty( $atts['heading'] ) || ! empty( $atts['instructions'] ) ) {
			$output .= '<hr class="ldj-group-divider">';
		}

		$output .= $inner;

		if ( $per_page > 0 ) {
			$output .= '<div class="ldj-group-pagination">';
			$output .= '<button type="button" class="ldj-group-prev" disabled>&larr; ' . esc_html__( 'Previous', 'lesson-journal' ) . '</button>';
			$output .= '<span class="ldj-group-page-info"></span>';
			$output .= '<button type="button" class="ldj-group-next">' . esc_html__( 'Next', 'lesson-journal' ) . ' &rarr;</button>';
			$output .= '</div>';
		}

		$output .= '<div class="ldj-group-actions">';
		$output .= '<button type="button" class="ldj-save-group">' . esc_html__( 'Submit', 'lesson-journal' ) . '</button>';
		$output .= '</div>';
		$output .= '<div class="ldj-feedback" aria-live="polite"></div>';
		$output .= '</div>';

		return $output;
	}

	public static function render_prompt( $atts ) {
		$atts = shortcode_atts( array(
			'id' => 0,
		), $atts, 'ldj' );

		$prompt_id = absint( $atts['id'] );

		if ( ! $prompt_id ) {
			return '';
		}

		$prompt = get_post( $prompt_id );

		if ( ! $prompt || $prompt->post_type !== 'ldj_prompt' || $prompt->post_status !== 'publish' ) {
			if ( current_user_can( 'edit_posts' ) ) {
				return '<p class="ldj-error">' . esc_html__( 'Journal prompt not found or not published.', 'lesson-journal' ) . '</p>';
			}
			return '';
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

		self::enqueue_assets();

		$output  = '<div class="ldj-prompt-wrap" data-prompt-id="' . esc_attr( $prompt_id ) . '" data-prompt-title="' . esc_attr( $prompt->post_title ) . '">';
		$output .= '<div class="ldj-prompt-text">' . wp_kses_post( wpautop( do_blocks( $prompt->post_content ) ) ) . '</div>';

		if ( $has_entry ) {
			$output .= '<div class="ldj-completed-entry">';
			$output .= '<div class="ldj-entry-display">' . wp_kses_post( nl2br( esc_html( $entry_text ) ) ) . '</div>';
			$output .= '<button type="button" class="ldj-edit-entry">' . esc_html__( 'Edit', 'lesson-journal' ) . '</button>';
			$output .= '<button type="button" class="ldj-delete-entry">' . esc_html__( 'Delete', 'lesson-journal' ) . '</button>';
			$output .= '</div>';
		}

		$textarea_style = $has_entry ? ' style="display:none"' : '';

		$output .= '<div class="ldj-textarea-wrap"' . $textarea_style;
		if ( $min_chars > 0 ) {
			$output .= ' data-min-chars="' . esc_attr( $min_chars ) . '"';
		}
		if ( $max_chars > 0 ) {
			$output .= ' data-max-chars="' . esc_attr( $max_chars ) . '"';
		}
		$output .= '>';
		$output .= '<textarea class="ldj-textarea" rows="' . esc_attr( $rows ) . '"';

		if ( $placeholder ) {
			$output .= ' placeholder="' . esc_attr( $placeholder ) . '"';
		}

		if ( $max_chars > 0 ) {
			$output .= ' maxlength="' . esc_attr( $max_chars ) . '"';
		}

		$output .= '>' . esc_textarea( $entry_text ) . '</textarea>';

		$output .= '<div class="ldj-char-count"><span class="ldj-current-chars">' . mb_strlen( $entry_text ) . '</span>';
		if ( $max_chars > 0 ) {
			$output .= ' / ' . esc_html( $max_chars );
		}
		if ( $min_chars > 0 ) {
			$output .= ' <span class="ldj-min-chars-label">' . sprintf( esc_html__( '(min %d)', 'lesson-journal' ), $min_chars ) . '</span>';
		}
		$output .= '</div>';

		$output .= '</div>';

		if ( $description ) {
			$output .= '<p class="ldj-prompt-description">' . esc_html( ucfirst( $description ) ) . '</p>';
		}

		$output .= '</div>';

		return $output;
	}
}
