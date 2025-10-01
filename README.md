# Data Machine Multisite

Multisite extension for Data Machine that exposes AI tools network-wide and provides multisite-aware search capabilities.

## Overview

**Data Machine Multisite** enables WordPress multisite networks to share Data Machine's general AI tools across all sites without requiring Data Machine to be installed on every site. This lightweight extension provides:

- **Network-Wide Tool Access**: Any plugin on any site can use Data Machine's 4 general AI tools
- **Multisite-Aware Search**: Search across ALL sites in the network with site context
- **Cross-Site Post Reading**: Read WordPress posts from any site in the network
- **Centralized Configuration**: Configure API keys once, use everywhere

## Requirements

- WordPress Multisite installation
- Data Machine plugin installed on at least one site in the network
- PHP 8.0 or higher
- WordPress 6.0 or higher

## Installation

1. **Install Data Machine** on your main site (e.g., extrachill.com)
2. **Upload dm-multisite** to `/wp-content/plugins/` directory
3. **Network Activate** dm-multisite from Network Admin → Plugins
4. **Configure Google Search** (optional) in Data Machine Settings on main site

## The 4 General AI Tools

### 1. Google Search
Search Google and return structured JSON results with titles, links, and snippets.

**Requirements**: API Key + Custom Search Engine ID (configured in Data Machine Settings)

**Example**:
```php
$tools = apply_filters('dm_ai_tools_multisite', []);
$result = call_user_func(
    [$tools['google_search']['class'], $tools['google_search']['method']],
    ['query' => 'latest music news'],
    $tools['google_search']
);
```

### 2. WebFetch
Fetch and extract clean content from any web page URL.

**Requirements**: None

**Example**:
```php
$tools = apply_filters('dm_ai_tools_multisite', []);
$result = call_user_func(
    [$tools['webfetch']['class'], $tools['webfetch']['method']],
    ['url' => 'https://example.com/article'],
    $tools['webfetch']
);
```

### 3. Local Search (Multisite Version)
Search across ALL sites in the WordPress multisite network with site context.

**Requirements**: None

**Example**:
```php
$tools = apply_filters('dm_ai_tools_multisite', []);
$result = call_user_func(
    [$tools['local_search']['class'], $tools['local_search']['method']],
    [
        'query' => 'electronic music',
        'post_types' => ['post', 'page']
    ],
    $tools['local_search']
);

// Returns results with site context:
// ['site_name', 'site_url', 'site_id', 'title', 'link', 'excerpt', ...]
```

### 4. WordPress Post Reader (Multisite Version)
Read full content from any WordPress post URL in the multisite network.

**Requirements**: None

**Example**:
```php
$tools = apply_filters('dm_ai_tools_multisite', []);
$result = call_user_func(
    [$tools['wordpress_post_reader']['class'], $tools['wordpress_post_reader']['method']],
    ['url' => 'https://shop.extrachill.com/post/sample-post/'],
    $tools['wordpress_post_reader']
);

// Returns post data with site context:
// ['site_name', 'site_url', 'site_id', 'title', 'content', 'categories', ...]
```

## Multisite Site Context

**Automatic Context Injection**: DM-Multisite automatically injects comprehensive network context into ALL AI requests through the global `ai_request` filter.

### What Gets Injected

Every AI request receives structured JSON context containing:

**Network Information**:
- Main site ID and URL
- Total number of sites in network
- List of all sites (up to 50) with IDs, names, and URLs

**Current Site Information**:
- Site ID, name, tagline, URL
- Language and timezone
- All public post types with published counts
- All public taxonomies with term counts

### How It Works

DM-Multisite hooks into the `ai_request` filter at priority 50, automatically appending network context to the messages array. This happens transparently for:

- ✅ **Data Machine pipelines** (replaces single-site context with multisite context)
- ✅ **ExtraChill Chat** (provides context on sites without Data Machine)
- ✅ **Any plugin** using `apply_filters('ai_request', ...)`

### Performance

Context is cached permanently until content changes. Cache automatically invalidates when:
- Posts are created, updated, or deleted
- Terms are created, updated, or deleted
- Sites are added or removed from network
- Site options change (blogname, URL, etc.)

**Result**: Zero performance overhead after initial cache generation.

### Example Context Output

```json
{
  "network": {
    "main_site_id": 1,
    "main_site_url": "https://extrachill.com",
    "total_sites": 6,
    "sites": [
      {"id": 1, "name": "ExtraChill", "url": "https://extrachill.com"},
      {"id": 2, "name": "Community", "url": "https://community.extrachill.com"}
    ]
  },
  "current_site": {
    "id": 2,
    "name": "ExtraChill Community",
    "url": "https://community.extrachill.com",
    "post_types": {
      "post": {"label": "Posts", "count": 150},
      "topic": {"label": "Topics", "count": 89}
    },
    "taxonomies": {
      "category": {"label": "Categories", "terms": {"Music": 45, "Events": 32}}
    }
  }
}
```

## Usage for Plugin Developers

### Basic Integration

Any plugin on any site in the network can discover and use tools:

```php
// In your plugin (e.g., ExtraChill-Chat)
function my_plugin_use_ai_tools() {
    // Discover available tools
    $tools = apply_filters('dm_ai_tools_multisite', []);

    if (empty($tools)) {
        // DM-Multisite not active
        return;
    }

    // Use Google Search
    if (isset($tools['google_search'])) {
        $tool = $tools['google_search'];
        $result = call_user_func(
            [$tool['class'], $tool['method']],
            ['query' => 'my search query'],
            $tool
        );

        if ($result['success']) {
            $search_results = $result['data']['results'];
            // Process results...
        }
    }

    // Use Local Search (multisite-aware)
    if (isset($tools['local_search'])) {
        $tool = $tools['local_search'];
        $result = call_user_func(
            [$tool['class'], $tool['method']],
            ['query' => 'content search', 'post_types' => ['post']],
            $tool
        );

        if ($result['success']) {
            // Results include site_name, site_url, site_id
            foreach ($result['data']['results'] as $item) {
                echo "{$item['title']} from {$item['site_name']}\n";
            }
        }
    }
}
```

### AI Agent Integration

Perfect for AI agents that need access to external information and network-wide content:

```php
class MyAIAgent {

    private $tools = [];

    public function __construct() {
        $this->tools = apply_filters('dm_ai_tools_multisite', []);
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
            ['query' => $query],
            $tool
        );
    }

    public function read_post($url) {
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

    public function fetch_webpage($url) {
        if (!isset($this->tools['webfetch'])) {
            return null;
        }

        $tool = $this->tools['webfetch'];
        return call_user_func(
            [$tool['class'], $tool['method']],
            ['url' => $url],
            $tool
        );
    }
}
```

## Architecture

### Dual-Layer Filter System

**Layer 1: Network Discovery** (`dm_ai_tools_multisite`)
- Makes tools available to ANY plugin in the network
- Works on sites WITHOUT Data Machine installed
- Provides multisite-aware versions of Local Search and Post Reader

**Layer 2: Core Integration** (`ai_tools`)
- Replaces single-site tools with multisite versions in Data Machine
- Ensures Data Machine pipelines use multisite-aware tools
- Priority 15 (after core tools register at priority 10)

### Network-Wide Configuration

Configuration is stored using `get_site_option()` / `update_site_option()`:
- **Multisite**: Uses network-wide `wp_sitemeta` table
- **Single-site**: Automatically falls back to `wp_options` table

This means:
- Configure Google Search API keys once on main site
- All sites in network can use the tools with those credentials
- Zero duplicate configuration needed

## Use Cases

### ExtraChill Music Platform

**Main Site** (extrachill.com): Data Machine installed
**Community Site** (community.extrachill.com): ExtraChill-Chat plugin
**Shop Site** (shop.extrachill.com): E-commerce functionality

With DM-Multisite:
- ✅ Chat agent on community site can search Google for artist info
- ✅ Chat agent can search ALL Extra Chill sites for relevant content
- ✅ Chat agent can read full posts from shop site for context
- ✅ Chat agent can fetch external web pages (artist websites, venues)

**Result**: Instant network-wide AI intelligence with zero duplicate setup.

## Development

### File Structure

```
dm-multisite/
├── dm-multisite.php                  # Main plugin file
├── inc/
│   ├── ToolRegistry.php              # Dual-layer filter system
│   ├── MultisiteLocalSearch.php      # Cross-site search
│   └── MultisiteWordPressPostReader.php  # Network post reading
├── README.md                         # This file
└── CLAUDE.md                         # Developer documentation
```

### Testing

1. **Network Activation Check**: Verify plugin requires network activation
2. **Tool Discovery**: Test `dm_ai_tools_multisite` filter returns 4 tools
3. **Cross-Site Search**: Search from one site, verify results from all sites
4. **Cross-Site Reading**: Read post URL from different site, verify content
5. **Configuration Sharing**: Configure on main site, use from secondary site

## Support

- **Issues**: [GitHub Issues](https://github.com/chubes4/data-machine/issues)
- **Documentation**: See `CLAUDE.md` for technical architecture details
- **Author**: Chris Huber (https://chubes.net)

## License

GPL v2 or later - https://www.gnu.org/licenses/gpl-2.0.html
