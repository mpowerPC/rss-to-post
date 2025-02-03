<?php
/*
Plugin Name: RSS to Post
Description: Fetches multiple RSS feeds and creates posts based on the feed items. Allows admin to manage feeds.
Version: 1.4
Author: mpowerpc@proton.me
*/

require_once plugin_dir_path(__FILE__) . 'rss-to-post-admin.php';

global $rss_to_post_db_version;
$rss_to_post_db_version = '1.1'; // Ensure this matches your current DB version

// Function to grab all feed
function rss_to_post_fetch_and_create_posts() {
	global $wpdb;
	$table_name = $wpdb->prefix . 'rss_to_post_feeds';

	$feeds = $wpdb->get_results("SELECT * FROM $table_name");

	foreach ($feeds as $feed) {
		$rss = fetch_feed($feed->url);

		if (!is_wp_error($rss)) {
			$max_items = $rss->get_item_quantity(5);
			$rss_items = $rss->get_items(0, $max_items);

			foreach ($rss_items as $item) {
				$post_title      = $item->get_title();
				$post_content    = $item->get_content(); // HTML content
				$post_date       = $item->get_date('Y-m-d H:i:s');
				$post_categories = $item->get_categories();
				$link            = $item->get_link();

				// Check if post already exists by GUID
				$existing_post = get_posts(array(
					'meta_key'    => 'rss_to_post_guid',
					'meta_value'  => $item->get_id(),
					'post_type'   => 'post',
					'post_status' => 'any',
				));

				if (!$existing_post) {
					// Extract the first image from the feed content
					preg_match('/<img[^>]+src="([^">]+)"/i', $post_content, $image_match);
					$image_url = isset($image_match[1]) ? $image_match[1] : '';

					// Remove images from content (optional)
					$post_content = preg_replace('/<img[^>]+>/i', '', $post_content);

					// Append message if the last paragraph doesn't contain the link
					$post_content = rss_to_post_append_message($post_content, $link, $post_title, $feed->url, $feed->name);

					// If no image in the feed content, try fetching from the linked article
					if (empty($image_url) && !empty($link)) {
						$image_url = rss_to_post_get_image_from_article($link);
					}

					$category_ids = array();
					if ($post_categories) {
						foreach ($post_categories as $category) {
							$term = term_exists($category->get_label(), 'category');
							if (!$term) {
								$term = wp_insert_term($category->get_label(), 'category');
							}
							if (!is_wp_error($term)) {
								$category_ids[] = $term['term_id'];
							}
						}
					}

					$post_id = wp_insert_post(array(
						'post_title'    => $post_title,
						'post_content'  => $post_content,
						'post_status'   => 'publish',
						'post_date'     => $post_date,
						'post_author'   => $feed->author_id,
						'post_category' => $category_ids,
					));

					if ($post_id) {
						// Save GUID to prevent duplicates
						add_post_meta($post_id, 'rss_to_post_guid', $item->get_id());

						// If an image URL was found, set it as the featured image
						if ($image_url) {
							$image_id = rss_to_post_media_sideload_image($image_url, $post_id, $post_title);
							if (!is_wp_error($image_id)) {
								set_post_thumbnail($post_id, $image_id);
							}
						}
					}
				}
			}
		}
	}
}

// Function to update a single feed
function rss_to_post_update_feed($feed_url, $author_id = 1) {
	global $wpdb;
	$table_name = $wpdb->prefix . 'rss_to_post_feeds';

	// Get the feed details
	$feed = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE url = %s", $feed_url));

	if (!$feed) {
		return 'Feed not found.';
	}

	$rss = fetch_feed($feed_url);

	if (is_wp_error($rss)) {
		return $rss->get_error_message();
	} else {
		$max_items = $rss->get_item_quantity(5);
		$rss_items = $rss->get_items(0, $max_items);

		foreach ($rss_items as $item) {
			$post_title      = $item->get_title();
			$post_content    = $item->get_content();
			$post_date       = $item->get_date('Y-m-d H:i:s');
			$post_categories = $item->get_categories();
			$link            = $item->get_link();

			// Check if post already exists by GUID
			$existing_post = get_posts(array(
				'meta_key'    => 'rss_to_post_guid',
				'meta_value'  => $item->get_id(),
				'post_type'   => 'post',
				'post_status' => 'any',
			));

			if (!$existing_post) {
				// Extract the first image from the feed content
				preg_match('/<img[^>]+src="([^">]+)"/i', $post_content, $image_match);
				$image_url = isset($image_match[1]) ? $image_match[1] : '';

				// Remove images from content (optional)
				$post_content = preg_replace('/<img[^>]+>/i', '', $post_content);

				// Append message if the last paragraph doesn't contain the link
				$post_content = rss_to_post_append_message($post_content, $link, $post_title, $feed->url, $feed->name);

				if (empty($image_url) && !empty($link)) {
					$image_url = rss_to_post_get_image_from_article($link);
				}

				$category_ids = array();
				if ($post_categories) {
					foreach ($post_categories as $category) {
						$term = term_exists($category->get_label(), 'category');
						if (!$term) {
							$term = wp_insert_term($category->get_label(), 'category');
						}
						if (!is_wp_error($term)) {
							$category_ids[] = $term['term_id'];
						}
					}
				}

				$post_id = wp_insert_post(array(
					'post_title'    => $post_title,
					'post_content'  => $post_content,
					'post_status'   => 'publish',
					'post_date'     => $post_date,
					'post_author'   => $feed->author_id,
					'post_category' => $category_ids,
				));

				if ($post_id) {
					// Save GUID to prevent duplicates
					add_post_meta($post_id, 'rss_to_post_guid', $item->get_id());

					// If an image URL was found, set it as the featured image
					if ($image_url) {
						$image_id = rss_to_post_media_sideload_image($image_url, $post_id, $post_title);
						if (!is_wp_error($image_id)) {
							set_post_thumbnail($post_id, $image_id);
						}
					}
				}
			}
		}

		return 'Feed updated successfully!';
	}
}

// Helper function to append the message if needed
function rss_to_post_append_message($post_content, $article_link, $post_title, $feed_url, $feed_name) {
	// Parse the post content into a DOMDocument
	$dom = new DOMDocument();
	libxml_use_internal_errors(true); // Suppress warnings
	$dom->loadHTML('<?xml encoding="utf-8" ?>' . $post_content);
	libxml_clear_errors();

	$paragraphs = $dom->getElementsByTagName('p');
	$last_paragraph = null;
	if ($paragraphs->length > 0) {
		$last_paragraph = $paragraphs->item($paragraphs->length - 1);
	}

	$contains_link = false;
	if ($last_paragraph) {
		$links = $last_paragraph->getElementsByTagName('a');
		foreach ($links as $link) {
			$href = $link->getAttribute('href');
			if ($href == $article_link) {
				$contains_link = true;
				break;
			}
		}
	}

	$message = '';
	if (!$contains_link) {
		// Prepare the message
		$feed_host = parse_url($feed_url, PHP_URL_HOST);

		$message = sprintf(
			'<p>The post <a href="%s">%s</a> appeared first on <a href="%s">%s</a>.</p>',
			esc_url($article_link),
			esc_html($post_title),
			esc_url($feed_host),
			esc_html($feed_name)
		);
	}

	// Remove the <body> tags
	$post_content = preg_replace('~<(?:/?body[^>]*)>~i', '', $post_content);
	return $post_content . $message;
}

// Function to load image as into media
function rss_to_post_media_sideload_image($image_url, $post_id, $desc = null) {
	// Check if the image already exists in the media library
	$media = get_posts(array(
		'post_type'  => 'attachment',
		'meta_key'   => '_source_url',
		'meta_value' => $image_url,
		'fields'     => 'ids',
	));
	if ($media) {
		return $media[0]; // Return existing image ID
	}

	// Download and sideload the image
	require_once(ABSPATH . 'wp-admin/includes/media.php');
	require_once(ABSPATH . 'wp-admin/includes/file.php');
	require_once(ABSPATH . 'wp-admin/includes/image.php');

	$tmp = download_url($image_url);
	if (is_wp_error($tmp)) {
		return $tmp;
	}

	$file_array = array();
	$file_array['name']     = basename(parse_url($image_url, PHP_URL_PATH));
	$file_array['tmp_name'] = $tmp;

	// Check for download errors
	if (is_wp_error($file_array['tmp_name'])) {
		@unlink($file_array['tmp_name']);
		return $file_array['tmp_name'];
	}

	$id = media_handle_sideload($file_array, $post_id, $desc);

	// Check for handle sideload errors
	if (is_wp_error($id)) {
		@unlink($file_array['tmp_name']);
		return $id;
	}

	// Save the original image URL
	add_post_meta($id, '_source_url', $image_url);

	return $id;
}

// Function to scrape website for article image
function rss_to_post_get_image_from_article($url) {
	// Fetch the HTML content of the article
	$response = wp_remote_get($url);

	if (is_wp_error($response)) {
		return '';
	}

	$html = wp_remote_retrieve_body($response);

	if (empty($html)) {
		return '';
	}

	// Use DOMDocument to parse HTML (requires libxml extension)
	libxml_use_internal_errors(true); // Suppress warnings

	$doc = new DOMDocument();
	$doc->loadHTML($html);
	libxml_clear_errors();

	$xpath = new DOMXPath($doc);

	// Try to get Open Graph image
	$meta_og_image = $xpath->query("//meta[@property='og:image']");
	if ($meta_og_image->length > 0) {
		$image_url = $meta_og_image->item(0)->getAttribute('content');
		if (!empty($image_url)) {
			return $image_url;
		}
	}

	// Try to get Twitter image
	$meta_twitter_image = $xpath->query("//meta[@name='twitter:image']");
	if ($meta_twitter_image->length > 0) {
		$image_url = $meta_twitter_image->item(0)->getAttribute('content');
		if (!empty($image_url)) {
			return $image_url;
		}
	}

	// Find the first image in the content
	$images = $doc->getElementsByTagName('img');
	if ($images->length > 0) {
		$image_url = $images->item(0)->getAttribute('src');
		if (!empty($image_url)) {
			// Resolve relative URLs
			$image_url = rss_to_post_resolve_url($image_url, $url);
			return $image_url;
		}
	}

	return '';
}

// Function to return absolute url
function rss_to_post_resolve_url($relative_url, $base_url) {
	// If the URL is already absolute, return it
	if (parse_url($relative_url, PHP_URL_SCHEME) != '') {
		return $relative_url;
	}

	// Parse base URL and convert to components
	$base = parse_url($base_url);
	if ($relative_url[0] == '/') {
		$path = '';
	} else {
		$path = dirname($base['path']) . '/';
	}

	// Build absolute URL
	$abs = $base['scheme'] . '://' . $base['host'] . $path . $relative_url;
	return $abs;
}

// Function to create or update the database table
function rss_to_post_create_table() {
	global $wpdb;
	global $rss_to_post_db_version;

	$table_name = $wpdb->prefix . 'rss_to_post_feeds';

	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name text NOT NULL,
        url text NOT NULL,
        author_id bigint(20) unsigned NOT NULL DEFAULT '1',
        PRIMARY KEY (id)
    ) $charset_collate;";

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

	dbDelta($sql);

	update_option('rss_to_post_db_version', $rss_to_post_db_version);
}

// Function to upgrade the database table if needed
function rss_to_post_upgrade_db() {
	global $rss_to_post_db_version;
	$installed_version = get_option('rss_to_post_db_version');

	if ($installed_version != $rss_to_post_db_version) {
		rss_to_post_create_table();
	}
}

add_action('plugins_loaded', 'rss_to_post_upgrade_db');

// Functions to check if plugin is activated
function rss_to_post_activate() {
	rss_to_post_create_table();
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
?>
