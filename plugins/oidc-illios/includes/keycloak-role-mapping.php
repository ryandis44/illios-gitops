<?php

/**
 * Global OIDC functions.
 * 
 * Implementation for Illios Digital LLC
 * 
 * This plugin assigns WordPress roles based on Keycloak roles at login and
 * updates user and role capabilities.
 * 
 * It marks all permissions as true for these special roles, effectively
 * granting them superuser access. They are named differently to easily
 * determine the type of user, but effectively have the same capabilities as
 * an administrator.
 */

$openid_connect = 'oidc-illios/openid-connect-generic.php';

add_action('openid-connect-generic-update-user-using-current-claim', function($user, $user_claim) {
    
    if ( ! defined('OIDC_CLIENT_ID') || ! defined('OIDC_CLIENT_SECRET') ) {
        wp_die(
            '<h1 style="color:red;font-size:2em;">CRITICAL ERROR</h1>
            <p style="font-size:1.2em;">OIDC_CLIENT_ID or OIDC_CLIENT_SECRET is not defined.<br>
            Please check your environment variables and wp-config.php file.</p>',
            'Critical Error',
            array('response' => 500)
        );
        return;
    }

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

    // Ensure custom roles exist. Also runs as an init action.
    if ( ! get_role('ssoadmin') ) {
        add_role('ssoadmin', 'Admin - SSO', ['read' => true]);
    }
    if ( ! get_role('ssoowner') ) {
        add_role('ssoowner', 'Owner - SSO', ['read' => true]);
    }
    if ( ! get_role('ssocontractor') ) {
        add_role('ssocontractor', 'Contractor - SSO', ['read' => true]);
    }


    $user->set_role(''); // Reset user role pre-authentication
    $role_weight = 0;
    foreach ( $user_claim as $key => $value ) {
        
        // Removing our access; was enabled for testing. Fully removing a future commit.
        // if ( $key == 'groups') {
        //     foreach ( $value as $group ) {
        //         if ( $group == 'GIGACHADMIN' ) {
        //             if ( $role_weight < 1000 ) {
        //                 $role_weight = 1000;
        //                 $user->set_role('ssoadmin');
        //             } else {
        //                 $user->set_role('');
        //             }
        //         }
        //     }
        // }

        if ( $key == 'resource_access' ) {

            if ( isset($value[getenv('OIDC_CLIENT_ID')]) ) {

                // Check if there's a 'roles' key within the client data
                if ( isset($value[getenv('OIDC_CLIENT_ID')]['roles']) ) {
                    $roles_data = $value[getenv('OIDC_CLIENT_ID')]['roles'];

                    // Iterate through all (Keycloak client) roles and assign WordPress roles.
                    // Roles are weighted based off permission level to prevent an administrator
                    // from being demoted to a ssocontractor if they have both roles in Keycloak
                    foreach ( $roles_data as $role ) {

                        if ( $role == 'owner' || $role == 'ssoowner' ) {
                            if ( $role_weight < 500 ) {
                                $role_weight = 500;
                                $user->set_role('ssoowner');
                            }
                        } else if ( $role == 'admin' || $role == 'administrator' ) {
                            if ( $role_weight < 100 ) {
                                $role_weight = 100;
                                $user->set_role('ssoadmin');
                            }
                        } else if ( $role == 'contractor' ) {
                            if ( $role_weight < 50 ) {
                                $role_weight = 50;
                                $user->set_role('ssocontractor');
                            }
                        } else {
                            $user->set_role('');
                        }
                    }
                }

            }
        }
    }

    if ( $role_weight == 0 ) {
        // If no roles were assigned, deny access to the site with a scarier message
        status_header(403);
        wp_die(
            '<h1 style="color:red;font-size:2em;">ACCESS DENIED</h1>
            <p style="font-size:1.2em;">You do not have the required permissions to log into this site.<br></p>',
            'Forbidden',
            array('response' => 403)
        );
        return;
    }

}, 10, 2);



const GOD_ROLES = [ 'ssoadmin', 'ssoowner', 'ssocontractor' ];

/**
 * Helper: does the user have any of our special roles?
 */
function user_is_god( $user ): bool {
	if ( ! $user || ! isset( $user->roles ) ) {
		return false;
	}
	return (bool) array_intersect( GOD_ROLES, (array) $user->roles );
}

/**
 * Neutralize `do_not_allow` from map_meta_cap()
 *    (some meta caps map to the sentinel which otherwise makes the check auto-fail)
 */
add_filter( 'map_meta_cap', function( $caps, $cap, $user_id, $args ) {
	$user = get_userdata( $user_id );
	if ( ! user_is_god( $user ) ) {
		return $caps;
	}

	// If WordPress mapped to 'do_not_allow', replace it with a benign primitive.
	if ( in_array( 'do_not_allow', $caps, true ) ) {
		$caps = array_values( array_diff( $caps, [ 'do_not_allow' ] ) );
		if ( empty( $caps ) ) {
			// 'exist' is a primitive cap that every logged-in user effectively has.
			$caps = [ 'exist' ];
		}
	}
	return $caps;
}, 0, 4 ); // priority 0 to run as early as possible

/**
 * Stamp the primitive capabilities as granted during the final check.
 */
add_filter( 'user_has_cap', function( $allcaps, $caps, $args, $user ) {
	if ( ! user_is_god( $user ) ) {
		return $allcaps;
	}

	// Mark whatever primitives WP decided to check as true.
	foreach ( (array) $caps as $cap ) {
		$allcaps[ $cap ] = true;
	}

	// Ensure a few commonly-checked primitives are present too.
	$allcaps['unfiltered_html'] = true;
	$allcaps['update_core']     = true;

	return $allcaps;
}, 10, 4 );

/**
 * Ensure the roles exist on init. Runs every time a user logs in via OIDC as well.
 */
add_action( 'init', function () {
	$labels = [
		'ssoadmin' => 'Admin - SSO',
		'ssoowner'  => 'Owner - SSO',
		'ssocontractor' => 'Contractor - SSO',
	];
	foreach ( $labels as $slug => $label ) {
		if ( ! get_role( $slug ) ) {
			add_role( $slug, $label, [ 'read' => true ] );
		}
	}
}, 5 );

?>