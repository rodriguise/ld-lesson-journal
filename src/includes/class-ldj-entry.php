<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LDJ_Entry {

	public static function get( int $prompt_id, int $user_id, int $lesson_id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE prompt_id = %d AND user_id = %d AND lesson_id = %d',
				LDJ_DB::table_name(),
				$prompt_id,
				$user_id,
				$lesson_id
			)
		);
	}

	public static function get_many( array $prompt_ids, int $user_id, int $lesson_id ): array {
		global $wpdb;

		if ( empty( $prompt_ids ) ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $prompt_ids ), '%d' ) );
		$args         = array_merge( array( LDJ_DB::table_name() ), $prompt_ids, array( $user_id, $lesson_id ) );

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE prompt_id IN ({$placeholders}) AND user_id = %d AND lesson_id = %d",
				$args
			)
		);

		$keyed = array();
		foreach ( $results as $row ) {
			$keyed[ $row->prompt_id ] = $row;
		}

		return $keyed;
	}

	public static function upsert( int $prompt_id, int $user_id, int $lesson_id, string $text ) {
		global $wpdb;

		$existing = self::get( $prompt_id, $user_id, $lesson_id );

		if ( $existing ) {
			$wpdb->update(
				LDJ_DB::table_name(),
				array(
					'entry_text' => $text,
					'updated_at' => current_time( 'mysql', true ),
				),
				array( 'id' => $existing->id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
			return (int) $existing->id;
		}

		$wpdb->insert(
			LDJ_DB::table_name(),
			array(
				'prompt_id'  => $prompt_id,
				'user_id'    => $user_id,
				'lesson_id'  => $lesson_id,
				'entry_text' => $text,
				'created_at' => current_time( 'mysql', true ),
				'updated_at' => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	public static function save_many( array $entries, int $user_id, int $lesson_id ): array {
		$saved = array();

		foreach ( $entries as $entry ) {
			$prompt_id = absint( $entry['prompt_id'] );
			$text      = sanitize_textarea_field( $entry['entry_text'] );

			if ( $prompt_id > 0 ) {
				$saved[ $prompt_id ] = self::upsert( $prompt_id, $user_id, $lesson_id, $text );
			}
		}

		return $saved;
	}

	public static function delete( int $prompt_id, int $user_id, int $lesson_id ): bool {
		global $wpdb;

		$deleted = $wpdb->delete(
			LDJ_DB::table_name(),
			array(
				'prompt_id' => $prompt_id,
				'user_id'   => $user_id,
				'lesson_id' => $lesson_id,
			),
			array( '%d', '%d', '%d' )
		);

		return $deleted > 0;
	}

	public static function get_by_user_and_course( int $user_id, int $course_id ): array {
		global $wpdb;

		$step_ids = array();

		if ( function_exists( 'learndash_course_get_steps_by_type' ) ) {
			$step_ids = array_merge(
				learndash_course_get_steps_by_type( $course_id, 'sfwd-lessons' ),
				learndash_course_get_steps_by_type( $course_id, 'sfwd-topic' )
			);
		}

		if ( empty( $step_ids ) && function_exists( 'learndash_get_lesson_list' ) ) {
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

		if ( empty( $step_ids ) ) {
			$posts = get_posts( array(
				'post_type'      => array( 'sfwd-lessons', 'sfwd-topic' ),
				'meta_key'       => 'course_id',
				'meta_value'     => $course_id,
				'post_status'    => 'publish',
				'posts_per_page' => 500,
				'fields'         => 'ids',
			) );
			$step_ids = $posts;
		}

		if ( empty( $step_ids ) ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $step_ids ), '%d' ) );
		$args         = array_merge( array( LDJ_DB::table_name(), $user_id ), $step_ids );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE user_id = %d AND lesson_id IN ({$placeholders}) ORDER BY lesson_id ASC, prompt_id ASC",
				$args
			)
		);
	}

	public static function get_by_user_and_lesson( int $user_id, int $lesson_id ): array {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE user_id = %d AND lesson_id = %d ORDER BY prompt_id ASC',
				LDJ_DB::table_name(),
				$user_id,
				$lesson_id
			)
		);
	}

	public static function get_all_by_user( int $user_id ): array {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE user_id = %d ORDER BY lesson_id ASC, prompt_id ASC',
				LDJ_DB::table_name(),
				$user_id
			)
		);
	}

	public static function has_completed_prompts( array $prompt_ids, int $user_id, int $lesson_id ): bool {
		if ( empty( $prompt_ids ) ) {
			return true;
		}

		$entries = self::get_many( $prompt_ids, $user_id, $lesson_id );

		foreach ( $prompt_ids as $pid ) {
			if ( ! isset( $entries[ $pid ] ) || trim( $entries[ $pid ]->entry_text ) === '' ) {
				return false;
			}
		}

		return true;
	}

	public static function get_entries_for_list_table( array $args ): array {
		global $wpdb;

		$defaults = array(
			'user_id'   => 0,
			'lesson_id' => 0,
			'prompt_id' => 0,
			'orderby'   => 'updated_at',
			'order'     => 'DESC',
			'per_page'  => 20,
			'offset'    => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		$where  = array( '1=1' );
		$values = array( LDJ_DB::table_name() );

		if ( $args['user_id'] > 0 ) {
			$where[]  = 'user_id = %d';
			$values[] = $args['user_id'];
		}

		if ( $args['lesson_id'] > 0 ) {
			$where[]  = 'lesson_id = %d';
			$values[] = $args['lesson_id'];
		}

		if ( $args['prompt_id'] > 0 ) {
			$where[]  = 'prompt_id = %d';
			$values[] = $args['prompt_id'];
		}

		$allowed_orderby = array( 'user_id', 'prompt_id', 'lesson_id', 'created_at', 'updated_at' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'updated_at';
		$order           = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		$where_clause = implode( ' AND ', $where );

		$total = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM %i WHERE {$where_clause}",
				$values
			)
		);

		$values[] = $args['per_page'];
		$values[] = $args['offset'];

		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
				$values
			)
		);

		return array(
			'items' => $items,
			'total' => (int) $total,
		);
	}

	public static function bulk_delete( array $ids ): int {
		global $wpdb;

		if ( empty( $ids ) ) {
			return 0;
		}

		$ids          = array_map( 'absint', $ids );
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$args         = array_merge( array( LDJ_DB::table_name() ), $ids );

		return (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM %i WHERE id IN ({$placeholders})",
				$args
			)
		);
	}
}
