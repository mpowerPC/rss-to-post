<?php
/*
File: rss-to-post-database.php
Description: Creates and updates the database for rss-to-post
Version: 1.5
Author: mpowerpc@proton.me
*/

global $rss_to_post_db_version;
$rss_to_post_db_version = '1.3';

add_action('plugins_loaded', 'rss_to_post_upgrade_db');

function rss_to_post_create_table() {
	global $wpdb;
	global $rss_to_post_db_version;

	$table_name_feeds = $wpdb->prefix . 'rss_to_post_feeds';
	$table_name_deleted = $wpdb->prefix . 'rss_to_post_deleted_guids';

	$charset_collate = $wpdb->get_charset_collate();

	$sql_feeds = "CREATE TABLE $table_name_feeds (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name text NOT NULL,
        url text NOT NULL,
        author_id bigint(20) unsigned NOT NULL DEFAULT '1',
        PRIMARY KEY (id)
    ) $charset_collate;";

	$sql_deleted = "CREATE TABLE $table_name_deleted (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        guid varchar(255) NOT NULL,
        feed_id mediumint(9) NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY guid_feed (guid, feed_id)
    ) $charset_collate;";

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql_feeds);
	dbDelta($sql_deleted);

	update_option('rss_to_post_db_version', $rss_to_post_db_version);
}

function rss_to_post_upgrade_db() {
	global $rss_to_post_db_version;
	$installed_version = get_option('rss_to_post_db_version');

	if ($installed_version != $rss_to_post_db_version) {
		rss_to_post_create_table();
	}
}
?>
