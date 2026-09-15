<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * B2B partner management + the public embed.js endpoint that lets a
 * partner site iframe a quiz cross-domain, tagging resulting
 * submissions with that partner's id.
 */
class MP_Embed {

	public static function register() {
		add_action( 'init', array( __CLASS__, 'maybe_serve_embed_script' ) );
	}

	public static function maybe_serve_embed_script() {
		if ( empty( $_GET['mp_embed_js'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		header( 'Content-Type: application/javascript; charset=utf-8' );
		echo self::embed_script();
		exit;
	}

	public static function embed_script() {
		$site_url = esc_js( home_url( '/' ) );

		return <<<JS
(function () {
	var scripts = document.getElementsByTagName('script');
	var current = scripts[scripts.length - 1];
	var quizId = current.getAttribute('data-quiz');
	var partnerKey = current.getAttribute('data-key');

	var iframe = document.createElement('iframe');
	iframe.src = '{$site_url}?mp_embed_quiz=' + encodeURIComponent(quizId) + '&mp_partner_key=' + encodeURIComponent(partnerKey);
	iframe.style.width = '100%';
	iframe.style.minHeight = '600px';
	iframe.style.border = '0';
	iframe.setAttribute('scrolling', 'no');

	current.parentNode.insertBefore(iframe, current);

	window.addEventListener('message', function (event) {
		if (event.data && event.data.mindpulseHeight) {
			iframe.style.height = event.data.mindpulseHeight + 'px';
		}
	});
})();
JS;
	}

	public static function generate_api_key() {
		return wp_generate_password( 40, false );
	}

	public static function get_embed_snippet( $quiz_id, $api_key ) {
		$src = esc_url( add_query_arg( 'mp_embed_js', 1, home_url( '/' ) ) );
		return sprintf(
			'<script src="%1$s" data-quiz="%2$d" data-key="%3$s"></script>',
			$src,
			$quiz_id,
			esc_attr( $api_key )
		);
	}
}
