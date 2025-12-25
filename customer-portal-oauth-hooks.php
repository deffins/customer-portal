<?php
/**
 * Custom hooks for Nextend Social Login integration
 * Handles Google OAuth user creation and account linking
 */

if (!defined('ABSPATH')) exit;

/**
 * Hook into WordPress login - fires AFTER Nextend saves all user meta
 * This ensures google_id is already saved in user meta
 */
add_action('wp_login', 'cp_check_social_login_on_wp_login', 10, 2);

/**
 * Check if this is a social login and handle it
 * Called on wp_login hook (username, WP_User object)
 */
function cp_check_social_login_on_wp_login($user_login, $wp_user) {
    $user_id = $wp_user->ID;

    // Check if this user has Google social login
    $google_id = get_user_meta($user_id, 'nsl_id_google', true);

    // Check if this is a social login
    if (empty($google_id)) {
        $all_meta = get_user_meta($user_id);
        $has_nsl = false;
        foreach ($all_meta as $key => $value) {
            if (strpos($key, 'nsl_') === 0) {
                $has_nsl = true;
                break;
            }
        }

        // If no NSL keys at all, this is not a social login
        if (!$has_nsl) {
            return;
        }
    }

    // Get user info
    $email = $wp_user->user_email;
    $first_name = get_user_meta($user_id, 'first_name', true);
    $last_name = get_user_meta($user_id, 'last_name', true);

    // If first/last name not set, try to extract from display name
    if (empty($first_name) && !empty($wp_user->display_name)) {
        $name_parts = explode(' ', $wp_user->display_name, 2);
        $first_name = $name_parts[0];
        $last_name = isset($name_parts[1]) ? $name_parts[1] : '';
    }

    // Save/link user in our customer portal database
    if (!empty($email)) {
        $cp_user_id = CP()->database->save_google_user($google_id, $email, $first_name, $last_name);

        // Store the customer portal user ID in WP user meta for quick access
        if ($cp_user_id) {
            update_user_meta($user_id, 'cp_user_id', $cp_user_id);
        }
    }
}

/**
 * Disable Google login button on WordPress admin login page
 * Only show it on the customer portal page
 */
add_filter('nsl_login_form_button', 'cp_disable_google_on_admin_login', 10, 1);

function cp_disable_google_on_admin_login($show) {
    // Check if we're on the WordPress admin login page
    global $pagenow;

    // Disable on wp-login.php
    if ($pagenow === 'wp-login.php' && !isset($_GET['action'])) {
        return false;
    }

    return $show;
}

/**
 * After Google login, redirect to customer portal instead of admin
 */
add_filter('nsl_login_redirect_url', 'cp_redirect_after_google_login', 10, 3);

function cp_redirect_after_google_login($redirect_url, $provider, $user_id) {
    // Only for Google login
    if ($provider !== 'google') {
        return $redirect_url;
    }

    // Get the customer portal page URL
    // You can change this to match your actual portal page slug/URL
    $portal_page_id = get_option('cp_portal_page_id'); // We'll need to set this

    if ($portal_page_id) {
        $redirect_url = get_permalink($portal_page_id);
    } else {
        // Fallback: try to find page with [customer_portal] shortcode
        $portal_pages = get_posts(array(
            'post_type' => 'page',
            'posts_per_page' => 1,
            's' => '[customer_portal]'
        ));

        if (!empty($portal_pages)) {
            $redirect_url = get_permalink($portal_pages[0]->ID);
        }
    }

    return $redirect_url;
}
