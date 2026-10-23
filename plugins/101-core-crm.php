<?php
/**
 * Plugin Name: 101 Demands — Module 1: Core Data & SQL Engine
 * Description: Registers Countries, Pillars, Assemblies, Coalition Orgs, Signatories, and 101 Demands. Includes [mobilization_vision_0307] and disables archive hijacking.
 * Version:     1.0.2
 * Author:      101 Demands Global Tech Collective
 * License:     GPL-3.0+
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', function() {

	register_taxonomy( 'mobilization_region', array( 'mobilization_hub', 'mobilization_org', 'mobilization_demand', 'mobilization_signer' ), array(
		'labels'            => array( 'name' => 'Countries & Regions', 'singular_name' => 'Country / Region' ),
		'public'            => true,
		'hierarchical'      => true,
		'show_admin_column' => true,
		'show_in_rest'      => true,
	) );

	register_taxonomy( 'mobilization_pillar', array( 'mobilization_demand', 'mobilization_org' ), array(
		'labels'            => array( 'name' => 'Pillars & Sectors', 'singular_name' => 'Pillar / Sector' ),
		'public'            => true,
		'hierarchical'      => true,
		'show_admin_column' => true,
		'show_in_rest'      => true,
	) );

	register_post_type( 'mobilization_hub', array(
		'labels'       => array( 'name' => 'Assemblies & Events', 'singular_name' => 'Assembly / Event' ),
		'public'       => true,
		'has_archive'  => false,
		'rewrite'      => array( 'slug' => 'assemblies-item' ),
		'menu_icon'    => 'dashicons-location-alt',
		'supports'     => array( 'title', 'editor', 'custom-fields' ),
		'show_in_rest' => true,
	) );

	register_post_type( 'mobilization_org', array(
		'labels'       => array( 'name' => 'Coalition Orgs', 'singular_name' => 'Coalition Organisation' ),
		'public'       => true,
		'has_archive'  => false,
		'rewrite'      => array( 'slug' => 'coalition-org' ),
		'menu_icon'    => 'dashicons-groups',
		'supports'     => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
		'show_in_rest' => true,
	) );

	register_post_type( 'mobilization_demand', array(
		'labels'       => array( 'name' => '101 Demands', 'singular_name' => 'Demand / Measure' ),
		'public'       => true,
		'has_archive'  => false,
		'rewrite'      => array( 'slug' => 'demand-item' ),
		'menu_icon'    => 'dashicons-megaphone',
		'supports'     => array( 'title', 'editor', 'excerpt', 'custom-fields' ),
		'show_in_rest' => true,
	) );

	register_post_type( 'mobilization_signer', array(
		'labels'       => array( 'name' => 'Appeal Signatories', 'singular_name' => 'Signatory' ),
		'public'       => false,
		'show_ui'      => true,
		'menu_icon'    => 'dashicons-edit-page',
		'supports'     => array( 'title', 'custom-fields' ),
	) );

	// Auto-flush rewrite rules once when upgrading to 1.0.2 so /demands/ works immediately!
	if ( get_option( '101_core_crm_version' ) !== '1.0.2' ) {
		flush_rewrite_rules();
		update_option( '101_core_crm_version', '1.0.2' );
	}
} );

register_activation_hook( __FILE__, function() {
	global $wpdb;
	$table_name      = $wpdb->prefix . '101_votes';
	$charset_collate = $wpdb->get_charset_collate();
	$sql = "CREATE TABLE IF NOT EXISTS $table_name (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		demand_id bigint(20) unsigned NOT NULL,
		voter_hash varchar(64) NOT NULL,
		vote_value varchar(16) NOT NULL,
		verification_tier varchar(20) DEFAULT 'guest',
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY  (id),
		UNIQUE KEY unique_demand_voter (demand_id, voter_hash),
		KEY demand_id (demand_id)
	) $charset_collate;";
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
	flush_rewrite_rules();
} );

/**
 * Shortcode: [mobilization_vision_0307]
 * Displays the 3rd July / 101 Demands Core Narrative, White Sheet of Paper symbol, and 8 Key Points.
 */
add_shortcode( 'mobilization_vision_0307', function() {
	ob_start();
	?>
	<section class="vision-0307-wrap" style="max-width:1100px;margin:40px auto;padding:20px;font-family:inherit;color:#f8fafc;">
		<!-- Top Intro Frame -->
		<div style="background:linear-gradient(135deg,#0f172a,#1e293b);border:1px solid #334155;border-radius:16px;padding:36px;margin-bottom:32px;box-shadow:0 10px 30px rgba(0,0,0,0.3);">
			<div style="display:inline-block;background:#f59e0b;color:#0f172a;font-weight:800;font-size:12px;letter-spacing:0.1em;text-transform:uppercase;padding:5px 12px;border-radius:99px;margin-bottom:16px;">
				20–21 March 2027 Assemblies &rarr; 3rd July 2027 Global Mobilisation
			</div>
			<h2 style="font-size:clamp(26px,4vw,40px);line-height:1.15;margin:0 0 18px;color:#ffffff;font-weight:900;">
				100 Demands for Daily Life. <span style="color:#f59e0b;">One 101st Demand for Real Power.</span>
			</h2>
			<p style="font-size:17px;line-height:1.7;color:#cbd5e1;margin:0 0 16px;">
				Right now, most of us are surviving in a rigged system—working harder for less, paying half our wages to landlords and energy giants, waiting weeks for a doctor, and watching billionaires buy up our media and our politicians while the living world burns. <strong>On 3rd July 2027</strong>, millions of us will step out together across our capital cities and hometowns to demand <strong>100 concrete fixes</strong> across five essentials of daily life: <strong>making ends meet, a liveable climate, thriving nature, technology &amp; AI that serves workers, and our fundamental rights.</strong>
			</p>
			<p style="font-size:17px;line-height:1.7;color:#cbd5e1;margin:0 0 16px;">
				Above all, we share <strong>one non-negotiable 101st Demand</strong>: take the power out of the hands of bought-and-paid-for career politicians, and give legally binding power to <strong>Citizens’ Assemblies</strong>—ordinary nurses, builders, carers, renters, and neighbours chosen by lottery, just like a jury. When ordinary people are in the driving seat, we get the world we actually need: warm affordable homes, clean rivers, honest media, a four-day week powered by technology rather than mass sackings, and communities where no one is left behind.
			</p>
			<p style="font-size:16px;line-height:1.65;color:#94a3b8;margin:0;">
				<strong>How we get there:</strong> Over the weekend of <strong>20–21st March 2027</strong>, thousands of local assemblies will feed into National Assemblies across the world to ratify our 101 Demands. Then, starting <strong>3rd July</strong>, we return to the streets day after day until we the people decide our common future.
			</p>
		</div>

		<!-- The Symbol: The White Sheet of Paper -->
		<div style="background:#ffffff;color:#0f172a;border-radius:16px;padding:32px;margin-bottom:32px;display:flex;flex-wrap:wrap;gap:24px;align-items:center;box-shadow:0 12px 32px rgba(0,0,0,0.25);">
			<div style="flex:1 1 220px;max-width:260px;background:#f8fafc;border:2px dashed #94a3b8;border-radius:12px;height:160px;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:16px;">
				<div style="width:90px;height:62px;background:#fff;border:2px solid #0f172a;box-shadow:0 6px 16px rgba(15,23,42,0.12);margin-bottom:10px;"></div>
				<span style="font-size:11px;font-weight:800;letter-spacing:0.08em;text-transform:uppercase;color:#475569;">Held Horizontally in Silence</span>
			</div>
			<div style="flex:2 1 420px;">
				<div style="font-size:12px;font-weight:800;letter-spacing:0.1em;text-transform:uppercase;color:#64748b;margin-bottom:6px;">Our Universal Symbol</div>
				<h3 style="font-size:26px;font-weight:900;margin:0 0 12px;color:#0f172a;">The Blank White Sheet of Paper</h3>
				<p style="font-size:15.5px;line-height:1.65;color:#334155;margin:0 0 10px;">
					Held up horizontally and in silence, the white sheet of paper says everything that needs to be said: <strong>We stand for a clean slate and a new political beginning.</strong> In a world that gets ever faster, we slow down. In a world that gets ever more shallow, we deepen. In a world that gets more violent, we embody calm, unbreakable resolve.
				</p>
				<p style="font-size:14.5px;line-height:1.6;color:#475569;margin:0;">
					We hold it up in mass rallies and vigils; we use it in assemblies to signal approval; we put it in windows, flypost it, and use it to write our demands. Everyone, everywhere, already has one at home.
				</p>
			</div>
		</div>

		<!-- The 8 Key Points Grid -->
		<h3 style="font-size:24px;font-weight:900;margin:0 0 18px;color:#ffffff;">How We Win — And Win the Day After (The 8 Key Points)</h3>
		<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;">
			<?php
			$points = array(
				array( '1. A Shared Starting Date', '3rd July 2027 is set for mass demonstrations across our capital cities, big cities, and hometowns.' ),
				array( '2. Sustained & Regenerative Presence', 'One-day marches are ignored, so we stay day after day. For every person in the capital, ten more back them up locally with mutual aid, solidarity kitchens, childcare, hardship funds, and town-square vigils.' ),
				array( '3. The 101st Demand: Citizens’ Assemblies', '100 demands come from local communities; the 101st Demand ensures they are decided not by bought politicians, but by National Citizens’ Assemblies chosen by lottery like a jury.' ),
				array( '4. Built for the Whole Community (3.5%)', 'Winning requires mass participation across society. Our spaces remain strictly nonviolent—welcoming workers, parents, young people, elders, and families with joy, music, and trained de-escalation teams.' ),
				array( '5. A Genuine Social Coalition', 'No single campaign can win alone while the whole system is captured. The 100 Demands connect movements across climate, poverty, nature, AI, and rights so we work in the overlaps without losing our identities.' ),
				array( '6. Accountable, Time-Limited Coordination', 'On 20–21st March 2027, National Assemblies ratify the 101 Demands and democratically mandate temporary, recallable coordination teams with strict end dates until sortition assemblies take over.' ),
				array( '7. International Cascade', 'We only have one world. When people see assemblies and sustained action rising across borders simultaneously, millions step forward knowing: this is what we have been waiting for.' ),
				array( '8. We Are the New Beginning', 'We combine strategic plans with deep care, silence with collective uproar, and concrete preparation for the day after—building a democratic era that belongs to the people who live in it.' ),
			);
			foreach ( $points as $pt ) :
				?>
				<div style="background:#1e293b;border:1px solid #334155;border-radius:12px;padding:20px;">
					<h4 style="font-size:16px;font-weight:800;color:#f59e0b;margin:0 0 8px;"><?php echo esc_html( $pt[0] ); ?></h4>
					<p style="font-size:14px;line-height:1.6;color:#cbd5e1;margin:0;"><?php echo esc_html( $pt[1] ); ?></p>
				</div>
			<?php endforeach; ?>
		</div>
	</section>
	<?php
	return ob_get_clean();
} );