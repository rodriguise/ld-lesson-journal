<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LDJ_Journal_Shortcode {

	public static function register() {
		add_shortcode( 'ldj_journal', array( __CLASS__, 'render' ) );
	}

	public static function render( $atts ) {
		$atts = shortcode_atts( array(
			'course_id'    => 0,
			'lesson_id'    => 0,
			'show_title'   => '0',
			'show_student' => '0',
			'show_print'   => '1',
			'heading'      => '',
		), $atts, 'ldj_journal' );

		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			return '<p class="ldj-login-prompt">' . esc_html__( 'Please log in to view your journal.', 'lesson-journal' ) . '</p>';
		}

		$course_id    = absint( $atts['course_id'] );
		$lesson_id    = absint( $atts['lesson_id'] );
		$show_title   = filter_var( $atts['show_title'], FILTER_VALIDATE_BOOLEAN );
		$show_student = filter_var( $atts['show_student'], FILTER_VALIDATE_BOOLEAN );
		$show_print   = filter_var( $atts['show_print'], FILTER_VALIDATE_BOOLEAN );
		$heading      = wp_kses_post( $atts['heading'] );

		if ( ! $course_id ) {
			if ( current_user_can( 'edit_posts' ) ) {
				return '<p class="ldj-error">' . esc_html__( 'Journal View: no course selected. Choose a course in the block settings.', 'lesson-journal' ) . '</p>';
			}
			return '';
		}

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

		wp_enqueue_style( 'dashicons' );

		wp_enqueue_script(
			'html2pdf',
			LESSON_JOURNAL_URL . 'assets/js/html2pdf.bundle.min.js',
			array(),
			'0.10.2',
			true
		);

		$course_title = get_the_title( $course_id );

		if ( $lesson_id > 0 ) {
			$entries = LDJ_Entry::get_by_user_and_lesson( $user_id, $lesson_id );
			$title   = $course_title . ' — ' . get_the_title( $lesson_id );
		} else {
			$entries = LDJ_Entry::get_by_user_and_course( $user_id, $course_id );
			$title   = $course_title;
		}

		if ( empty( $entries ) ) {
			return '<div class="ldj-journal-wrap"><p>' . esc_html__( 'No journal entries yet.', 'lesson-journal' ) . '</p></div>';
		}

		$user         = get_userdata( $user_id );
		$date_format  = get_option( 'date_format' );
		$time_format  = get_option( 'time_format' );
		$datetime_fmt = $date_format . ' ' . $time_format;

		$grouped = array();
		foreach ( $entries as $entry ) {
			$grouped[ $entry->lesson_id ][] = $entry;
		}

		$logo_url = '';
		$custom_logo_id = get_theme_mod( 'custom_logo' );
		if ( $custom_logo_id ) {
			$logo_url = wp_get_attachment_image_url( $custom_logo_id, 'medium' );
		}
		if ( ! $logo_url ) {
			$logo_url = get_site_icon_url( 128 );
		}

		$pdf_filename = sanitize_title( $title ) . '-journal';

	$output  = '<div class="ldj-journal-wrap" data-pdf-filename="' . esc_attr( $pdf_filename ) . '">';

		$output .= '<div class="ldj-journal-print-header">';
		if ( $logo_url ) {
			$output .= '<img class="ldj-journal-logo" src="' . esc_url( $logo_url ) . '" alt="' . esc_attr( get_bloginfo( 'name' ) ) . '">';
		}
		$output .= '<div class="ldj-journal-print-info">';
		$output .= '<h2 class="ldj-journal-print-title">' . esc_html( $title ) . '</h2>';
		$output .= '<p class="ldj-journal-print-student">' . esc_html( $user->display_name ) . '</p>';
		$output .= '</div>';
		$output .= '</div>';

		if ( ! empty( $heading ) ) {
			$output .= '<h3 class="ldj-journal-heading">' . $heading . '</h3>';
		}

		if ( $show_title || $show_student ) {
			$output .= '<div class="ldj-journal-header">';
			if ( $show_title ) {
				$output .= '<h2 class="ldj-journal-title">' . esc_html( $title ) . '</h2>';
			}
			if ( $show_student ) {
				$output .= '<p class="ldj-journal-student">' . esc_html( $user->display_name ) . '</p>';
			}
			$output .= '</div>';
		}

		$entry_index = 0;

		foreach ( $grouped as $group_lesson_id => $lesson_entries ) {
			$lesson_title = get_the_title( $group_lesson_id );

			$output .= '<div class="ldj-journal-section">';
			$output .= '<h3 class="ldj-journal-lesson-title">' . esc_html( $lesson_title ) . '</h3>';

			foreach ( $lesson_entries as $entry ) {
				$prompt = get_post( $entry->prompt_id );

				if ( ! $prompt ) {
					continue;
				}

				$output .= '<div class="ldj-journal-entry" data-entry-index="' . esc_attr( $entry_index ) . '">';
				$output .= '<div class="ldj-journal-question">' . wp_kses_post( wpautop( $prompt->post_content ) ) . '</div>';
				$output .= '<div class="ldj-journal-answer">' . wp_kses_post( nl2br( esc_html( $entry->entry_text ) ) ) . '</div>';
				$output .= '<div class="ldj-journal-meta">';
				$output .= '<span class="ldj-journal-date">'
					. esc_html( date_i18n( $datetime_fmt, strtotime( $entry->updated_at ) ) )
					. '</span>';
				if ( $entry->created_at !== $entry->updated_at ) {
					$output .= ' <span class="ldj-journal-edited">'
						. esc_html__( '(edited)', 'lesson-journal' )
						. '</span>';
				}
				$output .= '</div>';
				$output .= '</div>';

				$entry_index++;
			}

			$output .= '</div>';
		}

		$output .= '<div class="ldj-journal-toolbar">';
		$output .= '<div class="ldj-journal-pagination">';
		$output .= '<button type="button" class="ldj-journal-prev" disabled>&larr; ' . esc_html__( 'Previous', 'lesson-journal' ) . '</button>';
		$output .= '<span class="ldj-journal-page-info"></span>';
		$output .= '<button type="button" class="ldj-journal-next">' . esc_html__( 'Next', 'lesson-journal' ) . ' &rarr;</button>';
		$output .= '</div>';
		$output .= '<div class="ldj-journal-actions">';
		$output .= '<button type="button" class="ldj-journal-save-btn" title="' . esc_attr__( 'Save as PDF', 'lesson-journal' ) . '">';
		$output .= '<span class="dashicons dashicons-media-default"></span>';
		$output .= '</button>';
		if ( $show_print ) {
			$output .= '<button type="button" class="ldj-print-btn" title="' . esc_attr__( 'Print', 'lesson-journal' ) . '">';
			$output .= '<span class="dashicons dashicons-printer"></span>';
			$output .= '</button>';
		}
		$output .= '</div>';
		$output .= '</div>';

		$output .= '</div>';

		return $output;
	}
}
