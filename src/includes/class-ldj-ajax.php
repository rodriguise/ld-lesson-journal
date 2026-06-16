<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LDJ_Ajax {

	public static function register() {
		add_action( 'wp_ajax_ldj_save_group', array( __CLASS__, 'save_group' ) );
		add_action( 'wp_ajax_ldj_delete_entry', array( __CLASS__, 'delete_entry' ) );
		add_action( 'wp_ajax_ldj_grade_entry', array( __CLASS__, 'grade_entry' ) );
	}

	public static function save_group() {
		check_ajax_referer( 'ldj_entry_nonce', 'nonce' );

		$user_id     = get_current_user_id();
		$lesson_id   = absint( $_POST['lesson_id'] ?? 0 );
		$entries     = $_POST['entries'] ?? array();
		$group_title = sanitize_text_field( $_POST['group_title'] ?? '' );

		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'lesson-journal' ) ) );
		}

		if ( ! $lesson_id || ! in_array( get_post_type( $lesson_id ), array( 'sfwd-lessons', 'sfwd-topic' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid lesson or topic.', 'lesson-journal' ) ) );
		}

		if ( ! is_array( $entries ) || empty( $entries ) ) {
			wp_send_json_error( array( 'message' => __( 'No entries to save.', 'lesson-journal' ) ) );
		}

		$errors = array();

		foreach ( $entries as &$entry ) {
			$entry['prompt_id']  = absint( $entry['prompt_id'] ?? 0 );
			$entry['entry_text'] = sanitize_textarea_field( $entry['entry_text'] ?? '' );

			if ( ! $entry['prompt_id'] || get_post_type( $entry['prompt_id'] ) !== 'ldj_prompt' ) {
				$errors[] = __( 'Invalid prompt ID.', 'lesson-journal' );
				continue;
			}

			$required  = (bool) get_post_meta( $entry['prompt_id'], '_ldj_required', true );
			$min_chars = (int) get_post_meta( $entry['prompt_id'], '_ldj_min_chars', true );
			$max_chars = (int) get_post_meta( $entry['prompt_id'], '_ldj_max_chars', true );

			if ( $required && $min_chars < 1 ) {
				$min_chars = 1;
			}

			if ( $min_chars > 0 && mb_strlen( $entry['entry_text'] ) < $min_chars ) {
				$errors[] = sprintf(
					__( '"%1$s" must be at least %2$d characters.', 'lesson-journal' ),
					get_the_title( $entry['prompt_id'] ),
					$min_chars
				);
			}

			if ( $max_chars > 0 && mb_strlen( $entry['entry_text'] ) > $max_chars ) {
				$errors[] = sprintf(
					__( '"%1$s" exceeds the %2$d character limit.', 'lesson-journal' ),
					get_the_title( $entry['prompt_id'] ),
					$max_chars
				);
			}
		}
		unset( $entry );

		if ( ! empty( $errors ) ) {
			wp_send_json_error( array( 'message' => implode( ' ', $errors ) ) );
		}

		$saved = LDJ_Entry::save_many( $entries, $user_id, $lesson_id, $group_title );

		wp_send_json_success( array(
			'message' => __( 'Journal entries saved.', 'lesson-journal' ),
			'saved'   => $saved,
		) );
	}

	public static function grade_entry() {
		check_ajax_referer( 'ldj_grade_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_others_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'lesson-journal' ) ) );
		}

		$entry_id     = absint( $_POST['entry_id'] ?? 0 );
		$grade_type   = sanitize_text_field( $_POST['grade_type'] ?? '' );
		$grade_status = sanitize_text_field( $_POST['grade_status'] ?? '' );
		$grade_score  = isset( $_POST['grade_score'] ) && $_POST['grade_score'] !== '' ? (float) $_POST['grade_score'] : null;
		$grade_max    = isset( $_POST['grade_max'] ) && $_POST['grade_max'] !== '' ? (float) $_POST['grade_max'] : null;

		if ( ! $entry_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid entry.', 'lesson-journal' ) ) );
		}

		$entry = LDJ_Entry::get_by_id( $entry_id );

		if ( ! $entry ) {
			wp_send_json_error( array( 'message' => __( 'Entry not found.', 'lesson-journal' ) ) );
		}

		if ( $grade_type === 'clear' ) {
			LDJ_Entry::clear_grade( $entry_id );
			wp_send_json_success( array( 'message' => __( 'Grade cleared.', 'lesson-journal' ) ) );
		}

		if ( $grade_type === 'pass_fail' ) {
			if ( ! in_array( $grade_status, array( 'pass', 'fail' ), true ) ) {
				wp_send_json_error( array( 'message' => __( 'Invalid grade status.', 'lesson-journal' ) ) );
			}
			LDJ_Entry::grade( $entry_id, $grade_status );
		} elseif ( $grade_type === 'score' ) {
			if ( $grade_score === null || $grade_max === null || $grade_max <= 0 ) {
				wp_send_json_error( array( 'message' => __( 'Invalid score values.', 'lesson-journal' ) ) );
			}
			$status = $grade_score >= ( $grade_max * 0.5 ) ? 'pass' : 'fail';
			LDJ_Entry::grade( $entry_id, $status, $grade_score, $grade_max );
		} else {
			wp_send_json_error( array( 'message' => __( 'Invalid grade type.', 'lesson-journal' ) ) );
		}

		$updated_entry = LDJ_Entry::get_by_id( $entry_id );
		do_action( 'ldj_entry_graded', $entry_id, $updated_entry );

		wp_send_json_success( array( 'message' => __( 'Grade saved.', 'lesson-journal' ) ) );
	}

	public static function delete_entry() {
		check_ajax_referer( 'ldj_entry_nonce', 'nonce' );

		$user_id   = get_current_user_id();
		$prompt_id = absint( $_POST['prompt_id'] ?? 0 );
		$lesson_id = absint( $_POST['lesson_id'] ?? 0 );

		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'lesson-journal' ) ) );
		}

		$existing = LDJ_Entry::get( $prompt_id, $user_id, $lesson_id );

		if ( ! $existing ) {
			wp_send_json_error( array( 'message' => __( 'Entry not found.', 'lesson-journal' ) ) );
		}

		LDJ_Entry::delete( $prompt_id, $user_id, $lesson_id );

		wp_send_json_success( array(
			'message' => __( 'Entry deleted.', 'lesson-journal' ),
		) );
	}
}
