<?php
/**
 * Plugin Name: Morrow House Core
 * Description: Demo-mode safeguards and content model for the Morrow House conceptual storefront.
 * Version: 1.1.0
 * Author: Kauê Natan Jungles
 * License: GPL-2.0-or-later
 * WC requires at least: 10.9
 * WC tested up to: 10.9.4
 */
declare(strict_types=1);

defined('ABSPATH') || exit;

require_once __DIR__ . '/includes/class-demo-mode.php';
require_once __DIR__ . '/includes/class-product-details.php';

if (defined('MORROW_HOUSE_IS_PLAYGROUND') && true === MORROW_HOUSE_IS_PLAYGROUND) {
    add_action('elementor/connect/apps/register', static function ($connect_module): void {
        require_once __DIR__ . '/includes/class-playground-elementor-library.php';

        $connect_module->register_app('library', Morrow_House_Playground_Elementor_Library::class);
    }, 20);
}

add_action('before_woocommerce_init', static function (): void {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
});

add_action('plugins_loaded', static function (): void {
    Morrow_House_Demo_Mode::boot();
    Morrow_House_Product_Details::boot();
});
