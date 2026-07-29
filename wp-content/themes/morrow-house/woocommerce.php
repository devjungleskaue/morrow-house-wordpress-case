<?php
/**
 * This template calls woocommerce_content() directly rather than firing
 * woocommerce_before_main_content, so anything WooCommerce hooks to that action
 * has to be called here. The breadcrumb is one of those: without it a product
 * page offered no route back to the catalogue except the menu.
 */
get_header();
?>
<div class="shop-shell">
  <?php
  woocommerce_breadcrumb();
  woocommerce_content();
  ?>
</div>
<?php get_footer();
