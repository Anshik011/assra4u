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

require_once get_stylesheet_directory() . '/inc/gallery-ai-importer.php';

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
    register_taxonomy('assra_program', ['post', 'gallery', 'voice'], [
        'label' => 'Programs (Pillars)', 'hierarchical' => true, 'show_admin_column' => true, 'show_in_rest' => true, 'rewrite' => ['slug' => 'program-filter'],
    ]);
    register_post_type('gallery', [
        'labels' => ['name' => 'Gallery', 'singular_name' => 'Photo Album'], 'public' => true, 'menu_icon' => 'dashicons-format-gallery', 'supports' => ['title', 'editor', 'thumbnail', 'excerpt'], 'has_archive' => true, 'taxonomies' => ['assra_program'],
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
    register_post_type('assra_volunteer', [
        'labels' => ['name' => 'Volunteer Apps', 'singular_name' => 'Volunteer App'], 'public' => false, 'show_ui' => true, 'menu_icon' => 'dashicons-groups', 'supports' => ['title', 'editor', 'custom-fields'], 'capabilities' => ['create_posts' => false],
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
        echo '<link rel="preload" as="image" href="' . esc_url(get_stylesheet_directory_uri() . '/assets/images/elderly-care/8a75003e-9aba-4525-877a-b4ebd0faf31d_IMG_8276.webp') . '">' . "\n";
    }

    echo '<script>var assra_ajax = {'
       . '"ajax_url":"'       . esc_js(admin_url('admin-ajax.php'))             . '",'
       . '"donation_nonce":"' . esc_js(wp_create_nonce('assra_donation_nonce')) . '",'
       . '"contact_nonce":"'  . esc_js(wp_create_nonce('assra_contact_nonce'))  . '",'
       . '"volunteer_nonce":"' . esc_js(wp_create_nonce('assra_volunteer_nonce')) . '"'
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

function assra_handle_volunteer_submit() {
    $token = $_POST['assra_volunteer_token'] ?? '';
    if (empty($token) || !wp_verify_nonce($token, 'assra_volunteer_nonce')) {
        wp_die('Security check failed. Please go back and try again.');
    }
    if (!isset($_POST['submit-form'])) return;

    $ip_key = 'assra_volunteer_limit_' . md5($_SERVER['REMOTE_ADDR']);
    if (get_transient($ip_key)) {
        wp_die('You are submitting too quickly. Please wait a moment and try again.');
    }
    set_transient($ip_key, 1, 60);

    $name     = sanitize_text_field($_POST['name'] ?? '');
    $email    = sanitize_email($_POST['email'] ?? '');
    $phone    = sanitize_text_field($_POST['phone'] ?? '');
    $interest = sanitize_text_field(implode(', ', (array)($_POST['interest'] ?? [])));
    $message  = sanitize_textarea_field($_POST['message'] ?? '');

    if (empty($name) || empty($email) || !is_email($email)) {
        wp_die('Invalid form data. Please fill in all required fields.');
    }

    wp_insert_post([
        'post_title'   => 'Volunteer App: ' . $name,
        'post_content' => "<strong>Name:</strong> " . esc_html($name) . "\n"
                        . "<strong>Email:</strong> " . esc_html($email) . "\n"
                        . "<strong>Phone:</strong> " . esc_html($phone) . "\n"
                        . "<strong>Interest:</strong> " . esc_html($interest) . "\n\n"
                        . "<strong>Cover Message:</strong>\n" . esc_html($message),
        'post_type'    => 'assra_volunteer',
        'post_status'  => 'publish',
    ]);

    wp_redirect(home_url('/volunteer/?success=1'));
    exit;
}
add_action('admin_post_assra_volunteer_form', 'assra_handle_volunteer_submit');
add_action('admin_post_nopriv_assra_volunteer_form', 'assra_handle_volunteer_submit');

/* ==========================================================================
   5. STATIC PAGE ENGINE
   [S6] REQUEST_URI sanitized
   [S7] wp_head() / wp_footer() properly buffered
========================================================================== */
function utw_load_static_page($slug) {
    // Send Security Headers & Cache-Control for performance
    if (!headers_sent()) {
        header("X-Frame-Options: SAMEORIGIN");
        header("X-Content-Type-Options: nosniff");
        header("X-XSS-Protection: 1; mode=block");
        header("Referrer-Policy: no-referrer-when-downgrade");
        header("Cache-Control: public, max-age=3600");
    }

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

    // Performance Optimization: Cache File I/O
    $cache_capture = function ($path) use ($capture) {
        $cache_key = 'assra_file_' . md5($path);
        $cached = wp_cache_get($cache_key, 'assra_files');
        if (false === $cached) {
            $cached = $capture($path);
            wp_cache_set($cache_key, $cached, 'assra_files', 3600);
        }
        return $cached;
    };

    $cache_file_get = function ($path) {
        $cache_key = 'assra_file_' . md5($path);
        $cached = wp_cache_get($cache_key, 'assra_files');
        if (false === $cached) {
            $cached = file_get_contents($path);
            wp_cache_set($cache_key, $cached, 'assra_files', 3600);
        }
        return $cached;
    };

    $head_html   = $cache_capture($header_file);
    global $wp;
    $current_url = home_url(add_query_arg([], $wp->request));

    $seo_title = get_bloginfo('name') . " | Non-Profit Organization";
    $seo_desc  = "ASSRA is a non-profit NGO working for Education, Empowerment, Elderly Care, and Environmental initiatives.";
    $seo_img   = $theme_uri . '/assets/images/background/bg-banner-1.jpg';

    if ($slug === 'education-work') {
        $seo_title = "Education NGO in India | Charity Supporting Children Education - ASSRA";
        $seo_desc  = "ASSRA is a leading non-profit NGO dedicated to providing quality education, nutrition, and support to underprivileged children in India. Join us in transforming lives.";
        $seo_img   = $theme_uri . '/assets/images/education/WhatsApp Image 2026-07-01 at 8.25.34 AM.webp';
    } elseif ($slug === 'elderly-care') {
        $seo_title = "Elderly Care NGO in India | Supporting Destitute Senior Citizens - ASSRA";
        $seo_desc  = "ASSRA provides shelter, nutrition, healthcare, and rehabilitation for abandoned and destitute senior citizens, ensuring they live with safety and comfort.";
        $seo_img   = $theme_uri . '/assets/images/elderly-care/8a75003e-9aba-4525-877a-b4ebd0faf31d_IMG_8276.webp';
    } elseif ($slug === 'empowerment') {
        $seo_title = "Livelihood & Women Empowerment NGO | Skill Development - ASSRA";
        $seo_desc  = "ASSRA strengthens women and marginalized communities through vocational training, tailoring workshops, and digital literacy for sustainable livelihood.";
        $seo_img   = $theme_uri . '/assets/images/empowerment/ac41f0c0-9a8a-47b3-8595-e32beb8692f5_137815c9-4e93-4379-9556-ba88b93b19f6.webp';
    } elseif ($slug === 'environment') {
        $seo_title = "Environmental Conservation & Green Initiatives NGO - ASSRA";
        $seo_desc  = "ASSRA promotes environmental preservation, waste management, afforestation drives, and solar installations for a sustainable and green future.";
        $seo_img   = $theme_uri . '/assets/images/environment/031f56f1-0abd-432d-8a0c-8d816a8efc68_ec3.webp';
    } elseif ($slug === 'about-assra') {
        $seo_title = "About ASSRA NGO | Grassroots Development & Social Service India";
        $seo_desc  = "Learn about ASSRA's history, mission, vision, and core philosophy of transforming lives through sustainable grassroots social welfare initiatives.";
    } elseif ($slug === 'donors') {
        $seo_title = "Our Partners & Top Donors | Support ASSRA NGO";
        $seo_desc  = "Meet the supporters, donors, and corporate partners who fund and fuel ASSRA's welfare programs for education, elderly care, and livelihood.";
    } elseif ($slug === 'documents') {
        $seo_title = "ASSRA Publications & Regulatory Documents";
        $seo_desc  = "Access ASSRA's transparent compliance reports, societies registration certificate, 12A/80G status, and financial audit files.";
    } elseif ($slug === 'gallery') {
        $seo_title = "ASSRA Media Gallery | Grassroots Impact Photos & Videos";
        $seo_desc  = "Explore visual highlights of ASSRA's community initiatives, shelter operations, tree planting drives, and basic education classes.";
    } elseif ($slug === 'leadership') {
        $seo_title = "ASSRA Leadership Team | Executive Board & Office Bearers";
        $seo_desc  = "Meet the dedicated executive body, office bearers, and board members driving ASSRA's operations with transparency and accountability.";
    } elseif ($slug === 'our-story') {
        $seo_title = "Our Journey | From Relief to Sustainable Empowerment - ASSRA";
        $seo_desc  = "Read the story of ASSRA's growth, from providing immediate relief to establishing long-term community development structures.";
    } elseif ($slug === 'reach-presence') {
        $seo_title = "Geographic Footprint & Reach - ASSRA NGO India";
        $seo_desc  = "See where ASSRA operates across India, including tribal villages in Jharkhand and urban education clusters in Delhi.";

    } elseif ($slug === 'contact') {
        $seo_title = "Contact ASSRA NGO | Volunteer, Support, or Enquire";
        $seo_desc  = "Get in touch with ASSRA NGO. Speak to our team about donations, corporate CSR sponsorships, or volunteering on the ground.";
    } elseif ($slug === 'donate') {
        $seo_title = "Donate to ASSRA NGO | Tax Exempt Social Contributions";
        $seo_desc  = "Support underprivileged education, elderly shelters, and green initiatives with your tax-deductible contributions to ASSRA under 80G.";
    } elseif ($slug === 'media-coverage') {
        $seo_title = "ASSRA in the News | Media Coverage & Press Highlights";
        $seo_desc  = "Read news reports and press coverage highlighting ASSRA's impact, shelter operations, and community service milestones.";
    } elseif ($slug === 'volunteer') {
        $seo_title = "Join Us as a Volunteer | Grassroots Action - ASSRA";
        $seo_desc  = "Apply to volunteer with ASSRA NGO. Gain hands-on field experience in social service, education, environment, and elderly care.";
    } elseif (is_singular()) {
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

    $body_html = $cache_file_get($page_file);

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

    $foot_html = $cache_capture($footer_file);

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
    $html = preg_replace('/url\(\s*(["\']?)(\.{0,2}\/?)images\//',                      'url($1' . $theme_uri . '/assets/images/', $html);
    $html = preg_replace('/url\(\s*(["\']?)(\.{0,2}\/?)fonts\//',                       'url($1' . $theme_uri . '/assets/fonts/',  $html);
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

    // Performance Optimization: Cache Heavy unindexed 5-way JOIN query
    $cache_key = 'assra_gallery_years_' . md5($current_pillar);
    $years = get_transient($cache_key);
    if (false === $years) {
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
        set_transient($cache_key, $years, 12 * HOUR_IN_SECONDS);
    }

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

// New gallery_filter_bar shortcode for general gallery page
add_shortcode('gallery_filter_bar', function () {
    global $wpdb;

    $current_program = isset($_GET['program']) ? sanitize_key($_GET['program']) : '';
    $current_year = isset($_GET['gallery_year']) ? intval($_GET['gallery_year']) : 0;

    // Performance Optimization: Cache dynamic header category query
    $programs = get_transient('assra_menu_programs');
    if (false === $programs) {
        $programs = get_terms([
            'taxonomy' => 'assra_program',
            'hide_empty' => false,
        ]);
        set_transient('assra_menu_programs', $programs, 12 * HOUR_IN_SECONDS);
    }

    // Fetch years that have posts under the currently selected program (if selected)
    $years_cache_key = 'assra_gallery_years_' . ($current_program ? md5($current_program) : 'all');
    $years = get_transient($years_cache_key);

    if (false === $years) {
        if ($current_program) {
            $years = $wpdb->get_col($wpdb->prepare("
                SELECT DISTINCT pm.meta_value 
                FROM {$wpdb->postmeta} pm
                INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
                INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
                WHERE pm.meta_key = 'gallery_year' AND pm.meta_value != ''
                AND p.post_type = 'gallery' AND p.post_status = 'publish'
                AND t.slug = %s AND tt.taxonomy = 'assra_program'
                ORDER BY CAST(pm.meta_value AS UNSIGNED) DESC
            ", $current_program));
        } else {
            $years = $wpdb->get_col("
                SELECT DISTINCT pm.meta_value 
                FROM {$wpdb->postmeta} pm
                INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                WHERE pm.meta_key = 'gallery_year' AND pm.meta_value != ''
                AND p.post_type = 'gallery' AND p.post_status = 'publish'
                ORDER BY CAST(pm.meta_value AS UNSIGNED) DESC
            ");
        }
        set_transient($years_cache_key, $years, 12 * HOUR_IN_SECONDS);
    }

    ob_start();
    ?>
    <div class="gallery-filters-wrapper">
        <form method="get" action="<?php echo esc_url(get_permalink()); ?>" class="gallery-filter-form">
            <div class="filter-row">
                <!-- Program Pillars -->
                <div class="filter-group pillar-group">
                    <label class="filter-label" for="gallery-program-select">Filter by Pillar</label>
                    <div class="custom-select-wrapper">
                        <select name="program" id="gallery-program-select" onchange="this.form.submit()">
                            <option value="">All Pillars</option>
                            <?php foreach ($programs as $prog) : ?>
                                <option value="<?php echo esc_attr($prog->slug); ?>" <?php selected($current_program, $prog->slug); ?>>
                                    <?php echo esc_html($prog->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Years Dropdown -->
                <div class="filter-group year-group">
                    <label class="filter-label" for="gallery-year-select">Filter by Year</label>
                    <div class="custom-select-wrapper">
                        <select name="gallery_year" id="gallery-year-select" onchange="this.form.submit()">
                            <option value="">All Years</option>
                            <?php foreach ($years as $year) : ?>
                                <option value="<?php echo esc_attr($year); ?>" <?php selected($current_year, $year); ?>>
                                    <?php echo esc_html($year); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <!-- Reset Button -->
                <?php if (!empty($current_program) || !empty($current_year)) : ?>
                    <div class="filter-group reset-group">
                        <a href="<?php echo esc_url(get_permalink()); ?>" class="reset-filter-btn">
                            <span class="fa fa-times"></span> Clear Filters
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <style>
        .gallery-filters-wrapper {
            background: #ffffff;
            padding: 25px 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            margin-bottom: 40px;
            border: 1px solid #f0f0f0;
        }
        .gallery-filter-form {
            width: 100%;
        }
        .filter-row {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            gap: 25px;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .filter-group.year-group {
            min-width: 200px;
        }
        .filter-group.pillar-group {
            min-width: 200px;
        }
        .filter-label {
            font-size: 14px;
            font-weight: 700;
            color: #1a365d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0;
        }
        .pillar-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .pillar-buttons .filter-btn {
            display: inline-block;
            padding: 8px 18px;
            font-size: 14px;
            font-weight: 600;
            color: #4a5568;
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            border-radius: 30px;
            transition: all 0.3s ease;
            text-decoration: none !important;
        }
        .pillar-buttons .filter-btn:hover {
            background: #edf2f7;
            color: #28a745;
            border-color: #cbd5e0;
            transform: translateY(-1px);
        }
        .pillar-buttons .filter-btn.active {
            background: #28a745;
            color: #ffffff;
            border-color: #28a745;
            box-shadow: 0 4px 10px rgba(40,167,69,0.3);
        }
        .custom-select-wrapper {
            position: relative;
        }
        .custom-select-wrapper select {
            width: 100%;
            padding: 10px 40px 10px 15px;
            font-size: 14px;
            font-weight: 600;
            color: #4a5568;
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            cursor: pointer;
            transition: all 0.3s ease;
            outline: none;
        }
        .custom-select-wrapper select:focus, .custom-select-wrapper select:hover {
            background: #ffffff;
            border-color: #28a745;
            box-shadow: 0 0 0 3px rgba(40,167,69,0.1);
        }
        .custom-select-wrapper::after {
            content: "\f107";
            font-family: "Font Awesome 5 Free";
            font-weight: 900;
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #718096;
            pointer-events: none;
            font-size: 16px;
        }
        .reset-filter-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 600;
            color: #e53e3e;
            background: #fff5f5;
            border: 1px solid #fed7d7;
            border-radius: 8px;
            text-decoration: none !important;
            transition: all 0.3s ease;
            height: 42px;
            margin-top: auto;
        }
        .reset-filter-btn:hover {
            background: #e53e3e;
            color: #ffffff;
            border-color: #e53e3e;
            box-shadow: 0 4px 10px rgba(229,62,62,0.2);
        }
        @media (max-width: 768px) {
            .filter-row {
                flex-direction: column;
                align-items: stretch;
            }
            .filter-group.year-group, .filter-group.pillar-group {
                min-width: 100%;
            }
            .reset-filter-btn {
                justify-content: center;
                width: 100%;
            }
        }
    </style>
    <?php
    return ob_get_clean();
});

remove_shortcode('universal_loop');
add_shortcode('universal_loop', 'utw_universal_loop_handler');

function utw_universal_loop_handler($atts, $content = null) {
    global $wpdb, $post;
    $atts = shortcode_atts([
        'type'       => 'post',
        'count'      => 12,
        'taxonomy'   => '',
        'term'       => '',
        'pagination' => 'false',
        'image_size' => 'large',
        'template'   => '',
        'layout'     => '',
    ], $atts);

    $current_pillar = $atts['term'];
    if (empty($atts['taxonomy']) && empty($atts['term']) && is_page()) {
        $s = $post->post_name;
        if (in_array($s, ['education','empowerment','elderly-care','environment','health'])) {
            $atts['taxonomy'] = 'assra_program'; $atts['term'] = $s; $current_pillar = $s;
        }
    }

    if ($atts['type'] === 'gallery' && isset($_GET['program']) && !empty($_GET['program'])) {
        $atts['taxonomy'] = 'assra_program';
        $atts['term'] = sanitize_key($_GET['program']);
        $current_pillar = $atts['term'];
    }

    if ($atts['type'] === 'document' && isset($_GET['doc_type']) && !empty($_GET['doc_type'])) {
        $atts['taxonomy'] = 'doc_type';
        $atts['term'] = sanitize_key($_GET['doc_type']);
    }

    $filter_year = null;
    if ($atts['type'] === 'gallery') {
        if (isset($_GET['gallery_year']) && !empty($_GET['gallery_year'])) {
            $filter_year = intval($_GET['gallery_year']);
        }
    }

    $orderby = 'date'; $order = 'DESC';
    if (in_array($atts['type'], ['board_member','board','partner'])) { $orderby = 'menu_order'; $order = 'ASC'; }
    
    $paged = (get_query_var('paged')) ? get_query_var('paged') : ( (isset($_GET['paged'])) ? intval($_GET['paged']) : 1 );
    $args = [
        'post_type'      => $atts['type'],
        'posts_per_page' => $atts['count'],
        'post_status'    => 'publish',
        'paged'          => $paged,
        'orderby'        => $orderby,
        'order'          => $order
    ];

    if (!empty($atts['taxonomy']) && !empty($atts['term'])) {
        $args['tax_query'] = [[
            'taxonomy' => $atts['taxonomy'],
            'field'    => 'slug',
            'terms'    => $atts['term']
        ]];
    }

    if ($atts['type'] === 'gallery' && $filter_year) {
        $args['meta_key'] = 'gallery_year';
        $args['meta_value'] = $filter_year;
    }

    $query = new WP_Query($args);
    if (!$query->have_posts()) {
        if ($atts['type'] === 'document' && $atts['layout'] === 'table') {
            return '<tr class="no-data-row"><td colspan="3" class="no-data-cell"><div class="no-data-placeholder"><div class="placeholder-icon"><i class="fas fa-folder-open"></i></div><h3>More Updates Coming Soon</h3><p>We are currently organizing new documents for this section. Please check back later.</p></div></td></tr>';
        }
        return '<div class="no-data-placeholder col-12"><div class="placeholder-icon"><i class="fas fa-folder-open"></i></div><h3>More Updates Coming Soon</h3><p>We are currently organizing new content for this section. Please check back later.</p></div>';
    }

    $template_html = '';
    if (!empty($content)) {
        $template_html = do_shortcode(shortcode_unautop($content));
    } elseif (!empty($atts['template'])) {
        $template_file = get_stylesheet_directory() . '/static/partials/' . sanitize_file_name($atts['template']);
        if (file_exists($template_file)) {
            $template_html = file_get_contents($template_file);
        }
    } elseif ($atts['type'] === 'document' && $atts['layout'] === 'table') {
        $template_html = '
        <tr>
            <td style="padding: 20px; font-size: 16px; border-bottom: 1px solid #eee; font-weight: 500; text-align: left;">
                <span class="far fa-file-pdf" style="color: #e53e3e; margin-right: 10px;"></span> {title}
            </td>
            <td style="padding: 20px; font-size: 16px; border-bottom: 1px solid #eee; color: #666; text-align: left;">
                {day} {month} {year}
            </td>
            <td style="padding: 20px; font-size: 16px; border-bottom: 1px solid #eee; text-align: right;">
                <a href="{document_file}" class="theme-btn btn-style-one" style="padding: 6px 15px; font-size: 14px;" target="_blank">
                    <span class="btn-title">View / Download</span>
                </a>
            </td>
        </tr>';
    }

    if (empty($template_html)) {
        return '';
    }

    $output = '';
    while ($query->have_posts()) : $query->the_post();
        $id  = get_the_ID();
        $fallback_img = get_stylesheet_directory_uri() . '/assets/images/resource/news-1.jpg';
        if ($atts['type'] === 'partner') {
            $fallback_img = get_stylesheet_directory_uri() . '/assets/images/resource/default-donor.png';
        }
        $img = get_the_post_thumbnail_url($id, $atts['image_size']) ?: $fallback_img;
        $subtitle = get_post_meta($id, 'voice_subtitle', true);
        $location = get_post_meta($id, 'voice_location', true);
        $award_year = get_post_meta($id, 'award_year', true) ?: get_the_date('Y');
        $subtitle_html = $subtitle ? '<div class="subtitle">'.esc_html($subtitle).'</div>' : '';
        $location_html = $location ? '<div class="location"><i class="fa fa-map-marker-alt"></i> '.esc_html($location).'</div>' : '';
        
        $terms = get_the_terms($id, 'assra_program');
        $program_name = (!empty($terms) && !is_wp_error($terms)) ? $terms[0]->name : 'General';
        $program_slug = (!empty($terms) && !is_wp_error($terms)) ? $terms[0]->slug : 'general';
        $item_year = get_post_meta($id, 'gallery_year', true) ?: get_the_date('Y');

        $doc_file = get_post_meta($id, 'document_file', true) ?: '#';
        $output .= str_replace(
            ['{title}','{link}','{text}','{image}','{day}','{month}','{year}','{item_subtitle_html}','{item_location_html}','{full_text}','{award_year}','{program_name}','{program_slug}','{gallery_year}','{document_file}','{post_id}','{subtitle}','{location}'],
            [esc_html(get_the_title()),get_permalink(),get_the_excerpt(),esc_url($img).'" loading="lazy',get_the_date('d'),get_the_date('M'),$item_year,$subtitle_html,$location_html,get_the_content(),esc_html($award_year),esc_html($program_name),esc_attr($program_slug),esc_html($item_year),esc_url($doc_file),intval($id),esc_html($subtitle),esc_html($location)],
            $template_html
        );
    endwhile;

    if ($atts['pagination'] === 'true' && $query->max_num_pages > 1) {
        $big = 999999999;
        $pagination_html = paginate_links([
            'base'      => str_replace($big, '%#%', esc_url(get_pagenum_link($big))),
            'format'    => '?paged=%#%',
            'current'   => max(1, $paged),
            'total'     => $query->max_num_pages,
            'prev_text' => '<span class="fa fa-angle-left"></span>',
            'next_text' => '<span class="fa fa-angle-right"></span>',
            'type'      => 'list'
        ]);
        if ($pagination_html) {
            $pagination_html = str_replace("<ul class='page-numbers'>", '<ul class="styled-pagination text-center">', $pagination_html);
            $pagination_html = str_replace('page-numbers current', 'active', $pagination_html);
            $pagination_html = str_replace('page-numbers', '', $pagination_html);
            $output .= '<div class="pagination-box col-12 text-center" style="width:100%; margin-top:30px;">' . $pagination_html . '</div>';
        }
    }

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
/**
 * Fail-Secure: Enforce Razorpay Environment Configuration
 */
/*
if (!defined('RZP_KEY_ID') || !defined('RZP_KEY_SECRET') || RZP_KEY_ID === '' || RZP_KEY_SECRET === '') {
    
    // 1. Guard the backend admin dashboard
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error is-dismissible"><p><strong>ASSRA Theme Error:</strong> Razorpay Production Credentials are missing or empty in wp-config.php. Payment gateways are disabled.</p></div>';
    });

    // 2. Short-circuit frontend AJAX payment handlers to prevent silent failures
    $abort_handler = function() {
        wp_send_json_error([
            'message' => 'Payment gateway configuration error. Please contact the administrator.'
        ], 500);
    };

    add_action('wp_ajax_assra_create_order', $abort_handler);
    add_action('wp_ajax_nopriv_assra_create_order', $abort_handler);
    add_action('wp_ajax_assra_verify_payment', $abort_handler);
    add_action('wp_ajax_nopriv_assra_verify_payment', $abort_handler);
}
*/

add_action('init', function () {
    register_post_type('assra_donation', [
        'labels'=>['name'=>'Donations','singular_name'=>'Donation'],'public'=>false,'show_ui'=>true,
        'menu_icon'=>'dashicons-money','supports'=>['title','custom-fields'],'capabilities'=>['create_posts'=>false],
    ]);
});

add_action('wp_ajax_assra_create_order',        'assra_create_order');
add_action('wp_ajax_nopriv_assra_create_order', 'assra_create_order');
function assra_create_order() {
    if (!check_ajax_referer('assra_donation_nonce', 'nonce', false)) {
        wp_send_json_error('Security check failed.', 403);
    }
    $amount = intval($_POST['amount'] ?? 0);
    if (!$amount || $amount < 1 || $amount > 100000) {
        wp_send_json_error('Invalid amount. Must be ₹1–₹1,00,000.', 400);
    }
    $response = wp_remote_post('https://api.razorpay.com/v1/orders', [
        'headers' => ['Authorization' => 'Basic ' . base64_encode(RZP_KEY_ID . ':' . RZP_KEY_SECRET), 'Content-Type' => 'application/json'],
        'body'    => wp_json_encode(['receipt'=>'rcpt_'.time(),'amount'=>$amount*100,'currency'=>'INR','payment_capture'=>1]),
    ]);
    if (is_wp_error($response)) {
        wp_send_json_error('Connection to payment gateway failed. Please try again.', 500);
    }
    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (isset($body['id'])) {
        wp_send_json_success(['order_id'=>$body['id'],'rzp_key'=>RZP_KEY_ID]);
    } else {
        wp_send_json_error('Could not create order. Please contact support.', 500);
    }
}

add_action('wp_ajax_assra_verify_payment',        'assra_verify_payment');
add_action('wp_ajax_nopriv_assra_verify_payment', 'assra_verify_payment');
function assra_verify_payment() {
    if (!check_ajax_referer('assra_donation_nonce', 'nonce', false)) {
        wp_send_json_error('Security check failed.', 403);
    }
    $payment_id = sanitize_text_field($_POST['razorpay_payment_id'] ?? '');
    $order_id   = sanitize_text_field($_POST['razorpay_order_id']   ?? '');
    $signature  = sanitize_text_field($_POST['razorpay_signature']  ?? '');
    if (empty($payment_id) || empty($order_id) || empty($signature)) {
        wp_send_json_error('Missing required payment verification parameters.', 400);
    }
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
        if (!$post_id || is_wp_error($post_id)) {
            wp_send_json_error('Database failure. The transaction log could not be saved.', 500);
        }
        update_post_meta($post_id, 'payment_id', $payment_id);
        update_post_meta($post_id, 'amount', $amount);
        update_post_meta($post_id, 'phone', $phone);
        wp_send_json_success('Payment Verified and Logged');
    } else {
        wp_send_json_error('Signature Verification Failed.', 400);
    }
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
   11. MENU WALKER & DYNAMIC SUBMENUS
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

// Dynamically inject the "Our Work" parent dropdown and its submenus, and keep the "Gallery" submenus intact
add_filter('wp_nav_menu_objects', function ($sorted_menu_items, $args) {
    if ($args->theme_location !== 'main-menu') {
        return $sorted_menu_items;
    }

    // Filter out Vision & Mission item if it exists in the WordPress menu
    $sorted_menu_items = array_filter($sorted_menu_items, function ($item) {
        return !(
            strpos(strtolower($item->url), '/vision-mission') !== false || 
            strtolower($item->title) === 'vision & mission' || 
            strtolower($item->title) === 'vision and mission'
        );
    });

    $new_sorted = [];
    $our_work_inserted = false;
    $gallery_inserted = false;
    $documents_inserted = false;

    global $wp;
    $current_slug = basename(sanitize_file_name($wp->request));

    $pillars = [
        'education-work' => 'Education',
        'elderly-care'   => 'Elderly Care',
        'empowerment'    => 'Empowerment',
        'environment'    => 'Environment'
    ];

    foreach ($sorted_menu_items as $item) {
        $new_sorted[] = $item;

        // 1. Dynamic Gallery dropdown with program categories
        if (!$gallery_inserted && (strpos(strtolower($item->url), '/gallery') !== false || strtolower($item->title) === 'gallery')) {
            $item->classes[] = 'menu-item-has-children';
            $item->classes[] = 'dropdown';

            $terms = get_transient('assra_menu_programs');
            if (false === $terms) {
                $terms = get_terms(['taxonomy' => 'assra_program', 'hide_empty' => false]);
                set_transient('assra_menu_programs', $terms, 12 * HOUR_IN_SECONDS);
            }

            if (!is_wp_error($terms) && !empty($terms)) {
                $sub_idx = 1;
                foreach ($terms as $term) {
                    $term_id = 999800 + $sub_idx;
                    $sub_item = new stdClass();
                    $sub_item->ID = $term_id;
                    $sub_item->db_id = $term_id;
                    $sub_item->title = $term->name;
                    $sub_item->url = home_url('/gallery/?program=' . $term->slug);
                    $sub_item->menu_item_parent = $item->ID;
                    $sub_item->object_id = $term_id;
                    $sub_item->object = 'custom';
                    $sub_item->type = 'custom';
                    $sub_item->type_label = 'Custom Link';
                    $sub_item->classes = ['menu-item', 'menu-item-type-custom', 'menu-item-object-custom'];
                    $sub_item->target = '';
                    $sub_item->attr_title = '';
                    $sub_item->description = '';
                    $sub_item->xfn = '';
                    $sub_item->current = false;
                    $sub_item->current_item_parent = false;
                    $sub_item->current_item_ancestor = false;

                    $active = false;
                    $current_program = isset($_GET['program']) ? sanitize_key($_GET['program']) : '';

                    if (is_post_type_archive('gallery') || $current_slug === 'gallery') {
                        if ($current_program === $term->slug) {
                            $active = true;
                        }
                    } elseif (is_singular('gallery')) {
                        $post_terms = get_the_terms(get_the_ID(), 'assra_program');
                        if (!is_wp_error($post_terms) && !empty($post_terms)) {
                            if ($post_terms[0]->slug === $term->slug) {
                                $active = true;
                            }
                        }
                    }

                    if ($active) {
                        $sub_item->classes[] = 'current-menu-item';
                        $sub_item->current = true;
                        if (!in_array('current-menu-ancestor', $item->classes)) {
                            $item->classes[] = 'current-menu-ancestor';
                        }
                    }

                    $new_sorted[] = $sub_item;
                    $sub_idx++;
                }
            }
            $gallery_inserted = true;
        }

        // 3. Dynamic Documents dropdown with doc_type categories
        if (!$documents_inserted && (strpos(strtolower($item->url), '/documents') !== false || strtolower($item->title) === 'documents')) {
            $item->classes[] = 'menu-item-has-children';
            $item->classes[] = 'dropdown';

            $terms = get_transient('assra_menu_doc_types');
            if (false === $terms) {
                $terms = get_terms([
                    'taxonomy'   => 'doc_type',
                    'hide_empty' => false,
                ]);
                set_transient('assra_menu_doc_types', $terms, 12 * HOUR_IN_SECONDS);
            }

            if (!is_wp_error($terms) && !empty($terms)) {
                $sub_idx = 1;
                foreach ($terms as $term) {
                    $term_id = 999700 + $sub_idx;
                    $sub_item = new stdClass();
                    $sub_item->ID = $term_id;
                    $sub_item->db_id = $term_id;
                    $sub_item->title = $term->name;
                    $sub_item->url = home_url('/documents/?doc_type=' . $term->slug);
                    $sub_item->menu_item_parent = $item->ID;
                    $sub_item->object_id = $term_id;
                    $sub_item->object = 'custom';
                    $sub_item->type = 'custom';
                    $sub_item->type_label = 'Custom Link';
                    $sub_item->classes = ['menu-item', 'menu-item-type-custom', 'menu-item-object-custom'];
                    $sub_item->target = '';
                    $sub_item->attr_title = '';
                    $sub_item->description = '';
                    $sub_item->xfn = '';
                    $sub_item->current = false;
                    $sub_item->current_item_parent = false;
                    $sub_item->current_item_ancestor = false;

                    $active = false;
                    $current_doc_type = isset($_GET['doc_type']) ? sanitize_key($_GET['doc_type']) : '';

                    if (is_page('documents') || $current_slug === 'documents' || is_tax('doc_type')) {
                        if ($current_doc_type === $term->slug) {
                            $active = true;
                        }
                    }

                    if ($active) {
                        $sub_item->classes[] = 'current-menu-item';
                        $sub_item->current = true;
                        if (!in_array('current-menu-ancestor', $item->classes)) {
                            $item->classes[] = 'current-menu-ancestor';
                        }
                    }

                    $new_sorted[] = $sub_item;
                    $sub_idx++;
                }
            }
            $documents_inserted = true;
        }

        // 2. Check for About Us or About ASSRA item to inject "Our Work" dropdown
        if (!$our_work_inserted && (
            strpos(strtolower($item->url), '/about-us') !== false ||
            strpos(strtolower($item->url), '/about-assra') !== false ||
            strtolower($item->title) === 'about us' ||
            strtolower($item->title) === 'about assra'
        )) {
            // Create "Our Work" parent item as a dropdown
            $parent_id = 999900;
            $parent_item = new stdClass();
            $parent_item->ID = $parent_id;
            $parent_item->db_id = $parent_id;
            $parent_item->title = 'Our Work';
            $parent_item->url = '#';
            $parent_item->menu_item_parent = 0;
            $parent_item->object_id = $parent_id;
            $parent_item->object = 'custom';
            $parent_item->type = 'custom';
            $parent_item->type_label = 'Custom Link';
            $parent_item->classes = ['menu-item', 'menu-item-type-custom', 'menu-item-object-custom', 'menu-item-has-children', 'dropdown'];
            $parent_item->target = '';
            $parent_item->attr_title = '';
            $parent_item->description = '';
            $parent_item->xfn = '';
            $parent_item->current = false;
            $parent_item->current_item_parent = false;
            $parent_item->current_item_ancestor = false;

            if (array_key_exists($current_slug, $pillars)) {
                $parent_item->classes[] = 'current-menu-ancestor';
            }

            $new_sorted[] = $parent_item;

            // Create sub-items for each pillar under "Our Work"
            $idx = 1;
            foreach ($pillars as $slug_key => $title_val) {
                $sub_id = $parent_id + $idx;
                $mock_item = new stdClass();
                $mock_item->ID = $sub_id;
                $mock_item->db_id = $sub_id;
                $mock_item->title = $title_val;
                $mock_item->url = home_url('/' . $slug_key . '/');
                $mock_item->menu_item_parent = $parent_id;
                $mock_item->object_id = $sub_id;
                $mock_item->object = 'custom';
                $mock_item->type = 'custom';
                $mock_item->type_label = 'Custom Link';
                $mock_item->classes = ['menu-item', 'menu-item-type-custom', 'menu-item-object-custom'];
                $mock_item->target = '';
                $mock_item->attr_title = '';
                $mock_item->description = '';
                $mock_item->xfn = '';
                $mock_item->current = false;
                $mock_item->current_item_parent = false;
                $mock_item->current_item_ancestor = false;

                if ($current_slug === $slug_key) {
                    $mock_item->classes[] = 'current-menu-item';
                    $mock_item->current = true;
                }

                $new_sorted[] = $mock_item;
                $idx++;
            }
            $our_work_inserted = true;
        }
    }

    // Fallback if "Our Work" wasn't inserted (e.g. neither About nor Home was matched)
    if (!$our_work_inserted) {
        $parent_id = 999900;
        $parent_item = new stdClass();
        $parent_item->ID = $parent_id;
        $parent_item->db_id = $parent_id;
        $parent_item->title = 'Our Work';
        $parent_item->url = '#';
        $parent_item->menu_item_parent = 0;
        $parent_item->object_id = $parent_id;
        $parent_item->object = 'custom';
        $parent_item->type = 'custom';
        $parent_item->type_label = 'Custom Link';
        $parent_item->classes = ['menu-item', 'menu-item-type-custom', 'menu-item-object-custom', 'menu-item-has-children', 'dropdown'];
        $parent_item->target = '';
        $parent_item->attr_title = '';
        $parent_item->description = '';
        $parent_item->xfn = '';
        $parent_item->current = false;
        $parent_item->current_item_parent = false;
        $parent_item->current_item_ancestor = false;

        if (array_key_exists($current_slug, $pillars)) {
            $parent_item->classes[] = 'current-menu-ancestor';
        }

        $new_sorted[] = $parent_item;

        $idx = 1;
        foreach ($pillars as $slug_key => $title_val) {
            $sub_id = $parent_id + $idx;
            $mock_item = new stdClass();
            $mock_item->ID = $sub_id;
            $mock_item->db_id = $sub_id;
            $mock_item->title = $title_val;
            $mock_item->url = home_url('/' . $slug_key . '/');
            $mock_item->menu_item_parent = $parent_id;
            $mock_item->object_id = $sub_id;
            $mock_item->object = 'custom';
            $mock_item->type = 'custom';
            $mock_item->type_label = 'Custom Link';
            $mock_item->classes = ['menu-item', 'menu-item-type-custom', 'menu-item-object-custom'];
            $mock_item->target = '';
            $mock_item->attr_title = '';
            $mock_item->description = '';
            $mock_item->xfn = '';
            $mock_item->current = false;
            $mock_item->current_item_parent = false;
            $mock_item->current_item_ancestor = false;

            if ($current_slug === $slug_key) {
                $mock_item->classes[] = 'current-menu-item';
                $mock_item->current = true;
            }

            $new_sorted[] = $mock_item;
            $idx++;
        }
    }

    return $new_sorted;
}, 10, 2);

// Clear category menu transients on any term changes
add_action('saved_term', function () {
    delete_transient('assra_menu_programs');
    delete_transient('assra_menu_doc_types');
});

// Clear gallery year transients when new gallery images are saved or edited
add_action('save_post_gallery', function () {
    global $wpdb;
    $pillars = $wpdb->get_col("SELECT slug FROM {$wpdb->terms}");
    if (!empty($pillars)) {
        foreach ($pillars as $pillar) {
            delete_transient('assra_gallery_years_' . md5($pillar));
        }
    }
    delete_transient('assra_gallery_years_all');
});