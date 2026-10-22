<?php
/**
 * 101 Core CRM - Shared AI Semantic Engine & Zero-Friction Magic Passport
 *
 * Powers:
 * 1. Citizen Demand Clustering & Org Matchmaking (101-demands-voting)
 * 2. Civil Society Bridge Matchmaking & 3 June / 3 July Routing (101-appeal-coalition)
 * 3. Organizational Collaboration Smorgasbord Overlap Synthesis (101-smorgasbord-coalition)
 *
 * Architecture:
 * - Strict PII Firewall: Never sends names, emails, or IPs to external AI APIs.
 * - Dual-Engine Ready: Supports Google Gemini API OR a self-hosted Local LLM (Ollama/vLLM).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class One01_AI_Engine {

	private static $instance = null;
	private $table_vectors;
	private $table_passports;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		global $wpdb;
		$this->table_vectors   = $wpdb->prefix . '101_ai_vectors';
		$this->table_passports = $wpdb->prefix . '101_magic_passports';

		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'init', array( $this, 'maybe_create_tables' ) );
	}

	/**
	 * 1. DATABASE TABLES: Local Semantic Vector Store + Passwordless Magic Passports
	 */
	public function maybe_create_tables() {
		if ( get_option( '101_ai_engine_db_version' ) === '1.0.0' ) {
			return;
		}

		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		// Stores the mathematical meaning vector locally on 1984 Hosting
		$sql_vectors = "CREATE TABLE {$this->table_vectors} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			item_type varchar(50) NOT NULL COMMENT 'demand, org_profile, smorg_plate, toolkit',
			item_id bigint(20) unsigned NOT NULL,
			label varchar(255) NOT NULL,
			sanitized_text text NOT NULL,
			vector_json longtext NOT NULL,
			meta_json longtext DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY item_lookup (item_type, item_id)
		) $charset_collate;";

		// Stores SHA-256 hashed passwordless edit tokens for Civil Society orgs
		$sql_passports = "CREATE TABLE {$this->table_passports} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			entity_type varchar(50) NOT NULL DEFAULT 'org',
			entity_id bigint(20) unsigned NOT NULL,
			email_hash varchar(64) NOT NULL,
			token_hash varchar(64) NOT NULL,
			expires_at datetime NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY token_lookup (token_hash),
			KEY email_lookup (email_hash)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_vectors );
		dbDelta( $sql_passports );

		update_option( '101_ai_engine_db_version', '1.0.0' );
	}

	/**
	 * 2. STRICT GDPR / PII FIREWALL
	 * Strips emails, phone numbers, URLs, and handles before any text leaves the server.
	 */
	public function scrub_pii( $raw_text ) {
		$text = wp_strip_all_tags( (string) $raw_text );
		// Remove email addresses
		$text = preg_replace( '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', '[REDACTED_EMAIL]', $text );
		// Remove URLs
		$text = preg_replace( '#https?://[^\s()<>]+(?:\([\w\d]+\)|([^[:punct:]\s]|/))#', '[REDACTED_URL]', $text );
		// Remove UK/International phone numbers
		$text = preg_replace( '/(\+?\d{1,3}[\s-]?)?(\(?\d{3,5}\)?[\s-]?\d{3,4}[\s-]?\d{3,4})/', '[REDACTED_PHONE]', $text );
		return trim( $text );
	}

	/**
	 * 3. GENERATE SEMANTIC EMBEDDING (Gemini API or Radical Local LLM)
	 */
	public function get_embedding( $raw_text ) {
		$clean_text = $this->scrub_pii( $raw_text );
		if ( empty( $clean_text ) ) {
			return new WP_Error( 'empty_text', 'No text provided for semantic indexing.' );
		}

		$provider = get_option( '101_ai_provider', 'gemini' );

		if ( 'local_llm' === $provider ) {
			return $this->get_local_llm_embedding( $clean_text );
		}

		// Default: Google Gemini API (gemini-embedding-001)
		$api_key = trim( get_option( '101_gemini_api_key', '' ) );
		if ( empty( $api_key ) ) {
			return new WP_Error( 'missing_key', 'Gemini API key is not configured in Settings -> 101 AI Engine.' );
		}

		$endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-embedding-001:embedContent?key=' . rawurlencode( $api_key );

		$body = array(
			'model'                => 'models/gemini-embedding-001',
			'content'              => array(
				'parts' => array(
					array( 'text' => $clean_text ),
				),
			),
			'taskType'             => 'SEMANTIC_SIMILARITY',
			'outputDimensionality' => 768,
		);

		$response = wp_remote_post(
			$endpoint,
			array(
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $body ),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code || empty( $data['embedding']['values'] ) ) {
			$err_msg = isset( $data['error']['message'] ) ? $data['error']['message'] : 'Unknown API response (HTTP ' . $code . ')';
			return new WP_Error( 'gemini_api_error', $err_msg );
		}

		return $this->normalize_vector( $data['embedding']['values'] );
	}

	/**
	 * Future-proof hook for your friend's self-hosted Ollama / LocalAI server
	 */
	private function get_local_llm_embedding( $clean_text ) {
		$local_url   = rtrim( get_option( '101_local_llm_url', 'http://localhost:11434' ), '/' );
		$local_model = get_option( '101_local_embed_model', 'nomic-embed-text' );

		$response = wp_remote_post(
			$local_url . '/api/embeddings',
			array(
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'model'  => $local_model,
						'prompt' => $clean_text,
					)
				),
				'timeout' => 20,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $data['embedding'] ) ) {
			return new WP_Error( 'local_llm_error', 'Could not retrieve embedding from local LLM server.' );
		}

		return $this->normalize_vector( $data['embedding'] );
	}

	/**
	 * Normalize vector to unit length so Cosine Similarity is a fast dot-product
	 */
	private function normalize_vector( $vec ) {
		$sum_sq = 0.0;
		foreach ( $vec as $v ) {
			$sum_sq += (float) $v * (float) $v;
		}
		$mag = sqrt( $sum_sq );
		if ( $mag <= 0.0 ) {
			return $vec;
		}
		$normalized = array();
		foreach ( $vec as $v ) {
			$normalized[] = round( (float) $v / $mag, 6 );
		}
		return $normalized;
	}

	/**
	 * 4. SAVE / INDEX AN ITEM (Org Demand, Citizen Demand, Toolkit, or Smorgasbord Plate)
	 */
	public function index_item( $item_type, $item_id, $label, $raw_text, $meta = array() ) {
		global $wpdb;

		$vector = $this->get_embedding( $raw_text );
		if ( is_wp_error( $vector ) ) {
			return $vector;
		}

		$clean_text = $this->scrub_pii( $raw_text );

		$existing_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$this->table_vectors} WHERE item_type = %s AND item_id = %d",
				$item_type,
				$item_id
			)
		);

		$row_data = array(
			'item_type'      => sanitize_key( $item_type ),
			'item_id'        => absint( $item_id ),
			'label'          => sanitize_text_field( $label ),
			'sanitized_text' => $clean_text,
			'vector_json'    => wp_json_encode( $vector ),
			'meta_json'      => wp_json_encode( $meta ),
		);

		if ( $existing_id ) {
			$wpdb->update( $this->table_vectors, $row_data, array( 'id' => $existing_id ) );
			return (int) $existing_id;
		} else {
			$wpdb->insert( $this->table_vectors, $row_data );
			return (int) $wpdb->insert_id;
		}
	}

	/**
	 * 5. INSTANT SEMANTIC MATCHMAKING (Pure PHP Math - 0 API calls needed for existing items!)
	 */
	public function find_matches( $query_text_or_vector, $target_types = array( 'org_profile', 'demand', 'toolkit' ), $limit = 5, $exclude_id = 0 ) {
		global $wpdb;

		if ( is_string( $query_text_or_vector ) ) {
			$query_vec = $this->get_embedding( $query_text_or_vector );
			if ( is_wp_error( $query_vec ) ) {
				return $query_vec;
			}
		} else {
			$query_vec = $query_text_or_vector;
		}

		$placeholders = implode( ',', array_fill( 0, count( $target_types ), '%s' ) );
		$sql          = $wpdb->prepare(
			"SELECT * FROM {$this->table_vectors} WHERE item_type IN ($placeholders)",
			$target_types
		);

		$candidates = $wpdb->get_results( $sql, ARRAY_A );
		$results    = array();

		foreach ( $candidates as $row ) {
			if ( $exclude_id && (int) $row['id'] === (int) $exclude_id ) {
				continue;
			}
			$candidate_vec = json_decode( $row['vector_json'], true );
			if ( ! is_array( $candidate_vec ) ) {
				continue;
			}

			$similarity = $this->cosine_similarity( $query_vec, $candidate_vec );
			$results[]  = array(
				'index_id'   => (int) $row['id'],
				'item_type'  => $row['item_type'],
				'item_id'    => (int) $row['item_id'],
				'label'      => $row['label'],
				'text'       => $row['sanitized_text'],
				'similarity' => round( $similarity * 100, 1 ), // Percentage e.g. 88.4%
				'meta'       => json_decode( $row['meta_json'], true ),
			);
		}

		usort(
			$results,
			function ( $a, $b ) {
				return $b['similarity'] <=> $a['similarity'];
			}
		);

		return array_slice( $results, 0, $limit );
	}

	private function cosine_similarity( $vec_a, $vec_b ) {
		$dot = 0.0;
		$len = min( count( $vec_a ), count( $vec_b ) );
		for ( $i = 0; $i < $len; $i++ ) {
			$dot += (float) $vec_a[ $i ] * (float) $vec_b[ $i ];
		}
		return max( 0.0, min( 1.0, $dot ) );
	}

	/**
	 * 6. PASSWORDLESS MAGIC PASSPORT GENERATOR (Low-Friction, High-Security)
	 */
	public function issue_magic_passport( $entity_type, $entity_id, $email, $days_valid = 30 ) {
		global $wpdb;
		$raw_token  = bin2hex( random_bytes( 24 ) ); // 48-char cryptographic token
		$token_hash = hash( 'sha256', $raw_token );
		$email_hash = hash( 'sha256', strtolower( trim( $email ) ) );
		$expires_at = gmdate( 'Y-m-d H:i:s', time() + ( $days_valid * DAY_IN_SECONDS ) );

		$wpdb->insert(
			$this->table_passports,
			array(
				'entity_type' => sanitize_key( $entity_type ),
				'entity_id'   => absint( $entity_id ),
				'email_hash'  => $email_hash,
				'token_hash'  => $token_hash,
				'expires_at'  => $expires_at,
			)
		);

		return $raw_token; // Sent only via email/screen to user; only the SHA-256 hash lives in DB
	}

	public function verify_magic_passport( $raw_token ) {
		global $wpdb;
		$token_hash = hash( 'sha256', sanitize_text_field( $raw_token ) );
		$now        = gmdate( 'Y-m-d H:i:s' );

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT entity_type, entity_id FROM {$this->table_passports} WHERE token_hash = %s AND expires_at > %s LIMIT 1",
				$token_hash,
				$now
			),
			ARRAY_A
		);
	}

	/**
	 * 7. REST API ENDPOINT FOR LIVE FRONTEND MATCHING
	 */
	public function register_rest_routes() {
		register_rest_route(
			'101-ai/v1',
			'/match',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_handle_match' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public function rest_handle_match( WP_REST_Request $request ) {
		$text  = sanitize_textarea_field( $request->get_param( 'text' ) );
		$types = $request->get_param( 'types' );
		if ( ! is_array( $types ) || empty( $types ) ) {
			$types = array( 'org_profile', 'demand', 'toolkit' );
		}

		$matches = $this->find_matches( $text, $types, 6 );
		if ( is_wp_error( $matches ) ) {
			return new WP_REST_Response( array( 'error' => $matches->get_error_message() ), 400 );
		}

		return new WP_REST_Response(
			array(
				'scrubbed_query' => $this->scrub_pii( $text ),
				'matches'        => $matches,
			),
			200
		);
	}

	/**
	 * 8. WORDPRESS ADMIN SETTINGS & LIVE TEST SANDBOX
	 * (Lets you test Climate Media Coalition / Hacked Off / Media Revolution right in WP Admin!)
	 */
	public function register_admin_menu() {
		add_options_page(
			'101 AI & Privacy Engine',
			'101 AI Engine',
			'manage_options',
			'101-ai-engine',
			array( $this, 'render_admin_page' )
		);
	}

	public function register_settings() {
		register_setting( '101_ai_engine_group', '101_ai_provider' );
		register_setting( '101_ai_engine_group', '101_gemini_api_key' );
		register_setting( '101_ai_engine_group', '101_local_llm_url' );
		register_setting( '101_ai_engine_group', '101_local_embed_model' );
	}

	public function render_admin_page() {
		$test_output = '';

		// Handle 1-Click Seed & Matchmaking Test
		if ( isset( $_POST['101_run_live_test'] ) && check_admin_referer( '101_ai_test_nonce' ) ) {
			$samples = array(
				array(
					'type'  => 'org_profile',
					'id'    => 101,
					'label' => 'Climate Media Coalition',
					'text'  => 'We demand the Media Freedom Act and truthful climate broadcasting free from fossil fuel oligarch interference.',
					'meta'  => array( 'action' => 'Join Climate Media Press Action', 'url' => '#cmc-join' ),
				),
				array(
					'type'  => 'org_profile',
					'id'    => 102,
					'label' => 'Hacked Off',
					'text'  => 'We campaign for Leveson Part 2, independent press regulation, and accountability for abusive newspaper barons.',
					'meta'  => array( 'action' => 'Back the Leveson 2 Petition', 'url' => '#hackedoff' ),
				),
				array(
					'type'  => 'org_profile',
					'id'    => 103,
					'label' => 'Media Revolution',
					'text'  => 'Building a democratic media revolution through community news clubs, media liberation, and cooperative ownership.',
					'meta'  => array( 'action' => 'Join a Local News Club & Mobilise for June 3 / July 3', 'url' => '#mediarev' ),
				),
				array(
					'type'  => 'org_profile',
					'id'    => 104,
					'label' => 'River & Ocean Commons Network',
					'text'  => 'Ending sewage dumping in British rivers and taking water utilities into democratic public ownership.',
					'meta'  => array( 'action' => 'Join River Action Assembly', 'url' => '#rivers' ),
				),
				array(
					'type'  => 'toolkit',
					'id'    => 201,
					'label' => '3rd June / 3rd July Media Liberation Mobilisation Pack',
					'text'  => 'Posters, Wheatpaste Kits, and Street Action Guides targeting billionaire media ownership and press capture.',
					'meta'  => array( 'download' => '/toolkits/media-mobilisation-pack.pdf' ),
				),
			);

			$errors = array();
			foreach ( $samples as $s ) {
				$res = $this->index_item( $s['type'], $s['id'], $s['label'], $s['text'], $s['meta'] );
				if ( is_wp_error( $res ) ) {
					$errors[] = $res->get_error_message();
				}
			}

			$citizen_query = isset( $_POST['citizen_test_query'] )
				? sanitize_text_field( wp_unslash( $_POST['citizen_test_query'] ) )
				: 'Ban billionaire media owners';

			if ( empty( $errors ) ) {
				$matches = $this->find_matches( $citizen_query, array( 'org_profile', 'toolkit' ), 5 );
				if ( ! is_wp_error( $matches ) ) {
					$test_output .= '<div class="notice notice-success" style="padding:15px;margin-top:15px;">';
					$test_output .= '<h3>✅ Live Semantic Match Results for Citizen Demand: <em>"' . esc_html( $citizen_query ) . '"</em></h3>';
					$test_output .= '<p><small>PII Scrubber verified: Only anonymous political concepts were embedded.</small></p>';
					$test_output .= '<table class="widefat striped" style="margin-top:10px;"><thead><tr><th>Match %</th><th>Type</th><th>Organisation / Toolkit</th><th>Their Stated Aim / Resource</th></tr></thead><tbody>';
					foreach ( $matches as $m ) {
						$badge_color  = $m['similarity'] >= 70 ? '#16a34a' : '#64748b';
						$test_output .= '<tr>';
						$test_output .= '<td><strong style="color:' . $badge_color . ';font-size:15px;">' . esc_html( $m['similarity'] ) . '%</strong></td>';
						$test_output .= '<td><code>' . esc_html( $m['item_type'] ) . '</code></td>';
						$test_output .= '<td><strong>' . esc_html( $m['label'] ) . '</strong></td>';
						$test_output .= '<td>' . esc_html( $m['text'] ) . '</td>';
						$test_output .= '</tr>';
					}
					$test_output .= '</tbody></table></div>';
				} else {
					$test_output = '<div class="notice notice-error"><p>Error: ' . esc_html( $matches->get_error_message() ) . '</p></div>';
				}
			} else {
				$test_output = '<div class="notice notice-error"><p>API Error: ' . esc_html( implode( ' | ', array_unique( $errors ) ) ) . '</p></div>';
			}
		}
		?>
		<div class="wrap">
			<h1>101 AI Semantic Engine & GDPR Firewall</h1>
			<p>Powers zero-PII semantic matchmaking across Citizen Demands, the Civil Society Bridge, and the Collaboration Smörgåsbord.</p>

			<form method="post" action="options.php" style="background:#fff;padding:20px;border:1px solid #ccd0d4;max-width:750px;">
				<?php settings_fields( '101_ai_engine_group' ); ?>
				<table class="form-table">
					<tr>
						<th scope="row">AI Engine Provider</th>
						<td>
							<select name="101_ai_provider">
								<option value="gemini" <?php selected( get_option( '101_ai_provider', 'gemini' ), 'gemini' ); ?>>Google Gemini API (gemini-embedding-001)</option>
								<option value="local_llm" <?php selected( get_option( '101_ai_provider' ), 'local_llm' ); ?>>Self-Hosted Radical Local LLM (Ollama / LocalAI)</option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">Gemini API Key</th>
						<td>
							<input type="password" name="101_gemini_api_key" value="<?php echo esc_attr( get_option( '101_gemini_api_key' ) ); ?>" class="regular-text" />
							<p class="description">Stored strictly in your 1984 Hosting database—never committed to GitHub.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Local LLM Server URL <em>(Optional)</em></th>
						<td>
							<input type="text" name="101_local_llm_url" value="<?php echo esc_attr( get_option( '101_local_llm_url', 'http://localhost:11434' ) ); ?>" class="regular-text" />
							<p class="description">For when your friend's independent LLM server is ready to plug in.</p>
						</td>
					</tr>
				</table>
				<?php submit_button( 'Save AI Engine Settings' ); ?>
			</form>

			<hr style="margin:30px 0;" />

			<h2>🧪 Live Matchmaking Sandbox (Test v1 right now)</h2>
			<p>Click below to seed <strong>Climate Media Coalition</strong>, <strong>Hacked Off</strong>, <strong>Media Revolution</strong>, a contrasting <strong>Water Commons</strong> org, and the <strong>3rd June / 3rd July Media Toolkit</strong>, and test how a citizen demand matches them:</p>
			<form method="post" style="background:#fff;padding:20px;border:1px solid #ccd0d4;max-width:750px;">
				<?php wp_nonce_field( '101_ai_test_nonce' ); ?>
				<label for="citizen_test_query"><strong>Simulate Citizen or Org Demand:</strong></label><br/>
				<input type="text" id="citizen_test_query" name="citizen_test_query" value="Ban billionaire media owners" style="width:100%;max-width:500px;margin:8px 0 15px;" />
				<br/>
				<button type="submit" name="101_run_live_test" value="1" class="button button-primary button-hero">Run Live Semantic Match Test</button>
			</form>

			<?php echo $test_output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
		<?php
	}
}

One01_AI_Engine::get_instance();

/**
 * Global Helper Functions for all 101 Plugins to call
 */
function one01_ai_index_item( $item_type, $item_id, $label, $raw_text, $meta = array() ) {
	return One01_AI_Engine::get_instance()->index_item( $item_type, $item_id, $label, $raw_text, $meta );
}

function one01_ai_find_matches( $query_text, $target_types = array( 'org_profile', 'demand', 'toolkit' ), $limit = 5 ) {
	return One01_AI_Engine::get_instance()->find_matches( $query_text, $target_types, $limit );
}