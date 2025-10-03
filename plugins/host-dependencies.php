<?php

   // Ensure this is being run in a WordPress environment
   if ( ! defined( 'ABSPATH' ) ) {
       exit; // Exit if accessed directly
   }

   // Load required plugins from the mu-plugins directory
   require WPMU_PLUGIN_DIR . '/required-by-host/oidc-illios/openid-connect-generic.php';
   require WPMU_PLUGIN_DIR . '/required-by-host/cache-illios/cache-illios.php';
   