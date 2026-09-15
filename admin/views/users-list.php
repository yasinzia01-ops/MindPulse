<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$table = new MP_Users_List_Table();
$table->prepare_items();
?>
<div class="wrap mp-wrap">
	<h1><?php esc_html_e( 'Users', 'mindpulse' ); ?></h1>
	<p class="description"><?php esc_html_e( 'Everyone who has completed a quiz, with their score and result profile.', 'mindpulse' ); ?></p>
	<form method="post">
		<?php
		wp_nonce_field( 'bulk-' . 'submissions' );
		$table->display();
		?>
	</form>
</div>
