<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'after_setup_theme', function() {
    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'align-wide' );
    register_nav_menus([
        'primary' => __( 'Primary Top Navigation', '101demands-theme' ),
        'footer'  => __( 'Footer Navigation', '101demands-theme' ),
    ]);
});

add_action( 'wp_enqueue_scripts', function() {
    wp_enqueue_style( '101demands-fonts', 'https://fonts.googleapis.com/css2?family=Anton&family=Caveat:wght@700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap', [], null );
    wp_enqueue_style( '101demands-style', get_stylesheet_uri(), [ '101demands-fonts' ], '1.0.0' );
});