<?php
/**
 * Plugin Name: 101 Demands — Module 3: Assemblies, Map & Calendar
 * Description: Powers [assemblies_intro], [mobilization_map] (supports mode="teaser" for homepage or full mode for /assemblies/), and [teamup_calendar].
 * Version:     1.2.3
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
        <div style="color:var(--j3-orange);font-size:12px;font-weight:800;letter-spacing:1.5px;text-transform:uppercase;"><?php esc_html_e( 'REAL DEMOCRACY IN ACTION', '101-map-assemblies' ); ?></div>
        <h1 class="j3-poster-title" style="font-size:clamp(42px,5vw,58px);margin:6px 0 8px;"><?php esc_html_e( 'CITIZEN &amp; COMMUNITY ASSEMBLIES', '101-map-assemblies' ); ?></h1>
        <div class="j3-brush" style="margin-bottom:28px;"><?php esc_html_e( 'The key to unlocking real change across every demand', '101-map-assemblies' ); ?></div>

        <div style="max-width:1040px;margin:0 auto;background:var(--j3-espresso);color:#fff;border-radius:26px;padding:44px 36px;text-align:left;box-shadow:0 25px 50px -12px rgba(28,19,14,0.3);">
            <p style="font-size:17px;line-height:1.7;color:#f5f5f4;margin:0 0 28px;max-width:860px;">
                <?php echo wp_kses_post( __( 'For decades, vital reforms on climate, wealth inequality, housing, and peace have been blocked by short-term party politics and entrenched lobbying interests. <strong>Assemblies are how we break that deadlock.</strong> When ordinary people are given the time, independent evidence, and space to deliberate together, they consistently reach fair, courageous solutions that career politicians refuse to touch.', '101-map-assemblies' ) ); ?>
            </p>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:20px;">
                <div style="background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.12);border-radius:18px;padding:24px;">
                    <div style="color:var(--j3-amber);font-weight:800;font-size:13px;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;"><?php esc_html_e( '01 // BEYOND GOVERNANCE FAILURES', '101-map-assemblies' ); ?></div>
                    <h3 style="margin:0 0 8px;font-size:19px;font-weight:800;color:#fff;"><?php esc_html_e( 'Power in the Hands of the People', '101-map-assemblies' ); ?></h3>
                    <p style="margin:0;font-size:14px;line-height:1.6;color:#d6d3d1;">
                        <?php esc_html_e( 'Instead of outsourcing our future to distant political classes and corporate lobbyists, assemblies put decision-making directly into the hands of everyday citizens and local communities.', '101-map-assemblies' ); ?>
                    </p>
                </div>

                <div style="background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.12);border-radius:18px;padding:24px;">
                    <div style="color:var(--j3-amber);font-weight:800;font-size:13px;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;"><?php esc_html_e( '02 // INFORMED DELIBERATION', '101-map-assemblies' ); ?></div>
                    <h3 style="margin:0 0 8px;font-size:19px;font-weight:800;color:#fff;"><?php esc_html_e( 'The Key That Unlocks Every Demand', '101-map-assemblies' ); ?></h3>
                    <p style="margin:0;font-size:14px;line-height:1.6;color:#d6d3d1;">
                        <?php esc_html_e( 'Whether the issue is taxing extreme wealth, restoring nature, or fixing housing, assemblies bring communities and independent experts together to build mandates with real public legitimacy.', '101-map-assemblies' ); ?>
                    </p>
                </div>

                <div style="background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.12);border-radius:18px;padding:24px;">
                    <div style="color:var(--j3-amber);font-weight:800;font-size:13px;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;"><?php esc_html_e( '03 // LOCAL TO GLOBAL', '101-map-assemblies' ); ?></div>
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

function july3_mod3_render_map( $atts ) {
    $atts = shortcode_atts( array( 'mode' => 'full' ), $atts );
    $is_teaser = ( $atts['mode'] === 'teaser' );

    wp_enqueue_style( 'leaflet-css', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', array(), '1.9.4' );
    wp_enqueue_script( 'leaflet-js', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array(), '1.9.4', true );

    $hubs = get_posts( array(
        'post_type'      => 'mobilization_hub',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
    ) );

    $pins = array();
    foreach ( $hubs as $h ) {
        $lat = get_post_meta( $h->ID, '_hub_lat', true );
        $lng = get_post_meta( $h->ID, '_hub_lng', true );
        if ( $lat && $lng ) {
            $type = get_post_meta( $h->ID, '_hub_type', true );
            $pins[] = array(
                'title'  => $h->post_title,
                'lat'    => (float) $lat,
                'lng'    => (float) $lng,
                'type'   => $type ? $type : 'confirmed',
                'meetup' => get_post_meta( $h->ID, '_hub_meetup', true ),
                'date'   => get_post_meta( $h->ID, '_hub_next_date', true ),
                'chat'   => get_post_meta( $h->ID, '_hub_chat_url', true ),
            );
        }
    }

    $map_height = $is_teaser ? '330px' : '540px';
    ob_start();
    ?>
    <section id="map-preview" class="j3-container" style="padding:48px 24px;text-align:center;">
        <div style="color:var(--j3-orange);font-size:12px;font-weight:800;letter-spacing:1.5px;text-transform:uppercase;"><?php esc_html_e( 'LOCAL ASSEMBLIES, TOWN HALLS &amp; CONVERGENCES', '101-map-assemblies' ); ?></div>
        <h2 class="j3-poster-title" style="font-size:44px;margin:6px 0 8px;"><?php esc_html_e( 'THE GLOBAL ASSEMBLIES MAP', '101-map-assemblies' ); ?></h2>
        <div class="j3-brush" style="margin-bottom:20px;"><?php esc_html_e( 'Find a local assembly, town hall, or training near you', '101-map-assemblies' ); ?></div>

        <?php if ( ! $is_teaser ) : ?>
            <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:20px;justify-content:center;">
                <button type="button" onclick="july3FilterMap('all')" style="display:inline-flex;align-items:center;gap:8px;padding:9px 18px;border-radius:99px;border:none;background:var(--j3-espresso, #1c130e);color:#fff;font-weight:700;cursor:pointer;">
                    <span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:#ffffff;border:2px solid rgba(255,255,255,0.6);box-sizing:border-box;flex-shrink:0;"></span>
                    <?php printf( esc_html__( 'All Pins (%d)', '101-map-assemblies' ), count( $pins ) ); ?>
                </button>
                <button type="button" onclick="july3FilterMap('event')" style="display:inline-flex;align-items:center;gap:8px;padding:9px 18px;border-radius:99px;border:none;background:#f3e8ff;color:#6b21a8;font-weight:700;cursor:pointer;">
                    <span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:#8b5cf6;border:2px solid #1c130e;box-sizing:border-box;flex-shrink:0;"></span>
                    <?php esc_html_e( 'Talks, Town Halls &amp; Trainings', '101-map-assemblies' ); ?>
                </button>
                <button type="button" onclick="july3FilterMap('confirmed')" style="display:inline-flex;align-items:center;gap:8px;padding:9px 18px;border-radius:99px;border:none;background:#dcfce7;color:#166534;font-weight:700;cursor:pointer;">
                    <span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:#16a34a;border:2px solid #1c130e;box-sizing:border-box;flex-shrink:0;"></span>
                    <?php esc_html_e( 'Confirmed Assemblies', '101-map-assemblies' ); ?>
                </button>
                <button type="button" onclick="july3FilterMap('capital')" style="display:inline-flex;align-items:center;gap:8px;padding:9px 18px;border-radius:99px;border:none;background:#ffedd5;color:#c2410c;font-weight:700;cursor:pointer;">
                    <span style="display:inline-block;width:14px;height:14px;border-radius:50%;background:#ea580c;border:2px solid #1c130e;box-sizing:border-box;flex-shrink:0;"></span>
                    <?php esc_html_e( 'July 3 Capital Convergences', '101-map-assemblies' ); ?>
                </button>
                <button type="button" onclick="july3FilterMap('prep')" style="display:inline-flex;align-items:center;gap:8px;padding:9px 18px;border-radius:99px;border:none;background:#fef3c7;color:#92400e;font-weight:700;cursor:pointer;">
                    <span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:#f59e0b;border:2px solid #1c130e;box-sizing:border-box;flex-shrink:0;"></span>
                    <?php esc_html_e( 'Preparation Meetings', '101-map-assemblies' ); ?>
                </button>
            </div>
        <?php endif; ?>

        <div id="july3-osm-map" style="height:<?php echo esc_attr( $map_height ); ?>;width:100%;border-radius:24px;box-shadow:0 25px 50px -12px rgba(28,19,14,0.18);overflow:hidden;text-align:left;"></div>

        <?php if ( $is_teaser ) : ?>
            <div style="margin-top:24px;">
                <a href="<?php echo esc_url( home_url( '/assemblies/' ) ); ?>" class="j3-btn-orange" style="padding:14px 28px;font-size:16px;">
                    <?php esc_html_e( '🗺️ Explore Assemblies, Full Map &amp; Key Dates →', '101-map-assemblies' ); ?>
                </a>
            </div>
        <?php endif; ?>
    </section>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var map = L.map('july3-osm-map').setView([51.5, -1.5], <?php echo $is_teaser ? 4 : 5; ?>);
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);
        var rawPins = <?php echo wp_json_encode( $pins ); ?>;
        var detailsText = <?php echo wp_json_encode( __( 'Telegram →', '101-map-assemblies' ) ); ?>;
        var markers = [];
        window.july3FilterMap = function(filterType) {
            markers.forEach(function(m) { map.removeLayer(m); });
            markers = [];
            rawPins.forEach(function(p) {
                if (filterType !== 'all' && p.type !== filterType) return;
                var colors = { capital: '#ea580c', confirmed: '#16a34a', prep: '#f59e0b', event: '#8b5cf6' };
                var m = L.circleMarker([p.lat, p.lng], {
                    radius: p.type === 'capital' ? 12 : 10,
                    fillColor: colors[p.type] || '#ea580c',
                    color: '#1c130e',
                    weight: 2.5,
                    fillOpacity: 0.95
                }).addTo(map);
                var html = '<div style="font-family:sans-serif;line-height:1.45;padding:4px;"><strong style="font-size:15px;">' + p.title + '</strong><br>';
                if (p.meetup) html += '📍 ' + p.meetup + '<br>';
                if (p.date) html += '🗓️ <strong>' + p.date + '</strong><br>';
                if (p.chat) html += '<a href="' + p.chat + '" target="_blank" rel="noopener" style="display:inline-block;margin-top:8px;padding:6px 12px;background:#ea580c;color:#fff;text-decoration:none;border-radius:8px;font-size:12px;font-weight:700;">' + detailsText + '</a>';
                html += '</div>';
                m.bindPopup(html);
                markers.push(m);
            });
        };
        july3FilterMap('all');
    });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode( 'mobilization_map', 'july3_mod3_render_map' );

function july3_mod3_render_calendar() {
    ob_start();
    ?>
    <section id="calendar" class="j3-container" style="padding:32px 24px 64px;text-align:center;">
        <div style="color:var(--j3-orange);font-size:12px;font-weight:800;letter-spacing:1.5px;text-transform:uppercase;"><?php esc_html_e( 'KEY DATES &amp; REGIONAL GATHERINGS', '101-map-assemblies' ); ?></div>
        <h2 class="j3-poster-title" style="font-size:44px;margin:6px 0 20px;"><?php esc_html_e( 'THE MOBILIZATION CALENDAR', '101-map-assemblies' ); ?></h2>
        <div style="border-radius:24px;overflow:hidden;box-shadow:0 20px 45px -12px rgba(28,19,14,0.16);background:#fff;"><iframe src="https://teamup.com/kswvbpogo3pf6txkut?showProfileAndInfo=0&showSidepanel=1&showViewSelector=1&showMenu=1" style="width:100%;height:600px;border:none;" loading="lazy"></iframe></div>
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
        __( '📍 Hub & Assembly Details (Map Settings)', '101-map-assemblies' ),
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
    $type      = get_post_meta( $post->ID, '_hub_type', true ) ?: 'confirmed';
    $meetup    = get_post_meta( $post->ID, '_hub_meetup', true );
    $next_date = get_post_meta( $post->ID, '_hub_next_date', true );
    $chat_url  = get_post_meta( $post->ID, '_hub_chat_url', true );
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
        <label style="font-weight:700;display:block;margin-bottom:8px;"><?php esc_html_e( 'Assembly Type / Marker Color', '101-map-assemblies' ); ?></label>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px;max-width:900px;">
            <label style="display:flex;align-items:center;gap:10px;padding:10px 14px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;cursor:pointer;">
                <input type="radio" name="_hub_type" value="confirmed" <?php checked( $type, 'confirmed' ); ?> style="margin:0;" />
                <span style="display:inline-block;width:14px;height:14px;border-radius:50%;background:#16a34a;border:2.5px solid #1c130e;box-sizing:border-box;flex-shrink:0;"></span>
                <span style="font-weight:600;color:#0f172a;font-size:13.5px;"><?php esc_html_e( 'Confirmed Assembly (Green)', '101-map-assemblies' ); ?></span>
            </label>
            <label style="display:flex;align-items:center;gap:10px;padding:10px 14px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;cursor:pointer;">
                <input type="radio" name="_hub_type" value="capital" <?php checked( $type, 'capital' ); ?> style="margin:0;" />
                <span style="display:inline-block;width:18px;height:18px;border-radius:50%;background:#ea580c;border:2.5px solid #1c130e;box-sizing:border-box;flex-shrink:0;"></span>
                <span style="font-weight:600;color:#0f172a;font-size:13.5px;"><?php esc_html_e( 'July 3 Capital Convergence (Orange / Large)', '101-map-assemblies' ); ?></span>
            </label>
            <label style="display:flex;align-items:center;gap:10px;padding:10px 14px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;cursor:pointer;">
                <input type="radio" name="_hub_type" value="event" <?php checked( $type, 'event' ); ?> style="margin:0;" />
                <span style="display:inline-block;width:14px;height:14px;border-radius:50%;background:#8b5cf6;border:2.5px solid #1c130e;box-sizing:border-box;flex-shrink:0;"></span>
                <span style="font-weight:600;color:#0f172a;font-size:13.5px;"><?php esc_html_e( 'Talk, Town Hall &amp; Training (Purple)', '101-map-assemblies' ); ?></span>
            </label>
            <label style="display:flex;align-items:center;gap:10px;padding:10px 14px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;cursor:pointer;">
                <input type="radio" name="_hub_type" value="prep" <?php checked( $type, 'prep' ); ?> style="margin:0;" />
                <span style="display:inline-block;width:14px;height:14px;border-radius:50%;background:#f59e0b;border:2.5px solid #1c130e;box-sizing:border-box;flex-shrink:0;"></span>
                <span style="font-weight:600;color:#0f172a;font-size:13.5px;"><?php esc_html_e( 'Preparation Meeting (Yellow)', '101-map-assemblies' ); ?></span>
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
