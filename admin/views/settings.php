<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$from_name  = get_option( 'mp_settings_from_name', get_bloginfo( 'name' ) );
$from_email = get_option( 'mp_settings_from_email', get_bloginfo( 'admin_email' ) );
$stripe_key = get_option( 'mp_settings_stripe_secret_key', '' );
$stripe_wh  = get_option( 'mp_settings_stripe_webhook_secret', '' );
$webhook_url = esc_url( rest_url( 'mindpulse/v1/payment/webhook' ) );
?>
<div class="wrap mp-wrap">
	<h1><?php esc_html_e( 'Settings', 'mindpulse' ); ?></h1>

	<?php if ( ! empty( $_GET['saved'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'mindpulse' ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'mp_save_settings' ); ?>
		<input type="hidden" name="action" value="mp_save_settings" />
		<input type="hidden" name="_mp_settings_page" value="mindpulse-settings" />

		<h2><?php esc_html_e( 'Email', 'mindpulse' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><label for="mp_from_name"><?php esc_html_e( 'From Name', 'mindpulse' ); ?></label></th>
				<td><input type="text" id="mp_from_name" name="mp_settings_from_name" class="regular-text" value="<?php echo esc_attr( $from_name ); ?>" /></td>
			</tr>
			<tr>
				<th><label for="mp_from_email"><?php esc_html_e( 'From Email', 'mindpulse' ); ?></label></th>
				<td><input type="email" id="mp_from_email" name="mp_settings_from_email" class="regular-text" value="<?php echo esc_attr( $from_email ); ?>" /></td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Payments (Stripe)', 'mindpulse' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><label for="mp_stripe_key"><?php esc_html_e( 'Secret Key', 'mindpulse' ); ?></label></th>
				<td><input type="password" id="mp_stripe_key" name="mp_settings_stripe_secret_key" class="regular-text" value="<?php echo esc_attr( $stripe_key ); ?>" autocomplete="off" /></td>
			</tr>
			<tr>
				<th><label for="mp_stripe_wh"><?php esc_html_e( 'Webhook Signing Secret', 'mindpulse' ); ?></label></th>
				<td>
					<input type="password" id="mp_stripe_wh" name="mp_settings_stripe_webhook_secret" class="regular-text" value="<?php echo esc_attr( $stripe_wh ); ?>" autocomplete="off" />
					<p class="description"><?php esc_html_e( 'Point your Stripe webhook at:', 'mindpulse' ); ?> <code><?php echo esc_html( $webhook_url ); ?></code></p>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Save Settings', 'mindpulse' ) ); ?>
	</form>

	<p class="description"><?php esc_html_e( 'Abandon-email delay, subject and body are configured on the Abandon Emails page.', 'mindpulse' ); ?></p>
</div>
