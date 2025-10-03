<?php
/**
 * Host Dependencies Loader
 *
 * A WordPress plugin to load required host dependencies
 *
 * @package   HostDependenciesLoader
 * @category  Utility
 * @author    Illios Digital LLC
 * @copyright 2025 Illios Digital LLC
 * @license   http://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0+
 *
 * @wordpress-plugin
 * Plugin Name:       Host Dependencies Loader
 * Plugin URI:        https://github.com/ryandis44/illios-gitops
 * Description:       Plugin to load required host dependencies like SSO and cache management.
 * Version:           1.0.0
 * Requires at least: 5.0
 * Requires PHP:      7.4
 * Author:            Illios Digital LLC
 * Author URI:        https://illiosdigital.com
 * Text Domain:       host-dependencies-loader
 * Domain Path:       /languages
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

   // Ensure this is being run in a WordPress environment
   if ( ! defined( 'ABSPATH' ) ) {
       exit; // Exit if accessed directly
   }

   // Load required plugins from the mu-plugins directory
   require WPMU_PLUGIN_DIR . '/required-by-host/oidc-illios/openid-connect-generic.php';
   require WPMU_PLUGIN_DIR . '/required-by-host/cache-illios/cache-illios.php';
   