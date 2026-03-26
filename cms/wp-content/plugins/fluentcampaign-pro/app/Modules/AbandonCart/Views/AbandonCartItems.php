<?php
if (!defined('ABSPATH')) exit;
/**
 * @var $cartItems array
 * @var $currency string
 */

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
    .tax_label {
        font-size: 10px;
        color: #888;
    }
</style>


<div class="fc-abandoned-cart-table">
    <table style="border-spacing: 0;border-collapse: separate;width: 100%;border: 1px solid #D6DAE1;border-radius: 8px;">
        <thead>
        <tr>
            <th style="border-right:1px solid #e9ecf0;background: #EAECF0;padding: 8px 12px;color: #323232;line-height: 26px;font-weight: 700;font-size: 14px;width: 100px;border-top-left-radius: 6px;"><?php esc_html_e('Image', 'fluentcampaign-pro'); ?></th>
            <th style="min-width: 140px;border-right:1px solid #e9ecf0;background: #EAECF0;padding: 8px 12px;color: #323232;line-height: 26px;font-weight: 700;font-size: 14px;"><?php esc_html_e('Item', 'fluentcampaign-pro'); ?></th>
            <th style="border-right:1px solid #e9ecf0;background: #EAECF0;padding: 8px 12px;color: #323232;line-height: 26px;font-weight: 700;font-size: 14px;width: 60px;"><?php esc_html_e('Qty', 'fluentcampaign-pro'); ?></th>
            <th style="border-right:1px solid #e9ecf0;background: #EAECF0;padding: 8px 12px;color: #323232;line-height: 26px;font-weight: 700;font-size: 14px;width: 100px;border-top-right-radius: 6px;"><?php esc_html_e('Price', 'fluentcampaign-pro'); ?></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($cartItems as $cartItem) {

            $product = wc_get_product($cartItem['product_id']);
            $product_image_url = wp_get_attachment_url($product->get_image_id());
            if (!$product_image_url) {
                $product_image_url = wc_placeholder_img_src();
            }
            $decimals   = wc_get_price_decimals();       // e.g. 2 or 0
            $decimal_separator  = wc_get_price_decimal_separator(); // e.g. "." or ","
            $thousands_separator  = wc_get_price_thousand_separator(); // e.g. "," or "."
            $price = \FluentCrm\Framework\Support\Arr::get($cartItem, 'line_total_with_tax');
            $price = number_format($price, $decimals, $decimal_separator, $thousands_separator);
            $price = $currency . ' ' . $price;
            $includingTax = \FluentCrm\Framework\Support\Arr::get($cartItem, 'tax_including');
            ?>
            <tr>
                <td style="padding: 8px 12px;border-top: 1px solid #e9ecf0;border-right: 1px solid #e9ecf0;"><div class="table-head" style="display: none;width: 100px;min-width: 100px;flex:none;background: rgb(234, 236, 240);font-weight: 600;font-size: 14px;padding: 10px 12px;line-height: 1rem;"><?php esc_html_e('Image', 'fluentcampaign-pro') ; ?></div>
                    <img style="width: 50px;height: 50px;object-fit: contain;display: block;margin-top: 4px;margin-bottom: 4px;" src="<?php echo esc_url($product_image_url); ?>" alt="<?php echo esc_attr($cartItem['title']); ?>">
                </td>
                <td style="padding: 8px 12px;overflow-wrap: break-word;border-top: 1px solid #e9ecf0;border-right: 1px solid #e9ecf0;"><div class="table-head" style="display: none;width: 100px;min-width: 100px;flex:none;background: rgb(234, 236, 240);font-weight: 600;font-size: 14px;padding: 10px 12px;line-height: 1rem;"><?php esc_html_e('Item', 'fluentcampaign-pro') ; ?></div><?php echo esc_html($cartItem['title']); ?></td>
                <td style="padding: 8px 12px;border-top: 1px solid #e9ecf0;border-right: 1px solid #e9ecf0;"><div class="table-head" style="display: none;width: 100px;min-width: 100px;flex:none;background: rgb(234, 236, 240);font-weight: 600;font-size: 14px;padding: 10px 12px;line-height: 1rem;"><?php esc_html_e('Qty', 'fluentcampaign-pro') ; ?></div><?php echo esc_html($cartItem['quantity']); ?></td>
                <td style="padding: 8px 12px;border-top: 1px solid #e9ecf0;"><div class="table-head" style="display: none;width: 100px;min-width: 100px;flex:none;background: rgb(234, 236, 240);font-weight: 600;font-size: 14px;padding: 10px 12px;line-height: 1rem;"><?php esc_html_e('Price', 'fluentcampaign-pro') ; ?></div><?php echo esc_html($price); if($includingTax == 'yes') echo ' <small class="tax_label">(inc. tax)</small>'; ?></td>
            </tr>
        <?php } ?>
        </tbody>
    </table>
</div>
