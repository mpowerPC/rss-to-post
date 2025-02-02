<?php
/*
Plugin Name: RSS to Post
Description: Fetches an RSS feed and creates posts based on the feed items.
Version: 1.0
Author: mpower
*/

function rss_to_post_activate() {
    if (! wp_next_scheduled('rss_to_post_cron_job')) {
        wp_schedule_event(time(), 'hourly', 'rss_to_post_cron_job');
    }
}
register_activation_hook(__FILE__, 'rss_to_post_activate');

function rss_to_post_deactivate() {
    wp_clear_scheduled_hook('rss_to_post_cron_job');
}
register_deactivation_hook(__FILE__, 'rss_to_post_deactivate');

add_action('rss_to_post_cron_job', 'rss_to_post_fetch_and_create_posts');

function rss_to_post_fetch_and_create_posts() {
    $rss_feed_url = 'https://www.mindbodygreen.com/rss/feed.xml';
    $rss = fetch_feed($rss_feed_url);

    if (!is_wp_error($rss)) {
        $max_items = $rss->get_item_quantity(5);
        $rss_items = $rss->get_items(0, $max_items);

        foreach ($rss_items as $item) {
            $post_title = $item->get_title();
            $post_content = $item->get_content();
            $post_date = $item->get_date('Y-m-d H:i:s');

            $existing_post = get_page_by_title($post_title, OBJECT, 'post');

            if (!$existing_post) {
                wp_insert_post(array(
                    'post_title' => $post_title,
                    'post_content' => $post_content,
                    'post_status' => 'publish',
                    'post_date' => $post_date,
                    'post_author' => 1,
                ));
            }
        }
    }
}
?>
