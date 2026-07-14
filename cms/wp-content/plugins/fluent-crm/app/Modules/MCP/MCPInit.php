<?php

namespace FluentCrm\App\Modules\MCP;

/**
 * Bootstrap for FluentCRM's Model Context Protocol (MCP) integration.
 *
 * Hooks the WordPress 6.9 Abilities API + WP MCP Adapter (separate plugin):
 *  - registers a `fluent-crm` ability category
 *  - hands AbilitiesRegistrar a chance to declare every CRM ability
 *  - fires `fluent_crm/mcp_loaded` so FluentCampaign Pro can register its own
 *    abilities under the same namespace
 *  - filters the adapter's default-server config to expose every CRM ability
 *    as a direct MCP tool (not just an adapter wrapper)
 *
 * All the heavy lifting is gated by the lazy-register guard in
 * `app/Hooks/actions.php`, which checks `function_exists('wp_register_ability')`
 * before instantiating this class — so this code never runs on WP < 6.9 or
 * sites missing the adapter plugin.
 */
class MCPInit
{
    public function init()
    {
        add_action('wp_abilities_api_categories_init', [$this, 'registerCategory']);
        add_action('wp_abilities_api_init', [$this, 'registerAbilities']);

        // Register a dedicated FluentCRM MCP server (separate from the
        // adapter's default server). Endpoint:
        //   /wp-json/fluent-crm/mcp
        // Tools live only here — agents that want CRM access connect to this
        // URL specifically, and the adapter's default server is left to host
        // whatever else the user has installed.
        add_action('mcp_adapter_init', [$this, 'registerCustomServer']);

        // Invalidate the cached `get-crm-context` payload when reference data
        // an agent might have just learned changes — keeps stale enums or
        // missing tags out of the next session.
        $invalidate = [\FluentCrm\App\Modules\MCP\Tools\ContextTools::class, 'invalidateCache'];
        foreach ([
            'fluent_crm_tag_created',
            'fluent_crm_tag_updated',
            'fluent_crm_tag_deleted',
            'fluent_crm_list_created',
            'fluent_crm_list_updated',
            'fluent_crm_list_deleted',
            'fluent_crm/custom_field_added',
            'fluent_crm/custom_field_updated',
            'fluent_crm/custom_field_deleted',
            'fluent_crm/global_email_settings_saved',
        ] as $hook) {
            add_action($hook, $invalidate);
        }
    }

    public function registerCategory()
    {
        wp_register_ability_category('fluent-crm', [
            'label'       => __('FluentCRM', 'fluent-crm'),
            'description' => __('Contact, campaign, and automation abilities for FluentCRM.', 'fluent-crm'),
        ]);
    }

    public function registerAbilities()
    {
        AbilitiesRegistrar::register();

        /**
         * Fires after FluentCRM has registered its core MCP abilities.
         *
         * FluentCampaign Pro hooks this to register its 4 Pro abilities under
         * the same `fluent-crm/` namespace — agents do not need to know which
         * plugin owns which tool.
         *
         * @since 2.10.0
         */
        do_action('fluent_crm/mcp_loaded');
    }

    /**
     * Register the dedicated FluentCRM MCP server when the WP MCP Adapter
     * fires `mcp_adapter_init`.
     *
     * @param \WP\MCP\Core\McpAdapter $adapter
     */
    public function registerCustomServer($adapter)
    {
        if (!$adapter || !is_object($adapter) || !method_exists($adapter, 'create_server')) {
            return;
        }

        $abilityNames = array_keys(AbilitiesRegistrar::getDefinitions());

        /**
         * Filter the list of FluentCRM ability names registered with the
         * dedicated FluentCRM MCP server.
         *
         * FluentCampaign Pro hooks this filter (in its own MCPInit) to push
         * its 4 Pro abilities into the same server. Other extensions can do
         * the same to surface tools agents discover via `tools/list`.
         *
         * @since 2.10.0
         *
         * @param array $abilityNames Array of fully-qualified ability names.
         */
        $abilityNames = apply_filters('fluent_crm/mcp_ability_names', $abilityNames);

        // Allow operators to swap the route via filter. Default puts the
        // server at /wp-json/fluent-crm/mcp — sibling to the existing
        // FluentCRM REST namespace (fluent-crm/v2), but distinct so it does
        // not get caught by the v2 policy stack.
        $namespace = apply_filters('fluent_crm/mcp_server_namespace', 'fluent-crm');
        $route     = apply_filters('fluent_crm/mcp_server_route', 'mcp');

        $adapter->create_server(
            'fluent-crm',
            $namespace,
            $route,
            __('FluentCRM MCP Server', 'fluent-crm'),
            __('AI agent tools for FluentCRM contacts, campaigns, and automations.', 'fluent-crm'),
            defined('FLUENTCRM_PLUGIN_VERSION') ? FLUENTCRM_PLUGIN_VERSION : '1.0.0',
            ['\WP\MCP\Transport\HttpTransport'],
            '\WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler',
            '\WP\MCP\Infrastructure\Observability\NullMcpObservabilityHandler',
            array_values(array_unique(array_filter((array) $abilityNames)))
        );
    }

    /**
     * Public helper used by the Settings UI and the snippet generator to
     * report a stable endpoint URL for the FluentCRM MCP server.
     */
    public static function getEndpointUrl()
    {
        $namespace = apply_filters('fluent_crm/mcp_server_namespace', 'fluent-crm');
        $route     = apply_filters('fluent_crm/mcp_server_route', 'mcp');

        return get_rest_url(null, trailingslashit($namespace) . $route);
    }
}
