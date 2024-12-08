<?php

/**
 * Global OIDCG functions.
 * 
 * Implementation for Illios Digital LLC
 * 
 * This file serves to hide this plugin and its functionalities from any user that is not a
 * super administrator.
 * 
 * Additionally, this plugin assigns WordPress roles based on Keycloak roles at login and
 * updates user and role capabilities.
 */

$openid_connect = 'oidc-illios/openid-connect-generic.php';

// Prevent deactivation and deletion of this plugin
add_action('admin_init', function() use ($openid_connect) {
    if (isset($_GET['action']) && $_GET['action'] === 'deactivate' && isset($_GET['plugin'])) {
        if ($_GET['plugin'] === $openid_connect && !current_user_can('superadmin')) {
            wp_die(__('You do not have permission to deactivate this plugin.'));
        }
    }
});

// Hide the plugin from the plugins list
add_action('admin_menu', function() use ($openid_connect) {
    if (!current_user_can('superadmin')) {
        add_action('admin_head', function() use ($openid_connect) {
            ?>
            <script type="text/javascript">
            jQuery(document).ready(function($) {
                $('tr[data-plugin="<?php echo $openid_connect; ?>"]').hide(); // Hides the plugin row in the plugins list
            });
            </script>
            <?php
        });
    }
});

add_action('openid-connect-generic-update-user-using-current-claim', function($user, $user_claim) {
    
    /* Debugging
    Recursive print function. This function will print all the keys and values of a multi-dimensional array;
    depth of array does not matter.

    Prints to browser
    */
    if ( False ) {

        wp_roles()->roles;
        foreach ( wp_roles()->roles as $key => $value ) {
            echo $key . ': ' . $value['name'] . '<br>';
        }
        function printArray($array, $parentKey = '') {
            foreach ($array as $key => $value) {
                $fullKey = $parentKey ? $parentKey . '.' . $key : $key; // Create a new key for nested arrays
                if (is_array($value)) {
                    printArray($value, $fullKey); // Call recursively for nested arrays
                } else {
                    echo $fullKey . ': ' . $value . '<br>'; // Print the value
                }
            }
        }

        printArray($user_claim);
        return;
    }

    // Define all WordPress capabilities
    $all_capabilities = array(
        'keycloak' => false,
        'read' => true,
        'activate_plugins' => true,
        'edit_plugins' => true,
        'edit_dashboard' => true,
        'manage_options' => true,
        'edit_theme_options' => true,
        'install_plugins' => true,
        'update_plugins' => true,
        'delete_plugins' => true,
        'install_themes' => true,
        'update_themes' => true,
        'delete_themes' => true,
        'edit_users' => true,
        'delete_users' => true,
        'create_users' => true,
        'unfiltered_html' => true,
        'edit_files' => true,
        'edit_others_posts' => true,
        'edit_published_posts' => true,
        'publish_posts' => true,
        'edit_pages' => true,
        'edit_others_pages' => true,
        'edit_published_pages' => true,
        'publish_pages' => true,
        'delete_pages' => true,
        'delete_others_pages' => true,
        'delete_published_pages' => true,
        'delete_private_pages' => true,
        'edit_private_pages' => true,
        'read_private_pages' => true,
        'delete_private_posts' => true,
        'edit_private_posts' => true,
        'read_private_posts' => true,
        'manage_categories' => true,
        'manage_links' => true,
        'moderate_comments' => true,
        'upload_files' => true,
        'import' => true,
        'export' => true,
        'list_users' => true,
        'remove_users' => true,
        'promote_users' => true,
        'switch_themes' => true,
        'customize' => true,
        'update_core' => true,
        'delete_site' => true,
    );


    // Remove all user capabilities
    foreach ( $all_capabilities as $capability => $value ) {$user->remove_cap($capability);}


    // Remove all roles
    wp_roles()->roles;
    foreach ( wp_roles()->roles as $key => $value ) {remove_role($key);}


    // Subscriber role (for default role)
    add_role('subscriber', 'Subscriber', array('read' => true, 'keycloak' => false));


    // Super Administrator role
    add_role(
        'superadmin',
        'Super Administrator',
        array(
            'keycloak' => true,
            'read' => true,
            'activate_plugins' => true,
            'edit_plugins' => true,
            'edit_dashboard' => true,
            'manage_options' => true,
            'edit_theme_options' => true,
            'install_plugins' => true,
            'update_plugins' => true,
            'delete_plugins' => true,
            'install_themes' => true,
            'update_themes' => true,
            'delete_themes' => true,
            'edit_users' => true,
            'delete_users' => true,
            'create_users' => true,
            'unfiltered_html' => true,
            'edit_files' => true,
            'edit_others_posts' => true,
            'edit_published_posts' => true,
            'publish_posts' => true,
            'edit_pages' => true,
            'edit_others_pages' => true,
            'edit_published_pages' => true,
            'publish_pages' => true,
            'delete_pages' => true,
            'delete_others_pages' => true,
            'delete_published_pages' => true,
            'delete_private_pages' => true,
            'edit_private_pages' => true,
            'read_private_pages' => true,
            'delete_private_posts' => true,
            'edit_private_posts' => true,
            'read_private_posts' => true,
            'manage_categories' => true,
            'manage_links' => true,
            'moderate_comments' => true,
            'upload_files' => true,
            'import' => true,
            'export' => true,
            'list_users' => true,
            'remove_users' => true,
            'promote_users' => true,
            'switch_themes' => true,
            'customize' => true,
            'update_core' => true,
            'delete_site' => true,
        )
    );


    // Administrator role. Same as Super Administrator; only to to show a distinction in the WordPress admin panel
    add_role(
        'administrator',
        'Administrator',
        array(
            'keycloak' => false,
            'read' => true,
            'activate_plugins' => true,
            'edit_plugins' => true,
            'edit_dashboard' => true,
            'manage_options' => true,
            'edit_theme_options' => true,
            'install_plugins' => true,
            'update_plugins' => true,
            'delete_plugins' => true,
            'install_themes' => true,
            'update_themes' => true,
            'delete_themes' => true,
            'edit_users' => true,
            'delete_users' => true,
            'create_users' => true,
            'unfiltered_html' => true,
            'edit_files' => true,
            'edit_others_posts' => true,
            'edit_published_posts' => true,
            'publish_posts' => true,
            'edit_pages' => true,
            'edit_others_pages' => true,
            'edit_published_pages' => true,
            'publish_pages' => true,
            'delete_pages' => true,
            'delete_others_pages' => true,
            'delete_published_pages' => true,
            'delete_private_pages' => true,
            'edit_private_pages' => true,
            'read_private_pages' => true,
            'delete_private_posts' => true,
            'edit_private_posts' => true,
            'read_private_posts' => true,
            'manage_categories' => true,
            'manage_links' => true,
            'moderate_comments' => true,
            'upload_files' => true,
            'import' => true,
            'export' => true,
            'list_users' => true,
            'remove_users' => true,
            'promote_users' => true,
            'switch_themes' => true,
            'customize' => true,
            'update_core' => true,
            'delete_site' => true,
        )
    );


    // Contractor role
    add_role(
        'contractor',
        'Contractor',
        array(
            'keycloak' => false,
            'read' => true,
            'activate_plugins' => true,
            'edit_plugins' => true,
            'edit_dashboard' => true,
            'manage_options' => true,
            'edit_theme_options' => true,
            'install_plugins' => true,
            'update_plugins' => true,
            'delete_plugins' => true,
            'install_themes' => true,
            'update_themes' => true,
            'delete_themes' => true,
            'edit_users' => false,
            'delete_users' => false,
            'create_users' => false,
            'unfiltered_html' => false,
            'edit_files' => true,
            'edit_others_posts' => true,
            'edit_published_posts' => true,
            'publish_posts' => true,
            'edit_pages' => true,
            'edit_others_pages' => true,
            'edit_published_pages' => true,
            'publish_pages' => true,
            'delete_pages' => true,
            'delete_others_pages' => true,
            'delete_published_pages' => true,
            'delete_private_pages' => true,
            'edit_private_pages' => true,
            'read_private_pages' => true,
            'delete_private_posts' => false,
            'edit_private_posts' => false,
            'read_private_posts' => false,
            'manage_categories' => true,
            'manage_links' => true,
            'moderate_comments' => false,
            'upload_files' => true,
            'import' => true,
            'export' => true,
            'list_users' => true,
            'remove_users' => false,
            'promote_users' => false,
            'switch_themes' => true,
            'customize' => true,
            'update_core' => false,
            'delete_site' => false,
        )
    );


    $user->set_role(''); // Reset user role pre-authentication
    $role_weight = 0;
    foreach ( $user_claim as $key => $value ) {
        
        if ( $key == 'groups') {
            foreach ( $value as $group ) {
                if ( $group == 'GIGACHADMIN' ) {
                    if ( $role_weight < 1000 ) {
                        $role_weight = 1000;
                        $user->set_role('superadmin');
                    } else {
                        $user->set_role('');
                    }
                }
            }
        }

        if ( $key == 'roles' ) {

            // Iterate through all (Keycloak client) roles and assign WordPress roles.
            // Roles are weighted based off permission level to prevent an administrator
            // from being demoted to a contractor if they have both roles in Keycloak
            foreach ( $value as $role ) {
                if ( $role == 'admin' ) {
                    if ( $role_weight < 100 ) {
                        $role_weight = 100;
                        $user->set_role('administrator');
                    }
                } else if ( $role == 'contractor' ) {
                    if ( $role_weight < 50 ) {
                        $role_weight = 50;
                        $user->set_role('contractor');
                    }
                } else {
                    $user->set_role('');
                }
            }
        }
    }
}, 10, 2);

?>