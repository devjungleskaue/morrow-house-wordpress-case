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

/**
 * Cache-buster for the theme's own assets.
 *
 * Released builds are versioned by the theme version, which is what a visitor's
 * browser should key on. That is a trap while developing: edit store.css, reload,
 * and the browser serves the copy it already has, because the query string did
 * not move. It cost an afternoon once, with new product photography rendering
 * invisible behind a stale stylesheet. With WP_DEBUG on, the file's own mtime is
 * used instead, so a save is always a new URL.
 */
function morrow_house_asset_version(string $relative): string {
    $version = (string) wp_get_theme()->get('Version');

    if (!defined('WP_DEBUG') || !WP_DEBUG) {
        return $version;
    }

    $path = get_template_directory() . $relative;

    return is_readable($path) ? $version . '.' . filemtime($path) : $version;
}

add_action('wp_enqueue_scripts', static function (): void {
    $css = '/assets/css/store.css';
    $js = '/assets/js/store.js';
    wp_enqueue_style('morrow-house-store', get_template_directory_uri() . $css, [], morrow_house_asset_version($css));
    wp_enqueue_script('morrow-house-store', get_template_directory_uri() . $js, ['wc-cart-fragments'], morrow_house_asset_version($js), true);
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

/**
 * Product search above the catalogue.
 *
 * On the hook rather than in woocommerce.php: called from the template it
 * rendered before woocommerce_content(), which puts it above the page's h1.
 * woocommerce_before_shop_loop fires after the heading, and priority 5 places
 * it ahead of the result count and the sorting dropdown.
 *
 * The closure is not decoration either. Hooking get_search_form directly makes
 * the form vanish: do_action passes an empty string as the first argument, and
 * get_search_form still honours its pre-5.2 signature where the first argument
 * was $echo, so an empty string means "return it, do not print it".
 */
add_action('woocommerce_before_shop_loop', static function (): void {
    get_search_form();
}, 5);
