<?php
/**
 * Multisite Site Context Directive - Priority 50
 *
 * Injects WordPress multisite network context into AI requests. Hooks into
 * chubes_ai_request filter at priority 50, replacing Data Machine's single-site
 * context directive when both are active.
 *
 * Provides network-wide intelligence to any plugin using chubes_ai_request filter.
 *
 * @package DataMachineMultisite
 */

namespace DataMachineMultisite;

defined('ABSPATH') || exit;

class MultisiteSiteContextDirective {

    /**
     * Inject multisite network context into AI request.
     *
     * @param array $request AI request array with messages
     * @param string $provider_name AI provider name
     * @param array $tools Available tools (unused)
     * @param string|null $pipeline_step_id Pipeline step ID (unused)
     * @param array $payload Execution payload (unused)
     * @return array Modified request with network context added
     */
    public static function inject($request, $provider_name, $tools, $pipeline_step_id = null, array $payload = []): array {
        if (!is_multisite()) {
            return $request;
        }

        if (!isset($request['messages']) || !is_array($request['messages'])) {
            return $request;
        }

        $context_message = self::generate_multisite_context();

        if (empty($context_message)) {
            return $request;
        }

        array_push($request['messages'], [
            'role' => 'system',
            'content' => $context_message
        ]);

        return $request;
    }

    /**
     * Generate multisite network context for AI models.
     *
     * @return string JSON-formatted network and site context data
     */
    private static function generate_multisite_context(): string {
        $context_data = MultisiteSiteContext::get_context();

        $context_message = "WORDPRESS MULTISITE NETWORK CONTEXT:\n\n";
        $context_message .= "The following structured data provides comprehensive information about this WordPress multisite network:\n\n";
        $context_message .= json_encode($context_data, JSON_PRETTY_PRINT);

        return $context_message;
    }
}

/**
 * Replace Data Machine's single-site context with multisite context.
 * This filter allows datamachine-multisite to completely replace the site context directive
 * instead of adding a second context message.
 *
 * @param string $directive_class The directive class (SiteContextDirective from DM core)
 * @return string The multisite directive class that replaces it
 */
add_filter('datamachine_site_context_directive', function($directive_class) {
    // Replace single-site directive with multisite directive
    return MultisiteSiteContextDirective::class;
}, 10, 1);

// Note: No direct add_filter('chubes_ai_request') here - registered via datamachine_site_context_directive filter
