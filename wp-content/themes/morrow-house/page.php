<?php
get_header();
while (have_posts()) {
    the_post();
    $elementor_edit_mode = get_post_meta(get_the_ID(), '_elementor_edit_mode', true);
    ?>
    <article class="page">
      <?php if ('builder' !== $elementor_edit_mode) { ?>
        <header><h1><?php the_title(); ?></h1></header>
      <?php } ?>
      <?php the_content(); ?>
    </article>
    <?php
}
get_footer();
