<?php
/**
 * Plugin Name: 101 Demands — Module 6: Mobilization Registration Form
 * Description: Public "register your mobilization" form. Renders [mobilization_registration_form] (alias [recenser_mobilisation]), geocodes the postal code via OpenStreetMap/Nominatim, and files a pending mobilization_hub post for team review before it appears on [mobilization_map].
 * Version:     1.0.0
 * Author:      101 Demands Global Tech Collective
 * Text Domain: 101-mobilization-form
 * Domain Path: /languages
 * License:     GPL-3.0+
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'ONE01_MOBFORM_VERSION', '1.0.0' );
define( 'ONE01_MOBFORM_NONCE', 'one01_mobform_nonce' );

/**
 * Load plugin textdomain for translations.
 */
add_action( 'init', function () {
    load_plugin_textdomain( '101-mobilization-form', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    $locale = determine_locale();
    $mofile = plugin_dir_path( __FILE__ ) . 'languages/101-mobilization-form-' . $locale . '.mo';
    if ( file_exists( $mofile ) ) {
        load_textdomain( '101-mobilization-form', $mofile );
    }
} );

/**
 * Default form configuration. Filterable so each site / campaign can retune it.
 *
 * @return array
 */
function one01_mobform_defaults() {
    return apply_filters( 'one01_mobform_defaults', array(
        'event_date' => '2026-11-14',          // Default date prefilled in the picker (Y-m-d).
        'event_time' => '14:00',               // Default time prefilled in the picker (H:i).
        'country'    => one01_mobform_site_country(), // ISO code or name used to bias the geocoder.
        'hub_type'   => 'confirmed',           // Marker type once published (green by default).
        'throttle'   => 30,                    // Seconds a visitor must wait between submissions.
    ) );
}

/**
 * Country used to bias geocoding for the current site.
 *
 * On a multisite network each subsite resolves its own country from its locale
 * (en_GB -> GB, de_DE -> DE, fr_FR -> FR), so no per-site setup is required.
 * Override per site with the "one01_mobform_country" option, or globally with the
 * "one01_mobform_country" filter. Accepts an ISO 3166-1 alpha-2 code or a country name.
 *
 * @return string
 */
function one01_mobform_site_country() {
    $country = get_option( 'one01_mobform_country' );
    if ( ! $country ) {
        $locale = determine_locale();
        if ( preg_match( '/^[a-z]{2,3}[_-]([A-Za-z]{2})/', $locale, $m ) ) {
            $country = strtoupper( $m[1] );
        }
    }
    return trim( (string) apply_filters( 'one01_mobform_country', $country ) );
}

/**
 * Transient key used to cache a geocoding result.
 *
 * @param string $postal  Postal code.
 * @param string $city    Town/city.
 * @param string $country Country ISO code or name.
 * @return string
 */
function one01_mobform_geocode_cache_key( $postal, $city, $country ) {
    return 'one01_mobform_geo_' . md5( strtolower( $postal . '|' . $city . '|' . $country ) );
}

/**
 * Geocode a postal code + city through OpenStreetMap/Nominatim.
 *
 * Results (including misses) are cached in a transient to respect the
 * Nominatim usage policy of at most one request per second.
 *
 * @param string $postal  Postal code.
 * @param string $city    City name.
 * @param string $country Country ISO code or name used to bias the lookup.
 * @return array|WP_Error Array with lat/lng/label on success, WP_Error otherwise.
 */
function one01_mobform_geocode( $postal, $city, $country ) {
    if ( ! apply_filters( 'one01_mobform_geocode_enabled', true, $postal, $city, $country ) ) {
        return new WP_Error( 'disabled', __( 'Geocoding is disabled.', '101-mobilization-form' ) );
    }

    $cache_key = one01_mobform_geocode_cache_key( $postal, $city, $country );
    $cached    = get_transient( $cache_key );
    if ( is_array( $cached ) ) {
        return $cached;
    }
    if ( 'not-found' === $cached ) {
        return new WP_Error( 'not_found', __( 'Could not locate that postal code.', '101-mobilization-form' ) );
    }

    $endpoint = 'https://nominatim.openstreetmap.org/search';
    $email    = sanitize_email( get_option( 'admin_email' ) );
    $ua       = apply_filters(
        'one01_mobform_geocode_user_agent',
        'MobilizationRegistrationForm/' . ONE01_MOBFORM_VERSION . ' (' . home_url( '/' ) . ( $email ? '; ' . $email : '' ) . ')'
    );

    $args = array(
        'timeout' => 8,
        'headers' => array(
            'User-Agent'      => $ua,
            'Accept-Language' => substr( determine_locale(), 0, 2 ) ?: 'en',
        ),
    );

    $base_args = array(
        'format'         => 'jsonv2',
        'limit'          => 1,
        'addressdetails' => 0,
    );
    if ( $email ) {
        $base_args['email'] = $email;
    }

    // Bias the lookup by country: a 2-letter value is treated as an ISO code
    // (Nominatim "countrycodes"), anything else as a country name ("country").
    $country_args = array();
    $country_name = '';
    if ( preg_match( '/^[A-Za-z]{2}$/', $country ) ) {
        $country_args['countrycodes'] = strtolower( $country );
    } elseif ( '' !== $country ) {
        $country_args['country'] = $country;
        $country_name            = $country;
    }

    // Resolve the point from the postcode first; only fall back to a free-text
    // query that also includes the town when the postcode cannot be located.
    $queries = array(
        array_merge( $base_args, $country_args, array( 'postalcode' => $postal ) ),
        array_merge( $base_args, $country_args, array( 'q' => trim( $postal . ' ' . $city . ( $country_name ? ', ' . $country_name : '' ) ) ) ),
    );

    foreach ( $queries as $query ) {
        $response = wp_remote_get( add_query_arg( $query, $endpoint ), $args );
        if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
            continue;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! empty( $data[0]['lat'] ) && ! empty( $data[0]['lon'] ) ) {
            $coords = array(
                'lat'   => (float) $data[0]['lat'],
                'lng'   => (float) $data[0]['lon'],
                'label' => isset( $data[0]['display_name'] ) ? sanitize_text_field( $data[0]['display_name'] ) : '',
            );
            set_transient( $cache_key, $coords, 30 * DAY_IN_SECONDS );
            return $coords;
        }
    }

    set_transient( $cache_key, 'not-found', HOUR_IN_SECONDS );
    return new WP_Error( 'not_found', __( 'Could not locate that postal code.', '101-mobilization-form' ) );
}

/**
 * Handle the public form submission.
 *
 * Files a pending mobilization_hub post (held for moderation, exactly like
 * Coalition Orgs) and stores the private contact details as post meta.
 */
function one01_mobform_handle_submit() {
    // Honeypot: real users never fill this hidden field.
    if ( ! empty( $_POST['website_hp'] ) ) {
        wp_send_json_error( array( 'msg' => __( 'Spam detected.', '101-mobilization-form' ) ) );
    }

    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), ONE01_MOBFORM_NONCE ) ) {
        wp_send_json_error( array( 'msg' => __( 'Security check failed. Please refresh the page and try again.', '101-mobilization-form' ) ) );
    }

    $defaults = one01_mobform_defaults();

    // Light IP throttle to slow down scripted submissions.
    $ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
    $key = 'one01_mobform_throttle_' . md5( $ip );
    if ( $ip && get_transient( $key ) ) {
        wp_send_json_error( array( 'msg' => __( 'You just submitted the form. Please wait a moment and try again.', '101-mobilization-form' ) ) );
    }

    $email       = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
    $phone       = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
    $group_url   = isset( $_POST['group_url'] ) ? esc_url_raw( wp_unslash( $_POST['group_url'] ) ) : '';
    $city        = isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : '';
    $postal_code = isset( $_POST['postal_code'] ) ? sanitize_text_field( wp_unslash( $_POST['postal_code'] ) ) : '';
    $meetup      = isset( $_POST['meetup'] ) ? sanitize_text_field( wp_unslash( $_POST['meetup'] ) ) : '';
    $start_time  = isset( $_POST['start_time'] ) ? sanitize_text_field( wp_unslash( $_POST['start_time'] ) ) : '';
    $asm_time    = isset( $_POST['assembly_time'] ) ? sanitize_text_field( wp_unslash( $_POST['assembly_time'] ) ) : '';
    $asm_place   = isset( $_POST['assembly_place'] ) ? sanitize_text_field( wp_unslash( $_POST['assembly_place'] ) ) : '';
    $country     = isset( $_POST['country'] ) ? sanitize_text_field( wp_unslash( $_POST['country'] ) ) : '';
    if ( '' === $country ) {
        $country = $defaults['country'];
    }
    $consent     = ! empty( $_POST['gdpr_consent'] );

    // Required fields.
    if ( ! is_email( $email ) ) {
        wp_send_json_error( array( 'msg' => __( 'Please enter a valid email address.', '101-mobilization-form' ) ) );
    }
    if ( '' === $city ) {
        wp_send_json_error( array( 'msg' => __( 'Please enter your town/city.', '101-mobilization-form' ) ) );
    }
    if ( ! preg_match( '/^[A-Za-z0-9][A-Za-z0-9\- ]{1,11}$/', $postal_code ) ) {
        wp_send_json_error( array( 'msg' => __( 'Please enter a valid postal code.', '101-mobilization-form' ) ) );
    }
    if ( '' === $meetup ) {
        wp_send_json_error( array( 'msg' => __( 'Please give the meeting place.', '101-mobilization-form' ) ) );
    }
    // The datetime-local picker submits "YYYY-MM-DDTHH:MM"; store it as "YYYY-MM-DD HH:MM".
    $start_time = str_replace( 'T', ' ', $start_time );
    if ( ! preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $start_time ) ) {
        wp_send_json_error( array( 'msg' => __( 'Please choose a valid start date and time.', '101-mobilization-form' ) ) );
    }
    if ( ! $consent ) {
        wp_send_json_error( array( 'msg' => __( 'Please tick the confirmation box to submit.', '101-mobilization-form' ) ) );
    }

    if ( $ip ) {
        set_transient( $key, 1, max( 1, (int) $defaults['throttle'] ) );
    }

    // Resolve the map point from the postal code (town/country as fallback).
    $coords = one01_mobform_geocode( $postal_code, $city, $country );
    $geo_ok = ! is_wp_error( $coords );

    $post_id = wp_insert_post( array(
        'post_type'    => 'mobilization_hub',
        'post_title'   => $city,
        'post_status'  => 'pending', // Held for moderation, like Coalition Orgs.
        'post_content' => $meetup . ' — ' . $start_time,
    ), true );

    if ( is_wp_error( $post_id ) ) {
        wp_send_json_error( array( 'msg' => __( 'Something went wrong while saving. Please try again.', '101-mobilization-form' ) ) );
    }

    // Public map fields (consumed by [mobilization_map]).
    update_post_meta( $post_id, '_hub_type', in_array( $defaults['hub_type'], array( 'prep', 'confirmed', 'capital', 'event' ), true ) ? $defaults['hub_type'] : 'confirmed' );
    update_post_meta( $post_id, '_hub_meetup', $meetup );
    update_post_meta( $post_id, '_hub_next_date', $start_time );
    if ( $group_url ) {
        update_post_meta( $post_id, '_hub_chat_url', $group_url );
    }
    if ( $geo_ok ) {
        update_post_meta( $post_id, '_hub_lat', $coords['lat'] );
        update_post_meta( $post_id, '_hub_lng', $coords['lng'] );
    }

    // Private contact & submission details (never rendered publicly).
    update_post_meta( $post_id, '_entry_email', $email );
    update_post_meta( $post_id, '_entry_phone', $phone );
    update_post_meta( $post_id, '_entry_group_url', $group_url );
    update_post_meta( $post_id, '_entry_city', $city );
    update_post_meta( $post_id, '_entry_postal_code', $postal_code );
    update_post_meta( $post_id, '_entry_country', $country );
    update_post_meta( $post_id, '_entry_start_time', $start_time );
    update_post_meta( $post_id, '_entry_assembly_time', $asm_time );
    update_post_meta( $post_id, '_entry_assembly_place', $asm_place );
    update_post_meta( $post_id, '_entry_consent_timestamp', current_time( 'mysql' ) );
    update_post_meta( $post_id, '_entry_geocode_status', $geo_ok ? 'ok' : 'failed' );
    update_post_meta( $post_id, '_entry_geocode_label', $geo_ok ? $coords['label'] : '' );

    /**
     * Fires after a mobilization entry has been filed.
     *
     * @param int   $post_id The pending mobilization_hub post ID.
     * @param array $entry   The submitted values.
     */
    do_action( 'one01_mobform_after_submit', $post_id, compact( 'email', 'phone', 'group_url', 'city', 'postal_code', 'meetup', 'start_time', 'asm_time', 'asm_place' ) );

    // Optional acknowledgement email to the submitter.
    if ( apply_filters( 'one01_mobform_send_ack', true, $post_id, $email ) ) {
        one01_mobform_send_acknowledgement( $post_id, $email, $city, $meetup, $start_time );
    }

    $msg = $geo_ok
        ? __( 'A confirmation email is on its way. Your point will appear on the map after a quick review by the team. If anything needs correcting, simply reply to that email.', '101-mobilization-form' )
        : __( 'A confirmation email is on its way. The team will place your point on the map manually because we could not locate the address automatically.', '101-mobilization-form' );

    /**
     * Filter the success message returned to the browser after a submission.
     *
     * @param string $msg     Success message.
     * @param int    $post_id Pending hub post ID.
     * @param bool   $geo_ok  Whether the address was geocoded automatically.
     */
    $msg = apply_filters( 'one01_mobform_success_message', $msg, $post_id, $geo_ok );

    wp_send_json_success( array(
        'msg'     => $msg,
        'post_id' => $post_id,
    ) );
}
add_action( 'wp_ajax_one01_submit_mobilization', 'one01_mobform_handle_submit' );
add_action( 'wp_ajax_nopriv_one01_submit_mobilization', 'one01_mobform_handle_submit' );

/**
 * Send the acknowledgement email to the person who registered the mobilization.
 *
 * @param int    $post_id    Pending hub post ID.
 * @param string $email      Recipient email.
 * @param string $city       City.
 * @param string $meetup     Meeting place.
 * @param string $start_time Start time.
 */
function one01_mobform_send_acknowledgement( $post_id, $email, $city, $meetup, $start_time ) {
    $subject = apply_filters(
        'one01_mobform_ack_subject',
        sprintf( /* translators: %s: site name. */ __( 'Your mobilization is registered — %s', '101-mobilization-form' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ),
        $post_id
    );

    $body = sprintf(
        /* translators: 1: town/city, 2: meeting place, 3: start time and date. */
        __( "Hello,\n\nThank you for registering your mobilization.\n\nTown/City: %1\$s\nMeeting place: %2\$s\nStart time & date: %3\$s\n\nThe team will take a quick look, after which your point will appear on the map. If anything needs correcting, simply reply to this email.\n\n— %4\$s", '101-mobilization-form' ),
        $city,
        $meetup,
        $start_time,
        wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
    );

    $body = apply_filters( 'one01_mobform_ack_body', $body, $post_id );

    wp_mail( $email, $subject, $body );
}

/**
 * Register the private meta keys for REST / Gutenberg compatibility.
 */
add_action( 'init', function () {
    $meta_keys = array(
        '_entry_email',
        '_entry_phone',
        '_entry_group_url',
        '_entry_city',
        '_entry_postal_code',
        '_entry_country',
        '_entry_start_time',
        '_entry_assembly_time',
        '_entry_assembly_place',
        '_entry_consent_timestamp',
        '_entry_geocode_status',
        '_entry_geocode_label',
    );

    foreach ( $meta_keys as $meta_key ) {
        register_post_meta( 'mobilization_hub', $meta_key, array(
            'show_in_rest'      => true,
            'single'            => true,
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback'     => function () {
                return current_user_can( 'edit_posts' );
            },
        ) );
    }
} );

/**
 * Build the post-submit "thank you" panel (hidden until the form succeeds).
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function one01_mobform_success_panel( $atts ) {
    ob_start();
    ?>
    <div class="omf-success" hidden>
        <div class="omf-success-check" aria-hidden="true">✓</div>
        <h3 class="omf-success-title"><?php echo esc_html( $atts['success_heading'] ); ?></h3>
        <p class="omf-success-text"><?php echo esc_html__( 'A confirmation email is on its way. Your point will appear on the map after a quick review by the team. If anything needs correcting, simply reply to that email.', '101-mobilization-form' ); ?></p>
        <?php if ( ! empty( $atts['success_cta_url'] ) ) : ?>
            <a class="omf-success-cta" href="<?php echo esc_url( $atts['success_cta_url'] ); ?>"><?php echo esc_html( $atts['success_cta_text'] ); ?></a>
        <?php endif; ?>
    </div>
    <?php
    /**
     * Filter the rendered "thank you" panel so it can be fully customised.
     *
     * @param string $html Panel HTML.
     * @param array  $atts Shortcode attributes.
     */
    return apply_filters( 'one01_mobform_success_html', ob_get_clean(), $atts );
}

/**
 * Render the registration form.
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function one01_mobform_render_form( $atts ) {
    static $instance = 0;
    $instance++;

    $defaults = one01_mobform_defaults();
    $atts     = shortcode_atts( array(
        'date'             => $defaults['event_date'],
        'time'             => $defaults['event_time'],
        'country'          => $defaults['country'],
        'heading'          => __( 'Your mobilization on the map', '101-mobilization-form' ),
        'confetti'         => 'yes',
        'success_url'      => '',
        'success_heading'  => __( 'Thank you, your mobilization is registered!', '101-mobilization-form' ),
        'success_cta_url'  => home_url( '/assemblies/' ),
        'success_cta_text' => __( 'View the map', '101-mobilization-form' ),
    ), $atts, 'mobilization_registration_form' );

    // Prefill the date picker only when both a valid date and time are supplied.
    $default_datetime = '';
    if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $atts['date'] ) && preg_match( '/^\d{2}:\d{2}$/', $atts['time'] ) ) {
        $default_datetime = $atts['date'] . 'T' . $atts['time'];
    }

    // Confetti burst on success (js-confetti). Disable with confetti="no".
    $confetti_enabled = filter_var( $atts['confetti'], FILTER_VALIDATE_BOOLEAN );
    $confetti_url     = $confetti_enabled
        ? apply_filters( 'one01_mobform_confetti_url', 'https://cdn.jsdelivr.net/npm/js-confetti@0.13.1/dist/js-confetti.browser.js' )
        : '';
    $confetti_options = apply_filters( 'one01_mobform_confetti_options', array(
        'confettiNumber' => 220,
        'confettiColors' => array( '#000000', '#56666B', '#A8AF4A', '#C6E41B', '#FAF829' ),
    ) );

    $form_id  = 'one01-mobform-' . $instance;
    $nonce    = wp_create_nonce( ONE01_MOBFORM_NONCE );
    $ajax_url = admin_url( 'admin-ajax.php' );

    one01_mobform_print_styles();

    ob_start();
    ?>
    <section class="one01-mobform" id="<?php echo esc_attr( $form_id ); ?>" data-success-url="<?php echo esc_url( $atts['success_url'] ); ?>" data-confetti-url="<?php echo esc_url( $confetti_url ); ?>" data-confetti-options="<?php echo esc_attr( wp_json_encode( $confetti_options ) ); ?>">
        <div class="omf-card">
            <div class="omf-eyebrow"><?php esc_html_e( 'Register my mobilization', '101-mobilization-form' ); ?></div>
            <h2 class="omf-title"><?php echo esc_html( $atts['heading'] ); ?></h2>
            <p class="omf-lead"><?php esc_html_e( 'Organising a march, a gathering or an assembly? Give the place, the day and the time: your point will appear on the map, and those looking for it near them will find you.', '101-mobilization-form' ); ?></p>
            <ul class="omf-notes">
                <li><?php esc_html_e( 'Your contact details are never published: only the city, the meeting place and the time appear on the map.', '101-mobilization-form' ); ?></li>
                <li><?php esc_html_e( 'A confirmation email is sent to you.', '101-mobilization-form' ); ?></li>
                <li><?php esc_html_e( 'After a quick review by the team, your point appears on the map.', '101-mobilization-form' ); ?></li>
            </ul>

            <form class="omf-form" onsubmit="return one01MobformSubmit(event, '<?php echo esc_js( $form_id ); ?>');">
                <input type="hidden" name="nonce" value="<?php echo esc_attr( $nonce ); ?>">
                <input type="hidden" name="country" value="<?php echo esc_attr( $atts['country'] ); ?>">
                <div class="omf-hp" aria-hidden="true">
                    <input type="text" name="website_hp" tabindex="-1" autocomplete="off">
                </div>

                <fieldset class="omf-fieldset">
                    <legend class="omf-legend"><?php esc_html_e( 'Contacting you', '101-mobilization-form' ); ?></legend>

                    <div class="omf-field">
                        <label for="<?php echo esc_attr( $form_id ); ?>-email"><?php esc_html_e( 'Your email', '101-mobilization-form' ); ?> <span class="omf-req">*</span></label>
                        <input type="email" id="<?php echo esc_attr( $form_id ); ?>-email" name="email" required autocomplete="email">
                        <small><?php esc_html_e( 'Never published. This is how we will reach you if information is missing.', '101-mobilization-form' ); ?></small>
                    </div>

                    <div class="omf-field">
                        <label for="<?php echo esc_attr( $form_id ); ?>-phone"><?php esc_html_e( 'Phone', '101-mobilization-form' ); ?></label>
                        <input type="tel" id="<?php echo esc_attr( $form_id ); ?>-phone" name="phone" autocomplete="tel">
                        <small><?php esc_html_e( 'Never published either. It is used on the day, when a call is faster than an email.', '101-mobilization-form' ); ?></small>
                    </div>

                    <div class="omf-field">
                        <label for="<?php echo esc_attr( $form_id ); ?>-group"><?php esc_html_e( 'Link to your local group (optional)', '101-mobilization-form' ); ?></label>
                        <input type="url" id="<?php echo esc_attr( $form_id ); ?>-group" name="group_url" placeholder="https://" autocomplete="url">
                        <small><?php esc_html_e( 'WhatsApp, Telegram, Signal, Matrix… the invite link to your discussion group, if you already have one.', '101-mobilization-form' ); ?></small>
                    </div>
                </fieldset>

                <fieldset class="omf-fieldset">
                    <legend class="omf-legend"><?php esc_html_e( 'Where and when', '101-mobilization-form' ); ?></legend>

                    <div class="omf-grid">
                        <div class="omf-field">
                            <label for="<?php echo esc_attr( $form_id ); ?>-city"><?php esc_html_e( 'Town/City', '101-mobilization-form' ); ?> <span class="omf-req">*</span></label>
                            <input type="text" id="<?php echo esc_attr( $form_id ); ?>-city" name="city" required autocomplete="address-level2">
                        </div>
                        <div class="omf-field">
                            <label for="<?php echo esc_attr( $form_id ); ?>-postal"><?php esc_html_e( 'Postal code', '101-mobilization-form' ); ?> <span class="omf-req">*</span></label>
                            <input type="text" id="<?php echo esc_attr( $form_id ); ?>-postal" name="postal_code" required inputmode="numeric" autocomplete="postal-code">
                            <small><?php esc_html_e( 'Five digits: this is what places the point on the map.', '101-mobilization-form' ); ?></small>
                        </div>
                    </div>

                    <div class="omf-field">
                        <label for="<?php echo esc_attr( $form_id ); ?>-meetup"><?php esc_html_e( 'Meeting place', '101-mobilization-form' ); ?> <span class="omf-req">*</span></label>
                        <input type="text" id="<?php echo esc_attr( $form_id ); ?>-meetup" name="meetup" required>
                        <small><?php esc_html_e( 'A precise point, not just the city name: a square, a forecourt, a station, a monument. This is what lets someone who does not know you join you.', '101-mobilization-form' ); ?></small>
                    </div>

                    <div class="omf-field">
                        <label for="<?php echo esc_attr( $form_id ); ?>-start"><?php esc_html_e( 'Start time & date', '101-mobilization-form' ); ?> <span class="omf-req">*</span></label>
                        <input type="datetime-local" id="<?php echo esc_attr( $form_id ); ?>-start" name="start_time" required value="<?php echo esc_attr( $default_datetime ); ?>">
                        <small><?php esc_html_e( 'Pick the date and time of your event.', '101-mobilization-form' ); ?></small>
                    </div>
                </fieldset>

                <fieldset class="omf-fieldset">
                    <legend class="omf-legend"><?php esc_html_e( 'The assembly, if you are holding one (optional)', '101-mobilization-form' ); ?></legend>
                    <p class="omf-hint"><?php esc_html_e( 'The assembly that decides what comes next, after the march. Leave these two fields empty while nothing is decided: better to announce nothing than to announce wrongly.', '101-mobilization-form' ); ?></p>

                    <div class="omf-grid">
                        <div class="omf-field">
                            <label for="<?php echo esc_attr( $form_id ); ?>-asm-time"><?php esc_html_e( 'Assembly time', '101-mobilization-form' ); ?></label>
                            <input type="text" id="<?php echo esc_attr( $form_id ); ?>-asm-time" name="assembly_time">
                        </div>
                        <div class="omf-field">
                            <label for="<?php echo esc_attr( $form_id ); ?>-asm-place"><?php esc_html_e( 'Assembly place', '101-mobilization-form' ); ?></label>
                            <input type="text" id="<?php echo esc_attr( $form_id ); ?>-asm-place" name="assembly_place">
                        </div>
                    </div>
                </fieldset>

                <label class="omf-consent">
                    <input type="checkbox" name="gdpr_consent" value="yes" required>
                    <span><?php esc_html_e( 'I agree to be contacted again about this mobilization, and for the city, the meeting place and the time to be published on the map.', '101-mobilization-form' ); ?></span>
                </label>

                <button type="submit" class="omf-submit"><?php esc_html_e( 'Register my mobilization', '101-mobilization-form' ); ?></button>
                <div class="omf-feedback" role="status" aria-live="polite" hidden></div>
            </form>

            <?php echo one01_mobform_success_panel( $atts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>
    </section>
    <script>
    if (typeof window.one01MobformSubmit !== 'function') {
        window.one01MobformSubmit = function (e, formId) {
            e.preventDefault();
            var section = document.getElementById(formId);
            if (!section) { return false; }
            var form = section.querySelector('.omf-form');
            var feedback = section.querySelector('.omf-feedback');
            var button = section.querySelector('.omf-submit');

            function fireConfetti() {
                var url = section.getAttribute('data-confetti-url');
                if (!url) { return; }
                if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) { return; }
                var opts = {};
                try { opts = JSON.parse(section.getAttribute('data-confetti-options') || '{}'); } catch (err) {}
                function run() {
                    try { new window.JSConfetti().addConfetti(opts); } catch (err) {}
                }
                if (window.JSConfetti) { run(); return; }
                var s = document.createElement('script');
                s.src = url;
                s.async = true;
                s.onload = run;
                document.head.appendChild(s);
            }

            var fd = new FormData(form);
            fd.append('action', 'one01_submit_mobilization');

            button.disabled = true;
            feedback.hidden = true;

            fetch(<?php echo wp_json_encode( $ajax_url ); ?>, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res && res.success) {
                        var redirect = section.getAttribute('data-success-url');
                        if (redirect) {
                            window.location.href = redirect;
                            return;
                        }
                        form.reset();
                        form.hidden = true;
                        var panel = section.querySelector('.omf-success');
                        var panelText = panel ? panel.querySelector('.omf-success-text') : null;
                        if (panelText && res.data && res.data.msg) {
                            panelText.textContent = res.data.msg;
                        }
                        if (panel) {
                            panel.hidden = false;
                            if (panel.scrollIntoView) {
                                panel.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            }
                        }
                        fireConfetti();
                    } else {
                        feedback.hidden = false;
                        feedback.className = 'omf-feedback is-error';
                        feedback.textContent = res && res.data && res.data.msg ? res.data.msg : '';
                        button.disabled = false;
                    }
                })
                .catch(function () {
                    feedback.hidden = false;
                    feedback.className = 'omf-feedback is-error';
                    feedback.textContent = <?php echo wp_json_encode( __( 'Network error. Please try again.', '101-mobilization-form' ) ); ?>;
                    button.disabled = false;
                });
            return false;
        };
    }
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode( 'mobilization_registration_form', 'one01_mobform_render_form' );
add_shortcode( 'recenser_mobilisation', 'one01_mobform_render_form' );

/**
 * Print the scoped stylesheet once per request.
 */
function one01_mobform_print_styles() {
    static $printed = false;
    if ( $printed ) {
        return;
    }
    $printed = true;
    ?>
    <style>
    .one01-mobform{--omf-yellow:var(--j3-yellow,#faf829);--omf-black:var(--j3-black,#000);--omf-paper:var(--j3-paper-gray,#f4f4f0);--omf-white:#fff;max-width:760px;margin:40px auto;padding:0 16px;font-family:var(--j3-font-body,inherit);color:var(--omf-black);}
    .one01-mobform .omf-card{background:var(--omf-white);border:3px solid var(--omf-black);border-radius:2px;padding:32px;box-shadow:8px 8px 0 var(--omf-yellow);}
    .one01-mobform .omf-eyebrow{display:inline-block;background:var(--omf-black);color:var(--omf-yellow);font-size:11px;font-weight:800;letter-spacing:1.5px;text-transform:uppercase;padding:4px 10px;margin-bottom:12px;}
    .one01-mobform .omf-title{font-family:var(--j3-font-display,inherit);font-size:clamp(26px,4vw,38px);line-height:1.05;margin:0 0 12px;text-transform:uppercase;}
    .one01-mobform .omf-lead{font-size:15px;line-height:1.65;margin:0 0 14px;}
    .one01-mobform .omf-notes{margin:0 0 22px;padding-left:20px;font-size:13.5px;line-height:1.6;color:#333;}
    .one01-mobform .omf-notes li{margin-bottom:4px;}
    .one01-mobform .omf-fieldset{border:2px solid var(--omf-black);background:var(--omf-paper);padding:18px;margin:0 0 18px;}
    .one01-mobform .omf-legend{font-weight:800;font-size:13px;text-transform:uppercase;letter-spacing:0.5px;padding:0 6px;}
    .one01-mobform .omf-hint{font-size:13px;line-height:1.55;color:#444;margin:0 0 14px;}
    .one01-mobform .omf-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;}
    .one01-mobform .omf-field{margin-bottom:14px;}
    .one01-mobform .omf-field:last-child{margin-bottom:0;}
    .one01-mobform .omf-field label,.one01-mobform .omf-label{display:block;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:0.4px;margin-bottom:5px;}
    .one01-mobform .omf-req{color:#dc2626;}
    .one01-mobform .omf-field input[type=text],.one01-mobform .omf-field input[type=email],.one01-mobform .omf-field input[type=tel],.one01-mobform .omf-field input[type=url],.one01-mobform .omf-field input[type=datetime-local]{width:100%;padding:11px;border:2px solid var(--omf-black);background:var(--omf-white);box-sizing:border-box;font-size:15px;}
    .one01-mobform .omf-field small{display:block;margin-top:5px;font-size:12px;line-height:1.5;color:#555;}
    .one01-mobform .omf-consent{display:flex;gap:10px;align-items:flex-start;background:var(--omf-paper);border:2px solid var(--omf-black);padding:12px;font-size:12.5px;line-height:1.5;cursor:pointer;margin-bottom:18px;}
    .one01-mobform .omf-consent input{margin-top:3px;}
    .one01-mobform .omf-submit{width:100%;padding:15px;border:2px solid var(--omf-black);background:var(--omf-yellow);color:var(--omf-black);font-family:var(--j3-font-display,inherit);font-size:20px;text-transform:uppercase;letter-spacing:0.5px;cursor:pointer;box-shadow:4px 4px 0 var(--omf-black);transition:transform .1s ease;}
    .one01-mobform .omf-submit:hover{transform:translate(2px,2px);box-shadow:2px 2px 0 var(--omf-black);}
    .one01-mobform .omf-submit:disabled{opacity:.6;cursor:wait;transform:none;}
    .one01-mobform .omf-feedback{margin-top:14px;padding:12px 14px;border:2px solid var(--omf-black);font-weight:700;font-size:14px;}
    .one01-mobform .omf-feedback.is-success{background:#dcfce7;color:#166534;}
    .one01-mobform .omf-feedback.is-error{background:#fef2f2;color:#b91c1c;}
    .one01-mobform .omf-success{text-align:center;padding:8px 4px 4px;}
    .one01-mobform .omf-success-check{width:76px;height:76px;margin:0 auto 22px;border-radius:50%;background:var(--omf-yellow);border:3px solid var(--omf-black);display:flex;align-items:center;justify-content:center;font-size:42px;font-weight:800;line-height:1;box-shadow:4px 4px 0 var(--omf-black);}
    .one01-mobform .omf-success-title{font-family:var(--j3-font-display,inherit);font-size:clamp(24px,4vw,34px);line-height:1.05;text-transform:uppercase;margin:0 0 14px;}
    .one01-mobform .omf-success-text{font-size:15px;line-height:1.65;margin:0 auto 24px;max-width:540px;color:#333;}
    .one01-mobform .omf-success-cta{display:inline-block;padding:14px 28px;border:2px solid var(--omf-black);background:var(--omf-yellow);color:var(--omf-black);font-family:var(--j3-font-display,inherit);font-size:18px;text-transform:uppercase;letter-spacing:0.5px;text-decoration:none;box-shadow:4px 4px 0 var(--omf-black);transition:transform .1s ease;}
    .one01-mobform .omf-success-cta:hover{transform:translate(2px,2px);box-shadow:2px 2px 0 var(--omf-black);}
    .one01-mobform .omf-hp{position:absolute!important;left:-9999px!important;width:1px;height:1px;overflow:hidden;}
    </style>
    <?php
}

/**
 * Add submission columns to the Assemblies & Events list table.
 *
 * @param array $columns Existing columns.
 * @return array
 */
add_filter( 'manage_mobilization_hub_posts_columns', function ( $columns ) {
    $new = array();
    foreach ( $columns as $key => $label ) {
        $new[ $key ] = $label;
        if ( 'title' === $key ) {
            $new['omf_contact']  = __( 'Contact', '101-mobilization-form' );
            $new['omf_postal']   = __( 'Postal Code', '101-mobilization-form' );
            $new['omf_geocode']  = __( 'Geocode', '101-mobilization-form' );
        }
    }
    return $new;
} );

/**
 * Render the submission columns.
 *
 * @param string $column  Column key.
 * @param int    $post_id Post ID.
 */
add_action( 'manage_mobilization_hub_posts_custom_column', function ( $column, $post_id ) {
    $email = get_post_meta( $post_id, '_entry_email', true );
    if ( ! $email ) {
        return;
    }

    switch ( $column ) {
        case 'omf_contact':
            $phone = get_post_meta( $post_id, '_entry_phone', true );
            echo '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>';
            if ( $phone ) {
                echo '<br><a href="tel:' . esc_attr( $phone ) . '">' . esc_html( $phone ) . '</a>';
            }
            break;
        case 'omf_postal':
            echo esc_html( get_post_meta( $post_id, '_entry_postal_code', true ) );
            break;
        case 'omf_geocode':
            if ( 'ok' === get_post_meta( $post_id, '_entry_geocode_status', true ) ) {
                echo '<span style="color:#16a34a;font-weight:700;">✓ ' . esc_html__( 'Located', '101-mobilization-form' ) . '</span>';
            } else {
                echo '<span style="color:#dc2626;font-weight:700;">⚠ ' . esc_html__( 'Needs placement', '101-mobilization-form' ) . '</span>';
            }
            break;
    }
}, 10, 2 );

/**
 * Show the submitted contact & assembly details on the edit screen.
 */
add_action( 'add_meta_boxes', function () {
    add_meta_box(
        'one01_mobform_entry',
        __( 'Mobilization Registration Details', '101-mobilization-form' ),
        'one01_mobform_render_entry_meta_box',
        'mobilization_hub',
        'side',
        'high'
    );
} );

/**
 * Render the registration details meta box (read-only, private data).
 *
 * @param WP_Post $post Current post.
 */
function one01_mobform_render_entry_meta_box( $post ) {
    $email = get_post_meta( $post->ID, '_entry_email', true );
    if ( ! $email ) {
        echo '<p>' . esc_html__( 'No registration details for this entry.', '101-mobilization-form' ) . '</p>';
        return;
    }

    $phone      = get_post_meta( $post->ID, '_entry_phone', true );
    $group_url  = get_post_meta( $post->ID, '_entry_group_url', true );
    $postal     = get_post_meta( $post->ID, '_entry_postal_code', true );
    $asm_time   = get_post_meta( $post->ID, '_entry_assembly_time', true );
    $asm_place  = get_post_meta( $post->ID, '_entry_assembly_place', true );
    $geo_status = get_post_meta( $post->ID, '_entry_geocode_status', true );
    $geo_label  = get_post_meta( $post->ID, '_entry_geocode_label', true );
    $country    = get_post_meta( $post->ID, '_entry_country', true );
    if ( '' === $country ) {
        $country = one01_mobform_site_country();
    }
    $consent    = get_post_meta( $post->ID, '_entry_consent_timestamp', true );

    ?>
    <div style="font-size:13px;line-height:1.6;">
        <p style="margin:0 0 6px;"><strong><?php esc_html_e( 'Email', '101-mobilization-form' ); ?>:</strong><br>
            <a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a></p>
        <?php if ( $phone ) : ?>
            <p style="margin:0 0 6px;"><strong><?php esc_html_e( 'Phone', '101-mobilization-form' ); ?>:</strong><br>
                <a href="tel:<?php echo esc_attr( $phone ); ?>"><?php echo esc_html( $phone ); ?></a></p>
        <?php endif; ?>
        <?php if ( $group_url ) : ?>
            <p style="margin:0 0 6px;"><strong><?php esc_html_e( 'Group link', '101-mobilization-form' ); ?>:</strong><br>
                <a href="<?php echo esc_url( $group_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $group_url ); ?></a></p>
        <?php endif; ?>
        <p style="margin:0 0 6px;"><strong><?php esc_html_e( 'Postal code', '101-mobilization-form' ); ?>:</strong> <?php echo esc_html( $postal ); ?></p>
        <p style="margin:0 0 6px;"><strong><?php esc_html_e( 'Country (geocoding)', '101-mobilization-form' ); ?>:</strong> <?php echo esc_html( $country ? $country : '—' ); ?></p>
        <?php if ( $asm_time || $asm_place ) : ?>
            <p style="margin:0 0 6px;"><strong><?php esc_html_e( 'Assembly', '101-mobilization-form' ); ?>:</strong> <?php echo esc_html( trim( $asm_time . ' ' . $asm_place ) ); ?></p>
        <?php endif; ?>
        <p style="margin:0 0 6px;"><strong><?php esc_html_e( 'Consent given', '101-mobilization-form' ); ?>:</strong> <?php echo esc_html( $consent ? $consent : '—' ); ?></p>

        <p style="margin:8px 0 6px;">
            <strong><?php esc_html_e( 'Geocode', '101-mobilization-form' ); ?>:</strong>
            <?php if ( 'ok' === $geo_status ) : ?>
                <span style="color:#16a34a;font-weight:700;">✓ <?php esc_html_e( 'Located', '101-mobilization-form' ); ?></span>
            <?php else : ?>
                <span style="color:#dc2626;font-weight:700;">⚠ <?php esc_html_e( 'Needs placement', '101-mobilization-form' ); ?></span>
            <?php endif; ?>
        </p>
        <?php if ( $geo_label ) : ?>
            <p style="margin:0 0 8px;color:#64748b;font-size:12px;"><?php echo esc_html( $geo_label ); ?></p>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'one01_mobform_regeocode_' . $post->ID ); ?>
            <input type="hidden" name="action" value="one01_mobform_regeocode">
            <input type="hidden" name="post_id" value="<?php echo esc_attr( $post->ID ); ?>">
            <button type="submit" class="button button-secondary" style="width:100%;"><?php esc_html_e( 'Re-run geocoding', '101-mobilization-form' ); ?></button>
        </form>
        <p style="margin:8px 0 0;color:#64748b;font-size:12px;"><?php esc_html_e( 'Contact details are private and never published.', '101-mobilization-form' ); ?></p>
    </div>
    <?php
}

/**
 * Handle the "re-run geocoding" action from the meta box.
 */
add_action( 'admin_post_one01_mobform_regeocode', function () {
    $post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;

    if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
        wp_die( esc_html__( 'You are not allowed to do that.', '101-mobilization-form' ) );
    }
    check_admin_referer( 'one01_mobform_regeocode_' . $post_id );

    $defaults = one01_mobform_defaults();
    $postal   = get_post_meta( $post_id, '_entry_postal_code', true );
    $city     = get_post_meta( $post_id, '_entry_city', true );
    $country  = get_post_meta( $post_id, '_entry_country', true );
    if ( '' === $country ) {
        $country = $defaults['country'];
    }

    $result = 'missing';
    if ( $postal && $city ) {
        // Drop any cached miss so a manual re-run always hits the network.
        delete_transient( one01_mobform_geocode_cache_key( $postal, $city, $country ) );

        $coords = one01_mobform_geocode( $postal, $city, $country );
        if ( ! is_wp_error( $coords ) ) {
            update_post_meta( $post_id, '_hub_lat', $coords['lat'] );
            update_post_meta( $post_id, '_hub_lng', $coords['lng'] );
            update_post_meta( $post_id, '_entry_geocode_status', 'ok' );
            update_post_meta( $post_id, '_entry_geocode_label', $coords['label'] );
            $result = 'ok';
        } else {
            update_post_meta( $post_id, '_entry_geocode_status', 'failed' );
            $result = 'failed';
        }
    }

    wp_safe_redirect( add_query_arg( 'one01_geocode', $result, get_edit_post_link( $post_id, 'raw' ) ) );
    exit;
} );

/**
 * Show the outcome of a manual "re-run geocoding" action.
 */
add_action( 'admin_notices', function () {
    if ( ! isset( $_GET['one01_geocode'] ) ) {
        return;
    }

    $post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
    if ( ! $post_id || 'mobilization_hub' !== get_post_type( $post_id ) ) {
        return;
    }

    $state = sanitize_text_field( wp_unslash( $_GET['one01_geocode'] ) );

    if ( 'ok' === $state ) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Geocoding succeeded — the map point was updated.', '101-mobilization-form' ) . '</p></div>';
    } elseif ( 'missing' === $state ) {
        echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Geocoding did not run: the postal code or town/city is missing.', '101-mobilization-form' ) . '</p></div>';
    } else {
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Geocoding failed — the postal code could not be located. Check the postal code and the country set for this site.', '101-mobilization-form' ) . '</p></div>';
    }
} );
// === END OF MODULE 6 ===
