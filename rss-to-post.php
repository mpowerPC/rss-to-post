<?php
/*
Plugin Name: RSS to Post
Description: Fetches an RSS feed and creates posts based on the feed items.
Version: 1.2
Author: mpower
*/

function rss_to_post_activate() {
    if (!wp_next_scheduled('rss_to_post_cron_job')) {
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
    $rss_feed_url = get_option('rss_to_post_feed_url', 'https://www.wellandgood.com/feed/');
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

// Add settings page under Tools
add_action('admin_menu', 'rss_to_post_add_admin_menu');
add_action('admin_init', 'rss_to_post_settings_init');

function rss_to_post_add_admin_menu() {
    add_management_page('RSS to Post', 'RSS to Post', 'manage_options', 'rss_to_post', 'rss_to_post_options_page');
}

function rss_to_post_settings_init() {
    register_setting('rss_to_post', 'rss_to_post_settings');

    add_settings_section(
        'rss_to_post_section',
        __('RSS to Post Settings', 'rss_to_post'),
        'rss_to_post_settings_section_callback',
        'rss_to_post'
    );

    add_settings_field(
        'rss_to_post_feed_url',
        __('RSS Feed URL', 'rss_to_post'),
        'rss_to_post_feed_url_render',
        'rss_to_post',
        'rss_to_post_section'
    );
}

function rss_to_post_feed_url_render() {
    $options = get_option('rss_to_post_settings');
    ?>
    <input type='text' name='rss_to_post_settings[rss_to_post_feed_url]' value='<?php echo $options['rss_to_post_feed_url']; ?>'>
    <?php
}

function rss_to_post_settings_section_callback() {
    echo __('Enter the URL of the RSS feed to fetch posts from.', 'rss_to_post');
}

function rss_to_post_options_page() {
    ?>
    <form action='options.php' method='post'>
        <h2>RSS to Post</h2>
        <?php
        settings_fields('rss_to_post');
        do_settings_sections('rss_to_post');
        submit_button();
        ?>
    </form>
    <?php
}
?>

