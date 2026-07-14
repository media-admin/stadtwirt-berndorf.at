<?php

namespace FluentCrm\App\Modules\AbandonCart\Drivers;

use FluentCrm\App\Modules\AbandonCart\AbandonCartModel;

abstract class AbstractCartDriver
{

    public $logo = '';

    /**
     * Unique provider slug. Must match the `provider` column value in fc_abandoned_carts.
     * Examples: 'woo', 'fluent_cart', 'edd'
     *
     * @return string
     */
    abstract public function getProviderSlug();

    /**
     * Human-readable provider label for UI display.
     *
     * @return string
     */
    abstract public function getProviderLabel();

    /**
     * Whether this driver's platform is currently available (plugin active).
     *
     * @return bool
     */
    abstract public function isAvailable();

    /**
     * Register all hooks, filters, frontend scripts, ajax handlers,
     * order lifecycle listeners, and cart recovery URL handlers.
     *
     * @return void
     */
    abstract public function register();

    /**
     * Register the automation trigger class for this provider.
     *
     * @return void
     */
    abstract public function registerAutomationTrigger();

    /**
     * Check if the given cart is within a cool-off period for this provider.
     *
     * @param AbandonCartModel $cart
     * @return bool
     */
    abstract public function isWithinCoolOffPeriod(AbandonCartModel $cart);

    /**
     * Render cart items as HTML for email templates.
     *
     * @param AbandonCartModel $cart
     * @return string
     */
    abstract public function getCartItemsHtml(AbandonCartModel $cart);

    /**
     * Format a monetary amount for display using this provider's currency formatting.
     *
     * @param float|string $amount
     * @param string $currency
     * @return string
     */
    abstract public function formatPrice($amount, $currency = '');

    /**
     * Return the recovery URL for this cart.
     *
     * @param AbandonCartModel $cart
     * @return string
     */
    abstract public function getRecoveryUrl(AbandonCartModel $cart);

    /**
     * Extract product IDs and category IDs from the cart for condition matching.
     *
     * @param AbandonCartModel $cart
     * @return array ['product_ids' => [...], 'category_ids' => [...]]
     */
    abstract public function extractCartConditionData(AbandonCartModel $cart);

    /**
     * Enrich cart data for the admin listing API response.
     * Adds product images, order URL, etc.
     *
     * @param AbandonCartModel $cart
     * @return AbandonCartModel
     */
    abstract public function enrichCartForListing(AbandonCartModel $cart);

    /**
     * Return provider-specific data for the settings API response.
     * e.g. WooCommerce returns order statuses.
     *
     * @return array
     */
    public function getProviderSettingsResponse()
    {
        return [];
    }

    /**
     * Return provider-specific settings fields for the settings page.
     *
     * @return array
     */
    public function getSettingsFields()
    {
        return [];
    }

    /**
     * Return provider-specific default settings to merge into global defaults.
     *
     * @return array
     */
    public function getProviderSettingsDefaults()
    {
        return [];
    }

    /**
     * Apply provider-specific processing to settings after loading.
     *
     * @param array $settings
     * @return array
     */
    public function processSettings($settings)
    {
        return $settings;
    }

    /**
     * Get the trigger name for this provider's automation.
     *
     * @return string
     */
    public function getTriggerName()
    {
        return 'fc_ab_cart_simulation_' . $this->getProviderSlug();
    }

    /**
     * Get the handler name for cart recovery URL routing.
     *
     * @return string
     */
    public function getHandlerName()
    {
        return 'fc_cart_' . $this->getProviderSlug();
    }

    /**
     * Get the smart code group key for this provider.
     *
     * @return string
     */
    public function getSmartCodeGroupKey()
    {
        return 'ab_cart_' . $this->getProviderSlug();
    }

    /**
     * Get the base path for view templates.
     * Drivers should override this to point to their own plugin's Views directory.
     *
     * @return string
     */
    protected function getViewsBasePath()
    {
        return '';
    }

    /**
     * Load a view template from the driver's Views directory.
     *
     * @param string $templateName
     * @param array $data
     * @return string
     */
    protected function loadView($templateName, $data)
    {
        $basePath = $this->getViewsBasePath();
        if (!$basePath) {
            return '';
        }

        extract($data, EXTR_SKIP);
        ob_start();
        include $basePath . $templateName . '.php';
        return ltrim(ob_get_clean());
    }

    public function getLogo()
    {
        return $this->logo;
    }
}
