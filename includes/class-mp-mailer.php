<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Thin wrapper around wp_mail() with MindPulse's from-name/from-address
 * settings and {placeholder} replacement.
 */
class MP_Mailer {

	public static function send_template( $to, $subject, $body, array $placeholders = array() ) {
		foreach ( $placeholders as $key => $value ) {
			$subject = str_replace( '{' . $key . '}', $value, $subject );
			$body    = str_replace( '{' . $key . '}', $value, $body );
		}

		$from_name  = get_option( 'mp_settings_from_name', get_bloginfo( 'name' ) );
		$from_email = get_option( 'mp_settings_from_email', get_bloginfo( 'admin_email' ) );

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			sprintf( 'From: %s <%s>', $from_name, $from_email ),
		);

		return wp_mail( $to, $subject, wpautop( $body ), $headers );
	}
}
