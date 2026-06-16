<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LDJ_Admin_Grade {

	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu_page' ), 30 );
		add_action( 'admin_menu', array( __CLASS__, 'hide_submenu' ), 31 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	public static function add_menu_page() {
		add_submenu_page(
			'learndash-lms',
			__( 'Grade Journal', 'lesson-journal' ),
			__( 'Grade Journal', 'lesson-journal' ),
			'edit_others_posts',
			'ldj-grade',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function hide_submenu() {
		add_action( 'admin_head', function () {
			echo '<style>#adminmenu a[href="admin.php?page=ldj-grade"] { display: none !important; }</style>';
		} );
	}

	public static function enqueue_assets( $hook ) {
		if ( $hook !== 'learndash-lms_page_ldj-grade' ) {
			return;
		}

		wp_enqueue_style(
			'ldj-admin',
			LESSON_JOURNAL_URL . 'assets/css/ldj-admin.css',
			array(),
			LESSON_JOURNAL_VERSION
		);

		wp_enqueue_script(
			'ldj-admin-grade',
			LESSON_JOURNAL_URL . 'assets/js/ldj-admin-grade.js',
			array( 'jquery' ),
			LESSON_JOURNAL_VERSION,
			true
		);

		wp_localize_script( 'ldj-admin-grade', 'ldjGrade', array(
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

	public static function render_page() {
		$user_id   = absint( $_GET['user_id'] ?? 0 );
		$lesson_id = absint( $_GET['lesson_id'] ?? 0 );

		echo '<div class="wrap">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Student Journal', 'lesson-journal' ) . '</h1>';
		echo '<hr class="wp-header-end">';
		LDJ_Admin_Entries::output_tabs( 'grading' );

		if ( ! $user_id || ! $lesson_id ) {
			self::render_selector();
			echo '</div>';
			return;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Student not found.', 'lesson-journal' ) . '</p></div>';
			echo '</div>';
			return;
		}

		$lesson = get_post( $lesson_id );
		if ( ! $lesson || ! in_array( $lesson->post_type, array( 'sfwd-lessons', 'sfwd-topic' ), true ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Lesson not found.', 'lesson-journal' ) . '</p></div>';
			echo '</div>';
			return;
		}

		self::render_journal_view( $user, $lesson );
		echo '</div>';
	}

	private static function render_selector() {
		global $wpdb;

		$lesson_ids = $wpdb->get_col(
			$wpdb->prepare( 'SELECT DISTINCT lesson_id FROM %i ORDER BY lesson_id', LDJ_DB::table_name() )
		);

		$user_ids = $wpdb->get_col(
			$wpdb->prepare( 'SELECT DISTINCT user_id FROM %i ORDER BY user_id', LDJ_DB::table_name() )
		);

		$selected_lesson = absint( $_GET['lesson_id'] ?? 0 );
		$selected_user   = absint( $_GET['user_id'] ?? 0 );
		?>
		<form method="get" class="ldj-grade-selector">
			<input type="hidden" name="page" value="ldj-grade">
			<table class="form-table">
				<tr>
					<th scope="row"><label for="ldj-lesson"><?php esc_html_e( 'Lesson / Topic', 'lesson-journal' ); ?></label></th>
					<td>
						<select name="lesson_id" id="ldj-lesson">
							<option value=""><?php esc_html_e( '— Select —', 'lesson-journal' ); ?></option>
							<?php foreach ( $lesson_ids as $lid ) :
								$title = get_the_title( $lid );
								$type  = get_post_type( $lid );
								$label = $type === 'sfwd-topic' ? __( 'Topic', 'lesson-journal' ) : __( 'Lesson', 'lesson-journal' );
							?>
								<option value="<?php echo esc_attr( $lid ); ?>" <?php selected( $selected_lesson, (int) $lid ); ?>>
									<?php echo esc_html( $title ? "{$title} ({$label})" : "#{$lid}" ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ldj-user"><?php esc_html_e( 'Student', 'lesson-journal' ); ?></label></th>
					<td>
						<select name="user_id" id="ldj-user">
							<option value=""><?php esc_html_e( '— Select —', 'lesson-journal' ); ?></option>
							<?php foreach ( $user_ids as $uid ) :
								$user = get_userdata( $uid );
								if ( ! $user ) continue;
							?>
								<option value="<?php echo esc_attr( $uid ); ?>" <?php selected( $selected_user, (int) $uid ); ?>>
									<?php echo esc_html( $user->display_name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'View Journal', 'lesson-journal' ), 'primary', 'submit', true ); ?>
		</form>
		<?php
	}

	private static function render_journal_view( WP_User $user, WP_Post $lesson ) {
		$entries = LDJ_Entry::get_by_user_and_lesson( $user->ID, $lesson->ID );

		$entries = array_values( array_filter( $entries, function ( $entry ) {
			return ! (bool) get_post_meta( $entry->prompt_id, '_ldj_private', true );
		} ) );

		$type_label = get_post_type( $lesson->ID ) === 'sfwd-topic'
			? __( 'Topic', 'lesson-journal' )
			: __( 'Lesson', 'lesson-journal' );

		$back_url = admin_url( 'admin.php?page=ldj-grade' );
		echo '<p><a href="' . esc_url( $back_url ) . '">&larr; ' . esc_html__( 'Back to selector', 'lesson-journal' ) . '</a></p>';

		echo '<div class="ldj-grade-header">';
		printf(
			'<h2>%s: %s</h2>',
			esc_html( $type_label ),
			esc_html( $lesson->post_title )
		);
		printf(
			'<h3>%s: %s (%s)</h3>',
			esc_html__( 'Student', 'lesson-journal' ),
			esc_html( $user->display_name ),
			esc_html( $user->user_email )
		);
		echo '</div>';

		if ( empty( $entries ) ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'No entries found for this student and lesson.', 'lesson-journal' ) . '</p></div>';
			return;
		}

		$graded_entries = array_filter( $entries, function ( $e ) {
			return (bool) get_post_meta( $e->prompt_id, '_ldj_graded', true );
		} );

		$summary = LDJ_Entry::get_lesson_grade_summary( $user->ID, $lesson->ID );
		if ( $summary['total'] > 0 ) {
			echo '<div class="ldj-grade-summary">';
			printf(
				'<strong>%s:</strong> %d %s %d',
				esc_html__( 'Graded', 'lesson-journal' ),
				$summary['scored'],
				esc_html__( 'of', 'lesson-journal' ),
				count( $graded_entries )
			);
			printf(
				' &mdash; %s: %d &bull; %s: %d &bull; %s: %d',
				esc_html__( 'Outstanding', 'lesson-journal' ),
				$summary['outstanding'],
				esc_html__( 'Satisfactory', 'lesson-journal' ),
				$summary['satisfactory'],
				esc_html__( 'Insufficient', 'lesson-journal' ),
				$summary['insufficient']
			);
			if ( $summary['percentage'] !== null ) {
				printf(
					' &mdash; %s: %d / %d (%s%%)',
					esc_html__( 'Score', 'lesson-journal' ),
					$summary['sum'],
					$summary['max_sum'],
					esc_html( $summary['percentage'] )
				);
			}
			echo '</div>';
		}

		echo '<div class="ldj-grade-entries">';

		foreach ( $entries as $index => $entry ) {
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
				?>
				<div class="ldj-collapsible">
					<button type="button" class="ldj-collapse-toggle ldj-collapsed"><?php esc_html_e( 'Question', 'lesson-journal' ); ?></button>
					<div class="ldj-collapsible-body" style="display:none">
						<?php echo wp_kses_post( apply_filters( 'the_content', $prompt->post_content ) ); ?>
					</div>
				</div>
				<?php
				if ( $is_graded ) {
					$rubric = get_post_meta( $prompt->ID, '_ldj_rubric', true );
					if ( $rubric ) : ?>
					<div class="ldj-collapsible">
						<button type="button" class="ldj-collapse-toggle ldj-collapsed"><?php esc_html_e( 'Rubric', 'lesson-journal' ); ?></button>
						<div class="ldj-collapsible-body" style="display:none">
							<?php echo wp_kses_post( wpautop( $rubric ) ); ?>
						</div>
					</div>
					<?php endif;
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
							$badge_text  = __( 'Outstanding', 'lesson-journal' );
							break;
						case 'satisfactory':
						case 'pass':
							$badge_class = 'ldj-badge-satisfactory';
							$badge_text  = __( 'Satisfactory', 'lesson-journal' );
							break;
						case 'insufficient':
						case 'redo':
						case 'fail':
							$badge_class = 'ldj-badge-redo';
							$badge_text  = __( 'Insufficient', 'lesson-journal' );
							break;
						default:
							$badge_class = 'ldj-badge-ungraded';
							$badge_text  = __( 'Ungraded', 'lesson-journal' );
							break;
					}

					printf( '<span class="ldj-badge %s">%s</span>', esc_attr( $badge_class ), esc_html( $badge_text ) );

					if ( ! empty( $entry->reopened ) ) {
						echo ' <span class="ldj-badge ldj-badge-reopened">' . esc_html__( 'Re-opened', 'lesson-journal' ) . '</span>';
					}

					if ( $entry->grade_score !== null ) {
						$display_max = (int) get_post_meta( $entry->prompt_id, '_ldj_prompt_value', true ) ?: 10;
						printf( ' <span class="ldj-grade-score">%d / %d</span>',
							(int) $entry->grade_score,
							$display_max
						);
					}

					if ( $grader ) {
						printf( ' <span class="ldj-grade-by">%s %s</span>',
							esc_html__( 'by', 'lesson-journal' ),
							esc_html( $grader->display_name )
						);
					}
				} else {
					echo '<span class="ldj-badge ldj-badge-ungraded">' . esc_html__( 'Awaiting grade', 'lesson-journal' ) . '</span>';
				}
				echo '</div>';

				echo '<div class="ldj-grade-actions">';

				$prompt_value = (int) get_post_meta( $entry->prompt_id, '_ldj_prompt_value', true ) ?: 10;

				echo '<div class="ldj-grade-score-form">';
				printf(
					'<label>%s: </label>',
					esc_html__( 'Score', 'lesson-journal' )
				);
				printf(
					'<input type="number" class="ldj-score-input" placeholder="%s" step="1" min="0" max="%d" value="%s">',
					esc_attr__( 'Score', 'lesson-journal' ),
					$prompt_value,
					$entry->grade_score !== null ? esc_attr( (int) $entry->grade_score ) : ''
				);
				printf( ' / %d', $prompt_value );
				echo ' <button type="button" class="button ldj-grade-btn" data-grade-type="score">' . esc_html__( 'Set Score', 'lesson-journal' ) . '</button>';
				echo ' <button type="button" class="button ldj-grade-btn ldj-grade-clear" data-grade-type="clear">' . esc_html__( 'Clear Grade', 'lesson-journal' ) . '</button>';
				echo '</div>';

				if ( $entry->grade_status && empty( $entry->reopened ) ) {
					echo '<button type="button" class="button ldj-reopen-btn">' . esc_html__( 'Re-open for Editing', 'lesson-journal' ) . '</button>';
				}

				echo '</div>';
			}

			echo '<div class="ldj-comment-section">';
			echo '<label class="ldj-comment-label">' . esc_html__( 'Instructor Comment:', 'lesson-journal' ) . '</label>';
			printf(
				'<textarea class="ldj-comment-input" rows="2" placeholder="%s">%s</textarea>',
				esc_attr__( 'Add a comment for the student…', 'lesson-journal' ),
				esc_textarea( $entry->instructor_comment ?? '' )
			);
			echo '<button type="button" class="button ldj-comment-save-btn">' . esc_html__( 'Save Comment', 'lesson-journal' ) . '</button>';
			echo '</div>';

			echo '<div class="ldj-grade-feedback" aria-live="polite"></div>';
			echo '</div>';

			echo '</div>';
		}

		echo '</div>';
	}

	public static function get_grade_url( int $user_id, int $lesson_id ): string {
		return admin_url( sprintf( 'admin.php?page=ldj-grade&user_id=%d&lesson_id=%d', $user_id, $lesson_id ) );
	}
}
