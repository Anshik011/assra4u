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
    add_menu_page(
        'AI Bulk Import',
        'AI Bulk Import',
        'manage_options',
        'assra-gallery-ai-import',
        'assra_gallery_ai_import_page',
        'dashicons-cloud-upload',
        9
    );
});

add_action('admin_enqueue_scripts', function ($hook) {
    if (strpos($hook, 'assra-gallery-ai-import') === false) {
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
    $provider = get_option('assra_api_provider', 'gemini');
    $gemini_key = get_option('assra_gemini_api_key', '');
    $openrouter_key = get_option('assra_openrouter_api_key', '');
    $groq_key = get_option('assra_groq_api_key', '');

    // Set initial text area key based on provider
    $active_key = '';
    if ($provider === 'gemini') {
        $active_key = $gemini_key;
    } elseif ($provider === 'openrouter') {
        $active_key = $openrouter_key;
    } elseif ($provider === 'groq') {
        $active_key = $groq_key;
    }

    $categories = get_terms(array(
        'taxonomy'   => 'assra_program',
        'hide_empty' => false,
    ));
    $current_year = date('Y');
    ?>
    <script>
    var assra_stored_keys = {
        gemini: <?php echo wp_json_encode($gemini_key); ?>,
        openrouter: <?php echo wp_json_encode($openrouter_key); ?>,
        groq: <?php echo wp_json_encode($groq_key); ?>
    };
    </script>
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
                
                <?php
                $provider = get_option('assra_api_provider', 'gemini');
                $doc_types = get_terms(array(
                    'taxonomy'   => 'doc_type',
                    'hide_empty' => false,
                ));
                ?>
                <!-- API Provider -->
                <div class="assra-form-group">
                    <label for="assra-api-provider">API Provider</label>
                    <select id="assra-api-provider" class="assra-form-select">
                        <option value="gemini" <?php selected($provider, 'gemini'); ?>>Gemini (Google AI Studio)</option>
                        <option value="openrouter" <?php selected($provider, 'openrouter'); ?>>OpenRouter (Free Models)</option>
                        <option value="groq" <?php selected($provider, 'groq'); ?>>Groq (Llama 3.2 Vision)</option>
                    </select>
                </div>

                <!-- API Key(s) -->
                <div class="assra-form-group">
                    <label for="assra-api-key">API Key(s)</label>
                    <textarea id="assra-api-key" class="assra-form-input" rows="3" placeholder="Paste API key(s) here. Separate multiple keys with commas." style="resize: vertical; font-family: monospace; font-size: 12px;"><?php echo esc_textarea($active_key); ?></textarea>
                    <p class="assra-form-helper" id="assra-api-key-helper">
                        Supports key rotation: enter multiple keys (e.g. key1, key2, key3) to bypass rate limits automatically.
                    </p>
                </div>

                <!-- Import Destination -->
                <div class="assra-form-group">
                    <label for="assra-post-type">Import Destination</label>
                    <select id="assra-post-type" class="assra-form-select">
                        <option value="gallery" selected>Gallery (Photo Album)</option>
                        <option value="media_clip">Media Coverage (Media Clip)</option>
                        <option value="document">Document (PDF/Image)</option>
                        <option value="award">Award (Certificate/Trophy)</option>
                    </select>
                </div>

                <!-- Program Category -->
                <div class="assra-form-group" id="assra-category-group">
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

                <!-- Document Type -->
                <div class="assra-form-group" id="assra-doc-type-group" style="display: none;">
                    <label for="assra-doc-type">Document Type</label>
                    <select id="assra-doc-type" class="assra-form-select">
                        <option value="auto_detect" selected>Auto-detect Document Type (Using AI)</option>
                        <option value="">-- No Type --</option>
                        <?php if (!is_wp_error($doc_types) && !empty($doc_types)) : ?>
                            <?php foreach ($doc_types as $dt) : ?>
                                <option value="<?php echo esc_attr($dt->slug); ?>"><?php echo esc_html($dt->name); ?></option>
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
                    <h4>Drag & Drop image or PDF files here</h4>
                    <p>or click to browse from your computer (Multiple files supported)</p>
                </div>
                <input type="file" id="assra-file-input" class="assra-hidden" multiple accept="image/*,application/pdf">

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

// Callback to save API Key and Provider asynchronously
add_action('wp_ajax_assra_save_gemini_key', function() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized capacity', 403);
    }
    check_ajax_referer('assra_ai_import_nonce', 'security');

    $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';
    $provider = isset($_POST['provider']) ? sanitize_key($_POST['provider']) : 'gemini';

    update_option('assra_api_provider', $provider);
    if ($provider === 'gemini') {
        update_option('assra_gemini_api_key', $api_key);
    } elseif ($provider === 'openrouter') {
        update_option('assra_openrouter_api_key', $api_key);
    } elseif ($provider === 'groq') {
        update_option('assra_groq_api_key', $api_key);
    }
    
    wp_send_json_success('API settings saved.');
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
    $post_type = isset($_POST['post_type']) ? sanitize_key($_POST['post_type']) : 'gallery';
    if (!in_array($post_type, array('gallery', 'media_clip', 'document'))) {
        $post_type = 'gallery';
    }

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

    // 2. Call AI Vision API (handles Key Rotation & selected Provider)
    $mime_type = $uploaded_file['type'];
    $ai_data = assra_call_ai_vision_api($file_path, $mime_type, $uploaded_file['name'], $post_type);

    if (is_wp_error($ai_data)) {
        wp_send_json_error($ai_data->get_error_message());
    }

    // 4. Rename & Copy/Convert File and sideload into Media Library
    $seo_slug = sanitize_title($ai_data['seo_filename']);
    $orig_ext = pathinfo($uploaded_file['name'], PATHINFO_EXTENSION);
    $orig_ext_lower = strtolower($orig_ext);
    $new_filename = $seo_slug . '.' . $orig_ext_lower;

    $tmp_dir = get_temp_dir();
    $renamed_temp_path = $tmp_dir . '/' . $new_filename;

    // If PDF or WebP, copy directly. Otherwise try converting to WebP/JPEG or copy directly.
    if ($orig_ext_lower === 'pdf' || $orig_ext_lower === 'webp' || $mime_type === 'application/pdf' || $mime_type === 'image/webp') {
        if (!copy($file_path, $renamed_temp_path)) {
            wp_send_json_error('Failed to prepare renamed temp file on disk.');
        }
    } else {
        // Fallback for raw files not pre-converted: convert to JPEG format
        $converted = assra_convert_to_jpeg($file_path, $renamed_temp_path);
        if (!$converted) {
            if (!copy($file_path, $renamed_temp_path)) {
                wp_send_json_error('Failed to prepare renamed temp file on disk.');
            }
        }
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

    // 5. Create CPT Entry Post
    $gallery_post_id = wp_insert_post(array(
        'post_title'   => sanitize_text_field($ai_data['title']),
        'post_content' => sanitize_textarea_field($ai_data['description']),
        'post_excerpt' => sanitize_text_field($ai_data['caption']),
        'post_status'  => 'publish',
        'post_type'    => $post_type,
    ));

    if (is_wp_error($gallery_post_id)) {
        wp_delete_attachment($attachment_id, true); // Delete uploaded attachment if post creation failed
        wp_send_json_error('Failed to create post: ' . $gallery_post_id->get_error_message());
    }

    // Link attachment as featured image (post thumbnail)
    set_post_thumbnail($gallery_post_id, $attachment_id);

    // Assign Category / Doc Type
    $assigned_category_name = 'General';
    $category_slug = '';

    if ($post_type === 'gallery') {
        if (!empty($_POST['category']) && $_POST['category'] !== 'auto_detect') {
            $category_slug = sanitize_key($_POST['category']);
        } elseif (!empty($ai_data['auto_category'])) {
            $category_slug = sanitize_key($ai_data['auto_category']);
        }

        // Get list of valid active category slugs from the database
        $valid_slugs = array();
        $categories = get_terms(array(
            'taxonomy'   => 'assra_program',
            'hide_empty' => false,
        ));
        if (!is_wp_error($categories) && !empty($categories)) {
            foreach ($categories as $cat) {
                $valid_slugs[] = $cat->slug;
            }
        }

        // Enforce fallback if empty or not in valid slugs list (prevent "General" or uncategorized posts)
        if (empty($category_slug) || !in_array($category_slug, $valid_slugs)) {
            if (!empty($valid_slugs)) {
                $category_slug = $valid_slugs[0]; // Fallback to first active category term
            } else {
                $category_slug = '';
            }
        }

        if (!empty($category_slug)) {
            $term = get_term_by('slug', $category_slug, 'assra_program');
            if ($term) {
                wp_set_post_terms($gallery_post_id, array($term->term_id), 'assra_program');
                $assigned_category_name = $term->name;
            }
        }
    } elseif ($post_type === 'document') {
        $doc_type_slug = '';
        if (!empty($_POST['category']) && $_POST['category'] !== 'auto_detect') {
            $doc_type_slug = sanitize_key($_POST['category']);
        } elseif (!empty($ai_data['auto_doc_type'])) {
            $doc_type_slug = sanitize_key($ai_data['auto_doc_type']);
        }

        // Get list of valid active doc_type slugs from the database
        $valid_doc_slugs = array();
        $doc_categories = get_terms(array(
            'taxonomy'   => 'doc_type',
            'hide_empty' => false,
        ));
        if (!is_wp_error($doc_categories) && !empty($doc_categories)) {
            foreach ($doc_categories as $dc) {
                $valid_doc_slugs[] = $dc->slug;
            }
        }

        if (empty($doc_type_slug) || !in_array($doc_type_slug, $valid_doc_slugs)) {
            if (!empty($valid_doc_slugs)) {
                $doc_type_slug = $valid_doc_slugs[0]; // Fallback to first doc_type term
            } else {
                $doc_type_slug = '';
            }
        }

        if (!empty($doc_type_slug)) {
            $term = get_term_by('slug', $doc_type_slug, 'doc_type');
            if ($term) {
                wp_set_post_terms($gallery_post_id, array($term->term_id), 'doc_type');
                $assigned_category_name = $term->name;
            }
        }

        // Save document file URL to custom postmeta (used by universal_loop to render the link)
        update_post_meta($gallery_post_id, 'document_file', wp_get_attachment_url($attachment_id));
    } elseif ($post_type === 'award') {
        $assigned_category_name = 'Awards';
    } else {
        $assigned_category_name = 'Media Coverage';
    }

    // Detect Event Year (AI Visual -> EXIF -> Filename -> Fallback Input)
    $detected_year = null;

    // 1. Try AI Visually Detected Year
    if (!empty($ai_data['detected_year'])) {
        $ai_year = intval($ai_data['detected_year']);
        if ($ai_year >= 1900 && $ai_year <= 2100) {
            $detected_year = $ai_year;
        }
    }

    // 2. Try EXIF
    if (!$detected_year && function_exists('exif_read_data')) {
        $exif = @exif_read_data($file_path);
        if (!empty($exif['DateTimeOriginal'])) {
            if (preg_match('/^(19|20)\d{2}/', $exif['DateTimeOriginal'], $matches)) {
                $detected_year = intval($matches[0]);
            }
        } elseif (!empty($exif['FileDateTime'])) {
            $detected_year = intval(date('Y', $exif['FileDateTime']));
        }
    }

    // 3. Try Filename
    if (!$detected_year) {
        $orig_name = $uploaded_file['name'];
        if (preg_match('/(19|20)\d{2}/', $orig_name, $matches)) {
            $detected_year = intval($matches[0]);
        }
    }

    // 4. Fallback to input
    if (!$detected_year && isset($_POST['year'])) {
        $detected_year = intval($_POST['year']);
    }

    // Ensure valid range, fallback to current year
    if (!$detected_year || $detected_year < 1900 || $detected_year > 2100) {
        $detected_year = intval(date('Y'));
    }

    // Save Year to postmeta
    if ($post_type === 'award') {
        update_post_meta($gallery_post_id, 'award_year', $detected_year);
    } else {
        update_post_meta($gallery_post_id, 'gallery_year', $detected_year);
    }

    // Store AI-generated Tags (postmeta: gallery_tags)
    if (!empty($ai_data['tags']) && is_array($ai_data['tags'])) {
        $sanitized_tags = array_map('sanitize_text_field', $ai_data['tags']);
        update_post_meta($gallery_post_id, 'gallery_tags', implode(', ', $sanitized_tags));
    }

    // Flush transients to keep cache fresh
    if ($post_type === 'gallery') {
        delete_transient('assra_gallery_years_all');
        if (!empty($category_slug)) {
            delete_transient('assra_gallery_years_' . md5($category_slug));
        }
    } elseif ($post_type === 'document') {
        delete_transient('assra_menu_doc_types');
    } elseif ($post_type === 'award') {
        // No custom transients for awards
    } else {
        delete_transient('assra_media_coverage_all');
    }

    // Get the category name to return to UI
    $assigned_category_name = 'General';
    if ($post_type === 'gallery') {
        if (!empty($category_slug)) {
            $term = get_term_by('slug', $category_slug, 'assra_program');
            if ($term) {
                $assigned_category_name = $term->name;
            }
        }
    } elseif ($post_type === 'document') {
        if (!empty($doc_type_slug)) {
            $term = get_term_by('slug', $doc_type_slug, 'doc_type');
            if ($term) {
                $assigned_category_name = $term->name;
            }
        }
    } elseif ($post_type === 'award') {
        $assigned_category_name = 'Awards';
    } else {
        $assigned_category_name = 'Media Coverage';
    }

    wp_send_json_success(array(
        'gallery_post_id' => $gallery_post_id,
        'attachment_id'   => $attachment_id,
        'title'           => $ai_data['title'],
        'filename'        => $new_filename,
        'category'        => $assigned_category_name,
        'year'            => $detected_year,
        'preview_url'     => wp_get_attachment_thumb_url($attachment_id),
    ));
});

/**
 * Call the selected AI Vision API (handles Key Rotation & Fallback)
 */
function assra_call_ai_vision_api($file_path, $mime_type, $original_filename, $post_type) {
    $provider = get_option('assra_api_provider', 'gemini');
    
    // Retrieve the correct key based on active provider
    $api_keys_str = '';
    if ($provider === 'gemini') {
        $api_keys_str = get_option('assra_gemini_api_key', '');
    } elseif ($provider === 'openrouter') {
        $api_keys_str = get_option('assra_openrouter_api_key', '');
    } elseif ($provider === 'groq') {
        $api_keys_str = get_option('assra_groq_api_key', '');
    }

    // Split keys by commas, semicolons, or newlines
    $keys = preg_split('/[\s,;]+/', $api_keys_str);
    $keys = array_filter(array_map('trim', $keys));

    if (empty($keys)) {
        return new WP_Error('missing_key', 'No API Key is configured for the selected provider. Please configure it in settings.');
    }

    $image_base64 = base64_encode(file_get_contents($file_path));
    $last_error = '';

    foreach ($keys as $key) {
        $result = null;
        if ($provider === 'gemini') {
            $result = assra_call_gemini_api($key, $image_base64, $mime_type, $original_filename, $post_type);
        } elseif ($provider === 'openrouter') {
            $result = assra_call_openrouter_api($key, $image_base64, $mime_type, $original_filename, $post_type);
        } elseif ($provider === 'groq') {
            $result = assra_call_groq_api($key, $image_base64, $mime_type, $original_filename, $post_type);
        } else {
            return new WP_Error('invalid_provider', 'Invalid API Provider selected.');
        }

        if (!is_wp_error($result)) {
            // Success! Rotate the working key to the front of the list
            $current_idx = array_search($key, $keys);
            if ($current_idx > 0) {
                unset($keys[$current_idx]);
                array_unshift($keys, $key);
                $option_name = 'assra_' . $provider . '_api_key';
                update_option($option_name, implode(', ', $keys));
            }
            return $result;
        }

        $last_error = $result->get_error_message();
    }

    return new WP_Error('all_keys_failed', 'All API keys failed. Last error: ' . $last_error);
}

/**
 * Call Gemini AI Vision API
 */
function assra_call_gemini_api($api_key, $image_data, $mime_type, $original_filename, $post_type) {
    $is_document = ($post_type === 'document');
    $is_award = ($post_type === 'award');

    if ($is_document) {
        $prompt = "Analyze this document (image or PDF) for a non-profit NGO website named ASSRA. Generate descriptive metadata. The title and descriptions must look professional and human-written, avoiding generic words. Return the metadata in structured JSON format according to the schema.";
        $properties = array(
            'title'         => array('type' => 'STRING'),
            'alt_text'      => array('type' => 'STRING'),
            'caption'       => array('type' => 'STRING'),
            'description'   => array('type' => 'STRING'),
            'seo_filename'  => array('type' => 'STRING'),
            'auto_doc_type' => array('type' => 'STRING', 'description' => 'Classify the document into: "annual-report", "certificate".'),
            'tags'          => array(
                'type'  => 'ARRAY',
                'items' => array('type' => 'STRING')
            )
        );
        $required = array('title', 'alt_text', 'caption', 'description', 'seo_filename', 'auto_doc_type', 'tags');
    } elseif ($is_award) {
        $prompt = "Analyze this award certificate or trophy photo for a non-profit NGO website named ASSRA. Generate descriptive metadata. The title and descriptions must look professional and human-written, avoiding generic words. Return the metadata in structured JSON format according to the schema. For detected_year, try to visually inspect the certificate or trophy for the year it was issued. If no year is visually identifiable, return 0.";
        $properties = array(
            'title'         => array('type' => 'STRING'),
            'alt_text'      => array('type' => 'STRING'),
            'caption'       => array('type' => 'STRING'),
            'description'   => array('type' => 'STRING'),
            'seo_filename'  => array('type' => 'STRING'),
            'detected_year' => array('type' => 'INTEGER'),
            'tags'          => array(
                'type'  => 'ARRAY',
                'items' => array('type' => 'STRING')
            )
        );
        $required = array('title', 'alt_text', 'caption', 'description', 'seo_filename', 'detected_year', 'tags');
    } else {
        $prompt = "Analyze this photograph for a non-profit NGO website named ASSRA. Generate descriptive metadata. The title and descriptions must look professional and human-written, avoiding generic words. Return the metadata in structured JSON format according to the schema. For detected_year, try to visually inspect the photograph for any signs of the event year (e.g. banners, signs, posters, t-shirts, calendars). If no year is visually identifiable, return 0.";
        $properties = array(
            'title'         => array('type' => 'STRING'),
            'alt_text'      => array('type' => 'STRING'),
            'caption'       => array('type' => 'STRING'),
            'description'   => array('type' => 'STRING'),
            'seo_filename'  => array('type' => 'STRING'),
            'auto_category' => array('type' => 'STRING'),
            'detected_year' => array('type' => 'INTEGER'),
            'tags'          => array(
                'type'  => 'ARRAY',
                'items' => array('type' => 'STRING')
            )
        );
        $required = array('title', 'alt_text', 'caption', 'description', 'seo_filename', 'auto_category', 'detected_year', 'tags');
    }

    $request_body = array(
        'contents' => array(
            array(
                'parts' => array(
                    array('text' => $prompt),
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
                'properties' => $properties,
                'required'   => $required
            )
        )
    );

    $response = wp_remote_post(
        'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite:generateContent?key=' . $api_key,
        array(
            'headers' => array('Content-Type' => 'application/json'),
            'body'    => wp_json_encode($request_body),
            'timeout' => 45
        )
    );

    if (is_wp_error($response)) {
        return $response;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    if ($response_code !== 200) {
        $body_text = wp_remote_retrieve_body($response);
        $err_parsed = json_decode($body_text, true);
        $err_msg = !empty($err_parsed['error']['message']) ? $err_parsed['error']['message'] : $body_text;
        return new WP_Error('gemini_api_error', 'Gemini API Error (Status ' . $response_code . '): ' . $err_msg);
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (empty($body['candidates'][0]['content']['parts'][0]['text'])) {
        return new WP_Error('gemini_invalid_response', 'Invalid response format from Gemini API.');
    }

    $ai_data = json_decode($body['candidates'][0]['content']['parts'][0]['text'], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return new WP_Error('gemini_json_parse', 'Failed to parse Gemini JSON: ' . json_last_error_msg());
    }

    return $ai_data;
}

/**
 * Call OpenRouter AI Vision API (Supports Free Models)
 */
function assra_call_openrouter_api($api_key, $image_data, $mime_type, $original_filename, $post_type) {
    if ($mime_type === 'application/pdf' || strtolower(pathinfo($original_filename, PATHINFO_EXTENSION)) === 'pdf') {
        return new WP_Error('pdf_not_supported', 'PDF document analysis is only supported with the Gemini provider. Please select Gemini as the API Provider or upload image files.');
    }

    $is_document = ($post_type === 'document');
    $is_award = ($post_type === 'award');

    if ($is_document) {
        $prompt = "Analyze this document (image or PDF) for a non-profit NGO website named ASSRA. Generate descriptive metadata. The title and descriptions must look professional and human-written, avoiding generic words.
Return the metadata in structured JSON format according to this schema:
{
  \"title\": \"A descriptive, human-quality title suitable for the document (e.g. 'ASSRA Annual Audited Financial Report 2023-24')\",
  \"alt_text\": \"Short summary of the document contents for accessibility\",
  \"caption\": \"Brief caption summarizing the document\",
  \"description\": \"Detailed description of the document contents, sections, and findings\",
  \"seo_filename\": \"SEO friendly slug filename (lowercase, hyphenated, no spaces, no extension, e.g. 'assra-annual-audited-financial-report-2023-24')\",
  \"auto_doc_type\": \"Classify the document into one of these category slugs based on its content: 'annual-report', 'certificate'. Choose the closest matching slug.\",
  \"tags\": [\"keyword1\", \"keyword2\", \"keyword3\"]
}";
    } elseif ($is_award) {
        $prompt = "Analyze this award certificate or trophy photo for a non-profit NGO website named ASSRA. Generate descriptive metadata. The title and descriptions must look professional and human-written, avoiding generic words.
Return the metadata in structured JSON format according to this schema:
{
  \"title\": \"A descriptive, human-quality title suitable for the award (e.g. 'ASSRA Best Community Impact Award 2024')\",
  \"alt_text\": \"Short descriptive alt text for accessibility\",
  \"caption\": \"Brief caption summarizing the award\",
  \"description\": \"Detailed description of the award and what it was presented for\",
  \"seo_filename\": \"SEO friendly slug filename (lowercase, hyphenated, no spaces, no extension, e.g. 'assra-best-community-impact-award-2024')\",
  \"detected_year\": 2024, // Try to visually inspect the certificate or trophy for the year it was issued. If no year is visually identifiable, return 0.
  \"tags\": [\"keyword1\", \"keyword2\", \"keyword3\"]
}";
    } else {
        $prompt = "Analyze this photograph for a non-profit NGO website named ASSRA. Generate descriptive metadata. The title and descriptions must look professional and human-written, avoiding generic words.
Return the metadata in structured JSON format according to this schema:
{
  \"title\": \"A descriptive, human-quality title suitable for the NGO gallery (e.g. 'Underprivileged Children Receiving Remedial Education')\",
  \"alt_text\": \"SEO friendly descriptive alt text for accessibility\",
  \"caption\": \"Brief caption summarizing the scene\",
  \"description\": \"Detailed description of the activity/scene shown in the image\",
  \"seo_filename\": \"SEO friendly slug filename (lowercase, hyphenated, no spaces, no extension, e.g. 'underprivileged-children-remedial-education')\",
  \"auto_category\": \"Classify the image into one of these category slugs based on visual context: 'education-work', 'elderly-care', 'empowerment', 'environment'. Choose the closest matching slug.\",
  \"detected_year\": 2024, // Try to visually inspect the photograph for any signs of the event year (e.g. banners, signs, posters, t-shirts, calendars). If no year is visually identifiable, return 0.
  \"tags\": [\"keyword1\", \"keyword2\", \"keyword3\"]
}";
    }

    $request_body = array(
        'model' => 'meta-llama/llama-3.2-11b-vision-instruct:free',
        'messages' => array(
            array(
                'role' => 'user',
                'content' => array(
                    array(
                        'type' => 'text',
                        'text' => $prompt
                    ),
                    array(
                        'type' => 'image_url',
                        'image_url' => array(
                            'url' => 'data:' . $mime_type . ';base64,' . $image_data
                        )
                    )
                )
            )
        ),
        'response_format' => array('type' => 'json_object')
    );

    $response = wp_remote_post(
        'https://openrouter.ai/api/v1/chat/completions',
        array(
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key
            ),
            'body'    => wp_json_encode($request_body),
            'timeout' => 45
        )
    );

    if (is_wp_error($response)) {
        return $response;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    if ($response_code !== 200) {
        $body_text = wp_remote_retrieve_body($response);
        $err_parsed = json_decode($body_text, true);
        $err_msg = !empty($err_parsed['error']['message']) ? $err_parsed['error']['message'] : $body_text;
        return new WP_Error('openrouter_api_error', 'OpenRouter API Error (Status ' . $response_code . '): ' . $err_msg);
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (empty($body['choices'][0]['message']['content'])) {
        return new WP_Error('openrouter_invalid_response', 'Invalid response format from OpenRouter API.');
    }

    $content = $body['choices'][0]['message']['content'];
    $ai_data = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return new WP_Error('openrouter_json_parse', 'Failed to parse OpenRouter JSON: ' . json_last_error_msg());
    }

    return $ai_data;
}

/**
 * Call Groq AI Vision API (Supports Llama 3.2 Vision)
 */
function assra_call_groq_api($api_key, $image_data, $mime_type, $original_filename, $post_type) {
    if ($mime_type === 'application/pdf' || strtolower(pathinfo($original_filename, PATHINFO_EXTENSION)) === 'pdf') {
        return new WP_Error('pdf_not_supported', 'PDF document analysis is only supported with the Gemini provider. Please select Gemini as the API Provider or upload image files.');
    }

    $is_document = ($post_type === 'document');
    $is_award = ($post_type === 'award');

    if ($is_document) {
        $prompt = "Analyze this document (image or PDF) for a non-profit NGO website named ASSRA. Generate descriptive metadata. The title and descriptions must look professional and human-written, avoiding generic words.
Return the metadata in structured JSON format according to this schema:
{
  \"title\": \"A descriptive, human-quality title suitable for the document (e.g. 'ASSRA Annual Audited Financial Report 2023-24')\",
  \"alt_text\": \"Short summary of the document contents for accessibility\",
  \"caption\": \"Brief caption summarizing the document\",
  \"description\": \"Detailed description of the document contents, sections, and findings\",
  \"seo_filename\": \"SEO friendly slug filename (lowercase, hyphenated, no spaces, no extension, e.g. 'assra-annual-audited-financial-report-2023-24')\",
  \"auto_doc_type\": \"Classify the document into one of these category slugs based on its content: 'annual-report', 'certificate'. Choose the closest matching slug.\",
  \"tags\": [\"keyword1\", \"keyword2\", \"keyword3\"]
}";
    } elseif ($is_award) {
        $prompt = "Analyze this award certificate or trophy photo for a non-profit NGO website named ASSRA. Generate descriptive metadata. The title and descriptions must look professional and human-written, avoiding generic words.
Return the metadata in structured JSON format according to this schema:
{
  \"title\": \"A descriptive, human-quality title suitable for the award (e.g. 'ASSRA Best Community Impact Award 2024')\",
  \"alt_text\": \"Short descriptive alt text for accessibility\",
  \"caption\": \"Brief caption summarizing the award\",
  \"description\": \"Detailed description of the award and what it was presented for\",
  \"seo_filename\": \"SEO friendly slug filename (lowercase, hyphenated, no spaces, no extension, e.g. 'assra-best-community-impact-award-2024')\",
  \"detected_year\": 2024, // Try to visually inspect the certificate or trophy for the year it was issued. If no year is visually identifiable, return 0.
  \"tags\": [\"keyword1\", \"keyword2\", \"keyword3\"]
}";
    } else {
        $prompt = "Analyze this photograph for a non-profit NGO website named ASSRA. Generate descriptive metadata. The title and descriptions must look professional and human-written, avoiding generic words.
Return the metadata in structured JSON format according to this schema:
{
  \"title\": \"A descriptive, human-quality title suitable for the NGO gallery (e.g. 'Underprivileged Children Receiving Remedial Education')\",
  \"alt_text\": \"SEO friendly descriptive alt text for accessibility\",
  \"caption\": \"Brief caption summarizing the scene\",
  \"description\": \"Detailed description of the activity/scene shown in the image\",
  \"seo_filename\": \"SEO friendly slug filename (lowercase, hyphenated, no spaces, no extension, e.g. 'underprivileged-children-remedial-education')\",
  \"auto_category\": \"Classify the image into one of these category slugs based on visual context: 'education-work', 'elderly-care', 'empowerment', 'environment'. Choose the closest matching slug.\",
  \"detected_year\": 2024, // Try to visually inspect the photograph for any signs of the event year (e.g. banners, signs, posters, t-shirts, calendars). If no year is visually identifiable, return 0.
  \"tags\": [\"keyword1\", \"keyword2\", \"keyword3\"]
}";
    }

    $request_body = array(
        'model' => 'meta-llama/llama-4-scout-17b-16e-instruct',
        'messages' => array(
            array(
                'role' => 'user',
                'content' => array(
                    array(
                        'type' => 'text',
                        'text' => $prompt
                    ),
                    array(
                        'type' => 'image_url',
                        'image_url' => array(
                            'url' => 'data:' . $mime_type . ';base64,' . $image_data
                        )
                    )
                )
            )
        ),
        'response_format' => array('type' => 'json_object')
    );

    $response = wp_remote_post(
        'https://api.groq.com/openai/v1/chat/completions',
        array(
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key
            ),
            'body'    => wp_json_encode($request_body),
            'timeout' => 45
        )
    );

    if (is_wp_error($response)) {
        return $response;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    if ($response_code !== 200) {
        $body_text = wp_remote_retrieve_body($response);
        $err_parsed = json_decode($body_text, true);
        $err_msg = !empty($err_parsed['error']['message']) ? $err_parsed['error']['message'] : $body_text;
        return new WP_Error('groq_api_error', 'Groq API Error (Status ' . $response_code . '): ' . $err_msg);
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (empty($body['choices'][0]['message']['content'])) {
        return new WP_Error('groq_invalid_response', 'Invalid response format from Groq API.');
    }

    $content = $body['choices'][0]['message']['content'];
    $ai_data = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return new WP_Error('groq_json_parse', 'Failed to parse Groq JSON: ' . json_last_error_msg());
    }

    return $ai_data;
}

/**
 * Convert an image file to JPEG format using PHP GD
 */
function assra_convert_to_jpeg($source_file, $dest_file) {
    if (!function_exists('gd_info')) {
        return false; // GD extension is not enabled
    }

    $info = @getimagesize($source_file);
    if (empty($info)) {
        return false;
    }

    $mime = $info['mime'];
    $image = null;

    switch ($mime) {
        case 'image/jpeg':
            $image = @imagecreatefromjpeg($source_file);
            break;
        case 'image/png':
            $image = @imagecreatefrompng($source_file);
            break;
        case 'image/gif':
            $image = @imagecreatefromgif($source_file);
            break;
        case 'image/webp':
            if (function_exists('imagecreatefromwebp')) {
                $image = @imagecreatefromwebp($source_file);
            }
            break;
    }

    if (!$image) {
        return false;
    }

    $width = imagesx($image);
    $height = imagesy($image);

    // Create new truecolor image canvas
    $canvas = @imagecreatetruecolor($width, $height);
    if (!$canvas) {
        imagedestroy($image);
        return false;
    }

    // Allocate white background (for transparency handling in PNGs/WebPs)
    $white = imagecolorallocate($canvas, 255, 255, 255);
    imagefill($canvas, 0, 0, $white);

    // Copy original image onto canvas
    imagecopy($canvas, $image, 0, 0, 0, 0, $width, $height);

    // Save as JPEG with 85% compression quality
    $success = @imagejpeg($canvas, $dest_file, 85);

    // Clean up memory
    imagedestroy($image);
    imagedestroy($canvas);

    return $success;
}
