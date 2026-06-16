<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class LDJ_Admin_Entries {

	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu_page' ), 30 );
		add_action( 'admin_menu', array( __CLASS__, 'hide_submenu' ), 31 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'all_admin_notices', array( __CLASS__, 'render_tab_header' ) );
		add_filter( 'submenu_file', array( __CLASS__, 'highlight_submenu' ), 10, 2 );
	}

	public static function add_menu_page() {
		add_submenu_page(
			'learndash-lms',
			__( 'Journal Entries', 'lesson-journal' ),
			__( 'Journal Entries', 'lesson-journal' ),
			'edit_posts',
			'ldj-entries',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function hide_submenu() {
		add_action( 'admin_head', function () {
			echo '<style>#adminmenu a[href="admin.php?page=ldj-entries"] { display: none !important; }</style>';
		} );
	}

	public static function highlight_submenu( $submenu_file, $parent_file ) {
		$screen = get_current_screen();
		if ( $screen && in_array( $screen->id, array( 'learndash-lms_page_ldj-entries', 'learndash-lms_page_ldj-grade', 'learndash-lms_page_ldj-settings' ), true ) ) {
			return 'edit.php?post_type=ldj_prompt';
		}
		return $submenu_file;
	}

	public static function enqueue_assets( $hook ) {
		$screen = get_current_screen();

		$is_entries = $hook === 'learndash-lms_page_ldj-entries';
		$is_prompts = $screen && $screen->post_type === 'ldj_prompt' && $screen->base === 'edit';

		if ( ! $is_entries && ! $is_prompts ) {
			return;
		}

		wp_enqueue_style(
			'ldj-admin',
			LESSON_JOURNAL_URL . 'assets/css/ldj-admin.css',
			array(),
			LESSON_JOURNAL_VERSION
		);

		if ( $is_entries ) {
			add_thickbox();
		}
	}

	public static function render_tab_header() {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		if ( $screen->post_type !== 'ldj_prompt' || $screen->base !== 'edit' ) {
			return;
		}

		echo '<div class="wrap ldj-tab-header">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Student Journal', 'lesson-journal' ) . '</h1>';
		echo '<a href="' . esc_url( admin_url( 'post-new.php?post_type=ldj_prompt' ) ) . '" class="page-title-action">';
		echo esc_html__( 'Add New Prompt', 'lesson-journal' );
		echo '</a>';
		echo '<hr class="wp-header-end">';
		self::output_tabs( 'prompts' );
		echo '</div>';
	}

	public static function output_tabs( $active ) {
		$tabs = array(
			'prompts' => array(
				'label' => __( 'Prompts', 'lesson-journal' ),
				'url'   => admin_url( 'edit.php?post_type=ldj_prompt' ),
			),
			'entries' => array(
				'label' => __( 'Entries', 'lesson-journal' ),
				'url'   => admin_url( 'admin.php?page=ldj-entries' ),
			),
			'grading' => array(
				'label' => __( 'Grading', 'lesson-journal' ),
				'url'   => admin_url( 'admin.php?page=ldj-grade' ),
			),
		);

		$tabs['settings'] = array(
			'label' => __( 'Settings', 'lesson-journal' ),
			'url'   => admin_url( 'admin.php?page=ldj-settings' ),
		);

		echo '<nav class="nav-tab-wrapper ldj-nav-tabs">';
		foreach ( $tabs as $key => $tab ) {
			printf(
				'<a href="%s" class="nav-tab%s">%s</a>',
				esc_url( $tab['url'] ),
				$key === $active ? ' nav-tab-active' : '',
				esc_html( $tab['label'] )
			);
		}
		echo '</nav>';
	}

	public static function render_page() {
		$table = new LDJ_Entries_List_Table();
		$table->prepare_items();
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Student Journal', 'lesson-journal' ); ?></h1>
			<hr class="wp-header-end">
			<?php self::output_tabs( 'entries' ); ?>
			<form method="get">
				<input type="hidden" name="page" value="ldj-entries">
				<?php
				$table->search_box( __( 'Search', 'lesson-journal' ), 'ldj-search' );
				$table->display();
				?>
			</form>
		</div>
		<?php
	}
}

class LDJ_Entries_List_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct( array(
			'singular' => 'entry',
			'plural'   => 'entries',
			'ajax'     => false,
		) );
	}

	public function get_columns() {
		return array(
			'cb'         => '<input type="checkbox">',
			'student'    => __( 'Student', 'lesson-journal' ),
			'prompt'     => __( 'Prompt', 'lesson-journal' ),
			'lesson'     => __( 'Lesson / Topic', 'lesson-journal' ),
			'entry_text' => __( 'Entry', 'lesson-journal' ),
			'created_at' => __( 'Date', 'lesson-journal' ),
			'updated_at' => __( 'Updated', 'lesson-journal' ),
		);
	}

	public function get_sortable_columns() {
		return array(
			'student'    => array( 'user_id', false ),
			'prompt'     => array( 'prompt_id', false ),
			'lesson'     => array( 'lesson_id', false ),
			'created_at' => array( 'created_at', false ),
			'updated_at' => array( 'updated_at', true ),
		);
	}

	public function get_bulk_actions() {
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			return array();
		}

		return array(
			'delete' => __( 'Delete', 'lesson-journal' ),
		);
	}

	protected function extra_tablenav( $which ) {
		if ( $which !== 'top' ) {
			return;
		}

		$selected_lesson = absint( $_GET['filter_lesson'] ?? 0 );
		$selected_prompt = absint( $_GET['filter_prompt'] ?? 0 );
		$selected_user   = absint( $_GET['filter_user'] ?? 0 );
		?>
		<div class="alignleft actions">
			<?php
			$this->render_lesson_filter( $selected_lesson );
			$this->render_prompt_filter( $selected_prompt );
			$this->render_user_filter( $selected_user );
			?>
			<input type="submit" class="button" value="<?php esc_attr_e( 'Filter', 'lesson-journal' ); ?>">
		</div>
		<?php
	}

	private function render_lesson_filter( int $selected ) {
		global $wpdb;

		$lesson_ids = $wpdb->get_col(
			$wpdb->prepare( 'SELECT DISTINCT lesson_id FROM %i ORDER BY lesson_id', LDJ_DB::table_name() )
		);

		echo '<select name="filter_lesson">';
		echo '<option value="">' . esc_html__( 'All Lessons', 'lesson-journal' ) . '</option>';

		foreach ( $lesson_ids as $lid ) {
			printf(
				'<option value="%d" %s>%s</option>',
				(int) $lid,
				selected( $selected, (int) $lid, false ),
				esc_html( get_the_title( $lid ) ?: '#' . $lid )
			);
		}

		echo '</select>';
	}

	private function render_prompt_filter( int $selected ) {
		$prompts = get_posts( array(
			'post_type'      => 'ldj_prompt',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );

		echo '<select name="filter_prompt">';
		echo '<option value="">' . esc_html__( 'All Prompts', 'lesson-journal' ) . '</option>';

		foreach ( $prompts as $prompt ) {
			printf(
				'<option value="%d" %s>%s</option>',
				$prompt->ID,
				selected( $selected, $prompt->ID, false ),
				esc_html( $prompt->post_title ?: '#' . $prompt->ID )
			);
		}

		echo '</select>';
	}

	private function render_user_filter( int $selected ) {
		global $wpdb;

		$user_ids = $wpdb->get_col(
			$wpdb->prepare( 'SELECT DISTINCT user_id FROM %i ORDER BY user_id', LDJ_DB::table_name() )
		);

		echo '<select name="filter_user">';
		echo '<option value="">' . esc_html__( 'All Students', 'lesson-journal' ) . '</option>';

		foreach ( $user_ids as $uid ) {
			$user = get_userdata( $uid );
			if ( ! $user ) {
				continue;
			}

			printf(
				'<option value="%d" %s>%s</option>',
				(int) $uid,
				selected( $selected, (int) $uid, false ),
				esc_html( $user->display_name )
			);
		}

		echo '</select>';
	}

	public function prepare_items() {
		$this->process_bulk_action();

		$per_page = 20;
		$paged    = $this->get_pagenum();

		$result = LDJ_Entry::get_entries_for_list_table( array(
			'user_id'   => absint( $_GET['filter_user'] ?? 0 ),
			'lesson_id' => absint( $_GET['filter_lesson'] ?? 0 ),
			'prompt_id' => absint( $_GET['filter_prompt'] ?? 0 ),
			'orderby'   => sanitize_text_field( $_GET['orderby'] ?? 'updated_at' ),
			'order'     => sanitize_text_field( $_GET['order'] ?? 'DESC' ),
			'per_page'  => $per_page,
			'offset'    => ( $paged - 1 ) * $per_page,
		) );

		$this->items = $result['items'];

		$this->set_pagination_args( array(
			'total_items' => $result['total'],
			'per_page'    => $per_page,
			'total_pages' => ceil( $result['total'] / $per_page ),
		) );

		$this->_column_headers = array(
			$this->get_columns(),
			array(),
			$this->get_sortable_columns(),
		);
	}

	protected function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="entry_ids[]" value="%d">', $item->id );
	}

	protected function column_student( $item ) {
		$user = get_userdata( $item->user_id );
		$name = $user ? esc_html( $user->display_name ) : '#' . $item->user_id;

		$grade_url = LDJ_Admin_Grade::get_grade_url( $item->user_id, $item->lesson_id );
		$actions   = array(
			'grade' => sprintf(
				'<a href="%s" class="ldj-action-grade">%s</a>',
				esc_url( $grade_url ),
				esc_html__( 'Grade Journal', 'lesson-journal' )
			),
		);

		return $name . $this->row_actions( $actions );
	}

	protected function column_prompt( $item ) {
		$title = get_the_title( $item->prompt_id );
		return $title ? esc_html( $title ) : '#' . $item->prompt_id;
	}

	protected function column_lesson( $item ) {
		$title = get_the_title( $item->lesson_id );
		$type  = get_post_type( $item->lesson_id );
		$label = $type === 'sfwd-topic' ? __( 'Topic', 'lesson-journal' ) : __( 'Lesson', 'lesson-journal' );

		return $title
			? esc_html( $title ) . ' <small>(' . esc_html( $label ) . ')</small>'
			: '#' . $item->lesson_id;
	}

	protected function column_entry_text( $item ) {
		if ( (bool) get_post_meta( $item->prompt_id, '_ldj_private', true ) && (int) $item->user_id !== get_current_user_id() ) {
			return '<em>' . esc_html__( '[Private]', 'lesson-journal' ) . '</em>';
		}

		$excerpt = mb_strimwidth( $item->entry_text, 0, 100, '…' );
		$full    = esc_html( $item->entry_text );

		$output = esc_html( $excerpt );

		if ( mb_strlen( $item->entry_text ) > 100 ) {
			$output .= sprintf(
				' <a href="#TB_inline?width=600&height=400&inlineId=ldj-entry-%d" class="thickbox" title="%s">%s</a>',
				$item->id,
				esc_attr__( 'Full Entry', 'lesson-journal' ),
				esc_html__( 'View', 'lesson-journal' )
			);

			$output .= sprintf(
				'<div id="ldj-entry-%d" style="display:none"><div class="ldj-entry-full">%s</div></div>',
				$item->id,
				nl2br( $full )
			);
		}

		return $output;
	}

	protected function column_created_at( $item ) {
		return esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $item->created_at ) ) );
	}

	protected function column_updated_at( $item ) {
		return esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $item->updated_at ) ) );
	}

	protected function column_default( $item, $column_name ) {
		return isset( $item->$column_name ) ? esc_html( $item->$column_name ) : '';
	}

	private function process_bulk_action() {
		if ( $this->current_action() !== 'delete' ) {
			return;
		}

		if ( ! current_user_can( 'edit_others_posts' ) ) {
			return;
		}

		$ids = array_map( 'absint', $_GET['entry_ids'] ?? array() );

		if ( ! empty( $ids ) ) {
			check_admin_referer( 'bulk-entries' );
			LDJ_Entry::bulk_delete( $ids );
		}
	}
}
