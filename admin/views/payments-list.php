<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$table = new MP_Payments_List_Table();
$table->prepare_items();
?>
<div class="wrap mp-wrap">
	<h1><?php esc_html_e( 'Payments', 'mindpulse' ); ?></h1>
	<p class="description"><?php esc_html_e( 'Transactions for premium (paid) quiz results. Configure your Stripe keys under Settings.', 'mindpulse' ); ?></p>
	<?php $table->display(); ?>
</div>
