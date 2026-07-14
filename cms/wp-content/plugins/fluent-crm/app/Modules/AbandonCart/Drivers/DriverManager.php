<?php

namespace FluentCrm\App\Modules\AbandonCart\Drivers;

use FluentCrm\App\Modules\AbandonCart\AbCartHelper;

class DriverManager
{
    /** @var AbstractCartDriver[] keyed by provider slug */
    private static $drivers = [];

    public static function register(AbstractCartDriver $driver)
    {
        static::$drivers[$driver->getProviderSlug()] = $driver;
    }

    /**
     * @param string $providerSlug
     * @return AbstractCartDriver|null
     */
    public static function getDriver($providerSlug)
    {
        return static::$drivers[$providerSlug] ?? null;
    }

    /**
     * @return AbstractCartDriver[]
     */
    public static function getAll()
    {
        return static::$drivers;
    }

    /**
     * @return AbstractCartDriver[] Only drivers whose platform is currently active
     */
    public static function getAvailable()
    {
        return array_filter(static::$drivers, function ($driver) {
            return $driver->isAvailable();
        });
    }

    /**
     * @return string[]
     */
    public static function getAvailableSlugs()
    {
        return array_keys(static::getAvailable());
    }

    /**
     * @return bool
     */
    public static function hasAvailableDrivers()
    {
        return count(static::getAvailable()) > 0;
    }

    /**
     * Get drivers that are both available (plugin installed) and enabled in settings.
     *
     * @return AbstractCartDriver[]
     */
    public static function getEnabled()
    {
        $available = static::getAvailable();

        $settings = AbCartHelper::getSettings(true);

        $enabledProviders = $settings['enabled_providers'] ?? [];

        if (empty($enabledProviders)) {
            return [];
        }

        return array_filter($available, function ($driver) use ($enabledProviders) {
            return in_array($driver->getProviderSlug(), $enabledProviders);
        });
    }

    /**
     * @return string[]
     */
    public static function getEnabledSlugs()
    {
        return array_keys(static::getEnabled());
    }

    /**
     * Check if a specific driver is enabled
     *
     * @param string $providerSlug
     * @return bool
     */
    public static function isDriverEnabled($providerSlug)
    {
        return isset(static::getEnabled()[$providerSlug]);
    }

    /**
     * Get trigger names from all enabled drivers
     *
     * @return string[]
     */
    public static function getEnabledTriggerNames()
    {
        return array_map(function ($driver) {
            return $driver->getTriggerName();
        }, static::getEnabled());
    }

    /**
     * Get smart code group keys from all registered drivers
     *
     * @return string[]
     */
    public static function getAllSmartCodeGroupKeys()
    {
        return array_map(function ($driver) {
            return $driver->getSmartCodeGroupKey();
        }, static::getAll());
    }

    /**
     * Find a driver by its smart code group key (e.g. 'ab_cart_woo')
     *
     * @param string $groupKey
     * @return AbstractCartDriver|null
     */
    public static function getDriverByGroupKey($groupKey)
    {
        foreach (static::$drivers as $driver) {
            if ($driver->getSmartCodeGroupKey() === $groupKey) {
                return $driver;
            }
        }

        return null;
    }

    /**
     * Format a price using the appropriate driver, with a generic fallback
     *
     * @param float|string $amount
     * @param string $currency
     * @param string|null $providerSlug
     * @return string
     */
    public static function formatPrice($amount, $currency = '', $providerSlug = null)
    {
        if ($providerSlug) {
            $driver = static::getDriver($providerSlug);
            if ($driver) {
                return $driver->formatPrice($amount, $currency);
            }
        }

        // Fall back to first available driver
        $available = static::getAvailable();
        if ($available) {
            $driver = reset($available);
            return $driver->formatPrice($amount, $currency);
        }

        return '$' . number_format((float)$amount, 2);
    }
}
