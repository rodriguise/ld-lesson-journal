<?php
/**
 * Plugin Name:       Lesson Journal
 * Plugin URI:        https://github.com/rodriguise/ld-lesson-journal
 * Description:       A journaling companion for LearnDash lessons.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Rob Rodriguez
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       lesson-journal
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LESSON_JOURNAL_VERSION', '0.1.0' );
define( 'LESSON_JOURNAL_FILE', __FILE__ );
define( 'LESSON_JOURNAL_PATH', plugin_dir_path( __FILE__ ) );
define( 'LESSON_JOURNAL_URL', plugin_dir_url( __FILE__ ) );

require_once LESSON_JOURNAL_PATH . 'includes/class-ldj-db.php';
require_once LESSON_JOURNAL_PATH . 'includes/class-ldj-post-type.php';
require_once LESSON_JOURNAL_PATH . 'includes/class-ldj-entry.php';
require_once LESSON_JOURNAL_PATH . 'includes/class-ldj-ajax.php';
require_once LESSON_JOURNAL_PATH . 'includes/class-ldj-shortcode.php';
require_once LESSON_JOURNAL_PATH . 'includes/class-ldj-journal-shortcode.php';
require_once LESSON_JOURNAL_PATH . 'includes/class-ldj-completion.php';
require_once LESSON_JOURNAL_PATH . 'includes/class-ldj-admin-entries.php';
require_once LESSON_JOURNAL_PATH . 'includes/class-ldj-journal-page.php';

function lesson_journal_learndash_active() {
	return defined( 'LEARNDASH_VERSION' );
}

function lesson_journal_init() {
	load_plugin_textdomain(
		'lesson-journal',
		false,
		dirname( plugin_basename( LESSON_JOURNAL_FILE ) ) . '/languages'
	);

	if ( ! lesson_journal_learndash_active() ) {
		return;
	}

	LDJ_Post_Type::register();
	LDJ_Shortcode::register();
	LDJ_Journal_Shortcode::register();
	LDJ_Ajax::register();
	LDJ_Completion::register();
	LDJ_Journal_Page::register();

	if ( is_admin() ) {
		LDJ_Admin_Entries::register();
		LDJ_DB::maybe_upgrade();
	}
}
add_action( 'init', 'lesson_journal_init' );

function lesson_journal_editor_typography() {
	add_theme_support( 'custom-line-height' );
	if ( ! current_theme_supports( 'editor-font-sizes' ) ) {
		add_theme_support( 'editor-font-sizes', array(
			array( 'name' => __( 'Small', 'lesson-journal' ), 'slug' => 'small', 'size' => 13 ),
			array( 'name' => __( 'Normal', 'lesson-journal' ), 'slug' => 'normal', 'size' => 16 ),
			array( 'name' => __( 'Medium', 'lesson-journal' ), 'slug' => 'medium', 'size' => 20 ),
			array( 'name' => __( 'Large', 'lesson-journal' ), 'slug' => 'large', 'size' => 28 ),
			array( 'name' => __( 'Extra Large', 'lesson-journal' ), 'slug' => 'x-large', 'size' => 36 ),
		) );
	}
}
add_action( 'after_setup_theme', 'lesson_journal_editor_typography' );

function lesson_journal_register_blocks() {
	if ( ! lesson_journal_learndash_active() ) {
		return;
	}

	wp_register_style(
		'ldj-frontend',
		LESSON_JOURNAL_URL . 'assets/css/ldj-frontend.css',
		array(),
		LESSON_JOURNAL_VERSION
	);

	wp_register_script(
		'ldj-frontend',
		LESSON_JOURNAL_URL . 'assets/js/ldj-frontend.js',
		array(),
		LESSON_JOURNAL_VERSION,
		true
	);

	register_block_type( LESSON_JOURNAL_PATH . 'blocks/prompt-group' );
	register_block_type( LESSON_JOURNAL_PATH . 'blocks/prompt' );
	register_block_type( LESSON_JOURNAL_PATH . 'blocks/journal-view' );
}
add_action( 'init', 'lesson_journal_register_blocks' );

function lesson_journal_editor_assets() {
	wp_add_inline_script(
		'wp-block-editor',
		'window.ldjAdmin = ' . wp_json_encode( array( 'adminUrl' => admin_url() ) ) . ';'
	);
}
add_action( 'enqueue_block_editor_assets', 'lesson_journal_editor_assets' );

function lesson_journal_block_category( $categories ) {
	foreach ( $categories as $cat ) {
		if ( $cat['slug'] === 'learndash-blocks' ) {
			return $categories;
		}
	}

	$categories[] = array(
		'slug'  => 'learndash-blocks',
		'title' => __( 'LearnDash LMS Blocks', 'lesson-journal' ),
	);

	return $categories;
}
add_filter( 'block_categories_all', 'lesson_journal_block_category' );

function lesson_journal_rest_api() {
	register_rest_route( 'ldj/v1', '/courses', array(
		'methods'             => 'GET',
		'callback'            => 'lesson_journal_rest_courses',
		'permission_callback' => function () {
			return current_user_can( 'edit_posts' );
		},
	) );

	register_rest_route( 'ldj/v1', '/lessons', array(
		'methods'             => 'GET',
		'callback'            => 'lesson_journal_rest_lessons',
		'permission_callback' => function () {
			return current_user_can( 'edit_posts' );
		},
		'args'                => array(
			'course_id' => array(
				'required' => true,
				'type'     => 'integer',
			),
		),
	) );
}
add_action( 'rest_api_init', 'lesson_journal_rest_api' );

function lesson_journal_rest_courses() {
	$posts = get_posts( array(
		'post_type'      => 'sfwd-courses',
		'post_status'    => 'publish',
		'posts_per_page' => 200,
		'orderby'        => 'title',
		'order'          => 'ASC',
	) );

	$results = array();
	foreach ( $posts as $post ) {
		$results[] = array(
			'id'    => $post->ID,
			'title' => $post->post_title,
		);
	}

	return rest_ensure_response( $results );
}

function lesson_journal_rest_lessons( WP_REST_Request $request ) {
	$course_id = absint( $request->get_param( 'course_id' ) );
	$posts     = array();

	if ( function_exists( 'learndash_get_lesson_list' ) ) {
		$posts = learndash_get_lesson_list( $course_id );
	}

	if ( empty( $posts ) ) {
		$posts = get_posts( array(
			'post_type'      => 'sfwd-lessons',
			'meta_key'       => 'course_id',
			'meta_value'     => $course_id,
			'post_status'    => 'publish',
			'posts_per_page' => 200,
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
		) );
	}

	$results = array();
	foreach ( $posts as $post ) {
		if ( is_object( $post ) ) {
			$results[] = array(
				'id'    => $post->ID,
				'title' => $post->post_title,
			);
		}
	}

	return rest_ensure_response( $results );
}

function lesson_journal_dependency_notice() {
	if ( lesson_journal_learndash_active() ) {
		return;
	}

	$message = esc_html__(
		'Lesson Journal requires LearnDash to be installed and active.',
		'lesson-journal'
	);

	printf( '<div class="notice notice-warning"><p>%s</p></div>', $message );
}
add_action( 'admin_notices', 'lesson_journal_dependency_notice' );

function lesson_journal_activate() {
	require_once LESSON_JOURNAL_PATH . 'includes/class-ldj-db.php';
	LDJ_DB::create_tables();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'lesson_journal_activate' );

function lesson_journal_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'lesson_journal_deactivate' );
