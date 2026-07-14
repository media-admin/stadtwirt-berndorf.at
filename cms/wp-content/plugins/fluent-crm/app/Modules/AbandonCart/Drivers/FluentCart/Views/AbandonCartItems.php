<?php
if (!defined('ABSPATH')) exit;
/**
 * @var $cartItems array
 * @var $currency string
 */

$formatPrice = function ($amount) use ($currency) {
    if (class_exists('\FluentCart\Api\CurrencySettings')) {
        return \FluentCart\Api\CurrencySettings::getPriceHtml((int)$amount, $currency ?: null);
    }
    // Fallback: amount is in cents
    $symbol = $currency ?: '$';
    return $symbol . number_format((float)$amount / 100, 2);
};
?>

<style>
    .fc-abandoned-cart-table *,
    .fc-abandoned-cart-table {
        box-sizing: border-box;
    }

    @media (max-width: 767px) {
        .fc-abandoned-cart-table table thead {
            display: none;
        }

        .fc-abandoned-cart-table table {
            display: block !important;
            border: none !important;
        }

        .fc-abandoned-cart-table table tbody {
            display: block !important;
            width: 100%;
        }

        .fc-abandoned-cart-table table thead tr th:last-child,
        .fc-abandoned-cart-table table thead tr th:nth-child(3),
        .fc-abandoned-cart-table table thead tr td:first-child {
            width: 100% !important;
        }

        .fc-abandoned-cart-table table tbody tr td:first-child img {
            margin-top: 6px;
            margin-bottom: 6px;
        }

        .fc-abandoned-cart-table table tbody tr {
            display: block !important;
            flex-direction: column !important;
            margin-bottom: 10px;
            border: 1px solid rgb(214, 218, 225);
            border-radius: 4px;
        }

        .fc-abandoned-cart-table table tbody tr td:first-child {
            border-top: none;
        }

        .fc-abandoned-cart-table table tbody tr td {
            display: flex !important;
            width: 100% !important;
            border-right: none !important;
            gap: 6px;
            padding: 0 5px 0 0 !important;
        }

        .fc-abandoned-cart-table table tbody tr td .table-head {
            display: inline-block !important;
            margin-right: 6px;
            flex: none !important;
        }
    }
</style>

<div class="fc-abandoned-cart-table">
    <table
        style="border-spacing: 0;border-collapse: separate;width: 100%;border: 1px solid #D6DAE1;border-radius: 8px;">
        <thead>
        <tr>
            <th style="border-right:1px solid #e9ecf0;background: #EAECF0;padding: 8px 12px;color: #323232;line-height: 26px;font-weight: 700;font-size: 14px;width: 100px;border-top-left-radius: 6px;"><?php esc_html_e('Image', 'fluent-crm'); ?></th>
            <th style="min-width: 140px;border-right:1px solid #e9ecf0;background: #EAECF0;padding: 8px 12px;color: #323232;line-height: 26px;font-weight: 700;font-size: 14px;"><?php esc_html_e('Item', 'fluent-crm'); ?></th>
            <th style="border-right:1px solid #e9ecf0;background: #EAECF0;padding: 8px 12px;color: #323232;line-height: 26px;font-weight: 700;font-size: 14px;width: 60px;"><?php esc_html_e('Qty', 'fluent-crm'); ?></th>
            <th style="border-right:1px solid #e9ecf0;background: #EAECF0;padding: 8px 12px;color: #323232;line-height: 26px;font-weight: 700;font-size: 14px;width: 100px;border-top-right-radius: 6px;"><?php esc_html_e('Price', 'fluent-crm'); ?></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($cartItems as $cartItem) {
            $imageUrl = \FluentCrm\Framework\Support\Arr::get($cartItem, 'featured_media', '');
            if (!$imageUrl) {
                $postId = \FluentCrm\Framework\Support\Arr::get($cartItem, 'post_id');
                if ($postId) {
                    $imageUrl = get_the_post_thumbnail_url($postId, 'thumbnail');
                }
            }

            $title = \FluentCrm\Framework\Support\Arr::get($cartItem, 'post_title', '');
            $subTitle = \FluentCrm\Framework\Support\Arr::get($cartItem, 'variation_title', '');

            if ($subTitle && $title != $subTitle) {
                $title .= ' - ' . $subTitle;
            }

            $quantity = (int)\FluentCrm\Framework\Support\Arr::get($cartItem, 'quantity', 1);
            $subtotal = (int)\FluentCrm\Framework\Support\Arr::get($cartItem, 'subtotal', 0);
            $discount = (int)\FluentCrm\Framework\Support\Arr::get($cartItem, 'discount_total', 0);
            $lineTotal = $subtotal - $discount;
            ?>
            <tr>
                <td style="padding: 8px 12px;border-top: 1px solid #e9ecf0;border-right: 1px solid #e9ecf0;">
                    <div class="table-head"
                         style="display: none;width: 100px;min-width: 100px;flex:none;background: rgb(234, 236, 240);font-weight: 600;font-size: 14px;padding: 10px 12px;line-height: 1rem;"><?php esc_html_e('Image', 'fluent-crm'); ?></div>
                    <?php if ($imageUrl): ?>
                        <img
                            style="width: 50px;height: 50px;object-fit: contain;display: block;margin-top: 4px;margin-bottom: 4px;"
                            src="<?php echo esc_url($imageUrl); ?>" alt="<?php echo esc_attr($title); ?>">
                    <?php endif; ?>
                </td>
                <td style="padding: 8px 12px;overflow-wrap: break-word;border-top: 1px solid #e9ecf0;border-right: 1px solid #e9ecf0;">
                    <div class="table-head"
                         style="display: none;width: 100px;min-width: 100px;flex:none;background: rgb(234, 236, 240);font-weight: 600;font-size: 14px;padding: 10px 12px;line-height: 1rem;"><?php esc_html_e('Item', 'fluent-crm'); ?></div><?php echo esc_html($title); ?>
                </td>
                <td style="padding: 8px 12px;border-top: 1px solid #e9ecf0;border-right: 1px solid #e9ecf0;">
                    <div class="table-head"
                         style="display: none;width: 100px;min-width: 100px;flex:none;background: rgb(234, 236, 240);font-weight: 600;font-size: 14px;padding: 10px 12px;line-height: 1rem;"><?php esc_html_e('Qty', 'fluent-crm'); ?></div><?php echo esc_html($quantity); ?>
                </td>
                <td style="padding: 8px 12px;border-top: 1px solid #e9ecf0;">
                    <div class="table-head" style="display: none;width: 100px;min-width: 100px;flex:none;background: rgb(234, 236, 240);font-weight: 600;font-size: 14px;padding: 10px 12px;line-height: 1rem;"><?php esc_html_e('Price', 'fluent-crm'); ?></div><?php echo wp_kses_post($formatPrice($lineTotal)); ?>
                </td>
            </tr>
        <?php } ?>
        </tbody>
    </table>
</div>
