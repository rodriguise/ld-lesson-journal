<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LDJ_Post_Type {

	public static function register() {
		self::register_post_type();
		self::register_taxonomy();
		self::register_meta();
		add_action( 'add_meta_boxes_ldj_prompt', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post_ldj_prompt', array( __CLASS__, 'save_meta' ), 10, 2 );
	}

	public static function register_post_type() {
		$labels = array(
			'name'               => __( 'Journal Prompts', 'lesson-journal' ),
			'singular_name'      => __( 'Journal Prompt', 'lesson-journal' ),
			'add_new'            => __( 'Add New Prompt', 'lesson-journal' ),
			'add_new_item'       => __( 'Add New Journal Prompt', 'lesson-journal' ),
			'edit_item'          => __( 'Edit Journal Prompt', 'lesson-journal' ),
			'new_item'           => __( 'New Journal Prompt', 'lesson-journal' ),
			'view_item'          => __( 'View Journal Prompt', 'lesson-journal' ),
			'search_items'       => __( 'Search Journal Prompts', 'lesson-journal' ),
			'not_found'          => __( 'No journal prompts found.', 'lesson-journal' ),
			'not_found_in_trash' => __( 'No journal prompts found in Trash.', 'lesson-journal' ),
			'all_items'          => __( 'Journal Prompts', 'lesson-journal' ),
			'menu_name'          => __( 'Journal Prompts', 'lesson-journal' ),
		);

		register_post_type( 'ldj_prompt', array(
			'labels'            => $labels,
			'public'            => false,
			'show_ui'           => true,
			'show_in_menu'      => 'learndash-lms',
			'show_in_rest'      => true,
			'rest_base'         => 'ldj-prompts',
			'supports'          => array( 'title', 'editor' ),
			'capability_type'   => 'post',
			'has_archive'       => false,
			'hierarchical'      => false,
			'menu_icon'         => 'dashicons-book-alt',
		) );
	}

	public static function register_taxonomy() {
		$labels = array(
			'name'              => __( 'Prompt Categories', 'lesson-journal' ),
			'singular_name'     => __( 'Prompt Category', 'lesson-journal' ),
			'search_items'      => __( 'Search Categories', 'lesson-journal' ),
			'all_items'         => __( 'All Categories', 'lesson-journal' ),
			'parent_item'       => __( 'Parent Category', 'lesson-journal' ),
			'parent_item_colon' => __( 'Parent Category:', 'lesson-journal' ),
			'edit_item'         => __( 'Edit Category', 'lesson-journal' ),
			'update_item'       => __( 'Update Category', 'lesson-journal' ),
			'add_new_item'      => __( 'Add New Category', 'lesson-journal' ),
			'new_item_name'     => __( 'New Category Name', 'lesson-journal' ),
			'menu_name'         => __( 'Prompt Categories', 'lesson-journal' ),
		);

		register_taxonomy( 'ldj_prompt_category', 'ldj_prompt', array(
			'labels'            => $labels,
			'hierarchical'      => true,
			'public'            => false,
			'show_ui'           => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
		) );
	}

	public static function register_meta() {
		$meta_fields = array(
			'_ldj_rows'        => array(
				'type'    => 'integer',
				'default' => 5,
			),
			'_ldj_placeholder' => array(
				'type'    => 'string',
				'default' => '',
			),
			'_ldj_max_chars'   => array(
				'type'    => 'integer',
				'default' => 0,
			),
		);

		foreach ( $meta_fields as $key => $args ) {
			register_post_meta( 'ldj_prompt', $key, array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => $args['type'],
				'default'           => $args['default'],
				'sanitize_callback' => $args['type'] === 'integer' ? 'absint' : 'sanitize_text_field',
				'auth_callback'     => function () {
					return current_user_can( 'edit_posts' );
				},
			) );
		}
	}

	public static function add_meta_boxes() {
		add_meta_box(
			'ldj-prompt-settings',
			__( 'Prompt Settings', 'lesson-journal' ),
			array( __CLASS__, 'render_meta_box' ),
			'ldj_prompt',
			'side',
			'default'
		);
	}

	public static function render_meta_box( $post ) {
		wp_nonce_field( 'ldj_prompt_meta', 'ldj_prompt_meta_nonce' );

		$rows        = (int) get_post_meta( $post->ID, '_ldj_rows', true ) ?: 5;
		$placeholder = get_post_meta( $post->ID, '_ldj_placeholder', true );
		$max_chars   = (int) get_post_meta( $post->ID, '_ldj_max_chars', true );
		?>
		<p>
			<label for="ldj-rows"><?php esc_html_e( 'Number of lines', 'lesson-journal' ); ?></label><br>
			<input type="number" id="ldj-rows" name="_ldj_rows" value="<?php echo esc_attr( $rows ); ?>" min="1" max="10" class="small-text">
		</p>
		<p>
			<label for="ldj-placeholder"><?php esc_html_e( 'Placeholder text', 'lesson-journal' ); ?></label><br>
			<input type="text" id="ldj-placeholder" name="_ldj_placeholder" value="<?php echo esc_attr( $placeholder ); ?>" class="widefat">
		</p>
		<p>
			<label for="ldj-max-chars"><?php esc_html_e( 'Max characters', 'lesson-journal' ); ?></label><br>
			<input type="number" id="ldj-max-chars" name="_ldj_max_chars" value="<?php echo esc_attr( $max_chars ); ?>" min="0" class="small-text">
			<span class="description"><?php esc_html_e( '0 = unlimited', 'lesson-journal' ); ?></span>
		</p>
		<?php
	}

	public static function save_meta( $post_id, $post ) {
		if ( ! isset( $_POST['ldj_prompt_meta_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $_POST['ldj_prompt_meta_nonce'], 'ldj_prompt_meta' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$fields = array(
			'_ldj_rows'      => 'absint',
			'_ldj_max_chars' => 'absint',
		);

		foreach ( $fields as $key => $sanitizer ) {
			if ( isset( $_POST[ $key ] ) ) {
				update_post_meta( $post_id, $key, $sanitizer( $_POST[ $key ] ) );
			}
		}

		$text_fields = array( '_ldj_placeholder' );

		foreach ( $text_fields as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				update_post_meta( $post_id, $key, sanitize_text_field( $_POST[ $key ] ) );
			}
		}
	}
}
