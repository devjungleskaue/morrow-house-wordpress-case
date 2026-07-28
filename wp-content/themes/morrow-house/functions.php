<?php
declare(strict_types=1);

add_action('after_setup_theme', static function (): void {
    load_theme_textdomain('morrow-house', get_template_directory() . '/languages');
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', ['search-form', 'gallery', 'caption', 'style', 'script']);
    add_theme_support('woocommerce');
    add_theme_support('wc-product-gallery-zoom');
    register_nav_menus(['primary' => __('Primary navigation', 'morrow-house')]);
});

add_action('wp_enqueue_scripts', static function (): void {
    $version = wp_get_theme()->get('Version');
    wp_enqueue_style('morrow-house-store', get_template_directory_uri() . '/assets/css/store.css', [], $version);
    wp_enqueue_script('morrow-house-store', get_template_directory_uri() . '/assets/js/store.js', ['wc-cart-fragments'], $version, true);
});

function morrow_house_cart_link(): void {
    ?>
    <a class="cart-link" href="<?php echo esc_url(wc_get_cart_url()); ?>"><?php esc_html_e('Cart', 'morrow-house'); ?> <span aria-live="polite"><?php echo esc_html((string) WC()->cart?->get_cart_contents_count()); ?></span></a>
    <?php
}

add_filter('woocommerce_add_to_cart_fragments', static function (array $fragments): array {
    ob_start();
    morrow_house_cart_link();
    $fragments['a.cart-link'] = (string) ob_get_clean();

    return $fragments;
});

add_action('elementor/theme/register_locations', static function ($manager): void {
    $manager->register_all_core_location();
});

add_filter('woocommerce_enqueue_styles', '__return_empty_array');
