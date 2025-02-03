<?php

// Add settings page under Tools
add_action('admin_menu', 'rss_to_post_add_admin_menu');

// AJAX actions for managing feeds
add_action('wp_ajax_rss_to_post_add_feed', 'rss_to_post_add_feed');
add_action('wp_ajax_rss_to_post_remove_feed', 'rss_to_post_remove_feed');
add_action('wp_ajax_rss_to_post_update_feed', 'rss_to_post_update_feed_handler');

function rss_to_post_add_admin_menu() {
	add_management_page('RSS to Post', 'RSS to Post', 'manage_options', 'rss_to_post', 'rss_to_post_options_page');
}

function rss_to_post_options_page() {
	global $wpdb;
	$table_name = $wpdb->prefix . 'rss_to_post_feeds';
	$feeds = $wpdb->get_results("SELECT * FROM $table_name");
	?>
    <div class="wrap">
        <h1>RSS to Post Settings</h1>
        <table class="wp-list-table widefat fixed striped table-view-list rss-to-post-table">
            <thead>
            <tr>
                <th><?php _e('Name', 'rss_to_post'); ?></th>
                <th><?php _e('Feed URL', 'rss_to_post'); ?></th>
                <th><?php _e('Author', 'rss_to_post'); ?></th>
                <th class="actions-column"><?php _e('Actions', 'rss_to_post'); ?></th>
            </tr>
            </thead>
            <tbody id="the-list">
			<?php foreach ($feeds as $feed): ?>
                <tr data-feed-id="<?php echo esc_attr($feed->id); ?>">
                    <td><?php echo esc_html($feed->name); ?></td>
                    <td><?php echo esc_url($feed->url); ?></td>
                    <td>
						<?php
						$author = get_user_by('ID', $feed->author_id);
						echo esc_html($author ? $author->display_name : 'Unknown');
						?>
                    </td>
                    <td class="actions-column">
                        <button type="button" class="button update-feed" data-feed-id="<?php echo esc_attr($feed->id); ?>"><?php _e('Update Now', 'rss_to_post'); ?></button>
                        <button type="button" class="button remove-feed" data-feed-id="<?php echo esc_attr($feed->id); ?>"><?php _e('Remove', 'rss_to_post'); ?></button>
                    </td>
                </tr>
			<?php endforeach; ?>
            </tbody>
        </table>
        <h2><?php _e('Add New Feed', 'rss_to_post'); ?></h2>
        <table class="form-table">
            <tr>
                <th><label for="rss_to_post_feed_name"><?php _e('Name', 'rss_to_post'); ?></label></th>
                <td><input type="text" id="rss_to_post_feed_name" name="rss_to_post_feed_name" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="rss_to_post_feed_url"><?php _e('Feed URL', 'rss_to_post'); ?></label></th>
                <td><input type="text" id="rss_to_post_feed_url" name="rss_to_post_feed_url" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="rss_to_post_feed_author"><?php _e('Author', 'rss_to_post'); ?></label></th>
                <td>
					<?php
					wp_dropdown_users(array(
						'name' => 'rss_to_post_feed_author',
						'id' => 'rss_to_post_feed_author',
						'show_option_all' => false,
						'selected' => get_current_user_id(),
					));
					?>
                </td>
            </tr>
            <tr>
                <th></th>
                <td><button type="button" class="button button-primary" id="add-feed-button"><?php _e('Add Feed', 'rss_to_post'); ?></button></td>
            </tr>
        </table>
    </div>
    <script>
        (function($){
            $('#add-feed-button').on('click', function(){
                var name = $('#rss_to_post_feed_name').val();
                var url = $('#rss_to_post_feed_url').val();
                var author_id = $('#rss_to_post_feed_author').val();

                if(name && url && author_id){
                    $.post(ajaxurl, {
                        action: 'rss_to_post_add_feed',
                        name: name,
                        url: url,
                        author_id: author_id,
                        security: '<?php echo wp_create_nonce("rss_to_post_nonce"); ?>'
                    }, function(response){
                        if(response.success){
                            location.reload();
                        } else {
                            alert(response.data);
                        }
                    });
                } else {
                    alert('Please enter Name, Feed URL, and select an Author.');
                }
            });

            $('.remove-feed').on('click', function(){
                var feedId = $(this).data('feed-id');
                if(confirm('Are you sure you want to remove this feed?')){
                    $.post(ajaxurl, {
                        action: 'rss_to_post_remove_feed',
                        feed_id: feedId,
                        security: '<?php echo wp_create_nonce("rss_to_post_nonce"); ?>'
                    }, function(response){
                        if(response.success){
                            $('tr[data-feed-id="' + feedId + '"]').remove();
                        } else {
                            alert(response.data);
                        }
                    });
                }
            });

            $('.update-feed').on('click', function(){
                var feedId = $(this).data('feed-id');
                $.post(ajaxurl, {
                    action: 'rss_to_post_update_feed',
                    feed_id: feedId,
                    security: '<?php echo wp_create_nonce("rss_to_post_nonce"); ?>'
                }, function(response){
                    if(response.success){
                        alert('Feed updated successfully!');
                    } else {
                        alert('Error updating feed: ' + response.data);
                    }
                });
            });

        })(jQuery);
    </script>
	<?php
}

function rss_to_post_add_feed() {
	check_ajax_referer('rss_to_post_nonce', 'security');

	if (!current_user_can('manage_options')) {
		wp_send_json_error('Unauthorized');
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'rss_to_post_feeds';

	$name = sanitize_text_field($_POST['name']);
	$url = esc_url_raw($_POST['url']);
	$author_id = intval($_POST['author_id']);

	if (empty($name) || empty($url) || empty($author_id)) {
		wp_send_json_error('Name, URL, and Author cannot be empty.');
	}

	$user = get_user_by('ID', $author_id);
	if (!$user) {
		wp_send_json_error('Invalid author selected.');
	}

	$existing_feed = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_name WHERE url = %s", $url));

	if ($existing_feed) {
		wp_send_json_error('Feed already exists.');
	}

	$result = $wpdb->insert($table_name, array(
		'name'      => $name,
		'url'       => $url,
		'author_id' => $author_id
	));

	if ($result !== false) {
		wp_send_json_success();
	} else {
		wp_send_json_error('Failed to add feed.');
	}
}

function rss_to_post_remove_feed() {
	check_ajax_referer('rss_to_post_nonce', 'security');

	if (!current_user_can('manage_options')) {
		wp_send_json_error('Unauthorized');
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'rss_to_post_feeds';

	$feed_id = intval($_POST['feed_id']);

	$result = $wpdb->delete($table_name, array('id' => $feed_id));

	if ($result !== false) {
		wp_send_json_success();
	} else {
		wp_send_json_error('Failed to remove feed.');
	}
}

function rss_to_post_update_feed_handler() {
	check_ajax_referer('rss_to_post_nonce', 'security');

	if (!current_user_can('manage_options')) {
		wp_send_json_error('Unauthorized');
	}

	global $wpdb;
	$feed_id = intval($_POST['feed_id']);
	$table_name = $wpdb->prefix . 'rss_to_post_feeds';

	$feed = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $feed_id));

	if (!$feed) {
		wp_send_json_error('Feed not found.');
	}

	$result = rss_to_post_update_feed($feed->url, $feed->author_id);

	if ($result === 'Feed updated successfully!') {
		wp_send_json_success();
	} else {
		wp_send_json_error($result);
	}
}

?>
