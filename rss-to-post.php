<?php
/*
Plugin Name: RSS to Post
Description: Fetches multiple RSS feeds and creates posts based on the feed items. Allows admin to manage feeds.
Version: 1.3
Author: mpowerpc@proton.me
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
    $rss_feed_urls = get_option('rss_to_post_feed_urls', array());

    foreach ($rss_feed_urls as $rss_feed_url) {
        $rss = fetch_feed($rss_feed_url);

        if (!is_wp_error($rss)) {
            $max_items = $rss->get_item_quantity(5);
            $rss_items = $rss->get_items(0, $max_items);

            foreach ($rss_items as $item) {
                $post_title = $item->get_title();
                $post_content = $item->get_content();
                $post_date = $item->get_date('Y-m-d H:i:s');
                $post_categories = $item->get_categories();

                $existing_post = get_page_by_title($post_title, OBJECT, 'post');

                if (!$existing_post) {
                    $category_ids = array();
                    if ($post_categories) {
                        foreach ($post_categories as $category) {
                            $term = term_exists($category->get_label(), 'category');
                            if ($term === 0 || $term === null) {
                                $term = wp_insert_term($category->get_label(), 'category');
                            }
                            if (!is_wp_error($term)) {
                                $category_ids[] = $term['term_id'];
                            }
                        }
                    }

                    wp_insert_post(array(
                        'post_title' => $post_title,
                        'post_content' => $post_content,
                        'post_status' => 'publish',
                        'post_date' => $post_date,
                        'post_author' => 1,
                        'post_category' => $category_ids
                    ));
                }
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
    register_setting('rss_to_post', 'rss_to_post_feed_urls');

    add_settings_section(
        'rss_to_post_section',
        __('RSS to Post Settings', 'rss_to_post'),
        'rss_to_post_settings_section_callback',
        'rss_to_post'
    );

    add_settings_field(
        'rss_to_post_feed_urls',
        __('RSS Feed URLs', 'rss_to_post'),
        'rss_to_post_feed_urls_render',
        'rss_to_post',
        'rss_to_post_section'
    );
}

function rss_to_post_feed_urls_render() {
    $feed_urls = get_option('rss_to_post_feed_urls', array());
    ?>
    <table>
        <tr>
            <th><?php _e('Feed URL', 'rss_to_post'); ?></th>
            <th><?php _e('Actions', 'rss_to_post'); ?></th>
        </tr>
        <?php foreach ($feed_urls as $index => $url): ?>
            <tr>
                <td><input type='text' name='rss_to_post_feed_urls[]' value='<?php echo esc_attr($url); ?>'></td>
                <td>
                    <a href="#" class="remove-feed">Remove</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
    <button type="button" class="button add-feed"><?php _e('Add Feed', 'rss_to_post'); ?></button>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelector('.add-feed').addEventListener('click', function() {
                var table = document.querySelector('table');
                var newRow = table.insertRow();
                var cell1 = newRow.insertCell(0);
                var cell2 = newRow.insertCell(1);
                cell1.innerHTML = "<input type='text' name='rss_to_post_feed_urls[]' value=''>";
                cell2.innerHTML = "<a href='#' class='remove-feed'>Remove</a>";
            });

            document.querySelectorAll('.remove-feed').forEach(function(link) {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    var row = this.closest('tr');
                    row.remove();
                });
            });
        });
    </script>
    <?php
}

function rss_to_post_settings_section_callback() {
    echo __('Enter the URLs of the RSS feeds to fetch posts from.', 'rss_to_post');
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
