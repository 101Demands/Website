<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<header class="j3-topbar">
    <div style="display:flex;align-items:center;gap:10px;">
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="j3-badge-orange">101 DEMANDS</a>
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="j3-badge-amber"><?php esc_html_e( 'JULY 3 GLOBAL', '101demands-theme' ); ?></a>
    </div>

    <nav class="j3-nav">
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home & Appeal', '101demands-theme' ); ?></a>
        <a href="<?php echo esc_url( home_url( '/assemblies/' ) ); ?>"><?php esc_html_e( 'Assemblies', '101demands-theme' ); ?></a>
        <a href="<?php echo esc_url( home_url( '/demands/' ) ); ?>"><?php esc_html_e( 'The Demands', '101demands-theme' ); ?></a>
        <a href="<?php echo esc_url( home_url( '/signatory-orgs/' ) ); ?>"><?php esc_html_e( 'Signatory Orgs', '101demands-theme' ); ?></a>
        <a href="<?php echo esc_url( home_url( '/toolkits-flyers/' ) ); ?>"><?php esc_html_e( 'Toolkits & Flyers', '101demands-theme' ); ?></a>
        <a href="<?php echo esc_url( home_url( '/philosophy/' ) ); ?>"><?php esc_html_e( 'Philosophy', '101demands-theme' ); ?></a>
    </nav>

    <div>
        <a href="<?php echo esc_url( home_url( '/#sign-appeal' ) ); ?>" class="j3-btn-orange">✍️ <?php esc_html_e( 'Sign the appeal →', '101demands-theme' ); ?></a>
    </div>
</header>
<!-- === END OF HEADER.PHP === -->
