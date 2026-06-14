<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-ldj-db.php';

LDJ_DB::drop_tables();

$prompts = get_posts( array(
	'post_type'      => 'ldj_prompt',
	'post_status'    => 'any',
	'posts_per_page' => -1,
	'fields'         => 'ids',
) );

foreach ( $prompts as $prompt_id ) {
	wp_delete_post( $prompt_id, true );
}

delete_option( 'ldj_db_version' );
