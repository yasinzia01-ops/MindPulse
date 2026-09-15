<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates and provides access to MindPulse's custom tables.
 */
class MP_DB {

	public static function submissions_table() {
		global $wpdb;
		return $wpdb->prefix . 'mp_submissions';
	}

	public static function leads_table() {
		global $wpdb;
		return $wpdb->prefix . 'mp_leads';
	}

	public static function payments_table() {
		global $wpdb;
		return $wpdb->prefix . 'mp_payments';
	}

	public static function partners_table() {
		global $wpdb;
		return $wpdb->prefix . 'mp_partners';
	}

	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$submissions = self::submissions_table();
		$leads       = self::leads_table();
		$payments    = self::payments_table();
		$partners    = self::partners_table();

		$sql = "CREATE TABLE {$submissions} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			quiz_id BIGINT UNSIGNED NOT NULL,
			lead_name VARCHAR(191) DEFAULT '',
			lead_email VARCHAR(191) DEFAULT '',
			answers LONGTEXT NULL,
			total_score INT NOT NULL DEFAULT 0,
			band_key VARCHAR(64) DEFAULT '',
			profile_title VARCHAR(191) DEFAULT '',
			payment_status VARCHAR(20) NOT NULL DEFAULT 'n/a',
			partner_id BIGINT UNSIGNED DEFAULT NULL,
			lead_id BIGINT UNSIGNED DEFAULT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY quiz_id (quiz_id),
			KEY lead_email (lead_email),
			KEY partner_id (partner_id)
		) {$charset_collate};

		CREATE TABLE {$leads} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			quiz_id BIGINT UNSIGNED NOT NULL,
			lead_name VARCHAR(191) DEFAULT '',
			lead_email VARCHAR(191) DEFAULT '',
			last_step INT NOT NULL DEFAULT 0,
			answers LONGTEXT NULL,
			partner_id BIGINT UNSIGNED DEFAULT NULL,
			converted_submission_id BIGINT UNSIGNED DEFAULT NULL,
			recovery_emails_sent INT NOT NULL DEFAULT 0,
			last_email_sent_at DATETIME NULL,
			resume_token VARCHAR(64) DEFAULT '',
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY quiz_id (quiz_id),
			KEY lead_email (lead_email),
			KEY converted_submission_id (converted_submission_id),
			KEY resume_token (resume_token)
		) {$charset_collate};

		CREATE TABLE {$payments} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			submission_id BIGINT UNSIGNED NOT NULL,
			gateway VARCHAR(40) NOT NULL DEFAULT 'stripe',
			transaction_id VARCHAR(191) DEFAULT '',
			amount DECIMAL(10,2) NOT NULL DEFAULT 0,
			currency VARCHAR(10) NOT NULL DEFAULT 'usd',
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY submission_id (submission_id),
			KEY status (status)
		) {$charset_collate};

		CREATE TABLE {$partners} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL,
			api_key VARCHAR(64) NOT NULL,
			allowed_quiz_ids TEXT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY api_key (api_key)
		) {$charset_collate};";

		dbDelta( $sql );

		update_option( 'mp_db_version', MP_DB_VERSION );
	}
}
