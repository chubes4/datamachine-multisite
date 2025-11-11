<?php
/**
 * Multisite Site Context - Network-wide site metadata for AI context injection.
 *
 * Provides comprehensive multisite network information including all sites,
 * current site metadata, post types, and taxonomies. Uses permanent transient
 * caching with invalidation-on-change strategy.
 *
 * @package DataMachineMultisite
 */

namespace DataMachineMultisite;

defined('ABSPATH') || exit;

class MultisiteSiteContext {

    const CACHE_KEY = 'datamachine_multisite_site_context';
    const MAX_SITES = 50; // Limit sites in context for performance

    /**
     * Get multisite network context with automatic caching.
     *
     * @return array Network and current site metadata
     */
    public static function get_context(): array {
        $cached = get_transient(self::CACHE_KEY);
        if ($cached !== false) {
            return $cached;
        }

        $context = [
            'network' => self::get_network_metadata(),
            'current_site' => self::get_current_site_metadata()
        ];

        set_transient(self::CACHE_KEY, $context, 0); // 0 = never expire (invalidate on change)

        return $context;
    }

    /**
     * Get network-wide metadata.
     *
     * @return array Main site info, total sites count, and site list
     */
    private static function get_network_metadata(): array {
        $main_site_id = get_main_site_id();
        $total_sites = get_sites(['count' => true]);

        // Get site list (limited for performance)
        $sites = get_sites([
            'number' => self::MAX_SITES,
            'public' => 1,
            'archived' => 0,
            'spam' => 0,
            'deleted' => 0
        ]);

        $sites_data = [];
        foreach ($sites as $site) {
            switch_to_blog($site->blog_id);

            $sites_data[] = [
                'id' => (int) $site->blog_id,
                'name' => get_blog_option($site->blog_id, 'blogname'),
                'url' => get_site_url($site->blog_id),
                'post_types' => self::get_post_types_data(),
                'taxonomies' => self::get_taxonomies_data()
            ];

            restore_current_blog();
        }

        return [
            'main_site_id' => (int) $main_site_id,
            'main_site_url' => get_site_url($main_site_id),
            'total_sites' => (int) $total_sites,
            'sites' => $sites_data
        ];
    }

    /**
     * Get current site metadata.
     *
     * @return array Site ID, name, URL, post types, and taxonomies
     */
    private static function get_current_site_metadata(): array {
        return [
            'id' => get_current_blog_id(),
            'name' => get_bloginfo('name'),
            'tagline' => get_bloginfo('description'),
            'url' => home_url(),
            'language' => get_locale(),
            'timezone' => wp_timezone_string(),
            'post_types' => self::get_post_types_data(),
            'taxonomies' => self::get_taxonomies_data()
        ];
    }

    /**
     * Get public post types with published counts.
     *
     * @return array Post type labels, counts, and hierarchy status
     */
    private static function get_post_types_data(): array {
        $post_types_data = [];
        $post_types = get_post_types(['public' => true], 'objects');

        foreach ($post_types as $post_type) {
            $count = wp_count_posts($post_type->name);
            $published_count = $count->publish ?? 0;

            $post_types_data[$post_type->name] = [
                'label' => $post_type->label,
                'singular_label' => $post_type->labels->singular_name ?? $post_type->label,
                'count' => (int) $published_count,
                'hierarchical' => $post_type->hierarchical
            ];
        }

        return $post_types_data;
    }

    /**
     * Get public taxonomies with term and post counts.
     *
     * Only includes taxonomies with at least one term associated with posts.
     * Excludes post_format, nav_menu, and link_category.
     *
     * @return array Taxonomy labels, terms with counts, hierarchy, post type associations
     */
    private static function get_taxonomies_data(): array {
        $taxonomies_data = [];
        $taxonomies = get_taxonomies(['public' => true], 'objects');
        $excluded_taxonomies = ['post_format', 'nav_menu', 'link_category'];

        foreach ($taxonomies as $taxonomy) {
            if (in_array($taxonomy->name, $excluded_taxonomies)) {
                continue;
            }

            $terms = get_terms([
                'taxonomy' => $taxonomy->name,
                'hide_empty' => false,
                'count' => true,
                'orderby' => 'count',
                'order' => 'DESC'
            ]);

            $term_data = [];
            if (is_array($terms)) {
                foreach ($terms as $term) {
                    if ($term->count > 0) {
                        $term_data[$term->name] = (int) $term->count;
                    }
                }
            }

            if (!empty($term_data)) {
                $taxonomies_data[$taxonomy->name] = [
                    'label' => $taxonomy->label,
                    'singular_label' => $taxonomy->labels->singular_name ?? $taxonomy->label,
                    'terms' => $term_data,
                    'hierarchical' => $taxonomy->hierarchical,
                    'post_types' => $taxonomy->object_type ?? []
                ];
            }
        }

        return $taxonomies_data;
    }

    /**
     * Clear site context cache.
     */
    public static function clear_cache(): void {
        delete_transient(self::CACHE_KEY);
    }

    /**
     * Register automatic cache invalidation hooks.
     *
     * Clears cache when posts, terms, or sites change.
     */
    public static function register_cache_invalidation(): void {
        // Post changes
        add_action('save_post', [__CLASS__, 'clear_cache']);
        add_action('delete_post', [__CLASS__, 'clear_cache']);
        add_action('wp_trash_post', [__CLASS__, 'clear_cache']);
        add_action('untrash_post', [__CLASS__, 'clear_cache']);

        // Taxonomy changes
        add_action('create_term', [__CLASS__, 'clear_cache']);
        add_action('edit_term', [__CLASS__, 'clear_cache']);
        add_action('delete_term', [__CLASS__, 'clear_cache']);
        add_action('set_object_terms', [__CLASS__, 'clear_cache']);

        // Site changes
        add_action('wpmu_new_blog', [__CLASS__, 'clear_cache']);
        add_action('delete_blog', [__CLASS__, 'clear_cache']);
        add_action('update_blog_status', [__CLASS__, 'clear_cache']);

        // Site option changes
        add_action('update_option_blogname', [__CLASS__, 'clear_cache']);
        add_action('update_option_blogdescription', [__CLASS__, 'clear_cache']);
        add_action('update_option_home', [__CLASS__, 'clear_cache']);
        add_action('update_option_siteurl', [__CLASS__, 'clear_cache']);
    }
}

add_action('init', [MultisiteSiteContext::class, 'register_cache_invalidation']);
