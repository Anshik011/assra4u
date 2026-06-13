<?php
/*
=====================================================
UPTOWARE ROUTER (index.php)
=====================================================
*/

global $post;

// 1. Determine Current Slug
if (is_front_page() || is_home()) {
    $slug = 'home';
} elseif (is_page()) {
    $slug = $post->post_name; // e.g., 'about-us'
} elseif (is_singular()) {
    // If it's a Service or Project, looks for 'service-detail.html' or similar
    // You can customize this logic
    $slug = $post->post_type . '-detail'; 
} elseif (is_404()) {
    $slug = '404';
} else {
    $slug = 'index';
}

// 2. Try to load Static HTML
$loaded = utw_load_static_page($slug);

// 3. Fallback: If no HTML file found, show standard WP Content
if (!$loaded) :
    get_header(); 
    ?>
    
    <div class="container py-5">
        <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
            <h1><?php the_title(); ?></h1>
            <div class="content">
                <?php the_content(); ?>
            </div>
        <?php endwhile; endif; ?>
    </div>

    <?php 
    get_footer(); 
endif; 
?>