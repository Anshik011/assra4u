<?php
/**
 * UNIVERSAL SINGLE LOADER
 * Connects WP Data -> Your Static HTML Design
 */

// 1. Identify Content Type (e.g., 'program', 'event', 'post')
$post_type = get_post_type(); 
$theme_dir = get_stylesheet_directory();

// 2. Define Template Path
// Looks for: static/pages/single-program.html, single-event.html, etc.
$template_path = $theme_dir . "/static/pages/single-{$post_type}.html";

// Fallback: If specific file doesn't exist, look for generic 'single.html'
if (!file_exists($template_path)) {
    $template_path = $theme_dir . "/static/pages/single.html";
}

// 3. Start Page Generation
if (have_posts()) : while (have_posts()) : the_post();

    // --- A. LOAD HEADER ---
    ob_start();
    include $theme_dir . '/static/partials/_header.html';
    $header = ob_get_clean();
    
    // Inject WP Head logic
    if (strpos($header, '</head>') !== false) {
        echo str_replace('</head>', wp_head() . '</head>', $header);
    } else {
        echo $header; wp_head();
    }

    // --- B. LOAD HTML TEMPLATE ---
    if (file_exists($template_path)) {
        $html = file_get_contents($template_path);

        // Standard Data Injection
        $html = str_replace('{title}', get_the_title(), $html);
        $html = str_replace('{content}', apply_filters('the_content', get_the_content()), $html);
        $html = str_replace('{date}', get_the_date(), $html);
        $html = str_replace('{author}', get_the_author(), $html);
        
        // Image Logic
        $img_url = has_post_thumbnail() ? get_the_post_thumbnail_url(get_the_ID(), 'full') : '';
        $html = str_replace('{image}', $img_url, $html);

        // Custom Fields Logic (Safe for all types)
        $raised = get_post_meta(get_the_ID(), 'raised_amount', true) ?: '0';
        $goal   = get_post_meta(get_the_ID(), 'goal_amount', true) ?: '0';
        $percent = ($goal > 0) ? round(($raised / $goal) * 100) . '%' : '0%';
        
        $html = str_replace('{raised}', $raised, $html);
        $html = str_replace('{goal}', $goal, $html);
        $html = str_replace('{percent}', $percent, $html);

        // Asset Path Fixes (CSS/JS/Images)
        $theme_uri = get_stylesheet_directory_uri();
        $html = preg_replace('/(href|src)=["\']\s*(\.{0,2}\/?)css\//', '$1="' . $theme_uri . '/assets/css/', $html);
        $html = preg_replace('/(href|src)=["\']\s*(\.{0,2}\/?)js\//', '$1="' . $theme_uri . '/assets/js/', $html);
        $html = preg_replace('/(href|src|srcset|data-src)=["\']\s*(\.{0,2}\/?)images\//', '$1="' . $theme_uri . '/assets/images/', $html);
        $html = preg_replace('/(href|src)=["\']\s*(\.{0,2}\/?)assets\//', '$1="' . $theme_uri . '/assets/', $html);
        
        // Output the Final HTML
        echo do_shortcode($html);

    } else {
        // Dev Error Message if file is missing
        echo '<div style="padding:100px; text-align:center;">';
        echo '<h2>Template Missing</h2>';
        echo '<p>Create file: <code>static/pages/single-' . $post_type . '.html</code></p>';
        echo '</div>';
    }

    // --- C. LOAD FOOTER ---
    ob_start();
    include $theme_dir . '/static/partials/_footer.html';
    $footer = ob_get_clean();

    // Inject WP Footer logic
    if (strpos($footer, '</body>') !== false) {
        echo str_replace('</body>', wp_footer() . '</body>', $footer);
    } else {
        wp_footer(); echo $footer;
    }

endwhile; endif;
?>