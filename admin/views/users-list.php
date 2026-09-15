<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$table = new MP_Users_List_Table();
$table->prepare_items();
?>
<div class="wrap mp-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Users', 'mindpulse' ); ?></h1>
	<a
		href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=mp_export_submissions_csv' ), 'mp_export_csv' ) ); ?>"
		class="page-title-action"
	><?php esc_html_e( 'Export CSV', 'mindpulse' ); ?></a>
	<hr class="wp-header-end" />
	<p class="description"><?php esc_html_e( 'Everyone who has completed a quiz, with their score and result profile.', 'mindpulse' ); ?></p>
	<form method="post">
		<?php
		wp_nonce_field( 'bulk-' . 'submissions' );
		$table->display();
		?>
	</form>
</div>
