<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LDJ_Journal_Browse {

	public static function register() {
		add_shortcode( 'ldj_journal_browse', array( __CLASS__, 'render' ) );
	}

	public static function render( $atts ) {
		$atts = shortcode_atts( array(
			'show_course_filter' => '1',
			'show_lesson_filter' => '1',
			'show_print'         => '1',
			'show_save'          => '1',
			'button_style'       => 'icons',
		), $atts, 'ldj_journal_browse' );

		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			return '<p class="ldj-login-prompt">' . esc_html__( 'Please log in to view your journal.', 'lesson-journal' ) . '</p>';
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
			'i18n'    => array(
				'saving'         => __( 'Saving…', 'lesson-journal' ),
				'saved'          => __( 'Journal entries saved.', 'lesson-journal' ),
				'deleted'        => __( 'Entry deleted.', 'lesson-journal' ),
				'error'          => __( 'An error occurred. Please try again.', 'lesson-journal' ),
				'confirm'        => __( 'Are you sure you want to delete this entry?', 'lesson-journal' ),
				'required'       => __( 'Please complete all required entries.', 'lesson-journal' ),
				'promptRequired' => __( 'This prompt requires a response.', 'lesson-journal' ),
				'promptMinChars' => __( 'This prompt requires at least %d characters.', 'lesson-journal' ),
			),
		) );

		wp_enqueue_style( 'dashicons' );

		wp_enqueue_script(
			'html2pdf',
			LESSON_JOURNAL_URL . 'assets/js/html2pdf.bundle.min.js',
			array(),
			'0.10.2',
			true
		);

		$selected_course = absint( $_GET['ldj_browse_course'] ?? 0 );
		$selected_lesson = absint( $_GET['ldj_browse_lesson'] ?? 0 );

		$courses = self::get_enrolled_courses( $user_id );

		$output = '<div class="ldj-journal-browse">';

		if ( filter_var( $atts['show_course_filter'], FILTER_VALIDATE_BOOLEAN ) ) {
			$base_url = remove_query_arg( array( 'ldj_browse_course', 'ldj_browse_lesson' ) );

			$output .= '<div class="ldj-browse-selectors">';
			$output .= '<select class="ldj-browse-course-select" onchange="if(this.value){location.href=this.value}else{location.href=\'' . esc_js( $base_url ) . '\'}">';
			$output .= '<option value="">' . esc_html__( '— Select Course —', 'lesson-journal' ) . '</option>';
			foreach ( $courses as $course ) {
				$url      = add_query_arg( 'ldj_browse_course', $course->ID, $base_url );
				$selected = $selected_course === $course->ID ? ' selected' : '';
				$output  .= '<option value="' . esc_attr( $url ) . '"' . $selected . '>' . esc_html( $course->post_title ) . '</option>';
			}
			$output .= '</select>';

			if ( $selected_course && filter_var( $atts['show_lesson_filter'], FILTER_VALIDATE_BOOLEAN ) ) {
				$lessons = self::get_course_lessons( $selected_course );
				if ( ! empty( $lessons ) ) {
					$output .= '<select class="ldj-browse-lesson-select" onchange="if(this.value){location.href=this.value}else{location.href=\'' . esc_js( add_query_arg( 'ldj_browse_course', $selected_course, $base_url ) ) . '\'}">';
					$output .= '<option value="">' . esc_html__( '— All Lessons —', 'lesson-journal' ) . '</option>';
					foreach ( $lessons as $lesson ) {
						$url      = add_query_arg( array( 'ldj_browse_course' => $selected_course, 'ldj_browse_lesson' => $lesson->ID ), $base_url );
						$selected = $selected_lesson === $lesson->ID ? ' selected' : '';
						$type     = $lesson->post_type === 'sfwd-topic' ? __( 'Topic', 'lesson-journal' ) : __( 'Lesson', 'lesson-journal' );
						$output  .= '<option value="' . esc_attr( $url ) . '"' . $selected . '>' . esc_html( $lesson->post_title ) . ' (' . esc_html( $type ) . ')</option>';
					}
					$output .= '</select>';
				}
			}
			$output .= '</div>';
		}

		if ( $selected_course ) {
			$journal_atts = array(
				'course_id'    => $selected_course,
				'lesson_id'    => $selected_lesson,
				'show_title'   => '1',
				'show_student' => '1',
				'show_print'   => $atts['show_print'],
				'show_save'    => $atts['show_save'],
				'show_refresh' => '1',
				'show_content' => '1',
				'button_style' => $atts['button_style'],
				'show_filter'  => '0',
			);
			$output .= LDJ_Journal_Shortcode::render_inner( $journal_atts );
		} else {
			$output .= '<p class="ldj-browse-prompt">' . esc_html__( 'Select a course to view your journal.', 'lesson-journal' ) . '</p>';
		}

		$output .= '</div>';

		return $output;
	}

	private static function get_enrolled_courses( int $user_id ): array {
		if ( function_exists( 'learndash_user_get_enrolled_courses' ) ) {
			$course_ids = learndash_user_get_enrolled_courses( $user_id );
			if ( ! empty( $course_ids ) ) {
				$courses = get_posts( array(
					'post_type'      => 'sfwd-courses',
					'post__in'       => $course_ids,
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'orderby'        => 'title',
					'order'          => 'ASC',
				) );
				return $courses;
			}
		}

		return get_posts( array(
			'post_type'      => 'sfwd-courses',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );
	}

	private static function get_course_lessons( int $course_id ): array {
		$steps = array();

		if ( function_exists( 'learndash_course_get_steps_by_type' ) ) {
			$lesson_ids = learndash_course_get_steps_by_type( $course_id, 'sfwd-lessons' );
			$topic_ids  = learndash_course_get_steps_by_type( $course_id, 'sfwd-topic' );
			$step_ids   = array_merge( $lesson_ids, $topic_ids );
		} else {
			$step_ids = array();
			if ( function_exists( 'learndash_get_lesson_list' ) ) {
				$lessons = learndash_get_lesson_list( $course_id );
				foreach ( $lessons as $lesson ) {
					$step_ids[] = $lesson->ID;
					if ( function_exists( 'learndash_get_topic_list' ) ) {
						$topics = learndash_get_topic_list( $lesson->ID, $course_id );
						if ( ! empty( $topics ) ) {
							foreach ( $topics as $topic ) {
								$step_ids[] = $topic->ID;
							}
						}
					}
				}
			}
		}

		foreach ( $step_ids as $id ) {
			$post = get_post( $id );
			if ( $post ) {
				$steps[] = $post;
			}
		}

		return $steps;
	}
}
