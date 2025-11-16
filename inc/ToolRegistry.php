<?php
/**
 * Tool Registry - Dual-layer filter architecture for exposing Data Machine AI tools network-wide.
 *
 * Layer 1: datamachine_chubes_ai_tools_multisite - Makes tools available to plugins without Data Machine
 * Layer 2: chubes_ai_tools replacement - Provides multisite-aware versions to Data Machine core
 *
 * @package DataMachineMultisite
 */

namespace DataMachineMultisite;

defined('ABSPATH') || exit;

class ToolRegistry {

    public function __construct() {
        // Layer 1: Expose tools to network via datamachine_chubes_ai_tools_multisite filter
        add_action('init', [$this, 'register_network_tools'], 10);

        // Layer 2: Replace single-site tools with multisite versions in Data Machine core
        add_filter('chubes_ai_tools', [$this, 'replace_with_multisite_tools'], 15, 1);
    }

    /**
     * Register network-wide tool discovery filter.
     * Allows any plugin to discover tools via: apply_filters('datamachine_chubes_ai_tools_multisite', [])
     */
    public function register_network_tools() {
        add_filter('datamachine_chubes_ai_tools_multisite', [$this, 'get_network_tools'], 10, 1);
    }

    /**
     * Get all AI tools available network-wide.
     * Combines core Data Machine tools with multisite-aware versions.
     *
     * @param array $tools Existing tools (empty on first call)
     * @return array Complete tool registry
     */
    public function get_network_tools($tools = []) {
        $multisite_tools = [
            'google_search' => [
                'class' => 'DataMachine\\Engine\\AI\\Tools\\GoogleSearch',
                'method' => 'handle_tool_call',
                'description' => 'Search Google and return structured JSON results with titles, links, and snippets from external websites. Use for external information, current events, and fact-checking. Returns complete web search data in JSON format with title, link, snippet for each result.',
                'requires_config' => true,
                'parameters' => [
                    'query' => [
                        'type' => 'string',
                        'required' => true,
                        'description' => 'Search query for external web information. Returns JSON with "results" array containing web search results.'
                    ],
                    'site_restrict' => [
                        'type' => 'string',
                        'required' => false,
                        'description' => 'Restrict search to specific domain (e.g., "wikipedia.org" for Wikipedia only)'
                    ]
                ]
            ],
            'web_fetch' => [
                'class' => 'DataMachine\\Engine\\AI\\Tools\\WebFetch',
                'method' => 'handle_tool_call',
                'description' => 'Fetch and extract clean content from any web page URL. Returns structured JSON with title, text content, and metadata. Use for reading articles, documentation, and web pages. Maximum 50,000 characters of content.',
                'requires_config' => false,
                'parameters' => [
                    'url' => [
                        'type' => 'string',
                        'required' => true,
                        'description' => 'Full URL of web page to fetch. Returns JSON with "title", "content" (clean text), "url", "word_count".'
                    ]
                ]
            ],
            'local_search' => [
                'class' => 'DataMachineMultisite\\MultisiteLocalSearch',
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
                    ]
                ]
            ],
            'wordpress_post_reader' => [
                'class' => 'DataMachineMultisite\\MultisiteWordPressPostReader',
                'method' => 'handle_tool_call',
                'name' => 'WordPress Post Reader',
                'description' => 'Read full content from any WordPress post URL in the multisite network. Extracts complete post data including title, content, metadata, taxonomies, and custom fields. Use for analyzing existing posts from any site in the network.',
                'requires_config' => false,
                'parameters' => [
                    'source_url' => [
                        'type' => 'string',
                        'required' => true,
                        'description' => 'Full WordPress post URL from any site in the network. Returns JSON with complete post data including title, content, excerpt, author, publish_date, categories, tags, featured_image_url, site_name, site_url.'
                    ],
                    'include_meta' => [
                        'type' => 'boolean',
                        'required' => false,
                        'description' => 'Include custom fields and post meta in response'
                    ]
                ]
            ]
        ];

        return array_merge($tools, $multisite_tools);
    }

    /**
     * Replace single-site tools with multisite versions in Data Machine core.
     * Only runs when Data Machine is active on the current site.
     *
     * @param array $tools Core chubes_ai_tools array from Data Machine
     * @return array Modified tools with multisite versions
     */
    public function replace_with_multisite_tools($tools) {
        // Only replace if Data Machine's chubes_ai_tools filter exists (means DM is installed)
        if (empty($tools)) {
            return $tools;
        }

        // Replace local_search with multisite version
        $tools['local_search'] = [
            'class' => 'DataMachineMultisite\\MultisiteLocalSearch',
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
                ]
            ]
        ];

        // Replace wordpress_post_reader with multisite version
        $tools['wordpress_post_reader'] = [
            'class' => 'DataMachineMultisite\\MultisiteWordPressPostReader',
            'method' => 'handle_tool_call',
            'name' => 'WordPress Post Reader',
            'description' => 'Read full content from any WordPress post URL in the multisite network. Extracts complete post data including title, content, metadata, taxonomies, and custom fields. Use for analyzing existing posts from any site in the network.',
            'requires_config' => false,
            'parameters' => [
                'source_url' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'Full WordPress post URL from any site in the network. Returns JSON with complete post data including title, content, excerpt, author, publish_date, categories, tags, featured_image_url, site_name, site_url.'
                ],
                'include_meta' => [
                    'type' => 'boolean',
                    'required' => false,
                    'description' => 'Include custom fields and post meta in response'
                ]
            ]
        ];

        return $tools;
    }
}
