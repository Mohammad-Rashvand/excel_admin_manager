<?php
/* Template Name: Login Redirect */
if (!is_user_logged_in()) {
    wp_redirect(site_url('/login-custom'));
    exit;
}

$user = wp_get_current_user();
if (in_array('administrator', (array) $user->roles)) {
    wp_redirect(site_url('/admin-panel'));
} else {
    wp_redirect(site_url('/user-panel'));
}
exit;