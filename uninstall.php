<?php
/**
 * Uninstall cleanup for WP OG Meta.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

delete_option( 'wogm_settings' );

// Remove per-post overrides.
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('_wogm_title', '_wogm_description', '_wogm_image')" );
