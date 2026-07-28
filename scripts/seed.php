<?php
declare(strict_types=1);

defined('ABSPATH') || require '/wordpress/wp-load.php';

if (!function_exists('wc_get_product_id_by_sku')) {
    throw new RuntimeException('WooCommerce must be active before seeding.');
}

const MORROW_HOUSE_SEED_VERSION = '1.0.0';

function mh_upsert_page(string $title, string $slug, string $content): int {
    $existing = get_page_by_path($slug, OBJECT, 'page');
    $post = [
        'post_title' => $title,
        'post_name' => $slug,
        'post_content' => $content,
        'post_status' => 'publish',
        'post_type' => 'page',
    ];

    if ($existing instanceof WP_Post) {
        $post['ID'] = $existing->ID;
    }

    $id = wp_insert_post(wp_slash($post), true);

    if (is_wp_error($id)) {
        throw new RuntimeException($id->get_error_message());
    }

    return (int) $id;
}

function mh_upsert_product(array $data): int {
    $id = wc_get_product_id_by_sku($data['sku']);
    $product = $id ? wc_get_product($id) : new WC_Product_Simple();

    if (!$product instanceof WC_Product) {
        throw new RuntimeException('Could not load product ' . $data['sku']);
    }

    $product->set_name($data['name']);
    $product->set_slug($data['slug']);
    $product->set_sku($data['sku']);
    $product->set_regular_price($data['price']);
    $product->set_price($data['price']);
    $product->set_status('publish');
    $product->set_catalog_visibility('visible');
    $product->set_description($data['description']);
    $product->set_short_description($data['short']);
    $product->set_manage_stock(false);
    $saved = $product->save();

    update_post_meta($saved, '_mh_material', $data['material']);
    update_post_meta($saved, '_mh_care', $data['care']);

    return $saved;
}

function morrow_house_seed(): void {
    global $wp_rewrite;

    $wp_rewrite->set_permalink_structure('/%postname%/');

    $home = mh_upsert_page('Morrow House', 'home', '<section class="mh-hero"><p class="eyebrow">Toronto · Objects for considered rooms</p><h1>Useful objects, quietly made.</h1><p>Small-batch lighting, vessels and trays selected for rooms that are lived in.</p><a class="button" href="/shop/">Shop the collection</a></section><section class="mh-featured"><h2>Current collection</h2>[products limit="3" columns="3" orderby="menu_order"]</section>');
    $about = mh_upsert_page('About', 'about', '<p class="eyebrow">Independent by design</p><h2>A small reference shop with a practical brief.</h2><p>Morrow House is a conceptual build used to demonstrate an editable WooCommerce storefront. It is not a real retailer.</p>');
    $contact = mh_upsert_page('Contact', 'contact', '<h1>Contact</h1><p>This demonstration does not collect customer enquiries. Use <a href="mailto:hello@morrowhouse.example">hello@morrowhouse.example</a> only as a non-deliverable example address.</p>');
    $campaign = mh_upsert_page('Objects for slower mornings', 'campaign', '');
    $shop = mh_upsert_page('Shop', 'shop', '');
    $cart = mh_upsert_page('Cart', 'cart', '<!-- wp:woocommerce/cart /-->');
    $checkout = mh_upsert_page('Checkout', 'checkout', '<!-- wp:paragraph {"className":"mh-checkout-disclosure"} --><p class="mh-checkout-disclosure">Checkout is shown for flow testing only. Payment methods are intentionally unavailable.</p><!-- /wp:paragraph --><!-- wp:woocommerce/checkout /-->');

    update_option('show_on_front', 'page');
    update_option('page_on_front', $home);
    update_option('woocommerce_shop_page_id', $shop);
    update_option('woocommerce_cart_page_id', $cart);
    update_option('woocommerce_checkout_page_id', $checkout);
    update_option('woocommerce_currency', 'CAD');
    update_option('woocommerce_enable_guest_checkout', 'yes');

    $elementor_data = [[
        'id' => 'mhcampaign01',
        'elType' => 'container',
        'isInner' => false,
        'settings' => [
            'content_width' => 'boxed',
            'background_background' => 'classic',
            'background_color' => '#F7F3EC',
        ],
        'elements' => [
            [
                'id' => 'mhheading01',
                'elType' => 'widget',
                'widgetType' => 'heading',
                'settings' => ['title' => 'Objects for slower mornings', 'header_size' => 'h1'],
                'elements' => [],
            ],
            [
                'id' => 'mhcopy0001',
                'elType' => 'widget',
                'widgetType' => 'text-editor',
                'settings' => ['editor' => '<p>Start with a clear surface and keep the objects you reach for close at hand. Soft light and a low tray make room for an unhurried start.</p>'],
                'elements' => [],
            ],
            [
                'id' => 'mhbutton01',
                'elType' => 'widget',
                'widgetType' => 'button',
                'settings' => ['text' => 'Shop the collection', 'link' => ['url' => '/shop/']],
                'elements' => [],
            ],
        ],
    ]];

    update_post_meta($campaign, '_elementor_edit_mode', 'builder');
    update_post_meta($campaign, '_elementor_template_type', 'wp-page');
    update_post_meta($campaign, '_elementor_version', defined('ELEMENTOR_VERSION') ? ELEMENTOR_VERSION : '3.0.0');
    update_post_meta($campaign, '_elementor_data', wp_slash(wp_json_encode($elementor_data)));

    foreach ([
        [
            'name' => 'Vale Table Lamp',
            'slug' => 'vale-table-lamp',
            'sku' => 'MH-LAMP-VALE',
            'price' => '168',
            'description' => 'A compact table lamp with a warm, indirect presence.',
            'short' => 'Soft light for shelves and bedside tables.',
            'material' => 'Powder-coated steel and linen',
            'care' => 'Dust with a dry, soft cloth.',
        ],
        [
            'name' => 'Field Tray',
            'slug' => 'field-tray',
            'sku' => 'MH-TRAY-FIELD',
            'price' => '74',
            'description' => 'A low tray for keys, correspondence and the small objects that gather near a door.',
            'short' => 'A quiet landing place for daily objects.',
            'material' => 'Solid ash with plant-based oil',
            'care' => 'Wipe clean; avoid standing water.',
        ],
        [
            'name' => 'Low Vessel',
            'slug' => 'low-vessel',
            'sku' => 'MH-VESSEL-LOW',
            'price' => '92',
            'description' => 'A hand-finished vessel designed for open shelving and short stems.',
            'short' => 'Low profile, matte surface, useful proportions.',
            'material' => 'Stoneware ceramic',
            'care' => 'Hand wash and dry completely.',
        ],
    ] as $product) {
        mh_upsert_product($product);
    }

    $menu_name = 'Primary';
    $menu = wp_get_nav_menu_object($menu_name);
    if ($menu instanceof WP_Term) {
        $menu_id = (int) $menu->term_id;
    } else {
        $created_menu = wp_create_nav_menu($menu_name);
        if (is_wp_error($created_menu)) {
            throw new RuntimeException(sprintf(
                'Could not create navigation menu "%s": %s',
                $menu_name,
                $created_menu->get_error_message(),
            ));
        }
        $menu_id = (int) $created_menu;
    }
    $existing_items = wp_get_nav_menu_items($menu_id) ?: [];
    $existing_objects = array_map(static fn ($item): int => (int) $item->object_id, $existing_items);

    foreach ([$shop, $campaign, $about, $contact] as $page_id) {
        if (!in_array($page_id, $existing_objects, true)) {
            $created_item = wp_update_nav_menu_item($menu_id, 0, [
                'menu-item-object-id' => $page_id,
                'menu-item-object' => 'page',
                'menu-item-type' => 'post_type',
                'menu-item-status' => 'publish',
            ]);
            if (is_wp_error($created_item)) {
                throw new RuntimeException(sprintf(
                    'Could not add page "%s" (ID %d) to primary navigation: %s',
                    get_the_title($page_id),
                    $page_id,
                    $created_item->get_error_message(),
                ));
            }
        }
    }

    $locations = get_theme_mod('nav_menu_locations', []);
    $locations['primary'] = $menu_id;
    set_theme_mod('nav_menu_locations', $locations);

    if (get_option('morrow_house_seed_version') !== MORROW_HOUSE_SEED_VERSION) {
        flush_rewrite_rules();
        update_option('morrow_house_seed_version', MORROW_HOUSE_SEED_VERSION);
    }
}

morrow_house_seed();
