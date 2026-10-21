<?php
/**
 * Plugin Name: 101 Demands — Module 4: Demands Leaderboard, Voting & Evidence Dossiers
 * Description: Powers [demands_leaderboard] (Top 5 Homepage Leaderboard) and [demands_voting_deck] (Full /demands/ page with Bot Protection, Separate 101st Demand, and Multi-Assembly Evidence Dossiers).
 * Version:     1.1.0
 * Author:      101 Demands Global Tech Collective
 * License:     GPL-3.0+
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function july3_mod4_voter_hash() {
    $ip   = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    $ua   = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : 'browser';
    $salt = wp_salt( 'auth' ) . gmdate( 'Y-m' );
    return hash( 'sha256', $ip . '|' . $ua . '|' . $salt );
}

function july3_mod4_is_101st( $post ) {
    if ( get_post_meta( $post->ID, '_is_101st_demand', true ) ) {
        return true;
    }
    $title_lower = strtolower( $post->post_title );
    return ( strpos( $title_lower, '101st' ) !== false || strpos( $title_lower, '101.' ) !== false || strpos( $title_lower, 'your own demand' ) !== false );
}

function july3_mod4_handle_vote() {
    global $wpdb;
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'july3_demands_nonce' ) ) {
        wp_send_json_error( array( 'msg' => 'Security token expired. Please refresh.' ) );
    }

    $post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
    $vote    = isset( $_POST['vote'] ) ? sanitize_text_field( $_POST['vote'] ) : '';
    if ( ! $post_id || ! in_array( $vote, array( 'agree', 'disagree' ), true ) ) {
        wp_send_json_error( array( 'msg' => 'Invalid vote' ) );
    }

    $hash = july3_mod4_voter_hash();
    $rate_key = 'j3_rl_' . substr( $hash, 0, 16 );
    $recent_votes = (int) get_transient( $rate_key );
    if ( $recent_votes > 40 ) {
        wp_send_json_error( array( 'msg' => 'Rate limit reached. Please slow down.' ) );
    }
    set_transient( $rate_key, $recent_votes + 1, 60 );

    $table = $wpdb->prefix . '101_votes';
    $existing = $wpdb->get_var( $wpdb->prepare( "SELECT vote_value FROM $table WHERE demand_id = %d AND voter_hash = %s", $post_id, $hash ) );
    if ( $existing ) {
        $wpdb->update( $table, array( 'vote_value' => $vote ), array( 'demand_id' => $post_id, 'voter_hash' => $hash ) );
    } else {
        $wpdb->insert( $table, array( 'demand_id' => $post_id, 'voter_hash' => $hash, 'vote_value' => $vote, 'verification_tier' => 'guest' ) );
    }

    $agree    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE demand_id = %d AND vote_value = 'agree'", $post_id ) );
    $disagree = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE demand_id = %d AND vote_value = 'disagree'", $post_id ) );
    update_post_meta( $post_id, '_demand_votes_agree', $agree );
    update_post_meta( $post_id, '_demand_votes_disagree', $disagree );

    $tot = max( 1, $agree + $disagree );
    wp_send_json_success( array(
        'agree'    => $agree,
        'disagree' => $disagree,
        'total'    => $agree + $disagree,
        'pct'      => round( ( $agree / $tot ) * 100 ),
    ) );
}
add_action( 'wp_ajax_july3_cast_vote', 'july3_mod4_handle_vote' );
add_action( 'wp_ajax_nopriv_july3_cast_vote', 'july3_mod4_handle_vote' );

function july3_mod4_propose_demand() {
    if ( ! empty( $_POST['hp_trap'] ) ) {
        wp_send_json_error( array( 'msg' => 'Bot blocked.' ) );
    }
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'july3_demands_nonce' ) ) {
        wp_send_json_error( array( 'msg' => 'Security check failed.' ) );
    }
    $title  = isset( $_POST['title'] ) ? sanitize_text_field( $_POST['title'] ) : '';
    $origin = isset( $_POST['origin'] ) && ! empty( $_POST['origin'] ) ? sanitize_text_field( $_POST['origin'] ) : 'Citizen / Assembly Proposal';
    if ( strlen( $title ) < 5 ) {
        wp_send_json_error( array( 'msg' => 'Please enter a clear demand headline.' ) );
    }
    $pid = wp_insert_post( array(
        'post_type'   => 'mobilization_demand',
        'post_title'  => $title,
        'post_status' => 'pending',
    ) );
    update_post_meta( $pid, '_demand_origin', $origin );
    update_post_meta( $pid, '_demand_assemblies_count', 1 );
    wp_send_json_success( array( 'msg' => 'Proposal submitted to the moderation queue!' ) );
}
add_action( 'wp_ajax_july3_propose_demand', 'july3_mod4_propose_demand' );
add_action( 'wp_ajax_nopriv_july3_propose_demand', 'july3_mod4_propose_demand' );

function july3_mod4_add_evidence() {
    if ( ! empty( $_POST['hp_trap'] ) ) {
        wp_send_json_error( array( 'msg' => 'Bot blocked.' ) );
    }
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'july3_demands_nonce' ) ) {
        wp_send_json_error( array( 'msg' => 'Security check failed.' ) );
    }
    $demand_id = isset( $_POST['demand_id'] ) ? (int) $_POST['demand_id'] : 0;
    $assembly  = isset( $_POST['assembly'] ) ? sanitize_text_field( $_POST['assembly'] ) : '';
    $summary   = isset( $_POST['summary'] ) ? sanitize_textarea_field( $_POST['summary'] ) : '';
    $doc_url   = isset( $_POST['doc_url'] ) ? esc_url_raw( $_POST['doc_url'] ) : '';

    if ( ! $demand_id || empty( $assembly ) || empty( $summary ) ) {
        wp_send_json_error( array( 'msg' => 'Please enter your Assembly/Group name and report summary.' ) );
    }

    $dossiers = get_post_meta( $demand_id, '_demand_evidence_dossiers', true );
    if ( ! is_array( $dossiers ) ) {
        $dossiers = array();
    }
    $dossiers[] = array(
        'assembly' => $assembly,
        'summary'  => $summary,
        'url'      => $doc_url,
        'date'     => gmdate( 'M j, Y' ),
    );
    update_post_meta( $demand_id, '_demand_evidence_dossiers', $dossiers );

    $asm_count = (int) get_post_meta( $demand_id, '_demand_assemblies_count', true );
    update_post_meta( $demand_id, '_demand_assemblies_count', max( 1, $asm_count ) + 1 );

    wp_send_json_success( array( 'msg' => 'Assembly report & evidence attached to this demand!' ) );
}
add_action( 'wp_ajax_july3_add_evidence', 'july3_mod4_add_evidence' );
add_action( 'wp_ajax_nopriv_july3_add_evidence', 'july3_mod4_add_evidence' );

function july3_mod4_get_split_demands() {
    $raw = get_posts( array(
        'post_type'      => 'mobilization_demand',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
    ) );

    $regular = array();
    $wildcard_101 = null;

    foreach ( $raw as $d ) {
        $a        = (int) get_post_meta( $d->ID, '_demand_votes_agree', true );
        $dis      = (int) get_post_meta( $d->ID, '_demand_votes_disagree', true );
        $tot      = $a + $dis;
        $origin   = get_post_meta( $d->ID, '_demand_origin', true );
        $body     = $d->post_excerpt ? $d->post_excerpt : $d->post_content;
        $dossiers = get_post_meta( $d->ID, '_demand_evidence_dossiers', true );
        if ( ! is_array( $dossiers ) ) { $dossiers = array(); }

        $clean_title = preg_replace( '/^\d+\.\s*/', '', $d->post_title );
        $item = array(
            'id'       => $d->ID,
            'title'    => $clean_title,
            'content'  => wp_strip_all_tags( $body ),
            'origin'   => $origin ? $origin : 'House of the People Charter',
            'agree'    => $a,
            'disagree' => $dis,
            'total'    => $tot,
            'pct'      => $tot > 0 ? round( ( $a / $tot ) * 100 ) : 0,
            'dossiers' => $dossiers,
        );

        if ( july3_mod4_is_101st( $d ) ) {
            $wildcard_101 = $item;
        } else {
            $regular[] = $item;
        }
    }

    usort( $regular, function( $x, $y ) {
        if ( $y['agree'] !== $x['agree'] ) {
            return $y['agree'] - $x['agree'];
        }
        return $x['id'] - $y['id'];
    } );

    return array( 'regular' => $regular, 'wildcard' => $wildcard_101 );
}

function july3_mod4_render_leaderboard() {
    $split    = july3_mod4_get_split_demands();
    $top5     = array_slice( $split['regular'], 0, 5 );
    $wildcard = $split['wildcard'];
    $total_demands = count( $split['regular'] );

    ob_start();
    ?>
    <section id="leaderboard" class="j3-container" style="padding:64px 24px;text-align:center;">
        <div style="color:var(--j3-orange);font-size:12px;font-weight:800;letter-spacing:1.5px;text-transform:uppercase;">LIVE CITIZEN &amp; ASSEMBLY PULSE</div>
        <h2 class="j3-poster-title" style="font-size:48px;margin:6px 0 4px;">THE PEOPLE'S CHARTER — TOP DEMANDS LEADERBOARD</h2>
        <div class="j3-brush" style="margin-bottom:28px;">Ranked live by citizen &amp; assembly votes across the movement</div>

        <div style="max-width:900px;margin:0 auto;display:flex;flex-direction:column;gap:14px;text-align:left;">
            <?php foreach ( $top5 as $idx => $item ) : ?>
                <div style="background:#fff;border:1px solid #e7e0d6;border-radius:18px;padding:20px 26px;display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap;box-shadow:0 12px 24px -10px rgba(28,19,14,0.08);">
                    <div style="display:flex;align-items:center;gap:18px;flex:1;min-width:240px;">
                        <span class="j3-poster-title" style="font-size:38px;color:var(--j3-orange);min-width:44px;">#<?php echo esc_html( sprintf( '%02d', $idx + 1 ) ); ?></span>
                        <div>
                            <h3 style="margin:0 0 4px;font-size:19px;font-weight:800;color:var(--j3-espresso);"><?php echo esc_html( $item['title'] ); ?></h3>
                            <span style="font-size:12px;color:#78716c;font-weight:600;">🏛️ <?php echo esc_html( $item['origin'] ); ?> • 📚 <?php echo count( $item['dossiers'] ); ?> Assembly Reports</span>
                        </div>
                    </div>
                    <div style="display:flex;align-items:center;gap:14px;">
                        <span style="background:#dcfce7;color:#166534;font-weight:800;font-size:13px;padding:7px 14px;border-radius:99px;">
                            👍 <?php echo esc_html( $item['agree'] ); ?> Votes <?php if ( $item['total'] > 0 ) echo '(' . esc_html( $item['pct'] ) . '%)'; ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ( $wildcard ) : ?>
            <div style="max-width:900px;margin:24px auto 0;background:linear-gradient(135deg, #1c130e 0%, #3b1d0e 100%);color:#fff;border:2px solid var(--j3-amber);border-radius:20px;padding:24px 28px;text-align:left;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:20px;">
                <div style="flex:1;min-width:260px;">
                    <span style="background:var(--j3-amber);color:#120c08;font-weight:800;font-size:11px;padding:4px 10px;border-radius:99px;text-transform:uppercase;letter-spacing:1px;">🌟 SPECIAL WILDCARD MANDATE — THE 101st DEMAND</span>
                    <h3 style="margin:10px 0 6px;font-size:21px;font-weight:800;color:var(--j3-amber);"><?php echo esc_html( $wildcard['title'] ); ?></h3>
                    <p style="margin:0;font-size:14px;color:#d6d3d1;line-height:1.5;"><?php echo esc_html( $wildcard['content'] ); ?></p>
                </div>
                <a href="<?php echo esc_url( home_url( '/demands/#demand-101-wildcard' ) ); ?>" class="j3-btn-orange">✨ Add Your 101st Demand →</a>
            </div>
        <?php endif; ?>

        <div style="margin-top:32px;">
            <a href="<?php echo esc_url( home_url( '/demands/' ) ); ?>" class="j3-btn-orange" style="padding:16px 32px;font-size:17px;">
                🗳️ View, Search &amp; Vote on All <?php echo esc_html( $total_demands ); ?>+ Demands →
            </a>
        </div>
    </section>
    <?php
    return ob_get_clean();
}
add_shortcode( 'demands_leaderboard', 'july3_mod4_render_leaderboard' );

function july3_mod4_render_voting_deck() {
    $split    = july3_mod4_get_split_demands();
    $items    = $split['regular'];
    $wildcard = $split['wildcard'];
    $nonce    = wp_create_nonce( 'july3_demands_nonce' );

    $palettes = array(
        array( 'bg' => '#1c130e', 'text' => '#ffffff', 'num' => '#443024', 'accent' => '#ea580c', 'sub' => '#d6d3d1' ),
        array( 'bg' => '#ffffff', 'text' => '#1c130e', 'num' => '#e7e0d6', 'accent' => '#1c130e', 'sub' => '#57534e' ),
        array( 'bg' => '#ea580c', 'text' => '#1c130e', 'num' => '#fb923c', 'accent' => '#1c130e', 'sub' => '#291d16' ),
    );

    ob_start();
    ?>
    <section id="demands" class="j3-container" style="padding:56px 24px;text-align:center;">
        <div style="color:var(--j3-orange);font-size:12px;font-weight:800;letter-spacing:1.5px;text-transform:uppercase;">DELIBERATIVE DEMOCRACY &amp; ASSEMBLY EVIDENCE</div>
        <h2 class="j3-poster-title" style="font-size:50px;margin:6px 0 4px;">THE PEOPLE'S CHARTER &amp; DEMANDS</h2>
        <div class="j3-brush" style="margin-bottom:28px;">Search proposals, cast your vote, or attach local assembly reports &amp; expert evidence</div>

        <div style="max-width:860px;margin:0 auto 40px;background:var(--j3-espresso);color:#fff;border-radius:22px;padding:28px;box-shadow:0 20px 40px -15px rgba(28,19,14,0.3);text-align:left;">
            <label for="july3-demand-search" style="display:block;font-weight:700;font-size:16px;margin-bottom:10px;color:var(--j3-amber);">
                🔍 Search existing demands before proposing (or find a topic like "housing" or "tax" to attach your Assembly Report):
            </label>
            <input type="text" id="july3-demand-search" oninput="july3FilterDemands(this.value)" placeholder="Type a keyword (e.g. 'tax', 'housing', 'nature', 'media')..." style="width:100%;padding:14px 18px;font-size:15px;border:none;border-radius:12px;background:#fbf8f3;color:#1c130e;">
            <div id="july3-duplicate-matches" style="margin-top:14px;display:none;padding:12px 16px;background:rgba(255,255,255,0.08);border-radius:10px;font-size:14px;"></div>
            <div id="july3-propose-box" style="display:none;margin-top:16px;padding-top:16px;border-top:1px solid rgba(255,255,255,0.15);">
                <p style="margin:0 0 10px;font-weight:700;color:#fde68a;">✨ Propose a Distinct Demand for Moderation:</p>
                <input type="text" id="july3-new-demand-title" placeholder="Headline of your demand" style="width:100%;padding:10px;border-radius:8px;border:none;margin-bottom:8px;">
                <input type="text" id="july3-new-demand-origin" placeholder="Proposed by (e.g., Berlin Assembly / Exeter Town Hall)" style="width:100%;padding:10px;border-radius:8px;border:none;margin-bottom:8px;">
                <input type="text" id="july3-demand-hp" style="display:none !important;" tabindex="-1" autocomplete="off">
                <button type="button" onclick="july3SubmitNewDemand()" class="j3-btn-orange">➕ Submit Proposal</button>
                <div id="july3-propose-msg" style="margin-top:8px;font-weight:700;color:#4ade80;"></div>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:24px;text-align:left;">
            <?php foreach ( $items as $idx => $item ) :
                $pal = $palettes[ $idx % 3 ];
                $dcount = count( $item['dossiers'] );
                ?>
                <div class="july3-demand-card" data-search="<?php echo esc_attr( strtolower( $item['title'] . ' ' . $item['content'] ) ); ?>" style="background:<?php echo esc_attr( $pal['bg'] ); ?>;color:<?php echo esc_attr( $pal['text'] ); ?>;border-radius:22px;padding:30px;box-shadow:0 18px 38px -12px rgba(28,19,14,0.14);display:flex;flex-direction:column;justify-content:space-between;">
                    <div>
                        <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:10px;">
                            <span class="j3-poster-title" style="font-size:44px;color:<?php echo esc_attr( $pal['num'] ); ?>;">#<?php echo esc_html( sprintf( '%02d', $idx + 1 ) ); ?></span>
                            <span style="font-size:11px;font-weight:800;text-transform:uppercase;opacity:0.9;">🏛️ <?php echo esc_html( $item['origin'] ); ?></span>
                        </div>
                        <h3 style="margin:0 0 12px;font-size:20px;font-weight:800;line-height:1.3;color:<?php echo esc_attr( $pal['accent'] ); ?>;text-decoration:underline;text-underline-offset:4px;">
                            <?php echo esc_html( $item['title'] ); ?>
                        </h3>
                        <p style="font-size:14.5px;color:<?php echo esc_attr( $pal['sub'] ); ?>;line-height:1.6;margin:0 0 14px;"><?php echo esc_html( $item['content'] ); ?></p>

                        <!-- Assembly Reports & Expert Dossiers Drawer -->
                        <div style="background:rgba(0,0,0,0.12);border-radius:12px;padding:10px 14px;margin-top:12px;font-size:13px;">
                            <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;">
                                <span style="font-weight:700;">📚 <?php echo esc_html( $dcount ); ?> Assembly Report<?php echo $dcount === 1 ? '' : 's'; ?> / Expert Input</span>
                                <button type="button" onclick="july3ToggleDrawer('dossier-<?php echo esc_attr( $item['id'] ); ?>')" style="background:rgba(255,255,255,0.2);color:inherit;border:none;padding:4px 10px;border-radius:6px;font-size:12px;font-weight:700;cursor:pointer;">
                                    <?php echo $dcount > 0 ? '📖 Read / ➕ Add Report' : '➕ Attach Assembly Report'; ?>
                                </button>
                            </div>
                            <div id="dossier-<?php echo esc_attr( $item['id'] ); ?>" style="display:none;margin-top:12px;padding-top:12px;border-top:1px solid rgba(255,255,255,0.2);">
                                <?php if ( $dcount > 0 ) : foreach ( $item['dossiers'] as $dos ) : ?>
                                    <div style="background:rgba(255,255,255,0.92);color:#1c130e;padding:10px;border-radius:8px;margin-bottom:8px;">
                                        <strong>🏛️ <?php echo esc_html( $dos['assembly'] ); ?></strong> <span style="font-size:11px;color:#78716c;">(<?php echo esc_html( $dos['date'] ); ?>)</span>
                                        <p style="margin:4px 0;font-size:12.5px;"><?php echo esc_html( $dos['summary'] ); ?></p>
                                        <?php if ( ! empty( $dos['url'] ) ) : ?>
                                            <a href="<?php echo esc_url( $dos['url'] ); ?>" target="_blank" rel="noopener" style="color:#ea580c;font-weight:700;font-size:12px;">🔗 View Full Report / Document →</a>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; endif; ?>
                                <div style="margin-top:8px;">
                                    <div style="font-weight:700;margin-bottom:6px;font-size:12px;">📎 Compile Assembly Report or Expert Evidence on this Demand:</div>
                                    <input type="text" id="ev-asm-<?php echo esc_attr( $item['id'] ); ?>" placeholder="Assembly or Expert Name (e.g. Bristol Housing Assembly)" style="width:100%;padding:8px;border-radius:6px;border:1px solid #ccc;margin-bottom:6px;font-size:12px;color:#1c130e;background:#fff;">
                                    <textarea id="ev-sum-<?php echo esc_attr( $item['id'] ); ?>" rows="2" placeholder="Key findings or local resolution summary..." style="width:100%;padding:8px;border-radius:6px;border:1px solid #ccc;margin-bottom:6px;font-size:12px;color:#1c130e;background:#fff;"></textarea>
                                    <input type="url" id="ev-url-<?php echo esc_attr( $item['id'] ); ?>" placeholder="Link to full document / PDF / report (optional)" style="width:100%;padding:8px;border-radius:6px;border:1px solid #ccc;margin-bottom:6px;font-size:12px;color:#1c130e;background:#fff;">
                                    <button type="button" onclick="july3SubmitEvidence(<?php echo esc_attr( $item['id'] ); ?>)" style="background:#1c130e;color:#fff;border:none;padding:7px 14px;border-radius:6px;font-weight:700;font-size:12px;cursor:pointer;">Save Report to Demand</button>
                                    <div id="ev-msg-<?php echo esc_attr( $item['id'] ); ?>" style="margin-top:6px;font-weight:700;font-size:12px;"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div style="margin-top:20px;padding-top:16px;border-top:1px solid rgba(128,128,128,0.25);">
                        <div id="july3-vote-bar-<?php echo esc_attr( $item['id'] ); ?>" style="font-size:13px;font-weight:700;margin-bottom:10px;">
                            <?php if ( $item['total'] > 0 ) : ?>
                                📊 Citizen Pulse: <?php echo esc_html( $item['pct'] ); ?>% Agree (<?php echo esc_html( $item['agree'] ); ?> 👍 / <?php echo esc_html( $item['disagree'] ); ?> 👎)
                            <?php else : ?>
                                🗳️ 0 online votes yet — be the first to cast a pulse vote!
                            <?php endif; ?>
                        </div>
                        <div style="display:flex;gap:8px;">
                            <button type="button" onclick="july3CastVote(<?php echo esc_attr( $item['id'] ); ?>,'agree',this)" style="flex:1;padding:10px;background:rgba(255,255,255,0.95);color:#166534;border:none;border-radius:10px;font-weight:800;cursor:pointer;">👍 Agree</button>
                            <button type="button" onclick="july3CastVote(<?php echo esc_attr( $item['id'] ); ?>,'disagree',this)" style="flex:1;padding:10px;background:rgba(255,255,255,0.95);color:#9f1239;border:none;border-radius:10px;font-weight:800;cursor:pointer;">👎 Disagree</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ( $wildcard ) : ?>
            <div id="demand-101-wildcard" style="margin-top:44px;background:linear-gradient(135deg, #1c130e 0%, #3b1d0e 100%);color:#fff;border:3px solid var(--j3-amber);border-radius:26px;padding:36px;text-align:left;box-shadow:0 25px 50px -12px rgba(28,19,14,0.35);">
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:12px;">
                    <span style="background:var(--j3-amber);color:#120c08;font-weight:900;font-size:12px;padding:6px 14px;border-radius:99px;text-transform:uppercase;letter-spacing:1px;">🌟 SEPARATE PERSONAL &amp; ASSEMBLY MANDATE</span>
                    <span class="j3-poster-title" style="font-size:36px;color:var(--j3-amber);">THE 101st DEMAND</span>
                </div>
                <h3 style="margin:0 0 10px;font-size:26px;font-weight:800;color:#fff;"><?php echo esc_html( $wildcard['title'] ); ?></h3>
                <p style="font-size:15.5px;color:#d6d3d1;line-height:1.6;margin:0 0 20px;"><?php echo esc_html( $wildcard['content'] ); ?></p>
                <button type="button" onclick="document.getElementById('july3-propose-box').style.display='block';document.getElementById('july3-demand-search').scrollIntoView({behavior:'smooth'});" class="j3-btn-orange">
                    ✍️ Submit Your Assembly's 101st Demand Proposal ↑
                </button>
            </div>
        <?php endif; ?>
    </section>

    <script>
    var july3DemandsNonce = '<?php echo esc_js( $nonce ); ?>';
    window.july3ToggleDrawer = function(id) {
        var el = document.getElementById(id);
        if (el) el.style.display = (el.style.display === 'none') ? 'block' : 'none';
    };
    window.july3FilterDemands = function(val) {
        var q = val.toLowerCase().trim();
        var cards = document.querySelectorAll('.july3-demand-card');
        var matches = 0;
        cards.forEach(function(card) {
            var hay = card.getAttribute('data-search') || '';
            if (!q || hay.indexOf(q) !== -1) {
                card.style.display = 'flex';
                if (q.length > 1) matches++;
            } else {
                card.style.display = 'none';
            }
        });
        var mbox = document.getElementById('july3-duplicate-matches');
        var pbox = document.getElementById('july3-propose-box');
        if (q.length > 1) {
            mbox.style.display = 'block';
            if (matches > 0) {
                mbox.innerHTML = '✅ Found <strong>' + matches + '</strong> matching demand(s) below! You can vote or click <strong>"Attach Assembly Report"</strong> on any card below to compile your evidence, or <a href="javascript:void(0)" onclick="document.getElementById(\'july3-propose-box\').style.display=\'block\'" style="color:#fde68a;text-decoration:underline;">propose a distinct demand</a>.';
                pbox.style.display = 'none';
            } else {
                mbox.innerHTML = '💡 No existing demand matches <strong>"' + val.replace(/</g,'&lt;') + '"</strong> yet! Submit it below:';
                document.getElementById('july3-new-demand-title').value = val;
                pbox.style.display = 'block';
            }
        } else {
            mbox.style.display = 'none';
            pbox.style.display = 'none';
        }
    };
    window.july3CastVote = function(postId, voteType, btn) {
        var fd = new FormData();
        fd.append('action', 'july3_cast_vote');
        fd.append('nonce', july3DemandsNonce);
        fd.append('post_id', postId);
        fd.append('vote', voteType);
        btn.textContent = '⏳...';
        fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', { method: 'POST', body: fd })
            .then(function(r){ return r.json(); })
            .then(function(res){
                if (res.success) {
                    var bar = document.getElementById('july3-vote-bar-' + postId);
                    bar.innerHTML = '✅ Vote saved! Citizen Pulse: <strong>' + res.data.pct + '% Agree</strong> (' + res.data.agree + ' 👍 / ' + res.data.disagree + ' 👎)';
                    btn.textContent = voteType === 'agree' ? '👍 Agreed!' : '👎 Recorded';
                } else if (res.data && res.data.msg) {
                    alert(res.data.msg);
                }
            });
    };
    window.july3SubmitNewDemand = function() {
        var fd = new FormData();
        fd.append('action', 'july3_propose_demand');
        fd.append('nonce', july3DemandsNonce);
        fd.append('hp_trap', document.getElementById('july3-demand-hp').value);
        fd.append('title', document.getElementById('july3-new-demand-title').value);
        fd.append('origin', document.getElementById('july3-new-demand-origin').value);
        fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', { method: 'POST', body: fd })
            .then(function(r){ return r.json(); })
            .then(function(res){
                document.getElementById('july3-propose-msg').textContent = res.data ? res.data.msg : 'Submitted!';
            });
    };
    window.july3SubmitEvidence = function(demandId) {
        var fd = new FormData();
        fd.append('action', 'july3_add_evidence');
        fd.append('nonce', july3DemandsNonce);
        fd.append('demand_id', demandId);
        fd.append('assembly', document.getElementById('ev-asm-' + demandId).value);
        fd.append('summary', document.getElementById('ev-sum-' + demandId).value);
        fd.append('doc_url', document.getElementById('ev-url-' + demandId).value);
        fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', { method: 'POST', body: fd })
            .then(function(r){ return r.json(); })
            .then(function(res){
                var msgEl = document.getElementById('ev-msg-' + demandId);
                msgEl.style.color = res.success ? '#4ade80' : '#fca5a5';
                msgEl.textContent = res.data ? res.data.msg : 'Saved!';
            });
    };
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode( 'demands_voting_deck', 'july3_mod4_render_voting_deck' );
// === END OF MODULE 4 ===