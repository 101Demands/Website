<?php
/**
 * Plugin Name: 101 Demands — Module 2: Appeal & Coalition Wall
 * Description: Powers [mobilization_hero_and_appeal] and [coalition_logo_wall] with real database counts, Step 3 Mobilise CTA, and bot-protected onboarding.
 * Version:     1.1.0
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
            'post_status' => 'publish',
        ) );
        update_post_meta( $oid, '_org_website', esc_url_raw( isset( $_POST['org_web'] ) ? $_POST['org_web'] : '' ) );
        update_post_meta( $oid, '_org_logo_url', esc_url_raw( isset( $_POST['org_logo'] ) ? $_POST['org_logo'] : '' ) );
        update_post_meta( $oid, '_crm_org_contact', trim( $fname . ' ' . $lname ) );
        update_post_meta( $oid, '_crm_org_email', $email );
        if ( $country ) {
            wp_set_object_terms( $oid, $country, 'mobilization_region' );
        }
        wp_send_json_success( array( 'msg' => 'Thank you! ' . $orgname . ' has been added to the Coalition Wall! Next step: visit Toolkits & Flyers to share with your members.' ) );
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
    <div style="background:#f3ede2;text-align:center;padding:10px 16px;font-size:15px;border-bottom:1px solid rgba(28,19,14,0.08);">
        <strong style="font-family:'Anton',sans-serif;font-size:20px;color:var(--j3-orange);"><?php echo esc_html( number_format( $hub_count ) ); ?></strong> assemblies &amp; events •
        <strong style="font-family:'Anton',sans-serif;font-size:20px;margin-left:6px;"><?php echo esc_html( number_format( $org_count ) ); ?></strong> coalition organizations •
        <strong style="font-family:'Anton',sans-serif;font-size:20px;margin-left:6px;"><?php echo esc_html( number_format( $signer_count ) ); ?></strong> appeal signatories
    </div>

    <section id="appeal" style="padding:48px 20px 64px;text-align:center;">
        <div style="max-width:1120px;margin:0 auto;background:radial-gradient(circle at 75% 25%, #4a2511 0%, #1c130e 70%);color:#fff;border-radius:28px;padding:56px 40px;box-shadow:0 30px 60px -15px rgba(28,19,14,0.45);text-align:left;display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:40px;align-items:center;">
            <div style="background:#120c08;border-radius:22px;padding:36px 28px;text-align:center;box-shadow:0 20px 40px rgba(0,0,0,0.45);border:1px solid rgba(255,255,255,0.08);">
                <div style="color:#d6d3d1;font-size:13px;font-weight:700;letter-spacing:2px;text-transform:uppercase;">GLOBAL MOBILIZATION</div>
                <div class="j3-poster-title" style="font-size:54px;color:var(--j3-orange);margin:6px 0;">JULY 3</div>
                <div style="background:var(--j3-amber);color:#120c08;font-family:'Anton',sans-serif;font-size:22px;padding:8px 16px;border-radius:8px;margin:12px 0;text-transform:uppercase;">
                    « THE PEOPLE'S CONSTITUTION &amp; DEMANDS »
                </div>
                <p style="font-size:13px;color:#a8a29e;margin:14px 0 0;line-height:1.5;">
                    📡 <strong>March 20–21:</strong> Global Assembly Link-Up<br>
                    🔥 <strong>July 3:</strong> Capital City Mass Demonstrations<br>
                    🗳️ <strong>Beyond:</strong> Global Elections Accountability Hub
                </p>
            </div>
            <div>
                <div style="color:var(--j3-orange);font-size:12px;font-weight:800;letter-spacing:1.5px;text-transform:uppercase;margin-bottom:10px;">
                    MOVEMENT OF CITIZEN ASSEMBLIES • MAJORITY WORLD &amp; GLOBE
                </div>
                <h1 class="j3-poster-title" style="font-size:clamp(38px,4.5vw,54px);color:var(--j3-amber);margin:0 0 12px;">
                    FOR REAL DEMOCRACY, CLIMATE JUSTICE &amp; FUTURE GENERATIONS
                </h1>
                <div class="j3-poster-title" style="font-size:25px;color:#fff;margin-bottom:18px;">
                    FOR <span style="color:#84cc16;">LIFE</span>, FOR <span style="color:#f59e0b;">TAXING WEALTH</span>, FOR <span style="color:#38bdf8;">PEACE</span> AND <span style="color:#f43f5e;">CITIZEN ASSEMBLIES</span>
                </div>
                <p style="color:#d6d3d1;font-size:15px;line-height:1.6;margin-bottom:24px;">
                    Across local town halls and citizen assemblies worldwide, we are deliberating on the People's Constitution—and converging on Capital &amp; Major Cities on July 3rd.
                </p>
                <div style="display:flex;flex-wrap:wrap;gap:12px;">
                    <a href="#sign-appeal" class="j3-btn-orange">↓ Sign the appeal</a>
                    <a href="#leaderboard" style="padding:10px 22px;border-radius:999px;border:1px solid rgba(255,255,255,0.3);color:#fff;text-decoration:none;font-weight:700;font-size:14px;">↓ Top Demands Leaderboard</a>
                    <a href="#map-preview" style="padding:10px 22px;border-radius:999px;border:1px solid rgba(245,158,11,0.5);color:var(--j3-amber);text-decoration:none;font-weight:700;font-size:14px;">↓ Find an Assembly</a>
                </div>
            </div>
        </div>
    </section>

    <section id="sign-appeal" style="background:var(--j3-espresso);color:#fff;padding:72px 24px;width:100%;">
        <div style="max-width:1120px;margin:0 auto;display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:48px;align-items:center;">
            <div style="text-align:left;">
                <div style="color:var(--j3-orange);font-size:12px;font-weight:800;letter-spacing:1.5px;text-transform:uppercase;margin-bottom:8px;">JOIN THE MOVEMENT &amp; MOBILISE</div>
                <h2 class="j3-poster-title" style="font-size:50px;color:#fff;margin:0 0 12px;">ADD YOUR <span style="color:var(--j3-orange);">SIGNATURE</span> TO THE CALL FOR JULY 3RD.</h2>
                <div class="j3-brush" style="margin-bottom:24px;">Let us assemble and march for the living.</div>
                <div style="color:#d6d3d1;font-size:15px;line-height:1.75;display:flex;flex-direction:column;gap:14px;">
                    <div>📍 <strong style="color:#fff;">Step 1 — Sign the Appeal:</strong> Add your voice as an individual citizen or endorse the call as a coalition organization.</div>
                    <div>🏛️ <strong style="color:#fff;">Step 2 — Join a Local Assembly:</strong> Connect with your nearest town hall or deliberative assembly to shape the demands.</div>
                    <div style="background:rgba(234,88,12,0.14);border-left:4px solid var(--j3-orange);padding:12px 16px;border-radius:0 12px 12px 0;">
                        📣 <strong style="color:var(--j3-amber);">Step 3 — Get Mobilising!</strong> Every signatory is needed to help recruit and organise locally. Share the call in your community, union, or workplace—<a href="<?php echo esc_url( home_url( '/toolkits-flyers/' ) ); ?>" style="color:#fff;text-decoration:underline;font-weight:700;">grab the flyers &amp; mobilisation tools →</a>
                    </div>
                </div>
            </div>
            <div style="background:#fbf8f3;color:var(--j3-espresso);border-radius:24px;padding:32px;box-shadow:0 25px 50px -12px rgba(0,0,0,0.5);text-align:left;">
                <div style="display:flex;background:#e7e0d6;border-radius:12px;padding:4px;margin-bottom:22px;">
                    <button type="button" id="j3-tab-ind" onclick="july3SetAppealMode('individual')" style="flex:1;padding:10px;border:none;border-radius:9px;background:var(--j3-espresso);color:#fff;font-weight:700;cursor:pointer;">✋ Sign as an Individual</button>
                    <button type="button" id="j3-tab-org" onclick="july3SetAppealMode('org')" style="flex:1;padding:10px;border:none;border-radius:9px;background:transparent;color:var(--j3-espresso);font-weight:700;cursor:pointer;">🤝 Endorse as an Organization</button>
                </div>
                <form id="j3-appeal-form" onsubmit="return july3SubmitAppeal(event)">
                    <input type="hidden" name="mode" id="j3-appeal-mode" value="individual">
                    <input type="hidden" name="nonce" value="<?php echo esc_attr( $nonce ); ?>">
                    <div style="display:none !important;" aria-hidden="true">
                        <input type="text" name="website_hp" tabindex="-1" autocomplete="off">
                    </div>
                    <div id="j3-org-fields" style="display:none;margin-bottom:14px;padding:14px;background:#fff;border-radius:12px;border:1px solid #e7e0d6;">
                        <label style="display:block;font-size:11px;font-weight:800;text-transform:uppercase;margin-bottom:4px;">Organization Name *</label>
                        <input type="text" name="org_name" placeholder="e.g., Media Revolution" style="width:100%;padding:10px;border-radius:8px;border:1px solid #d6d3d1;margin-bottom:10px;">
                        <label style="display:block;font-size:11px;font-weight:800;text-transform:uppercase;margin-bottom:4px;">Website URL</label>
                        <input type="url" name="org_web" placeholder="https://..." style="width:100%;padding:10px;border-radius:8px;border:1px solid #d6d3d1;margin-bottom:10px;">
                        <label style="display:block;font-size:11px;font-weight:800;text-transform:uppercase;margin-bottom:4px;">Logo URL (Optional)</label>
                        <input type="url" name="org_logo" placeholder="https://.../logo.png" style="width:100%;padding:10px;border-radius:8px;border:1px solid #d6d3d1;">
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                        <div><label style="display:block;font-size:11px;font-weight:800;text-transform:uppercase;margin-bottom:4px;">First Name *</label><input type="text" name="fname" required style="width:100%;padding:11px;border-radius:8px;border:1px solid #d6d3d1;background:#fff;"></div>
                        <div><label style="display:block;font-size:11px;font-weight:800;text-transform:uppercase;margin-bottom:4px;">Last Name *</label><input type="text" name="lname" required style="width:100%;padding:11px;border-radius:8px;border:1px solid #d6d3d1;background:#fff;"></div>
                    </div>
                    <div style="margin-bottom:12px;"><label style="display:block;font-size:11px;font-weight:800;text-transform:uppercase;margin-bottom:4px;">Email Address *</label><input type="email" name="email" required style="width:100%;padding:11px;border-radius:8px;border:1px solid #d6d3d1;background:#fff;"></div>
                    <div style="margin-bottom:14px;">
                        <label style="display:block;font-size:11px;font-weight:800;text-transform:uppercase;margin-bottom:4px;">Country / Region *</label>
                        <select name="country" style="width:100%;padding:11px;border-radius:8px;border:1px solid #d6d3d1;background:#fff;">
                            <option value="">🌍 Select Country / Region...</option>
                            <?php if ( ! is_wp_error( $regions ) && ! empty( $regions ) ) : foreach ( $regions as $r ) : ?>
                                <option value="<?php echo esc_attr( $r->name ); ?>"><?php echo esc_html( $r->name ); ?></option>
                            <?php endforeach; endif; ?>
                        </select>
                    </div>
                    <label style="display:flex;gap:10px;align-items:flex-start;font-size:12px;background:#fff;padding:10px 12px;border-radius:8px;border:1px solid #e7e0d6;margin-bottom:16px;cursor:pointer;">
                        <input type="checkbox" name="gdpr_consent" value="yes" required style="margin-top:3px;">
                        <span>I agree to receive assembly and mobilisation updates so we can build the July 3rd convergence together. (Zero third-party sharing; unsubscribe anytime).</span>
                    </label>
                    <button type="submit" class="j3-btn-orange" style="width:100%;justify-content:center;padding:14px;font-size:16px;">✍️ Sign the appeal</button>
                    <div id="j3-appeal-feedback" style="margin-top:12px;font-weight:700;display:none;text-align:center;"></div>
                </form>
            </div>
        </div>
    </section>
    <script>
    window.july3SetAppealMode = function(mode) {
        document.getElementById('j3-appeal-mode').value = mode;
        document.getElementById('j3-org-fields').style.display = (mode === 'org') ? 'block' : 'none';
        document.getElementById('j3-tab-ind').style.background = (mode === 'org') ? 'transparent' : '#1c130e';
        document.getElementById('j3-tab-ind').style.color = (mode === 'org') ? '#1c130e' : '#fff';
        document.getElementById('j3-tab-org').style.background = (mode === 'org') ? '#1c130e' : 'transparent';
        document.getElementById('j3-tab-org').style.color = (mode === 'org') ? '#fff' : '#1c130e';
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
                fb.style.color = res.success ? '#16a34a' : '#dc2626';
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
    $orgs = get_posts( array( 'post_type' => 'mobilization_org', 'posts_per_page' => -1, 'post_status' => 'publish' ) );
    ob_start();
    ?>
    <section id="coalition" style="background:#f3ede2;padding:64px 24px;text-align:center;width:100%;">
        <div class="j3-container">
            <div style="color:var(--j3-orange);font-size:12px;font-weight:800;letter-spacing:1.5px;text-transform:uppercase;">COALITION &amp; MOVEMENT PARTNERS</div>
            <h2 class="j3-poster-title" style="font-size:46px;margin:6px 0 8px;">SIGNATORY ORGANIZATIONS</h2>
            <div class="j3-brush" style="margin-bottom:28px;">Grassroots groups, unions &amp; alliances mobilizing together</div>
            <div style="display:flex;flex-wrap:wrap;justify-content:center;gap:20px;">
                <?php foreach ( $orgs as $o ) :
                    $web  = get_post_meta( $o->ID, '_org_website', true );
                    if ( ! $web ) { $web = get_permalink( $o->ID ); }
                    $logo = get_the_post_thumbnail_url( $o->ID, 'medium' );
                    if ( ! $logo ) { $logo = get_post_meta( $o->ID, '_org_logo_url', true ); }
                    $regs = wp_get_post_terms( $o->ID, 'mobilization_region', array( 'fields' => 'names' ) );
                    ?>
                    <a href="<?php echo esc_url( $web ); ?>" target="_blank" rel="noopener" style="width:200px;background:#fff;border-radius:18px;padding:22px;display:flex;flex-direction:column;align-items:center;justify-content:center;text-decoration:none;color:var(--j3-espresso);box-shadow:0 12px 28px -8px rgba(28,19,14,0.1);">
                        <?php if ( $logo ) : ?>
                            <img src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr( $o->post_title ); ?>" loading="lazy" style="max-width:140px;max-height:70px;object-fit:contain;margin-bottom:10px;">
                        <?php endif; ?>
                        <strong style="font-size:15px;"><?php echo esc_html( $o->post_title ); ?></strong>
                        <?php if ( ! is_wp_error( $regs ) && ! empty( $regs ) ) : ?>
                            <span style="font-size:11px;color:#78716c;margin-top:4px;"><?php echo esc_html( implode( ', ', $regs ) ); ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
                <a href="<?php echo esc_url( home_url( '/#sign-appeal' ) ); ?>" onclick="if(window.july3SetAppealMode){july3SetAppealMode('org');}" style="width:200px;background:rgba(234,88,12,0.08);border:2px dashed var(--j3-orange);border-radius:18px;padding:22px;display:flex;flex-direction:column;align-items:center;justify-content:center;text-decoration:none;color:var(--j3-orange);font-weight:800;">
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