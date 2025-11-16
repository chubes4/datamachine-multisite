# CLAUDE.md - Data Machine Multisite

This file provides guidance to Claude Code (claude.ai/code) when working with the DM-Multisite extension.

## Migration Status

**Prefix Migration**: ✅ Complete - `datamachine_` prefixes throughout

**REST API Integration**: Filter-based tool registration via `chubes_ai_tools` filter - no custom endpoints needed

## Project Overview

**Data Machine Multisite** is a lightweight WordPress multisite extension that exposes Data Machine's 4 general AI tools network-wide, enabling any plugin on any site to use AI capabilities without requiring Data Machine installation on every site.

## Architecture

### Dual-Layer Filter System

**Layer 1**: Network discovery via `datamachine_chubes_ai_tools_multisite` filter - exposes tools to ANY network plugin
**Layer 2**: Core integration via `chubes_ai_tools` filter - replaces single-site tools with multisite versions

**Key Principles**: Network activation required, multisite-only, zero core modifications, filter-based

## File Structure

```
dm-multisite/
├── datamachine-multisite.php                     # Main plugin file
├── inc/
│   ├── ToolRegistry.php                 # Dual-layer filter architecture
│   ├── MultisiteLocalSearch.php         # Cross-site search
│   └── MultisiteWordPressPostReader.php # Network post reading
├── README.md                            # User documentation
└── CLAUDE.md                            # This file
```

## Core Components

### datamachine-multisite.php

**Purpose**: Main plugin file with initialization and validation

**Key Features**:
- Network activation requirement check
- Multisite detection and validation
- Component loading and initialization
- Hooks into `plugins_loaded` at priority 25 (after Data Machine at priority 20)

### ToolRegistry.php

**Purpose**: Dual-layer filter architecture - network discovery + core integration

**Layer 2 - Core Integration**:
```php
add_filter('chubes_ai_tools', [$this, 'replace_with_multisite_tools'], 15, 1);

public function replace_with_multisite_tools($tools) {
    // Only runs if Data Machine's chubes_ai_tools filter exists
    if (empty($tools)) {
        return $tools;
    }

    // Replace single-site tools with multisite versions
    $tools['local_search'] = [...]; // MultisiteLocalSearch
    $tools['wordpress_post_reader'] = [...]; // MultisiteWordPressPostReader

    return $tools;
}
```

**Priority System**:
- Core tools register at priority 10
- Multisite replacement at priority 15
- Ensures multisite versions override single-site versions

### MultisiteLocalSearch.php

**Purpose**: Search across ALL sites in WordPress multisite network

**Implementation Pattern**:
```php
public static function handle_tool_call(array $parameters, array $tool_def = []): array {
    $query = sanitize_text_field($parameters['query']);
    $post_types = $parameters['post_types'] ?? ['post', 'page'];

    // Get all sites in network
    $sites = get_sites(['number' => 999, 'public' => 1]);

    $all_results = [];

    foreach ($sites as $site) {
        switch_to_blog($site->blog_id);

        // Standard WP_Query search
        $wp_query = new \WP_Query([
            's' => $query,
            'post_type' => $post_types,
            'post_status' => 'publish',
            'posts_per_page' => 5
        ]);

        if ($wp_query->have_posts()) {
            $site_name = get_bloginfo('name');
            $site_url = get_site_url();

            while ($wp_query->have_posts()) {
                $wp_query->the_post();

                $all_results[] = [
                    'title' => get_the_title(),
                    'link' => get_permalink(),
                    'excerpt' => get_the_excerpt(),
                    'site_name' => $site_name,
                    'site_url' => $site_url,
                    'site_id' => $site->blog_id
                ];
            }

            wp_reset_postdata();
        }

        restore_current_blog();
    }

    return ['success' => true, 'data' => ['results' => $all_results]];
}
```

**Key Features**:
- `get_sites()` enumerates all network sites
- `switch_to_blog()` / `restore_current_blog()` for context switching
- Site context included in results: `site_name`, `site_url`, `site_id`
- Limits to 5 results per site to prevent data overload

### MultisiteWordPressPostReader.php

**Purpose**: Read WordPress posts from any site in the multisite network

**Implementation Pattern**:
```php
public static function handle_tool_call(array $parameters, array $tool_def = []): array {
    $source_url = sanitize_url($parameters['url']);

    // Extract blog_id from URL
    $blog_id = self::get_blog_id_from_url($source_url);

    if (!$blog_id) {
        return ['success' => false, 'error' => 'Could not determine site'];
    }

    // Switch to target site
    switch_to_blog($blog_id);

    $site_name = get_bloginfo('name');
    $site_url = get_site_url();

    // Extract post ID and read content
    $post_id = url_to_postid($source_url);
    $post = get_post($post_id);

    $response_data = [
        'post_id' => $post_id,
        'title' => $post->post_title,
        'content' => $post->post_content,
        'site_name' => $site_name,
        'site_url' => $site_url,
        'site_id' => $blog_id
        // ... more post data
    ];

    restore_current_blog();

    return ['success' => true, 'data' => $response_data];
}

private static function get_blog_id_from_url($url) {
    $parsed_url = parse_url($url);
    $host = $parsed_url['host'] ?? '';
    $path = $parsed_url['path'] ?? '/';

    // Try WordPress's get_site_by_path()
    $site = get_site_by_path($host, $path);
    if ($site) {
        return $site->blog_id;
    }

    // Fallback: iterate all sites and match URLs
    $sites = get_sites(['number' => 999]);
    foreach ($sites as $network_site) {
        $site_url = get_site_url($network_site->blog_id);
        if (strpos($url, $site_url) === 0) {
            return $network_site->blog_id;
        }
    }

    // Final fallback: current site
    return get_current_blog_id();
}
```

**Key Features**:
- Smart blog_id extraction from URLs (subdomain and subdirectory multisite)
- Falls back to URL matching for complex multisite setups
- Complete post data with site context
- Includes categories, tags, featured image, custom fields (if requested)

### MultisiteSiteContext.php

**Purpose**: Cached WordPress multisite network metadata for AI context injection

**Data Collection**: Provides comprehensive post type and taxonomy data for ALL sites in the network by using `switch_to_blog()` to gather data from each site.

**Performance Considerations**: Data collection for all network sites is cached permanently via transient and only invalidated when relevant changes occur (posts, terms, sites, or options).

**Implementation Pattern**:
```php
public static function get_context(): array {
    $cached = get_transient(self::CACHE_KEY);
    if ($cached !== false) {
        return $cached;
    }

    $context = [
        'network' => self::get_network_metadata(),
        'current_site' => self::get_current_site_metadata()
    ];

    set_transient(self::CACHE_KEY, $context, 0); // 0 = permanent until invalidated

    return $context;
}

private static function get_network_metadata(): array {
    return [
        'main_site_id' => get_main_site_id(),
        'main_site_url' => get_site_url(get_main_site_id()),
        'total_sites' => get_sites(['count' => true]),
        'sites' => [
            // Array of sites (max 50 for performance)
            // Each site includes complete post types and taxonomies data
            [
                'id' => 1,
                'name' => 'Site Name',
                'url' => 'https://...',
                'post_types' => [...],  // Public post types with counts
                'taxonomies' => [...]   // Public taxonomies with term counts
            ]
        ]
    ];
}

private static function get_current_site_metadata(): array {
    return [
        'id' => get_current_blog_id(),
        'name' => get_bloginfo('name'),
        'url' => home_url(),
        'post_types' => [...], // Public post types with counts
        'taxonomies' => [...]  // Public taxonomies with term counts
    ];
}
```

**Caching Strategy**:
- **Cache Key**: `datamachine_multisite_site_context`
- **Cache Duration**: `0` (permanent until invalidated)
- **Invalidation Triggers**: Post changes, term changes, site changes, option changes
- **Rationale**: Comprehensive invalidation hooks eliminate need for time-based expiration

**Cache Invalidation Hooks**:
- Post changes: `save_post`, `delete_post`, `wp_trash_post`, `untrash_post`
- Term changes: `create_term`, `edit_term`, `delete_term`, `set_object_terms`
- Site changes: `wpmu_new_blog`, `delete_blog`, `update_blog_status`
- Option changes: `blogname`, `blogdescription`, `home`, `siteurl`

### MultisiteSiteContextDirective.php

**Purpose**: Injects multisite network context into AI requests via chubes_ai_request filter

**Implementation Pattern**:
```php
public static function inject($request, $provider_name, $streaming_callback, $tools, $pipeline_step_id = null): array {
    if (!is_multisite()) {
        return $request;
    }

    if (!isset($request['messages']) || !is_array($request['messages'])) {
        return $request;
    }

    $context_message = self::generate_multisite_context();

    array_push($request['messages'], [
        'role' => 'system',
        'content' => $context_message
    ]);

    return $request;
}

private static function generate_multisite_context(): string {
    $context_data = MultisiteSiteContext::get_context();

    $context_message = "WORDPRESS MULTISITE NETWORK CONTEXT:\n\n";
    $context_message .= "The following structured data provides comprehensive information about this WordPress multisite network:\n\n";
    $context_message .= json_encode($context_data, JSON_PRETTY_PRINT);

    return $context_message;
}
```

**Filter Registration**:
```php
add_filter('chubes_ai_request', [MultisiteSiteContextDirective::class, 'inject'], 50, 5);
```

**Priority System**:
- **Priority 50**: Replaces Data Machine's single-site context when both active
- **Behavior**: Hooks at same priority as Data Machine's `SiteContextDirective`
- **Result**: Last loaded plugin wins (dm-multisite loads after DM at priority 25)

**Automatic Integration**:
- Works with Data Machine pipelines (replaces single-site context)
- Works with ExtraChill Chat (provides context where DM not installed)
- Works with ANY plugin using `chubes_ai_request` filter

## Data Machine Core Integration

### Network-Wide Configuration Storage

Data Machine's `GoogleSearch.php` uses `get_site_option()` / `update_site_option()` for configuration:

```php
// In Data Machine core: GoogleSearch.php
public static function get_config(): array {
    $config = get_site_option('datamachine_search_config', []); // Network-wide
    return $config['google_search'] ?? [];
}

public function save_configuration($tool_id, $config_data) {
    $stored_config = get_site_option('datamachine_search_config', []);
    $stored_config['google_search'] = [...];
    update_site_option('datamachine_search_config', $stored_config); // Network-wide
}
```

**Behavior**:
- **Multisite**: Uses `wp_sitemeta` network table
- **Single-site**: Automatically falls back to `wp_options` table
- **Result**: Configure once on main site, available network-wide

## Usage Patterns

### Plugin Integration (Sites Without Data Machine)

```php
// In ExtraChill-Chat plugin on chat.extrachill.com
class ChatAgent {
    private $tools = [];

    public function __construct() {
        // Discover tools via datamachine_chubes_ai_tools_multisite
        $this->tools = apply_filters('datamachine_chubes_ai_tools_multisite', []);
    }

    public function search_web($query) {
        if (!isset($this->tools['google_search'])) {
            return null;
        }

        $tool = $this->tools['google_search'];
        return call_user_func(
            [$tool['class'], $tool['method']],
            ['query' => $query],
            $tool
        );
    }

    public function search_network($query) {
        if (!isset($this->tools['local_search'])) {
            return null;
        }

        $tool = $this->tools['local_search'];
        return call_user_func(
            [$tool['class'], $tool['method']],
            ['query' => $query, 'post_types' => ['post']],
            $tool
        );
    }

    public function read_network_post($url) {
        if (!isset($this->tools['wordpress_post_reader'])) {
            return null;
        }

        $tool = $this->tools['wordpress_post_reader'];
        return call_user_func(
            [$tool['class'], $tool['method']],
            ['url' => $url],
            $tool
        );
    }
}
```

### Data Machine Pipeline Integration (Sites With Data Machine)

No code changes needed - multisite tools are automatically injected via `chubes_ai_tools` filter replacement at priority 15.

AI agents in Data Machine pipelines automatically get:
- Multisite Local Search (searches all network sites)
- Multisite WordPress Post Reader (reads from any network site)

## Development Guidelines

### Adding New Multisite Tools

To add a new multisite-aware tool:

1. Create new class in `/inc/` following `MultisiteLocalSearch.php` pattern
2. Implement static `handle_tool_call()` method with multisite logic
3. Register in `ToolRegistry::get_network_tools()` array
4. Optionally add to `ToolRegistry::replace_with_multisite_tools()` if it should replace a core tool

### Testing Multisite Tools

**Test Scenarios**:
1. Network activation check - verify plugin requires network activation
2. Tool discovery - test `datamachine_chubes_ai_tools_multisite` returns 4 tools
3. Cross-site search - search from site A, verify results from sites B, C, D
4. Cross-site reading - read post URL from site B while on site A
5. Configuration sharing - configure on main site, verify accessible from secondary sites
6. Single-site compatibility - verify core GoogleSearch fallback works

### Security Considerations

- All multisite operations respect WordPress post status (only `publish`)
- Uses WordPress's built-in `switch_to_blog()` security model
- No direct database queries - uses WordPress APIs exclusively
- Site enumeration limited to `public` sites only
- Respects site archival, spam, and deletion flags

## Use Cases

### ExtraChill Music Platform

**Network Structure**:
- Main site (extrachill.com): Data Machine installed
- Community (community.extrachill.com): ExtraChill-Chat plugin
- Shop (shop.extrachill.com): E-commerce
- Forum (forum.extrachill.com): bbPress

**DM-Multisite Benefits**:
- Chat agent searches Google for artist information
- Chat agent searches ALL Extra Chill sites for relevant music content
- Chat agent reads full posts from shop site for product context
- Chat agent fetches external web pages (venue websites, artist pages)
- Zero duplicate configuration - API keys from main site work everywhere

### Multi-Brand Content Network

**Network Structure**:
- Brand A (branda.com): Data Machine installed
- Brand B (brandb.com): Custom content plugin
- Brand C (brandc.com): E-commerce plugin

**DM-Multisite Benefits**:
- Content plugins on Brand B and C can use AI tools
- Cross-brand content discovery via multisite search
- Unified API key management
- Network-wide content intelligence

## Roadmap

**Future Enhancements**:
- Network admin settings page for tool configuration
- Per-site tool enable/disable controls
- Enhanced blog_id extraction for complex multisite setups
- Additional multisite-aware tools (WebFetch with site context, etc.)
- Performance optimization (result caching, parallel site queries)

## Support

- **Issues**: Report bugs at [GitHub Issues](https://github.com/chubes4/data-machine/issues)
- **Documentation**: See `README.md` for user-facing documentation
- **Core Integration**: See `/datamachine/CLAUDE.md` for Data Machine architecture

---

**Version**: 0.1.0
**Author**: Chris Huber (https://chubes.net)
**License**: GPL v2 or later
