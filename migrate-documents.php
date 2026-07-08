<?php
/**
 * ASSRA NGO Document Migration Script
 * Migrates regulatory PDFs and certificates from the old site (assra4u.org) to the new WordPress site.
 */

// Enable error reporting and output buffering
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(0); // Prevent PHP execution timeout
ini_set('memory_limit', '512M'); // Increase memory limit for heavy downloads

// Find wp-load.php dynamically (works if placed in root or inside theme directory)
$wp_load_path = '';
$search_paths = array(
    __DIR__ . '/../../../wp-load.php', // Inside theme dir (wp-content/themes/assra/)
    __DIR__ . '/wp-load.php'           // In WordPress root
);

foreach ($search_paths as $path) {
    if (file_exists($path)) {
        $wp_load_path = $path;
        break;
    }
}

if (empty($wp_load_path)) {
    die("Error: Could not locate wp-load.php. Please make sure this script is placed inside your WordPress root or theme directory.");
}

require_once($wp_load_path);

// Required files for programmatic media sideloading
require_once(ABSPATH . 'wp-admin/includes/image.php');
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/media.php');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ASSRA NGO - Document Migration Tool</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background: #f4f6f9; color: #333; padding: 40px 20px; line-height: 1.5; }
        .container { max-width: 800px; margin: 0 auto; background: #ffffff; padding: 30px; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); border: 1px solid #e1e4e8; }
        h1 { color: #1e1e1e; font-size: 24px; margin-top: 0; margin-bottom: 10px; border-bottom: 2px solid #007cba; padding-bottom: 10px; }
        h2 { font-size: 18px; margin-top: 25px; margin-bottom: 10px; color: #007cba; }
        .log-box { background: #1e1e1e; color: #32c12c; font-family: monospace; font-size: 13px; padding: 20px; border-radius: 6px; height: 350px; overflow-y: auto; white-space: pre-wrap; margin-top: 15px; border: 1px solid #000; }
        .log-success { color: #32c12c; }
        .log-warning { color: #f0b840; }
        .log-error { color: #f44336; }
        .log-info { color: #00bcd4; }
        .btn { display: inline-block; background: #007cba; color: #fff; padding: 12px 24px; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; text-decoration: none; font-size: 15px; transition: background 0.2s; }
        .btn:hover { background: #005a87; }
        .stats { display: flex; justify-content: space-between; background: #eef1f5; padding: 15px; border-radius: 6px; margin-top: 20px; font-size: 14px; }
        .stat-item { text-align: center; flex: 1; }
        .stat-val { font-size: 20px; font-weight: bold; color: #1e1e1e; }
    </style>
</head>
<body>

<div class="container">
    <h1><span style="font-size:30px;">📄</span> ASSRA NGO Document Migration Tool</h1>
    <p>This tool automatically downloads regulatory documents and certificates from <strong>assra4u.org</strong> and imports them as <strong>Document Type</strong> custom posts in your WordPress environment.</p>

    <?php
    if (!isset($_GET['run'])) :
    ?>
        <div style="text-align:center; padding: 40px 0;">
            <a href="?run=1" class="btn">🚀 Start Document Migration</a>
        </div>
    <?php
    else :
        // Output buffering flush function
        function log_msg($msg, $type = 'success') {
            $class = 'log-' . $type;
            echo "<span class='{$class}'>" . esc_html($msg) . "</span>\n";
            echo str_pad('', 4096) . "\n"; // Padding to force browser buffer flush
            ob_flush();
            flush();
        }

        echo '<div class="log-box">';
        ob_start();

        log_msg("--- INITIALIZING DOCUMENT MIGRATION ---", 'info');

        // Document Category Mappings
        $categories = array(
            1 => array('slug' => 'accreditation', 'default_type' => 'certificate', 'name' => 'Accreditation'),
            2 => array('slug' => 'covid-19-fcra', 'default_type' => 'annual-report', 'name' => 'Covid-19 FCRA'),
            3 => array('slug' => 'legal-documents', 'default_type' => 'certificate', 'name' => 'Legal Documents'),
            4 => array('slug' => 'activity-reports', 'default_type' => 'annual-report', 'name' => 'Activity Reports'),
            5 => array('slug' => 'fc-financials-returns', 'default_type' => 'annual-report', 'name' => 'FC Financials & Returns'),
            6 => array('slug' => 'consolidated-financials', 'default_type' => 'annual-report', 'name' => 'Consolidated Financials')
        );

        $total_imported = 0;
        $total_skipped = 0;
        $total_errors = 0;

        foreach ($categories as $cat_id => $cat_info) {
            $url = "https://www.assra4u.org/pdfdownload/{$cat_id}/{$cat_info['slug']}";
            log_msg("\nFetching category: {$cat_info['name']} (URL: {$url})...", 'info');

            $response = wp_remote_get($url, array('timeout' => 30));
            if (is_wp_error($response)) {
                log_msg("[ERROR] Failed to fetch category page: " . $response->get_error_message(), 'error');
                $total_errors++;
                continue;
            }

            $html = wp_remote_retrieve_body($response);
            if (empty($html)) {
                log_msg("[WARNING] Empty HTML body returned for category page.", 'warning');
                $total_skipped++;
                continue;
            }

            // Regex pattern to extract table rows with file URLs
            // Format: <tr> <th scope="row">1</th> <td>EPF Registration</td> <td><a href="/uploads/document/...pdf"
            $pattern = '/<tr>\s*<th[^>]*>.*?<\/th>\s*<td>\s*(.*?)\s*<\/td>\s*<td>\s*<a\s+href="([^"]+)"[^>]*>/is';
            if (preg_match_all($pattern, $html, $matches, PREG_SET_ORDER)) {
                log_msg("Found " . count($matches) . " documents in this category.", 'success');

                foreach ($matches as $match) {
                    $doc_name = trim(html_entity_decode($match[1]));
                    $relative_url = trim($match[2]);

                    // Skip invalid links
                    if (empty($relative_url) || $relative_url === '#') {
                        log_msg("  Skipped: {$doc_name} (Invalid or missing URL link)", 'warning');
                        $total_skipped++;
                        continue;
                    }

                    // Build full URL to download the document
                    $source_url = "https://www.assra4u.org" . $relative_url;
                    log_msg("  Downloading document '{$doc_name}' from: {$source_url} ...", 'info');

                    // Download file locally using WordPress HTTP API
                    $temp_file = download_url($source_url);
                    if (is_wp_error($temp_file)) {
                        log_msg("  [ERROR] Failed to download document: " . $temp_file->get_error_message(), 'error');
                        $total_errors++;
                        continue;
                    }

                    // Compute file MD5 hash for duplicate checks
                    $file_hash = md5_file($temp_file);
                    if (!$file_hash) {
                        @unlink($temp_file);
                        log_msg("  [ERROR] Could not compute hash for downloaded file.", 'error');
                        $total_errors++;
                        continue;
                    }

                    // Check if document already imported in Media Library
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
                        $dup_attachment = $duplicate_check->posts[0];
                        @unlink($temp_file);
                        log_msg("  [SKIP] Document '{$doc_name}' is already imported (Duplicate of attachment #{$dup_attachment->ID})", 'warning');
                        $total_skipped++;
                        continue;
                    }

                    // Sideload the document file into WordPress Media Library
                    $orig_filename = basename($relative_url);
                    $file_array = array(
                        'name'     => $orig_filename,
                        'tmp_name' => $temp_file
                    );

                    // media_handle_sideload moves temp file into uploads directory and registers attachment
                    $attachment_id = media_handle_sideload($file_array, 0);

                    if (is_wp_error($attachment_id)) {
                        @unlink($temp_file);
                        log_msg("  [ERROR] Failed to register sideloaded media: " . $attachment_id->get_error_message(), 'error');
                        $total_errors++;
                        continue;
                    }

                    // Save the file hash and update attachment title/details
                    update_post_meta($attachment_id, '_attachment_file_hash', $file_hash);
                    wp_update_post(array(
                        'ID'           => $attachment_id,
                        'post_title'   => $doc_name,
                    ));

                    // Determine Document Type term dynamically
                    $target_term = $cat_info['default_type']; // default_type is 'certificate' or 'annual-report'
                    
                    // Fine-tune term choice based on document title wording
                    if ($cat_id == 2) { // Covid-19 FCRA page contains mixed certificates & reports
                        if (stripos($doc_name, 'certificate') !== false || stripos($doc_name, 'registration') !== false || stripos($doc_name, 'approval') !== false) {
                            $target_term = 'certificate';
                        } else {
                            $target_term = 'annual-report';
                        }
                    }

                    // Create the Document CPT Post
                    $doc_post_id = wp_insert_post(array(
                        'post_title'   => $doc_name,
                        'post_content' => '',
                        'post_status'  => 'publish',
                        'post_type'    => 'document'
                    ));

                    if (is_wp_error($doc_post_id)) {
                        wp_delete_attachment($attachment_id, true);
                        log_msg("  [ERROR] Failed to create CPT post: " . $doc_post_id->get_error_message(), 'error');
                        $total_errors++;
                        continue;
                    }

                    // Map CPT post thumbnail and document_file URL postmeta
                    set_post_thumbnail($doc_post_id, $attachment_id);
                    update_post_meta($doc_post_id, 'document_file', wp_get_attachment_url($attachment_id));

                    // Set Taxonomy Terms
                    $term = get_term_by('slug', $target_term, 'doc_type');
                    if ($term) {
                        wp_set_post_terms($doc_post_id, array($term->term_id), 'doc_type');
                    }

                    log_msg("  [SUCCESS] Imported '{$doc_name}' as " . ($target_term === 'certificate' ? 'Certificate' : 'Annual Report') . " (CPT ID: {$doc_post_id})", 'success');
                    $total_imported++;
                }
            } else {
                log_msg("No documents found in category {$cat_info['name']}.", 'warning');
            }
        }

        // Flush Document menu transients to show updates immediately
        delete_transient('assra_menu_doc_types');

        log_msg("\n--- MIGRATION RUN COMPLETE ---", 'info');
        echo '</div>';

        // Display final statistics summary
        ?>
        <div class="stats">
            <div class="stat-item">
                <div class="stat-val" style="color: #32c12c;"><?php echo $total_imported; ?></div>
                <div>Imported</div>
            </div>
            <div class="stat-item">
                <div class="stat-val" style="color: #f0b840;"><?php echo $total_skipped; ?></div>
                <div>Skipped / Duplicates</div>
            </div>
            <div class="stat-item">
                <div class="stat-val" style="color: #f44336;"><?php echo $total_errors; ?></div>
                <div>Errors</div>
            </div>
        </div>

        <div style="text-align:center; margin-top:30px;">
            <a href="../../wp-admin/edit.php?post_type=document" class="btn" style="background:#4caf50;">Go to Documents List in WP Admin</a>
        </div>
        <?php
    endif;
    ?>
</div>

</body>
</html>
