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
     * Since tools now self-register, this just returns the tools that were registered.
     *
     * @param array $tools Existing tools (empty on first call)
     * @return array Complete tool registry
     */
    public function get_network_tools($tools = []) {
        // Tools now self-register, so we just return what's been registered
        // This maintains backward compatibility for plugins that use the multisite filter
        return apply_filters('chubes_ai_tools', $tools);
    }

    /**
     * Since tools now self-register with correct multisite implementations,
     * this method is simplified. The multisite tools will override the core tools
     * because they register later in the filter chain.
     *
     * @param array $tools Core chubes_ai_tools array from Data Machine
     * @return array Tools array (multisite tools will have overridden core ones)
     */
    public function replace_with_multisite_tools($tools) {
        // Tools now self-register, so multisite versions automatically override core versions
        // due to registration order. No manual replacement needed.
        return $tools;
    }
}
