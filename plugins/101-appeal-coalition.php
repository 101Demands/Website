<?php
/**
 * Plugin Name: 101 Demands — Module 2: Appeal & Coalition Wall
 * Description: Powers [mobilization_hero_and_appeal] and [coalition_logo_wall] with real database counts, Step 3 Mobilise CTA, and bot-protected onboarding.
 * Version:     1.2.0
 * Author:      101 Demands Global Tech Collective
 * License:     GPL-3.0+
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function july3_mod2_handle_appeal() {
    if ( ! empty( $_POST['website_hp'] ) ) {
        wp_send_json_error( array( 'msg' => 'Spam detected.' ) );
    }
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'july3_appeal_nonce' ) ) {
        wp_send_json_error( array( 'msg' => 'Security check failed. Please refresh the page and try again.' ) );
    }

    $mode    = isset( $_POST['mode'] ) ? sanitize_text_field( $_POST['mode'] ) : 'individual';
    $fname   = isset( $_POST['fname'] ) ? sanitize_text_field( $_POST['fname'] ) : '';
    $lname   = isset( $_POST['lname'] ) ? sanitize_text_field( $_POST['lname'] ) : '';
    $email   = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';
    $country = isset( $_POST['country'] ) ? sanitize_text_field( $_POST['country'] ) : '';
    $consent = isset( $_POST['gdpr_consent'] ) ? 'yes' : 'no';

    if ( $consent !== 'yes' ) {
        wp_send_json_error( array( 'msg' => 'Please tick the confirmation box to submit.' ) );
    }

    if ( $mode === 'org' && ! empty( $_POST['org_name'] ) ) {
        $orgname = sanitize_text_field( $_POST['org_name'] );
        $oid = wp_insert_post( array(
            'post_type'   => 'mobilization_org',
            'post_title'  => $orgname,
            'post_status' => 'pending', // Held for moderation before appearing on public wall
        ) );
        update_post_meta( $oid, '_org_website', esc_url_raw( isset( $_POST['org_web'] ) ? $_POST['org_web'] : '' ) );
        update_post_meta( $oid, '_org_logo_url', esc_url_raw( isset( $_POST['org_logo'] ) ? $_POST['org_logo'] : '' ) );
        update_post_meta( $oid, '_crm_org_contact', trim( $fname . ' ' . $lname ) );
        update_post_meta( $oid, '_crm_org_email', $email );
        if ( $country ) {
            wp_set_object_terms( $oid, $country, 'mobilization_region' );
        }
        wp_send_json_success( array( 'msg' => 'Thank you! ' . $orgname . ' has been submitted for verification and will appear on the Coalition Wall shortly.' ) );
    } else {
        $sid = wp_insert_post( array(
            'post_type'   => 'mobilization_signer',
            'post_title'  => trim( $fname . ' ' . $lname ) ? trim( $fname . ' ' . $lname ) : 'Verified Signatory',
            'post_status' => 'publish',
        ) );
        update_post_meta( $sid, '_signer_email', $email );
        update_post_meta( $sid, '_gdpr_consent_timestamp', current_time( 'mysql' ) );
        if ( $country ) {
            wp_set_object_terms( $sid, $country, 'mobilization_region' );
        }
        wp_send_json_success( array( 'msg' => 'Signature recorded! Now take Step 3: grab the Mobilisation Toolkit and invite 5 friends to join!' ) );
    }
}
add_action( 'wp_ajax_july3_submit_appeal', 'july3_mod2_handle_appeal' );
add_action( 'wp_ajax_nopriv_july3_submit_appeal', 'july3_mod2_handle_appeal' );

function july3_mod2_render_hero_appeal() {
    $org_posts    = wp_count_posts( 'mobilization_org' );
    $signer_posts = wp_count_posts( 'mobilization_signer' );
    $hub_posts    = wp_count_posts( 'mobilization_hub' );

    $org_count    = isset( $org_posts->publish ) ? (int) $org_posts->publish : 0;
    $signer_count = isset( $signer_posts->publish ) ? (int) $signer_posts->publish : 0;
    $hub_count    = isset( $hub_posts->publish ) ? (int) $hub_posts->publish : 0;
    $regions      = get_terms( array( 'taxonomy' => 'mobilization_region', 'hide_empty' => false ) );
    $nonce        = wp_create_nonce( 'july3_appeal_nonce' );

    ob_start();
    ?>
    <!-- Top Live Counter Strip -->
    <div class="j3-counter-bar">
        <span class="j3-counter-item">
            <strong class="j3-counter-num"><?php echo esc_html( number_format( $hub_count ) ); ?></strong> assemblies &amp; events
        </span>
        <span class="j3-counter-sep">•</span>
        <span class="j3-counter-item">
            <strong class="j3-counter-num"><?php echo esc_html( number_format( $org_count ) ); ?></strong> coalition organizations
        </span>
        <span class="j3-counter-sep">•</span>
        <span class="j3-counter-item">
            <strong class="j3-counter-num"><?php echo esc_html( number_format( $signer_count ) ); ?></strong> appeal signatories
        </span>
    </div>

    <section id="appeal" style="padding:48px 20px 64px;text-align:center;">
        <div class="j3-hero-box">
            <!-- Left Card: Crisp White Sheet of Paper -->
            <div class="j3-hero-sheet">
                <div style="color:#000;font-size:12px;font-weight:800;letter-spacing:2px;text-transform:uppercase;">GLOBAL MOBILIZATION</div>
                <div class="j3-poster-title" style="font-size:64px;color:#000;margin:4px 0 8px;line-height:0.95;">03.07</div>
                <div style="border-top:3px solid #000;border-bottom:3px solid #000;padding:8px 0;margin:10px 0 14px;font-family:var(--j3-font-display);font-size:20px;color:#000;letter-spacing:1px;text-transform:uppercase;">
                    100 CITIZEN DEMANDS • 1 BINDING MANDATE
                </div>
                <p style="font-size:13.5px;color:#000;margin:14px 0 0;line-height:1.6;text-align:left;">
                    📡 <strong>March 20–21:</strong> Global Assembly Link-Up<br>
                    🔥 <strong>July 3:</strong> Capital City Mass Demonstrations<br>
                    🏛️ <strong>101st Demand:</strong> Binding Citizens' Assemblies
                </p>
            </div>

            <!-- Right Column: High-Contrast Yellow & White on Black -->
            <div>
                <div style="display:inline-block;background:var(--j3-yellow);color:#000;font-size:12px;font-weight:800;letter-spacing:1.5px;text-transform:uppercase;padding:4px 10px;margin-bottom:14px;">
                    INTERNATIONAL MOVEMENT OF CITIZEN ASSEMBLIES
                </div>
                <h1 class="j3-poster-title" style="font-size:clamp(40px,4.8vw,60px);color:var(--j3-yellow);margin:0 0 14px;line-height:0.96;">
                    FOR REAL DEMOCRACY, CLIMATE JUSTICE &amp; FUTURE GENERATIONS
                </h1>
                <div class="j3-poster-title" style="font-size:26px;color:#fff;margin-bottom:18px;line-height:1.15;">
                    FOR <span style="color:var(--j3-yellow);">LIFE</span>, FOR <span style="color:var(--j3-yellow);">TAXING WEALTH</span>, FOR <span style="color:var(--j3-yellow);">PEACE</span> AND <span style="background:var(--j3-yellow);color:#000;padding:0 6px;">CITIZEN ASSEMBLIES</span>
                </div>
                <p style="color:#e5e5e5;font-size:16px;line-height:1.6;margin-bottom:28px;">
                    Across local town halls and community assemblies worldwide, we are deliberating on 101 concrete demands for daily life and real democracy—and converging on Capital &amp; Major Cities on July 3rd.
                </p>
                <div style="display:flex;flex-wrap:wrap;gap:14px;">
                    <a href="#sign-appeal" class="j3-btn-orange">↓ Sign the appeal</a>
                    <a href="#map-preview" class="j3-btn-outline">↓ Find an Assembly</a>
                </div>
            </div>
        </div>
    </section>

    <section id="sign-appeal" style="background:#000000;color:#ffffff;padding:72px 24px;width:100%;border-top:4px solid var(--j3-yellow);border-bottom:4px solid var(--j3-yellow);">
        <div style="max-width:1120px;margin:0 auto;display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:48px;align-items:center;">
            <div style="text-align:left;">
                <div style="display:inline-block;background:var(--j3-yellow);color:#000;font-size:12px;font-weight:800;letter-spacing:1.5px;text-transform:uppercase;padding:4px 10px;margin-bottom:12px;">JOIN THE MOVEMENT &amp; MOBILISE</div>
                <h2 class="j3-poster-title" style="font-size:52px;color:#fff;margin:0 0 14px;">ADD YOUR <span style="color:var(--j3-yellow);">SIGNATURE</span> TO THE CALL FOR JULY 3RD.</h2>
                <div class="j3-brush" style="margin-bottom:24px;">Let us assemble and stand together for the living.</div>
                <div style="color:#e5e5e5;font-size:15px;line-height:1.75;display:flex;flex-direction:column;gap:14px;">
                    <div>📍 <strong style="color:#fff;">Step 1 — Sign the Appeal:</strong> Add your voice as an individual citizen or endorse the call as a coalition organization.</div>
                    <div>🏛️ <strong style="color:#fff;">Step 2 — Join a Local Assembly:</strong> Connect with your nearest town hall or deliberative assembly.</div>
                    <div style="background:#141414;border-left:4px solid var(--j3-yellow);padding:12px 16px;">
                        📣 <strong style="color:var(--j3-yellow);">Step 3 — Get Mobilising!</strong> Every signatory is needed to help recruit and organise locally. Share the call in your community, union, or workplace—<a href="<?php echo esc_url( home_url( '/toolkits-flyers/' ) ); ?>" style="color:var(--j3-yellow);text-decoration:underline;font-weight:700;">grab the flyers &amp; mobilisation tools →</a>
                    </div>
                </div>
            </div>
            <div style="background:#ffffff;color:#000000;border:3px solid #000000;border-radius:2px;padding:32px;box-shadow:10px 10px 0px var(--j3-yellow);text-align:left;">
                <div style="display:flex;background:#f4f4f0;border:2px solid #000000;padding:4px;margin-bottom:22px;">
                    <button type="button" id="j3-tab-ind" onclick="july3SetAppealMode('individual')" style="flex:1;padding:10px;border:none;background:#000000;color:var(--j3-yellow);font-family:var(--j3-font-display);font-size:18px;letter-spacing:0.5px;text-transform:uppercase;cursor:pointer;">✋ Sign as an Individual</button>
                    <button type="button" id="j3-tab-org" onclick="july3SetAppealMode('org')" style="flex:1;padding:10px;border:none;background:transparent;color:#000000;font-family:var(--j3-font-display);font-size:18px;letter-spacing:0.5px;text-transform:uppercase;cursor:pointer;">🤝 Endorse as an Organization</button>
                </div>
                <form id="j3-appeal-form" onsubmit="return july3SubmitAppeal(event)">
                    <input type="hidden" name="mode" id="j3-appeal-mode" value="individual">
                    <input type="hidden" name="nonce" value="<?php echo esc_attr( $nonce ); ?>">
                    <div style="display:none !important;" aria-hidden="true">
                        <input type="text" name="website_hp" tabindex="-1" autocomplete="off">
                    </div>
                    <div id="j3-org-fields" style="display:none;margin-bottom:14px;padding:14px;background:#f4f4f0;border:2px solid #000;">
                        <label style="display:block;font-size:11px;font-weight:800;text-transform:uppercase;margin-bottom:4px;">Organization Name *</label>
                        <input type="text" name="org_name" placeholder="e.g., Media Revolution" style="width:100%;padding:10px;border:2px solid #000;margin-bottom:10px;">
                        <label style="display:block;font-size:11px;font-weight:800;text-transform:uppercase;margin-bottom:4px;">Website URL</label>
                        <input type="url" name="org_web" placeholder="https://..." style="width:100%;padding:10px;border:2px solid #000;margin-bottom:10px;">
                        <label style="display:block;font-size:11px;font-weight:800;text-transform:uppercase;margin-bottom:4px;">Logo URL (Optional)</label>
                        <input type="url" name="org_logo" placeholder="https://.../logo.png" style="width:100%;padding:10px;border:2px solid #000;">
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                        <div><label style="display:block;font-size:11px;font-weight:800;text-transform:uppercase;margin-bottom:4px;">First Name *</label><input type="text" name="fname" required style="width:100%;padding:11px;border:2px solid #000;background:#fff;"></div>
                        <div><label style="display:block;font-size:11px;font-weight:800;text-transform:uppercase;margin-bottom:4px;">Last Name *</label><input type="text" name="lname" required style="width:100%;padding:11px;border:2px solid #000;background:#fff;"></div>
                    </div>
                    <div style="margin-bottom:12px;"><label style="display:block;font-size:11px;font-weight:800;text-transform:uppercase;margin-bottom:4px;">Email Address *</label><input type="email" name="email" required style="width:100%;padding:11px;border:2px solid #000;background:#fff;"></div>
                    <div style="margin-bottom:14px;">
                        <label style="display:block;font-size:11px;font-weight:800;text-transform:uppercase;margin-bottom:4px;">Country / Region *</label>
                        <select name="country" style="width:100%;padding:11px;border:2px solid #000;background:#fff;">
                            <option value="">🌍 Select Country / Region...</option>
                            <?php if ( ! is_wp_error( $regions ) && ! empty( $regions ) ) : foreach ( $regions as $r ) : ?>
                                <option value="<?php echo esc_attr( $r->name ); ?>"><?php echo esc_html( $r->name ); ?></option>
                            <?php endforeach; endif; ?>
                        </select>
                    </div>
                    <label style="display:flex;gap:10px;align-items:flex-start;font-size:12px;background:#f4f4f0;padding:10px 12px;border:2px solid #000;margin-bottom:16px;cursor:pointer;">
                        <input type="checkbox" name="gdpr_consent" value="yes" required style="margin-top:3px;">
                        <span>I agree to receive assembly and mobilisation updates so we can build the July 3rd convergence together. (Zero third-party sharing; unsubscribe anytime).</span>
                    </label>
                    <button type="submit" class="j3-btn-orange" style="width:100%;justify-content:center;padding:14px;font-size:20px;">✍️ Sign the appeal</button>
                    <div id="j3-appeal-feedback" style="margin-top:12px;font-weight:700;display:none;text-align:center;"></div>
                </form>
            </div>
        </div>
    </section>
    <script>
    window.july3SetAppealMode = function(mode) {
        document.getElementById('j3-appeal-mode').value = mode;
        document.getElementById('j3-org-fields').style.display = (mode === 'org') ? 'block' : 'none';
        document.getElementById('j3-tab-ind').style.background = (mode === 'org') ? 'transparent' : '#000000';
        document.getElementById('j3-tab-ind').style.color = (mode === 'org') ? '#000000' : '#faf829';
        document.getElementById('j3-tab-org').style.background = (mode === 'org') ? '#000000' : 'transparent';
        document.getElementById('j3-tab-org').style.color = (mode === 'org') ? '#faf829' : '#000000';
    };
    window.july3SubmitAppeal = function(e) {
        e.preventDefault();
        var form = document.getElementById('j3-appeal-form');
        var fd = new FormData(form);
        fd.append('action', 'july3_submit_appeal');
        fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', { method:'POST', body:fd })
            .then(function(r){ return r.json(); })
            .then(function(res){
                var fb = document.getElementById('j3-appeal-feedback');
                fb.style.display = 'block';
                fb.style.color = res.success ? '#166534' : '#dc2626';
                fb.textContent = res.data ? res.data.msg : 'Submitted!';
                if (res.success) form.reset();
            });
        return false;
    };
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode( 'mobilization_hero_and_appeal', 'july3_mod2_render_hero_appeal' );

function july3_mod2_render_coalition_wall() {
    $orgs = get_posts( array( 'post_type' => 'mobilization_org', 'posts_per_page' => 24, 'orderby' => 'rand', 'post_status' => 'publish' ) );
    ob_start();
    ?>
    <section id="coalition" style="background:var(--j3-paper-gray);padding:64px 24px;text-align:center;width:100%;border-top:3px solid #000;">
        <div class="j3-container">
            <div style="display:inline-block;background:#000;color:var(--j3-yellow);font-size:12px;font-weight:800;letter-spacing:1.5px;text-transform:uppercase;padding:4px 12px;margin-bottom:8px;">COALITION &amp; MOVEMENT PARTNERS</div>
            <h2 class="j3-poster-title" style="font-size:48px;margin:6px 0 8px;color:#000;">SIGNATORY ORGANIZATIONS</h2>
            <div class="j3-brush" style="margin-bottom:28px;">Grassroots groups, unions &amp; alliances mobilizing together</div>
            <div style="display:flex;flex-wrap:wrap;justify-content:center;gap:20px;">
                <?php foreach ( $orgs as $o ) :
                    $web  = get_post_meta( $o->ID, '_org_website', true );
                    if ( ! $web ) { $web = get_permalink( $o->ID ); }
                    $logo = get_the_post_thumbnail_url( $o->ID, 'medium' );
                    if ( ! $logo ) { $logo = get_post_meta( $o->ID, '_org_logo_url', true ); }
                    $regs = wp_get_post_terms( $o->ID, 'mobilization_region', array( 'fields' => 'names' ) );
                    ?>
                    <a href="<?php echo esc_url( $web ); ?>" target="_blank" rel="noopener" style="width:200px;background:#fff;border:2px solid #000;border-radius:2px;padding:22px;display:flex;flex-direction:column;align-items:center;justify-content:center;text-decoration:none;color:#000;box-shadow:5px 5px 0px #000;">
                        <?php if ( $logo ) : ?>
                            <img src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr( $o->post_title ); ?>" loading="lazy" style="max-width:140px;max-height:70px;object-fit:contain;margin-bottom:10px;">
                        <?php endif; ?>
                        <strong style="font-size:15px;"><?php echo esc_html( $o->post_title ); ?></strong>
                        <?php if ( ! is_wp_error( $regs ) && ! empty( $regs ) ) : ?>
                            <span style="font-size:11px;color:#525252;margin-top:4px;font-weight:700;"><?php echo esc_html( implode( ', ', $regs ) ); ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
                <a href="<?php echo esc_url( home_url( '/#sign-appeal' ) ); ?>" onclick="if(window.july3SetAppealMode){july3SetAppealMode('org');}" style="width:200px;background:#000;border:2px dashed var(--j3-yellow);border-radius:2px;padding:22px;display:flex;flex-direction:column;align-items:center;justify-content:center;text-decoration:none;color:var(--j3-yellow);font-weight:800;box-shadow:5px 5px 0px var(--j3-yellow);">
                    <span style="font-size:28px;margin-bottom:6px;">+</span>
                    <span>Add Your Organization</span>
                </a>
            </div>
        </div>
    </section>
    <?php
    return ob_get_clean();
}
add_shortcode( 'coalition_logo_wall', 'july3_mod2_render_coalition_wall' );
// === END OF MODULE 2 ===