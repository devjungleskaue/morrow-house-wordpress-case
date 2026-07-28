<?php
defined('ABSPATH') || exit(1);

function mh_smoke_assert(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException('Runtime smoke failed: ' . $message);
    }
}

function mh_smoke_seed_state(): array {
    $page_slugs = ['home', 'about', 'contact', 'campaign', 'shop', 'cart', 'checkout'];
    $pages = [];

    foreach ($page_slugs as $slug) {
        $page = get_page_by_path($slug, OBJECT, 'page');
        $pages[$slug] = $page instanceof WP_Post ? (int) $page->ID : 0;
    }

    $product_skus = ['MH-LAMP-VALE', 'MH-TRAY-FIELD', 'MH-VESSEL-LOW'];
    $products = [];

    foreach ($product_skus as $sku) {
        $products[$sku] = (int) wc_get_product_id_by_sku($sku);
    }

    $menu = wp_get_nav_menu_object('Primary');
    $menu_id = $menu instanceof WP_Term ? (int) $menu->term_id : 0;
    $menu_items = $menu_id > 0 ? (wp_get_nav_menu_items($menu_id) ?: []) : [];
    $menu_object_ids = array_map(static fn (WP_Post $item): int => (int) $item->object_id, $menu_items);
    sort($menu_object_ids);

    return [
        'pages' => $pages,
        'products' => $products,
        'menu_id' => $menu_id,
        'menu_object_ids' => $menu_object_ids,
        'primary_location' => (int) (get_nav_menu_locations()['primary'] ?? 0),
        'permalink_structure' => (string) get_option('permalink_structure', ''),
        'seed_version' => (string) get_option('morrow_house_seed_version', ''),
    ];
}

function mh_smoke_count_elementor_h1(array $elements): int {
    $count = 0;

    foreach ($elements as $element) {
        if (
            ($element['widgetType'] ?? '') === 'heading'
            && strtolower((string) ($element['settings']['header_size'] ?? '')) === 'h1'
        ) {
            ++$count;
        }

        if (isset($element['elements']) && is_array($element['elements'])) {
            $count += mh_smoke_count_elementor_h1($element['elements']);
        }
    }

    return $count;
}

mh_smoke_assert(get_bloginfo('version') === '7.0.2', 'WordPress must be 7.0.2.');
mh_smoke_assert(defined('WC_VERSION') && WC_VERSION === '10.9.4', 'WooCommerce must be 10.9.4.');
mh_smoke_assert(defined('ELEMENTOR_VERSION') && ELEMENTOR_VERSION === '4.2.1', 'Elementor must be 4.2.1.');

delete_option('morrow_house_seed_version');
$rewrite_flushes = 0;
add_filter('flush_rewrite_rules_hard', static function (bool $hard) use (&$rewrite_flushes): bool {
    ++$rewrite_flushes;
    return $hard;
});

require '/project/scripts/seed.php';
$first_state = mh_smoke_seed_state();
$first_seed_flushes = $rewrite_flushes;
morrow_house_seed();
$second_state = mh_smoke_seed_state();

mh_smoke_assert($first_state === $second_state, 'seeding twice must preserve page, product, menu, and location state.');
mh_smoke_assert($first_seed_flushes >= 1, 'the first versioned seed must flush rewrite rules.');
mh_smoke_assert($rewrite_flushes === $first_seed_flushes, 'a repeated seed at the same version must not flush rewrite rules again.');
mh_smoke_assert(!in_array(0, $second_state['pages'], true), 'all seven seeded pages must exist.');
mh_smoke_assert(count(array_unique($second_state['pages'])) === 7, 'seeded page slugs must identify seven distinct pages.');
mh_smoke_assert(!in_array(0, $second_state['products'], true), 'all three seeded SKUs must exist.');
mh_smoke_assert(count(array_unique($second_state['products'])) === 3, 'seeded SKUs must identify three distinct products.');

$published_products = get_posts([
    'post_type' => 'product',
    'post_status' => 'publish',
    'numberposts' => -1,
    'fields' => 'ids',
]);
mh_smoke_assert(count($published_products) === 3, 'the catalogue must contain exactly three published products.');

$expected_menu_items = [
    $second_state['pages']['shop'],
    $second_state['pages']['campaign'],
    $second_state['pages']['about'],
    $second_state['pages']['contact'],
];
sort($expected_menu_items);
mh_smoke_assert($second_state['menu_id'] > 0, 'Primary menu must exist.');
mh_smoke_assert($second_state['menu_object_ids'] === $expected_menu_items, 'Primary menu must contain each expected page exactly once.');
mh_smoke_assert($second_state['primary_location'] === $second_state['menu_id'], 'Primary menu location must point to the completed menu.');
mh_smoke_assert($second_state['permalink_structure'] === '/%postname%/', 'pretty project routes must use the post-name permalink structure.');
mh_smoke_assert($second_state['seed_version'] === MORROW_HOUSE_SEED_VERSION, 'seed version must be recorded after success.');

$campaign_id = $second_state['pages']['campaign'];
mh_smoke_assert(get_post_meta($campaign_id, '_elementor_edit_mode', true) === 'builder', 'Campaign must remain owned by Elementor.');
mh_smoke_assert(get_post_meta($campaign_id, '_elementor_template_type', true) === 'wp-page', 'Campaign must use the Elementor page template type.');
$elementor_data = json_decode((string) get_post_meta($campaign_id, '_elementor_data', true), true);
mh_smoke_assert(is_array($elementor_data), 'Campaign Elementor metadata must be valid JSON.');
mh_smoke_assert(mh_smoke_count_elementor_h1($elementor_data) === 1, 'Campaign Elementor data must contain exactly one h1.');

if (null === WC()->session) {
    WC()->initialize_session();
}
if (null === WC()->customer) {
    WC()->initialize_customer();
}
if (null === WC()->cart) {
    WC()->initialize_cart();
}
WC()->cart->empty_cart();
mh_smoke_assert(false !== WC()->cart->add_to_cart($second_state['products']['MH-LAMP-VALE']), 'smoke cart must accept the sample product.');
$cart_fragments = apply_filters('woocommerce_add_to_cart_fragments', []);
mh_smoke_assert(isset($cart_fragments['a.cart-link']), 'the authoritative cart-link fragment must be registered.');
mh_smoke_assert(
    1 === preg_match('~<span aria-live="polite">\s*1\s*</span>~', $cart_fragments['a.cart-link']),
    'the cart-link fragment must contain the live server-side count.'
);
$gateways = WC()->payment_gateways()->get_available_payment_gateways();
mh_smoke_assert($gateways === [], 'available payment gateways must remain empty with a non-zero cart.');

echo "Runtime database smoke passed.\n";
