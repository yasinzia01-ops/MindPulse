<?php
/**
 * Plugin Name: MindPulse
 * Plugin URI:  https://example.com/mindpulse
 * Description: Build IQ, personality and aptitude quizzes with point-band scoring, result profiles, lead capture, abandon-cart email recovery, payment-gated results, and B2B embeds. Works as a shortcode or Elementor widget.
 * Version:     1.2.1
 * Author:      MindPulse
 * Text Domain: mindpulse
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MP_VERSION', '1.2.1' );
define( 'MP_PLUGIN_FILE', __FILE__ );
define( 'MP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MP_DB_VERSION', '1.1.0' );

/**
 * Simple class-map autoloader for MP_* classes.
 */
spl_autoload_register(
	function ( $class ) {
		if ( strpos( $class, 'MP_' ) !== 0 ) {
			return;
		}

		$map = array(
			'MP_CPT'              => 'includes/class-mp-cpt.php',
			'MP_DB'                => 'includes/class-mp-db.php',
			'MP_Scoring'           => 'includes/class-mp-scoring.php',
			'MP_REST'              => 'includes/class-mp-rest.php',
			'MP_Cron'              => 'includes/class-mp-cron.php',
			'MP_Payments'          => 'includes/class-mp-payments.php',
			'MP_Payment_Gateway'   => 'includes/class-mp-payments.php',
			'MP_Gateway_Stripe'    => 'includes/class-mp-payments.php',
			'MP_Embed'             => 'includes/class-mp-embed.php',
			'MP_Mailer'            => 'includes/class-mp-mailer.php',
			'MP_Admin_Menu'        => 'admin/class-mp-admin-menu.php',
			'MP_Users_List_Table'  => 'admin/class-mp-users-list-table.php',
			'MP_Payments_List_Table' => 'admin/class-mp-payments-list-table.php',
			'MP_Shortcode'         => 'public/class-mp-shortcode.php',
			'MP_Elementor_Widget'  => 'public/class-mp-elementor-widget.php',
		);

		if ( isset( $map[ $class ] ) ) {
			require_once MP_PLUGIN_DIR . $map[ $class ];
		}
	}
);

/**
 * Activation: create custom tables and CPTs, flush rewrite rules.
 */
function mp_activate() {
	MP_DB::create_tables();
	MP_CPT::register();
	if ( ! wp_next_scheduled( 'mp_abandon_email_cron' ) ) {
		wp_schedule_event( time(), 'mp_fifteen_minutes', 'mp_abandon_email_cron' );
	}
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'mp_activate' );

/**
 * Deactivation: clear scheduled cron (data + tables are kept).
 */
function mp_deactivate() {
	wp_clear_scheduled_hook( 'mp_abandon_email_cron' );
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'mp_deactivate' );

/**
 * Custom cron interval used for abandon-email checks.
 */
add_filter(
	'cron_schedules',
	function ( $schedules ) {
		$schedules['mp_fifteen_minutes'] = array(
			'interval' => 15 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 15 Minutes (MindPulse)', 'mindpulse' ),
		);
		return $schedules;
	}
);

/**
 * Bootstrap.
 */
function mp_init() {
	if ( get_option( 'mp_db_version' ) !== MP_DB_VERSION ) {
		MP_DB::create_tables();
	}

	add_action( 'init', array( 'MP_CPT', 'register' ) );
	MP_REST::register();
	MP_Cron::register();
	MP_Payments::register();
	MP_Embed::register();

	if ( is_admin() ) {
		MP_Admin_Menu::register();
	} else {
		MP_Shortcode::register();
	}

	add_action( 'elementor/widgets/register', array( 'MP_Elementor_Widget', 'register' ) );

	add_action( 'init', 'mp_register_frontend_assets' );
	add_action( 'wp_enqueue_scripts', 'mp_enqueue_frontend_assets' );
}
add_action( 'plugins_loaded', 'mp_init' );

/**
 * Registered on init (not just wp_enqueue_scripts) so the embed bare
 * page in MP_Shortcode::maybe_render_embed_page(), which runs on
 * template_redirect before wp_enqueue_scripts fires, can still enqueue
 * and print them manually.
 */
function mp_register_frontend_assets() {
	wp_register_style( 'mp-frontend', MP_PLUGIN_URL . 'public/css/frontend.css', array(), MP_VERSION );

	// Loaded so the report stage's "Download PDF / Download Image" buttons
	// can render the result to a canvas (html2canvas) and wrap it in a PDF
	// (jsPDF) entirely client-side -- no server-side rendering dependency.
	wp_register_script( 'mp-html2canvas', 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js', array(), '1.4.1', true );
	wp_register_script( 'mp-jspdf', 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js', array(), '2.5.1', true );

	wp_register_script( 'mp-quiz-runner', MP_PLUGIN_URL . 'public/js/quiz-runner.js', array( 'mp-html2canvas', 'mp-jspdf' ), MP_VERSION, true );
	wp_localize_script(
		'mp-quiz-runner',
		'MindPulseConfig',
		array(
			'restUrl' => esc_url_raw( rest_url( 'mindpulse/v1' ) ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
		)
	);
}

/**
 * Always loads on normal front-end pages so the CSS is present in
 * wp_head regardless of when the shortcode/widget itself enqueues it.
 */
function mp_enqueue_frontend_assets() {
	wp_enqueue_style( 'mp-frontend' );
	wp_enqueue_script( 'mp-quiz-runner' );
}
