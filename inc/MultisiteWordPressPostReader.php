<?php
/**
 * Multisite WordPress Post Reader - Read posts from any site in the WordPress network.
 *
 * Extracts blog_id from multisite URLs and switches context to read posts from any
 * site in the network.
 *
 * @package DMMultisite
 */

namespace DMMultisite;

defined('ABSPATH') || exit;

class MultisiteWordPressPostReader {

    /**
     * Read WordPress post content from any site in the multisite network.
     *
     * @param array $parameters Contains 'url' and optional 'include_meta'
     * @param array $tool_def Tool definition (unused)
     * @return array Post data with success status and site context
     */
    public static function handle_tool_call(array $parameters, array $tool_def = []): array {

        if (empty($parameters['url'])) {
            return [
                'success' => false,
                'error' => 'WordPress Post Reader tool call missing required url parameter',
                'tool_name' => 'wordpress_post_reader'
            ];
        }

        $source_url = sanitize_url($parameters['url']);
        $include_meta = !empty($parameters['include_meta']);

        // Extract blog_id from multisite URL
        $blog_id = self::get_blog_id_from_url($source_url);

        if (!$blog_id) {
            return [
                'success' => false,
                'error' => sprintf('Could not determine site from URL: %s', $source_url),
                'tool_name' => 'wordpress_post_reader'
            ];
        }

        // Switch to the target site
        switch_to_blog($blog_id);

        $site_name = get_bloginfo('name');
        $site_url = get_site_url();

        // Extract post ID from URL
        $post_id = url_to_postid($source_url);

        if (!$post_id) {
            restore_current_blog();
            return [
                'success' => false,
                'error' => sprintf('Could not extract valid WordPress post ID from URL: %s', $source_url),
                'tool_name' => 'wordpress_post_reader'
            ];
        }

        $post = get_post($post_id);

        if (!$post || $post->post_status === 'trash') {
            restore_current_blog();
            return [
                'success' => false,
                'error' => sprintf('Post at URL %s (ID: %d) not found or is trashed', $source_url, $post_id),
                'tool_name' => 'wordpress_post_reader'
            ];
        }

        $title = $post->post_title ?: '';
        $content = $post->post_content ?: '';
        $excerpt = $post->post_excerpt ?: '';
        $permalink = get_permalink($post_id);
        $post_type = get_post_type($post_id);
        $post_status = $post->post_status;
        $publish_date = get_the_date('Y-m-d H:i:s', $post_id);
        $author_name = get_the_author_meta('display_name', $post->post_author);

        $content_length = strlen($content);
        $content_word_count = str_word_count(wp_strip_all_tags($content));

        // Featured image
        $featured_image_url = null;
        $featured_image_id = get_post_thumbnail_id($post_id);
        if ($featured_image_id) {
            $featured_image_url = wp_get_attachment_image_url($featured_image_id, 'full');
        }

        // Categories and tags
        $categories = wp_get_post_categories($post_id, ['fields' => 'names']);
        $tags = wp_get_post_tags($post_id, ['fields' => 'names']);

        $response_data = [
            'post_id' => $post_id,
            'title' => $title,
            'content' => $content,
            'excerpt' => $excerpt,
            'content_length' => $content_length,
            'content_word_count' => $content_word_count,
            'permalink' => $permalink,
            'post_type' => $post_type,
            'post_status' => $post_status,
            'publish_date' => $publish_date,
            'author' => $author_name,
            'featured_image' => $featured_image_url,
            'categories' => $categories,
            'tags' => $tags,
            'site_name' => $site_name,
            'site_url' => $site_url,
            'site_id' => $blog_id
        ];

        if ($include_meta) {
            $meta_fields = get_post_meta($post_id);
            $clean_meta = [];
            foreach ($meta_fields as $key => $values) {
                if (strpos($key, '_') === 0) {
                    continue;
                }
                $clean_meta[$key] = count($values) === 1 ? $values[0] : $values;
            }
            $response_data['meta_fields'] = $clean_meta;
        } else {
            $response_data['meta_fields'] = [];
        }

        restore_current_blog();

        return [
            'success' => true,
            'data' => $response_data,
            'tool_name' => 'wordpress_post_reader'
        ];
    }

    /**
     * Extract blog_id from multisite URL.
     *
     * @param string $url WordPress post URL
     * @return int|null Blog ID or null if cannot be determined
     */
    private static function get_blog_id_from_url($url) {
        // Parse URL to get host and path
        $parsed_url = parse_url($url);
        if (!$parsed_url) {
            return null;
        }

        $host = $parsed_url['host'] ?? '';
        $path = $parsed_url['path'] ?? '/';

        // Get site by domain (for subdomain multisite)
        $site = get_site_by_path($host, $path);

        if ($site) {
            return $site->blog_id;
        }

        // Fallback: try to match against all sites
        $sites = get_sites(['number' => 999]);
        foreach ($sites as $network_site) {
            $site_url = get_site_url($network_site->blog_id);
            if (strpos($url, $site_url) === 0) {
                return $network_site->blog_id;
            }
        }

        // Final fallback: use current site
        return get_current_blog_id();
    }

    /**
     * Check if tool is configured (always true for multisite post reader).
     *
     * @return bool Always returns true
     */
    public static function is_configured(): bool {
        return true;
    }
}
