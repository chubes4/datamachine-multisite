<?php
/**
 * Multisite Local Search - Cross-site WordPress content discovery for AI agents.
 *
 * Searches across ALL sites in the WordPress multisite network and returns results
 * with site context (site_name, site_url, site_id).
 *
 * @package DataMachineMultisite
 */

namespace DataMachineMultisite;

defined('ABSPATH') || exit;

use \DataMachine\Engine\AI\Tools\ToolRegistrationTrait;

class MultisiteLocalSearch {

    use ToolRegistrationTrait;

    public function __construct() {
        $this->registerSuccessMessageHandler('local_search');
        $this->registerGlobalTool('local_search', $this->getToolDefinition());
    }

    /**
     * Execute search across all sites in the multisite network.
     *
     * @param array $parameters Contains 'query' and optional 'post_types'
     * @param array $tool_def Tool definition (unused)
     * @return array Search results with success status and site context
     */
    public function handle_tool_call(array $parameters, array $tool_def = []): array {
        $job_id = $parameters['job_id'] ?? null;
        if (!$job_id) {
            return [
                'success' => false,
                'error' => 'job_id parameter is required for multisite operations',
                'tool_name' => 'local_search'
            ];
        }

        if (empty($parameters['query'])) {
            return [
                'success' => false,
                'error' => 'Multisite Local Search tool call missing required query parameter',
                'tool_name' => 'local_search'
            ];
        }

        $query = sanitize_text_field($parameters['query']);
        $max_results_per_site = 5;
        $post_types = $parameters['post_types'] ?? ['post', 'page'];

        if (!is_array($post_types)) {
            $post_types = ['post', 'page'];
        }
        $post_types = array_map('sanitize_text_field', $post_types);

        // Get all sites in the network
        $sites = get_sites([
            'number' => 999,
            'public' => 1,
            'archived' => 0,
            'spam' => 0,
            'deleted' => 0
        ]);

        $all_results = [];
        $total_results_count = 0;
        $sites_searched = 0;

        foreach ($sites as $site) {
            switch_to_blog($site->blog_id);

            $query_args = [
                's' => $query,
                'post_type' => $post_types,
                'post_status' => 'publish',
                'posts_per_page' => $max_results_per_site,
                'orderby' => 'relevance',
                'order' => 'DESC',
                'no_found_rows' => false,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
            ];

            $wp_query = new \WP_Query($query_args);

            if ($wp_query->have_posts()) {
                $site_name = get_bloginfo('name');
                $site_url = get_site_url();

                while ($wp_query->have_posts()) {
                    $wp_query->the_post();

                    $post = get_post();
                    $permalink = get_permalink($post->ID);

                    $excerpt = get_the_excerpt($post->ID);
                    if (empty($excerpt)) {
                        $content = wp_strip_all_tags(get_the_content('', false, $post));
                        $excerpt = wp_trim_words($content, 25, '...');
                    }

                    $all_results[] = [
                        'title' => get_the_title($post->ID),
                        'link' => $permalink,
                        'excerpt' => $excerpt,
                        'post_type' => get_post_type($post->ID),
                        'publish_date' => get_the_date('Y-m-d H:i:s', $post->ID),
                        'author' => get_the_author_meta('display_name', $post->post_author),
                        'site_name' => $site_name,
                        'site_url' => $site_url,
                        'site_id' => $site->blog_id
                    ];
                }

                wp_reset_postdata();
            }

            $sites_searched++;
            restore_current_blog();
        }

        $results_count = count($all_results);

        return [
            'success' => true,
            'data' => [
                'query' => $query,
                'results_count' => $results_count,
                'sites_searched' => $sites_searched,
                'post_types_searched' => $post_types,
                'max_results_per_site' => $max_results_per_site,
                'results' => $all_results
            ],
            'tool_name' => 'local_search'
        ];
    }

    /**
     * Check if tool is configured (always true for multisite local search).
     *
     * @return bool Always returns true
     */
    public static function is_configured(): bool {
        return true;
    }

    /**
     * Get tool definition for registration.
     *
     * @return array Tool definition array
     */
    private function getToolDefinition(): array {
        return [
            'class' => self::class,
            'method' => 'handle_tool_call',
            'description' => 'Search across ALL sites in the WordPress multisite network and return structured JSON results with post titles, excerpts, permalinks, and site context. Use to find existing content before creating new content. Returns complete search data in JSON format.',
            'requires_config' => false,
            'parameters' => [
                'query' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'Search terms to find relevant posts across all network sites. Returns JSON with "results" array containing title, link, excerpt, post_type, publish_date, author, site_name, site_url for each match.'
                ],
                'post_types' => [
                    'type' => 'array',
                    'required' => false,
                    'description' => 'Post types to search (default: ["post", "page"]). Available types depend on site configuration.'
                ],
                'job_id' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'Job ID for tracking workflow execution'
                ]
            ]
        ];
    }

    /**
     * Get searchable post types across the network.
     *
     * @return array Array of searchable post type names
     */
    public static function get_searchable_post_types(): array {
        $post_types = get_post_types([
            'public' => true,
            'exclude_from_search' => false
        ], 'names');

        return array_values($post_types);
    }
}
