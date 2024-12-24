<?php
// Load all plugins from subdirectories in the mu-plugins directory

// Ensure this is being run in a WordPress environment
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Function to include all .php files from given directory recursively
function include_all_plugins($directory) {
    // Check if the directory exists
    if (!is_dir($directory)) {
        return;
    }

    // Get all .php files in the current directory
    foreach (glob($directory . '/*.php') as $file) {
        require_once $file;
    }

    // Get all subdirectories and include .php files from them
    foreach (glob($directory . '/*', GLOB_ONLYDIR) as $subDir) {
        include_all_plugins($subDir); // Recursively include from subdirectories
    }
}

// Path to the mu-plugins directory
$muPluginsDirectory = WPMU_PLUGIN_DIR;

// Include all PHP files found within the mu-plugins directory and its subdirectories
include_all_plugins($muPluginsDirectory);