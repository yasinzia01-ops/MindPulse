<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$quiz    = $quiz_id ? get_post( $quiz_id ) : null;
$data    = $quiz_id ? MP_CPT::get_quiz_data( $quiz_id ) : MP_CPT::get_quiz_data( 0 );
$title   = $quiz ? $quiz->post_title : '';
$settings = $data['settings'];
?>
<div class="wrap mp-wrap">
	<h1><?php echo $quiz_id ? esc_html__( 'Edit Quiz', 'mindpulse' ) : esc_html__( 'Add New Quiz', 'mindpulse' ); ?></h1>

	<?php if ( ! empty( $_GET['saved'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Quiz saved.', 'mindpulse' ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="mp-quiz-form">
		<?php wp_nonce_field( 'mp_save_quiz' ); ?>
		<input type="hidden" name="action" value="mp_save_quiz" />
		<input type="hidden" name="quiz_id" value="<?php echo esc_attr( $quiz_id ); ?>" />
		<input type="hidden" name="quiz_data" id="mp-quiz-data-input" value="" />

		<table class="form-table">
			<tr>
				<th><label for="quiz_title"><?php esc_html_e( 'Quiz Title', 'mindpulse' ); ?></label></th>
				<td><input type="text" id="quiz_title" name="quiz_title" class="regular-text" value="<?php echo esc_attr( $title ); ?>" required /></td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Questions', 'mindpulse' ); ?></h2>
		<div id="mp-questions"></div>
		<p><button type="button" class="button" id="mp-add-question"><?php esc_html_e( '+ Add Question', 'mindpulse' ); ?></button></p>

		<h2><?php esc_html_e( 'Score Bands & Result Profiles', 'mindpulse' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Each band maps a score range to the result profile shown to the user.', 'mindpulse' ); ?></p>
		<div id="mp-bands"></div>
		<p><button type="button" class="button" id="mp-add-band"><?php esc_html_e( '+ Add Score Band', 'mindpulse' ); ?></button></p>

		<h2><?php esc_html_e( 'Settings', 'mindpulse' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Lead Capture', 'mindpulse' ); ?></th>
				<td>
					<label><input type="checkbox" name="settings_lead_capture_before" value="1" <?php checked( ! empty( $settings['lead_capture_before'] ) ); ?> /> <?php esc_html_e( 'Ask for name & email before showing questions', 'mindpulse' ); ?></label>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Premium Results', 'mindpulse' ); ?></th>
				<td>
					<label><input type="checkbox" id="settings_is_premium" name="settings_is_premium" value="1" <?php checked( ! empty( $settings['is_premium'] ) ); ?> /> <?php esc_html_e( 'Require payment to unlock full result profile', 'mindpulse' ); ?></label>
					<p>
						<label><?php esc_html_e( 'Price', 'mindpulse' ); ?>
							<input type="number" step="0.01" min="0" name="settings_price" value="<?php echo esc_attr( $settings['price'] ?? 0 ); ?>" style="width:100px" />
						</label>
						<label style="margin-left:10px"><?php esc_html_e( 'Currency', 'mindpulse' ); ?>
							<input type="text" name="settings_currency" value="<?php echo esc_attr( $settings['currency'] ?? 'usd' ); ?>" style="width:70px" />
						</label>
					</p>
				</td>
			</tr>
			<tr>
				<th><label for="settings_thank_you"><?php esc_html_e( 'Thank You Message', 'mindpulse' ); ?></label></th>
				<td><textarea id="settings_thank_you" name="settings_thank_you_message" class="large-text" rows="3"><?php echo esc_textarea( $settings['thank_you_message'] ?? '' ); ?></textarea></td>
			</tr>
		</table>

		<?php submit_button( $quiz_id ? __( 'Update Quiz', 'mindpulse' ) : __( 'Create Quiz', 'mindpulse' ) ); ?>
	</form>
</div>

<script type="application/json" id="mp-quiz-data-seed"><?php echo wp_json_encode( $data ); ?></script>
