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
        woocommerce_wp_text_input(['id' => '_mh_material', 'label' => __('Material', 'morrow-house-core')]);
        woocommerce_wp_textarea_input(['id' => '_mh_care', 'label' => __('Care', 'morrow-house-core')]);
    }

    public static function save(int $product_id): void {
        // WooCommerce ja checa nonce e permissao antes de disparar este hook.
        // A checagem aqui e proposital mesmo assim: o hook e publico e outro
        // plugin pode dispara-lo fora do fluxo do admin, onde nada garante que
        // quem chamou pode editar este produto.
        if (!current_user_can('edit_product', $product_id)) {
            return;
        }

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
                . ($material ? '<dt>' . esc_html__('Material', 'morrow-house-core') . '</dt><dd>' . esc_html($material) . '</dd>' : '')
                . ($care ? '<dt>' . esc_html__('Care', 'morrow-house-core') . '</dt><dd>' . esc_html($care) . '</dd>' : '')
                . '</dl>';
        }
    }
}
