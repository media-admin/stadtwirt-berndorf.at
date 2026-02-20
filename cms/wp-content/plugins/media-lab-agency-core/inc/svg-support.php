<?php
/**
 * SVG Upload Support
 */

if (!defined('ABSPATH')) exit;

/**
 * Allow SVG uploads
 */
add_filter('upload_mimes', function($mimes) {
    $mimes['svg'] = 'image/svg+xml';
    return $mimes;
});

/**
 * Fix SVG display in media library
 */
add_filter('wp_check_filetype_and_ext', function($data, $file, $filename, $mimes) {
    $filetype = wp_check_filetype($filename, $mimes);
    
    return [
        'ext'             => $filetype['ext'],
        'type'            => $filetype['type'],
        'proper_filename' => $data['proper_filename']
    ];
}, 10, 4);

/**
 * Display SVG thumbnails in media library
 */
add_filter('wp_prepare_attachment_for_js', function($response, $attachment, $meta) {
    if ($response['mime'] === 'image/svg+xml' && empty($response['sizes'])) {
        $response['sizes'] = [
            'full' => [
                'url' => $response['url']
            ]
        ];
    }
    return $response;
}, 10, 3);

/**
 * Optional: Basic SVG sanitization (removes scripts)
 * For production use a library like enshrined/svg-sanitize
 */
add_filter('wp_handle_upload_prefilter', function($file) {
    if ($file['type'] === 'image/svg+xml') {
        $svg_content = file_get_contents($file['tmp_name']);
        
        // Remove script tags and event handlers
        $svg_content = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $svg_content);
        $svg_content = preg_replace('/on\w+\s*=\s*["\'].*?["\']/i', '', $svg_content);
        
        file_put_contents($file['tmp_name'], $svg_content);
    }
    return $file;
});
