<?php
/**
 * UNIVERSAL ARCHIVE LOADER
 * Handles Category Pages, Search Results, and Archives
 */

// 1. Identify Post Type (e.g., 'program', 'event')
$post_type = get_post_type();
if (empty($post_type)) {
    $queried_object = get_queried_object();
    if (isset($queried_object->taxonomy)) {
        $post_type = str_replace('_category', '', $queried_object->taxonomy);
    }
}

// 2. Define Template Path
// Looks for: static/pages/archive-program.html, archive-event.html
$theme_dir = get_stylesheet_directory();
$template_path = $theme_dir . "/static/pages/archive-{$post_type}.html";

// Fallback
if (!file_exists($template_path)) {
    $template_path = $theme_dir . "/static/pages/archive.html";
}

// 3. LOAD HEADER
ob_start();
include $theme_dir . '/static/partials/_header.html';
$header = ob_get_clean();

if (strpos($header, '</head>') !== false) {
    echo str_replace('</head>', wp_head() . '</head>', $header);
} else {
    echo $header; wp_head();
}

// 4. LOAD HTML & INJECT DATA
if (file_exists($template_path)) {
    $html = file_get_contents($template_path);

    // Dynamic Title Logic (Removes "Archives:", "Category:" prefixes)
    $title = get_the_archive_title();
    $title = preg_replace('/^Prefix:/', '', $title); 
    $title = str_replace(['Archives: ', 'Category: '], '', $title);

    $html = str_replace('{title}', $title, $html);

    // Asset Path Fixes
    $theme_uri = get_stylesheet_directory_uri();
    $html = preg_replace('/(href|src)=["\']\s*(\.{0,2}\/?)css\//', '$1="' . $theme_uri . '/assets/css/', $html);
    $html = preg_replace('/(href|src)=["\']\s*(\.{0,2}\/?)js\//', '$1="' . $theme_uri . '/assets/js/', $html);
    $html = preg_replace('/(href|src|srcset|data-src)=["\']\s*(\.{0,2}\/?)images\//', '$1="' . $theme_uri . '/assets/images/', $html);
    $html = preg_replace('/(href|src)=["\']\s*(\.{0,2}\/?)assets\//', '$1="' . $theme_uri . '/assets/', $html);

    // Render Shortcodes (This triggers [archive_loop])
    echo do_shortcode($html);

} else {
    // Dev Error Message
    echo '<div style="padding:100px; text-align:center;">';
    echo '<h2>Archive Template Missing</h2>';
    echo '<p>Create file: <code>static/pages/archive-' . $post_type . '.html</code></p>';
    echo '</div>';
}

// 5. LOAD FOOTER
ob_start();
include $theme_dir . '/static/partials/_footer.html';
$footer = ob_get_clean();

if (strpos($footer, '</body>') !== false) {
    echo str_replace('</body>', wp_footer() . '</body>', $footer);
} else {
    wp_footer(); echo $footer;
}
?>