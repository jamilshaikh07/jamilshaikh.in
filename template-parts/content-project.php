<article id="post-<?php the_ID(); ?>" class="flex flex-col items-start justify-between">
    <a href="<?php the_permalink(); ?>">
        <div class="relative w-full">
            <img src="<?php the_post_thumbnail_url() ?>" alt=""
                class="aspect-[16/9] w-full rounded-2xl bg-gray-100 object-cover sm:aspect-[2/1] lg:aspect-[3/2]">
            <div class="absolute inset-0 rounded-2xl ring-1 ring-inset ring-gray-900/10"></div>
        </div>
    </a>
    <div class="max-w-xl">
        <div class="mt-8 flex items-center gap-x-4 text-xs">
            <time datetime="2020-03-16" class="text-gray-200"><?php the_date() ?></time>
        </div>
        <div class="group relative">
            <h3 class="mt-5 text-lg font-semibold leading-6 text-gray-100 group-hover:text-gray-300">
                <a href="<?php the_permalink(); ?>">
                    <span class="absolute inset-0"></span>
                    <?php the_title() ?>
                </a>
            </h3>
            <p class="mt-5 line-clamp-3 text-sm leading-6 text-gray-600"><?php the_excerpt() ?></p>
        </div>
    </div>
</article>