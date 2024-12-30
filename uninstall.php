<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit();
}

$obs_options = get_option('obs_options', obs_get_default_options());
$upload_url_path = get_option('upload_url_path');
$obs_upload_url_path = esc_attr($obs_options['upload_url_path']);

if ($upload_url_path == $obs_upload_url_path) {
    update_option('upload_url_path', '');
}

delete_option('obs_options');
