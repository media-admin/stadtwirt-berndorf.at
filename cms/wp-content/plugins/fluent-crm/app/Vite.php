<?php

namespace FluentCrm\App;

use Exception;
use FluentCrm\Framework\Support\Arr;

class Vite
{
    private array $moduleScripts = [];
    private string $viteHostProtocol = 'http://';
    private string $viteHost = 'localhost';
    private string $vitePort = '5174';
    private string $resourceDirectory = 'resources/';

    /**
     * Bridge from Mix-era enqueue paths to Vite source paths.
     *
     * The PHP codebase still calls fluentCrmMix('admin/js/app.js') and
     * fluentCrmMix('admin/css/app3.css') even though Vite's manifest and
     * dev server are keyed by source paths (admin/app.js, styles/app3.scss).
     * Both mapToSourcePath() and getSourcePathForManifest() read this
     * table — one source of truth.
     */
    private const MIX_TO_VITE_PATH_MAP = [
        // JS: Mix wrote `admin/js/<name>.js`, Vite reads the source path
        'admin/js/boot.js'                 => 'admin/boot.js',
        'admin/js/app.js'                  => 'admin/app.js',
        'admin/js/adminbar-search.js'      => 'admin/adminbar-search.js',
        'admin/js/global_admin.js'         => 'admin/global_admin.js',
        'admin/js/setup-wizard.js'         => 'admin/setup-wizard.js',
        'admin/js/contact-navigations.js'  => 'admin/experiments/contact-navigations.js',
        'admin/js/visual-editor.js'        => 'admin/visual-editor/visual-editor.js',
        'public/public_pref.js'            => 'public/public_pref.js',

        // CSS: Mix wrote `admin/css/<name>.css`, Vite serves SCSS sources
        'admin/css/admin_rtl.css'    => 'scss/admin_rtl.scss',
        'admin/css/app_global.css'   => 'scss/app_global.scss',
        'admin/css/setup-wizard.css' => 'scss/setup-wizard.scss',
        'admin/css/app3.css'         => 'styles/app3.scss',
        'public/public_pref.css'     => 'scss/public_pref.scss',
    ];

    protected static ?Vite $instance = null;
    public ?string $lastJsHandle = null;
    private ?array $manifestData = null;
    private array $enqueuedChunkCss = [];

    public function __construct()
    {
        $serverConfigPath = FLUENTCRM_PLUGIN_PATH . 'config' . DIRECTORY_SEPARATOR . 'vite.json';
        if (file_exists($serverConfigPath)) {
            $serverConfig = json_decode(file_get_contents($serverConfigPath));
            $this->viteHost = $serverConfig->host ?: $this->viteHost;
            $this->viteHostProtocol = $serverConfig->protocol ?: $this->viteHostProtocol;
            $this->vitePort = $serverConfig->port ?: $this->vitePort;
        }
        
        // Add global filter to convert Vite scripts to modules
        add_filter('script_loader_tag', [$this, 'maybeConvertToModule'], 999, 3);
    }
    
    /**
     * Convert scripts from Vite dev server or built assets to ES modules
     */
    public function maybeConvertToModule($tag, $handle, $src): string
    {
        // Fast rejection for scripts that obviously aren't ours. The filter
        // is registered globally at priority 999 so it fires for EVERY
        // script on EVERY admin page (including pages where FluentCRM
        // isn't active). On a typical admin page that's ~30 unrelated
        // scripts. Short-circuit here so the detailed checks below only
        // run for our own assets.
        $isPotentiallyOurs =
            strpos($src, FLUENTCRM_PLUGIN_URL) !== false ||
            strpos($src, 'localhost:' . $this->vitePort) !== false ||
            strpos($src, '@vite/client') !== false ||
            in_array($handle, $this->moduleScripts, true);

        if (!$isPotentiallyOurs) {
            return $tag;
        }

        // Check if this script is from Vite dev server, Vite built assets, or is a module script
        $isViteScript = false;
        $fluentCrmAssetBase = FLUENTCRM_PLUGIN_URL . 'assets/';

        // Check if from dev server
        if (strpos($src, 'localhost:' . $this->vitePort) !== false || strpos($src, '@vite/client') !== false) {
            $isViteScript = true;
        }
        
        // Check if explicitly marked as module script
        if (in_array($handle, $this->moduleScripts)) {
            $isViteScript = true;
        }
        
        // Check if from FluentCRM Vite built assets in production (or dev mode without server).
        // Only rewrite this plugin's own built files, never third-party plugin assets.
        if (!$this->shouldServeViaDevServer()) {
            $assetPatterns = [
                $fluentCrmAssetBase . 'admin/',
                $fluentCrmAssetBase . 'public/',
            ];
            foreach ($assetPatterns as $pattern) {
                if (strpos($src, $pattern) !== false && strpos($src, '.js') !== false) {
                    // Exclude third-party libs that are not ES modules
                    $excludePatterns = [
                        '/libs/',
                        '/vendor/',
                        'purify.min.js',
                    ];
                    $isExcluded = false;
                    foreach ($excludePatterns as $exclude) {
                        if (strpos($src, $exclude) !== false) {
                            $isExcluded = true;
                            break;
                        }
                    }
                    if (!$isExcluded) {
                        $isViteScript = true;
                        break;
                    }
                }
            }
        }
        
        if ($isViteScript) {
            // Already has type="module"
            if (strpos($tag, 'type="module"') !== false || strpos($tag, "type='module'") !== false) {
                return $tag;
            }
            
            // Convert to module
            $tag = preg_replace('/<script\s+/', '<script type="module" crossorigin ', $tag);
        }
        
        return $tag;
    }

    private static function getInstance(): Vite
    {
        if (static::$instance === null) {
            static::$instance = new static();
            // Load manifest whenever Vite dev server is NOT running.
            // This covers both production (env != dev) AND dev installs where
            // the Vite server isn't active, so built assets are served instead.
            if (!static::$instance->usingDevMode() || !static::$instance->isViteServerRunning()) {
                (static::$instance)->loadViteManifest();
            }
        }

        return static::$instance;
    }

    /**
     * @throws Exception
     */
    private function loadViteManifest()
    {
        if (!empty($this->manifestData)) {
            return;
        }

        $manifestPath = FLUENTCRM_PLUGIN_PATH . 'config' . DIRECTORY_SEPARATOR . 'vite_config.php';
        
        if (file_exists($manifestPath)) {
            $this->manifestData = require $manifestPath;
        }

        if (empty($this->manifestData)) {
            $this->manifestData = [];
            // In production, you might want to uncomment this to enforce manifest requirement
            // throw new Exception('Vite Manifest Not Found. Run: npm run dev or npm run build');
        }
    }

    public static function enqueueScript($handle, $src, $dependency = [], $version = null, $inFooter = false): Vite
    {
        return static::getInstance()->enqueue_script(
            $handle,
            $src,
            $dependency,
            $version,
            $inFooter
        );
    }

    private function enqueue_script($handle, $src, $dependency = [], $version = null, $inFooter = false): Vite
    {
        if (in_array($handle, $this->moduleScripts)) {
            if ($this->usingDevMode()) {
                $callerReference = (debug_backtrace(2)[1]);
                $fileName = explode('plugins', $callerReference['file']);
                $line = $callerReference['line'];
                // Uncomment to debug duplicate handles
                // throw new \Exception("Handle already used: $handle at File: {$fileName[1]} Line: $line");
            }
        }

        $this->moduleScripts[] = $handle;
        $this->lastJsHandle = $handle;

        // No per-handle script_loader_tag filter needed — the constructor
        // registers maybeConvertToModule globally at priority 999 and it
        // already detects handles in $moduleScripts as Vite scripts.

        if ($this->shouldServeViaDevServer()) {
            $srcPath = $this->getVitePath() . $src;
        } else {
            $assetFile = $this->getFileFromManifest($src);
            $srcPath = $this->getProductionFilePath($assetFile);
        }

        if (empty($srcPath)) {
            return $this;
        }

        $version = empty($version) ? FLUENTCRM_PLUGIN_VERSION : $version;

        wp_enqueue_script(
            $handle,
            $srcPath,
            $dependency,
            $version,
            $inFooter
        );
        
        return $this;
    }

    private function getFileFromManifest($src)
    {
        if (isset($this->manifestData[$this->resourceDirectory . $src])) {
            return $this->manifestData[$this->resourceDirectory . $src];
        }

        return '';
    }

    private function getProductionFilePath($file): string
    {
        if (!isset($file['file'])) {
            return '';
        }
        
        $assetPath = static::getAssetPath();
        $this->ensureChunkCssIsLoaded($file);

        return ($assetPath . $file['file']);
    }

    // Per-chunk CSS auto-enqueue. The Vite build's mergeCssChunksPlugin
    // collapses most chunk CSS into admin/css/style.css (which AdminMenu.php
    // enqueues explicitly), and the moveManifestPlugin then strips the
    // merged paths from the manifest. What remains in manifest `css` arrays
    // is only files that survived to disk (e.g. legacy SCSS entry outputs
    // like admin/css/admin_rtl.css) — those we enqueue here.
    private function ensureChunkCssIsLoaded($file)
    {
        $assetPath = static::getAssetPath();
        $cssFiles = $this->collectChunkCssFiles($file);

        foreach ($cssFiles as $cssPath) {
            if (isset($this->enqueuedChunkCss[$cssPath])) {
                continue;
            }

            wp_enqueue_style(
                'fluentcrm_vite_css_' . md5($cssPath),
                $assetPath . $cssPath,
                [],
                FLUENTCRM_PLUGIN_VERSION
            );

            $this->enqueuedChunkCss[$cssPath] = true;
        }
    }

    private function collectChunkCssFiles($file, &$visited = []): array
    {
        $cssFiles = [];

        if (!is_array($file)) {
            return $cssFiles;
        }

        $fileId = isset($file['file']) ? $file['file'] : md5(wp_json_encode($file));
        if (isset($visited[$fileId])) {
            return $cssFiles;
        }
        $visited[$fileId] = true;

        if (isset($file['css']) && is_array($file['css'])) {
            foreach ($file['css'] as $path) {
                if (is_string($path) && $path !== '') {
                    $cssFiles[] = $path;
                }
            }
        }

        if (isset($file['imports']) && is_array($file['imports'])) {
            foreach ($file['imports'] as $importKey) {
                if (!isset($this->manifestData[$importKey]) || !is_array($this->manifestData[$importKey])) {
                    continue;
                }

                $cssFiles = array_merge($cssFiles, $this->collectChunkCssFiles($this->manifestData[$importKey], $visited));
            }
        }

        return array_values(array_unique($cssFiles));
    }

    public function with($params)
    {
        if (!is_array($params) || !Arr::isAssoc($params) || empty($this->lastJsHandle)) {
            $this->lastJsHandle = null;
            return;
        }

        foreach ($params as $key => $val) {
            wp_localize_script($this->lastJsHandle, $key, $val);
        }
        $this->lastJsHandle = null;
    }

    public static function enqueueStyle($handle, $src, $dependency = [], $version = null, $media = 'all')
    {
        static::getInstance()->enqueue_style(
            $handle,
            $src,
            $dependency,
            $version,
            $media
        );
    }

    private function enqueue_style($handle, $src, $dependency = [], $version = null, $media = 'all')
    {
        if ($this->shouldServeViaDevServer()) {
            $srcPath = $this->getVitePath() . $src;
        } else {
            $assetFile = $this->getFileFromManifest($src);
            $srcPath = $this->getProductionFilePath($assetFile);
        }

        if (empty($srcPath)) {
            return;
        }

        $version = empty($version) ? FLUENTCRM_PLUGIN_VERSION : $version;

        wp_enqueue_style(
            $handle,
            $srcPath,
            $dependency,
            $version,
            $media
        );
    }

    public static function enqueueStaticScript($handle, $src, $dependency = [], $version = null, $inFooter = false): Vite
    {
        $version = empty($version) ? FLUENTCRM_PLUGIN_VERSION : $version;

        return static::getInstance()->enqueue_static_script(
            $handle,
            $src,
            $dependency,
            $version,
            $inFooter
        );
    }

    private function enqueue_static_script($handle, $src, $dependency = [], $version = null, $inFooter = false): Vite
    {
        $version = empty($version) ? FLUENTCRM_PLUGIN_VERSION : $version;
        
        wp_enqueue_script(
            $handle,
            $this->getStaticEnqueuePath($src),
            $dependency,
            $version,
            $inFooter
        );

        return $this;
    }

    private function getStaticEnqueuePath($path): string
    {
        if ($this->shouldServeViaDevServer()) {
            return $this->getVitePath() . $path;
        }

        return $this->get_asset_url($path);
    }

    public static function enqueueStaticStyle($handle, $src, $dependency = [], $version = null, $media = 'all')
    {
        $version = empty($version) ? FLUENTCRM_PLUGIN_VERSION : $version;

        static::getInstance()->enqueue_static_style(
            $handle, $src, $dependency, $version, $media
        );
    }

    private function enqueue_static_style($handle, $src, $dependency = [], $version = null, $media = 'all')
    {
        $version = empty($version) ? FLUENTCRM_PLUGIN_VERSION : $version;

        wp_enqueue_style(
            $handle,
            $this->getStaticEnqueuePath($src),
            $dependency,
            $version,
            $media
        );
    }

    public static function underDevelopment(): bool
    {
        return static::getInstance()->usingDevMode();
    }

    public function usingDevMode(): bool
    {
        $app = FluentCrm();
        return $app['config']->get('app.env') === 'dev';
    }

    /**
     * True only when env=dev AND the Vite dev server is reachable.
     * Use this — not usingDevMode() — to decide whether to proxy URLs to
     * the Vite server. When the server is down we fall back to built assets.
     */
    private function shouldServeViaDevServer(): bool
    {
        return $this->usingDevMode() && $this->isViteServerRunning();
    }

    /**
     * Check if Vite dev server is actually running
     */
    private function isViteServerRunning(): bool
    {
        static $isRunning = null;
        
        if ($isRunning !== null) {
            return $isRunning;
        }
        
        // Check if Vite client endpoint is accessible
        $viteUrl = $this->viteHostProtocol . $this->viteHost . ':' . $this->vitePort . '/@vite/client';
        
        $response = wp_remote_get($viteUrl, [
            'timeout' => 1,
            'sslverify' => false
        ]);
        
        $isRunning = !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
        
        return $isRunning;
    }

    public function getVitePath(): string
    {
        $protocol = rtrim($this->viteHostProtocol, ':/');
        $host = rtrim($this->viteHost, '/');
        $port = $this->vitePort;
        $resource = ltrim($this->resourceDirectory, '/');

        return sprintf('%s://%s:%s/%s', $protocol, $host, $port, $resource);
    }

    public static function getEnqueuePath($path = ''): string
    {
        $vite = static::getInstance();
        
        // Normalize the path - remove leading slash
        $path = ltrim($path, '/');

        if (!$vite->usingDevMode()) {
            // In production, map the path to source path first for manifest lookup
            $sourcePath = $vite->getSourcePathForManifest($path);
            $assetFile = $vite->getFileFromManifest($sourcePath);
            if ($assetFile) {
                $srcPath = $vite->getProductionFilePath($assetFile);
            } else {
                // Fallback to direct asset path
                $srcPath = static::getAssetPath() . $path;
            }
        } else {
            // Check if Vite dev server is actually running
            if ($vite->isViteServerRunning()) {
                // Use Vite dev server URL (source path, served via HMR)
                $srcPath = $vite->mapToSourcePath($path);
            } else {
                // Vite server not running — resolve via manifest just like production.
                // The plugin ships with env=dev, so without this the fallback would use
                // the old Mix path pattern (assets/admin/js/app.js) which doesn't
                // exist in the Vite output (assets/admin/app.js). 404 = blank app.
                $sourcePath = $vite->getSourcePathForManifest($path);
                $assetFile = $vite->getFileFromManifest($sourcePath);
                if ($assetFile) {
                    $srcPath = $vite->getProductionFilePath($assetFile);
                } else {
                    // Last resort: direct path (for assets not in manifest)
                    $srcPath = static::getAssetPath() . $path;
                }
            }
        }

        return $srcPath;
    }
    
    /**
     * Resolve a Mix-style enqueue path to a dev-server URL.
     * Used when the Vite dev server is running.
     */
    private function mapToSourcePath($path): string
    {
        if (isset(self::MIX_TO_VITE_PATH_MAP[$path])) {
            return $this->getVitePath() . self::MIX_TO_VITE_PATH_MAP[$path];
        }

        // If path already starts with resources/, use it as-is
        if (strpos($path, 'resources/') === 0) {
            return $this->getVitePath() . substr($path, 10); // Remove 'resources/' prefix
        }

        // Fallback: use the path as-is (getVitePath includes resources/)
        return $this->getVitePath() . $path;
    }

    /**
     * Resolve a Mix-style enqueue path to the manifest source key.
     * Used in production for manifest lookups.
     */
    private function getSourcePathForManifest($path): string
    {
        return self::MIX_TO_VITE_PATH_MAP[$path] ?? $path;
    }

    public static function getAssetUrl($path = ''): string
    {
        return esc_url(static::getInstance()->get_asset_url($path) ?? '');
    }

    private function get_asset_url($path = ''): string
    {
        if ($this->shouldServeViaDevServer()) {
            return $this->getVitePath() . $path;
        }

        return FLUENTCRM_PLUGIN_URL . 'assets/' . ltrim($path, '/');
    }

    static function getAssetPath(): string
    {
        return FLUENTCRM_PLUGIN_URL . 'assets/';
    }

    /**
     * Inject Vite client for HMR in development mode
     */
    public static function injectViteClient()
    {
        $vite = static::getInstance();

        if ($vite->shouldServeViaDevServer()) {
            $protocol = rtrim($vite->viteHostProtocol, ':/');
            $host = rtrim($vite->viteHost, '/');
            $port = $vite->vitePort;

            // Vite client URL should NOT include /resources/
            $viteClientUrl = sprintf('%s://%s:%s/@vite/client', $protocol, $host, $port);
            echo '<script type="module" crossorigin src="' . esc_url($viteClientUrl) . '"></script>' . "\n";
        }
    }

}
