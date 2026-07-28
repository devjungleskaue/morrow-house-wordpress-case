<!doctype html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#content"><?php esc_html_e('Skip to content', 'morrow-house'); ?></a>
<header class="site-header">
  <a class="site-brand" href="<?php echo esc_url(home_url('/')); ?>" aria-label="<?php esc_attr_e('Morrow House home', 'morrow-house'); ?>">Morrow House</a>
  <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="primary-menu"><?php esc_html_e('Menu', 'morrow-house'); ?></button>
  <nav id="primary-menu" aria-label="<?php esc_attr_e('Primary', 'morrow-house'); ?>"><?php wp_nav_menu(['theme_location' => 'primary', 'container' => false, 'fallback_cb' => false]); ?></nav>
  <?php morrow_house_cart_link(); ?>
</header>
<main id="content">
