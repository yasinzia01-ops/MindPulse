<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Native Elementor widget wrapping the same quiz runner used by the
 * [mindpulse_quiz] shortcode. Only registered if Elementor is active
 * (see the elementor/widgets/register hook in the main plugin file).
 */
class MP_Elementor_Widget extends \Elementor\Widget_Base {

	public function get_name() {
		return 'mindpulse_quiz';
	}

	public function get_title() {
		return __( 'MindPulse Quiz', 'mindpulse' );
	}

	public function get_icon() {
		return 'eicon-form-horizontal';
	}

	public function get_categories() {
		return array( 'general' );
	}

	protected function register_controls() {
		$this->start_controls_section(
			'content_section',
			array( 'label' => __( 'Quiz', 'mindpulse' ) )
		);

		$this->add_control(
			'quiz_id',
			array(
				'label'   => __( 'Select Quiz', 'mindpulse' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => $this->get_quiz_options(),
				'default' => '',
			)
		);

		$this->end_controls_section();
	}

	private function get_quiz_options() {
		$options = array( '' => __( '— Select a quiz —', 'mindpulse' ) );

		$quizzes = get_posts(
			array(
				'post_type'      => 'mp_quiz',
				'posts_per_page' => -1,
			)
		);

		foreach ( $quizzes as $quiz ) {
			$options[ $quiz->ID ] = $quiz->post_title;
		}

		return $options;
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$quiz_id  = absint( $settings['quiz_id'] ?? 0 );

		if ( ! $quiz_id ) {
			echo '<p>' . esc_html__( 'Select a quiz in the widget settings.', 'mindpulse' ) . '</p>';
			return;
		}

		echo MP_Shortcode::render_quiz( $quiz_id, '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public static function register( $widgets_manager ) {
		$widgets_manager->register( new self() );
	}
}
