<?php
defined('ABSPATH') || exit(1);

function mh_menu_failure_assert(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException('Menu failure smoke failed: ' . $message);
    }
}

$menu = wp_get_nav_menu_object('Primary');
if ($menu instanceof WP_Term) {
    $deleted = wp_delete_nav_menu($menu->term_id);
    mh_menu_failure_assert(!is_wp_error($deleted) && $deleted, 'test setup could not remove the existing menu.');
}

$locations = get_theme_mod('nav_menu_locations', []);
unset($locations['primary']);
set_theme_mod('nav_menu_locations', $locations);

add_filter('pre_insert_term', static function ($term, string $taxonomy) {
    if ('nav_menu' === $taxonomy && 'Primary' === $term) {
        return new WP_Error('mh_forced_menu_failure', 'forced menu creation failure');
    }

    return $term;
}, 10, 2);

$caught = null;
try {
    require '/project/scripts/seed.php';
} catch (RuntimeException $error) {
    $caught = $error;
}

mh_menu_failure_assert($caught instanceof RuntimeException, 'seed must throw when WordPress rejects menu creation.');
mh_menu_failure_assert(
    str_contains($caught->getMessage(), 'Could not create navigation menu "Primary": forced menu creation failure'),
    'seed exception must retain the operation, menu name, and WordPress error.',
);
mh_menu_failure_assert(!isset(get_nav_menu_locations()['primary']), 'failed menu creation must not assign the primary location.');

echo "Runtime menu failure smoke passed.\n";
