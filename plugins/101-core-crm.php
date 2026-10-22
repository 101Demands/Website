<?php
/**
 * Plugin Name: 101 Demands — Module 1: Core Data & SQL Engine
 * Description: Registers Countries, Pillars, Assemblies, Coalition Orgs, Signatories, and 101 Demands. Creates the high-scale wp_101_votes SQL table and resets prototype vote counts to zero.
 * Version:     1.0.0
 * Author:      101 Demands Global Tech Collective
 * License:     GPL-3.0+
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'init', function() {

    register_taxonomy( 'mobilization_region', [ 'mobilization_hub', 'mobilization_org', 'mobilization_demand', 'mobilization_signer' ], [
        'labels'            => [ 'name' => 'Countries & Regions', 'singular_name' => 'Country / Region' ],
        'public'            => true,
        'hierarchical'      => true,
        'show_admin_column' => true,
        'show_in_rest'      => true,
    ]);

    register_taxonomy( 'mobilization_pillar', [ 'mobilization_demand', 'mobilization_org' ], [
        'labels'            => [ 'name' => 'Pillars & Sectors', 'singular_name' => 'Pillar / Sector' ],
        'public'            => true,
        'hierarchical'      => true,
        'show_admin_column' => true,
        'show_in_rest'      => true,
    ]);

    register_post_type( 'mobilization_hub', [
        'labels'       => [ 'name' => 'Assemblies & Events', 'singular_name' => 'Assembly / Event' ],
        'public'       => true,
        'has_archive'  => true,
        'rewrite'      => [ 'slug' => 'assemblies' ],
        'menu_icon'    => 'dashicons-location-alt',
        'supports'     => [ 'title', 'editor', 'custom-fields' ],
        'show_in_rest' => true,
    ]);

    register_post_type( 'mobilization_org', [
        'labels'       => [ 'name' => 'Coalition Orgs', 'singular_name' => 'Coalition Organisation' ],
        'public'       => true,
        'has_archive'  => true,
        'rewrite'      => [ 'slug' => 'coalition' ],
        'menu_icon'    => 'dashicons-groups',
        'supports'     => [ 'title', 'editor', 'thumbnail', 'custom-fields' ],
        'show_in_rest' => true,
    ]);

    register_post_type( 'mobilization_demand', [
        'labels'       => [ 'name' => '101 Demands', 'singular_name' => 'Demand / Measure' ],
        'public'       => true,
        'has_archive'  => true,
        'rewrite'      => [ 'slug' => 'demands' ],
        'menu_icon'    => 'dashicons-megaphone',
        'supports'     => [ 'title', 'editor', 'excerpt', 'custom-fields' ],
        'show_in_rest' => true,
    ]);

    register_post_type( 'mobilization_signer', [
        'labels'       => [ 'name' => 'Appeal Signatories', 'singular_name' => 'Signatory' ],
        'public'       => false,
        'show_ui'      => true,
        'menu_icon'    => 'dashicons-edit-page',
        'supports'     => [ 'title', 'custom-fields' ],
    ]);
});

register_activation_hook( __FILE__, function() {
    global $wpdb;
    $table_name      = $wpdb->prefix . '101_votes';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        demand_id bigint(20) unsigned NOT NULL,
        voter_hash varchar(64) NOT NULL,
        vote_value varchar(16) NOT NULL,
        verification_tier varchar(20) DEFAULT 'guest',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY unique_demand_voter (demand_id, voter_hash),
        KEY demand_id (demand_id)
    ) $charset_collate;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );

    $demands = get_posts([ 'post_type' => 'mobilization_demand', 'posts_per_page' => -1, 'post_status' => 'any' ]);
    foreach ( $demands as $d ) {
        update_post_meta( $d->ID, '_demand_votes_agree', 0 );
        update_post_meta( $d->ID, '_demand_votes_disagree', 0 );
        update_post_meta( $d->ID, '_demand_assemblies_count', 1 );
    }
    flush_rewrite_rules();
});
require_once plugin_dir_path( __FILE__ ) . 'class-101-ai-engine.php';