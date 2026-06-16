<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LDJ_Completion {

	private static $cache     = array();
	private static $positions = array();

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
			return self::maybe_wrap_with_tooltip( $mark_complete_html, $post, array() );
		}

		if ( LDJ_Entry::has_completed_prompts( $prompt_ids, $user_id, $post->ID ) ) {
			return self::maybe_wrap_with_tooltip( $mark_complete_html, $post, array() );
		}

		$mark_complete_html = preg_replace(
			'/(class=["\'][^"\']*learndash_mark_complete_button[^"\']*["\'])/',
			'$1 disabled',
			$mark_complete_html
		);

		$incomplete = self::get_incomplete_prompts( $prompt_ids, $user_id, $post->ID );

		return self::wrap_with_tooltip( $mark_complete_html, $post, $incomplete );
	}

	private static function maybe_wrap_with_tooltip( string $html, $post, array $incomplete ): string {
		$items = self::build_tooltip_items( $post->ID, $incomplete );

		if ( empty( $items ) ) {
			return $html;
		}

		return self::render_tooltip_wrap( $html, $items );
	}

	private static function wrap_with_tooltip( string $html, $post, array $incomplete ): string {
		$items = self::build_tooltip_items( $post->ID, $incomplete );

		return self::render_tooltip_wrap( $html, $items );
	}

	private static function build_tooltip_items( int $post_id, array $incomplete ): array {
		$items = array();

		foreach ( $incomplete as $prompt_id ) {
			$description = get_post_meta( $prompt_id, '_ldj_description', true );
			if ( $description ) {
				$label = $description;
			} elseif ( isset( self::$positions[ $prompt_id ] ) ) {
				$label = sprintf( __( 'Prompt #%d', 'lesson-journal' ), self::$positions[ $prompt_id ] );
			} else {
				$label = get_the_title( $prompt_id ) ?: sprintf( __( 'Prompt #%d', 'lesson-journal' ), $prompt_id );
			}
			$items[] = '<li>' . sprintf(
				esc_html__( 'Complete journal entry: %s', 'lesson-journal' ),
				'<strong>' . esc_html( $label ) . '</strong>'
			) . '</li>';
		}

		if ( self::has_video_progression( $post_id ) ) {
			$items[] = '<li>' . esc_html__( 'Watch the video to completion', 'lesson-journal' ) . '</li>';
		}

		return $items;
	}

	private static function render_tooltip_wrap( string $button_html, array $items ): string {
		$tooltip  = '<div class="ldj-completion-tooltip" data-ldj-notice>';
		$tooltip .= '<p class="ldj-completion-tooltip__heading">' . esc_html__( 'To mark this lesson complete:', 'lesson-journal' ) . '</p>';
		$tooltip .= '<ul class="ldj-completion-tooltip__list">' . implode( '', $items ) . '</ul>';
		$tooltip .= '</div>';

		return '<div class="ldj-mark-complete-wrap">' . $button_html . $tooltip . '</div>';
	}

	private static function get_incomplete_prompts( array $prompt_ids, int $user_id, int $lesson_id ): array {
		$entries    = LDJ_Entry::get_many( $prompt_ids, $user_id, $lesson_id );
		$completed  = array();

		foreach ( $entries as $entry ) {
			if ( ! empty( trim( $entry->entry_text ) ) ) {
				$min_chars = (int) get_post_meta( $entry->prompt_id, '_ldj_min_chars', true );
				$required  = (bool) get_post_meta( $entry->prompt_id, '_ldj_required', true );
				if ( $required && $min_chars < 1 ) {
					$min_chars = 1;
				}
				if ( $min_chars > 0 && mb_strlen( $entry->entry_text ) < $min_chars ) {
					continue;
				}
				$completed[] = (int) $entry->prompt_id;
			}
		}

		return array_values( array_diff( $prompt_ids, $completed ) );
	}

	private static function has_video_progression( int $post_id ): bool {
		if ( ! function_exists( 'learndash_get_setting' ) ) {
			return false;
		}

		$video_enabled = learndash_get_setting( $post_id, 'lesson_video_enabled' );

		return ( 'on' === $video_enabled );
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

			$position = 0;
			foreach ( $block['innerBlocks'] as $inner ) {
				if ( $inner['blockName'] === 'ldj/prompt' && ! empty( $inner['attrs']['promptId'] ) ) {
					$position++;
					$pid = absint( $inner['attrs']['promptId'] );
					if ( (bool) get_post_meta( $pid, '_ldj_required', true ) ) {
						$prompt_ids[]             = $pid;
						self::$positions[ $pid ] = $position;
					}
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

			$inner    = $groups[2][ $i ];
			$position = 0;

			if ( preg_match_all( '/\[ldj\s+[^\]]*id=["\']?(\d+)["\']?[^\]]*\]/', $inner, $ldj_matches ) ) {
				foreach ( $ldj_matches[1] as $id ) {
					$position++;
					$pid = absint( $id );
					if ( (bool) get_post_meta( $pid, '_ldj_required', true ) ) {
						$prompt_ids[]             = $pid;
						self::$positions[ $pid ] = $position;
					}
				}
			}
		}

		return $prompt_ids;
	}
}
