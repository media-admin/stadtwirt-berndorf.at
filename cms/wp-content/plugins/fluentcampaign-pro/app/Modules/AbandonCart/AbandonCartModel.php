<?php

namespace FluentCampaign\App\Modules\AbandonCart;

use FluentCrm\App\Models\Model;
use FluentCrm\App\Services\Funnel\FunnelHelper;
use FluentCrm\Framework\Support\Arr;

class AbandonCartModel extends Model
{
    protected $table = 'fc_abandoned_carts';

    protected $fillable = [
        'checkout_key',
        'cart_hash',
        'contact_id',
        'is_optout',
        'full_name',
        'email',
        'provider',
        'user_id',
        'order_id',
        'automation_id',
        'checkout_page_id',
        'status',
        'subtotal',
        'shipping',
        'discounts',
        'fees',
        'tax',
        'total',
        'currency',
        'cart',
        'note',
        'recovered_at',
        'abandoned_at',
        'click_counts'
    ];

    protected $searchable = ['full_name', 'email'];

    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->checkout_key = md5(time() . wp_generate_uuid4());
        });
    }

    public function scopeProvider($query, $provider)
    {
        return $query->where('provider', $provider);
    }

    public function scopeStatusBy($query, $status)
    {
        if (!$status || $status == 'all') {
            return $query;
        }

        return $query->where('status', $status);
    }

    public function scopeSearchBy($query, $search)
    {
        if (!$search) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('full_name', 'LIKE', '%' . $search . '%')
                ->orWhere('email', 'LIKE', '%' . $search . '%');
        });
    }

    public function setCartAttribute($data)
    {
        $this->attributes['cart'] = \maybe_serialize($data);
    }

    public function getCartAttribute($data)
    {
        return \maybe_unserialize($data);
    }

    public function subscriber()
    {
        return $this->belongsTo('FluentCrm\App\Models\Subscriber', 'contact_id');
    }

    public function automation()
    {
        return $this->belongsTo('FluentCrm\App\Models\Funnel', 'automation_id');
    }

    public function getAddress($type = 'billing')
    {
        $customerData = Arr::get($this->cart, 'customer_data', []);

        if (Arr::get($customerData, 'differentShipping') != 'yes') {
            $type = 'billingAddress';
        } else {
            $type = 'shippingAddress';
        }

        return array_filter([
            'address_1' => Arr::get($customerData, $type . '.address_1'),
            'address_2' => Arr::get($customerData, $type . '.address_2'),
            'city'      => Arr::get($customerData, $type . '.city'),
            'state'     => Arr::get($customerData, $type . '.state'),
            'postcode'  => Arr::get($customerData, $type . '.postcode'),
            'country'   => Arr::get($customerData, $type . '.country'),
        ]);
    }

    private function getAddressLineByKey($type, $key)
    {
        $address = $this->getAddress($type);
        return Arr::get($address, $key, '');
    }

    public function getInputProp($key, $default = '')
    {
        $customerData = Arr::get($this->cart, 'customer_data', []);

        return Arr::get($customerData, $key, $default);
    }

    public function getAddressProp($key, $addressType = 'billingAddress', $default = '')
    {
        $address = Arr::get($this->cart, 'customer_data.'.$addressType, []);

        return Arr::get($address, $key, $default);
    }

    /*
     * Get the cart items as html
     * This function is called by shortcodes/mergecodes/smartcode
     * {{ab_cart_woo.cart_items_table}}
     */
    public function getCartItemsHtml()
    {
        $cartItems = Arr::get($this->cart, 'cart_contents', []);

        $updatedCartItems = self::reconstructCartItemsWithTax($cartItems);

        $content = self::loadView('AbandonCartItems', [
            'cartItems'     => $updatedCartItems,
            'currency' => $this->currency
        ]);

        return $content;
    }
    public static function reconstructCartItemsWithTax($cartItems)
    {
        // Check if Display prices during cart and checkout sets to  including tax
        $pricesIncludeTax = get_option('woocommerce_tax_display_cart') === 'incl';

        foreach ($cartItems as $key => &$item) {
            if ($pricesIncludeTax) {
                // Add line tax to line total
                $item['line_total_with_tax'] = $item['line_total'] + $item['line_tax'];
                $item['tax_including'] = 'yes';
            } else {
                // Keep the original line total if tax is not included
                $item['line_total_with_tax'] = $item['line_total'];
                $item['tax_including'] = 'no';
            }
        }

        return $cartItems;
    }

    private static function loadView($templateName, $data)
    {
        extract($data, EXTR_SKIP);
        ob_start();
        include FLUENTCAMPAIGN_PLUGIN_PATH.'app/Modules/AbandonCart/Views/' . $templateName .'.php';
        return ltrim(ob_get_clean());
    }

    public function getRecoveryUrl()
    {
        if ($this->status != 'processing') {
            return '';
        }

        return add_query_arg([
            'fluentcrm'  => 1,
            'route'      => 'general',
            'handler'    => 'fc_cart_' . $this->provider,
            'fc_ab_hash' => $this->checkout_key
        ], home_url());
    }

    public function deleteCart()
    {
        if ($this->automation_id && $this->contact_id) {
            FunnelHelper::removeSubscribersFromFunnel($this->automation_id, [$this->contact_id]);
        }

        $this->delete();
    }

    public function optOut()
    {
        if ($this->is_optout) {
            return $this;
        }

        $this->is_optout = 1;
        $this->status = 'opt_out';
        $this->save();

        if (!$this->contact_id || !$this->automation_id) {
            return $this;
        }

        if ($this->status == 'processing') {
            FunnelHelper::removeSubscribersFromFunnel($this->automation_id, [$this->contact_id]);
            $this->automation_id = null;
            $this->save();
        }

        return $this;
    }
}
