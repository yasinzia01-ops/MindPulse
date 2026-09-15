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

		$data = wp_parse_args(
			$data,
			array(
				'questions'     => array(),
				'sections'      => array(),
				'bands'         => array(),
				'scoring_mode'  => 'points',
				'settings'      => array(
					'lead_capture_before' => true,
					'is_premium'          => false,
					'price'               => 0,
					'currency'            => 'usd',
					'thank_you_message'   => '',
				),
				'intro'         => array(
					'enabled'     => false,
					'logo_url'    => '',
					'title'       => '',
					'subtitle'    => '',
					'meta_text'   => '',
					'tips'        => array(),
					'button_text' => __( "Let's Start →", 'mindpulse' ),
				),
				'processing'    => array(
					'enabled'         => false,
					'title'           => __( 'Scoring your test', 'mindpulse' ),
					'subtitle'        => '',
					'duration_seconds' => 4,
				),
				'preview'       => array(
					'enabled'             => false,
					'headline'            => '',
					'subheadline'         => '',
					'locked_label'        => __( 'Estimated Score', 'mindpulse' ),
					'benefits'            => array(),
					'guarantee_title'     => '',
					'guarantee_text'      => '',
					'testimonials'        => array(),
					'button_text'         => __( 'Unlock My Results', 'mindpulse' ),
					'social_proof_enabled' => false,
					'social_proof_items'  => array(),
				),
				'email_capture' => array(
					'enabled'     => false,
					'headline'    => '',
					'subheadline' => '',
					'stat1_label' => '',
					'stat1_value' => '',
					'stat2_label' => '',
					'stat2_value' => '',
					'button_text' => __( 'Continue', 'mindpulse' ),
					'trust_text'  => '',
				),
				'checkout'      => array(
					'headline'      => '',
					'benefits'      => array(),
					'price_caption' => '',
				),
				'report'        => array(
					'hero_title'           => '',
					'hero_subtitle'        => '',
					'show_iq_style'        => false,
					'classification_labels' => array(),
					'certificate_enabled'  => false,
					'certificate_title'    => __( 'Certificate of Achievement', 'mindpulse' ),
					'disclaimer'           => '',
				),
				'custom_css'    => '',
			)
		);

		// Every question belongs to a section; quizzes saved before sections
		// existed (or with questions added outside any section) fall back to
		// one implicit section so the frontend always has something to group by.
		if ( empty( $data['sections'] ) && ! empty( $data['questions'] ) ) {
			$data['sections'] = array(
				array( 'id' => 'default', 'name' => __( 'Quiz', 'mindpulse' ) ),
			);
			foreach ( $data['questions'] as &$question ) {
				if ( empty( $question['section_id'] ) ) {
					$question['section_id'] = 'default';
				}
			}
			unset( $question );
		}

		return $data;
	}

	public static function save_quiz_data( $quiz_id, array $data ) {
		update_post_meta( $quiz_id, '_mp_quiz_data', wp_json_encode( $data ) );
	}
}
