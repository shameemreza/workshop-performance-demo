<?php
/**
 * Uninstall Workshop Performance Demo
 *
 * Removes all plugin data when the plugin is deleted.
 *
 * @package Workshop_Performance_Demo
 * @since 1.0.0
 */

// Exit if not uninstalling.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete all workshop_test custom post type posts.
$workshop_posts = get_posts(
	array(
		'post_type'   => 'workshop_test',
		'numberposts' => -1,
		'post_status' => 'any',
	)
);

foreach ( $workshop_posts as $post ) {
	wp_delete_post( $post->ID, true );
}

// Drop custom database table.
global $wpdb;
$table_name = $wpdb->prefix . 'workshop_demo_logs';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Cleanup on uninstall
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table_name ) );

// Clean up transients.
delete_transient( 'workshop_demo_cache' );

// Clean up any options if they exist.
delete_option( 'workshop_demo_version' );
delete_option( 'workshop_demo_settings' );

// Clear any scheduled cron jobs.
$timestamp = wp_next_scheduled( 'workshop_demo_cron' );
if ( $timestamp ) {
	wp_unschedule_event( $timestamp, 'workshop_demo_cron' );
}
