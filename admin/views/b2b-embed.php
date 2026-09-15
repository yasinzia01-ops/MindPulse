<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$partners = $wpdb->get_results( 'SELECT * FROM ' . MP_DB::partners_table() . ' ORDER BY id DESC' );
$quizzes  = get_posts( array( 'post_type' => 'mp_quiz', 'posts_per_page' => -1 ) );
?>
<div class="wrap mp-wrap">
	<h1><?php esc_html_e( 'B2B Embed', 'mindpulse' ); ?></h1>
	<p class="description"><?php esc_html_e( 'Create an API key per partner, then give them the embed snippet below to iframe a quiz on their own site. Their submissions are tagged with the partner in Users.', 'mindpulse' ); ?></p>

	<?php if ( ! empty( $_GET['created'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Partner created.', 'mindpulse' ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'mp_create_partner' ); ?>
		<input type="hidden" name="action" value="mp_create_partner" />
		<table class="form-table">
			<tr>
				<th><label for="partner_name"><?php esc_html_e( 'Partner Name', 'mindpulse' ); ?></label></th>
				<td><input type="text" id="partner_name" name="partner_name" class="regular-text" required /></td>
			</tr>
		</table>
		<?php submit_button( __( 'Create Partner', 'mindpulse' ) ); ?>
	</form>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Partner', 'mindpulse' ); ?></th>
				<th><?php esc_html_e( 'API Key', 'mindpulse' ); ?></th>
				<th><?php esc_html_e( 'Embed Snippet', 'mindpulse' ); ?></th>
				<th><?php esc_html_e( 'Status', 'mindpulse' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $partners ) ) : ?>
				<tr><td colspan="4"><?php esc_html_e( 'No partners yet.', 'mindpulse' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $partners as $partner ) :
				$example_quiz_id = ! empty( $quizzes ) ? $quizzes[0]->ID : 0;
				?>
				<tr>
					<td><?php echo esc_html( $partner->name ); ?></td>
					<td><code><?php echo esc_html( $partner->api_key ); ?></code></td>
					<td>
						<code style="word-break:break-all;display:block;max-width:420px;"><?php echo esc_html( MP_Embed::get_embed_snippet( $example_quiz_id, $partner->api_key ) ); ?></code>
						<p class="description"><?php esc_html_e( 'Replace data-quiz with the target quiz ID (see Forms).', 'mindpulse' ); ?></p>
					</td>
					<td><?php echo esc_html( ucfirst( $partner->status ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
