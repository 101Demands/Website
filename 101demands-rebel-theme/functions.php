<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'after_setup_theme', function() {
    load_theme_textdomain( '101demands-theme', get_template_directory() . '/languages' );
    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'align-wide' );
    register_nav_menus([
        'primary' => __( 'Primary Top Navigation', '101demands-theme' ),
        'footer'  => __( 'Footer Navigation', '101demands-theme' ),
    ]);
});

add_action( 'wp_enqueue_scripts', function() {
    wp_enqueue_style( '101demands-main', get_template_directory_uri() . '/lib/css/main.css', [], '1.0.2' );
    wp_enqueue_script( '101demands-main', get_template_directory_uri() . '/lib/js/main.js', [], '1.0.2', true );
});
