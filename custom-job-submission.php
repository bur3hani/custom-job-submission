<?php
/**
 * Plugin Name: Custom Job Submission
 * Plugin URI: https://github.com/bur3hani
 * Description: A plugin to allow employers to submit job postings from the front-end using Contact Form 7.
 * Version: 1.0
 * Author: Burhani Mtengwa
 * Author URI: https://github.com/bur3hani
 * License: GPL2
 */

// Ensure no direct access to this file
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Register the Custom Post Type (CPT) for Jobs
function create_job_post_type() {
    $args = array(
        'labels' => array(
            'name' => 'Jobs',
            'singular_name' => 'Job',
            'add_new' => 'Add New',
            'add_new_item' => 'Add New Job',
            'edit_item' => 'Edit Job',
            'new_item' => 'New Job',
            'view_item' => 'View Job',
            'search_items' => 'Search Jobs',
            'not_found' => 'No jobs found',
            'not_found_in_trash' => 'No jobs found in Trash',
        ),
        'public' => true,
        'has_archive' => false,
        'show_in_rest' => true,  // Important for the Gutenberg editor
        'supports' => array('title', 'editor', 'custom-fields', 'thumbnail'), // Thumbnail for featured image
        'menu_icon' => 'dashicons-briefcase', // Icon for the CPT
    );

    register_post_type('job', $args);
}
add_action('init', 'create_job_post_type');

// Hook into Contact Form 7 Submission and Create Job Post
function create_job_post_from_cf7($cf7) {
    // Ensure this is the correct form by checking the form ID
    if ($cf7->id() == 'YOUR_FORM_ID') { // Replace with your form ID
        $submission = WPCF7_Submission::get_instance();
        $data = $submission->get_posted_data();

        // Prepare job post data
        $post_data = array(
            'post_title'   => sanitize_text_field($data['job_title']),
            'post_content' => sanitize_textarea_field($data['job_description']),
            'post_status'  => 'publish', // Set to publish immediately
            'post_type'    => 'job', // Post type is Job
            'meta_input'   => array(
                'job_type'    => sanitize_text_field($data['job_type']),
                'location'    => sanitize_text_field($data['location']),
                'company_name'=> sanitize_text_field($data['company_name']),
                'salary'      => sanitize_text_field($data['salary']),
                'deadline'    => sanitize_text_field($data['deadline']),
            ),
        );

        // Insert the post into the WordPress database
        $post_id = wp_insert_post($post_data);

        // Handle file upload (CV)
        if (!empty($_FILES['cv']['name'])) {
            $upload = wp_upload_bits($_FILES['cv']['name'], null, file_get_contents($_FILES['cv']['tmp_name']));
            if (!$upload['error']) {
                $file_url = $upload['url'];
                update_post_meta($post_id, 'cv_upload', $file_url); // Save CV file URL as post meta
            }
        }

        // Handle featured image upload
        if (!empty($_FILES['featured_image']['name'])) {
            $upload = wp_upload_bits($_FILES['featured_image']['name'], null, file_get_contents($_FILES['featured_image']['tmp_name']));
            if (!$upload['error']) {
                // Get the uploaded file URL
                $featured_image_url = $upload['url'];

                // Upload the image and set it as the featured image
                $attachment = array(
                    'post_mime_type' => 'image/jpeg', // Adjust mime type if necessary
                    'post_title'     => sanitize_text_field($data['job_title']),
                    'post_content'   => '',
                    'post_status'    => 'inherit',
                    'guid'           => $featured_image_url,
                );
                $attachment_id = wp_insert_attachment($attachment, $upload['file'], $post_id);

                // Generate the metadata for the image
                require_once(ABSPATH . 'wp-admin/includes/image.php');
                $metadata = wp_generate_attachment_metadata($attachment_id, $upload['file']);
                wp_update_attachment_metadata($attachment_id, $metadata);

                // Set the featured image
                set_post_thumbnail($post_id, $attachment_id);
            }
        }
    }
}
add_action('wpcf7_before_send_mail', 'create_job_post_from_cf7');
