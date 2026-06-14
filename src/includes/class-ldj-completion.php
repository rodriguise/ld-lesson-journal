<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LDJ_Completion {

	private static $cache = array();

	public static function register() {
		add_filter( 'learndash_mark_complete', array( __CLASS__, 'gate_mark_complete_button' ), 10, 2 );
		add_filter( 'learndash_process_mark_complete', array( __CLASS__, 'gate_process_complete' ), 10, 2 );
	}

	public static function gate_mark_complete_button( $mark_complete_html, $post ) {
		if ( empty( $mark_complete_html ) || ! $post ) {
			return $mark_complete_html;
		}

		$user_id    = get_current_user_id();
		$prompt_ids = self::get_required_prompt_ids( $post->ID );

		if ( empty( $prompt_ids ) || ! $user_id ) {
			return $mark_complete_html;
		}

		if ( LDJ_Entry::has_completed_prompts( $prompt_ids, $user_id, $post->ID ) ) {
			return $mark_complete_html;
		}

		$mark_complete_html = preg_replace(
			'/(class=["\'][^"\']*learndash_mark_complete_button[^"\']*["\'])/',
			'$1 disabled',
			$mark_complete_html
		);

		return $mark_complete_html;
	}

	public static function gate_process_complete( $process, $post ) {
		if ( ! $process || ! $post ) {
			return $process;
		}

		$user_id    = get_current_user_id();
		$prompt_ids = self::get_required_prompt_ids( $post->ID );

		if ( empty( $prompt_ids ) || ! $user_id ) {
			return $process;
		}

		if ( ! LDJ_Entry::has_completed_prompts( $prompt_ids, $user_id, $post->ID ) ) {
			return false;
		}

		return $process;
	}

	private static function get_required_prompt_ids( int $post_id ): array {
		if ( isset( self::$cache[ $post_id ] ) ) {
			return self::$cache[ $post_id ];
		}

		$post = get_post( $post_id );

		if ( ! $post ) {
			self::$cache[ $post_id ] = array();
			return array();
		}

		$prompt_ids = array();

		$prompt_ids = array_merge( $prompt_ids, self::extract_from_blocks( $post->post_content ) );
		$prompt_ids = array_merge( $prompt_ids, self::extract_from_shortcodes( $post->post_content ) );

		$prompt_ids = array_unique( array_filter( $prompt_ids ) );

		self::$cache[ $post_id ] = $prompt_ids;

		return $prompt_ids;
	}

	private static function extract_from_blocks( string $content ): array {
		if ( ! has_blocks( $content ) ) {
			return array();
		}

		$blocks     = parse_blocks( $content );
		$prompt_ids = array();

		foreach ( $blocks as $block ) {
			if ( $block['blockName'] !== 'ldj/prompt-group' ) {
				continue;
			}

			$required = ! empty( $block['attrs']['required'] );

			if ( ! $required ) {
				continue;
			}

			foreach ( $block['innerBlocks'] as $inner ) {
				if ( $inner['blockName'] === 'ldj/prompt' && ! empty( $inner['attrs']['promptId'] ) ) {
					$prompt_ids[] = absint( $inner['attrs']['promptId'] );
				}
			}
		}

		return $prompt_ids;
	}

	private static function extract_from_shortcodes( string $content ): array {
		$prompt_ids = array();

		if ( ! has_shortcode( $content, 'ldj_group' ) ) {
			return array();
		}

		if ( ! preg_match_all( '/\[ldj_group([^\]]*)\](.*?)\[\/ldj_group\]/s', $content, $groups ) ) {
			return array();
		}

		foreach ( $groups[0] as $i => $group_match ) {
			$group_atts = shortcode_parse_atts( $groups[1][ $i ] );
			$required   = isset( $group_atts['required'] ) && filter_var( $group_atts['required'], FILTER_VALIDATE_BOOLEAN );

			if ( ! $required ) {
				continue;
			}

			$inner = $groups[2][ $i ];

			if ( preg_match_all( '/\[ldj\s+[^\]]*id=["\']?(\d+)["\']?[^\]]*\]/', $inner, $ldj_matches ) ) {
				foreach ( $ldj_matches[1] as $id ) {
					$prompt_ids[] = absint( $id );
				}
			}
		}

		return $prompt_ids;
	}
}
