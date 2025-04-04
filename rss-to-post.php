<?php
/*
Plugin Name: RSS to Post
Description: Fetches multiple RSS feeds and creates posts based on the feed items. Allows admin to manage feeds.
Version: 1.5
Author: mpowerpc@proton.me
*/

require_once plugin_dir_path(__FILE__) . 'rss-to-post-admin.php';
require_once plugin_dir_path(__FILE__) . 'rss-to-post-database.php';
require_once plugin_dir_path(__FILE__) . 'rss-to-post-images.php';

register_activation_hook(__FILE__, 'rss_to_post_activate');
register_deactivation_hook(__FILE__, 'rss_to_post_deactivate');

add_action('rss_to_post_cron_job', 'rss_to_post_fetch_and_create_posts');

add_action('before_delete_post', 'rss_to_post_track_deleted_post');
add_action('before_delete_post', 'rss_to_post_delete_associated_attachments');

function rss_to_post_fetch_and_create_posts() {
	global $wpdb;
	$table_name = $wpdb->prefix . 'rss_to_post_feeds';
	$table_name_deleted = $wpdb->prefix . 'rss_to_post_deleted_guids';

	$feeds = $wpdb->get_results("SELECT * FROM $table_name");

	foreach ($feeds as $feed) {
		$rss = fetch_feed($feed->url);

		if (!is_wp_error($rss)) {
			$max_items = $rss->get_item_quantity(10);
			$rss_items = $rss->get_items(0, $max_items);

			foreach ($rss_items as $item) {
				$guid            = $item->get_guid();
				$post_title      = $item->get_title();
				$post_content    = $item->get_content();
				$post_date       = $item->get_date('Y-m-d H:i:s');
				$post_categories = $item->get_categories();
				$link            = $item->get_link();

				$is_deleted = $wpdb->get_var($wpdb->prepare(
					"SELECT COUNT(*) FROM $table_name_deleted WHERE guid = %s",
					$guid,
					$feed->id
				));

				if ($is_deleted) {
					continue;
				}

				$existing_post = get_posts(array(
					'meta_key'    => 'rss_to_post_guid',
					'meta_value'  => $item->get_id(),
					'post_type'   => 'post',
					'post_status' => 'any',
				));

				if (!$existing_post) {
					preg_match('/<img[^>]+src="([^">]+)"/i', $post_content, $image_match);
					$image_url = isset($image_match[1]) ? $image_match[1] : '';

					$post_content = preg_replace('/<img[^>]+>/i', '', $post_content);

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
						add_post_meta($post_id, 'rss_to_post_guid', $item->get_id());

						add_post_meta($post_id, 'rss_to_post_feed_id', $feed->id);

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

function rss_to_post_update_feed($feed_url, $author_id = 1) {
	global $wpdb;
	$table_name = $wpdb->prefix . 'rss_to_post_feeds';
	$table_name_deleted = $wpdb->prefix . 'rss_to_post_deleted_guids';

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
			$guid            = $item->get_id();
			$post_title      = $item->get_title();
			$post_content    = $item->get_content();
			$post_date       = $item->get_date('Y-m-d H:i:s');
			$post_categories = $item->get_categories();
			$link            = $item->get_link();

			$is_deleted = $wpdb->get_var($wpdb->prepare(
				"SELECT COUNT(*) FROM $table_name_deleted WHERE guid = %s",
				$guid,
				$feed->id
			));

			if ($is_deleted) {
				continue;
			}

			$existing_post = get_posts(array(
				'meta_key'    => 'rss_to_post_guid',
				'meta_value'  => $item->get_id(),
				'post_type'   => 'post',
				'post_status' => 'any',
			));

			if (!$existing_post) {
				preg_match('/<img[^>]+src="([^">]+)"/i', $post_content, $image_match);
				$image_url = isset($image_match[1]) ? $image_match[1] : '';

				$post_content = preg_replace('/<img[^>]+>/i', '', $post_content);

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
					add_post_meta($post_id, 'rss_to_post_guid', $item->get_id());

					add_post_meta($post_id, 'rss_to_post_feed_id', $feed->id);

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

function rss_to_post_append_message($post_content, $article_link, $post_title, $feed_url, $feed_name) {
	$dom = new DOMDocument();
	libxml_use_internal_errors(true);
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
		$feed_host = parse_url($feed_url, PHP_URL_HOST);

		$message = sprintf(
			'<p>The post <a href="%s">%s</a> appeared first on <a href="%s">%s</a>.</p>',
			esc_url($article_link),
			esc_html($post_title),
			esc_url($feed_host),
			esc_html($feed_name)
		);
	}

	$post_content = preg_replace('~<(?:/?body[^>]*)>~i', '', $post_content);
	return $post_content . $message;
}

function rss_to_post_track_deleted_post($post_id) {
	$guid = get_post_meta($post_id, 'rss_to_post_guid', true);
	$feed_id = get_post_meta($post_id, 'rss_to_post_feed_id', true);

	if ($guid && $feed_id) {
		global $wpdb;
		$table_name_deleted = $wpdb->prefix . 'rss_to_post_deleted_guids';

		$wpdb->insert(
			$table_name_deleted,
			array(
				'guid'    => $guid,
				'feed_id' => $feed_id,
			),
			array(
				'%s',
				'%d',
			)
		);
	}

	rss_to_post_delete_associated_attachments($post_id);
}

function rss_to_post_delete_associated_attachments($post_id) {
	$guid = get_post_meta($post_id, 'rss_to_post_guid', true);

	if ($guid) {
		$attachments = get_children(array(
			'post_parent' => $post_id,
			'post_type'   => 'attachment',
			'numberposts' => -1,
			'fields'      => 'ids',
		));

		if ($attachments) {
			foreach ($attachments as $attachment_id) {
				wp_delete_attachment($attachment_id, true);
			}
		}
	}
}

function rss_to_post_activate() {
	rss_to_post_create_table();
	if (!wp_next_scheduled('rss_to_post_cron_job')) {
		wp_schedule_event(time(), 'hourly', 'rss_to_post_cron_job');
	}
}

function rss_to_post_deactivate() {
	wp_clear_scheduled_hook('rss_to_post_cron_job');
}
?>
