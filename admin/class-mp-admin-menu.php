<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once MP_PLUGIN_DIR . 'admin/class-mp-users-list-table.php';
require_once MP_PLUGIN_DIR . 'admin/class-mp-payments-list-table.php';

/**
 * Registers the MindPulse admin menu (Forms, Users, Payments, Abandon
 * Emails, Brain Games, Settings, B2B Embed) and handles their form
 * submissions.
 */
class MP_Admin_Menu {

	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_post_mp_save_quiz', array( __CLASS__, 'handle_save_quiz' ) );
		add_action( 'admin_post_mp_delete_quiz', array( __CLASS__, 'handle_delete_quiz' ) );
		add_action( 'admin_post_mp_save_settings', array( __CLASS__, 'handle_save_settings' ) );
		add_action( 'admin_post_mp_create_partner', array( __CLASS__, 'handle_create_partner' ) );
		add_action( 'admin_post_mp_save_game', array( __CLASS__, 'handle_save_game' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	public static function add_menu() {
		add_menu_page(
			__( 'MindPulse', 'mindpulse' ),
			__( 'MindPulse', 'mindpulse' ),
			'manage_options',
			'mindpulse',
			array( __CLASS__, 'render_forms' ),
			'dashicons-forms',
			26
		);

		add_submenu_page( 'mindpulse', __( 'Forms', 'mindpulse' ), __( 'Forms', 'mindpulse' ), 'manage_options', 'mindpulse', array( __CLASS__, 'render_forms' ) );
		add_submenu_page( 'mindpulse', __( 'Users', 'mindpulse' ), __( 'Users', 'mindpulse' ), 'manage_options', 'mindpulse-users', array( __CLASS__, 'render_users' ) );
		add_submenu_page( 'mindpulse', __( 'Payments', 'mindpulse' ), __( 'Payments', 'mindpulse' ), 'manage_options', 'mindpulse-payments', array( __CLASS__, 'render_payments' ) );
		add_submenu_page( 'mindpulse', __( 'Abandon Emails', 'mindpulse' ), __( 'Abandon Emails', 'mindpulse' ), 'manage_options', 'mindpulse-abandon-emails', array( __CLASS__, 'render_abandon_emails' ) );
		add_submenu_page( 'mindpulse', __( 'Brain Games', 'mindpulse' ), __( 'Brain Games', 'mindpulse' ), 'manage_options', 'mindpulse-brain-games', array( __CLASS__, 'render_brain_games' ) );
		add_submenu_page( 'mindpulse', __( 'Settings', 'mindpulse' ), __( 'Settings', 'mindpulse' ), 'manage_options', 'mindpulse-settings', array( __CLASS__, 'render_settings' ) );
		add_submenu_page( 'mindpulse', __( 'B2B Embed', 'mindpulse' ), __( 'B2B Embed', 'mindpulse' ), 'manage_options', 'mindpulse-b2b-embed', array( __CLASS__, 'render_b2b_embed' ) );
	}

	public static function enqueue_assets( $hook ) {
		if ( strpos( $hook, 'mindpulse' ) === false ) {
			return;
		}
		wp_enqueue_style( 'mp-admin', MP_PLUGIN_URL . 'public/css/admin.css', array(), MP_VERSION );

		if ( isset( $_GET['page'] ) && 'mindpulse' === $_GET['page'] && isset( $_GET['action'] ) && 'edit' === $_GET['action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_enqueue_script( 'mp-quiz-builder', MP_PLUGIN_URL . 'public/js/quiz-builder.js', array(), MP_VERSION, true );
		}
	}

	/* ---------------------------------------------------------------
	 * Views
	 * ------------------------------------------------------------- */

	public static function render_forms() {
		$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : 'list'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'edit' === $action ) {
			$quiz_id = isset( $_GET['quiz_id'] ) ? absint( $_GET['quiz_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			include MP_PLUGIN_DIR . 'admin/views/forms-edit.php';
			return;
		}

		include MP_PLUGIN_DIR . 'admin/views/forms-list.php';
	}

	public static function render_users() {
		include MP_PLUGIN_DIR . 'admin/views/users-list.php';
	}

	public static function render_payments() {
		include MP_PLUGIN_DIR . 'admin/views/payments-list.php';
	}

	public static function render_abandon_emails() {
		include MP_PLUGIN_DIR . 'admin/views/abandon-emails.php';
	}

	public static function render_brain_games() {
		include MP_PLUGIN_DIR . 'admin/views/brain-games.php';
	}

	public static function render_settings() {
		include MP_PLUGIN_DIR . 'admin/views/settings.php';
	}

	public static function render_b2b_embed() {
		include MP_PLUGIN_DIR . 'admin/views/b2b-embed.php';
	}

	/* ---------------------------------------------------------------
	 * Form handlers
	 * ------------------------------------------------------------- */

	public static function handle_save_quiz() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'mp_save_quiz' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'mindpulse' ) );
		}

		$quiz_id = isset( $_POST['quiz_id'] ) ? absint( $_POST['quiz_id'] ) : 0;
		$title   = isset( $_POST['quiz_title'] ) ? sanitize_text_field( wp_unslash( $_POST['quiz_title'] ) ) : __( 'Untitled Quiz', 'mindpulse' );

		if ( $quiz_id ) {
			wp_update_post( array( 'ID' => $quiz_id, 'post_title' => $title ) );
		} else {
			$quiz_id = wp_insert_post(
				array(
					'post_type'   => 'mp_quiz',
					'post_title'  => $title,
					'post_status' => 'publish',
				)
			);
		}

		$data_json = isset( $_POST['quiz_data'] ) ? wp_unslash( $_POST['quiz_data'] ) : '{}';
		$data      = json_decode( $data_json, true );

		if ( is_array( $data ) ) {
			MP_CPT::save_quiz_data( $quiz_id, $data );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=mindpulse&action=edit&quiz_id=' . $quiz_id . '&saved=1' ) );
		exit;
	}

	public static function handle_delete_quiz() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'mp_delete_quiz' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'mindpulse' ) );
		}

		$quiz_id = isset( $_POST['quiz_id'] ) ? absint( $_POST['quiz_id'] ) : 0;
		if ( $quiz_id ) {
			wp_trash_post( $quiz_id );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=mindpulse&deleted=1' ) );
		exit;
	}

	public static function handle_save_settings() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'mp_save_settings' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'mindpulse' ) );
		}

		$referer_page = isset( $_POST['_mp_settings_page'] ) ? sanitize_key( wp_unslash( $_POST['_mp_settings_page'] ) ) : 'mindpulse-settings';

		// Fields are scoped per page so submitting one settings form never
		// resets checkboxes (like abandon_enabled) that only live on the other.
		$fields_by_page = array(
			'mindpulse-settings'       => array(
				'mp_settings_from_name'            => 'sanitize_text_field',
				'mp_settings_from_email'            => 'sanitize_email',
				'mp_settings_stripe_secret_key'     => 'sanitize_text_field',
				'mp_settings_stripe_webhook_secret' => 'sanitize_text_field',
			),
			'mindpulse-abandon-emails' => array(
				'mp_settings_abandon_enabled'        => 'absint',
				'mp_settings_abandon_delay_minutes'  => 'absint',
				'mp_settings_abandon_sequence_count' => 'absint',
				'mp_settings_abandon_subject'        => 'sanitize_text_field',
				'mp_settings_abandon_body'            => 'wp_kses_post',
			),
		);

		$fields = $fields_by_page[ $referer_page ] ?? array();

		foreach ( $fields as $key => $sanitizer ) {
			if ( isset( $_POST[ $key ] ) ) {
				update_option( $key, call_user_func( $sanitizer, wp_unslash( $_POST[ $key ] ) ) );
			} elseif ( 'mp_settings_abandon_enabled' === $key ) {
				update_option( $key, 0 );
			}
		}
		wp_safe_redirect( admin_url( 'admin.php?page=' . $referer_page . '&saved=1' ) );
		exit;
	}

	public static function handle_create_partner() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'mp_create_partner' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'mindpulse' ) );
		}

		global $wpdb;

		$wpdb->insert(
			MP_DB::partners_table(),
			array(
				'name'       => sanitize_text_field( wp_unslash( $_POST['partner_name'] ?? '' ) ),
				'api_key'    => MP_Embed::generate_api_key(),
				'status'     => 'active',
				'created_at' => current_time( 'mysql' ),
			)
		);

		wp_safe_redirect( admin_url( 'admin.php?page=mindpulse-b2b-embed&created=1' ) );
		exit;
	}

	public static function handle_save_game() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'mp_save_game' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'mindpulse' ) );
		}

		$title     = sanitize_text_field( wp_unslash( $_POST['game_title'] ?? '' ) );
		$embed_url = esc_url_raw( wp_unslash( $_POST['game_embed_url'] ?? '' ) );

		$game_id = wp_insert_post(
			array(
				'post_type'   => 'mp_game',
				'post_title'  => $title,
				'post_status' => 'publish',
			)
		);

		if ( $game_id && ! is_wp_error( $game_id ) ) {
			update_post_meta( $game_id, '_mp_game_embed_url', $embed_url );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=mindpulse-brain-games&saved=1' ) );
		exit;
	}
}
