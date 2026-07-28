<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

final class Morrow_House_Product_Details {
    public static function boot(): void {
        add_action('woocommerce_product_options_general_product_data', [self::class, 'fields']);
        add_action('woocommerce_process_product_meta', [self::class, 'save']);
        add_action('woocommerce_single_product_summary', [self::class, 'render'], 25);
    }

    public static function fields(): void {
        woocommerce_wp_text_input(['id' => '_mh_material', 'label' => __('Material', 'morrow-house')]);
        woocommerce_wp_textarea_input(['id' => '_mh_care', 'label' => __('Care', 'morrow-house')]);
    }

    public static function save(int $product_id): void {
        foreach (['_mh_material', '_mh_care'] as $key) {
            $value = isset($_POST[$key]) ? sanitize_textarea_field(wp_unslash($_POST[$key])) : '';
            update_post_meta($product_id, $key, $value);
        }
    }

    public static function render(): void {
        global $product;

        if (!$product instanceof WC_Product) {
            return;
        }

        $material = get_post_meta($product->get_id(), '_mh_material', true);
        $care = get_post_meta($product->get_id(), '_mh_care', true);

        if ($material || $care) {
            echo '<dl class="product-details">'
                . ($material ? '<dt>Material</dt><dd>' . esc_html($material) . '</dd>' : '')
                . ($care ? '<dt>Care</dt><dd>' . esc_html($care) . '</dd>' : '')
                . '</dl>';
        }
    }
}
