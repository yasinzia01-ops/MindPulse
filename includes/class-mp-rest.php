<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST routes under mindpulse/v1.
 */
class MP_REST {

	public static function register() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes() {
		register_rest_route(
			'mindpulse/v1',
			'/lead-capture',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'lead_capture' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'quiz_id' => array( 'required' => true ),
					'email'   => array( 'required' => true ),
				),
			)
		);

		register_rest_route(
			'mindpulse/v1',
			'/resume',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'resume' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'token'   => array( 'required' => true ),
					'quiz_id' => array( 'required' => true ),
				),
			)
		);

		register_rest_route(
			'mindpulse/v1',
			'/submit',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'submit' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'quiz_id' => array( 'required' => true ),
					'answers' => array( 'required' => true ),
				),
			)
		);

		register_rest_route(
			'mindpulse/v1',
			'/submission/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_submission' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'id' => array( 'required' => true ),
				),
			)
		);

		register_rest_route(
			'mindpulse/v1',
			'/payment/webhook',
			array(
				'methods'             => 'POST',
				'callback'            => array( 'MP_Payments', 'handle_webhook' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Called as soon as a visitor enters an email, before finishing the
	 * quiz, so an abandoned attempt can be recovered later.
	 */
	public static function lead_capture( WP_REST_Request $request ) {
		global $wpdb;

		$quiz_id       = absint( $request->get_param( 'quiz_id' ) );
		$email         = sanitize_email( $request->get_param( 'email' ) );
		$name          = sanitize_text_field( (string) $request->get_param( 'name' ) );
		$step          = absint( $request->get_param( 'step' ) );
		$answers       = $request->get_param( 'answers' );
		$partner       = self::resolve_partner_id( $request );
		$submission_id = absint( $request->get_param( 'submission_id' ) );

		if ( ! $quiz_id || ! is_email( $email ) ) {
			return new WP_Error( 'mp_invalid_lead', __( 'A valid quiz and email are required.', 'mindpulse' ), array( 'status' => 400 ) );
		}

		// Deferred email capture (structured Email Capture page shown after
		// the quiz already submitted anonymously, e.g. after a paywall
		// preview): attach the now-known name/email back onto that
		// submission. Only ever fills in a blank lead_email for the same
		// quiz — never overwrites an already-attributed submission, since
		// this endpoint has no auth and submission_id is guessable.
		if ( $submission_id ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->prefix}mp_submissions SET lead_name = %s, lead_email = %s WHERE id = %d AND quiz_id = %d AND ( lead_email IS NULL OR lead_email = '' )",
					$name,
					$email,
					$submission_id,
					$quiz_id
				)
			);
		}

		$answers_json = is_array( $answers ) ? wp_json_encode( $answers ) : null;

		$table = MP_DB::leads_table();
		$now   = current_time( 'mysql' );

		$existing_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id, resume_token FROM {$table} WHERE quiz_id = %d AND lead_email = %s AND converted_submission_id IS NULL ORDER BY id DESC LIMIT 1",
				$quiz_id,
				$email
			)
		);

		if ( $existing_id ) {
			$wpdb->update(
				$table,
				array(
					'lead_name'  => $name,
					'last_step'  => $step,
					'answers'    => $answers_json,
					'updated_at' => $now,
				),
				array( 'id' => $existing_id )
			);
			$lead_id      = (int) $existing_id;
			$resume_token = $wpdb->get_var( $wpdb->prepare( "SELECT resume_token FROM {$table} WHERE id = %d", $lead_id ) );
		} else {
			$resume_token = wp_generate_password( 20, false );

			$wpdb->insert(
				$table,
				array(
					'quiz_id'      => $quiz_id,
					'lead_name'    => $name,
					'lead_email'   => $email,
					'last_step'    => $step,
					'answers'      => $answers_json,
					'partner_id'   => $partner,
					'resume_token' => $resume_token,
					'created_at'   => $now,
					'updated_at'   => $now,
				)
			);
			$lead_id = (int) $wpdb->insert_id;
		}

		if ( $submission_id ) {
			$wpdb->update(
				$table,
				array( 'converted_submission_id' => $submission_id ),
				array( 'id' => $lead_id )
			);
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->prefix}mp_submissions SET lead_id = %d WHERE id = %d AND lead_id IS NULL",
					$lead_id,
					$submission_id
				)
			);
		}

		return rest_ensure_response(
			array(
				'lead_id'      => $lead_id,
				'resume_token' => $resume_token,
			)
		);
	}

	/**
	 * Looks up an in-progress (unconverted) lead by its resume token so
	 * the frontend can restore name/email/answers/step instead of
	 * restarting the quiz from the abandon-email link.
	 */
	public static function resume( WP_REST_Request $request ) {
		global $wpdb;

		$token   = sanitize_text_field( (string) $request->get_param( 'token' ) );
		$quiz_id = absint( $request->get_param( 'quiz_id' ) );

		if ( ! $token || ! $quiz_id ) {
			return new WP_Error( 'mp_invalid_resume', __( 'A valid resume link is required.', 'mindpulse' ), array( 'status' => 400 ) );
		}

		$table = MP_DB::leads_table();
		$lead  = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE resume_token = %s AND quiz_id = %d AND converted_submission_id IS NULL LIMIT 1",
				$token,
				$quiz_id
			)
		);

		if ( ! $lead ) {
			return new WP_Error( 'mp_resume_not_found', __( 'This quiz session could not be found or was already completed.', 'mindpulse' ), array( 'status' => 404 ) );
		}

		$answers = json_decode( (string) $lead->answers, true );

		return rest_ensure_response(
			array(
				'lead_id'   => (int) $lead->id,
				'name'      => $lead->lead_name,
				'email'     => $lead->lead_email,
				'last_step' => (int) $lead->last_step,
				'answers'   => is_array( $answers ) ? $answers : array(),
			)
		);
	}

	/**
	 * Scores a completed quiz, stores the submission, marks the lead
	 * (if any) as converted, and returns the resulting result profile.
	 */
	public static function submit( WP_REST_Request $request ) {
		global $wpdb;

		$quiz_id = absint( $request->get_param( 'quiz_id' ) );
		$answers = $request->get_param( 'answers' );
		$name    = sanitize_text_field( (string) $request->get_param( 'name' ) );
		$email   = sanitize_email( (string) $request->get_param( 'email' ) );
		$lead_id = absint( $request->get_param( 'lead_id' ) );
		$partner = self::resolve_partner_id( $request );

		if ( ! $quiz_id || 'mp_quiz' !== get_post_type( $quiz_id ) || ! is_array( $answers ) ) {
			return new WP_Error( 'mp_invalid_submit', __( 'A valid quiz and answers are required.', 'mindpulse' ), array( 'status' => 400 ) );
		}

		$quiz_data = MP_CPT::get_quiz_data( $quiz_id );
		$result    = MP_Scoring::score( $quiz_data, $answers );
		$band      = $result['band'];
		$is_premium = ! empty( $quiz_data['settings']['is_premium'] );

		$table = MP_DB::submissions_table();
		$now   = current_time( 'mysql' );

		$wpdb->insert(
			$table,
			array(
				'quiz_id'        => $quiz_id,
				'lead_name'      => $name,
				'lead_email'     => $email,
				'answers'        => wp_json_encode( $answers ),
				'total_score'    => $result['total_score'],
				'band_key'       => $band['key'] ?? '',
				'profile_title'  => $band['title'] ?? '',
				'payment_status' => $is_premium ? 'unpaid' : 'n/a',
				'partner_id'     => $partner,
				'lead_id'        => $lead_id ?: null,
				'created_at'     => $now,
			)
		);
		$submission_id = (int) $wpdb->insert_id;

		if ( $lead_id ) {
			$wpdb->update(
				MP_DB::leads_table(),
				array( 'converted_submission_id' => $submission_id ),
				array( 'id' => $lead_id )
			);
		}

		$unlocked = ! $is_premium;

		$response = array_merge(
			self::scoring_extras( $result ),
			array(
				'submission_id' => $submission_id,
				'total_score'   => $result['total_score'],
				'band'          => $unlocked ? $band : self::lock_band( $band ),
				'is_premium'    => $is_premium,
				'unlocked'      => $unlocked,
			)
		);

		if ( $is_premium ) {
			$response['checkout'] = MP_Payments::create_checkout( $submission_id, $quiz_id, $quiz_data );
		}

		return rest_ensure_response( $response );
	}

	/**
	 * correct_count/total_questions/percent only exist in 'correct' scoring
	 * mode (see MP_Scoring::score_correct()); omit them entirely for the
	 * default points mode rather than sending zeros that don't mean anything.
	 */
	private static function scoring_extras( array $result ) {
		if ( ! isset( $result['correct_count'] ) ) {
			return array();
		}

		return array(
			'correct_count'   => $result['correct_count'],
			'total_questions' => $result['total_questions'],
			'percent'         => $result['percent'],
		);
	}

	/**
	 * Fetches a previously stored submission by id, re-scoring its saved
	 * answers to recover the matching band. Used when a visitor returns
	 * from a Stripe Checkout redirect (`?mp_submission=ID&mp_paid=`) so
	 * the frontend can show the now-unlocked result, or offer to retry
	 * payment if it's still unpaid.
	 */
	public static function get_submission( WP_REST_Request $request ) {
		global $wpdb;

		$id = absint( $request->get_param( 'id' ) );
		$table = MP_DB::submissions_table();
		$submission = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );

		if ( ! $submission ) {
			return new WP_Error( 'mp_submission_not_found', __( 'Submission not found.', 'mindpulse' ), array( 'status' => 404 ) );
		}

		$quiz_data  = MP_CPT::get_quiz_data( $submission->quiz_id );
		$answers    = json_decode( (string) $submission->answers, true );
		$result     = MP_Scoring::score( $quiz_data, is_array( $answers ) ? $answers : array() );
		$band       = $result['band'];
		$is_premium = ! empty( $quiz_data['settings']['is_premium'] );
		$unlocked   = ! $is_premium || 'paid' === $submission->payment_status;

		$response = array_merge(
			self::scoring_extras( $result ),
			array(
				'submission_id' => $id,
				'total_score'   => (int) $submission->total_score,
				'band'          => $unlocked ? $band : self::lock_band( $band ),
				'is_premium'    => $is_premium,
				'unlocked'      => $unlocked,
				// The visitor's own page state (name typed into the lead
				// form) doesn't exist yet on this fresh page load, so hand
				// their stored name back for the certificate/report.
				'name'          => $submission->lead_name,
			)
		);

		if ( $is_premium && ! $unlocked ) {
			$response['checkout'] = MP_Payments::create_checkout( $id, $submission->quiz_id, $quiz_data );
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Strips the paid-only fields (description/image/cta) from a band so
	 * an unpaid premium result can't be read off the network response.
	 */
	private static function lock_band( $band ) {
		if ( ! is_array( $band ) ) {
			return $band;
		}

		return array( 'title' => $band['title'] ?? '' );
	}

	private static function resolve_partner_id( WP_REST_Request $request ) {
		$api_key = sanitize_text_field( (string) $request->get_param( 'partner_key' ) );
		if ( ! $api_key ) {
			return null;
		}

		global $wpdb;
		$table = MP_DB::partners_table();
		$id    = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE api_key = %s AND status = 'active'", $api_key ) );

		return $id ? (int) $id : null;
	}
}
