<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Lists quiz submissions ("Users" tab) with checkboxes + bulk delete,
 * matching the screenshot's list UI.
 */
class MP_Users_List_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'submission',
				'plural'   => 'submissions',
				'ajax'     => false,
			)
		);
	}

	public function get_columns() {
		return array(
			'cb'             => '<input type="checkbox" />',
			'lead_name'      => __( 'Name', 'mindpulse' ),
			'lead_email'     => __( 'Email', 'mindpulse' ),
			'quiz'           => __( 'Quiz', 'mindpulse' ),
			'total_score'    => __( 'Score', 'mindpulse' ),
			'profile_title'  => __( 'Result Profile', 'mindpulse' ),
			'payment_status' => __( 'Payment', 'mindpulse' ),
			'created_at'     => __( 'Date', 'mindpulse' ),
		);
	}

	protected function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="submission_ids[]" value="%d" />', $item['id'] );
	}

	protected function column_default( $item, $column_name ) {
		return isset( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : '';
	}

	protected function column_quiz( $item ) {
		$title = get_the_title( $item['quiz_id'] );
		return esc_html( $title ?: __( '(deleted quiz)', 'mindpulse' ) );
	}

	protected function get_bulk_actions() {
		return array( 'delete' => __( 'Delete', 'mindpulse' ) );
	}

	public function prepare_items() {
		global $wpdb;

		$table    = MP_DB::submissions_table();
		$per_page = 20;
		$paged    = $this->get_pagenum();

		$this->_column_headers = array( $this->get_columns(), array(), array() );

		if ( isset( $_POST['action'] ) && 'delete' === $_POST['action'] && ! empty( $_POST['submission_ids'] ) && check_admin_referer( 'bulk-' . $this->_args['plural'] ) ) {
			$ids = array_map( 'absint', (array) $_POST['submission_ids'] );
			foreach ( $ids as $id ) {
				$wpdb->delete( $table, array( 'id' => $id ) );
			}
		}

		$total_items = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
			)
		);

		$offset = ( $paged - 1 ) * $per_page;

		$this->items = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d", $per_page, $offset ),
			ARRAY_A
		);
	}
}
