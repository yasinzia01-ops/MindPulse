<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$quizzes = get_posts(
	array(
		'post_type'      => 'mp_quiz',
		'posts_per_page' => -1,
		'orderby'        => 'date',
		'order'          => 'DESC',
	)
);
?>
<div class="wrap mp-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Forms', 'mindpulse' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=mindpulse&action=edit' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add New', 'mindpulse' ); ?></a>
	<hr class="wp-header-end" />

	<?php if ( ! empty( $_GET['deleted'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Quiz moved to trash.', 'mindpulse' ); ?></p></div>
	<?php endif; ?>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Form Name', 'mindpulse' ); ?></th>
				<th><?php esc_html_e( 'Questions', 'mindpulse' ); ?></th>
				<th><?php esc_html_e( 'Premium', 'mindpulse' ); ?></th>
				<th><?php esc_html_e( 'Shortcode', 'mindpulse' ); ?></th>
				<th><?php esc_html_e( 'Date', 'mindpulse' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $quizzes ) ) : ?>
				<tr><td colspan="5"><?php esc_html_e( 'No quizzes yet. Click "Add New" to build your first one.', 'mindpulse' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $quizzes as $quiz ) :
				$data = MP_CPT::get_quiz_data( $quiz->ID );
				$edit_url = admin_url( 'admin.php?page=mindpulse&action=edit&quiz_id=' . $quiz->ID );
				?>
				<tr>
					<td>
						<strong><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $quiz->post_title ); ?></a></strong>
						<div class="row-actions">
							<span class="edit"><a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'mindpulse' ); ?></a> | </span>
							<span class="trash">
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
									<?php wp_nonce_field( 'mp_delete_quiz' ); ?>
									<input type="hidden" name="action" value="mp_delete_quiz" />
									<input type="hidden" name="quiz_id" value="<?php echo esc_attr( $quiz->ID ); ?>" />
									<button type="submit" class="button-link" onclick="return confirm('<?php echo esc_js( __( 'Move this quiz to trash?', 'mindpulse' ) ); ?>');"><?php esc_html_e( 'Trash', 'mindpulse' ); ?></button>
								</form>
							</span>
						</div>
					</td>
					<td><?php echo esc_html( count( $data['questions'] ) ); ?></td>
					<td><?php echo ! empty( $data['settings']['is_premium'] ) ? esc_html__( 'Yes', 'mindpulse' ) : esc_html__( 'No', 'mindpulse' ); ?></td>
					<td><code>[mindpulse_quiz id="<?php echo esc_attr( $quiz->ID ); ?>"]</code></td>
					<td><?php echo esc_html( get_the_date( '', $quiz ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
