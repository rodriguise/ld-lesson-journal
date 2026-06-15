<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LDJ_Journal_Shortcode {

	public static function register() {
		add_shortcode( 'ldj_journal', array( __CLASS__, 'render' ) );
		add_action( 'wp_ajax_ldj_refresh_journal', array( __CLASS__, 'ajax_refresh' ) );
	}

	public static function ajax_refresh() {
		check_ajax_referer( 'ldj_entry_nonce', 'nonce' );

		$atts = array(
			'course_id'    => absint( $_POST['course_id'] ?? 0 ),
			'lesson_id'    => absint( $_POST['lesson_id'] ?? 0 ),
			'show_title'   => sanitize_text_field( $_POST['show_title'] ?? '0' ),
			'show_student' => sanitize_text_field( $_POST['show_student'] ?? '0' ),
			'show_print'   => sanitize_text_field( $_POST['show_print'] ?? '1' ),
			'show_save'    => sanitize_text_field( $_POST['show_save'] ?? '1' ),
			'show_refresh' => sanitize_text_field( $_POST['show_refresh'] ?? '1' ),
			'heading'      => wp_kses_post( $_POST['heading'] ?? '' ),
			'instructions' => wp_kses_post( $_POST['instructions'] ?? '' ),
			'show_content' => sanitize_text_field( $_POST['show_content'] ?? '1' ),
			'button_style' => sanitize_text_field( $_POST['button_style'] ?? 'icons' ),
			'show_filter'  => sanitize_text_field( $_POST['show_filter'] ?? '0' ),
		);

		$html = self::render_inner( $atts );

		wp_send_json_success( array( 'html' => $html ) );
	}

	public static function render( $atts ) {
		$atts = shortcode_atts( array(
			'course_id'    => 0,
			'lesson_id'    => 0,
			'show_title'   => '0',
			'show_student' => '0',
			'show_print'   => '1',
			'show_save'    => '1',
			'show_refresh' => '1',
			'heading'      => '',
			'instructions' => '',
			'show_content' => '1',
			'button_style' => 'icons',
			'show_filter'  => '0',
		), $atts, 'ldj_journal' );

		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			return '<p class="ldj-login-prompt">' . esc_html__( 'Please log in to view your journal.', 'lesson-journal' ) . '</p>';
		}

		$course_id = absint( $atts['course_id'] );

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

		wp_localize_script( 'ldj-frontend', 'ldjData', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'ldj_entry_nonce' ),
		) );

		wp_enqueue_style( 'dashicons' );

		wp_enqueue_script(
			'html2pdf',
			LESSON_JOURNAL_URL . 'assets/js/html2pdf.bundle.min.js',
			array(),
			'0.10.2',
			true
		);

		return self::render_inner( $atts );
	}

	public static function render_inner( $atts ) {
		$user_id      = get_current_user_id();
		$course_id    = absint( $atts['course_id'] );
		$lesson_id    = absint( $atts['lesson_id'] );
		$show_title   = filter_var( $atts['show_title'], FILTER_VALIDATE_BOOLEAN );
		$show_student = filter_var( $atts['show_student'], FILTER_VALIDATE_BOOLEAN );
		$show_print   = filter_var( $atts['show_print'], FILTER_VALIDATE_BOOLEAN );
		$show_save    = filter_var( $atts['show_save'] ?? '1', FILTER_VALIDATE_BOOLEAN );
		$show_refresh = filter_var( $atts['show_refresh'] ?? '1', FILTER_VALIDATE_BOOLEAN );
		$heading      = wp_kses_post( $atts['heading'] );
		$instructions = wp_kses_post( $atts['instructions'] ?? '' );
		$show_content = filter_var( $atts['show_content'] ?? '1', FILTER_VALIDATE_BOOLEAN );
		$button_style = ( $atts['button_style'] ?? 'icons' ) === 'text' ? 'text' : 'icons';
		$show_filter  = filter_var( $atts['show_filter'] ?? '0', FILTER_VALIDATE_BOOLEAN );

		if ( ! $user_id || ! $course_id ) {
			return '';
		}

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

		$output  = '<div class="ldj-journal-wrap"'
			. ' data-pdf-filename="' . esc_attr( $pdf_filename ) . '"'
			. ' data-course-id="' . esc_attr( $course_id ) . '"'
			. ' data-lesson-id="' . esc_attr( $lesson_id ) . '"'
			. ' data-show-title="' . esc_attr( $show_title ? '1' : '0' ) . '"'
			. ' data-show-student="' . esc_attr( $show_student ? '1' : '0' ) . '"'
			. ' data-show-print="' . esc_attr( $show_print ? '1' : '0' ) . '"'
			. ' data-show-save="' . esc_attr( $show_save ? '1' : '0' ) . '"'
			. ' data-show-refresh="' . esc_attr( $show_refresh ? '1' : '0' ) . '"'
			. ' data-heading="' . esc_attr( $heading ) . '"'
			. ' data-instructions="' . esc_attr( $instructions ) . '"'
			. ' data-show-content="' . esc_attr( $show_content ? '1' : '0' ) . '"'
			. ' data-button-style="' . esc_attr( $button_style ) . '"'
			. ' data-show-filter="' . esc_attr( $show_filter ? '1' : '0' ) . '"'
			. '>';

		$site_name   = get_bloginfo( 'name' );
		$print_title = $site_name . ' | ' . $course_title;
		if ( $lesson_id > 0 ) {
			$print_title .= ' | ' . get_the_title( $lesson_id );
		}

		$output .= '<div class="ldj-journal-print-header">';
		if ( $logo_url ) {
			$output .= '<img class="ldj-journal-logo" src="' . esc_url( $logo_url ) . '" alt="' . esc_attr( $site_name ) . '">';
		}
		$output .= '<div class="ldj-journal-print-info">';
		$output .= '<h2 class="ldj-journal-print-title">' . esc_html( $print_title ) . '</h2>';
		$output .= '<p class="ldj-journal-print-student">' . esc_html( $user->display_name ) . '</p>';
		$output .= '</div>';
		$output .= '</div>';

		if ( ! empty( $heading ) ) {
			$output .= '<h3 class="ldj-journal-heading">' . $heading . '</h3>';
		}

		if ( ! empty( $instructions ) ) {
			$output .= '<p class="ldj-group-instructions">' . $instructions . '</p>';
		}

		if ( ! empty( $heading ) || ! empty( $instructions ) ) {
			$output .= '<hr class="ldj-group-divider">';
		}

		$content_style = $show_content ? '' : ' style="display:none"';

		if ( $show_filter ) {
			$lessons = array();
			if ( function_exists( 'learndash_get_lesson_list' ) ) {
				$lesson_posts = learndash_get_lesson_list( $course_id );
				foreach ( $lesson_posts as $lp ) {
					if ( is_object( $lp ) ) {
						$lessons[] = $lp;
					}
				}
			}

			if ( ! empty( $lessons ) ) {
				$output .= '<div class="ldj-journal-filter">';
				$output .= '<label for="ldj-lesson-filter">' . esc_html__( 'Filter by lesson:', 'lesson-journal' ) . '</label>';
				$output .= '<select id="ldj-lesson-filter" class="ldj-lesson-filter">';
				$output .= '<option value="0">' . esc_html__( 'All Lessons', 'lesson-journal' ) . '</option>';
				foreach ( $lessons as $lp ) {
					$selected = ( $lesson_id === $lp->ID ) ? ' selected' : '';
					$output .= '<option value="' . esc_attr( $lp->ID ) . '"' . $selected . '>' . esc_html( $lp->post_title ) . '</option>';
				}
				$output .= '</select>';
				$output .= '</div>';
			}
		}

		if ( $show_title || $show_student ) {
			$output .= '<div class="ldj-journal-header"' . $content_style . '>';
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
			$section_title = get_the_title( $group_lesson_id );
			if ( get_post_type( $group_lesson_id ) === 'sfwd-topic' ) {
				$parent_lesson_id = 0;
				if ( function_exists( 'learndash_get_setting' ) ) {
					$parent_lesson_id = (int) learndash_get_setting( $group_lesson_id, 'lesson' );
				}
				if ( $parent_lesson_id > 0 ) {
					$section_title = get_the_title( $parent_lesson_id ) . ' | ' . $section_title;
				}
			}

			$output .= '<div class="ldj-journal-section"' . $content_style . '>';
			$output .= '<h3 class="ldj-journal-lesson-title">' . esc_html( $section_title ) . '</h3>';

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
				$output .= ' <span class="ldj-journal-topic">| <a href="' . esc_url( get_permalink( $group_lesson_id ) ) . '" target="_blank" rel="noopener">' . esc_html( $lesson_title ) . '</a></span>';
				$output .= '</div>';
				$output .= '</div>';

				$entry_index++;
			}

			$output .= '</div>';
		}

		$is_text = $button_style === 'text';

		$output .= '<div class="ldj-journal-toolbar">';
		$output .= '<div class="ldj-journal-pagination"' . $content_style . '>';
		$output .= '<button type="button" class="ldj-journal-prev" disabled>&larr; ' . esc_html__( 'Previous', 'lesson-journal' ) . '</button>';
		$output .= '<span class="ldj-journal-page-info"></span>';
		$output .= '<button type="button" class="ldj-journal-next">' . esc_html__( 'Next', 'lesson-journal' ) . ' &rarr;</button>';
		$output .= '</div>';
		$output .= '<div class="ldj-journal-actions">';
		if ( $show_refresh ) {
			$output .= '<button type="button" class="ldj-journal-refresh-btn" title="' . esc_attr__( 'Refresh', 'lesson-journal' ) . '">';
			if ( $is_text ) {
				$output .= esc_html__( 'Refresh', 'lesson-journal' );
			} else {
				$output .= '<span class="dashicons dashicons-update"></span>';
			}
			$output .= '</button>';
		}
		if ( $show_save ) {
			$output .= '<button type="button" class="ldj-journal-save-btn" title="' . esc_attr__( 'Download', 'lesson-journal' ) . '">';
			if ( $is_text ) {
				$output .= esc_html__( 'Download', 'lesson-journal' );
			} else {
				$output .= '<span class="dashicons dashicons-media-default"></span>';
			}
			$output .= '</button>';
		}
		if ( $show_print ) {
			$output .= '<button type="button" class="ldj-print-btn" title="' . esc_attr__( 'Print', 'lesson-journal' ) . '">';
			if ( $is_text ) {
				$output .= esc_html__( 'Print', 'lesson-journal' );
			} else {
				$output .= '<span class="dashicons dashicons-printer"></span>';
			}
			$output .= '</button>';
		}
		$output .= '</div>';
		$output .= '</div>';

		$output .= '</div>';

		return $output;
	}
}
