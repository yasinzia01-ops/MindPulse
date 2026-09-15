<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$quiz     = $quiz_id ? get_post( $quiz_id ) : null;
$data     = $quiz_id ? MP_CPT::get_quiz_data( $quiz_id ) : MP_CPT::get_quiz_data( 0 );
$title    = $quiz ? $quiz->post_title : '';
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

		<div class="mp-tabs-layout">
			<div class="mp-tabs-nav">
				<button type="button" class="mp-tab-link is-active" data-tab="details"><?php esc_html_e( 'Form Details', 'mindpulse' ); ?></button>
				<button type="button" class="mp-tab-link" data-tab="intro"><?php esc_html_e( 'Test Introduction', 'mindpulse' ); ?></button>
				<button type="button" class="mp-tab-link" data-tab="sections"><?php esc_html_e( 'Test Sections', 'mindpulse' ); ?></button>
				<button type="button" class="mp-tab-link" data-tab="processing"><?php esc_html_e( 'Processing Page', 'mindpulse' ); ?></button>
				<button type="button" class="mp-tab-link" data-tab="preview"><?php esc_html_e( 'Preview Page', 'mindpulse' ); ?></button>
				<button type="button" class="mp-tab-link" data-tab="email-capture"><?php esc_html_e( 'Email Capture', 'mindpulse' ); ?></button>
				<button type="button" class="mp-tab-link" data-tab="checkout"><?php esc_html_e( 'Checkout Page', 'mindpulse' ); ?></button>
				<button type="button" class="mp-tab-link" data-tab="report"><?php esc_html_e( 'Report Settings', 'mindpulse' ); ?></button>
				<button type="button" class="mp-tab-link" data-tab="css"><?php esc_html_e( 'Custom CSS', 'mindpulse' ); ?></button>
			</div>

			<div class="mp-tabs-content">

				<!-- Form Details -->
				<div class="mp-tab-panel is-active" data-tab="details">
					<table class="form-table">
						<tr>
							<th><label for="quiz_title"><?php esc_html_e( 'Quiz Title', 'mindpulse' ); ?></label></th>
							<td><input type="text" id="quiz_title" name="quiz_title" class="regular-text" value="<?php echo esc_attr( $title ); ?>" required /></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Scoring Mode', 'mindpulse' ); ?></th>
							<td>
								<select id="scoring_mode">
									<option value="points" <?php selected( ( $data['scoring_mode'] ?? 'points' ), 'points' ); ?>><?php esc_html_e( 'Points (sum each chosen option\'s points)', 'mindpulse' ); ?></option>
									<option value="correct" <?php selected( ( $data['scoring_mode'] ?? 'points' ), 'correct' ); ?>><?php esc_html_e( 'Correct / Incorrect (count right answers)', 'mindpulse' ); ?></option>
								</select>
								<p class="description"><?php esc_html_e( 'Correct/Incorrect mode uses the "Correct answer" checkbox on each option instead of its points value.', 'mindpulse' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Lead Capture', 'mindpulse' ); ?></th>
							<td>
								<label><input type="checkbox" name="settings_lead_capture_before" value="1" <?php checked( ! empty( $settings['lead_capture_before'] ) ); ?> /> <?php esc_html_e( 'Ask for name & email before showing questions', 'mindpulse' ); ?></label>
								<p class="description"><?php esc_html_e( 'If off, and Email Capture (tab) is enabled, name/email is instead asked after the paywall preview — see Email Capture tab.', 'mindpulse' ); ?></p>
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
				</div>

				<!-- Test Introduction -->
				<div class="mp-tab-panel" data-tab="intro">
					<p><label><input type="checkbox" id="intro_enabled" <?php checked( ! empty( $data['intro']['enabled'] ) ); ?> /> <?php esc_html_e( 'Show an introduction screen before the quiz starts', 'mindpulse' ); ?></label></p>
					<table class="form-table">
						<tr><th><?php esc_html_e( 'Logo URL', 'mindpulse' ); ?></th><td><input type="text" id="intro_logo_url" class="regular-text" value="<?php echo esc_attr( $data['intro']['logo_url'] ?? '' ); ?>" /></td></tr>
						<tr><th><?php esc_html_e( 'Title', 'mindpulse' ); ?></th><td><input type="text" id="intro_title" class="large-text" value="<?php echo esc_attr( $data['intro']['title'] ?? '' ); ?>" /></td></tr>
						<tr><th><?php esc_html_e( 'Subtitle', 'mindpulse' ); ?></th><td><textarea id="intro_subtitle" class="large-text" rows="2"><?php echo esc_textarea( $data['intro']['subtitle'] ?? '' ); ?></textarea></td></tr>
						<tr><th><?php esc_html_e( 'Meta text', 'mindpulse' ); ?></th><td><input type="text" id="intro_meta_text" class="regular-text" placeholder="~10 minutes &middot; 4.8 stars from 12,000 users" value="<?php echo esc_attr( $data['intro']['meta_text'] ?? '' ); ?>" /></td></tr>
						<tr><th><?php esc_html_e( 'Button text', 'mindpulse' ); ?></th><td><input type="text" id="intro_button_text" class="regular-text" value="<?php echo esc_attr( $data['intro']['button_text'] ?? '' ); ?>" /></td></tr>
					</table>
					<h3><?php esc_html_e( 'What to expect (tips)', 'mindpulse' ); ?></h3>
					<div id="mp-intro-tips"></div>
					<p><button type="button" class="button" id="mp-add-intro-tip"><?php esc_html_e( '+ Add Tip', 'mindpulse' ); ?></button></p>
				</div>

				<!-- Test Sections -->
				<div class="mp-tab-panel" data-tab="sections">
					<p class="description"><?php esc_html_e( 'Group questions into named sections. Each option can carry both a points value (used in Points scoring mode) and a Correct-answer flag (used in Correct/Incorrect scoring mode).', 'mindpulse' ); ?></p>
					<div id="mp-sections"></div>
					<p><button type="button" class="button button-primary" id="mp-add-section"><?php esc_html_e( '+ Add Section', 'mindpulse' ); ?></button></p>

					<h2><?php esc_html_e( 'Score Bands & Result Profiles', 'mindpulse' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Each band maps a score range (points total, or correct-answer count, depending on Scoring Mode) to the result profile shown to the user.', 'mindpulse' ); ?></p>
					<div id="mp-bands"></div>
					<p><button type="button" class="button" id="mp-add-band"><?php esc_html_e( '+ Add Score Band', 'mindpulse' ); ?></button></p>
				</div>

				<!-- Processing Page -->
				<div class="mp-tab-panel" data-tab="processing">
					<p><label><input type="checkbox" id="processing_enabled" <?php checked( ! empty( $data['processing']['enabled'] ) ); ?> /> <?php esc_html_e( 'Show an animated "scoring" screen after the last question', 'mindpulse' ); ?></label></p>
					<table class="form-table">
						<tr><th><?php esc_html_e( 'Title', 'mindpulse' ); ?></th><td><input type="text" id="processing_title" class="regular-text" value="<?php echo esc_attr( $data['processing']['title'] ?? '' ); ?>" /></td></tr>
						<tr><th><?php esc_html_e( 'Subtitle', 'mindpulse' ); ?></th><td><input type="text" id="processing_subtitle" class="regular-text" value="<?php echo esc_attr( $data['processing']['subtitle'] ?? '' ); ?>" /></td></tr>
						<tr><th><?php esc_html_e( 'Duration (seconds)', 'mindpulse' ); ?></th><td><input type="number" min="1" max="60" id="processing_duration_seconds" style="width:80px" value="<?php echo esc_attr( $data['processing']['duration_seconds'] ?? 4 ); ?>" /></td></tr>
					</table>
				</div>

				<!-- Preview Page -->
				<div class="mp-tab-panel" data-tab="preview">
					<p><label><input type="checkbox" id="preview_enabled" <?php checked( ! empty( $data['preview']['enabled'] ) ); ?> /> <?php esc_html_e( 'Show a paywall preview page before checkout (premium quizzes only)', 'mindpulse' ); ?></label></p>
					<table class="form-table">
						<tr><th><?php esc_html_e( 'Headline', 'mindpulse' ); ?></th><td><input type="text" id="preview_headline" class="large-text" value="<?php echo esc_attr( $data['preview']['headline'] ?? '' ); ?>" /></td></tr>
						<tr><th><?php esc_html_e( 'Subheadline', 'mindpulse' ); ?></th><td><textarea id="preview_subheadline" class="large-text" rows="2"><?php echo esc_textarea( $data['preview']['subheadline'] ?? '' ); ?></textarea></td></tr>
						<tr><th><?php esc_html_e( 'Locked score label', 'mindpulse' ); ?></th><td><input type="text" id="preview_locked_label" class="regular-text" value="<?php echo esc_attr( $data['preview']['locked_label'] ?? '' ); ?>" /></td></tr>
						<tr><th><?php esc_html_e( 'Button text', 'mindpulse' ); ?></th><td><input type="text" id="preview_button_text" class="regular-text" value="<?php echo esc_attr( $data['preview']['button_text'] ?? '' ); ?>" /></td></tr>
						<tr><th><?php esc_html_e( 'Guarantee title', 'mindpulse' ); ?></th><td><input type="text" id="preview_guarantee_title" class="regular-text" value="<?php echo esc_attr( $data['preview']['guarantee_title'] ?? '' ); ?>" /></td></tr>
						<tr><th><?php esc_html_e( 'Guarantee text', 'mindpulse' ); ?></th><td><textarea id="preview_guarantee_text" class="large-text" rows="2"><?php echo esc_textarea( $data['preview']['guarantee_text'] ?? '' ); ?></textarea></td></tr>
					</table>

					<h3><?php esc_html_e( 'Benefits list', 'mindpulse' ); ?></h3>
					<div id="mp-preview-benefits"></div>
					<p><button type="button" class="button" id="mp-add-preview-benefit"><?php esc_html_e( '+ Add Benefit', 'mindpulse' ); ?></button></p>

					<h3><?php esc_html_e( 'Testimonials', 'mindpulse' ); ?></h3>
					<div id="mp-preview-testimonials"></div>
					<p><button type="button" class="button" id="mp-add-preview-testimonial"><?php esc_html_e( '+ Add Testimonial', 'mindpulse' ); ?></button></p>

					<h3><?php esc_html_e( 'Live social proof notice', 'mindpulse' ); ?></h3>
					<p class="description"><?php esc_html_e( 'Rotates through these example entries with a "X just unlocked..." style notice. These are illustrative examples you write yourself, not real visitor data.', 'mindpulse' ); ?></p>
					<p><label><input type="checkbox" id="preview_social_proof_enabled" <?php checked( ! empty( $data['preview']['social_proof_enabled'] ) ); ?> /> <?php esc_html_e( 'Show the rotating notice', 'mindpulse' ); ?></label></p>
					<div id="mp-preview-social-proof"></div>
					<p><button type="button" class="button" id="mp-add-preview-social-proof"><?php esc_html_e( '+ Add Entry', 'mindpulse' ); ?></button></p>
				</div>

				<!-- Email Capture -->
				<div class="mp-tab-panel" data-tab="email-capture">
					<p><label><input type="checkbox" id="email_capture_enabled" <?php checked( ! empty( $data['email_capture']['enabled'] ) ); ?> /> <?php esc_html_e( 'Use this structured email capture screen (after the preview page, for premium quizzes) instead of the plain lead form', 'mindpulse' ); ?></label></p>
					<table class="form-table">
						<tr><th><?php esc_html_e( 'Headline', 'mindpulse' ); ?></th><td><input type="text" id="email_capture_headline" class="large-text" value="<?php echo esc_attr( $data['email_capture']['headline'] ?? '' ); ?>" /></td></tr>
						<tr><th><?php esc_html_e( 'Subheadline', 'mindpulse' ); ?></th><td><textarea id="email_capture_subheadline" class="large-text" rows="2"><?php echo esc_textarea( $data['email_capture']['subheadline'] ?? '' ); ?></textarea></td></tr>
						<tr><th><?php esc_html_e( 'Stat 1', 'mindpulse' ); ?></th><td>
							<input type="text" id="email_capture_stat1_label" placeholder="<?php esc_attr_e( 'Label', 'mindpulse' ); ?>" class="regular-text" value="<?php echo esc_attr( $data['email_capture']['stat1_label'] ?? '' ); ?>" />
							<input type="text" id="email_capture_stat1_value" placeholder="<?php esc_attr_e( 'Value', 'mindpulse' ); ?>" class="regular-text" value="<?php echo esc_attr( $data['email_capture']['stat1_value'] ?? '' ); ?>" />
						</td></tr>
						<tr><th><?php esc_html_e( 'Stat 2', 'mindpulse' ); ?></th><td>
							<input type="text" id="email_capture_stat2_label" placeholder="<?php esc_attr_e( 'Label', 'mindpulse' ); ?>" class="regular-text" value="<?php echo esc_attr( $data['email_capture']['stat2_label'] ?? '' ); ?>" />
							<input type="text" id="email_capture_stat2_value" placeholder="<?php esc_attr_e( 'Value', 'mindpulse' ); ?>" class="regular-text" value="<?php echo esc_attr( $data['email_capture']['stat2_value'] ?? '' ); ?>" />
						</td></tr>
						<tr><th><?php esc_html_e( 'Button text', 'mindpulse' ); ?></th><td><input type="text" id="email_capture_button_text" class="regular-text" value="<?php echo esc_attr( $data['email_capture']['button_text'] ?? '' ); ?>" /></td></tr>
						<tr><th><?php esc_html_e( 'Trust / legal text', 'mindpulse' ); ?></th><td><textarea id="email_capture_trust_text" class="large-text" rows="2"><?php echo esc_textarea( $data['email_capture']['trust_text'] ?? '' ); ?></textarea></td></tr>
					</table>
				</div>

				<!-- Checkout Page -->
				<div class="mp-tab-panel" data-tab="checkout">
					<p class="description"><?php esc_html_e( 'Shown right before the visitor is sent to Stripe Checkout. The actual payment is still handled by Stripe (Settings tab) — this only controls the copy on the page leading up to it.', 'mindpulse' ); ?></p>
					<table class="form-table">
						<tr><th><?php esc_html_e( 'Headline', 'mindpulse' ); ?></th><td><input type="text" id="checkout_headline" class="large-text" value="<?php echo esc_attr( $data['checkout']['headline'] ?? '' ); ?>" /></td></tr>
						<tr><th><?php esc_html_e( 'Price caption', 'mindpulse' ); ?></th><td><input type="text" id="checkout_price_caption" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. one-time payment, no subscription', 'mindpulse' ); ?>" value="<?php echo esc_attr( $data['checkout']['price_caption'] ?? '' ); ?>" /></td></tr>
					</table>
					<h3><?php esc_html_e( 'Benefits list', 'mindpulse' ); ?></h3>
					<div id="mp-checkout-benefits"></div>
					<p><button type="button" class="button" id="mp-add-checkout-benefit"><?php esc_html_e( '+ Add Benefit', 'mindpulse' ); ?></button></p>
				</div>

				<!-- Report Settings -->
				<div class="mp-tab-panel" data-tab="report">
					<table class="form-table">
						<tr><th><?php esc_html_e( 'Hero title', 'mindpulse' ); ?></th><td><input type="text" id="report_hero_title" class="large-text" value="<?php echo esc_attr( $data['report']['hero_title'] ?? '' ); ?>" /></td></tr>
						<tr><th><?php esc_html_e( 'Hero subtitle', 'mindpulse' ); ?></th><td><input type="text" id="report_hero_subtitle" class="large-text" value="<?php echo esc_attr( $data['report']['hero_subtitle'] ?? '' ); ?>" /></td></tr>
						<tr>
							<th><?php esc_html_e( 'IQ-style score display', 'mindpulse' ); ?></th>
							<td>
								<label><input type="checkbox" id="report_show_iq_style" <?php checked( ! empty( $data['report']['show_iq_style'] ) ); ?> /> <?php esc_html_e( 'Show the raw score transformed into an IQ-style number and percentile', 'mindpulse' ); ?></label>
								<p class="description"><?php esc_html_e( 'This is a statistical transform of this quiz\'s own score, not a validated psychometric test — keep the disclaimer below visible on the report.', 'mindpulse' ); ?></p>
							</td>
						</tr>
						<tr><th><?php esc_html_e( 'Certificate', 'mindpulse' ); ?></th><td>
							<label><input type="checkbox" id="report_certificate_enabled" <?php checked( ! empty( $data['report']['certificate_enabled'] ) ); ?> /> <?php esc_html_e( 'Show a certificate with the visitor\'s name, score, and date', 'mindpulse' ); ?></label>
							<p><input type="text" id="report_certificate_title" class="regular-text" placeholder="<?php esc_attr_e( 'Certificate title', 'mindpulse' ); ?>" value="<?php echo esc_attr( $data['report']['certificate_title'] ?? '' ); ?>" /></p>
						</td></tr>
						<tr><th><?php esc_html_e( 'Disclaimer', 'mindpulse' ); ?></th><td><textarea id="report_disclaimer" class="large-text" rows="2" placeholder="<?php esc_attr_e( 'e.g. This score is a self-administered estimate, not a clinical or professionally validated measurement.', 'mindpulse' ); ?>"><?php echo esc_textarea( $data['report']['disclaimer'] ?? '' ); ?></textarea></td></tr>
					</table>

					<h3><?php esc_html_e( 'Classification labels (for IQ-style display)', 'mindpulse' ); ?></h3>
					<p class="description"><?php esc_html_e( 'Maps the IQ-style number to a label, e.g. 130+ = "Exceptionally Gifted". Ignored when IQ-style display is off.', 'mindpulse' ); ?></p>
					<div id="mp-report-classifications"></div>
					<p><button type="button" class="button" id="mp-add-report-classification"><?php esc_html_e( '+ Add Classification', 'mindpulse' ); ?></button></p>
				</div>

				<!-- Custom CSS -->
				<div class="mp-tab-panel" data-tab="css">
					<p class="description"><?php esc_html_e( 'Injected on the frontend only for this quiz, scoped to its own container.', 'mindpulse' ); ?></p>
					<textarea id="custom_css" class="large-text code" rows="16"><?php echo esc_textarea( $data['custom_css'] ?? '' ); ?></textarea>
				</div>

			</div>
		</div>

		<?php submit_button( $quiz_id ? __( 'Update Quiz', 'mindpulse' ) : __( 'Create Quiz', 'mindpulse' ) ); ?>
	</form>
</div>

<script type="application/json" id="mp-quiz-data-seed"><?php echo wp_json_encode( $data ); ?></script>
