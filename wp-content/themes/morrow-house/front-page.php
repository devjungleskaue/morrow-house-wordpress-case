<?php
/**
 * The container here is not decoration. Without it the_content() rendered
 * straight into <main>, so the home page ran edge to edge while the header and
 * footer kept their own padding, and the headline sat 38px left of the brand
 * above it. That went unnoticed because a bare `.page` in the stylesheet was
 * matching the `page` class WordPress puts on <body> and constraining the whole
 * document by accident. Fixing the selector exposed the missing wrapper.
 */
get_header();
while (have_posts()) {
    the_post();
    ?>
    <article class="page"><?php the_content(); ?></article>
    <?php
}
get_footer();
