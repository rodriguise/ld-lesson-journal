<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LDJ_Gradebook {

	public static function register() {
		if ( ! class_exists( 'LearnDash_Gradebook' ) ) {
			return;
		}

		add_action( 'admin_menu', array( __CLASS__, 'add_settings_page' ), 32 );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'wp_ajax_ldj_gb_components', array( __CLASS__, 'ajax_get_components' ) );
		add_filter( 'ld_gb_user_grade_components', array( __CLASS__, 'inject_journal_grades' ), 10, 3 );
		add_action( 'ldj_entry_graded', array( __CLASS__, 'sync_grade_to_gradebook' ), 10, 2 );
	}

	public static function add_settings_page() {
		add_submenu_page(
			'learndash-lms',
			__( 'Journal Gradebook Settings', 'lesson-journal' ),
			__( 'Journal Gradebook', 'lesson-journal' ),
			'manage_options',
			'ldj-gradebook-settings',
			array( __CLASS__, 'render_settings' )
		);

		add_action( 'admin_head', function () {
			echo '<style>#adminmenu a[href="admin.php?page=ldj-gradebook-settings"] { display: none !important; }</style>';
		} );
	}

	public static function enqueue_assets( $hook ) {
		if ( $hook !== 'learndash-lms_page_ldj-gradebook-settings' ) {
			return;
		}

		wp_enqueue_style(
			'ldj-admin',
			LESSON_JOURNAL_URL . 'assets/css/ldj-admin.css',
			array(),
			LESSON_JOURNAL_VERSION
		);
	}

	public static function ajax_get_components() {
		check_ajax_referer( 'ldj_gb_settings', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$gradebook_id = absint( $_POST['gradebook_id'] ?? 0 );

		if ( ! $gradebook_id || ! function_exists( 'ld_gb_get_field' ) ) {
			wp_send_json_success( array( 'components' => array() ) );
		}

		$components = ld_gb_get_field( 'components', $gradebook_id );
		if ( ! is_array( $components ) ) {
			$components = array();
		}

		$result = array();
		foreach ( $components as $comp ) {
			$id   = absint( $comp['id'] ?? 0 );
			$name = $comp['name'] ?? '';
			if ( $id ) {
				$result[] = array( 'id' => $id, 'name' => $name ?: "#{$id}" );
			}
		}

		wp_send_json_success( array( 'components' => $result ) );
	}

	public static function register_settings() {
		register_setting( 'ldj_gradebook', 'ldj_gradebook_map', array(
			'type'              => 'array',
			'sanitize_callback' => array( __CLASS__, 'sanitize_map' ),
			'default'           => array(),
		) );
	}

	public static function sanitize_map( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		return array(
			'gradebook_id'  => absint( $value['gradebook_id'] ?? 0 ),
			'component_id'  => absint( $value['component_id'] ?? 0 ),
		);
	}

	public static function render_settings() {
		echo '<div class="wrap">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Student Journal', 'lesson-journal' ) . '</h1>';
		echo '<hr class="wp-header-end">';
		LDJ_Admin_Entries::output_tabs( 'gradebook' );

		$map = get_option( 'ldj_gradebook_map', array() );

		$gradebooks = get_posts( array(
			'post_type'      => 'gradebook',
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );

		$selected_gb = absint( $map['gradebook_id'] ?? 0 );
		$selected_comp = absint( $map['component_id'] ?? 0 );

		$components = array();
		if ( $selected_gb && function_exists( 'ld_gb_get_field' ) ) {
			$components = ld_gb_get_field( 'components', $selected_gb );
			if ( ! is_array( $components ) ) {
				$components = array();
			}
		}

		?>
			<p><?php esc_html_e( 'Connect journal grades to a LearnDash Gradebook component. When you grade journal entries on graded prompts, scores will automatically sync to the selected gradebook component.', 'lesson-journal' ); ?></p>

			<form method="post" action="options.php">
				<?php settings_fields( 'ldj_gradebook' ); ?>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="ldj-gb"><?php esc_html_e( 'Gradebook', 'lesson-journal' ); ?></label></th>
						<td>
							<select name="ldj_gradebook_map[gradebook_id]" id="ldj-gb">
								<option value=""><?php esc_html_e( '— None (disabled) —', 'lesson-journal' ); ?></option>
								<?php foreach ( $gradebooks as $gb ) : ?>
									<option value="<?php echo esc_attr( $gb->ID ); ?>" <?php selected( $selected_gb, $gb->ID ); ?>>
										<?php echo esc_html( $gb->post_title ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Select the gradebook to sync journal grades into.', 'lesson-journal' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ldj-comp"><?php esc_html_e( 'Component', 'lesson-journal' ); ?></label></th>
						<td>
							<select name="ldj_gradebook_map[component_id]" id="ldj-comp">
								<option value=""><?php esc_html_e( '— Select —', 'lesson-journal' ); ?></option>
								<?php foreach ( $components as $comp ) :
									$comp_id = absint( $comp['id'] ?? 0 );
									$comp_name = $comp['name'] ?? '';
									if ( ! $comp_id ) continue;
								?>
									<option value="<?php echo esc_attr( $comp_id ); ?>" <?php selected( $selected_comp, $comp_id ); ?>>
										<?php echo esc_html( $comp_name ?: "#{$comp_id}" ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Create a "Journal" component in your gradebook, then select it here. Journal grades will appear as grades within this component.', 'lesson-journal' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
			<script>
			(function(){
				var gbSelect = document.getElementById('ldj-gb');
				var compSelect = document.getElementById('ldj-comp');
				var savedComp = <?php echo (int) $selected_comp; ?>;

				gbSelect.addEventListener('change', function(){
					var gbId = parseInt(gbSelect.value, 10);
					compSelect.innerHTML = '<option value=""><?php echo esc_js( __( 'Loading…', 'lesson-journal' ) ); ?></option>';

					if (!gbId) {
						compSelect.innerHTML = '<option value=""><?php echo esc_js( __( '— Select —', 'lesson-journal' ) ); ?></option>';
						return;
					}

					var data = new FormData();
					data.append('action', 'ldj_gb_components');
					data.append('nonce', '<?php echo esc_js( wp_create_nonce( 'ldj_gb_settings' ) ); ?>');
					data.append('gradebook_id', gbId);

					fetch('<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>', {method:'POST', body:data})
						.then(function(r){ return r.json(); })
						.then(function(resp){
							compSelect.innerHTML = '<option value=""><?php echo esc_js( __( '— Select —', 'lesson-journal' ) ); ?></option>';
							if (resp.success && resp.data.components) {
								resp.data.components.forEach(function(c){
									var opt = document.createElement('option');
									opt.value = c.id;
									opt.textContent = c.name;
									if (c.id === savedComp) opt.selected = true;
									compSelect.appendChild(opt);
								});
							}
						});
				});
			})();
			</script>
		</div>
		<?php
	}

	public static function inject_journal_grades( $components, $gradebook_id, $user_id ) {
		$map = get_option( 'ldj_gradebook_map', array() );

		if ( empty( $map['gradebook_id'] ) || (int) $map['gradebook_id'] !== (int) $gradebook_id ) {
			return $components;
		}

		$target_component_id = (int) ( $map['component_id'] ?? 0 );

		if ( ! $target_component_id ) {
			return $components;
		}

		$entries = self::get_graded_entries( $user_id );

		if ( empty( $entries ) ) {
			return $components;
		}

		$journal_grades = array();
		foreach ( $entries as $entry ) {
			$score = 0;

			if ( $entry->grade_score !== null && $entry->grade_max !== null && (float) $entry->grade_max > 0 ) {
				$score = round( ( (float) $entry->grade_score / (float) $entry->grade_max ) * 100, 2 );
			} elseif ( $entry->grade_status === 'pass' ) {
				$score = 100;
			}

			$prompt_title = get_the_title( $entry->prompt_id );
			$lesson_title = get_the_title( $entry->lesson_id );

			$journal_grades[] = array(
				'name'      => sprintf( '%s — %s', $lesson_title, $prompt_title ),
				'type'      => 'journal',
				'score'     => $score,
				'status'    => $entry->grade_status === 'pass' ? 'completed' : 'failed',
				'completed' => strtotime( $entry->graded_at ),
				'post_id'   => $entry->prompt_id,
			);
		}

		foreach ( $components as &$component ) {
			$comp_id = (int) ( $component['id'] ?? 0 );
			if ( $comp_id !== $target_component_id ) {
				continue;
			}

			if ( ! isset( $component['grades'] ) || ! is_array( $component['grades'] ) ) {
				$component['grades'] = array();
			}

			$component['grades'] = array_merge( $component['grades'], $journal_grades );

			if ( ! empty( $component['grades'] ) && empty( $component['overridden'] ) ) {
				$total = 0;
				$count = 0;
				foreach ( $component['grades'] as $g ) {
					if ( isset( $g['score'] ) ) {
						$total += (float) $g['score'];
						$count++;
					}
				}
				$component['averaged_score'] = $count > 0 ? round( $total / $count, 2 ) : 0;
			}

			break;
		}
		unset( $component );

		return $components;
	}

	public static function sync_grade_to_gradebook( $entry_id, $entry ) {
		if ( ! function_exists( 'learndash_gradebook_update_manual_grade' ) ) {
			return;
		}

		if ( ! (bool) get_post_meta( $entry->prompt_id, '_ldj_graded', true ) ) {
			return;
		}

		$map = get_option( 'ldj_gradebook_map', array() );
		$gradebook_id  = absint( $map['gradebook_id'] ?? 0 );
		$component_id  = absint( $map['component_id'] ?? 0 );

		if ( ! $gradebook_id || ! $component_id ) {
			return;
		}

		$prompt_title = get_the_title( $entry->prompt_id );
		$lesson_title = get_the_title( $entry->lesson_id );
		$grade_name   = sprintf( 'Journal: %s — %s', $lesson_title, $prompt_title );

		$score = 0;
		if ( $entry->grade_score !== null && $entry->grade_max !== null && (float) $entry->grade_max > 0 ) {
			$score = round( ( (float) $entry->grade_score / (float) $entry->grade_max ) * 100, 2 );
		} elseif ( $entry->grade_status === 'pass' ) {
			$score = 100;
		}

		learndash_gradebook_update_manual_grade( array(
			'score'     => $score,
			'name'      => $grade_name,
			'component' => $component_id,
			'gradebook' => $gradebook_id,
			'user_id'   => $entry->user_id,
			'status'    => $entry->grade_status === 'pass' ? 'completed' : 'failed',
			'completed' => time(),
		) );
	}

	private static function get_graded_entries( int $user_id ): array {
		global $wpdb;

		$entries = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE user_id = %d AND grade_status IS NOT NULL ORDER BY lesson_id ASC, prompt_id ASC',
				LDJ_DB::table_name(),
				$user_id
			)
		);

		return array_filter( $entries, function ( $entry ) {
			return (bool) get_post_meta( $entry->prompt_id, '_ldj_graded', true );
		} );
	}
}
