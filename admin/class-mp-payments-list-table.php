<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Lists payment transactions for premium quiz results.
 */
class MP_Payments_List_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'payment',
				'plural'   => 'payments',
				'ajax'     => false,
			)
		);
	}

	public function get_columns() {
		return array(
			'id'             => __( 'ID', 'mindpulse' ),
			'submission_id'  => __( 'Submission', 'mindpulse' ),
			'gateway'        => __( 'Gateway', 'mindpulse' ),
			'amount'         => __( 'Amount', 'mindpulse' ),
			'status'         => __( 'Status', 'mindpulse' ),
			'transaction_id' => __( 'Transaction ID', 'mindpulse' ),
			'created_at'     => __( 'Date', 'mindpulse' ),
		);
	}

	protected function column_default( $item, $column_name ) {
		return isset( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : '';
	}

	protected function column_amount( $item ) {
		return esc_html( strtoupper( $item['currency'] ) . ' ' . number_format_i18n( (float) $item['amount'], 2 ) );
	}

	protected function column_status( $item ) {
		$class = 'paid' === $item['status'] ? 'mp-badge mp-badge--paid' : 'mp-badge mp-badge--pending';
		return sprintf( '<span class="%s">%s</span>', esc_attr( $class ), esc_html( ucfirst( $item['status'] ) ) );
	}

	public function prepare_items() {
		global $wpdb;

		$table    = MP_DB::payments_table();
		$per_page = 20;
		$paged    = $this->get_pagenum();

		$this->_column_headers = array( $this->get_columns(), array(), array() );

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
