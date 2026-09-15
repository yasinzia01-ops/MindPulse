<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$table = MP_DB::leads_table();
$leads = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC LIMIT 50" );

$enabled = get_option( 'mp_settings_abandon_enabled', true );
$delay   = get_option( 'mp_settings_abandon_delay_minutes', 30 );
$count   = get_option( 'mp_settings_abandon_sequence_count', 1 );
$subject = get_option( 'mp_settings_abandon_subject', __( 'Finish your {quiz_title} results', 'mindpulse' ) );
$body    = get_option( 'mp_settings_abandon_body', __( "Hi {first_name},\n\nYou're almost done with {quiz_title}! Click below to see your results.\n\n{resume_link}", 'mindpulse' ) );
?>
<div class="wrap mp-wrap">
	<h1><?php esc_html_e( 'Abandon Emails', 'mindpulse' ); ?></h1>
	<p class="description"><?php esc_html_e( 'Recover leads who started a quiz but never finished. Placeholders: {first_name}, {quiz_title}, {resume_link}.', 'mindpulse' ); ?></p>

	<?php if ( ! empty( $_GET['saved'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'mindpulse' ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'mp_save_settings' ); ?>
		<input type="hidden" name="action" value="mp_save_settings" />
		<input type="hidden" name="_mp_settings_page" value="mindpulse-abandon-emails" />
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Enabled', 'mindpulse' ); ?></th>
				<td><label><input type="checkbox" name="mp_settings_abandon_enabled" value="1" <?php checked( $enabled ); ?> /> <?php esc_html_e( 'Automatically email leads who abandon a quiz', 'mindpulse' ); ?></label></td>
			</tr>
			<tr>
				<th><label for="mp_delay"><?php esc_html_e( 'Delay before first email', 'mindpulse' ); ?></label></th>
				<td><input type="number" id="mp_delay" name="mp_settings_abandon_delay_minutes" value="<?php echo esc_attr( $delay ); ?>" min="5" /> <?php esc_html_e( 'minutes', 'mindpulse' ); ?></td>
			</tr>
			<tr>
				<th><label for="mp_count"><?php esc_html_e( 'Number of follow-up emails', 'mindpulse' ); ?></label></th>
				<td><input type="number" id="mp_count" name="mp_settings_abandon_sequence_count" value="<?php echo esc_attr( $count ); ?>" min="1" max="5" /></td>
			</tr>
			<tr>
				<th><label for="mp_subject"><?php esc_html_e( 'Email subject', 'mindpulse' ); ?></label></th>
				<td><input type="text" id="mp_subject" name="mp_settings_abandon_subject" class="large-text" value="<?php echo esc_attr( $subject ); ?>" /></td>
			</tr>
			<tr>
				<th><label for="mp_body"><?php esc_html_e( 'Email body', 'mindpulse' ); ?></label></th>
				<td><textarea id="mp_body" name="mp_settings_abandon_body" class="large-text" rows="6"><?php echo esc_textarea( $body ); ?></textarea></td>
			</tr>
		</table>
		<?php submit_button( __( 'Save Abandon Email Settings', 'mindpulse' ) ); ?>
	</form>

	<h2><?php esc_html_e( 'Recent Abandoned Leads', 'mindpulse' ); ?></h2>
	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Name', 'mindpulse' ); ?></th>
				<th><?php esc_html_e( 'Email', 'mindpulse' ); ?></th>
				<th><?php esc_html_e( 'Quiz', 'mindpulse' ); ?></th>
				<th><?php esc_html_e( 'Emails Sent', 'mindpulse' ); ?></th>
				<th><?php esc_html_e( 'Converted', 'mindpulse' ); ?></th>
				<th><?php esc_html_e( 'Started', 'mindpulse' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $leads ) ) : ?>
				<tr><td colspan="6"><?php esc_html_e( 'No leads yet.', 'mindpulse' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $leads as $lead ) : ?>
				<tr>
					<td><?php echo esc_html( $lead->lead_name ); ?></td>
					<td><?php echo esc_html( $lead->lead_email ); ?></td>
					<td><?php echo esc_html( get_the_title( $lead->quiz_id ) ); ?></td>
					<td><?php echo esc_html( $lead->recovery_emails_sent ); ?></td>
					<td><?php echo $lead->converted_submission_id ? esc_html__( 'Yes', 'mindpulse' ) : esc_html__( 'No', 'mindpulse' ); ?></td>
					<td><?php echo esc_html( $lead->created_at ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
