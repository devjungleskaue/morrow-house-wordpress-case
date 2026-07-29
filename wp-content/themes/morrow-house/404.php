<?php
/**
 * Without this file WordPress fell through to index.php, which rendered a bare
 * "Nothing found." with no heading and no way back to the shop. The status code
 * was already correct; the page just did not help anyone who landed on it.
 */
get_header();
?>
<article class="page">
  <header>
    <p class="eyebrow"><?php esc_html_e('404', 'morrow-house'); ?></p>
    <h1><?php esc_html_e('That page is not here.', 'morrow-house'); ?></h1>
  </header>
  <p><?php esc_html_e('The link may be old, or the page may have moved. The collection is the best place to pick things up again.', 'morrow-house'); ?></p>
  <p>
    <a class="button" href="<?php echo esc_url(get_permalink(wc_get_page_id('shop'))); ?>">
      <?php esc_html_e('Browse the collection', 'morrow-house'); ?>
    </a>
  </p>
  <?php get_search_form(); ?>
</article>
<?php
get_footer();
