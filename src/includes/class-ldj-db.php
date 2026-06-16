<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LDJ_DB {

	const VERSION = '1.6.0';

	public static function create_tables() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'ldj_entries';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			prompt_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			lesson_id bigint(20) unsigned NOT NULL,
			entry_text longtext NOT NULL,
			group_title varchar(255) NOT NULL DEFAULT '',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			grade_status varchar(20) DEFAULT NULL,
			grade_score decimal(5,2) DEFAULT NULL,
			grade_max decimal(5,2) DEFAULT NULL,
			graded_by bigint(20) unsigned DEFAULT NULL,
			graded_at datetime DEFAULT NULL,
			instructor_comment text DEFAULT NULL,
			reopened tinyint(1) NOT NULL DEFAULT 0,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY prompt_user_lesson (prompt_id, user_id, lesson_id),
			KEY user_id (user_id),
			KEY lesson_id (lesson_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'ldj_db_version', self::VERSION );
	}

	public static function maybe_upgrade() {
		$current = get_option( 'ldj_db_version', '0' );
		if ( $current === self::VERSION ) {
			return;
		}

		self::create_tables();

		if ( version_compare( $current, '1.6.0', '<' ) ) {
			self::migrate_redo_to_insufficient();
		}
	}

	private static function migrate_redo_to_insufficient() {
		global $wpdb;
		$wpdb->update(
			self::table_name(),
			array( 'grade_status' => 'insufficient' ),
			array( 'grade_status' => 'redo' ),
			array( '%s' ),
			array( '%s' )
		);
	}

	public static function drop_tables() {
		global $wpdb;
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}ldj_entries" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'ldj_entries';
	}
}
