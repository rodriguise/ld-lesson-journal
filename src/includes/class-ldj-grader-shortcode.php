<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LDJ_Grader_Shortcode {

	private static $enqueued = false;

	public static function register() {
		add_shortcode( 'ldj_grader', array( __CLASS__, 'render' ) );
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
			'ldj-grader',
			LESSON_JOURNAL_URL . 'assets/js/ldj-admin-grade.js',
			array( 'jquery' ),
			LESSON_JOURNAL_VERSION,
			true
		);

		wp_localize_script( 'ldj-grader', 'ldjGrade', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'ldj_grade_nonce' ),
			'i18n'    => array(
				'saving'  => __( 'Saving…', 'lesson-journal' ),
				'saved'   => __( 'Grade saved.', 'lesson-journal' ),
				'cleared' => __( 'Grade cleared.', 'lesson-journal' ),
				'error'   => __( 'An error occurred.', 'lesson-journal' ),
			),
		) );
	}

	public static function render( $atts ) {
		if ( ! is_user_logged_in() ) {
			return '';
		}

		if ( ! self::can_grade() ) {
			return '';
		}

		self::enqueue_assets();

		$course_id = absint( $_GET['ldj_course'] ?? 0 );
		$lesson_id = absint( $_GET['ldj_lesson'] ?? 0 );
		$student_id = absint( $_GET['ldj_student'] ?? 0 );

		if ( $course_id && ! self::can_access_course( $course_id ) ) {
			$course_id = 0;
			$lesson_id = 0;
			$student_id = 0;
		}

		ob_start();
		echo '<div class="ldj-grader">';
		echo '<h2 class="ldj-grader-title">' . esc_html__( 'Journal Grader', 'lesson-journal' ) . '</h2>';

		self::render_selectors( $course_id, $lesson_id, $student_id );

		if ( $course_id && $lesson_id && $student_id ) {
			self::render_grading_view( $student_id, $lesson_id );
		}

		echo '</div>';
		return ob_get_clean();
	}

	private static function can_grade(): bool {
		return current_user_can( 'manage_options' ) || current_user_can( 'edit_others_posts' );
	}

	private static function can_access_course( int $course_id ): bool {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		$user_id = get_current_user_id();
		$course  = get_post( $course_id );

		if ( ! $course || $course->post_type !== 'sfwd-courses' ) {
			return false;
		}

		if ( (int) $course->post_author === $user_id ) {
			return true;
		}

		$shared = get_post_meta( $course_id, 'ir_shared_instructor_ids', true );
		if ( is_array( $shared ) && in_array( $user_id, array_map( 'intval', $shared ), true ) ) {
			return true;
		}

		return false;
	}

	private static function get_allowed_courses(): array {
		if ( current_user_can( 'manage_options' ) ) {
			return get_posts( array(
				'post_type'      => 'sfwd-courses',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			) );
		}

		$user_id = get_current_user_id();

		$authored = get_posts( array(
			'post_type'      => 'sfwd-courses',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'author'         => $user_id,
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );

		$all_with_sharing = get_posts( array(
			'post_type'      => 'sfwd-courses',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_key'       => 'ir_shared_instructor_ids',
			'meta_compare'   => 'EXISTS',
		) );

		$shared = array();
		foreach ( $all_with_sharing as $course ) {
			$ids = get_post_meta( $course->ID, 'ir_shared_instructor_ids', true );
			if ( is_array( $ids ) && in_array( $user_id, array_map( 'intval', $ids ), true ) ) {
				$shared[] = $course;
			}
		}

		$seen   = array();
		$result = array();
		foreach ( array_merge( $authored, $shared ) as $course ) {
			if ( ! isset( $seen[ $course->ID ] ) ) {
				$seen[ $course->ID ] = true;
				$result[] = $course;
			}
		}

		usort( $result, function ( $a, $b ) {
			return strcasecmp( $a->post_title, $b->post_title );
		} );

		return $result;
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

		if ( empty( $step_ids ) ) {
			return array();
		}

		global $wpdb;
		$placeholders = implode( ',', array_fill( 0, count( $step_ids ), '%d' ) );
		$args         = array_merge( array( LDJ_DB::table_name() ), $step_ids );

		$lesson_ids_with_entries = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT lesson_id FROM %i WHERE lesson_id IN ({$placeholders})",
				$args
			)
		);

		if ( empty( $lesson_ids_with_entries ) ) {
			return array();
		}

		foreach ( $lesson_ids_with_entries as $lid ) {
			$post = get_post( $lid );
			if ( $post ) {
				$steps[] = $post;
			}
		}

		usort( $steps, function ( $a, $b ) {
			return $a->menu_order - $b->menu_order;
		} );

		return $steps;
	}

	private static function get_lesson_students( int $lesson_id ): array {
		global $wpdb;

		$user_ids = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT DISTINCT user_id FROM %i WHERE lesson_id = %d ORDER BY user_id',
				LDJ_DB::table_name(),
				$lesson_id
			)
		);

		$users = array();
		foreach ( $user_ids as $uid ) {
			$user = get_userdata( $uid );
			if ( $user ) {
				$users[] = $user;
			}
		}

		usort( $users, function ( $a, $b ) {
			return strcasecmp( $a->display_name, $b->display_name );
		} );

		return $users;
	}

	private static function render_selectors( int $course_id, int $lesson_id, int $student_id ) {
		$base_url = remove_query_arg( array( 'ldj_course', 'ldj_lesson', 'ldj_student' ) );
		$courses  = self::get_allowed_courses();

		echo '<div class="ldj-grader-selectors">';

		echo '<div class="ldj-grader-selector">';
		echo '<label for="ldj-grader-course">' . esc_html__( 'Course', 'lesson-journal' ) . '</label>';
		echo '<select id="ldj-grader-course" onchange="if(this.value){location.href=this.value}">';
		echo '<option value="">' . esc_html__( '— Select Course —', 'lesson-journal' ) . '</option>';
		foreach ( $courses as $course ) {
			$url      = add_query_arg( 'ldj_course', $course->ID, $base_url );
			$selected = $course_id === $course->ID ? ' selected' : '';
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $url ),
				$selected,
				esc_html( $course->post_title )
			);
		}
		echo '</select>';
		echo '</div>';

		if ( $course_id ) {
			$lessons = self::get_course_lessons( $course_id );

			echo '<div class="ldj-grader-selector">';
			echo '<label for="ldj-grader-lesson">' . esc_html__( 'Lesson / Topic', 'lesson-journal' ) . '</label>';
			echo '<select id="ldj-grader-lesson" onchange="if(this.value){location.href=this.value}">';
			echo '<option value="">' . esc_html__( '— Select Lesson —', 'lesson-journal' ) . '</option>';
			foreach ( $lessons as $lesson ) {
				$url      = add_query_arg( array( 'ldj_course' => $course_id, 'ldj_lesson' => $lesson->ID ), $base_url );
				$type     = $lesson->post_type === 'sfwd-topic' ? __( 'Topic', 'lesson-journal' ) : __( 'Lesson', 'lesson-journal' );
				$selected = $lesson_id === $lesson->ID ? ' selected' : '';
				printf(
					'<option value="%s"%s>%s (%s)</option>',
					esc_attr( $url ),
					$selected,
					esc_html( $lesson->post_title ),
					esc_html( $type )
				);
			}
			echo '</select>';
			echo '</div>';
		}

		if ( $course_id && $lesson_id ) {
			$students = self::get_lesson_students( $lesson_id );

			echo '<div class="ldj-grader-selector">';
			echo '<label for="ldj-grader-student">' . esc_html__( 'Student', 'lesson-journal' ) . '</label>';
			echo '<select id="ldj-grader-student" onchange="if(this.value){location.href=this.value}">';
			echo '<option value="">' . esc_html__( '— Select Student —', 'lesson-journal' ) . '</option>';
			foreach ( $students as $user ) {
				$url      = add_query_arg( array( 'ldj_course' => $course_id, 'ldj_lesson' => $lesson_id, 'ldj_student' => $user->ID ), $base_url );
				$selected = $student_id === $user->ID ? ' selected' : '';
				printf(
					'<option value="%s"%s>%s</option>',
					esc_attr( $url ),
					$selected,
					esc_html( $user->display_name )
				);
			}
			echo '</select>';
			echo '</div>';
		}

		echo '</div>';
	}

	private static function render_grading_view( int $student_id, int $lesson_id ) {
		$user   = get_userdata( $student_id );
		$lesson = get_post( $lesson_id );

		if ( ! $user || ! $lesson ) {
			echo '<p class="ldj-grader-error">' . esc_html__( 'Student or lesson not found.', 'lesson-journal' ) . '</p>';
			return;
		}

		$entries = LDJ_Entry::get_by_user_and_lesson( $student_id, $lesson_id );

		$entries = array_values( array_filter( $entries, function ( $entry ) {
			return ! (bool) get_post_meta( $entry->prompt_id, '_ldj_private', true );
		} ) );

		$type_label = get_post_type( $lesson->ID ) === 'sfwd-topic'
			? __( 'Topic', 'lesson-journal' )
			: __( 'Lesson', 'lesson-journal' );

		echo '<div class="ldj-grader-header">';
		printf(
			'<h3>%s: %s</h3>',
			esc_html( $type_label ),
			esc_html( $lesson->post_title )
		);
		printf(
			'<p class="ldj-grader-student-name">%s: <strong>%s</strong></p>',
			esc_html__( 'Student', 'lesson-journal' ),
			esc_html( $user->display_name )
		);
		echo '</div>';

		if ( empty( $entries ) ) {
			echo '<p class="ldj-grader-empty">' . esc_html__( 'No entries found for this student and lesson.', 'lesson-journal' ) . '</p>';
			return;
		}

		$graded_entries = array_filter( $entries, function ( $e ) {
			return (bool) get_post_meta( $e->prompt_id, '_ldj_graded', true );
		} );

		$summary = LDJ_Entry::get_lesson_grade_summary( $student_id, $lesson_id );
		if ( $summary['total'] > 0 ) {
			echo '<div class="ldj-grader-summary">';
			printf(
				'<strong>%s:</strong> %d %s %d',
				esc_html__( 'Graded', 'lesson-journal' ),
				$summary['total'],
				esc_html__( 'of', 'lesson-journal' ),
				count( $graded_entries )
			);
			if ( $summary['outstanding'] > 0 || $summary['satisfactory'] > 0 || $summary['insufficient'] > 0 ) {
				printf(
					' &mdash; %d %s, %d %s, %d %s',
					$summary['outstanding'],
					esc_html__( 'outstanding', 'lesson-journal' ),
					$summary['satisfactory'],
					esc_html__( 'satisfactory', 'lesson-journal' ),
					$summary['insufficient'],
					esc_html__( 'insufficient', 'lesson-journal' )
				);
			}
			if ( $summary['percentage'] !== null ) {
				printf(
					' &mdash; %s: %s%%',
					esc_html__( 'Score', 'lesson-journal' ),
					esc_html( $summary['percentage'] )
				);
			}
			echo '</div>';
		}

		echo '<div class="ldj-grader-entries">';

		foreach ( $entries as $entry ) {
			$prompt    = get_post( $entry->prompt_id );
			$required  = $prompt ? (bool) get_post_meta( $prompt->ID, '_ldj_required', true ) : false;
			$is_graded = $prompt ? (bool) get_post_meta( $prompt->ID, '_ldj_graded', true ) : false;

			echo '<div class="ldj-grade-entry" data-entry-id="' . esc_attr( $entry->id ) . '">';

			echo '<div class="ldj-grade-entry-header">';
			printf(
				'<h4>%s%s%s</h4>',
				esc_html( $prompt ? $prompt->post_title : '#' . $entry->prompt_id ),
				$required ? ' <span class="ldj-required-badge">' . esc_html__( 'Required', 'lesson-journal' ) . '</span>' : '',
				$is_graded ? '' : ' <span class="ldj-badge ldj-badge-ungraded">' . esc_html__( 'Not Graded', 'lesson-journal' ) . '</span>'
			);
			echo '<span class="ldj-grade-date">' . esc_html(
				date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $entry->updated_at ) )
			) . '</span>';
			echo '</div>';

			if ( $prompt ) {
				echo '<div class="ldj-collapsible">';
				echo '<button type="button" class="ldj-collapse-toggle ldj-collapsed">' . esc_html__( 'Question', 'lesson-journal' ) . '</button>';
				echo '<div class="ldj-collapsible-body" style="display:none">';
				echo '<div class="ldj-grade-question">' . wp_kses_post( wpautop( do_blocks( $prompt->post_content ) ) ) . '</div>';
				echo '</div>';
				echo '</div>';

				if ( $is_graded ) {
					$rubric = get_post_meta( $prompt->ID, '_ldj_rubric', true );
					if ( $rubric ) {
						echo '<div class="ldj-collapsible">';
						echo '<button type="button" class="ldj-collapse-toggle ldj-collapsed">' . esc_html__( 'Rubric', 'lesson-journal' ) . '</button>';
						echo '<div class="ldj-collapsible-body" style="display:none">';
						echo '<div class="ldj-grade-rubric">' . wp_kses_post( wpautop( $rubric ) ) . '</div>';
						echo '</div>';
						echo '</div>';
					}
				}
			}

			echo '<div class="ldj-grade-answer">';
			echo '<strong>' . esc_html__( 'Student Response:', 'lesson-journal' ) . '</strong>';
			echo '<div class="ldj-grade-answer-text">' . wp_kses_post( nl2br( esc_html( $entry->entry_text ) ) ) . '</div>';
			echo '</div>';

			echo '<div class="ldj-grade-controls">';

			if ( $is_graded ) {
				echo '<div class="ldj-grade-current">';
				if ( $entry->grade_status ) {
					$grader = $entry->graded_by ? get_userdata( $entry->graded_by ) : null;

					switch ( $entry->grade_status ) {
						case 'outstanding':
							$badge_class = 'ldj-badge-outstanding';
							$badge_text  = esc_html__( 'Outstanding', 'lesson-journal' );
							break;
						case 'satisfactory':
						case 'pass':
							$badge_class = 'ldj-badge-satisfactory';
							$badge_text  = esc_html__( 'Satisfactory', 'lesson-journal' );
							break;
						case 'insufficient':
						case 'redo':
						case 'fail':
							$badge_class = 'ldj-badge-redo';
							$badge_text  = esc_html__( 'Insufficient', 'lesson-journal' );
							break;
						default:
							$badge_class = 'ldj-badge-ungraded';
							$badge_text  = esc_html__( 'Ungraded', 'lesson-journal' );
							break;
					}

					printf( '<span class="ldj-badge %s">%s</span>', esc_attr( $badge_class ), $badge_text );

					if ( ! empty( $entry->reopened ) ) {
						echo ' <span class="ldj-badge ldj-badge-reopened">' . esc_html__( 'Re-opened', 'lesson-journal' ) . '</span>';
					}

					if ( $entry->grade_score !== null ) {
						$prompt_value = (int) get_post_meta( $entry->prompt_id, '_ldj_prompt_value', true ) ?: 10;
						printf(
							' <span class="ldj-grade-score">%d / %d</span>',
							(int) $entry->grade_score,
							$prompt_value
						);
					}

					if ( $grader ) {
						printf(
							' <span class="ldj-grade-by">%s %s</span>',
							esc_html__( 'by', 'lesson-journal' ),
							esc_html( $grader->display_name )
						);
					}
				} else {
					echo '<span class="ldj-badge ldj-badge-ungraded">' . esc_html__( 'Awaiting grade', 'lesson-journal' ) . '</span>';
				}
				echo '</div>';

				$prompt_value = (int) get_post_meta( $entry->prompt_id, '_ldj_prompt_value', true ) ?: 10;

				echo '<div class="ldj-grade-actions">';
				echo '<div class="ldj-grade-score-form">';
				echo '<span class="ldj-score-label">' . esc_html__( 'Score:', 'lesson-journal' ) . '</span> ';
				printf(
					'<input type="number" class="ldj-score-input" placeholder="%s" step="1" min="0" max="%d" value="%s">',
					esc_attr__( 'Score', 'lesson-journal' ),
					$prompt_value,
					$entry->grade_score !== null ? esc_attr( (int) $entry->grade_score ) : ''
				);
				printf( ' / <span class="ldj-prompt-value">%d</span>', $prompt_value );
				echo ' <button type="button" class="ldj-grader-btn ldj-grade-btn" data-grade-type="score">' . esc_html__( 'Set Score', 'lesson-journal' ) . '</button>';
				if ( $entry->grade_status ) {
					echo ' <button type="button" class="ldj-grader-btn ldj-grade-btn ldj-grade-clear" data-grade-type="clear">' . esc_html__( 'Clear Grade', 'lesson-journal' ) . '</button>';
					if ( empty( $entry->reopened ) ) {
						echo ' <button type="button" class="ldj-grader-btn ldj-reopen-btn">' . esc_html__( 'Re-Open', 'lesson-journal' ) . '</button>';
					}
				}
				echo '</div>';

				echo '</div>';
			}

			echo '<div class="ldj-comment-section">';
			echo '<label class="ldj-comment-label">' . esc_html__( 'Instructor Comment:', 'lesson-journal' ) . '</label>';
			printf(
				'<textarea class="ldj-comment-input" rows="2" placeholder="%s">%s</textarea>',
				esc_attr__( 'Add a comment for the student…', 'lesson-journal' ),
				esc_textarea( $entry->instructor_comment ?? '' )
			);
			echo '<button type="button" class="ldj-grader-btn ldj-comment-save-btn">' . esc_html__( 'Save Comment', 'lesson-journal' ) . '</button>';
			echo '</div>';

			echo '<div class="ldj-grade-feedback" aria-live="polite"></div>';
			echo '</div>';

			echo '</div>';
		}

		echo '</div>';
	}
}
