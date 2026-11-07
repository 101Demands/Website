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
    <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="j3-brand-lockup">
        <!-- Holding Logo: White Sheet with #faf829 Yellow Dog-Ear Fold -->
        <svg class="j3-sheet-logo" width="40" height="48" viewBox="0 0 84 100" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M4 4H60L80 24V96H4V4Z" fill="#FFFFFF" stroke="#000000" stroke-width="4"/>
            <path d="M60 4V24H80L60 4Z" fill="#FAF829" stroke="#000000" stroke-width="4" stroke-linejoin="round"/>
            <rect x="21" y="24" width="7" height="34" fill="#000000"/>
            <rect x="33" y="24" width="16" height="34" rx="7" stroke="#000000" stroke-width="6.5" fill="none"/>
            <rect x="54" y="24" width="7" height="34" fill="#000000"/>
            <polygon points="54,24 61,24 61,31" fill="#FAF829"/>
            <rect x="8" y="65" width="68" height="20" fill="#000000"/>
            <text x="42" y="80" text-anchor="middle" fill="#FFFFFF" font-family="'Anton', Impact, sans-serif" font-size="16" letter-spacing="1">DEMANDS</text>
        </svg>

        <div class="j3-brand-text">
            <span class="j3-brand-date">03<span class="j3-dot-white">.</span>07</span>
            <span class="j3-brand-sub"><?php esc_html_e( '101 DEMANDS', '101demands-theme' ); ?></span>
        </div>
    </a>

    <nav class="j3-nav">
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home & Appeal', '101demands-theme' ); ?></a>
        <a href="<?php echo esc_url( home_url( '/assemblies/' ) ); ?>"><?php esc_html_e( 'Assemblies', '101demands-theme' ); ?></a>
        <?php /* Paused for Phase 1 Launch: <a href="<?php echo esc_url( home_url( '/demands/' ) ); ?>"><?php esc_html_e( 'The Demands', '101demands-theme' ); ?></a> */ ?>
        <a href="<?php echo esc_url( home_url( '/signatory-orgs/' ) ); ?>"><?php esc_html_e( 'Signatory Orgs', '101demands-theme' ); ?></a>
        <a href="<?php echo esc_url( home_url( '/toolkits-flyers/' ) ); ?>"><?php esc_html_e( 'Toolkits & Flyers', '101demands-theme' ); ?></a>
        <a href="<?php echo esc_url( home_url( '/philosophy/' ) ); ?>"><?php esc_html_e( 'Philosophy', '101demands-theme' ); ?></a>
    </nav>

    <div class="j3-header-actions">
        <span class="j3-global-pill">🌍 <?php esc_html_e( 'GLOBAL', '101demands-theme' ); ?></span>
        <a href="<?php echo esc_url( home_url( '/#sign-appeal' ) ); ?>" class="j3-btn-orange"><?php esc_html_e( 'Sign the appeal →', '101demands-theme' ); ?></a>
    </div>
</header>
<!-- === END OF HEADER.PHP === -->