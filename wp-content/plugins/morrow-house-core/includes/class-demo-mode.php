<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * Keeps this build from ever taking money.
 *
 * Demo mode is ON unless a deployment explicitly turns it off, and that
 * direction matters: a conceptual storefront that quietly starts accepting
 * checkouts is a worse failure than one that refuses a sale it should have
 * made. Opting out takes a deliberate line in wp-config.php.
 *
 *     define('MORROW_HOUSE_DEMO_MODE', false);
 */
final class Morrow_House_Demo_Mode {
    public static function is_enabled(): bool {
        if (defined('MORROW_HOUSE_DEMO_MODE')) {
            return (bool) constant('MORROW_HOUSE_DEMO_MODE');
        }

        return true;
    }

    public static function boot(): void {
        if (!self::is_enabled()) {
            return;
        }

        add_filter('woocommerce_available_payment_gateways', '__return_empty_array', 99);
        add_action('wp_body_open', [self::class, 'notice']);
    }

    public static function notice(): void {
        echo '<p class="demo-notice">' . esc_html__('Concept build — no real orders or payments are processed.', 'morrow-house-core') . '</p>';
    }
}
