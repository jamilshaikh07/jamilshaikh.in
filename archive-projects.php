<?php get_header(); ?>

<div class="container mx-auto my-8">
    <h2 class="text-3xl font-bold text-center mt-12">My Projects</h2>

    <div class="py-6 sm:py-12">
        <div class="mx-auto max-w-7xl px-6 lg:px-8">
            <div class="mx-auto mt-16 grid max-w-2xl grid-cols-1 gap-x-8 gap-y-20 lg:mx-0 lg:max-w-none lg:grid-cols-3">


                <?php if ( have_posts() ) : ?>
                <?php
                  while ( have_posts() ) :
                    the_post();
                    ?>


                <?php get_template_part( 'template-parts/content-project', get_post_format() ); ?>




                <?php endwhile; ?>

                <?php endif; ?>

            </div>
        </div>
    </div>


</div>

<?php
get_footer();