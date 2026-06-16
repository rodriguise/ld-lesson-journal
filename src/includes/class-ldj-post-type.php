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
			'all_items'          => __( 'Student Journal', 'lesson-journal' ),
			'menu_name'          => __( 'Student Journal', 'lesson-journal' ),
		);

		register_post_type( 'ldj_prompt', array(
			'labels'            => $labels,
			'public'            => false,
			'show_ui'           => true,
			'show_in_menu'      => 'learndash-lms',
			'show_in_rest'      => true,
			'rest_base'         => 'ldj-prompts',
			'supports'          => array( 'title', 'editor', 'custom-fields' ),
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
			'_ldj_description' => array(
				'type'    => 'string',
				'default' => '',
			),
			'_ldj_required'    => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'_ldj_graded'      => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'_ldj_min_chars'   => array(
				'type'    => 'integer',
				'default' => 0,
			),
			'_ldj_max_chars'   => array(
				'type'    => 'integer',
				'default' => 0,
			),
		);

		foreach ( $meta_fields as $key => $args ) {
			$sanitizer = 'sanitize_text_field';
			if ( $args['type'] === 'integer' ) {
				$sanitizer = 'absint';
			} elseif ( $args['type'] === 'boolean' ) {
				$sanitizer = 'rest_sanitize_boolean';
			}

			register_post_meta( 'ldj_prompt', $key, array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => $args['type'],
				'default'           => $args['default'],
				'sanitize_callback' => $sanitizer,
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

		$description = get_post_meta( $post->ID, '_ldj_description', true );
		$rows        = (int) get_post_meta( $post->ID, '_ldj_rows', true ) ?: 5;
		$placeholder = get_post_meta( $post->ID, '_ldj_placeholder', true );
		$graded      = (bool) get_post_meta( $post->ID, '_ldj_graded', true );
		$required    = (bool) get_post_meta( $post->ID, '_ldj_required', true );
		$min_chars   = (int) get_post_meta( $post->ID, '_ldj_min_chars', true );
		$max_chars   = (int) get_post_meta( $post->ID, '_ldj_max_chars', true );

		if ( ( $required || $graded ) && $min_chars < 1 ) {
			$min_chars = 1;
		}
		?>
		<p>
			<label for="ldj-description"><?php esc_html_e( 'Description', 'lesson-journal' ); ?></label><br>
			<input type="text" id="ldj-description" name="_ldj_description" value="<?php echo esc_attr( $description ); ?>" class="widefat">
			<span class="description"><?php esc_html_e( 'Short label shown in the completion checklist (optional).', 'lesson-journal' ); ?></span>
		</p>
		<p>
			<label for="ldj-rows"><?php esc_html_e( 'Number of lines', 'lesson-journal' ); ?></label><br>
			<input type="number" id="ldj-rows" name="_ldj_rows" value="<?php echo esc_attr( $rows ); ?>" min="1" max="10" class="small-text">
		</p>
		<p>
			<label for="ldj-placeholder"><?php esc_html_e( 'Placeholder text', 'lesson-journal' ); ?></label><br>
			<input type="text" id="ldj-placeholder" name="_ldj_placeholder" value="<?php echo esc_attr( $placeholder ); ?>" class="widefat">
		</p>
		<p>
			<label for="ldj-graded">
				<input type="checkbox" id="ldj-graded" name="_ldj_graded" value="1" <?php checked( $graded ); ?>>
				<?php esc_html_e( 'Graded', 'lesson-journal' ); ?>
			</label>
			<span class="description"><?php esc_html_e( 'Graded prompts are always required and appear in the gradebook.', 'lesson-journal' ); ?></span>
		</p>
		<p>
			<label for="ldj-required">
				<input type="checkbox" id="ldj-required" name="_ldj_required" value="1" <?php checked( $required || $graded ); ?> <?php echo $graded ? 'disabled' : ''; ?>>
				<?php esc_html_e( 'Required', 'lesson-journal' ); ?>
			</label>
			<?php if ( $graded ) : ?>
				<input type="hidden" name="_ldj_required" value="1">
			<?php endif; ?>
		</p>
		<p id="ldj-min-chars-row" style="<?php echo ( $required || $graded ) ? '' : 'display:none'; ?>">
			<label for="ldj-min-chars"><?php esc_html_e( 'Min characters', 'lesson-journal' ); ?></label><br>
			<input type="number" id="ldj-min-chars" name="_ldj_min_chars" value="<?php echo esc_attr( $min_chars ); ?>" min="1" class="small-text">
		</p>
		<p>
			<label for="ldj-max-chars"><?php esc_html_e( 'Max characters', 'lesson-journal' ); ?></label><br>
			<input type="number" id="ldj-max-chars" name="_ldj_max_chars" value="<?php echo esc_attr( $max_chars ); ?>" min="0" class="small-text">
			<span class="description"><?php esc_html_e( '0 = unlimited', 'lesson-journal' ); ?></span>
		</p>
		<script>
		(function(){
			var gradedCb = document.getElementById('ldj-graded');
			var requiredCb = document.getElementById('ldj-required');
			var row = document.getElementById('ldj-min-chars-row');
			var input = document.getElementById('ldj-min-chars');

			function syncRequired() {
				if (gradedCb.checked) {
					requiredCb.checked = true;
					requiredCb.disabled = true;
					if (!requiredCb.nextElementSibling || requiredCb.nextElementSibling.name !== '_ldj_required') {
						var hidden = document.createElement('input');
						hidden.type = 'hidden';
						hidden.name = '_ldj_required';
						hidden.value = '1';
						hidden.id = 'ldj-required-hidden';
						requiredCb.parentNode.appendChild(hidden);
					}
				} else {
					requiredCb.disabled = false;
					var hidden = document.getElementById('ldj-required-hidden');
					if (hidden) hidden.remove();
				}
			}

			function syncMinChars() {
				if (requiredCb.checked || gradedCb.checked) {
					row.style.display = '';
					if (parseInt(input.value,10) < 1) input.value = '1';
				} else {
					row.style.display = 'none';
					input.value = '0';
				}
			}

			gradedCb.addEventListener('change', function(){
				syncRequired();
				syncMinChars();
			});
			requiredCb.addEventListener('change', syncMinChars);
		})();
		</script>
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

		$graded   = ! empty( $_POST['_ldj_graded'] );
		$required = $graded || ! empty( $_POST['_ldj_required'] );
		update_post_meta( $post_id, '_ldj_graded', $graded );
		update_post_meta( $post_id, '_ldj_required', $required );

		$min_chars = absint( $_POST['_ldj_min_chars'] ?? 0 );
		if ( $required && $min_chars < 1 ) {
			$min_chars = 1;
		}
		if ( ! $required ) {
			$min_chars = 0;
		}
		update_post_meta( $post_id, '_ldj_min_chars', $min_chars );

		$fields = array(
			'_ldj_rows'      => 'absint',
			'_ldj_max_chars' => 'absint',
		);

		foreach ( $fields as $key => $sanitizer ) {
			if ( isset( $_POST[ $key ] ) ) {
				update_post_meta( $post_id, $key, $sanitizer( $_POST[ $key ] ) );
			}
		}

		$text_fields = array( '_ldj_placeholder', '_ldj_description' );

		foreach ( $text_fields as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				update_post_meta( $post_id, $key, sanitize_text_field( $_POST[ $key ] ) );
			}
		}
	}
}
