<?php
/**
 * Plugin Name: 101 Demands — Module 3: Assemblies, Map & Calendar
 * Description: Powers [assemblies_intro], [mobilization_map] (supports mode="teaser" for homepage or full mode for /assemblies/), [mobilization_map_global], and [teamup_calendar].
 * Version:     1.3.0
 * Author:      101 Demands Global Tech Collective
 * Text Domain: 101-map-assemblies
 * Domain Path: /languages
 * License:     GPL-3.0+
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Load plugin textdomain for translations.
 */
add_action( 'init', function() {
    load_plugin_textdomain( '101-map-assemblies', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    $locale = determine_locale();
    $mofile = plugin_dir_path( __FILE__ ) . 'languages/101-map-assemblies-' . $locale . '.mo';
    if ( file_exists( $mofile ) ) {
        load_textdomain( '101-map-assemblies', $mofile );
    }
} );

/**
 * Normalize legacy _hub_type values to the 5-stage momentum ladder:
 * 1: seed     (#000000 Black — Telegram group exists, dormant)
 * 2: chat     (#56666B Blue Slate — Active Telegram chat)
 * 3: meetup   (#A8AF4A Lime Moss — Local planning meet-up in pub/space)
 * 4: assembly (#D4E117 Electric Chartreuse — Confirmed Assembly planned)
 * 5: capital  (#FAF829 Brand Yellow — July 3rd Capital / Major City Mobilisation)
 */
function july3_mod3_normalize_stage( $raw_type, $chat_url = '', $meetup = '', $date = '' ) {
    $raw_type = strtolower( trim( (string) $raw_type ) );
    if ( in_array( $raw_type, array( 'seed', 'chat', 'meetup', 'assembly', 'capital' ), true ) ) {
        return $raw_type;
    }
    if ( 'capital' === $raw_type ) {
        return 'capital';
    }
    if ( 'confirmed' === $raw_type ) {
        return 'assembly';
    }
    if ( 'event' === $raw_type ) {
        return 'meetup';
    }
    if ( 'prep' === $raw_type ) {
        // If a 'prep' pin already has a physical meeting place or date, show as Stage 3 (meetup); otherwise Stage 1 (seed)
        if ( ! empty( $meetup ) || ! empty( $date ) ) {
            return 'meetup';
        }
        return 'seed';
    }
    return 'seed';
}

/**
 * Ensure the /assemblies/ WordPress Page takes priority over the CPT archive slug.
 */
add_filter( 'register_post_type_args', function( $args, $post_type ) {
    if ( 'mobilization_hub' === $post_type ) {
        $args['has_archive'] = false;
        $args['rewrite']     = array( 'slug' => 'assembly-hub' );
    }
    return $args;
}, 10, 2 );

function july3_mod3_render_assemblies_intro() {
    ob_start();
    ?>
    <section class="j3-container" style="padding:56px 24px 24px;text-align:center;">
        <div style="background:#000;color:var(--j3-yellow,#faf829);display:inline-block;padding:4px 12px;font-size:12px;font-weight:800;letter-spacing:1.5px;text-transform:uppercase;margin-bottom:8px;"><?php esc_html_e( 'REAL DEMOCRACY IN ACTION', '101-map-assemblies' ); ?></div>
        <h1 class="j3-poster-title" style="font-size:clamp(42px,5vw,58px);margin:6px 0 8px;"><?php esc_html_e( 'CITIZEN &amp; COMMUNITY ASSEMBLIES', '101-map-assemblies' ); ?></h1>
        <div class="j3-brush" style="margin-bottom:28px;"><?php esc_html_e( 'The key to unlocking real change across every demand', '101-map-assemblies' ); ?></div>

        <div style="max-width:1040px;margin:0 auto;background:#000000;color:#fff;border:3px solid #000000;border-radius:2px;padding:44px 36px;text-align:left;box-shadow:10px 10px 0px var(--j3-yellow,#faf829);">
            <p style="font-size:17px;line-height:1.7;color:#f5f5f4;margin:0 0 28px;max-width:860px;">
                <?php echo wp_kses_post( __( 'For decades, vital reforms on climate, wealth inequality, housing, and peace have been blocked by short-term party politics and entrenched lobbying interests. <strong>Assemblies are how we break that deadlock.</strong> When ordinary people are given the time, independent evidence, and space to deliberate together, they consistently reach fair, courageous solutions that career politicians refuse to touch.', '101-map-assemblies' ) ); ?>
            </p>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:20px;">
                <div style="background:#111111;border:2px solid #2b3336;border-radius:2px;padding:24px;">
                    <div style="color:var(--j3-yellow,#faf829);font-weight:800;font-size:13px;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;"><?php esc_html_e( '01 // BEYOND GOVERNANCE FAILURES', '101-map-assemblies' ); ?></div>
                    <h3 style="margin:0 0 8px;font-size:19px;font-weight:800;color:#fff;"><?php esc_html_e( 'Power in the Hands of the People', '101-map-assemblies' ); ?></h3>
                    <p style="margin:0;font-size:14px;line-height:1.6;color:#d6d3d1;">
                        <?php esc_html_e( 'Instead of outsourcing our future to distant political classes and corporate lobbyists, assemblies put decision-making directly into the hands of everyday citizens and local communities.', '101-map-assemblies' ); ?>
                    </p>
                </div>

                <div style="background:#111111;border:2px solid #2b3336;border-radius:2px;padding:24px;">
                    <div style="color:var(--j3-yellow,#faf829);font-weight:800;font-size:13px;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;"><?php esc_html_e( '02 // INFORMED DELIBERATION', '101-map-assemblies' ); ?></div>
                    <h3 style="margin:0 0 8px;font-size:19px;font-weight:800;color:#fff;"><?php esc_html_e( 'The Key That Unlocks Every Demand', '101-map-assemblies' ); ?></h3>
                    <p style="margin:0;font-size:14px;line-height:1.6;color:#d6d3d1;">
                        <?php esc_html_e( 'Whether the issue is taxing extreme wealth, restoring nature, or fixing housing, assemblies bring communities and independent experts together to build mandates with real public legitimacy.', '101-map-assemblies' ); ?>
                    </p>
                </div>

                <div style="background:#111111;border:2px solid #2b3336;border-radius:2px;padding:24px;">
                    <div style="color:var(--j3-yellow,#faf829);font-weight:800;font-size:13px;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;"><?php esc_html_e( '03 // LOCAL TO GLOBAL', '101-map-assemblies' ); ?></div>
                    <h3 style="margin:0 0 8px;font-size:19px;font-weight:800;color:#fff;"><?php esc_html_e( 'From Town Halls to July 3rd', '101-map-assemblies' ); ?></h3>
                    <p style="margin:0;font-size:14px;line-height:1.6;color:#d6d3d1;">
                        <?php esc_html_e( 'Local town halls and grassroots assemblies are meeting now to deliberate, compile local evidence reports, and build towards the mass capital-city convergences on July 3rd.', '101-map-assemblies' ); ?>
                    </p>
                </div>
            </div>
        </div>
    </section>
    <?php
    return ob_get_clean();
}
add_shortcode( 'assemblies_intro', 'july3_mod3_render_assemblies_intro' );

/**
 * Retrieve mobilization hub pins from current site or across all multisite sub-sites.
 *
 * @param bool $is_global Whether to fetch pins from all network sites in a multisite install.
 * @return array Array of pin data.
 */
function july3_mod3_get_hub_pins( $is_global = false ) {
    $pins = array();

    if ( $is_global && is_multisite() ) {
        $sites = get_sites( array(
            'number'   => 500,
            'deleted'  => 0,
            'archived' => 0,
            'spam'     => 0,
        ) );

        foreach ( $sites as $site ) {
            switch_to_blog( (int) $site->blog_id );

            $hubs = get_posts( array(
                'post_type'        => 'mobilization_hub',
                'posts_per_page'   => -1,
                'post_status'      => 'publish',
                'suppress_filters' => false,
            ) );

            $site_name = get_bloginfo( 'name' );

            foreach ( $hubs as $h ) {
                $lat = get_post_meta( $h->ID, '_hub_lat', true );
                $lng = get_post_meta( $h->ID, '_hub_lng', true );
                if ( $lat && $lng ) {
                    $raw_type = get_post_meta( $h->ID, '_hub_type', true );
                    $meetup   = get_post_meta( $h->ID, '_hub_meetup', true );
                    $date     = get_post_meta( $h->ID, '_hub_next_date', true );
                    $chat     = get_post_meta( $h->ID, '_hub_chat_url', true );
                    $stage    = july3_mod3_normalize_stage( $raw_type, $chat, $meetup, $date );

                    $pins[] = array(
                        'title'     => $h->post_title,
                        'lat'       => (float) $lat,
                        'lng'       => (float) $lng,
                        'type'      => $stage,
                        'meetup'    => $meetup,
                        'date'      => $date,
                        'chat'      => $chat,
                        'site_name' => $site_name,
                    );
                }
            }

            restore_current_blog();
        }
    } else {
        $hubs = get_posts( array(
            'post_type'        => 'mobilization_hub',
            'posts_per_page'   => -1,
            'post_status'      => 'publish',
            'suppress_filters' => false,
        ) );

        $site_name = get_bloginfo( 'name' );

        foreach ( $hubs as $h ) {
            $lat = get_post_meta( $h->ID, '_hub_lat', true );
            $lng = get_post_meta( $h->ID, '_hub_lng', true );
            if ( $lat && $lng ) {
                $raw_type = get_post_meta( $h->ID, '_hub_type', true );
                $meetup   = get_post_meta( $h->ID, '_hub_meetup', true );
                $date     = get_post_meta( $h->ID, '_hub_next_date', true );
                $chat     = get_post_meta( $h->ID, '_hub_chat_url', true );
                $stage    = july3_mod3_normalize_stage( $raw_type, $chat, $meetup, $date );

                $pins[] = array(
                    'title'     => $h->post_title,
                    'lat'       => (float) $lat,
                    'lng'       => (float) $lng,
                    'type'      => $stage,
                    'meetup'    => $meetup,
                    'date'      => $date,
                    'chat'      => $chat,
                    'site_name' => $site_name,
                );
            }
        }
    }

    return $pins;
}

/**
 * Determine default map center & zoom based on current subdomain or global flag.
 */
function july3_mod3_get_default_view( $is_global = false, $is_teaser = false ) {
    if ( $is_global ) {
        return array( 'lat' => 48.5, 'lng' => 6.0, 'zoom' => 4, 'fit_bounds' => true );
    }

    $host = isset( $_SERVER['HTTP_HOST'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) ) : '';
    if ( strpos( $host, 'uk.' ) === 0 ) {
        return array( 'lat' => 54.2, 'lng' => -2.8, 'zoom' => $is_teaser ? 5 : 6, 'fit_bounds' => false );
    }
    if ( strpos( $host, 'fr.' ) === 0 ) {
        return array( 'lat' => 46.6, 'lng' => 2.4, 'zoom' => $is_teaser ? 5 : 6, 'fit_bounds' => false );
    }
    if ( strpos( $host, 'de.' ) === 0 ) {
        return array( 'lat' => 51.1, 'lng' => 10.4, 'zoom' => $is_teaser ? 5 : 6, 'fit_bounds' => false );
    }

    return array( 'lat' => 50.0, 'lng' => 4.0, 'zoom' => $is_teaser ? 4 : 5, 'fit_bounds' => true );
}

function july3_mod3_render_map( $atts ) {
    static $map_instance = 0;
    $map_instance++;

    $atts = shortcode_atts( array(
        'mode'   => 'full',
        'global' => false,
    ), $atts );

    $is_teaser = ( 'teaser' === $atts['mode'] );
    $is_global = filter_var( $atts['global'], FILTER_VALIDATE_BOOLEAN );

    // On main global hub (101demands.org without country subdomain), default to global view
    $host = isset( $_SERVER['HTTP_HOST'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) ) : '';
    if ( '101demands.org' === $host || 'www.101demands.org' === $host ) {
        $is_global = true;
    }

    wp_enqueue_style( 'leaflet-css', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', array(), '1.9.4' );
    wp_enqueue_script( 'leaflet-js', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array(), '1.9.4', true );

    $pins           = july3_mod3_get_hub_pins( $is_global );
    $default_view   = july3_mod3_get_default_view( $is_global, $is_teaser );
    $add_url        = home_url( '/add-your-gathering/' );
    $map_wrapper_id = 'july3-map-wrapper-' . $map_instance;
    $map_canvas_id  = 'july3-osm-map-' . $map_instance;
    $map_height     = $is_teaser ? '440px' : '560px';

    ob_start();
    ?>
    <section id="<?php echo esc_attr( $map_wrapper_id ); ?>" class="j3-container july3-map-container" style="padding:32px 24px 48px;text-align:center;">
        <div id="map-preview" style="background:#000;color:var(--j3-yellow,#faf829);display:inline-block;padding:4px 12px;font-size:12px;font-weight:800;letter-spacing:1.5px;text-transform:uppercase;margin-bottom:6px;">
            <?php echo $is_global ? esc_html__( 'NETWORK-WIDE ASSEMBLIES, TOWN HALLS &amp; CONVERGENCES', '101-map-assemblies' ) : esc_html__( 'LOCAL ASSEMBLIES, TOWN HALLS &amp; CONVERGENCES', '101-map-assemblies' ); ?>
        </div>
        <h2 class="j3-poster-title" style="font-size:44px;margin:4px 0 6px;"><?php esc_html_e( 'THE ASSEMBLIES &amp; MOBILISATION MAP', '101-map-assemblies' ); ?></h2>
        <div class="j3-brush" style="margin-bottom:18px;"><?php esc_html_e( 'Find your local group, join the Telegram chat, or put your town on the map', '101-map-assemblies' ); ?></div>

        <!-- Compact Filter & Action Bar Above the Map (Keeps Map High on Page) -->
        <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px;justify-content:space-between;align-items:center;background:#f4f4f0;border:2px solid #000;padding:10px 14px;border-radius:2px;">
            <div style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;">
                <button type="button" class="july3-filter-btn is-active" data-filter="all" style="display:inline-flex;align-items:center;gap:6px;padding:7px 13px;border-radius:2px;border:2px solid #000;background:#000;color:var(--j3-yellow,#faf829);font-weight:800;font-size:12px;text-transform:uppercase;cursor:pointer;">
                    <?php printf( esc_html__( 'All Pins (%d)', '101-map-assemblies' ), count( $pins ) ); ?>
                </button>
                <button type="button" class="july3-filter-btn" data-filter="seed" style="display:inline-flex;align-items:center;gap:6px;padding:7px 12px;border-radius:2px;border:2px solid #000;background:#fff;color:#000;font-weight:700;font-size:12px;cursor:pointer;">
                    <span style="display:inline-block;width:11px;height:11px;border-radius:50%;background:#000000;border:2px solid #ffffff;box-shadow:0 0 0 1.5px #000;flex-shrink:0;"></span>
                    <?php esc_html_e( '1. Telegram Group', '101-map-assemblies' ); ?>
                </button>
                <button type="button" class="july3-filter-btn" data-filter="chat" style="display:inline-flex;align-items:center;gap:6px;padding:7px 12px;border-radius:2px;border:2px solid #000;background:#fff;color:#000;font-weight:700;font-size:12px;cursor:pointer;">
                    <span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:#56666B;border:2px solid #000;flex-shrink:0;"></span>
                    <?php esc_html_e( '2. Active Chat', '101-map-assemblies' ); ?>
                </button>
                <button type="button" class="july3-filter-btn" data-filter="meetup" style="display:inline-flex;align-items:center;gap:6px;padding:7px 12px;border-radius:2px;border:2px solid #000;background:#fff;color:#000;font-weight:700;font-size:12px;cursor:pointer;">
                    <span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:#A8AF4A;border:2px solid #000;flex-shrink:0;"></span>
                    <?php esc_html_e( '3. Local Meet-Up', '101-map-assemblies' ); ?>
                </button>
                <button type="button" class="july3-filter-btn" data-filter="assembly" style="display:inline-flex;align-items:center;gap:6px;padding:7px 12px;border-radius:2px;border:2px solid #000;background:#fff;color:#000;font-weight:700;font-size:12px;cursor:pointer;">
                    <span style="display:inline-block;width:13px;height:13px;border-radius:50%;background:#D4E117;border:2px solid #000;flex-shrink:0;"></span>
                    <?php esc_html_e( '4. Assembly Planned', '101-map-assemblies' ); ?>
                </button>
                <button type="button" class="july3-filter-btn" data-filter="capital" style="display:inline-flex;align-items:center;gap:6px;padding:7px 12px;border-radius:2px;border:2px solid #000;background:#fff;color:#000;font-weight:700;font-size:12px;cursor:pointer;">
                    <span style="display:inline-block;width:14px;height:14px;border-radius:50%;background:#FAF829;border:2.5px solid #000;flex-shrink:0;"></span>
                    <?php esc_html_e( '5. July 3 Convergence', '101-map-assemblies' ); ?>
                </button>
            </div>

            <a href="<?php echo esc_url( $add_url ); ?>" class="j3-btn-orange" style="padding:8px 16px !important;font-size:16px !important;box-shadow:3px 3px 0px #000 !important;">
                ➕ <?php esc_html_e( 'Add Your Gathering →', '101-map-assemblies' ); ?>
            </a>
        </div>

        <!-- Map Canvas -->
        <div id="<?php echo esc_attr( $map_canvas_id ); ?>" style="height:<?php echo esc_attr( $map_height ); ?>;width:100%;border:3px solid #000000;border-radius:2px;box-shadow:10px 10px 0px var(--j3-yellow,#faf829);overflow:hidden;text-align:left;background:#e5e7eb;"></div>

        <!-- Single-Column Stacked Colour Key & Momentum Guide BELOW the Map -->
        <div style="margin-top:24px;background:#ffffff;border:3px solid #000000;border-radius:2px;padding:24px 28px;text-align:left;box-shadow:8px 8px 0px #000000;">
            <div style="display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:12px;border-bottom:2px solid #000;padding-bottom:12px;margin-bottom:16px;">
                <div>
                    <span style="background:#000;color:var(--j3-yellow,#faf829);font-size:11px;font-weight:800;letter-spacing:1.2px;padding:3px 8px;text-transform:uppercase;"><?php esc_html_e( 'MAP COLOUR KEY', '101-map-assemblies' ); ?></span>
                    <h3 class="j3-poster-title" style="font-size:26px;margin:6px 0 0;color:#000;"><?php esc_html_e( '5 STAGES OF LOCAL MOMENTUM: TURN YOUR PIN FROM BLACK TO YELLOW', '101-map-assemblies' ); ?></h3>
                </div>
                <a href="<?php echo esc_url( $add_url ); ?>" class="j3-btn-orange" style="padding:8px 18px !important;font-size:16px !important;box-shadow:3px 3px 0px #000 !important;">
                    📍 <?php esc_html_e( 'Register a Local Meet-Up or Assembly →', '101-map-assemblies' ); ?>
                </a>
            </div>

            <!-- Single-Column Stacked Stages (1 to 5) -->
            <div style="display:flex;flex-direction:column;gap:10px;">
                <div style="display:flex;align-items:center;gap:14px;padding:10px 14px;background:#f4f4f0;border:2px solid #000;">
                    <span style="display:inline-block;width:16px;height:16px;border-radius:50%;background:#000000;border:2.5px solid #ffffff;box-shadow:0 0 0 2px #000000;flex-shrink:0;"></span>
                    <div style="font-size:14px;line-height:1.4;color:#000;">
                        <strong style="text-transform:uppercase;letter-spacing:0.4px;"><?php esc_html_e( 'Stage 1 — Seed Pin (Black):', '101-map-assemblies' ); ?></strong>
                        <?php esc_html_e( 'A local Telegram group exists for this town, waiting for local citizens to join and spark the conversation.', '101-map-assemblies' ); ?>
                    </div>
                </div>

                <div style="display:flex;align-items:center;gap:14px;padding:10px 14px;background:#f4f4f0;border:2px solid #000;">
                    <span style="display:inline-block;width:18px;height:18px;border-radius:50%;background:#56666B;border:2.5px solid #000000;flex-shrink:0;"></span>
                    <div style="font-size:14px;line-height:1.4;color:#000;">
                        <strong style="text-transform:uppercase;letter-spacing:0.4px;"><?php esc_html_e( 'Stage 2 — Active Telegram Chat (Blue Slate):', '101-map-assemblies' ); ?></strong>
                        <?php esc_html_e( 'People are actively chatting in the local Telegram group and connecting with neighbours.', '101-map-assemblies' ); ?>
                    </div>
                </div>

                <div style="display:flex;align-items:center;gap:14px;padding:10px 14px;background:#f4f4f0;border:2px solid #000;">
                    <span style="display:inline-block;width:20px;height:20px;border-radius:50%;background:#A8AF4A;border:2.5px solid #000000;flex-shrink:0;"></span>
                    <div style="font-size:14px;line-height:1.4;color:#000;">
                        <strong style="text-transform:uppercase;letter-spacing:0.4px;"><?php esc_html_e( 'Stage 3 — Local Planning Meet-Up (Lime Moss):', '101-map-assemblies' ); ?></strong>
                        <?php esc_html_e( 'An informal local meet-up is arranged in a pub, cafe, or community room to plan an assembly together.', '101-map-assemblies' ); ?>
                    </div>
                </div>

                <div style="display:flex;align-items:center;gap:14px;padding:10px 14px;background:#f4f4f0;border:2px solid #000;">
                    <span style="display:inline-block;width:22px;height:22px;border-radius:50%;background:#D4E117;border:2.5px solid #000000;flex-shrink:0;"></span>
                    <div style="font-size:14px;line-height:1.4;color:#000;">
                        <strong style="text-transform:uppercase;letter-spacing:0.4px;"><?php esc_html_e( 'Stage 4 — Assembly Planned! (Electric Chartreuse):', '101-map-assemblies' ); ?></strong>
                        <?php esc_html_e( 'A full community or citizens\' assembly is scheduled with a confirmed venue, date, and time.', '101-map-assemblies' ); ?>
                    </div>
                </div>

                <div style="display:flex;align-items:center;gap:14px;padding:12px 14px;background:#000000;color:#ffffff;border:2px solid #000;">
                    <span style="display:inline-block;width:24px;height:24px;border-radius:50%;background:#FAF829;border:3px solid #000000;box-shadow:0 0 0 2px #FAF829;flex-shrink:0;"></span>
                    <div style="font-size:14px;line-height:1.4;">
                        <strong style="color:var(--j3-yellow,#faf829);text-transform:uppercase;letter-spacing:0.4px;"><?php esc_html_e( 'Stage 5 — July 3rd Mass Mobilisation (Signal Yellow):', '101-map-assemblies' ); ?></strong>
                        <?php esc_html_e( 'Capital & major city mass convergence on July 3rd bringing all local assemblies and movements together.', '101-map-assemblies' ); ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if ( $is_teaser ) : ?>
            <div style="margin-top:24px;">
                <a href="<?php echo esc_url( home_url( '/assemblies/' ) ); ?>" class="j3-btn-orange">
                    <?php esc_html_e( '🗺️ Explore Assemblies &amp; Full Page →', '101-map-assemblies' ); ?>
                </a>
            </div>
        <?php endif; ?>
    </section>
    <script>
    (function() {
        function initJuly3Map() {
            var mapCanvas = document.getElementById(<?php echo wp_json_encode( $map_canvas_id ); ?>);
            var wrapper = document.getElementById(<?php echo wp_json_encode( $map_wrapper_id ); ?>);
            if (!mapCanvas || typeof L === 'undefined') {
                return;
            }

            var defaultView = <?php echo wp_json_encode( $default_view ); ?>;
            var map = L.map(mapCanvas).setView([defaultView.lat, defaultView.lng], defaultView.zoom);

            // Clean architectural greyscale tiles (CartoDB Positron) so the Black-to-Yellow palette pops cleanly
            L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
                maxZoom: 19,
                subdomains: 'abcd',
                attribution: '&copy; OpenStreetMap contributors &copy; CARTO'
            }).addTo(map);

            var rawPins = <?php echo wp_json_encode( $pins ); ?>;
            var detailsText = <?php echo wp_json_encode( __( 'Join Telegram Group →', '101-map-assemblies' ) ); ?>;
            var addEventText = <?php echo wp_json_encode( __( '+ Organising an event here? Add details →', '101-map-assemblies' ) ); ?>;
            var addUrl = <?php echo wp_json_encode( esc_url( $add_url ) ); ?>;

            var stageConfig = {
                seed:     { fill: '#000000', stroke: '#ffffff', radius: 7,  weight: 2.5, label: <?php echo wp_json_encode( __( 'Stage 1: Local Telegram Group', '101-map-assemblies' ) ); ?> },
                chat:     { fill: '#56666B', stroke: '#000000', radius: 8,  weight: 2.5, label: <?php echo wp_json_encode( __( 'Stage 2: Active Telegram Chat', '101-map-assemblies' ) ); ?> },
                meetup:   { fill: '#A8AF4A', stroke: '#000000', radius: 10, weight: 2.5, label: <?php echo wp_json_encode( __( 'Stage 3: Local Planning Meet-Up', '101-map-assemblies' ) ); ?> },
                assembly: { fill: '#D4E117', stroke: '#000000', radius: 11, weight: 2.5, label: <?php echo wp_json_encode( __( 'Stage 4: Confirmed Assembly', '101-map-assemblies' ) ); ?> },
                capital:  { fill: '#FAF829', stroke: '#000000', radius: 14, weight: 3.5, label: <?php echo wp_json_encode( __( 'Stage 5: July 3 Convergence', '101-map-assemblies' ) ); ?> }
            };

            var markers = [];

            function filterMap(filterType, isInitial) {
                markers.forEach(function(m) { map.removeLayer(m); });
                markers = [];
                rawPins.forEach(function(p) {
                    var st = stageConfig[p.type] ? p.type : 'seed';
                    if (filterType !== 'all' && st !== filterType) return;
                    var cfg = stageConfig[st];

                    var m = L.circleMarker([p.lat, p.lng], {
                        radius: cfg.radius,
                        fillColor: cfg.fill,
                        color: cfg.stroke,
                        weight: cfg.weight,
                        fillOpacity: 0.96
                    }).addTo(map);

                    var html = '<div style="font-family:Helvetica,Arial,sans-serif;line-height:1.45;padding:4px;min-width:210px;color:#000;">';
                    html += '<div style="display:inline-block;background:#000;color:#faf829;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:0.8px;padding:2px 7px;margin-bottom:6px;">' + cfg.label + '</div>';
                    html += '<div style="font-size:17px;font-weight:800;margin-bottom:6px;">' + p.title + '</div>';
                    if (p.meetup) html += '<div style="font-size:13px;margin-bottom:4px;">📍 ' + p.meetup + '</div>';
                    if (p.date) html += '<div style="font-size:13px;margin-bottom:6px;">🗓️ <strong>' + p.date + '</strong></div>';
                    if (p.chat) {
                        html += '<a href="' + p.chat + '" target="_blank" rel="noopener" style="display:block;text-align:center;margin-top:8px;padding:8px 12px;background:#faf829;color:#000;border:2px solid #000;text-decoration:none;font-size:12px;font-weight:800;text-transform:uppercase;box-shadow:2px 2px 0 #000;">✈️ ' + detailsText + '</a>';
                    }
                    html += '<a href="' + addUrl + '" style="display:block;margin-top:8px;font-size:11px;color:#000;text-decoration:underline;font-weight:700;">' + addEventText + '</a>';
                    html += '</div>';

                    m.bindPopup(html);
                    markers.push(m);
                });

                if (markers.length > 0 && (!isInitial || defaultView.fit_bounds)) {
                    var group = new L.featureGroup(markers);
                    map.fitBounds(group.getBounds().pad(0.12));
                }
            }

            if (wrapper) {
                var buttons = wrapper.querySelectorAll('.july3-filter-btn');
                buttons.forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        buttons.forEach(function(b) {
                            b.style.background = '#ffffff';
                            b.style.color = '#000000';
                        });
                        this.style.background = '#000000';
                        this.style.color = '#faf829';
                        var f = this.getAttribute('data-filter') || 'all';
                        filterMap(f, false);
                    });
                });
            }

            filterMap('all', true);
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initJuly3Map);
        } else {
            initJuly3Map();
        }
    })();
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode( 'mobilization_map', 'july3_mod3_render_map' );

/**
 * Global map shortcodes for multisite networks.
 */
function july3_mod3_render_global_map( $atts ) {
    $atts = (array) $atts;
    $atts['global'] = true;
    return july3_mod3_render_map( $atts );
}
add_shortcode( 'mobilization_map-global', 'july3_mod3_render_global_map' );
add_shortcode( 'mobilization_map_global', 'july3_mod3_render_global_map' );

function july3_mod3_render_calendar() {
    ob_start();
    ?>
    <section id="calendar" class="j3-container" style="padding:32px 24px 64px;text-align:center;">
        <div style="background:#000;color:var(--j3-yellow,#faf829);display:inline-block;padding:4px 12px;font-size:12px;font-weight:800;letter-spacing:1.5px;text-transform:uppercase;"><?php esc_html_e( 'KEY DATES &amp; REGIONAL GATHERINGS', '101-map-assemblies' ); ?></div>
        <h2 class="j3-poster-title" style="font-size:44px;margin:6px 0 20px;"><?php esc_html_e( 'THE MOBILIZATION CALENDAR', '101-map-assemblies' ); ?></h2>
        <div style="border:3px solid #000;border-radius:2px;overflow:hidden;box-shadow:10px 10px 0px var(--j3-yellow,#faf829);background:#fff;"><iframe src="https://teamup.com/kswvbpogo3pf6txkut?showProfileAndInfo=0&showSidepanel=1&showViewSelector=1&showMenu=1" style="width:100%;height:600px;border:none;" loading="lazy"></iframe></div>
    </section>
    <?php
    return ob_get_clean();
}
add_shortcode( 'teamup_calendar', 'july3_mod3_render_calendar' );

/**
 * Register post meta for REST API / Gutenberg compatibility.
 */
add_action( 'init', function() {
    $meta_fields = array(
        '_hub_lat'       => 'sanitize_text_field',
        '_hub_lng'       => 'sanitize_text_field',
        '_hub_type'      => 'sanitize_text_field',
        '_hub_meetup'    => 'sanitize_text_field',
        '_hub_next_date' => 'sanitize_text_field',
        '_hub_chat_url'  => 'esc_url_raw',
    );

    foreach ( $meta_fields as $meta_key => $sanitizer ) {
        register_post_meta( 'mobilization_hub', $meta_key, array(
            'show_in_rest'      => true,
            'single'            => true,
            'type'              => 'string',
            'sanitize_callback' => $sanitizer,
            'auth_callback'     => function() {
                return current_user_can( 'edit_posts' );
            },
        ) );
    }
} );

/**
 * Enqueue Flatpickr for mobilization_hub post edit screen.
 */
add_action( 'admin_enqueue_scripts', function( $hook ) {
    global $post;
    if ( ( 'post.php' === $hook || 'post-new.php' === $hook ) && isset( $post->post_type ) && 'mobilization_hub' === $post->post_type ) {
        wp_enqueue_style( 'flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', array(), '4.6.13' );
        wp_enqueue_script( 'flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.js', array(), '4.6.13', true );
        $locale = determine_locale();
        if ( strpos( $locale, 'de' ) === 0 ) {
            wp_enqueue_script( 'flatpickr-de', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/de.js', array( 'flatpickr' ), '4.6.13', true );
        } elseif ( strpos( $locale, 'fr' ) === 0 ) {
            wp_enqueue_script( 'flatpickr-fr', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/fr.js', array( 'flatpickr' ), '4.6.13', true );
        }
    }
} );

/**
 * Register Meta Box for Assembly / Hub details.
 */
add_action( 'add_meta_boxes', function() {
    add_meta_box(
        'mobilization_hub_details',
        __( 'Hub & Assembly Details (Map Settings)', '101-map-assemblies' ),
        'july3_render_hub_meta_box',
        'mobilization_hub',
        'normal',
        'high'
    );
} );

/**
 * Render the Hub & Assembly meta box.
 */
function july3_render_hub_meta_box( $post ) {
    wp_nonce_field( 'july3_save_hub_meta', 'july3_hub_nonce' );

    $lat       = get_post_meta( $post->ID, '_hub_lat', true );
    $lng       = get_post_meta( $post->ID, '_hub_lng', true );
    $raw_type  = get_post_meta( $post->ID, '_hub_type', true ) ?: 'seed';
    $meetup    = get_post_meta( $post->ID, '_hub_meetup', true );
    $next_date = get_post_meta( $post->ID, '_hub_next_date', true );
    $chat_url  = get_post_meta( $post->ID, '_hub_chat_url', true );
    $type      = july3_mod3_normalize_stage( $raw_type, $chat_url, $meetup, $next_date );
    ?>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;padding:12px 0;">
        <div>
            <label for="_hub_lat" style="font-weight:700;display:block;margin-bottom:4px;"><?php esc_html_e( 'Latitude', '101-map-assemblies' ); ?> <span style="color:#dc2626;">* (<?php esc_html_e( 'Required', '101-map-assemblies' ); ?>)</span></label>
            <input type="text" id="_hub_lat" name="_hub_lat" value="<?php echo esc_attr( $lat ); ?>" placeholder="52.5200" style="width:100%;padding:6px 8px;" />
            <small style="color:#64748b;"><?php esc_html_e( 'Decimal value (e.g. 52.5200 for Berlin)', '101-map-assemblies' ); ?></small>
        </div>
        <div>
            <label for="_hub_lng" style="font-weight:700;display:block;margin-bottom:4px;"><?php esc_html_e( 'Longitude', '101-map-assemblies' ); ?> <span style="color:#dc2626;">* (<?php esc_html_e( 'Required', '101-map-assemblies' ); ?>)</span></label>
            <input type="text" id="_hub_lng" name="_hub_lng" value="<?php echo esc_attr( $lng ); ?>" placeholder="13.4050" style="width:100%;padding:6px 8px;" />
            <small style="color:#64748b;"><?php esc_html_e( 'Decimal value (e.g. 13.4050 for Berlin)', '101-map-assemblies' ); ?></small>
        </div>
    </div>

    <div style="margin-top:16px;">
        <label style="font-weight:700;display:block;margin-bottom:8px;"><?php esc_html_e( '5-Stage Momentum Level / Marker Color', '101-map-assemblies' ); ?></label>
        <div style="display:flex;flex-direction:column;gap:8px;max-width:680px;">
            <label style="display:flex;align-items:center;gap:10px;padding:10px 14px;border:1px solid #cbd5e1;border-radius:6px;background:#fff;cursor:pointer;">
                <input type="radio" name="_hub_type" value="seed" <?php checked( $type, 'seed' ); ?> style="margin:0;" />
                <span style="display:inline-block;width:14px;height:14px;border-radius:50%;background:#000000;border:2px solid #fff;box-shadow:0 0 0 1.5px #000;flex-shrink:0;"></span>
                <span style="font-weight:600;color:#0f172a;font-size:13.5px;"><?php esc_html_e( 'Stage 1 — Seed Pin: Telegram Group Exists (#000000 Black)', '101-map-assemblies' ); ?></span>
            </label>
            <label style="display:flex;align-items:center;gap:10px;padding:10px 14px;border:1px solid #cbd5e1;border-radius:6px;background:#fff;cursor:pointer;">
                <input type="radio" name="_hub_type" value="chat" <?php checked( $type, 'chat' ); ?> style="margin:0;" />
                <span style="display:inline-block;width:14px;height:14px;border-radius:50%;background:#56666B;border:2px solid #000;flex-shrink:0;"></span>
                <span style="font-weight:600;color:#0f172a;font-size:13.5px;"><?php esc_html_e( 'Stage 2 — Active Telegram Chat (#56666B Blue Slate)', '101-map-assemblies' ); ?></span>
            </label>
            <label style="display:flex;align-items:center;gap:10px;padding:10px 14px;border:1px solid #cbd5e1;border-radius:6px;background:#fff;cursor:pointer;">
                <input type="radio" name="_hub_type" value="meetup" <?php checked( $type, 'meetup' ); ?> style="margin:0;" />
                <span style="display:inline-block;width:15px;height:15px;border-radius:50%;background:#A8AF4A;border:2px solid #000;flex-shrink:0;"></span>
                <span style="font-weight:600;color:#0f172a;font-size:13.5px;"><?php esc_html_e( 'Stage 3 — Local Planning Meet-Up in Pub/Space (#A8AF4A Lime Moss)', '101-map-assemblies' ); ?></span>
            </label>
            <label style="display:flex;align-items:center;gap:10px;padding:10px 14px;border:1px solid #cbd5e1;border-radius:6px;background:#fff;cursor:pointer;">
                <input type="radio" name="_hub_type" value="assembly" <?php checked( $type, 'assembly' ); ?> style="margin:0;" />
                <span style="display:inline-block;width:16px;height:16px;border-radius:50%;background:#D4E117;border:2px solid #000;flex-shrink:0;"></span>
                <span style="font-weight:600;color:#0f172a;font-size:13.5px;"><?php esc_html_e( 'Stage 4 — Confirmed Assembly Planned (#D4E117 Electric Chartreuse)', '101-map-assemblies' ); ?></span>
            </label>
            <label style="display:flex;align-items:center;gap:10px;padding:10px 14px;border:1px solid #cbd5e1;border-radius:6px;background:#fff;cursor:pointer;">
                <input type="radio" name="_hub_type" value="capital" <?php checked( $type, 'capital' ); ?> style="margin:0;" />
                <span style="display:inline-block;width:18px;height:18px;border-radius:50%;background:#FAF829;border:2.5px solid #000;flex-shrink:0;"></span>
                <span style="font-weight:600;color:#0f172a;font-size:13.5px;"><?php esc_html_e( 'Stage 5 — July 3rd Capital / Major City Mobilisation (#FAF829 Yellow)', '101-map-assemblies' ); ?></span>
            </label>
        </div>
    </div>

    <div style="margin-top:16px;">
        <label for="_hub_meetup" style="font-weight:700;display:block;margin-bottom:4px;"><?php esc_html_e( 'Meeting Point / Address', '101-map-assemblies' ); ?></label>
        <input type="text" id="_hub_meetup" name="_hub_meetup" value="<?php echo esc_attr( $meetup ); ?>" placeholder="<?php echo esc_attr__( 'e.g. Town Square 1, City', '101-map-assemblies' ); ?>" style="width:100%;padding:6px 8px;" />
    </div>

    <div style="margin-top:16px;">
        <label for="_hub_next_date" style="font-weight:700;display:block;margin-bottom:4px;"><?php esc_html_e( 'Next Meeting / Date &amp; Time', '101-map-assemblies' ); ?></label>
        <div style="display:flex;align-items:center;gap:8px;max-width:440px;">
            <div style="position:relative;flex:1;">
                <input type="text" id="_hub_next_date" name="_hub_next_date" value="<?php echo esc_attr( $next_date ); ?>" placeholder="<?php echo esc_attr__( 'e.g. 2027-07-03 14:00', '101-map-assemblies' ); ?>" style="width:100%;padding:7px 10px 7px 34px;border:1px solid #cbd5e1;border-radius:6px;background:#fff;" />
                <span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);pointer-events:none;font-size:14px;line-height:1;">🗓️</span>
            </div>
            <button type="button" id="clear_hub_next_date" class="button" style="padding:4px 10px;height:auto;" title="<?php esc_attr_e( 'Clear Date', '101-map-assemblies' ); ?>">✕</button>
        </div>
        <small style="color:#64748b;"><?php esc_html_e( 'Pick the date and time for the next scheduled meeting or assembly.', '101-map-assemblies' ); ?></small>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var dateEl = document.getElementById('_hub_next_date');
        if (dateEl && typeof flatpickr !== 'undefined') {
            var localeObj = 'default';
            if (typeof flatpickr.l10ns !== 'undefined') {
                var lang = '<?php echo esc_js( substr( determine_locale(), 0, 2 ) ); ?>';
                if (flatpickr.l10ns[lang]) {
                    localeObj = flatpickr.l10ns[lang];
                }
            }
            var fp = flatpickr(dateEl, {
                enableTime: true,
                time_24hr: true,
                dateFormat: 'Y-m-d H:i',
                altInput: true,
                altFormat: 'F j, Y, H:i',
                locale: localeObj,
                allowInput: true
            });
            var clearBtn = document.getElementById('clear_hub_next_date');
            if (clearBtn) {
                clearBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    fp.clear();
                });
            }
        }
    });
    </script>

    <div style="margin-top:16px;">
        <label for="_hub_chat_url" style="font-weight:700;display:block;margin-bottom:4px;"><?php esc_html_e( 'Link / Chat / RSVP URL', '101-map-assemblies' ); ?></label>
        <input type="url" id="_hub_chat_url" name="_hub_chat_url" value="<?php echo esc_attr( $chat_url ); ?>" placeholder="https://t.me/example_assembly" style="width:100%;padding:6px 8px;" />
    </div>
    <?php
}

/**
 * Save Hub & Assembly meta fields on post save.
 */
add_action( 'save_post_mobilization_hub', function( $post_id ) {
    if ( ! isset( $_POST['july3_hub_nonce'] ) || ! wp_verify_nonce( $_POST['july3_hub_nonce'], 'july3_save_hub_meta' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    $fields = array(
        '_hub_lat'       => 'sanitize_text_field',
        '_hub_lng'       => 'sanitize_text_field',
        '_hub_type'      => 'sanitize_text_field',
        '_hub_meetup'    => 'sanitize_text_field',
        '_hub_next_date' => 'sanitize_text_field',
        '_hub_chat_url'  => 'esc_url_raw',
    );

    foreach ( $fields as $key => $sanitizer ) {
        if ( isset( $_POST[ $key ] ) ) {
            $val = call_user_func( $sanitizer, wp_unslash( $_POST[ $key ] ) );
            update_post_meta( $post_id, $key, $val );
        }
    }
} );
// === END OF MODULE 3 ===