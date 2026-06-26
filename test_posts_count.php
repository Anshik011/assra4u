<?php
define('WP_USE_THEMES', false);
require(__DIR__ . '/../../../wp-load.php');

$terms = get_terms([
    'taxonomy' => 'assra_program',
    'hide_empty' => false,
]);

foreach ($terms as $t) {
    $query = new WP_Query([
        'post_type' => 'gallery',
        'tax_query' => [[
            'taxonomy' => 'assra_program',
            'field'    => 'slug',
            'terms'    => $t->slug,
        ]],
        'posts_per_page' => -1,
    ]);
    echo "Slug: " . $t->slug . " | Name: " . $t->name . " | Count: " . $query->found_posts . "\n";
}
