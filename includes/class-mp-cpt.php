<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the mp_quiz and mp_game post types.
 *
 * mp_quiz holds one quiz/form. Its questions, answer options + points,
 * score bands and result profiles are stored as JSON in the
 * `_mp_quiz_data` post meta key and edited via the custom builder UI
 * in admin/views/forms-edit.php (no page-builder dependency).
 */
class MP_CPT {

	public static function register() {
		register_post_type(
			'mp_quiz',
			array(
				'labels'       => array(
					'name'          => __( 'Quizzes', 'mindpulse' ),
					'singular_name' => __( 'Quiz', 'mindpulse' ),
				),
				'public'       => true,
				'show_ui'      => false, // Custom admin UI is used instead of the default post editor.
				'show_in_menu' => false,
				'has_archive'  => false,
				'rewrite'      => array( 'slug' => 'mindpulse-quiz' ),
				'supports'     => array( 'title' ),
			)
		);

		register_post_type(
			'mp_game',
			array(
				'labels'       => array(
					'name'          => __( 'Brain Games', 'mindpulse' ),
					'singular_name' => __( 'Brain Game', 'mindpulse' ),
				),
				'public'       => true,
				'show_ui'      => false,
				'show_in_menu' => false,
				'has_archive'  => false,
				'rewrite'      => array( 'slug' => 'mindpulse-game' ),
				'supports'     => array( 'title' ),
			)
		);
	}

	/**
	 * Decoded quiz data (questions, bands, profiles, settings) for a quiz post.
	 */
	public static function get_quiz_data( $quiz_id ) {
		$raw = get_post_meta( $quiz_id, '_mp_quiz_data', true );
		$data = json_decode( $raw, true );

		if ( ! is_array( $data ) ) {
			$data = array();
		}

		return wp_parse_args(
			$data,
			array(
				'questions' => array(),
				'bands'     => array(),
				'settings'  => array(
					'lead_capture_before' => true,
					'is_premium'          => false,
					'price'               => 0,
					'currency'            => 'usd',
					'thank_you_message'   => '',
				),
			)
		);
	}

	public static function save_quiz_data( $quiz_id, array $data ) {
		update_post_meta( $quiz_id, '_mp_quiz_data', wp_json_encode( $data ) );
	}
}
