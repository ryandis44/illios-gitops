<?php
   // Load the OIDC Generic Client for Illios

   // Ensure this is being run in a WordPress environment
   if ( ! defined( 'ABSPATH' ) ) {
       exit; // Exit if accessed directly
   }

   // Require the OIDC Generic Client file
   require WPMU_PLUGIN_DIR . '/oidc-illios/openid-connect-generic.php'; // Adjust the path if needed
   require WPMU_PLUGIN_DIR . '/cache-illios/cache-illios.php'; // Adjust the path if needed
   