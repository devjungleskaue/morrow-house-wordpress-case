<?php
/**
 * Plugin Name: Morrow House Core
 * Description: Demo-mode safeguards and content model for the Morrow House conceptual storefront.
 * Version: 1.1.0
 * Author: Kauê Natan Jungles
 * License: GPL-2.0-or-later
 * Text Domain: morrow-house-core
 * Domain Path: /languages
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

/**
 * The plugin owns its text domain rather than borrowing the theme's.
 *
 * It used to pass 'morrow-house' to every translation call, which happens to be
 * the theme's domain. That works only while this exact theme is active: swap the
 * theme and the plugin's strings fall back to English with nothing to show for
 * it. A plugin is a separate extension and loads its own catalogue.
 */
add_action('init', static function (): void {
    load_plugin_textdomain('morrow-house-core', false, dirname(plugin_basename(__FILE__)) . '/languages');
});

add_action('plugins_loaded', static function (): void {
    Morrow_House_Demo_Mode::boot();
    Morrow_House_Product_Details::boot();
});
