<?php
/**
 * Plugin Name: 101 Demands — Module 3: Assemblies, Map & Calendar
 * Description: Powers [assemblies_intro], [mobilization_map] (supports mode="teaser" for homepage or full mode for /assemblies/), and [teamup_calendar].
 * Version:     1.2.0
 * Author:      101 Demands Global Tech Collective
 * License:     GPL-3.0+
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
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
        <div style="color:var(--j3-orange);font-size:12px;font-weight:800;letter-spacing:1.5px;text-transform:uppercase;">REAL DEMOCRACY IN ACTION</div>
        <h1 class="j3-poster-title" style="font-size:clamp(42px,5vw,58px);margin:6px 0 8px;">CITIZEN &amp; COMMUNITY ASSEMBLIES</h1>
        <div class="j3-brush" style="margin-bottom:28px;">The key to unlocking real change across every demand</div>

        <div style="max-width:1040px;margin:0 auto;background:var(--j3-espresso);color:#fff;border-radius:26px;padding:44px 36px;text-align:left;box-shadow:0 25px 50px -12px rgba(28,19,14,0.3);">
            <p style="font-size:17px;line-height:1.7;color:#f5f5f4;margin:0 0 28px;max-width:860px;">
                For decades, vital reforms on climate, wealth inequality, housing, and peace have been blocked by short-term party politics and entrenched lobbying interests. <strong>Assemblies are how we break that deadlock.</strong> When ordinary people are given the time, independent evidence, and space to deliberate together, they consistently reach fair, courageous solutions that career politicians refuse to touch.
            </p>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:20px;">
                <div style="background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.12);border-radius:18px;padding:24px;">
                    <div style="color:var(--j3-amber);font-weight:800;font-size:13px;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">01 // BEYOND GOVERNANCE FAILURES</div>
                    <h3 style="margin:0 0 8px;font-size:19px;font-weight:800;color:#fff;">Power in the Hands of the People</h3>
                    <p style="margin:0;font-size:14px;line-height:1.6;color:#d6d3d1;">
                        Instead of outsourcing our future to distant political classes and corporate lobbyists, assemblies put decision-making directly into the hands of everyday citizens and local communities.
                    </p>
                </div>

                <div style="background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.12);border-radius:18px;padding:24px;">
                    <div style="color:var(--j3-amber);font-weight:800;font-size:13px;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">02 // INFORMED DELIBERATION</div>
                    <h3 style="margin:0 0 8px;font-size:19px;font-weight:800;color:#fff;">The Key That Unlocks Every Demand</h3>
                    <p style="margin:0;font-size:14px;line-height:1.6;color:#d6d3d1;">
                        Whether the issue is taxing extreme wealth, restoring nature, or fixing housing, assemblies bring communities and independent experts together to build mandates with real public legitimacy.
                    </p>
                </div>

                <div style="background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.12);border-radius:18px;padding:24px;">
                    <div style="color:var(--j3-amber);font-weight:800;font-size:13px;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">03 // LOCAL TO GLOBAL</div>
                    <h3 style="margin:0 0 8px;font-size:19px;font-weight:800;color:#fff;">From Town Halls to July 3rd</h3>
                    <p style="margin:0;font-size:14px;line-height:1.6;color:#d6d3d1;">
                        Local town halls and grassroots assemblies are meeting now to deliberate, compile local evidence reports, and build towards the mass capital-city convergences on July 3rd.
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
        <div style="color:var(--j3-orange);font-size:12px;font-weight:800;letter-spacing:1.5px;text-transform:uppercase;">LOCAL ASSEMBLIES, TOWN HALLS &amp; CONVERGENCES</div>
        <h2 class="j3-poster-title" style="font-size:44px;margin:6px 0 8px;">THE GLOBAL ASSEMBLIES MAP</h2>
        <div class="j3-brush" style="margin-bottom:20px;">Find a local assembly, town hall, or training near you</div>

        <?php if ( ! $is_teaser ) : ?>
            <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:20px;justify-content:center;">
                <button type="button" onclick="july3FilterMap('all')" style="padding:9px 18px;border-radius:99px;border:none;background:var(--j3-espresso);color:#fff;font-weight:700;cursor:pointer;">🌍 All Pins (<?php echo count( $pins ); ?>)</button>
                <button type="button" onclick="july3FilterMap('event')" style="padding:9px 18px;border-radius:99px;border:none;background:#f3e8ff;color:#6b21a8;font-weight:700;cursor:pointer;">🎤 Talks, Town Halls &amp; Trainings</button>
                <button type="button" onclick="july3FilterMap('confirmed')" style="padding:9px 18px;border-radius:99px;border:none;background:#dcfce7;color:#166534;font-weight:700;cursor:pointer;">✅ Confirmed Assemblies</button>
                <button type="button" onclick="july3FilterMap('capital')" style="padding:9px 18px;border-radius:99px;border:none;background:#ffedd5;color:#c2410c;font-weight:700;cursor:pointer;">🔥 July 3 Capital Convergences</button>
            </div>
        <?php endif; ?>

        <div id="july3-osm-map" style="height:<?php echo esc_attr( $map_height ); ?>;width:100%;border-radius:24px;box-shadow:0 25px 50px -12px rgba(28,19,14,0.18);overflow:hidden;text-align:left;"></div>

        <?php if ( $is_teaser ) : ?>
            <div style="margin-top:24px;">
                <a href="<?php echo esc_url( home_url( '/assemblies/' ) ); ?>" class="j3-btn-orange" style="padding:14px 28px;font-size:16px;">
                    🗺️ Explore Assemblies, Full Map &amp; Key Dates →
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
                if (p.chat) html += '<a href="' + p.chat + '" target="_blank" rel="noopener" style="display:inline-block;margin-top:8px;padding:6px 12px;background:#ea580c;color:#fff;text-decoration:none;border-radius:8px;font-size:12px;font-weight:700;">🔗 Details &amp; RSVP →</a>';
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
    return '<section id="calendar" class="j3-container" style="padding:32px 24px 64px;text-align:center;">
        <div style="color:var(--j3-orange);font-size:12px;font-weight:800;letter-spacing:1.5px;text-transform:uppercase;">KEY DATES &amp; REGIONAL GATHERINGS</div>
        <h2 class="j3-poster-title" style="font-size:44px;margin:6px 0 20px;">THE MOBILIZATION CALENDAR</h2>
        <div style="border-radius:24px;overflow:hidden;box-shadow:0 20px 45px -12px rgba(28,19,14,0.16);background:#fff;"><iframe src="https://teamup.com/kswvbpogo3pf6txkut?showProfileAndInfo=0&showSidepanel=1&showViewSelector=1&showMenu=1" style="width:100%;height:600px;border:none;" loading="lazy"></iframe></div>
    </section>';
}
add_shortcode( 'teamup_calendar', 'july3_mod3_render_calendar' );
// === END OF MODULE 3 ===