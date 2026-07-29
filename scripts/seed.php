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

/**
 * The Cart and Checkout pages WooCommerce created on install, left alone.
 *
 * This used to write '<!-- wp:woocommerce/cart /-->' over them. That is the
 * void form of the block, and the block renders its inner content, so a void
 * one renders nothing: both pages came up carrying only their heading. The
 * real page content is a nested template WooCommerce builds during install
 * (filled-cart-block, cart-items-block, and a dozen more). Rebuilding that by
 * hand means owning a copy of WooCommerce's internals that goes stale on the
 * next release, so the seed asks WooCommerce where its pages are instead.
 */
function mh_woo_page(string $chave, string $titulo): int {
    $id = wc_get_page_id($chave);

    if ($id > 0 && get_post_status($id) === 'publish') {
        return $id;
    }

    throw new RuntimeException(sprintf(
        'WooCommerce has no published %s page. Activate WooCommerce so it can create its pages before seeding.',
        $titulo,
    ));
}

/** Puts a block at the top of a page once. Running the seed again is a no-op. */
function mh_prepend_block(int $page_id, string $bloco, string $marcador): int {
    $atual = (string) get_post_field('post_content', $page_id);

    if (str_contains($atual, $marcador)) {
        return $page_id;
    }

    $resultado = wp_update_post(['ID' => $page_id, 'post_content' => wp_slash($bloco . $atual)], true);

    if (is_wp_error($resultado)) {
        throw new RuntimeException($resultado->get_error_message());
    }

    return $page_id;
}

/**
 * Puts a bundled product photo in the media library and on the product.
 *
 * The files live in scripts/assets so both environments get them: Docker
 * mounts the directory and the Playground blueprint writes the same path out of
 * the repository. Idempotent through _mh_image_source, so a second seed run
 * reuses the attachment instead of uploading a duplicate.
 */
function mh_attach_product_image(int $product_id, string $arquivo, string $alt): void {
    $existing = get_posts([
        'post_type' => 'attachment',
        'post_status' => 'inherit',
        'numberposts' => 1,
        'fields' => 'ids',
        'meta_key' => '_mh_image_source',
        'meta_value' => $arquivo,
    ]);

    if ($existing) {
        set_post_thumbnail($product_id, (int) $existing[0]);

        return;
    }

    $caminho = __DIR__ . '/assets/' . $arquivo;

    if (!is_readable($caminho)) {
        throw new RuntimeException('Missing product image: ' . $caminho);
    }

    $enviado = wp_upload_bits($arquivo, null, (string) file_get_contents($caminho));

    if (!empty($enviado['error'])) {
        throw new RuntimeException('Could not store ' . $arquivo . ': ' . $enviado['error']);
    }

    $attachment_id = wp_insert_attachment([
        'post_mime_type' => 'image/jpeg',
        'post_title' => pathinfo($arquivo, PATHINFO_FILENAME),
        'post_status' => 'inherit',
    ], $enviado['file'], $product_id, true);

    if (is_wp_error($attachment_id)) {
        throw new RuntimeException($attachment_id->get_error_message());
    }

    require_once ABSPATH . 'wp-admin/includes/image.php';
    wp_update_attachment_metadata(
        $attachment_id,
        wp_generate_attachment_metadata($attachment_id, $enviado['file']),
    );

    // Alt text is the reason this helper exists rather than a bare copy: a
    // storefront that ships images without it fails the accessibility contract
    // in scripts/smoke-test.sh, which is the point of having that contract.
    update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt);
    update_post_meta($attachment_id, '_mh_image_source', $arquivo);
    set_post_thumbnail($product_id, $attachment_id);
}

/**
 * Product category, created on demand and returned as a term id.
 *
 * Without this every product sat in "Uncategorized", which showed on the
 * product page and left the catalogue with no archives to browse. Three
 * products do not need taxonomy to function; a storefront that demonstrates
 * WooCommerce does.
 */
function mh_product_category(string $nome, string $slug): int {
    $existing = get_term_by('slug', $slug, 'product_cat');

    if ($existing instanceof WP_Term) {
        return (int) $existing->term_id;
    }

    $criado = wp_insert_term($nome, 'product_cat', ['slug' => $slug]);

    if (is_wp_error($criado)) {
        throw new RuntimeException('Could not create category ' . $slug . ': ' . $criado->get_error_message());
    }

    return (int) $criado['term_id'];
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
    $product->set_category_ids([mh_product_category($data['category'], $data['category_slug'])]);
    $saved = $product->save();

    update_post_meta($saved, '_mh_material', $data['material']);
    update_post_meta($saved, '_mh_care', $data['care']);
    mh_attach_product_image($saved, $data['image'], $data['alt']);

    return $saved;
}

function morrow_house_seed(): void {
    global $wp_rewrite;

    $wp_rewrite->set_permalink_structure('/%postname%/');

    $home = mh_upsert_page('Morrow House', 'home', '<section class="mh-hero"><p class="eyebrow">Toronto · Objects for considered rooms</p><h1>Useful objects, quietly made.</h1><p>Small-batch vases, vessels and trays selected for rooms that are lived in.</p><a class="button" href="/shop/">Shop the collection</a></section><section class="mh-featured"><h2>Current collection</h2>[products limit="3" columns="3" orderby="menu_order"]</section>');
    $about = mh_upsert_page('About', 'about', '<p class="eyebrow">Independent by design</p><h2>A small reference shop with a practical brief.</h2><p>Morrow House is a conceptual build used to demonstrate an editable WooCommerce storefront. It is not a real retailer.</p>');
    // No <h1> here: page.php already renders one from the title, and repeating
    // it gave this page two.
    $contact = mh_upsert_page('Contact', 'contact', '<p>This demonstration does not collect customer enquiries. Use <a href="mailto:hello@morrowhouse.example">hello@morrowhouse.example</a> only as a non-deliverable example address.</p>');
    $campaign = mh_upsert_page('Objects for slower mornings', 'campaign', '');
    $shop = mh_upsert_page('Shop', 'shop', '');
    $cart = mh_woo_page('cart', 'Cart');
    $checkout = mh_prepend_block(
        mh_woo_page('checkout', 'Checkout'),
        '<!-- wp:paragraph {"className":"mh-checkout-disclosure"} --><p class="mh-checkout-disclosure">Checkout is shown for flow testing only. Payment methods are intentionally unavailable.</p><!-- /wp:paragraph -->',
        'mh-checkout-disclosure',
    );

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
            'name' => 'Vale Fluted Vase',
            'slug' => 'vale-fluted-vase',
            'sku' => 'MH-VASE-VALE',
            'price' => '168',
            'description' => 'A tall vase whose flutes close into a bud at the lip and open again at the foot.',
            'short' => 'Sculptural enough to stand empty.',
            'material' => 'Glazed white earthenware',
            'care' => 'Wipe with a damp cloth; not for the dishwasher.',
            'category' => 'Vases', 'category_slug' => 'vases',
            'image' => 'vale-fluted-vase.jpg',
            'alt' => 'Tall cream vase with vertical flutes that gather to a closed bud at the rim and flare into leaf shapes at the base.',
        ],
        [
            'name' => 'Field Tray',
            'slug' => 'field-tray',
            'sku' => 'MH-TRAY-FIELD',
            'price' => '74',
            'description' => 'A low tray for keys, correspondence and the small objects that gather near a door.',
            'short' => 'A quiet landing place for daily objects.',
            'material' => 'Coiled paper under lacquer',
            'care' => 'Wipe clean; avoid standing water.',
            'category' => 'Trays', 'category_slug' => 'trays',
            'image' => 'field-tray.jpg',
            'alt' => 'Shallow rectangular tray on four short feet, its ribbed grey-green surface catching the light along each coil.',
        ],
        [
            'name' => 'Low Vessel',
            'slug' => 'low-vessel',
            'sku' => 'MH-VESSEL-LOW',
            'price' => '92',
            'description' => 'A wide vessel that sits lower than it is round, for open shelving and short stems.',
            'short' => 'Low profile, matte surface, useful proportions.',
            'material' => 'Stoneware with an ash glaze',
            'care' => 'Hand wash and dry completely.',
            'category' => 'Vessels', 'category_slug' => 'vessels',
            'image' => 'low-vessel.jpg',
            'alt' => 'Round grey stoneware jar, wider than it is tall, with a short collar and a softly mottled ash-glazed surface.',
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
