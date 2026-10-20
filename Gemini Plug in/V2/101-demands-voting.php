<?php
/**
 * Plugin Name: 101 Demands — Module 4: Demands Voting & Duplicate Matcher
 * Description: Powers [demands_voting_deck] with live duplicate search, new proposal moderation queue, and GDPR-safe SHA-256 voting via wp_101_votes.
 * Version:     1.0.1
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

function july3_mod4_handle_vote() {
    global $wpdb;
    $post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
    $vote    = isset( $_POST['vote'] ) ? sanitize_text_field( $_POST['vote'] ) : '';
    if ( ! $post_id || ! in_array( $vote, array( 'agree', 'disagree' ), true ) ) {
        wp_send_json_error( array( 'msg' => 'Invalid vote' ) );
    }

    $table = $wpdb->prefix . '101_votes';
    $hash  = july3_mod4_voter_hash();

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

function july3_mod4_render_voting_deck() {
    $demands = get_posts( array(
        'post_type'      => 'mobilization_demand',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'orderby'        => 'date',
        'order'          => 'ASC',
    ) );

    $items = array();
    foreach ( $demands as $d ) {
        $a      = (int) get_post_meta( $d->ID, '_demand_votes_agree', true );
        $dis    = (int) get_post_meta( $d->ID, '_demand_votes_disagree', true );
        $tot    = $a + $dis;
        $origin = get_post_meta( $d->ID, '_demand_origin', true );
        $body   = $d->post_excerpt ? $d->post_excerpt : $d->post_content;

        $items[] = array(
            'id'       => $d->ID,
            'title'    => $d->post_title,
            'content'  => wp_strip_all_tags( $body ),
            'origin'   => $origin ? $origin : 'House of the People Charter',
            'agree'    => $a,
            'disagree' => $dis,
            'total'    => $tot,
            'pct'      => $tot > 0 ? round( ( $a / $tot ) * 100 ) : 0,
        );
    }

    $palettes = array(
        array( 'bg' => '#1c130e', 'text' => '#ffffff', 'num' => '#443024', 'accent' => '#ea580c', 'sub' => '#d6d3d1' ),
        array( 'bg' => '#ffffff', 'text' => '#1c130e', 'num' => '#e7e0d6', 'accent' => '#1c130e', 'sub' => '#57534e' ),
        array( 'bg' => '#ea580c', 'text' => '#1c130e', 'num' => '#fb923c', 'accent' => '#1c130e', 'sub' => '#291d16' ),
    );

    ob_start();
    ?>
    <section id="demands" class="j3-container" style="padding:64px 24px;text-align:center;">
        <div style="color:var(--j3-orange);font-size:12px;font-weight:800;letter-spacing:1.5px;text-transform:uppercase;">DELIBERATIVE DEMOCRACY IN ACTION</div>
        <h2 class="j3-poster-title" style="font-size:50px;margin:6px 0 4px;">THE PEOPLE'S CHARTER &amp; 101 DEMANDS</h2>
        <div class="j3-brush" style="margin-bottom:28px;">Search existing proposals, vote, or submit a new demand from your assembly</div>

        <div style="max-width:860px;margin:0 auto 36px;background:var(--j3-espresso);color:#fff;border-radius:22px;padding:28px;box-shadow:0 20px 40px -15px rgba(28,19,14,0.3);text-align:left;">
            <label for="july3-demand-search" style="display:block;font-weight:700;font-size:16px;margin-bottom:10px;color:var(--j3-amber);">
                🔍 Type a topic or demand to filter matching proposals (e.g. "tax", "housing", "corruption", "nature"):
            </label>
            <input type="text" id="july3-demand-search" oninput="july3FilterDemands(this.value)" placeholder="Start typing to search existing demands or propose a new one..." style="width:100%;padding:14px 18px;font-size:15px;border:none;border-radius:12px;background:#fbf8f3;color:#1c130e;">
            <div id="july3-duplicate-matches" style="margin-top:14px;display:none;padding:12px 16px;background:rgba(255,255,255,0.08);border-radius:10px;font-size:14px;"></div>
            <div id="july3-propose-box" style="display:none;margin-top:16px;padding-top:16px;border-top:1px solid rgba(255,255,255,0.15);">
                <p style="margin:0 0 10px;font-weight:700;color:#fde68a;">✨ Submit a New Demand Proposal for Moderation:</p>
                <input type="text" id="july3-new-demand-title" placeholder="Headline of your demand" style="width:100%;padding:10px;border-radius:8px;border:none;margin-bottom:8px;">
                <input type="text" id="july3-new-demand-origin" placeholder="Proposed by (e.g., Totnes Town Hall / Citizen)" style="width:100%;padding:10px;border-radius:8px;border:none;margin-bottom:8px;">
                <button type="button" onclick="july3SubmitNewDemand()" class="j3-btn-orange">➕ Submit Proposal</button>
                <div id="july3-propose-msg" style="margin-top:8px;font-weight:700;color:#4ade80;"></div>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(330px,1fr));gap:24px;text-align:left;">
            <?php foreach ( $items as $idx => $item ) :
                $pal = $palettes[ $idx % 3 ];
                ?>
                <div class="july3-demand-card" data-search="<?php echo esc_attr( strtolower( $item['title'] . ' ' . $item['content'] ) ); ?>" style="background:<?php echo esc_attr( $pal['bg'] ); ?>;color:<?php echo esc_attr( $pal['text'] ); ?>;border-radius:22px;padding:30px;box-shadow:0 18px 38px -12px rgba(28,19,14,0.14);display:flex;flex-direction:column;justify-content:space-between;">
                    <div>
                        <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:10px;">
                            <span class="j3-poster-title" style="font-size:44px;color:<?php echo esc_attr( $pal['num'] ); ?>;"><?php echo esc_html( sprintf( '%02d', $idx + 1 ) ); ?></span>
                            <span style="font-size:11px;font-weight:800;text-transform:uppercase;opacity:0.9;">🏛️ <?php echo esc_html( $item['origin'] ); ?></span>
                        </div>
                        <h3 style="margin:0 0 12px;font-size:20px;font-weight:800;line-height:1.3;color:<?php echo esc_attr( $pal['accent'] ); ?>;text-decoration:underline;text-underline-offset:4px;">
                            <?php echo esc_html( preg_replace( '/^\d+\.\s*/', '', $item['title'] ) ); ?>
                        </h3>
                        <p style="font-size:14.5px;color:<?php echo esc_attr( $pal['sub'] ); ?>;line-height:1.6;margin:0;"><?php echo esc_html( $item['content'] ); ?></p>
                    </div>
                    <div style="margin-top:22px;padding-top:16px;border-top:1px solid rgba(128,128,128,0.25);">
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
                            <button type="button" onclick="this.closest('.july3-demand-card').style.opacity='0.3';" style="padding:10px 12px;background:rgba(0,0,0,0.18);color:inherit;border:none;border-radius:10px;font-weight:600;cursor:pointer;">Skip</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <script>
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
                mbox.innerHTML = '✅ Found <strong>' + matches + '</strong> matching demand(s) below! Vote on an existing card, or <a href="javascript:void(0)" onclick="document.getElementById(\'july3-propose-box\').style.display=\'block\'" style="color:#fde68a;text-decoration:underline;">propose a distinct demand</a>.';
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
                }
            });
    };
    window.july3SubmitNewDemand = function() {
        var fd = new FormData();
        fd.append('action', 'july3_propose_demand');
        fd.append('title', document.getElementById('july3-new-demand-title').value);
        fd.append('origin', document.getElementById('july3-new-demand-origin').value);
        fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', { method: 'POST', body: fd })
            .then(function(r){ return r.json(); })
            .then(function(res){
                document.getElementById('july3-propose-msg').textContent = res.data ? res.data.msg : 'Submitted!';
            });
    };
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode( 'demands_voting_deck', 'july3_mod4_render_voting_deck' );
// === END OF MODULE 4 ===