<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$games = get_posts(
	array(
		'post_type'      => 'mp_game',
		'posts_per_page' => -1,
	)
);
?>
<div class="wrap mp-wrap">
	<h1><?php esc_html_e( 'Brain Games', 'mindpulse' ); ?></h1>
	<p class="description"><?php esc_html_e( 'Lightweight brain-training mini games, separate from scored quizzes. Create a game entry below; embed it with the mindpulse_game shortcode.', 'mindpulse' ); ?></p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'mp_save_game' ); ?>
		<input type="hidden" name="action" value="mp_save_game" />
		<table class="form-table">
			<tr>
				<th><label for="game_title"><?php esc_html_e( 'Game Title', 'mindpulse' ); ?></label></th>
				<td><input type="text" id="game_title" name="game_title" class="regular-text" required /></td>
			</tr>
			<tr>
				<th><label for="game_embed_url"><?php esc_html_e( 'Embed URL', 'mindpulse' ); ?></label></th>
				<td><input type="url" id="game_embed_url" name="game_embed_url" class="large-text" placeholder="https://..." /></td>
			</tr>
		</table>
		<?php submit_button( __( 'Add Game', 'mindpulse' ) ); ?>
	</form>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Game', 'mindpulse' ); ?></th>
				<th><?php esc_html_e( 'Shortcode', 'mindpulse' ); ?></th>
				<th><?php esc_html_e( 'Date', 'mindpulse' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $games ) ) : ?>
				<tr><td colspan="3"><?php esc_html_e( 'No games yet.', 'mindpulse' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $games as $game ) : ?>
				<tr>
					<td><?php echo esc_html( $game->post_title ); ?></td>
					<td><code>[mindpulse_game id="<?php echo esc_attr( $game->ID ); ?>"]</code></td>
					<td><?php echo esc_html( get_the_date( '', $game ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
