<?php

declare(strict_types=1);

const CSS_SELECTOR_PACKAGE = 'symfony/css-selector';

$buildDir = __DIR__;
$emogrifierDir = dirname(__DIR__);
$scopedVendorDir = $emogrifierDir . '/scoped-vendor';
$sourceCssSelectorDir = $buildDir . '/vendor/symfony/css-selector';
$targetCssSelectorDir = $scopedVendorDir . '/symfony/css-selector';
$installedJsonPath = $scopedVendorDir . '/composer/installed.json';
$installedPhpPath = $scopedVendorDir . '/composer/installed.php';
$platformCheckPath = $scopedVendorDir . '/composer/platform_check.php';
$buildLockPath = $buildDir . '/composer.lock';

if (!is_dir($sourceCssSelectorDir)) {
    fwrite(STDERR, "Missing {$sourceCssSelectorDir}. Run composer install in build directory first.\n");
    exit(1);
}

if (!file_exists($buildLockPath)) {
    fwrite(STDERR, "Missing composer.lock in build directory.\n");
    exit(1);
}

$lock = json_decode((string) file_get_contents($buildLockPath), true);
if (!is_array($lock)) {
    fwrite(STDERR, "Could not parse build composer.lock.\n");
    exit(1);
}

$package = null;
foreach (($lock['packages'] ?? []) as $candidate) {
    if (($candidate['name'] ?? '') === CSS_SELECTOR_PACKAGE) {
        $package = $candidate;
        break;
    }
}

if (!$package) {
    fwrite(STDERR, "Could not find " . CSS_SELECTOR_PACKAGE . " in build composer.lock.\n");
    exit(1);
}

$version = (string) ($package['version'] ?? '');
$reference = (string) ($package['source']['reference'] ?? '');
$requirePhp = (string) ($package['require']['php'] ?? '>=7.3.0');
$supportSource = (string) ($package['support']['source'] ?? '');
$distUrl = (string) ($package['dist']['url'] ?? '');

if (!$version || !$reference) {
    fwrite(STDERR, "Missing version/reference in build lock for " . CSS_SELECTOR_PACKAGE . ".\n");
    exit(1);
}

$normalizedVersion = preg_replace('/^v/', '', $version) . '.0';
if (!$normalizedVersion) {
    fwrite(STDERR, "Failed to compute normalized version for {$version}.\n");
    exit(1);
}

rrmdir($targetCssSelectorDir);
mkdirOrFail(dirname($targetCssSelectorDir));
copyDir($sourceCssSelectorDir, $targetCssSelectorDir);

removeIfExists($targetCssSelectorDir . '/README.md');
removeIfExists($targetCssSelectorDir . '/CHANGELOG.md');
removeIfExists($targetCssSelectorDir . '/LICENSE');
removeIfExists($targetCssSelectorDir . '/composer.json');

$phpFiles = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($targetCssSelectorDir, FilesystemIterator::SKIP_DOTS)
);

foreach ($phpFiles as $file) {
    /** @var SplFileInfo $file */
    if ($file->getExtension() !== 'php') {
        continue;
    }

    $path = $file->getPathname();
    $content = (string) file_get_contents($path);

    $content = str_replace(
        'namespace Symfony\\Component\\CssSelector',
        'namespace FluentEmogrifier\\Vendor\\Symfony\\Component\\CssSelector',
        $content
    );
    $content = str_replace(
        'use Symfony\\Component\\CssSelector\\',
        'use FluentEmogrifier\\Vendor\\Symfony\\Component\\CssSelector\\',
        $content
    );

    $content = preg_replace(
        '/(?<![A-Za-z0-9_\\\\])str_contains\s*\(/',
        '\\FluentEmogrifier\\Vendor\\Symfony\\Component\\CssSelector\\Util\\Php74Compat::strContains(',
        $content
    );

    file_put_contents($path, (string) $content);
}

$compatDir = $targetCssSelectorDir . '/Util';
mkdirOrFail($compatDir);
file_put_contents($compatDir . '/Php74Compat.php', php74CompatClass());

updateInstalledJson($installedJsonPath, $version, $normalizedVersion, $reference, $requirePhp, $supportSource, $distUrl);
updateInstalledPhp($installedPhpPath, $version, $normalizedVersion, $reference);
updatePlatformCheck($platformCheckPath);

echo "Synced css-selector {$version} ({$reference}) into scoped-vendor.\n";

function mkdirOrFail(string $path): void
{
    if (is_dir($path)) {
        return;
    }

    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException("Failed to create directory: {$path}");
    }
}

function rrmdir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($items as $item) {
        /** @var SplFileInfo $item */
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }

    rmdir($dir);
}

function copyDir(string $source, string $target): void
{
    mkdirOrFail($target);

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($items as $item) {
        /** @var SplFileInfo $item */
        $relative = substr($item->getPathname(), strlen($source) + 1);
        $destination = $target . '/' . $relative;

        if ($item->isDir()) {
            mkdirOrFail($destination);
            continue;
        }

        mkdirOrFail(dirname($destination));
        copy($item->getPathname(), $destination);
    }
}

function removeIfExists(string $path): void
{
    if (file_exists($path)) {
        unlink($path);
    }
}

function php74CompatClass(): string
{
    return <<<'PHP'
<?php

namespace FluentEmogrifier\Vendor\Symfony\Component\CssSelector\Util;

/**
 * Minimal compatibility helpers for keeping scoped css-selector PHP 7.4-safe.
 */
final class Php74Compat
{
    public static function strContains(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }

        return strpos($haystack, $needle) !== false;
    }
}
PHP;
}

function updateInstalledJson(
    string $path,
    string $version,
    string $normalizedVersion,
    string $reference,
    string $requirePhp,
    string $supportSource,
    string $distUrl
): void {
    $json = json_decode((string) file_get_contents($path), true);
    if (!is_array($json) || !isset($json['packages']) || !is_array($json['packages'])) {
        throw new RuntimeException("Could not parse installed.json: {$path}");
    }

    foreach ($json['packages'] as &$package) {
        if (($package['name'] ?? '') !== CSS_SELECTOR_PACKAGE) {
            continue;
        }

        $package['version'] = $version;
        $package['version_normalized'] = $normalizedVersion;

        if (isset($package['source']) && is_array($package['source'])) {
            $package['source']['reference'] = $reference;
        }

        if (isset($package['dist']) && is_array($package['dist'])) {
            $package['dist']['reference'] = $reference;
            if ($distUrl) {
                $package['dist']['url'] = $distUrl;
            }
        }

        if (!isset($package['require']) || !is_array($package['require'])) {
            $package['require'] = [];
        }
        $package['require']['php'] = $requirePhp;

        if ($supportSource) {
            if (!isset($package['support']) || !is_array($package['support'])) {
                $package['support'] = [];
            }
            $package['support']['source'] = $supportSource;
        }

        break;
    }
    unset($package);

    file_put_contents(
        $path,
        json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL
    );
}

function updateInstalledPhp(string $path, string $version, string $normalizedVersion, string $reference): void
{
    $content = (string) file_get_contents($path);
    $content = preg_replace_callback(
        "/'symfony\\/css-selector' => array\\([\\s\\S]*?\\n\\s*\\),/",
        function (array $matches) use ($version, $normalizedVersion, $reference): string {
            $block = $matches[0];
            $block = preg_replace("/'pretty_version' => '[^']+'/", "'pretty_version' => '{$version}'", $block, 1);
            $block = preg_replace("/'version' => '[^']+'/", "'version' => '{$normalizedVersion}'", $block, 1);
            $block = preg_replace("/'reference' => '[^']+'/", "'reference' => '{$reference}'", $block, 1);
            return (string) $block;
        },
        $content,
        1
    );

    file_put_contents($path, $content);
}

function updatePlatformCheck(string $path): void
{
    $content = (string) file_get_contents($path);
    $content = preg_replace('/PHP_VERSION_ID >= \d+/', 'PHP_VERSION_ID >= 70300', $content);
    $content = preg_replace('/>= [0-9]+\.[0-9]+\.[0-9]+/', '>= 7.3.0', $content);
    file_put_contents($path, $content);
}
