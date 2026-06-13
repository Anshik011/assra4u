<?php
/*
=====================================================
ASSRA PROFESSIONAL ENGINE – FUNCTIONS.PHP
(Production: Security Hardened + Mobile Fixed)
=====================================================
SCRIPT LOADING STRATEGY:
  - CSS only loaded via wp_enqueue_styles
  - ALL scripts loaded manually in _footer.html
  - This avoids double-loading and load order issues
  - WP jQuery deregistered to prevent conflicts

SECURITY FIXES APPLIED:
  [S1]  Razorpay keys in wp-config.php (fallback for dev)
  [S2]  CSRF nonce on contact form
  [S3]  Rate limiting on contact form
  [S4]  CSRF nonce on donation AJAX
  [S5]  Donation amount capped ₹1–₹1,00,000
  [S6]  REQUEST_URI sanitized
  [S7]  wp_head/wp_footer properly buffered
  [S8]  Router slug sanitized
  [S9]  Admin notice escaped
  [S10] save_post autosave + capability checks
  [S11] assra_ajax injected via wp_head
  [S12] hash_equals() timing-safe signature check

REQUIRED — add to wp-config.php before "stop editing":
  define('RZP_KEY_ID',     'rzp_test_SEkhbMuQglqWkJ');
  define('RZP_KEY_SECRET', 'zBRkbpmN174ITw7z40Q5hdFS');
=====================================================
*/

/* ==============================
   1. THEME SETUP
==============================*/
add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    register_nav_menus(['main-menu' => 'Main Header Menu']);
});

/* ==============================
   2. DATA STRUCTURE
==============================*/
add_action('init', function () {
    register_taxonomy('assra_program', ['post', 'event', 'gallery'], [
        'label' => 'Programs (Pillars)', 'hierarchical' => true, 'show_admin_column' => true, 'show_in_rest' => true, 'rewrite' => ['slug' => 'program-filter'],
    ]);
    register_post_type('gallery', [
        'labels' => ['name' => 'Gallery', 'singular_name' => 'Photo Album'], 'public' => true, 'menu_icon' => 'dashicons-format-gallery', 'supports' => ['title', 'editor', 'thumbnail', 'excerpt'], 'has_archive' => true, 'taxonomies' => ['assra_program'],
    ]);
    register_post_type('event', [
        'labels' => ['name' => 'Events', 'singular_name' => 'Event'], 'public' => true, 'menu_icon' => 'dashicons-calendar-alt', 'supports' => ['title', 'editor', 'thumbnail', 'custom-fields'], 'has_archive' => true, 'taxonomies' => ['assra_program'],
    ]);
    register_taxonomy('doc_type', ['document'], [
        'label' => 'Document Types', 'hierarchical' => true, 'show_admin_column' => true, 'rewrite' => ['slug' => 'doc-type'],
    ]);
    register_post_type('document', [
        'labels' => ['name' => 'Documents', 'singular_name' => 'Document'], 'public' => true, 'menu_icon' => 'dashicons-media-document', 'supports' => ['title', 'custom-fields'], 'has_archive' => true, 'taxonomies' => ['doc_type'],
    ]);
    register_post_type('assra_inquiry', [
        'labels' => ['name' => 'Web Inquiries', 'singular_name' => 'Inquiry'], 'public' => false, 'show_ui' => true, 'menu_icon' => 'dashicons-email', 'supports' => ['title', 'editor', 'custom-fields'], 'capabilities' => ['create_posts' => false],
    ]);
    register_post_type('board_member', [
        'labels' => ['name' => 'Board Members', 'singular_name' => 'Member'], 'public' => true, 'menu_icon' => 'dashicons-businessperson', 'supports' => ['title', 'thumbnail', 'excerpt'],
    ]);
    register_post_type('media_clip', [
        'labels' => ['name' => 'Media Coverage', 'singular_name' => 'Media Clip'], 'public' => true, 'menu_icon' => 'dashicons-megaphone', 'supports' => ['title', 'thumbnail'],
    ]);
    register_post_type('partner', [
        'labels' => ['name' => 'Top Donors', 'singular_name' => 'Donor'], 'public' => true, 'menu_icon' => 'dashicons-heart', 'supports' => ['title', 'thumbnail'],
    ]);
    register_post_type('voice', [
        'labels' => ['name' => 'Community Voices', 'singular_name' => 'Voice'], 'public' => true, 
'menu_icon' => 'dashicons-testimonial', 'supports' => ['title', 'editor', 'custom-fields'],
    ]);
    register_post_type('award', [
        'labels' => ['name' => 'Awards', 'singular_name' => 'Award'], 'public' => true, 
'menu_icon' => 'dashicons-awards', 'supports' => ['title', 'editor', 'custom-fields'],
    ]);
});

// Add Custom Meta Boxes for Community Voices
add_action('add_meta_boxes', function() {
    add_meta_box('voice_details_meta', 'Voice Details', function($post) {
        $subtitle = get_post_meta($post->ID, 'voice_subtitle', true);
        $location = get_post_meta($post->ID, 'voice_location', true);
        wp_nonce_field('voice_details_nonce', 'voice_details_nonce_field');
        echo '<p><label for="voice_subtitle"><strong>Subtitle:</strong></label><br/>';
        echo '<input type="text" id="voice_subtitle" name="voice_subtitle" value="' . esc_attr($subtitle) . '" style="width:100%; max-width:600px;" placeholder="e.g. Beneficiary — Community Mental Health" /></p>';
        echo '<p><label for="voice_location"><strong>Location:</strong></label><br/>';
        echo '<input type="text" id="voice_location" name="voice_location" value="' . esc_attr($location) . '" style="width:100%; max-width:600px;" placeholder="e.g. Bhikangaon, Khargone" /></p>';
    }, 'voice', 'normal', 'high');
});

// Save Custom Meta Box Data
add_action('save_post', function($post_id) {
    if (!isset($_POST['voice_details_nonce_field']) || !wp_verify_nonce($_POST['voice_details_nonce_field'], 'voice_details_nonce')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    
    if (isset($_POST['voice_subtitle'])) {
        update_post_meta($post_id, 'voice_subtitle', sanitize_text_field($_POST['voice_subtitle']));
    }
    if (isset($_POST['voice_location'])) {
        update_post_meta($post_id, 'voice_location', sanitize_text_field($_POST['voice_location']));
    }
});

// Add Custom Meta Box for Awards
add_action('add_meta_boxes', function() {
    add_meta_box('award_details_meta', 'Award Details', function($post) {
        $award_year = get_post_meta($post->ID, 'award_year', true);
        wp_nonce_field('award_details_nonce', 'award_details_nonce_field');
        echo '<p><label for="award_year">Award Year</label><br><input type="text" id="award_year" name="award_year" value="'.esc_attr($award_year).'" style="width:100%;"></p>';
    }, 'award', 'normal', 'high');
});

add_action('save_post', function($post_id) {
    if (!isset($_POST['award_details_nonce_field']) || !wp_verify_nonce($_POST['award_details_nonce_field'], 'award_details_nonce')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (isset($_POST['award_year'])) {
        update_post_meta($post_id, 'award_year', sanitize_text_field($_POST['award_year']));
    }
});

/* ==============================
   3. ASSET LOADER
   CSS ONLY — scripts are in _footer.html
   Deregister WP jQuery to prevent conflicts
   with the jQuery loaded in _footer.html
==============================*/
add_action('wp_enqueue_scripts', function () {
    $theme_uri = get_stylesheet_directory_uri();
    $theme_dir = get_stylesheet_directory();

    // CSS only
    wp_enqueue_style('assra-style', $theme_uri . '/assets/css/style.css', [], filemtime($theme_dir . '/assets/css/style.css'));

    // Prevent WordPress from injecting its own jQuery — _footer.html handles it
    wp_deregister_script('jquery');
    wp_deregister_script('jquery-core');
    wp_deregister_script('jquery-migrate');
});

// [S11] Inject AJAX config into every page <head>
// Uses wp_head which is buffered by utw_load_static_page() — works in .html files
add_action('wp_head', function () {
    // Preload LCP image for the home page to improve PageSpeed score
    global $post;
    if (is_front_page() || is_home() || (isset($post) && $post->post_name === 'home')) {
        echo '<link rel="preload" as="image" href="' . esc_url(get_stylesheet_directory_uri() . '/assets/images/main-slider/7.jpg') . '">' . "\n";
    }

    echo '<script>var assra_ajax = {'
       . '"ajax_url":"'       . esc_js(admin_url('admin-ajax.php'))             . '",'
       . '"donation_nonce":"' . esc_js(wp_create_nonce('assra_donation_nonce')) . '",'
       . '"contact_nonce":"'  . esc_js(wp_create_nonce('assra_contact_nonce'))  . '"'
       . '};</script>' . "\n";
}, 1);

/* ==============================
   4. CONTACT FORM HANDLER
   [S2] Nonce verification
   [S3] Rate limiting (1 per 60s per IP)
==============================*/
add_action('admin_post_assra_contact_form', 'assra_handle_form_submit');
add_action('admin_post_nopriv_assra_contact_form', 'assra_handle_form_submit');

function assra_handle_form_submit() {
    $token = $_POST['assra_contact_token'] ?? '';
    if (empty($token) || !wp_verify_nonce($token, 'assra_contact_nonce')) {
        wp_die('Security check failed. Please go back and try again.');
    }
    if (!isset($_POST['submit-form'])) return;

    $ip_key = 'assra_contact_limit_' . md5($_SERVER['REMOTE_ADDR']);
    if (get_transient($ip_key)) {
        wp_die('You are submitting too quickly. Please wait a moment and try again.');
    }
    set_transient($ip_key, 1, 60);

    $name    = sanitize_text_field($_POST['name']    ?? '');
    $email   = sanitize_email($_POST['email']        ?? '');
    $phone   = sanitize_text_field($_POST['phone']   ?? '');
    $subject = sanitize_text_field($_POST['subject'] ?? '');
    $message = sanitize_textarea_field($_POST['message'] ?? '');

    if (empty($name) || empty($email) || !is_email($email)) {
        wp_die('Invalid form data. Please fill in all required fields.');
    }

    wp_insert_post([
        'post_title'   => $name . ' - ' . $subject,
        'post_content' => "<strong>From:</strong> "    . esc_html($name)    . "\n"
                        . "<strong>Email:</strong> "   . esc_html($email)   . "\n"
                        . "<strong>Phone:</strong> "   . esc_html($phone)   . "\n\n"
                        . "<strong>Message:</strong>\n". esc_html($message),
        'post_type'    => 'assra_inquiry',
        'post_status'  => 'publish',
    ]);

    wp_redirect(home_url('/contact/?success=1'));
    exit;
}

/* ==========================================================================
   5. STATIC PAGE ENGINE
   [S6] REQUEST_URI sanitized
   [S7] wp_head() / wp_footer() properly buffered
========================================================================== */
function utw_load_static_page($slug) {
    $theme_dir = get_stylesheet_directory();
    $theme_uri = get_stylesheet_directory_uri();

    $page_file   = $theme_dir . "/static/pages/{$slug}.html";
    $header_file = $theme_dir . "/static/partials/_header.html";
    $footer_file = $theme_dir . "/static/partials/_footer.html";

    if (!file_exists($page_file)) return false;

    $capture = function ($path) {
        if (!file_exists($path)) return '';
        ob_start(); include($path); return ob_get_clean();
    };

    $head_html   = $capture($header_file);
    $current_url = home_url(sanitize_url($_SERVER['REQUEST_URI']));

    $seo_title = get_bloginfo('name') . " | Non-Profit Organization";
    $seo_desc  = "ASSRA is a non-profit NGO working for Education, Empowerment, Elderly Care, and Environmental initiatives.";
    $seo_img   = $theme_uri . '/assets/images/background/bg-banner-1.jpg';

    if (is_singular()) {
        $id        = get_the_ID();
        $seo_title = get_the_title($id) . " | ASSRA";
        $seo_desc  = wp_trim_words(get_the_excerpt($id), 25);
        $feat_img  = get_the_post_thumbnail_url($id, 'large');
        if ($feat_img) $seo_img = $feat_img;
    }

    $meta_block = '
    <title>' . esc_html($seo_title) . '</title>
    <meta name="description" content="' . esc_attr($seo_desc) . '">
    <link rel="canonical" href="' . esc_url($current_url) . '">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta property="og:title" content="' . esc_html($seo_title) . '">
    <meta property="og:description" content="' . esc_attr($seo_desc) . '">
    <meta property="og:image" content="' . esc_url($seo_img) . '">
    <meta property="og:url" content="' . esc_url($current_url) . '">
    <meta name="twitter:card" content="summary_large_image">';

    $head_html = preg_replace('/<title>.*?<\/title>/i', '', $head_html);

    // [S7] Buffer wp_head() — it echoes, does not return
    ob_start(); wp_head(); $wp_head_output = ob_get_clean();

    if (strpos($head_html, '</head>') !== false) {
        $head_html = str_replace('</head>', $meta_block . $wp_head_output . '</head>', $head_html);
    } else {
        $head_html .= $meta_block . $wp_head_output;
    }

    $body_html = file_get_contents($page_file);

    if (is_singular()) {
        $id    = get_the_ID();
        $p_obj = get_post($id);
        $body_html = str_replace('{page_title}', esc_html($p_obj->post_title), $body_html);
        $final_content = apply_filters('the_content', $p_obj->post_content);
        $body_html = str_replace('{content}',    $final_content ?: "<p>No description available.</p>", $body_html);
        $feat_img  = get_the_post_thumbnail_url($id, 'full') ?: $theme_uri . '/assets/images/background/bg-banner-1.jpg';
        $body_html = str_replace('{page_image}', esc_url($feat_img), $body_html);
        $body_html = str_replace(['{day}','{month}','{year}'], [get_the_date('d',$id), get_the_date('M',$id), get_the_date('Y',$id)], $body_html);
        $body_html = str_replace('{time}',     get_post_meta($id, 'event_time', true)     ?: '10:00 AM - 5:00 PM', $body_html);
        $body_html = str_replace('{location}', get_post_meta($id, 'event_location', true) ?: 'Odisha, India',      $body_html);
    }

    $foot_html = $capture($footer_file);

    // [S7] Buffer wp_footer() — it echoes, does not return
    ob_start(); wp_footer(); $wp_footer_output = ob_get_clean();

    if (strpos($foot_html, '</body>') !== false) {
        $foot_html = str_replace('</body>', $wp_footer_output . '</body>', $foot_html);
    } else {
        $foot_html .= $wp_footer_output;
    }

    $html = $head_html . $body_html . $foot_html;

    // Path fixer
    $html = str_replace('__ADMIN_POST_URL__', admin_url('admin-post.php'), $html);
    $html = preg_replace('/(href|src)=["\']\s*(\.{0,2}\/?)css\//',                    '$1="' . $theme_uri . '/assets/css/',    $html);
    $html = preg_replace('/(href|src)=["\']\s*(\.{0,2}\/?)js\//',                     '$1="' . $theme_uri . '/assets/js/',     $html);
    $html = preg_replace('/(href|src|srcset|data-src)=["\']\s*(\.{0,2}\/?)images\//', '$1="' . $theme_uri . '/assets/images/', $html);
    $html = preg_replace('/(href|src)=["\']\s*(\.{0,2}\/?)assets\//',                 '$1="' . $theme_uri . '/assets/',        $html);
    $html = preg_replace('/url\(\s*["\']?(\.{0,2}\/?)images\//',                      'url(' . $theme_uri . '/assets/images/', $html);
    $html = preg_replace('/url\(\s*["\']?(\.{0,2}\/?)fonts\//',                       'url(' . $theme_uri . '/assets/fonts/',  $html);
    $html = preg_replace('/href=["\']\/(?!\/)/',                                       'href="' . home_url('/'),                $html);
    $html = str_replace(['{theme_url}', '{site_url}'], [$theme_uri, site_url()], $html);

    echo do_shortcode($html);
    return true;
}

/* ==============================
   6. UNIVERSAL ROUTER
   [S8] Slug sanitized
==============================*/
add_action('template_redirect', function () {
    global $post;
    $slug = 'home';

    if (is_front_page()) {
        $slug = 'home';
    } elseif (is_singular('post')) {
        $slug = 'single-post';
    } elseif (is_singular('event') || is_singular('gallery')) {
        $slug = 'single-event';
    } elseif (is_tax('doc_type') || is_page('documents')) {
        $slug = 'documents';
    } elseif (is_page()) {
        $slug = $post->post_name;
        if (in_array($slug, ['education', 'empowerment', 'elderly-care', 'environment'])) {
            $slug = 'programs';
        }
    } elseif (is_post_type_archive('event')) {
        $slug = 'events';
    } elseif (is_post_type_archive('gallery')) {
        $slug = 'gallery';
    } else {
        global $wp;
        $slug = basename(sanitize_file_name($wp->request));
    }

    if (file_exists(get_stylesheet_directory() . "/static/pages/{$slug}.html")) {
        utw_load_static_page($slug);
        exit;
    }
});

/* ==============================
   7. UNIVERSAL TOOLKIT
==============================*/
add_shortcode('year_filter', function () {
    global $wpdb, $post;
    $current_pillar = '';
    if (is_page()) {
        $s = $post->post_name;
        if (in_array($s, ['education','empowerment','elderly-care','environment','health'])) $current_pillar = $s;
    }
    $years = $wpdb->get_col($wpdb->prepare("
        SELECT DISTINCT pm.meta_value FROM {$wpdb->postmeta} pm
        INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
        INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
        INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
        INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
        WHERE pm.meta_key = 'gallery_year' AND pm.meta_value != ''
        AND p.post_type = 'gallery' AND p.post_status = 'publish' AND t.slug = %s
        ORDER BY pm.meta_value DESC
    ", $current_pillar));
    if (empty($years)) return '';
    $selected = isset($_GET['gallery_year']) ? intval($_GET['gallery_year']) : $years[0];
    ob_start();
    echo '<div class="gallery-filter-bar" style="text-align:center;margin-bottom:30px;">';
    foreach ($years as $year) {
        echo '<a href="?gallery_year=' . esc_attr($year) . '" class="filter-btn ' . ($selected == $year ? 'active' : '') . '">' . esc_html($year) . '</a>';
    }
    echo '</div>';
    return ob_get_clean();
});

remove_shortcode('universal_loop');
add_shortcode('universal_loop', 'utw_universal_loop_handler');

function utw_universal_loop_handler($atts, $content = null) {
    global $wpdb, $post;
    $atts = shortcode_atts(['type'=>'post','count'=>12,'taxonomy'=>'','term'=>'','pagination'=>'false','image_size'=>'large'], $atts);
    $current_pillar = $atts['term'];
    if (empty($atts['taxonomy']) && empty($atts['term']) && is_page()) {
        $s = $post->post_name;
        if (in_array($s, ['education','empowerment','elderly-care','environment','health'])) {
            $atts['taxonomy'] = 'assra_program'; $atts['term'] = $s; $current_pillar = $s;
        }
    }
    $filter_year = null;
    if ($atts['type'] === 'gallery') {
        $latest = $wpdb->get_var($wpdb->prepare("
            SELECT pm.meta_value FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
            INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
            WHERE pm.meta_key = 'gallery_year' AND p.post_status = 'publish' AND t.slug = %s
            ORDER BY CAST(pm.meta_value AS UNSIGNED) DESC LIMIT 1
        ", $current_pillar));
        $filter_year = isset($_GET['gallery_year']) ? intval($_GET['gallery_year']) : ($latest ?: date('Y'));
    }
    $orderby = 'date'; $order = 'DESC';
    if (in_array($atts['type'], ['board_member','board','partner'])) { $orderby = 'menu_order'; $order = 'ASC'; }
    $args = ['post_type'=>$atts['type'],'posts_per_page'=>$atts['count'],'post_status'=>'publish','paged'=>(get_query_var('paged'))?:1,'orderby'=>$orderby,'order'=>$order];
    if (!empty($atts['taxonomy']) && !empty($atts['term'])) $args['tax_query'] = [['taxonomy'=>$atts['taxonomy'],'field'=>'slug','terms'=>$atts['term']]];
    if ($atts['type']==='gallery' && $filter_year) { $args['meta_key']='gallery_year'; $args['meta_value']=$filter_year; }
    $query = new WP_Query($args);
    if (!$query->have_posts()) return '<div class="no-data-placeholder col-12" style="text-align:center;padding:60px 20px;background:#fafafa;border:2px dashed #e0e0e0;border-radius:10px;margin-bottom:30px;width:100%;"><div style="font-size:45px;color:#28a745;margin-bottom:15px;"><i class="fas fa-folder-open"></i></div><h3 style="font-size:24px;color:#1a365d;margin-bottom:10px;">More Updates Coming Soon</h3><p style="color:#666;font-size:16px;max-width:500px;margin:0 auto;">We are currently organizing new content for this section. Please check back later.</p></div>';
    $template_html = do_shortcode(shortcode_unautop($content));
    $output = '';
    while ($query->have_posts()) : $query->the_post();
        $id  = get_the_ID();
        $img = get_the_post_thumbnail_url($id, $atts['image_size']) ?: get_stylesheet_directory_uri() . '/assets/images/resource/news-1.jpg';
        $subtitle = get_post_meta($id, 'voice_subtitle', true);
        $location = get_post_meta($id, 'voice_location', true);
        $award_year = get_post_meta($id, 'award_year', true) ?: get_the_date('Y');
        $subtitle_html = $subtitle ? '<div class="subtitle">'.esc_html($subtitle).'</div>' : '';
        $location_html = $location ? '<div class="location"><i class="fa fa-map-marker-alt"></i> '.esc_html($location).'</div>' : '';
        
        $output .= str_replace(
            ['{title}','{link}','{text}','{image}','{day}','{month}','{year}','{item_subtitle_html}','{item_location_html}','{full_text}','{award_year}'],
            [esc_html(get_the_title()),get_permalink(),get_the_excerpt(),esc_url($img).'" loading="lazy',get_the_date('d'),get_the_date('M'),$filter_year,$subtitle_html,$location_html,get_the_content(),esc_html($award_year)],
            $template_html
        );
    endwhile;
    wp_reset_postdata();
    return $output;
}

/* ==============================
   8. ADMIN FIELDS
   [S10] Autosave skip + capability check
==============================*/
add_action('add_meta_boxes', function () {
    add_meta_box('doc_settings_box', 'Document Settings', 'assra_render_doc_settings', 'document', 'normal', 'high');
    add_meta_box('gallery_year_box', 'Event Year',        'assra_render_gallery_year',  'gallery',  'side',   'high');
});
function assra_render_doc_settings($post) {
    $value = get_post_meta($post->ID, 'document_file', true);
    wp_nonce_field('save_meta_data', 'assra_nonce');
    echo '<p><label><strong>PDF / File URL:</strong></label><br><input type="text" name="document_file" value="' . esc_attr($value) . '" style="width:100%;padding:8px;"></p>';
}
function assra_render_gallery_year($post) {
    $value = get_post_meta($post->ID, 'gallery_year', true) ?: date('Y');
    wp_nonce_field('save_meta_data', 'assra_nonce');
    echo '<p><label><strong>Year of Event:</strong></label><br><input type="number" name="gallery_year" value="' . esc_attr($value) . '" style="width:100%;padding:5px;"><br><small>Type the year (e.g. 2018) to group this photo.</small></p>';
}
add_action('save_post', function ($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    if (!isset($_POST['assra_nonce']) || !wp_verify_nonce($_POST['assra_nonce'], 'save_meta_data')) return;
    if (isset($_POST['document_file'])) update_post_meta($post_id, 'document_file', sanitize_text_field($_POST['document_file']));
    if (isset($_POST['gallery_year'])) { $y = intval($_POST['gallery_year']); if ($y >= 1900 && $y <= 2100) update_post_meta($post_id, 'gallery_year', $y); }
});

/* ==============================
   9. RAZORPAY
   [S1]  Fallback keys for dev (move to wp-config for production)
   [S4]  CSRF nonce
   [S5]  Amount cap
   [S12] Timing-safe signature
==============================*/
if (!defined('RZP_KEY_ID'))     define('RZP_KEY_ID',     'rzp_test_SEkhbMuQglqWkJ');
if (!defined('RZP_KEY_SECRET')) define('RZP_KEY_SECRET', 'zBRkbpmN174ITw7z40Q5hdFS');

add_action('init', function () {
    register_post_type('assra_donation', [
        'labels'=>['name'=>'Donations','singular_name'=>'Donation'],'public'=>false,'show_ui'=>true,
        'menu_icon'=>'dashicons-money','supports'=>['title','custom-fields'],'capabilities'=>['create_posts'=>false],
    ]);
});

add_action('wp_ajax_assra_create_order',        'assra_create_order');
add_action('wp_ajax_nopriv_assra_create_order', 'assra_create_order');
function assra_create_order() {
    if (!check_ajax_referer('assra_donation_nonce', 'nonce', false)) wp_send_json_error('Security check failed.');
    $amount = intval($_POST['amount'] ?? 0);
    if (!$amount || $amount < 1 || $amount > 100000) wp_send_json_error('Invalid amount. Must be ₹1–₹1,00,000.');
    $response = wp_remote_post('https://api.razorpay.com/v1/orders', [
        'headers' => ['Authorization' => 'Basic ' . base64_encode(RZP_KEY_ID . ':' . RZP_KEY_SECRET), 'Content-Type' => 'application/json'],
        'body'    => wp_json_encode(['receipt'=>'rcpt_'.time(),'amount'=>$amount*100,'currency'=>'INR','payment_capture'=>1]),
    ]);
    if (is_wp_error($response)) wp_send_json_error('Connection error.');
    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (isset($body['id'])) wp_send_json_success(['order_id'=>$body['id'],'rzp_key'=>RZP_KEY_ID]);
    else wp_send_json_error('Could not create order.');
}

add_action('wp_ajax_assra_verify_payment',        'assra_verify_payment');
add_action('wp_ajax_nopriv_assra_verify_payment', 'assra_verify_payment');
function assra_verify_payment() {
    if (!check_ajax_referer('assra_donation_nonce', 'nonce', false)) wp_send_json_error('Security check failed.');
    $payment_id = sanitize_text_field($_POST['razorpay_payment_id'] ?? '');
    $order_id   = sanitize_text_field($_POST['razorpay_order_id']   ?? '');
    $signature  = sanitize_text_field($_POST['razorpay_signature']  ?? '');
    if (empty($payment_id)||empty($order_id)||empty($signature)) wp_send_json_error('Missing payment data.');
    if (hash_equals(hash_hmac('sha256', $order_id.'|'.$payment_id, RZP_KEY_SECRET), $signature)) {
        $donor_name = sanitize_text_field($_POST['donor_name']  ?? '');
        $amount     = intval($_POST['amount']                   ?? 0);
        $phone      = sanitize_text_field($_POST['donor_phone'] ?? '');
        $purpose    = sanitize_text_field($_POST['purpose']     ?? '');
        $post_id = wp_insert_post([
            'post_title'   => esc_html($donor_name).' - ₹'.$amount,
            'post_type'    => 'assra_donation', 'post_status' => 'publish',
            'post_content' => "<strong>Payment ID:</strong> ".esc_html($payment_id)."\n<strong>Order ID:</strong> ".esc_html($order_id)."\n<strong>Phone:</strong> ".esc_html($phone)."\n<strong>Purpose:</strong> ".esc_html($purpose),
        ]);
        if ($post_id) { update_post_meta($post_id,'payment_id',$payment_id); update_post_meta($post_id,'amount',$amount); update_post_meta($post_id,'phone',$phone); }
        wp_send_json_success('Payment Verified and Logged');
    } else { wp_send_json_error('Signature Verification Failed'); }
}

/* ==============================
   10. ADMIN UTILITIES
   [S9] Escaped output
==============================*/
add_filter('bulk_actions-upload', function ($ba) { $ba['make_media_auto'] = 'Auto-Create Media Clips (Uses Image Names)'; return $ba; });
add_filter('handle_bulk_actions-upload', function ($url, $action, $ids) {
    if ($action !== 'make_media_auto') return $url;
    $count = 0;
    foreach ($ids as $id) {
        if (strpos(get_post_mime_type($id), 'image') === false) continue;
        $title = ucwords(str_replace(['-','_'],' ',get_the_title($id)));
        $new   = wp_insert_post(['post_title'=>$title,'post_type'=>'media_clip','post_status'=>'publish']);
        if ($new) {
            set_post_thumbnail($new, $id);
            wp_update_post(['ID'=>$id,'post_parent'=>$new]);
            $year = date('Y');
            if (preg_match('/\b(201\d|202\d)\b/', $title, $m)) $year = $m[1];
            update_post_meta($new, 'gallery_year', $year);
            $count++;
        }
    }
    return add_query_arg('media_converted_auto', $count, $url);
}, 10, 3);
add_action('admin_notices', function () {
    if (!empty($_REQUEST['media_converted_auto']))
        echo '<div class="notice notice-success is-dismissible"><p><strong>Done!</strong> Generated <strong>' . esc_html(intval($_REQUEST['media_converted_auto'])) . '</strong> Media Coverage posts.</p></div>';
});

/* ==============================
   11. MENU WALKER
==============================*/
class ASSRA_Menu_Walker extends Walker_Nav_Menu {
    public function start_lvl(&$output, $depth = 0, $args = null) { $output .= '<ul>'; }
    public function start_el(&$output, $item, $depth = 0, $args = null, $id = 0) {
        $classes      = empty($item->classes) ? [] : (array) $item->classes;
        $has_children = in_array('menu-item-has-children', $classes);
        if ($has_children) $classes[] = 'dropdown';
        $output .= '<li class="' . esc_attr(implode(' ', array_filter($classes))) . '">';
        $output .= '<a href="' . esc_url($item->url) . '">' . esc_html($item->title) . '</a>';
        if ($has_children) $output .= '<div class="dropdown-btn"><span class="fa fa-angle-down"></span></div>';
    }
}