<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LDJ_DB {

	const VERSION = '1.0.0';

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
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
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
		if ( get_option( 'ldj_db_version' ) !== self::VERSION ) {
			self::create_tables();
		}
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
