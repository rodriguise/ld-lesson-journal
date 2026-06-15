<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LDJ_Journal_Page {

	public static function register() {
		self::add_rewrite_rules();
		add_filter( 'query_vars', array( __CLASS__, 'add_query_vars' ) );
		add_filter( 'template_include', array( __CLASS__, 'load_template' ) );
	}

	public static function add_rewrite_rules() {
		add_rewrite_rule( '^course-journal/?$', 'index.php?ldj_journal=1', 'top' );
	}

	public static function add_query_vars( $vars ) {
		$vars[] = 'ldj_journal';
		return $vars;
	}

	public static function get_url( int $course_id = 0 ): string {
		$url = home_url( '/course-journal/' );
		if ( $course_id > 0 ) {
			$url = add_query_arg( 'course_id', $course_id, $url );
		}
		return $url;
	}

	public static function load_template( $template ) {
		if ( ! get_query_var( 'ldj_journal' ) ) {
			return $template;
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_safe_redirect( wp_login_url( self::get_url( absint( $_GET['course_id'] ?? 0 ) ) ) );
			exit;
		}

		$course_id = absint( $_GET['course_id'] ?? 0 );

		if ( ! $course_id && function_exists( 'learndash_user_get_enrolled_courses' ) ) {
			$courses = learndash_user_get_enrolled_courses( $user_id );
			if ( ! empty( $courses ) ) {
				$course_id = $courses[0];
			}
		}

		if ( ! $course_id ) {
			$content = '<p>' . esc_html__( 'No course found. Please access this page from a lesson.', 'lesson-journal' ) . '</p>';
			$title   = __( 'My Journal', 'lesson-journal' );
		} else {
			$title   = sprintf( '%s — %s', get_the_title( $course_id ), __( 'Journal', 'lesson-journal' ) );
			$content = do_shortcode( sprintf(
				'[ldj_journal course_id="%d" show_filter="1" show_title="1" show_student="0" show_print="1" show_save="1" show_refresh="1"]',
				$course_id
			) );
		}

		global $post, $wp_query;

		$post = new WP_Post( (object) array(
			'ID'             => -1,
			'post_title'     => $title,
			'post_content'   => $content,
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'post_name'      => 'course-journal',
			'comment_status' => 'closed',
			'ping_status'    => 'closed',
			'filter'         => 'raw',
		) );

		$wp_query->post        = $post;
		$wp_query->posts       = array( $post );
		$wp_query->is_page     = true;
		$wp_query->is_singular = true;
		$wp_query->found_posts = 1;
		$wp_query->post_count  = 1;
		$wp_query->is_404      = false;

		add_filter( 'the_content', function ( $c ) use ( $content ) {
			if ( ! in_the_loop() || ! is_main_query() ) {
				return $c;
			}
			return $content;
		}, 1 );

		return get_page_template();
	}
}
