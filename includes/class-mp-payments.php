<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gateway-agnostic payment handling. A quiz marked "premium" in its
 * settings gates the full result profile behind checkout; the free
 * band/score is still returned so the front end can show a teaser.
 */
interface MP_Payment_Gateway {
	public function create_checkout_session( $submission_id, $amount, $currency, $success_url, $cancel_url );
	public function verify_webhook( WP_REST_Request $request );
}

class MP_Payments {

	public static function register() {
		// Reserved for future gateway-specific hooks (e.g. Stripe SDK init).
	}

	private static function get_gateway() {
		return new MP_Gateway_Stripe();
	}

	public static function create_checkout( $submission_id, $quiz_id, array $quiz_data ) {
		$settings = $quiz_data['settings'];
		$amount   = (float) ( $settings['price'] ?? 0 );
		$currency = $settings['currency'] ?? 'usd';

		if ( $amount <= 0 ) {
			return null;
		}

		global $wpdb;
		$wpdb->insert(
			MP_DB::payments_table(),
			array(
				'submission_id' => $submission_id,
				'gateway'       => 'stripe',
				'amount'        => $amount,
				'currency'      => $currency,
				'status'        => 'pending',
				'created_at'    => current_time( 'mysql' ),
			)
		);

		$success_url = add_query_arg( array( 'mp_submission' => $submission_id, 'mp_paid' => 1 ), get_permalink( $quiz_id ) ?: home_url( '/' ) );
		$cancel_url  = add_query_arg( array( 'mp_submission' => $submission_id, 'mp_paid' => 0 ), get_permalink( $quiz_id ) ?: home_url( '/' ) );

		return self::get_gateway()->create_checkout_session( $submission_id, $amount, $currency, $success_url, $cancel_url );
	}

	public static function handle_webhook( WP_REST_Request $request ) {
		$gateway = self::get_gateway();
		$result  = $gateway->verify_webhook( $request );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( empty( $result['submission_id'] ) || empty( $result['status'] ) ) {
			return new WP_Error( 'mp_webhook_incomplete', __( 'Webhook missing submission reference.', 'mindpulse' ), array( 'status' => 400 ) );
		}

		global $wpdb;

		$wpdb->update(
			MP_DB::payments_table(),
			array( 'status' => $result['status'], 'transaction_id' => $result['transaction_id'] ?? '' ),
			array( 'submission_id' => $result['submission_id'] )
		);

		if ( 'paid' === $result['status'] ) {
			$wpdb->update(
				MP_DB::submissions_table(),
				array( 'payment_status' => 'paid' ),
				array( 'id' => $result['submission_id'] )
			);
		}

		return rest_ensure_response( array( 'received' => true ) );
	}
}

/**
 * Stripe Checkout integration using direct REST calls (no SDK/Composer
 * dependency). Requires mp_settings_stripe_secret_key and
 * mp_settings_stripe_webhook_secret to be set on the Settings page.
 */
class MP_Gateway_Stripe implements MP_Payment_Gateway {

	private function secret_key() {
		return get_option( 'mp_settings_stripe_secret_key', '' );
	}

	public function create_checkout_session( $submission_id, $amount, $currency, $success_url, $cancel_url ) {
		$secret_key = $this->secret_key();
		if ( ! $secret_key ) {
			return array( 'error' => __( 'Stripe is not configured yet.', 'mindpulse' ) );
		}

		$response = wp_remote_post(
			'https://api.stripe.com/v1/checkout/sessions',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $secret_key,
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'    => array(
					'mode'                              => 'payment',
					'success_url'                       => $success_url,
					'cancel_url'                         => $cancel_url,
					'client_reference_id'                => $submission_id,
					'line_items[0][quantity]'            => 1,
					'line_items[0][price_data][currency]' => $currency,
					'line_items[0][price_data][unit_amount]' => (int) round( $amount * 100 ),
					'line_items[0][price_data][product_data][name]' => __( 'Full Quiz Results', 'mindpulse' ),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array( 'error' => $response->get_error_message() );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['url'] ) ) {
			return array( 'error' => $body['error']['message'] ?? __( 'Unable to start checkout.', 'mindpulse' ) );
		}

		return array( 'checkout_url' => $body['url'] );
	}

	/**
	 * Verifies the Stripe webhook signature and extracts the submission
	 * id + payment status from a checkout.session.completed event.
	 */
	public function verify_webhook( WP_REST_Request $request ) {
		$webhook_secret = get_option( 'mp_settings_stripe_webhook_secret', '' );
		$payload        = $request->get_body();
		$sig_header     = $request->get_header( 'stripe-signature' );

		// Refuse to process anything unless a webhook secret is configured
		// and the signature checks out — never trust an unsigned payload,
		// even if that means payments stay unconfirmed until Settings is
		// filled in.
		if ( ! $webhook_secret ) {
			return new WP_Error( 'mp_webhook_not_configured', __( 'Stripe webhook signing secret is not configured in MindPulse Settings.', 'mindpulse' ), array( 'status' => 400 ) );
		}

		if ( ! $sig_header || ! $this->signature_is_valid( $payload, $sig_header, $webhook_secret ) ) {
			return new WP_Error( 'mp_invalid_signature', __( 'Invalid Stripe signature.', 'mindpulse' ), array( 'status' => 400 ) );
		}

		$event = json_decode( $payload, true );
		$type  = $event['type'] ?? '';
		$obj   = $event['data']['object'] ?? array();

		if ( 'checkout.session.completed' !== $type ) {
			return array( 'submission_id' => 0, 'status' => 'ignored' );
		}

		return array(
			'submission_id'  => absint( $obj['client_reference_id'] ?? 0 ),
			'status'         => 'paid' === ( $obj['payment_status'] ?? '' ) ? 'paid' : 'pending',
			'transaction_id' => $obj['payment_intent'] ?? '',
		);
	}

	private function signature_is_valid( $payload, $sig_header, $secret ) {
		$parts = array();
		foreach ( explode( ',', $sig_header ) as $part ) {
			list( $key, $value ) = array_pad( explode( '=', $part, 2 ), 2, '' );
			$parts[ $key ] = $value;
		}

		if ( empty( $parts['t'] ) || empty( $parts['v1'] ) ) {
			return false;
		}

		$expected = hash_hmac( 'sha256', $parts['t'] . '.' . $payload, $secret );

		return hash_equals( $expected, $parts['v1'] );
	}
}
