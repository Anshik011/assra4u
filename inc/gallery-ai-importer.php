<?php
/**
 * ASSRA AI Bulk Gallery Importer
 * Author: Antigravity AI
 * Description: AI-powered bulk gallery import engine using Gemini AI Vision.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/* ==========================================================================
   1. ADMIN MENUS & ENQUEUING
   ========================================================================== */

add_action('admin_menu', function () {
    add_submenu_page(
        'edit.php?post_type=gallery',
        'AI Bulk Import',
        'AI Bulk Import',
        'manage_options',
        'assra-gallery-ai-import',
        'assra_gallery_ai_import_page'
    );
});

add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'gallery_page_assra-gallery-ai-import') {
        return;
    }
    
    $theme_uri = get_stylesheet_directory_uri();
    $theme_dir = get_stylesheet_directory();
    
    wp_enqueue_style('assra-admin-ai-importer', $theme_uri . '/assets/css/admin-ai-importer.css', [], filemtime($theme_dir . '/assets/css/admin-ai-importer.css'));
    
    wp_enqueue_script('assra-admin-ai-importer-js', $theme_uri . '/assets/js/admin-ai-importer.js', ['jquery'], filemtime($theme_dir . '/assets/js/admin-ai-importer.js'), true);
    
    // Inject localization data
    wp_localize_script('assra-admin-ai-importer-js', 'assra_importer_nonce', wp_create_nonce('assra_ai_import_nonce'));
});

/* ==========================================================================
   2. DASHBOARD VIEW RENDERING
   ========================================================================== */

function assra_gallery_ai_import_page() {
    // Get stored credentials and taxonomy terms
    $api_key = get_option('assra_gemini_api_key', '');
    $categories = get_terms(array(
        'taxonomy'   => 'assra_program',
        'hide_empty' => false,
    ));
    $current_year = date('Y');
    ?>
    <div class="wrap assra-importer-container">
        <!-- Header -->
        <div class="assra-importer-header">
            <h1><i class="dashicons dashicons-images-alt2"></i> AI Bulk Gallery Importer</h1>
            <p>Upload hundreds or thousands of photographs in bulk. The system uses Gemini AI Vision to automatically analyze each image, generate descriptive SEO-optimized metadata (Title, Alt Text, Caption, Description, and Tags), rename filenames for search engine friendliness, and create standard Media Library and Gallery posts without duplicates.</p>
        </div>

        <div class="assra-importer-grid">
            <!-- Sidebar: Settings -->
            <div class="assra-importer-card">
                <h3>Import Settings</h3>
                
                <!-- Gemini API Key -->
                <div class="assra-form-group">
                    <label for="assra-api-key">Gemini API Key</label>
                    <input type="password" id="assra-api-key" value="<?php echo esc_attr($api_key); ?>" class="assra-form-input" placeholder="AI Studio API Key (AIZA...)">
                    <p class="assra-form-helper">Get a free API key from <a href="https://aistudio.google.com/" target="_blank">Google AI Studio</a>.</p>
                </div>

                <!-- Program Category -->
                <div class="assra-form-group">
                    <label for="assra-category">Default Category (Pillar)</label>
                    <select id="assra-category" class="assra-form-select">
                        <option value="auto_detect" selected>Auto-detect Category (Using AI)</option>
                        <option value="">-- No Category --</option>
                        <?php if (!is_wp_error($categories) && !empty($categories)) : ?>
                            <?php foreach ($categories as $cat) : ?>
                                <option value="<?php echo esc_attr($cat->slug); ?>"><?php echo esc_html($cat->name); ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <!-- Event Year -->
                <div class="assra-form-group">
                    <label for="assra-year">Gallery Event Year</label>
                    <input type="number" id="assra-year" value="<?php echo esc_attr($current_year); ?>" class="assra-form-input" min="1900" max="2100">
                </div>
            </div>

            <!-- Main Content Area -->
            <div>
                <!-- Drag & Drop Uploader -->
                <div class="assra-dropzone" id="assra-dropzone">
                    <div class="assra-dropzone-icon">
                        <i class="dashicons dashicons-cloud-upload"></i>
                    </div>
                    <h4>Drag & Drop image files here</h4>
                    <p>or click to browse from your computer (Multiple files supported)</p>
                </div>
                <input type="file" id="assra-file-input" class="assra-hidden" multiple accept="image/*">

                <!-- Progress Tracker -->
                <div class="assra-progress-wrapper" id="assra-progress-wrapper">
                    <div class="assra-progress-header">
                        <span>Bulk Import Progress</span>
                        <span id="assra-progress-text">0%</span>
                    </div>
                    <div class="assra-progress-bar-bg">
                        <div class="assra-progress-bar-fill" id="assra-progress-fill"></div>
                    </div>
                    <div class="assra-progress-stats">
                        <div class="assra-progress-stat">Processed: <span id="assra-stat-processed">0 / 0</span></div>
                        <div class="assra-progress-stat">Completed: <span id="assra-stat-completed">0</span></div>
                        <div class="assra-progress-stat">Failed: <span id="assra-stat-failed">0</span></div>
                        <div class="assra-progress-stat">Skipped (Duplicates): <span id="assra-stat-skipped">0</span></div>
                    </div>
                </div>

                <!-- Control Buttons -->
                <div class="assra-btn-group">
                    <button class="assra-btn assra-btn-primary" id="assra-btn-start" disabled>
                        <i class="dashicons dashicons-controls-play"></i> <span>Start Import</span>
                    </button>
                    <button class="assra-btn assra-btn-secondary" id="assra-btn-pause" disabled>
                        <i class="dashicons dashicons-controls-pause"></i> Pause
                    </button>
                    <button class="assra-btn assra-btn-secondary" id="assra-btn-retry" disabled>
                        <i class="dashicons dashicons-update"></i> Retry Failed
                    </button>
                    <button class="assra-btn assra-btn-danger" id="assra-btn-cancel" disabled>
                        <i class="dashicons dashicons-no"></i> Cancel Queue
                    </button>
                </div>

                <!-- Queue Display -->
                <div class="assra-queue-card">
                    <table class="assra-queue-table">
                        <thead>
                            <tr>
                                <th style="width: 70px;">Preview</th>
                                <th>File Name</th>
                                <th style="width: 120px;">Status</th>
                                <th>Details / Results</th>
                            </tr>
                        </thead>
                        <tbody id="assra-queue-body">
                            <tr id="assra-no-files">
                                <td colspan="4">
                                    <div class="assra-no-files">
                                        <i class="dashicons dashicons-images-alt"></i>
                                        No files selected yet. Drag images above to build the import queue.
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php
}

/* ==========================================================================
   3. AJAX ENDPOINTS
   ========================================================================== */

// Callback to save API Key asynchronously
add_action('wp_ajax_assra_save_gemini_key', function() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized capacity', 403);
    }
    check_ajax_referer('assra_ai_import_nonce', 'security');

    $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';
    update_option('assra_gemini_api_key', $api_key);
    wp_send_json_success('API Key saved.');
});

// Main callback to process a single image in the batch
add_action('wp_ajax_assra_ai_import_single', function() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized capacity', 403);
    }
    check_ajax_referer('assra_ai_import_nonce', 'security');

    if (empty($_FILES['image'])) {
        wp_send_json_error('No file was uploaded.');
    }

    $uploaded_file = $_FILES['image'];
    $file_path = $uploaded_file['tmp_name'];

    // 1. Calculate File Hash for Duplicate Check
    $file_hash = md5_file($file_path);
    if (!$file_hash) {
        wp_send_json_error('Could not compute file hash.');
    }

    // Check if any attachment already has this hash meta key
    $duplicate_check = new WP_Query(array(
        'post_type'      => 'attachment',
        'post_status'    => 'any',
        'posts_per_page' => 1,
        'meta_query'     => array(
            array(
                'key'     => '_attachment_file_hash',
                'value'   => $file_hash,
                'compare' => '='
            )
        )
    ));

    if ($duplicate_check->have_posts()) {
        $duplicate_attachment = $duplicate_check->posts[0];
        wp_send_json_success(array(
            'skipped' => true,
            'message' => 'Skipped (Duplicate of media #' . $duplicate_attachment->ID . ')'
        ));
    }

    // 2. Fetch API Key and Verify
    $api_key = get_option('assra_gemini_api_key', '');
    if (empty($api_key)) {
        wp_send_json_error('Gemini API Key is not configured. Please configure it in settings.');
    }

    // 3. Call Gemini AI Vision API
    $image_data = base64_encode(file_get_contents($file_path));
    $mime_type = $uploaded_file['type'];

    $prompt = "Analyze this photograph for a non-profit NGO website named ASSRA. Generate descriptive metadata. The title and descriptions must look professional and human-written, avoiding generic words. Return the metadata in structured JSON format according to the schema.";

    $request_body = array(
        'contents' => array(
            array(
                'parts' => array(
                    array(
                        'text' => $prompt
                    ),
                    array(
                        'inlineData' => array(
                            'mimeType' => $mime_type,
                            'data'     => $image_data
                        )
                    )
                )
            )
        ),
        'generationConfig' => array(
            'responseMimeType' => 'application/json',
            'responseSchema'   => array(
                'type'       => 'OBJECT',
                'properties' => array(
                    'title'         => array('type' => 'STRING', 'description' => 'A descriptive, human-quality title suitable for the NGO gallery (e.g. "Underprivileged Children Receiving Remedial Education")'),
                    'alt_text'      => array('type' => 'STRING', 'description' => 'SEO friendly descriptive alt text for accessibility'),
                    'caption'       => array('type' => 'STRING', 'description' => 'Brief caption summarizing the scene'),
                    'description'   => array('type' => 'STRING', 'description' => 'Detailed description of the activity/scene shown in the image'),
                    'seo_filename'  => array('type' => 'STRING', 'description' => 'SEO friendly slug filename (lowercase, hyphenated, no spaces, no extension, e.g. "underprivileged-children-remedial-education")'),
                    'auto_category' => array(
                        'type'        => 'STRING',
                        'description' => 'Choose the most appropriate category slug for this image from: "education-work", "elderly-care", "empowerment", "environment". If the image does not fit any of these, return an empty string.'
                    ),
                    'tags'          => array(
                        'type'        => 'ARRAY',
                        'items'       => array('type' => 'STRING'),
                        'description' => 'List of relevant keywords'
                    )
                ),
                'required' => array('title', 'alt_text', 'caption', 'description', 'seo_filename', 'auto_category', 'tags')
            )
        )
    );

    // Call API using wp_remote_post
    $response = wp_remote_post(
        'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . $api_key,
        array(
            'headers' => array('Content-Type' => 'application/json'),
            'body'    => wp_json_encode($request_body),
            'timeout' => 45
        )
    );

    if (is_wp_error($response)) {
        wp_send_json_error('Gemini API call failed: ' . $response->get_error_message());
    }

    $response_code = wp_remote_retrieve_response_code($response);
    if ($response_code !== 200) {
        wp_send_json_error('Gemini API error (Status ' . $response_code . '): ' . wp_remote_retrieve_body($response));
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (empty($body['candidates'][0]['content']['parts'][0]['text'])) {
        wp_send_json_error('Invalid response content from Gemini API.');
    }

    $ai_data = json_decode($body['candidates'][0]['content']['parts'][0]['text'], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        wp_send_json_error('Failed to parse Gemini metadata JSON: ' . json_last_error_msg());
    }

    // 4. Rename File to SEO Filename & sideload into Media Library
    $seo_slug = sanitize_title($ai_data['seo_filename']);
    $orig_ext = pathinfo($uploaded_file['name'], PATHINFO_EXTENSION);
    $new_filename = $seo_slug . '.' . strtolower($orig_ext);

    $tmp_dir = get_temp_dir();
    $renamed_temp_path = $tmp_dir . '/' . $new_filename;

    if (!copy($file_path, $renamed_temp_path)) {
        wp_send_json_error('Failed to prepare renamed temp file on disk.');
    }

    // Required files for programmatic sideloading
    require_once(ABSPATH . 'wp-content/themes/assra/inc/gallery-ai-importer.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');

    $file_array = array(
        'name'     => $new_filename,
        'tmp_name' => $renamed_temp_path
    );

    // Sideload file (this moves it to the uploads folder and creates attachment)
    $attachment_id = media_handle_sideload($file_array, 0);

    if (is_wp_error($attachment_id)) {
        @unlink($renamed_temp_path);
        wp_send_json_error('Failed to sideload media: ' . $attachment_id->get_error_message());
    }

    @unlink($renamed_temp_path); // Clean up temp file

    // Update attachment details
    wp_update_post(array(
        'ID'           => $attachment_id,
        'post_title'   => sanitize_text_field($ai_data['title']),
        'post_excerpt' => sanitize_text_field($ai_data['caption']),
        'post_content' => sanitize_textarea_field($ai_data['description']),
    ));

    // Save alt text and file hash meta keys
    update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($ai_data['alt_text']));
    update_post_meta($attachment_id, '_attachment_file_hash', $file_hash);

    // 5. Create CPT Gallery Entry Post
    $gallery_post_id = wp_insert_post(array(
        'post_title'   => sanitize_text_field($ai_data['title']),
        'post_content' => sanitize_textarea_field($ai_data['description']),
        'post_excerpt' => sanitize_text_field($ai_data['caption']),
        'post_status'  => 'publish',
        'post_type'    => 'gallery',
    ));

    if (is_wp_error($gallery_post_id)) {
        wp_delete_attachment($attachment_id, true); // Delete uploaded attachment if post creation failed
        wp_send_json_error('Failed to create Gallery post: ' . $gallery_post_id->get_error_message());
    }

    // Link attachment as featured image (post thumbnail)
    set_post_thumbnail($gallery_post_id, $attachment_id);

    // Assign Category (taxonomy: assra_program)
    $category_slug = '';
    if (!empty($_POST['category']) && $_POST['category'] !== 'auto_detect') {
        $category_slug = sanitize_key($_POST['category']);
    } elseif (!empty($ai_data['auto_category'])) {
        $category_slug = sanitize_key($ai_data['auto_category']);
    }

    if (!empty($category_slug)) {
        $term = get_term_by('slug', $category_slug, 'assra_program');
        if ($term) {
            wp_set_post_terms($gallery_post_id, array($term->term_id), 'assra_program');
        }
    }

    // Set Event Year (postmeta: gallery_year)
    if (isset($_POST['year'])) {
        $year = intval($_POST['year']);
        if ($year >= 1900 && $year <= 2100) {
            update_post_meta($gallery_post_id, 'gallery_year', $year);
        }
    }

    // Store AI-generated Tags (postmeta: gallery_tags)
    if (!empty($ai_data['tags']) && is_array($ai_data['tags'])) {
        $sanitized_tags = array_map('sanitize_text_field', $ai_data['tags']);
        update_post_meta($gallery_post_id, 'gallery_tags', implode(', ', $sanitized_tags));
    }

    // Flush gallery transients to keep cache fresh
    delete_transient('assra_gallery_years_all');
    if (!empty($category_slug)) {
        delete_transient('assra_gallery_years_' . md5($category_slug));
    }

    // Get the category name to return to UI
    $assigned_category_name = 'General';
    if (!empty($category_slug)) {
        $term = get_term_by('slug', $category_slug, 'assra_program');
        if ($term) {
            $assigned_category_name = $term->name;
        }
    }

    wp_send_json_success(array(
        'gallery_post_id' => $gallery_post_id,
        'attachment_id'   => $attachment_id,
        'title'           => $ai_data['title'],
        'filename'        => $new_filename,
        'category'        => $assigned_category_name,
        'preview_url'     => wp_get_attachment_thumb_url($attachment_id),
    ));
});
