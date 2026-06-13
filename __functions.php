<?php
/*
=====================================================
ASSRA PROFESSIONAL ENGINE – FUNCTIONS.PHP
(Production Version: Security Hardened)
=====================================================

SECURITY FIXES APPLIED:
  [FIX-1]  Razorpay API keys removed — must be defined in wp-config.php
  [FIX-2]  CSRF nonce added to contact form handler
  [FIX-3]  Rate limiting added to contact form (1 per 60s per IP)
  [FIX-4]  CSRF nonce added to AJAX donation endpoints
  [FIX-5]  Donation amount max cap enforced (₹1 – ₹100,000)
  [FIX-6]  REQUEST_URI sanitized before use in SEO meta
  [FIX-7]  wp_head() and wp_footer() properly buffered (were echoing into str_replace)
  [FIX-8]  Router slug sanitized with sanitize_file_name() + basename()
  [FIX-9]  Admin notice output properly escaped
  [FIX-10] save_post hook skips autosave and checks edit capability
  [FIX-11] AJAX handlers localize nonce to JS via wp_enqueue_scripts

REQUIRED: Add these two lines to your wp-config.php (before "That's all, stop editing!"):
  define('RZP_KEY_ID',     'rzp_live_XXXXXXXXXXXX');
  define('RZP_KEY_SECRET', 'YOUR_LIVE_SECRET_HERE');
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
});

/* ==============================
   3. ASSET LOADER
   CSS only — scripts handled manually in _footer.html
   to preserve correct load order and avoid double-loading.
==============================*/
add_action('wp_enqueue_scripts', function () {
    $theme_uri = get_stylesheet_directory_uri();
    $theme_dir = get_stylesheet_directory();

    // CSS only
    wp_enqueue_style('assra-style', $theme_uri . '/assets/css/style.css', [], filemtime($theme_dir . '/assets/css/style.css'));

    // Deregister WordPress built-in jQuery so it doesn't conflict
    // with the jQuery loaded manually in _footer.html
    wp_deregister_script('jquery');
    wp_deregister_script('jquery-core');
    wp_deregister_script('jquery-migrate');
});


// [FIX-11] Inject AJAX config + both nonces inline into <head>
// Works even if script.js is missing, and works inside static .html pages
// because wp_head() output is captured by utw_load_static_page()
add_action('wp_head', function () {
    echo '<script>var assra_ajax = {'
       . '"ajax_url":"'       . esc_js(admin_url('admin-ajax.php'))              . '",'
       . '"donation_nonce":"' . esc_js(wp_create_nonce('assra_donation_nonce'))  . '",'
       . '"contact_nonce":"'  . esc_js(wp_create_nonce('assra_contact_nonce'))   . '"'
       . '};</script>' . "\n";
}, 1); // Priority 1 — fires before any other scripts

// Scripts are loaded in footer (true) which is already good for performance.
// Defer is NOT used — it causes race conditions between jQuery and script.js
// which breaks mobile menu, dropdowns, and carousel initialisers.

/* ==============================
   4. FORM HANDLER
   [FIX-2] Added nonce verification
   [FIX-3] Added rate limiting (1 submission per 60s per IP)
==============================*/
add_action('admin_post_assra_contact_form', 'assra_handle_form_submit');
add_action('admin_post_nopriv_assra_contact_form', 'assra_handle_form_submit');

function assra_handle_form_submit() {
    // [FIX-2] Verify CSRF nonce — token is injected by JS from assra_ajax.contact_nonce
    $token = $_POST['assra_contact_token'] ?? '';
    if (empty($token) || !wp_verify_nonce($token, 'assra_contact_nonce')) {
        wp_die('Security check failed. Please go back and try again.');
    }

    if (!isset($_POST['submit-form'])) return;

    // [FIX-3] Rate limiting: 1 submission per 60 seconds per IP
    $ip_key = 'assra_contact_limit_' . md5($_SERVER['REMOTE_ADDR']);
    if (get_transient($ip_key)) {
        wp_die('You are submitting too quickly. Please wait a moment and try again.');
    }
    set_transient($ip_key, 1, 60);

    $name    = sanitize_text_field($_POST['name'] ?? '');
    $email   = sanitize_email($_POST['email'] ?? '');
    $phone   = sanitize_text_field($_POST['phone'] ?? '');
    $subject = sanitize_text_field($_POST['subject'] ?? '');
    $message = sanitize_textarea_field($_POST['message'] ?? '');

    // Basic validation
    if (empty($name) || empty($email) || !is_email($email)) {
        wp_die('Invalid form data. Please go back and fill in all required fields.');
    }

    $post_title   = $name . ' - ' . $subject;
    $post_content = "<strong>From:</strong> " . esc_html($name) . "\n"
                  . "<strong>Email:</strong> " . esc_html($email) . "\n"
                  . "<strong>Phone:</strong> " . esc_html($phone) . "\n\n"
                  . "<strong>Message:</strong>\n" . esc_html($message);

    wp_insert_post([
        'post_title'   => $post_title,
        'post_content' => $post_content,
        'post_type'    => 'assra_inquiry',
        'post_status'  => 'publish',
    ]);

    wp_redirect(home_url('/contact/?success=1'));
    exit;
}

/*
  IMPORTANT — Add this nonce field inside your contact HTML form:
  <?php wp_nonce_field('assra_contact_nonce', 'assra_contact_token'); ?>
*/

/* ==========================================================================
   5. STATIC PAGE ENGINE (Optimized for 90+ SEO & Performance)
   [FIX-6]  REQUEST_URI sanitized before use
   [FIX-7]  wp_head() and wp_footer() properly output-buffered
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
        ob_start();
        include($path);
        return ob_get_clean();
    };

    $head_html = $capture($header_file);

    // --- SMART SEO ENGINE ---
    $seo_title = get_bloginfo('name') . " | Non-Profit Organization";
    $seo_desc  = "ASSRA is a non-profit NGO working for Education, Empowerment, Elderly Care, and Environmental initiatives.";
    $seo_img   = $theme_uri . '/assets/images/logo.png';

    // [FIX-6] Sanitize REQUEST_URI before passing to home_url()
    $sanitized_uri = sanitize_url($_SERVER['REQUEST_URI']);
    $current_url   = home_url($sanitized_uri);

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

    // [FIX-7] Buffer wp_head() output correctly (it echoes, not returns)
    ob_start();
    wp_head();
    $wp_head_output = ob_get_clean();

    // Strip old title and inject new meta block + wp_head
    $head_html = preg_replace('/<title>.*?<\/title>/i', '', $head_html);
    if (strpos($head_html, '</head>') !== false) {
        $head_html = str_replace('</head>', $meta_block . $wp_head_output . '</head>', $head_html);
    } else {
        $head_html .= $meta_block . $wp_head_output;
    }

    $body_html = file_get_contents($page_file);

    // --- DATA INJECTION (Singular Content) ---
    if (is_singular()) {
        $id    = get_the_ID();
        $p_obj = get_post($id);

        $body_html = str_replace('{page_title}', esc_html($p_obj->post_title), $body_html);
        $final_content = apply_filters('the_content', $p_obj->post_content);
        $body_html = str_replace('{content}', $final_content ?: "<p>No description available.</p>", $body_html);

        $feat_img  = get_the_post_thumbnail_url($id, 'full') ?: $theme_uri . '/assets/images/background/bg-banner-1.jpg';
        $body_html = str_replace('{page_image}', esc_url($feat_img), $body_html);
        $body_html = str_replace(
            ['{day}', '{month}', '{year}'],
            [get_the_date('d', $id), get_the_date('M', $id), get_the_date('Y', $id)],
            $body_html
        );

        $body_html = str_replace('{time}',     get_post_meta($id, 'event_time', true) ?: '10:00 AM - 5:00 PM', $body_html);
        $body_html = str_replace('{location}', get_post_meta($id, 'event_location', true) ?: 'Odisha, India',   $body_html);
    }

    $foot_html = $capture($footer_file);

    // [FIX-7] Buffer wp_footer() output correctly (it echoes, not returns)
    ob_start();
    wp_footer();
    $wp_footer_output = ob_get_clean();

    if (strpos($foot_html, '</body>') !== false) {
        $foot_html = str_replace('</body>', $wp_footer_output . '</body>', $foot_html);
    } else {
        $foot_html .= $wp_footer_output;
    }

    $html = $head_html . $body_html . $foot_html;

    // --- PATH FIXER (Optimized for performance) ---
    $html = str_replace('__ADMIN_POST_URL__', admin_url('admin-post.php'), $html);
    $html = preg_replace('/(href|src)=["\']\s*(\.{0,2}\/?)css\//',           '$1="' . $theme_uri . '/assets/css/',    $html);
    $html = preg_replace('/(href|src)=["\']\s*(\.{0,2}\/?)js\//',            '$1="' . $theme_uri . '/assets/js/',     $html);
    $html = preg_replace('/(href|src|srcset|data-src)=["\']\s*(\.{0,2}\/?)images\//', '$1="' . $theme_uri . '/assets/images/', $html);
    $html = preg_replace('/(href|src)=["\']\s*(\.{0,2}\/?)assets\//',        '$1="' . $theme_uri . '/assets/',        $html);
    $html = preg_replace('/url\(\s*["\']?(\.{0,2}\/?)images\//',             'url(' . $theme_uri . '/assets/images/', $html);
    $html = preg_replace('/url\(\s*["\']?(\.{0,2}\/?)fonts\//',              'url(' . $theme_uri . '/assets/fonts/',  $html);
    $html = preg_replace('/href=["\']\/(?!\/)/',                              'href="' . home_url('/'),                $html);
    $html = str_replace(['{theme_url}', '{site_url}'], [$theme_uri, site_url()], $html);

    echo do_shortcode($html);
    return true;
}

/* ==============================
   6. UNIVERSAL ROUTER
   [FIX-8] Slug sanitized to prevent path traversal
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
        // [FIX-8] Sanitize the raw URL request to prevent path traversal attacks
        $raw_slug = $wp->request;
        $slug     = basename(sanitize_file_name($raw_slug));
    }

    if (file_exists(get_stylesheet_directory() . "/static/pages/{$slug}.html")) {
        utw_load_static_page($slug);
        exit;
    }
});

/* ==============================
   7. UNIVERSAL TOOLKIT (Performance Optimized)
==============================*/

// A. Filter Buttons
add_shortcode('year_filter', function () {
    global $wpdb, $post;

    $current_pillar = '';
    if (is_page()) {
        $current_slug    = $post->post_name;
        $program_pillars = ['education', 'empowerment', 'elderly-care', 'environment', 'health'];
        if (in_array($current_slug, $program_pillars)) {
            $current_pillar = $current_slug;
        }
    }

    $query = "
        SELECT DISTINCT pm.meta_value
        FROM {$wpdb->postmeta} pm
        INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
        INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
        INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
        INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
        WHERE pm.meta_key = 'gallery_year'
        AND pm.meta_value != ''
        AND p.post_type = 'gallery'
        AND p.post_status = 'publish'
        AND t.slug = %s
        ORDER BY pm.meta_value DESC
    ";

    $years = $wpdb->get_col($wpdb->prepare($query, $current_pillar));

    if (empty($years)) return '';

    $selected = isset($_GET['gallery_year']) ? intval($_GET['gallery_year']) : $years[0];

    ob_start();
    ?>
    <div class="gallery-filter-bar" style="text-align:center; margin-bottom: 30px;">
        <?php foreach ($years as $year) : ?>
            <a href="?gallery_year=<?php echo esc_attr($year); ?>"
               class="filter-btn <?php echo ($selected == $year) ? 'active' : ''; ?>">
               <?php echo esc_html($year); ?>
            </a>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
});

// B. Loop Handler: Auto-Detects Page Context + Native Lazy Loading
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
    ], $atts);

    // --- 1. AUTO-DETECT PILLAR ---
    $current_pillar = $atts['term'];
    if (empty($atts['taxonomy']) && empty($atts['term']) && is_page()) {
        $current_slug    = $post->post_name;
        $program_pillars = ['education', 'empowerment', 'elderly-care', 'environment', 'health'];
        if (in_array($current_slug, $program_pillars)) {
            $atts['taxonomy'] = 'assra_program';
            $atts['term']     = $current_slug;
            $current_pillar   = $current_slug;
        }
    }

    // --- 2. SYNC DEFAULT YEAR (Only runs for Galleries) ---
    $filter_year = null;
    if ($atts['type'] === 'gallery') {
        $latest_year_in_db = $wpdb->get_var($wpdb->prepare("
            SELECT pm.meta_value
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
            INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
            WHERE pm.meta_key = 'gallery_year'
            AND p.post_status = 'publish'
            AND t.slug = %s
            ORDER BY CAST(pm.meta_value AS UNSIGNED) DESC LIMIT 1
        ", $current_pillar));

        $filter_year = isset($_GET['gallery_year']) ? intval($_GET['gallery_year']) : ($latest_year_in_db ?: date('Y'));
    }

    // --- 3. CUSTOM ORDERING LOGIC ---
    $orderby = 'date';
    $order   = 'DESC';

    if (in_array($atts['type'], ['board_member', 'board', 'partner'])) {
        $orderby = 'menu_order';
        $order   = 'ASC';
    }

    // --- 4. CONSOLIDATED ARGS ---
    $args = [
        'post_type'      => $atts['type'],
        'posts_per_page' => $atts['count'],
        'post_status'    => 'publish',
        'paged'          => (get_query_var('paged')) ? get_query_var('paged') : 1,
        'orderby'        => $orderby,
        'order'          => $order,
    ];

    if (!empty($atts['taxonomy']) && !empty($atts['term'])) {
        $args['tax_query'] = [['taxonomy' => $atts['taxonomy'], 'field' => 'slug', 'terms' => $atts['term']]];
    }

    if ($atts['type'] === 'gallery' && $filter_year) {
        $args['meta_key']   = 'gallery_year';
        $args['meta_value'] = $filter_year;
    }

    $query = new WP_Query($args);

    // --- 5. NO DATA UI ---
    if (!$query->have_posts()) {
        return '<div class="no-data-placeholder col-12" style="text-align: center; padding: 60px 20px; background: #fafafa; border: 2px dashed #e0e0e0; border-radius: 10px; margin-bottom: 30px; width: 100%;">
                    <div style="font-size: 45px; color: #28a745; margin-bottom: 15px;"><i class="fas fa-folder-open"></i></div>
                    <h3 style="font-size: 24px; color: #1a365d; margin-bottom: 10px;">More Updates Coming Soon</h3>
                    <p style="color: #666; font-size: 16px; max-width: 500px; margin: 0 auto;">We are currently organizing new content and activities for this section. Please check back later.</p>
                </div>';
    }

    // --- 6. RENDER THE LOOP ---
    $template_html = do_shortcode(shortcode_unautop($content));
    $output        = '';

    while ($query->have_posts()) : $query->the_post();
        $id  = get_the_ID();
        $img = get_the_post_thumbnail_url($id, $atts['image_size']) ?: get_stylesheet_directory_uri() . '/assets/images/resource/news-1.jpg';

        $output .= str_replace(
            ['{title}', '{link}', '{text}', '{image}', '{day}', '{month}', '{year}'],
            [esc_html(get_the_title()), get_permalink(), get_the_excerpt(), esc_url($img) . '" loading="lazy', get_the_date('d'), get_the_date('M'), $filter_year],
            $template_html
        );
    endwhile;

    wp_reset_postdata();
    return $output;
}

/* ==============================
   8. ADMIN FIELDS (Documents & Gallery Year)
   [FIX-10] save_post skips autosave + checks user capability
==============================*/
add_action('add_meta_boxes', function () {
    add_meta_box('doc_settings_box', 'Document Settings', 'assra_render_doc_settings', 'document', 'normal', 'high');
    add_meta_box('gallery_year_box', 'Event Year', 'assra_render_gallery_year', 'gallery', 'side', 'high');
});

function assra_render_doc_settings($post) {
    $value = get_post_meta($post->ID, 'document_file', true);
    wp_nonce_field('save_meta_data', 'assra_nonce');
    ?>
    <p>
        <label><strong>PDF / File URL:</strong></label><br>
        <input type="text" name="document_file" value="<?php echo esc_attr($value); ?>" style="width:100%; padding:8px;">
    </p>
    <?php
}

function assra_render_gallery_year($post) {
    $value = get_post_meta($post->ID, 'gallery_year', true);
    if (empty($value)) $value = date('Y');
    wp_nonce_field('save_meta_data', 'assra_nonce');
    ?>
    <p>
        <label><strong>Year of Event:</strong></label><br>
        <input type="number" name="gallery_year" value="<?php echo esc_attr($value); ?>" style="width:100%; padding:5px;">
        <br><small>Type the year (e.g. 2018) to group this photo.</small>
    </p>
    <?php
}

add_action('save_post', function ($post_id) {
    // [FIX-10] Skip autosaves
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

    // [FIX-10] Only allow users who can actually edit this post
    if (!current_user_can('edit_post', $post_id)) return;

    // Verify nonce
    if (!isset($_POST['assra_nonce']) || !wp_verify_nonce($_POST['assra_nonce'], 'save_meta_data')) return;

    if (isset($_POST['document_file'])) {
        update_post_meta($post_id, 'document_file', sanitize_text_field($_POST['document_file']));
    }

    if (isset($_POST['gallery_year'])) {
        $year = intval($_POST['gallery_year']);
        if ($year >= 1900 && $year <= 2100) {
            update_post_meta($post_id, 'gallery_year', $year);
        }
    }
});

/* ==============================
   9. RAZORPAY SECURE DONATION ENGINE
   [FIX-1]  Keys REMOVED from code — must live in wp-config.php
   [FIX-4]  CSRF nonce verification added to both AJAX handlers
   [FIX-5]  Amount capped at ₹1 – ₹100,000
==============================*/

// [FIX-1] Keys are NOT defined here anymore.
// Add these two lines to your wp-config.php BEFORE "That's all, stop editing!":
//   define('RZP_KEY_ID',     'rzp_live_XXXXXXXXXXXX');
//   define('RZP_KEY_SECRET', 'YOUR_LIVE_SECRET_HERE');

// Safety check: warn developer loudly if keys are missing
if (!defined('RZP_KEY_ID') || !defined('RZP_KEY_SECRET')) {
    add_action('admin_notices', function () {
        echo '<div class="notice notice-error"><p><strong>Razorpay Setup Required:</strong> Please define <code>RZP_KEY_ID</code> and <code>RZP_KEY_SECRET</code> in your <code>wp-config.php</code> file.</p></div>';
    });
}

add_action('init', function () {
    register_post_type('assra_donation', [
        'labels'       => ['name' => 'Donations', 'singular_name' => 'Donation'],
        'public'       => false,
        'show_ui'      => true,
        'menu_icon'    => 'dashicons-money',
        'supports'     => ['title', 'custom-fields'],
        'capabilities' => ['create_posts' => false],
    ]);
});

add_action('wp_ajax_assra_create_order', 'assra_create_order');
add_action('wp_ajax_nopriv_assra_create_order', 'assra_create_order');

function assra_create_order() {
    // [FIX-4] Verify CSRF nonce
    if (!check_ajax_referer('assra_donation_nonce', 'nonce', false)) {
        wp_send_json_error('Security check failed.');
    }

    // [FIX-5] Validate amount: must be between ₹1 and ₹1,00,000
    $amount = intval($_POST['amount'] ?? 0);
    if (!$amount || $amount < 1 || $amount > 100000) {
        wp_send_json_error('Invalid donation amount. Must be between ₹1 and ₹1,00,000.');
    }

    if (!defined('RZP_KEY_ID') || !defined('RZP_KEY_SECRET')) {
        wp_send_json_error('Payment gateway is not configured.');
    }

    $orderData = [
        'receipt'         => 'rcpt_' . time(),
        'amount'          => $amount * 100,
        'currency'        => 'INR',
        'payment_capture' => 1,
    ];

    $response = wp_remote_post('https://api.razorpay.com/v1/orders', [
        'headers' => [
            'Authorization' => 'Basic ' . base64_encode(RZP_KEY_ID . ':' . RZP_KEY_SECRET),
            'Content-Type'  => 'application/json',
        ],
        'body' => wp_json_encode($orderData),
    ]);

    if (is_wp_error($response)) {
        wp_send_json_error('Connection error. Please try again.');
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);

    if (isset($body['id'])) {
        // Send the public key alongside order_id so donate.html never needs it hardcoded
        wp_send_json_success([
            'order_id' => $body['id'],
            'rzp_key'  => RZP_KEY_ID,   // Public key only — secret never leaves server
        ]);
    } else {
        wp_send_json_error('Could not create order. Please try again.');
    }
}

add_action('wp_ajax_assra_verify_payment', 'assra_verify_payment');
add_action('wp_ajax_nopriv_assra_verify_payment', 'assra_verify_payment');

function assra_verify_payment() {
    // [FIX-4] Verify CSRF nonce
    if (!check_ajax_referer('assra_donation_nonce', 'nonce', false)) {
        wp_send_json_error('Security check failed.');
    }

    if (!defined('RZP_KEY_SECRET')) {
        wp_send_json_error('Payment gateway is not configured.');
    }

    $payment_id = sanitize_text_field($_POST['razorpay_payment_id'] ?? '');
    $order_id   = sanitize_text_field($_POST['razorpay_order_id'] ?? '');
    $signature  = sanitize_text_field($_POST['razorpay_signature'] ?? '');

    if (empty($payment_id) || empty($order_id) || empty($signature)) {
        wp_send_json_error('Missing payment data.');
    }

    $generated_signature = hash_hmac('sha256', $order_id . '|' . $payment_id, RZP_KEY_SECRET);

    // Use hash_equals() to prevent timing attacks
    if (hash_equals($generated_signature, $signature)) {
        $donor_name = sanitize_text_field($_POST['donor_name'] ?? '');
        $amount     = intval($_POST['amount'] ?? 0);
        $phone      = sanitize_text_field($_POST['donor_phone'] ?? '');
        $purpose    = sanitize_text_field($_POST['purpose'] ?? '');

        $post_id = wp_insert_post([
            'post_title'   => esc_html($donor_name) . ' - ₹' . $amount,
            'post_type'    => 'assra_donation',
            'post_status'  => 'publish',
            'post_content' => "<strong>Payment ID:</strong> " . esc_html($payment_id) . "\n"
                            . "<strong>Order ID:</strong> " . esc_html($order_id) . "\n"
                            . "<strong>Phone:</strong> " . esc_html($phone) . "\n"
                            . "<strong>Purpose:</strong> " . esc_html($purpose),
        ]);

        if ($post_id) {
            update_post_meta($post_id, 'payment_id', $payment_id);
            update_post_meta($post_id, 'amount', $amount);
            update_post_meta($post_id, 'phone', $phone);
        }

        wp_send_json_success('Payment Verified and Logged');
    } else {
        wp_send_json_error('Signature Verification Failed');
    }
}

/* ==========================================================================
   10. ADMIN UTILITIES: 100% Automatic Media Coverage Creator
   [FIX-9] Admin notice output properly escaped
========================================================================== */

add_filter('bulk_actions-upload', function ($bulk_actions) {
    $bulk_actions['make_media_auto'] = 'Auto-Create Media Clips (Uses Image Names)';
    return $bulk_actions;
});

add_filter('handle_bulk_actions-upload', function ($redirect_url, $action, $post_ids) {
    if ($action !== 'make_media_auto') {
        return $redirect_url;
    }

    $count = 0;

    foreach ($post_ids as $attachment_id) {
        if (strpos(get_post_mime_type($attachment_id), 'image') === false) continue;

        $image_title = get_the_title($attachment_id);
        $clean_title = ucwords(str_replace(['-', '_'], ' ', $image_title));

        $new_post_id = wp_insert_post([
            'post_title'  => $clean_title,
            'post_type'   => 'media_clip',
            'post_status' => 'publish',
        ]);

        if ($new_post_id) {
            set_post_thumbnail($new_post_id, $attachment_id);
            wp_update_post(['ID' => $attachment_id, 'post_parent' => $new_post_id]);

            $year = date('Y');
            if (preg_match('/\b(201\d|202\d)\b/', $clean_title, $matches)) {
                $year = $matches[1];
            }
            update_post_meta($new_post_id, 'gallery_year', $year);

            $count++;
        }
    }

    $redirect_url = add_query_arg('media_converted_auto', $count, $redirect_url);
    return $redirect_url;
}, 10, 3);

add_action('admin_notices', function () {
    // [FIX-9] Properly escape admin notice output
    if (!empty($_REQUEST['media_converted_auto'])) {
        $count = intval($_REQUEST['media_converted_auto']);
        echo '<div class="notice notice-success is-dismissible"><p>'
           . '<strong>Automation Complete!</strong> Successfully generated <strong>'
           . esc_html($count)
           . '</strong> Media Coverage posts using their original filenames.</p></div>';
    }
});

/* ==============================
   11. MENU WALKER
==============================*/
class ASSRA_Menu_Walker extends Walker_Nav_Menu {

    public function start_lvl(&$output, $depth = 0, $args = null) {
        $output .= '<ul>';
    }

    public function start_el(&$output, $item, $depth = 0, $args = null, $id = 0) {
        $classes = empty($item->classes) ? [] : (array) $item->classes;

        if (in_array('menu-item-has-children', $classes)) {
            $classes[] = 'dropdown';
        }

        $class_names = implode(' ', array_filter($classes));

        $output .= '<li class="' . esc_attr($class_names) . '">';
        $output .= '<a href="' . esc_url($item->url) . '">';
        $output .= esc_html($item->title);
        $output .= '</a>';

        if (in_array('menu-item-has-children', $classes)) {
            $output .= '<div class="dropdown-btn"><span class="fa fa-angle-down"></span></div>';
        }
    }
}
