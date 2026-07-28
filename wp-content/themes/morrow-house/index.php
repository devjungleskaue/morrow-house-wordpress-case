<?php
get_header();
if (have_posts()) {
    while (have_posts()) {
        the_post();
        ?>
        <article class="page">
          <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
          <?php the_excerpt(); ?>
        </article>
        <?php
    }
} else {
    ?>
    <p class="page"><?php esc_html_e('Nothing found.', 'morrow-house'); ?></p>
    <?php
}
get_footer();
