<?php
/*
File: rss-to-post-images.php
Description: Fetches images from RSS feeds and scrapes article URL if they don't exist to populate featured images.
Version: 1.5
Author: mpowerpc@proton.me
*/

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
?>
