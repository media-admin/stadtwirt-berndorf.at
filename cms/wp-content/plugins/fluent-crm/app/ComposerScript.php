<?php
// phpcs:disable
namespace FluentCrm\App;

use Composer\Script\Event;
use InvalidArgumentException;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
 
class ComposerScript
{
    public static function postInstall(Event $event)
    {
        static::postUpdate($event);
    }

    public static function postUpdate(Event $event)
    {
        $vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');
        $composerJson = json_decode(file_get_contents($vendorDir . '/../composer.json'), true);
        $namespace = $composerJson['extra']['wpfluent']['namespace']['current'];

        if (!$namespace) {
            throw new InvalidArgumentException("Namespace not set in composer.json file.");
        }

        $itr = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
            $vendorDir.'/wpfluent/framework/src/', RecursiveDirectoryIterator::SKIP_DOTS
        ), RecursiveIteratorIterator::SELF_FIRST);

        foreach ($itr as $file) {
            if ($file->isDir()) {
                continue;
            }

            $fileName = $file->getPathname();

            $content = file_get_contents($fileName);
            $content = str_replace(
                ['WPFluent\\', 'WPFluentPackage\\'],
                [$namespace . '\\Framework\\', $namespace . '\\'],
                $content
            );

            file_put_contents($fileName, $content);
        }

        static::updateVendorComposerFiles($vendorDir, $namespace);
    }

    protected static function updateVendorComposerFiles($vendorDir, $namespace)
    {
        $composerInstalledJson = json_decode(file_get_contents(
            $installedJsonFile = $vendorDir . '/composer/installed.json'
        ), true);

        foreach ($composerInstalledJson['packages'] as &$package) {
            if ($package['name'] == 'wpfluent/framework') {
                $package['autoload']['psr-4'] = [
                    $namespace . "\\Framework\\" => "src/WPFluent"
                ];
            } else {
                $packageDir = $vendorDir . "/{$package['name']}/src/";

                if(!is_dir($packageDir)) {
                    continue;
                }

                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator(
                        $packageDir, RecursiveDirectoryIterator::SKIP_DOTS
                    ),
                    RecursiveIteratorIterator::SELF_FIRST
                );

                foreach ($iterator as $item) {

                    if ($item->isDir()) {
                        continue;
                    }

                    $fileName = $item->getPathname();
                    $content = file_get_contents($fileName);
                    $content = str_replace(
                        ['WPFluent\\', 'WPFluentPackage\\'],
                        [$namespace . '\\Framework\\', $namespace . '\\'],
                        $content
                    );

                    file_put_contents($fileName, $content);
                }

                $psr4 = array_keys($package['autoload']['psr-4']);

                $replaced = str_replace(
                    'WPFluentPackage', $namespace, $psr4[0]
                );

                $package['autoload']['psr-4'] = [
                    $replaced => "src/"
                ];

                $packageComposerJson = json_decode(file_get_contents(
                    $vendorDir .'/' . $package['name'] . '/composer.json'
                ), true);

                $packageComposerJson['autoload']['psr-4'] = [
                    $replaced => "src/"
                ];

                file_put_contents(
                    $vendorDir .'/' . $package['name'] . '/composer.json',
                    json_encode($packageComposerJson, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)
                );
            }
        }

        file_put_contents(
            $installedJsonFile,
            json_encode($composerInstalledJson, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT)
        );

        exec('composer dump-autoload');
    }
}
