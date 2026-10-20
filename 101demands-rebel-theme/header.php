<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php wp_head(); ?>
    <style>
        :root {
            --j3-espresso: #1c130e;
            --j3-espresso-dark: #120c08;
            --j3-orange: #ea580c;
            --j3-amber: #f59e0b;
            --j3-parchment: #fbf8f3;
            --j3-sand: #f3ede2;
        }
        body.admin-bar .j3-topbar { top: 32px !important; }
        @media screen and (max-width: 782px) {
            body.admin-bar .j3-topbar { top: 46px !important; }
        }
        .j3-topbar {
            position: sticky !important;
            top: 0;
            z-index: 9999;
            background: #120c08 !important;
            color: #ffffff !important;
            padding: 14px 28px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            flex-wrap: wrap !important;
            gap: 16px !important;
            border-bottom: 1px solid rgba(255,255,255,0.08) !important;
            width: 100% !important;
            box-sizing: border-box !important;
        }
        .j3-badge-orange {
            background: #ea580c !important;
            color: #ffffff !important;
            font-family: 'Anton', sans-serif !important;
            font-size: 20px !important;
            letter-spacing: 0.8px !important;
            padding: 4px 12px !important;
            border-radius: 6px !important;
            line-height: 1.2 !important;
            text-decoration: none !important;
            display: inline-block !important;
        }
        .j3-badge-amber {
            background: #f59e0b !important;
            color: #120c08 !important;
            font-weight: 800 !important;
            font-size: 11px !important;
            letter-spacing: 1px !important;
            padding: 5px 10px !important;
            border-radius: 999px !important;
            text-transform: uppercase !important;
            text-decoration: none !important;
            display: inline-block !important;
        }
        .j3-nav {
            display: flex !important;
            align-items: center !important;
            gap: 22px !important;
            flex-wrap: wrap !important;
        }
        .j3-nav a, .j3-nav a:visited {
            color: #e7e5e4 !important;
            text-decoration: none !important;
            font-size: 14px !important;
            font-weight: 600 !important;
        }
        .j3-nav a:hover {
            color: #f59e0b !important;
        }
        .j3-btn-orange, .j3-btn-orange:visited {
            display: inline-flex !important;
            align-items: center !important;
            gap: 6px !important;
            background: #ea580c !important;
            color: #ffffff !important;
            font-weight: 800 !important;
            font-size: 14px !important;
            padding: 10px 20px !important;
            border-radius: 999px !important;
            text-decoration: none !important;
            border: none !important;
            cursor: pointer !important;
            box-shadow: 0 8px 20px -4px rgba(234, 88, 12, 0.5) !important;
        }
    </style>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="j3-topbar">
    <div style="display:flex;align-items:center;gap:10px;">
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="j3-badge-orange">101 DEMANDS</a>
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="j3-badge-amber">JULY 3 GLOBAL</a>
    </div>

    <nav class="j3-nav">
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home &amp; Appeal</a>
        <a href="<?php echo esc_url( home_url( '/assemblies/' ) ); ?>">Assemblies</a>
        <a href="<?php echo esc_url( home_url( '/demands/' ) ); ?>">The Demands</a>
        <a href="<?php echo esc_url( home_url( '/signatory-orgs/' ) ); ?>">Signatory Orgs</a>
        <a href="<?php echo esc_url( home_url( '/toolkits-flyers/' ) ); ?>">Toolkits &amp; Flyers</a>
        <a href="<?php echo esc_url( home_url( '/philosophy/' ) ); ?>">Philosophy</a>
    </nav>

    <div>
        <a href="<?php echo esc_url( home_url( '/#sign-appeal' ) ); ?>" class="j3-btn-orange">✍️ Sign the appeal →</a>
    </div>
</header>
<!-- === END OF HEADER.PHP === -->