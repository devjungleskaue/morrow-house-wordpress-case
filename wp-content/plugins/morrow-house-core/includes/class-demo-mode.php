<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

final class Morrow_House_Demo_Mode {
    public static function boot(): void {
        add_filter('woocommerce_available_payment_gateways', '__return_empty_array', 99);
        add_action('wp_body_open', [self::class, 'notice']);
    }

    public static function notice(): void {
        echo '<p class="demo-notice">' . esc_html__('Concept build — no real orders or payments are processed.', 'morrow-house') . '</p>';
    }

}
