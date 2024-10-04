<?php get_header(); ?>

<div class="container mx-auto my-24">

    <?php if ( have_posts() ) : ?>
    <?php
		while ( have_posts() ) :
			the_post();
			?>

    <?php get_template_part( 'template-parts/content-front', get_post_format() ); ?>

    <?php endwhile; ?>

    <?php endif; ?>

</div>

<?php
get_footer();