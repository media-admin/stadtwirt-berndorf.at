<?php

namespace FluentCrm\App\Http\Controllers;

use FluentCrm\App\Modules\MCP\AbilitiesRegistrar;
use FluentCrm\App\Modules\MCP\MCPInit;
use FluentCrm\Framework\Http\Request\Request;

/**
 * Settings → MCP admin endpoints (MCP_PLAN.md § 13).
 *
 * Surfaces:
 *  - status: adapter detected? CRM count?  enabled toggle?
 *  - install-adapter: one-click install of the WP MCP Adapter plugin
 *  - toggle: enable/disable the entire MCP module without uninstalling
 *  - config-snippet: pre-filled JSON for Claude Desktop / Claude Code /
 *    Cursor / generic clients
 */
class MCPSettingsController extends Controller
{
    const ADAPTER_PLUGIN_FILE = 'mcp-adapter/mcp-adapter.php';
    const TOOLKIT_PLUGIN_FILE = 'fluent-toolkit/fluent-toolkit.php';

    /**
     * Status block — what the Settings page lights up with on load.
     */
    public function status()
    {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $adapterInstalled    = $this->isAdapterPresent();
        $toolkitInstalled    = $this->isToolkitPresent();
        $adapterRuntimeAvailable = $this->isAdapterRuntimeAvailable();
        $standaloneActive    = is_plugin_active(self::ADAPTER_PLUGIN_FILE) && $adapterRuntimeAvailable;
        $toolkitActive       = $this->isToolkitLoaded();
        $toolkitAdapterActive = $toolkitActive && $this->isToolkitAdapterAvailable();
        $adapterActive       = $standaloneActive || $toolkitAdapterActive;
        $adapterProvider     = $standaloneActive ? 'plugin' : ($toolkitAdapterActive ? 'toolkit' : '');
        $abilitiesAvailable  = function_exists('wp_register_ability');
        $canAutoInstall      = (bool) apply_filters('fluent_toolkit/can_auto_install', false);

        $toolsCount = $abilitiesAvailable ? $this->countAbilities() : 0;

        $currentUser = wp_get_current_user();

        // Detect a local dev environment heuristically. Self-signed/local
        // certs trip Node's TLS validation in the npx proxy that Claude
        // Desktop uses; if we know the user is on dev, we pre-bake the
        // workaround into the generated snippet. The Vue page also exposes
        // a manual toggle for edge cases.
        $isLocalDev = self::detectLocalDevEnvironment();

        return [
            'adapter_installed'    => $adapterInstalled || $toolkitInstalled,
            'adapter_active'       => $adapterActive,
            'adapter_provider'     => $adapterProvider,
            'standalone_adapter_installed' => $adapterInstalled,
            'toolkit_installed'    => $toolkitInstalled,
            'toolkit_active'       => $toolkitActive,
            'toolkit_adapter_available' => $toolkitAdapterActive,
            'adapter_runtime_available' => $adapterRuntimeAvailable,
            'adapter_version'      => $this->detectAdapterVersion(),
            'toolkit_version'      => $this->detectToolkitVersion(),
            'abilities_api_loaded' => $abilitiesAvailable,
            'endpoint_url'         => MCPInit::getEndpointUrl(),
            'tools_count'          => $toolsCount,
            'mcp_enabled'          => fluentcrm_get_option('mcp_enabled', 'yes') === 'yes',
            'pro_active'           => defined('FLUENTCAMPAIGN'),
            'app_passwords_url'    => admin_url('profile.php#application-passwords-section'),
            'plugins_url'          => admin_url('plugins.php'),
            'can_auto_install_adapter' => $canAutoInstall,
            'toolkit_download_url' => 'https://github.com/WPManageNinja/fluent-toolkit',
            'current_user_login'   => $currentUser ? $currentUser->user_login : '',
            'is_local_dev'         => $isLocalDev,
        ];
    }

    /**
     * Toggle the kill-switch. Stored as a FluentCRM option so the lazy-register
     * guard in app/Hooks/actions.php picks it up on the next request.
     */
    public function toggle(Request $request)
    {
        $value = $request->get('mcp_enabled');
        $enabled = is_string($value) ? ($value === 'yes' || $value === 'true' || $value === '1') : (bool) $value;

        fluentcrm_update_option('mcp_enabled', $enabled ? 'yes' : 'no');

        return [
            'ok'          => true,
            'mcp_enabled' => $enabled,
            'message'     => $enabled
                ? __('MCP tools enabled. New requests will see the FluentCRM abilities.', 'fluent-crm')
                : __('MCP tools disabled. The adapter will no longer report FluentCRM abilities.', 'fluent-crm'),
        ];
    }

    /**
     * One-click adapter install. Free can only explain the missing dependency;
     * Pro may opt in to the FluentHub background installer via hooks.
     */
    public function installAdapter()
    {
        if (!current_user_can('install_plugins')) {
            return $this->sendError([
                'message' => __('Sorry! you do not have permission to install plugins', 'fluent-crm'),
            ]);
        }

        $canAutoInstall = (bool) apply_filters('fluent_toolkit/can_auto_install', false);
        if (!$canAutoInstall) {
            return $this->sendError([
                'message' => __('Please install FluentHub from GitHub, then reload this page to connect FluentCRM with AI agents.', 'fluent-crm'),
                'toolkit_download_url' => 'https://github.com/WPManageNinja/fluent-toolkit',
            ]);
        }

        do_action('fluent_toolkit/do_auto_install');

        wp_clean_plugins_cache();

        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $toolkitInstalled = $this->isToolkitPresent();
        $toolkitActive = $this->isToolkitLoaded();
        $adapterRuntimeAvailable = $this->isAdapterRuntimeAvailable();
        $toolkitAdapterAvailable = $toolkitActive && $this->isToolkitAdapterAvailable();
        $isInstalled = $this->isAdapterPresent() || $toolkitInstalled;
        $isActive = (is_plugin_active(self::ADAPTER_PLUGIN_FILE) && $adapterRuntimeAvailable) || $toolkitAdapterAvailable;

        if ($isInstalled && $isActive) {
            $message = __('FluentHub installed and activated. Reload the page to register FluentCRM MCP tools.', 'fluent-crm');
        } elseif ($toolkitInstalled && $toolkitActive) {
            $message = __('FluentHub is installed and active, but this version does not include the bundled MCP adapter yet. Please update FluentHub when the MCP-ready build is available, then reload this page.', 'fluent-crm');
        } elseif ($toolkitInstalled) {
            $message = __('FluentHub is installed but could not be activated automatically. Please activate FluentHub from the Plugins page, then reload this page.', 'fluent-crm');
        } else {
            $message = __('Could not install FluentHub automatically. Please install FluentHub manually, then reload this page.', 'fluent-crm');
        }

        return [
            'is_installed' => $isInstalled,
            'adapter_active' => $isActive,
            'toolkit_active' => $toolkitActive,
            'toolkit_adapter_available' => $toolkitAdapterAvailable,
            'message'      => $message,
        ];
    }

    /**
     * Generate a copy-paste config snippet for the requested client.
     *
     * Every client uses WordPress Application Passwords — built into WP 5.6+,
     * no extra plugin needed. Direct HTTP clients (Claude Code, Cursor,
     * generic) carry credentials via Basic Auth header; the
     * @automattic/mcp-wordpress-remote stdio bridge that Claude Desktop uses
     * accepts the username/password directly via WP_API_USERNAME and
     * WP_API_PASSWORD env vars and handles encoding itself.
     *
     * Placeholders used here are stable strings the Vue page substitutes via
     * regex when the user fills the credentials inputs.
     */
    public function getConfigSnippet(Request $request)
    {
        $client = sanitize_key((string) $request->get('client', 'claude-code'));
        $endpoint = MCPInit::getEndpointUrl();
        // Optional override from the Settings UI checkbox. When the user
        // explicitly says "I'm on local dev" we add the TLS-bypass env var to
        // Claude Desktop's snippet; when they say "no" we omit it even if
        // auto-detection thinks otherwise.
        $forceLocalDev = $request->get('local_dev');
        if ($forceLocalDev === 'yes' || $forceLocalDev === '1' || $forceLocalDev === 'true') {
            $isLocalDev = true;
        } elseif ($forceLocalDev === 'no' || $forceLocalDev === '0' || $forceLocalDev === 'false') {
            $isLocalDev = false;
        } else {
            $isLocalDev = self::detectLocalDevEnvironment();
        }

        // The Vue page replaces these tokens with the user's real values.
        // Keep them stable + distinct so the regex stays simple.
        $basicPlaceholder    = '<base64(your-username:application-password)>';
        $usernamePlaceholder = '<your-username>';
        $passwordPlaceholder = '<your-application-password>';

        $appPasswordsUrl = admin_url('profile.php#application-passwords-section');

        switch ($client) {
            case 'codex':
                $snippet = sprintf(
                    "Settings → Connect to a custom MCP\n\nName:        fluent-crm\nTransport:   Streamable HTTP   ← click this tab first\n\nURL:         %s\n\nHeader:\n  Key:       Authorization\n  Value:     Basic %s\n\nClick Save.",
                    $endpoint,
                    $basicPlaceholder
                );
                $instructions = sprintf(
                    /* translators: %s: link to WP user profile application passwords section */
                    __('Open OpenAI Codex → Settings → Connect to a custom MCP. Click the "Streamable HTTP" tab. Generate a WordPress Application Password from %s, then paste username + app password into the inputs above — the Value field will auto-fill with the encoded Basic auth string.', 'fluent-crm'),
                    $appPasswordsUrl
                );
                break;

            case 'cursor':
                $snippet = wp_json_encode([
                    'mcpServers' => [
                        'fluent-crm' => [
                            'url'     => $endpoint,
                            'type'    => 'http',
                            'headers' => [
                                'Authorization' => 'Basic ' . $basicPlaceholder,
                            ],
                        ],
                    ],
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                $instructions = __('Cursor speaks HTTP MCP natively. Fill in your username and application password above and the snippet will be ready to paste into Cursor → Settings → MCP. Restart Cursor afterwards.', 'fluent-crm');
                break;

            case 'generic':
                $snippet = sprintf(
                    "URL:   %s\nAuth:  Authorization: Basic %s\n\n# Quick test (curl handles the base64 for you)\ncurl -s -u '%s:%s' \\\n  -X POST %s \\\n  -H 'Content-Type: application/json' \\\n  -d '{\"jsonrpc\":\"2.0\",\"id\":1,\"method\":\"tools/list\"}'",
                    $endpoint,
                    $basicPlaceholder,
                    $usernamePlaceholder,
                    $passwordPlaceholder,
                    $endpoint
                );
                $instructions = __('Use the URL + Basic Auth header with any HTTP MCP client. The endpoint speaks the standard MCP protocol — initialize, tools/list, tools/call — over JSON-RPC.', 'fluent-crm');
                break;

            case 'claude-desktop':
                // Claude Desktop cannot speak HTTP MCP directly yet — it
                // routes through @automattic/mcp-wordpress-remote, which
                // accepts WP_API_USERNAME / WP_API_PASSWORD plain (proxy
                // does the encoding). No JWT plugin needed.
                $env = [
                    'WP_API_URL'      => $endpoint,
                    'WP_API_USERNAME' => $usernamePlaceholder,
                    'WP_API_PASSWORD' => $passwordPlaceholder,
                    'OAUTH_ENABLED'   => 'false',
                ];

                if ($isLocalDev) {
                    // Self-signed certs trip Node's bundled CA store. Trust
                    // the connection wholesale for local dev — the proxy
                    // only ever talks to one URL the user explicitly chose,
                    // so the practical risk is bounded.
                    $env['NODE_TLS_REJECT_UNAUTHORIZED'] = '0';
                }

                $snippet = wp_json_encode([
                    'mcpServers' => [
                        'fluent-crm' => [
                            'command' => 'npx',
                            'args'    => ['-y', '@automattic/mcp-wordpress-remote@latest'],
                            'env'     => $env,
                        ],
                    ],
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

                $localDevNote = $isLocalDev
                    ? ' ' . __('Local dev mode is on, so NODE_TLS_REJECT_UNAUTHORIZED is included — the npx proxy needs it to talk to self-signed Valet/MAMP/Local SSL.', 'fluent-crm')
                    : '';
                $instructions = __('Paste into ~/Library/Application Support/Claude/claude_desktop_config.json (macOS) or %APPDATA%\\Claude\\claude_desktop_config.json (Windows), then restart Claude Desktop.', 'fluent-crm') . $localDevNote;
                break;

            case 'claude-code':
            default:
                $snippet = sprintf(
                    "claude mcp add \\\n  --transport http \\\n  fluent-crm %s \\\n  --header \"Authorization: Basic %s\"",
                    $endpoint,
                    $basicPlaceholder
                );
                $instructions = __('Fill in your username and application password above, then paste the command into your terminal. Run `claude` and the FluentCRM tools will appear under MCP servers.', 'fluent-crm');
                $client = 'claude-code';
                break;
        }

        return [
            'client'              => $client,
            'snippet'             => $snippet,
            'instructions'        => $instructions,
            'endpoint'            => $endpoint,
            'app_passwords_url'   => $appPasswordsUrl,
            'is_local_dev'        => $isLocalDev,
        ];
    }

    /**
     * Heuristic check for "we're running on a local development install."
     *
     * Tested in order:
     *   1. Hostname ends in a dev TLD (.test, .lab, .local, .localhost)
     *   2. Hostname is literally `localhost`
     *   3. Host resolves to a private/loopback IP range
     *
     * Filterable via `fluent_crm/mcp_is_local_dev` so operators can override
     * detection on edge cases (a public-facing site on `.local`, an internal
     * tool that needs the dev-mode behavior anyway, etc.).
     *
     * @return bool
     */
    private static function detectLocalDevEnvironment()
    {
        $host = wp_parse_url(home_url(), PHP_URL_HOST);
        $host = strtolower((string) $host);

        $isDev = false;

        $devTlds = ['.test', '.lab', '.local', '.localhost', '.docker', '.dev'];
        foreach ($devTlds as $tld) {
            $len = strlen($tld);
            if ($len > 0 && substr($host, -$len) === $tld) {
                $isDev = true;
                break;
            }
        }

        if (!$isDev && ($host === 'localhost' || $host === '127.0.0.1' || $host === '::1')) {
            $isDev = true;
        }

        if (!$isDev && filter_var($host, FILTER_VALIDATE_IP)) {
            // Private IP ranges per RFC 1918.
            $isPrivate = !filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
            if ($isPrivate) {
                $isDev = true;
            }
        }

        /**
         * Override the local-dev detection. Useful when the heuristic gets
         * it wrong (e.g. a public site on a `.local` mDNS hostname).
         *
         * @since 2.10.0
         *
         * @param bool   $isDev Whether the install looks like local dev.
         * @param string $host  The detected hostname.
         */
        return (bool) apply_filters('fluent_crm/mcp_is_local_dev', $isDev, $host);
    }

    // ---------------------------------------------------------------------
    // helpers
    // ---------------------------------------------------------------------

    private function isAdapterPresent()
    {
        return $this->isPluginPresent(self::ADAPTER_PLUGIN_FILE);
    }

    private function isToolkitPresent()
    {
        return $this->isToolkitLoaded() || $this->isPluginPresent(self::TOOLKIT_PLUGIN_FILE);
    }

    private function detectAdapterVersion()
    {
        return $this->detectPluginVersion(self::ADAPTER_PLUGIN_FILE);
    }

    private function detectToolkitVersion()
    {
        if ($this->isToolkitLoaded()) {
            return (string) FLUENT_TOOLKIT_VERSION;
        }

        return $this->detectPluginVersion(self::TOOLKIT_PLUGIN_FILE);
    }

    private function isToolkitLoaded()
    {
        return defined('FLUENT_TOOLKIT_VERSION');
    }

    private function isPluginPresent($pluginFile)
    {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $plugins = get_plugins();
        return isset($plugins[$pluginFile]);
    }

    private function detectPluginVersion($pluginFile)
    {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $plugins = get_plugins();
        if (!isset($plugins[$pluginFile])) {
            return null;
        }
        return $plugins[$pluginFile]['Version'] ?? null;
    }

    private function isToolkitAdapterAvailable()
    {
        if (!$this->isToolkitLoaded()) {
            return false;
        }

        if (class_exists('\FluentToolkit\Mcp\AdapterBootstrap') && method_exists('\FluentToolkit\Mcp\AdapterBootstrap', 'available')) {
            return (bool) \FluentToolkit\Mcp\AdapterBootstrap::available();
        }

        return $this->isAdapterRuntimeAvailable();
    }

    private function isAdapterRuntimeAvailable()
    {
        return defined('WP_MCP_VERSION')
            && class_exists('\WP\MCP\Core\McpAdapter')
            && function_exists('wp_register_ability');
    }

    private function countAbilities()
    {
        $count = count(AbilitiesRegistrar::getDefinitions());

        // Pro tools are pushed onto the names list via the
        // `fluent_crm/mcp_ability_names` filter in MCPInit.
        $names = apply_filters('fluent_crm/mcp_ability_names', array_keys(AbilitiesRegistrar::getDefinitions()));
        if (is_array($names)) {
            $count = count(array_unique($names));
        }
        return $count;
    }
}
