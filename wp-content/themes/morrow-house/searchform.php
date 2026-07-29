<?php
/**
 * Search restricted to products, because a shop of three objects has nothing
 * else worth finding. The stylesheet already carried rules for
 * form.woocommerce-product-search and nothing rendered that class, so the
 * styling was waiting for a component that did not exist.
 *
 * The label is visible rather than a placeholder: a placeholder disappears as
 * soon as someone types, which is exactly when they might want to check what
 * the field was for.
 */
$mh_search_id = wp_unique_id('mh-search-');
?>
<form role="search" method="get" class="woocommerce-product-search" action="<?php echo esc_url(home_url('/')); ?>">
  <label for="<?php echo esc_attr($mh_search_id); ?>" class="search-label">
    <?php esc_html_e('Search the collection', 'morrow-house'); ?>
  </label>
  <div class="search-row">
    <input
      id="<?php echo esc_attr($mh_search_id); ?>"
      type="search"
      name="s"
      value="<?php echo esc_attr(get_search_query()); ?>"
      autocomplete="off"
    >
    <button type="submit"><?php esc_html_e('Search', 'morrow-house'); ?></button>
  </div>
  <input type="hidden" name="post_type" value="product">
</form>
