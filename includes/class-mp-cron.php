<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abandon-cart style email recovery for quiz leads who entered an email
 * but never finished the quiz. Runs on the mp_fifteen_minutes schedule
 * registered in the main plugin file.
 */
class MP_Cron {

	public static function register() {
		add_action( 'mp_abandon_email_cron', array( __CLASS__, 'process_abandoned_leads' ) );
	}

	public static function process_abandoned_leads() {
		if ( ! get_option( 'mp_settings_abandon_enabled', true ) ) {
			return;
		}

		global $wpdb;

		$delay_minutes  = (int) get_option( 'mp_settings_abandon_delay_minutes', 30 );
		$sequence_count = max( 1, (int) get_option( 'mp_settings_abandon_sequence_count', 1 ) );
		$table          = MP_DB::leads_table();

		$cutoff = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp', true ) - ( $delay_minutes * MINUTE_IN_SECONDS ) );

		$leads = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				 WHERE converted_submission_id IS NULL
				 AND recovery_emails_sent < %d
				 AND updated_at <= %s
				 AND ( last_email_sent_at IS NULL OR last_email_sent_at <= %s )
				 LIMIT 50",
				$sequence_count,
				$cutoff,
				$cutoff
			)
		);

		foreach ( $leads as $lead ) {
			self::send_recovery_email( $lead );
		}
	}

	private static function send_recovery_email( $lead ) {
		global $wpdb;

		$quiz    = get_post( $lead->quiz_id );
		$subject = get_option( 'mp_settings_abandon_subject', __( 'Finish your {quiz_title} results', 'mindpulse' ) );
		$body    = get_option( 'mp_settings_abandon_body', __( "Hi {first_name},\n\nYou're almost done with {quiz_title}! Click below to see your results.\n\n{resume_link}", 'mindpulse' ) );

		$resume_url = add_query_arg(
			array(
				'mp_resume' => $lead->resume_token,
				'mp_quiz'   => $lead->quiz_id,
			),
			home_url( '/' )
		);

		$first_name = trim( explode( ' ', (string) $lead->lead_name )[0] ?? '' );

		$sent = MP_Mailer::send_template(
			$lead->lead_email,
			$subject,
			$body,
			array(
				'first_name'  => $first_name ?: __( 'there', 'mindpulse' ),
				'quiz_title'  => $quiz ? $quiz->post_title : __( 'your quiz', 'mindpulse' ),
				'resume_link' => '<a href="' . esc_url( $resume_url ) . '">' . esc_html( $resume_url ) . '</a>',
			)
		);

		if ( $sent ) {
			$wpdb->update(
				MP_DB::leads_table(),
				array(
					'recovery_emails_sent' => (int) $lead->recovery_emails_sent + 1,
					'last_email_sent_at'   => current_time( 'mysql' ),
				),
				array( 'id' => $lead->id )
			);
		}
	}
}
