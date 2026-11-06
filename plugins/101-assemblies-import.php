<?php
/**
 * Plugin Name: 101 Demands — Module 3.1: Assemblies & Hubs CSV Importer
 * Description: Dedicated CSV importer for Mobilization Hubs and Assemblies with customizable field mapping, delimiter detection, and assembly type assignment.
 * Version:     1.0.0
 * Author:      101 Demands Global Tech Collective
 * Text Domain: 101-assemblies-import
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
    load_plugin_textdomain( '101-assemblies-import', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    $locale = determine_locale();
    $mofile = plugin_dir_path( __FILE__ ) . 'languages/101-assemblies-import-' . $locale . '.mo';
    if ( file_exists( $mofile ) ) {
        load_textdomain( '101-assemblies-import', $mofile );
    }
} );

/**
 * Register admin menu items.
 */
add_action( 'admin_menu', function() {
    add_submenu_page(
        'edit.php?post_type=mobilization_hub',
        __( 'Import Assemblies (CSV)', '101-assemblies-import' ),
        __( 'Import CSV', '101-assemblies-import' ),
        'edit_posts',
        '101-assemblies-import',
        'july3_render_assemblies_importer_page'
    );

    add_management_page(
        __( 'Import Assemblies (CSV)', '101-assemblies-import' ),
        __( 'Assemblies Importer', '101-assemblies-import' ),
        'edit_posts',
        '101-assemblies-import',
        'july3_render_assemblies_importer_page'
    );
} );

/**
 * Helper: Detect CSV delimiter.
 */
function july3_detect_csv_delimiter( $file_path ) {
    $delimiters = array( ';', ',', "\t", '|' );
    $best_delimiter = ';';
    $max_count = 0;

    if ( ( $handle = fopen( $file_path, 'r' ) ) !== false ) {
        $first_line = fgets( $handle );
        fclose( $handle );

        if ( $first_line ) {
            foreach ( $delimiters as $delimiter ) {
                $count = count( str_getcsv( $first_line, $delimiter ) );
                if ( $count > $max_count ) {
                    $max_count = $count;
                    $best_delimiter = $delimiter;
                }
            }
        }
    }

    return $best_delimiter;
}

/**
 * Helper: Get sample CSV data.
 */
function july3_get_csv_preview( $file_path, $delimiter = ';', $limit = 5 ) {
    $headers = array();
    $rows = array();
    $total_rows = 0;

    if ( ( $handle = fopen( $file_path, 'r' ) ) !== false ) {
        // Read BOM if present
        $bom = fread( $handle, 3 );
        if ( $bom !== "\xEF\xBB\xBF" ) {
            rewind( $handle );
        }

        $header_line = fgetcsv( $handle, 0, $delimiter );
        if ( $header_line ) {
            $headers = array_map( 'trim', $header_line );
            while ( ( $row = fgetcsv( $handle, 0, $delimiter ) ) !== false ) {
                $total_rows++;
                if ( count( $rows ) < $limit ) {
                    $rows[] = $row;
                }
            }
        }
        fclose( $handle );
    }

    return array(
        'headers'    => $headers,
        'rows'       => $rows,
        'total_rows' => $total_rows,
    );
}

/**
 * Helper: Get or save temporary CSV file.
 */
function july3_get_importer_temp_dir() {
    $upload_dir = wp_upload_dir();
    $temp_dir = trailingslashit( $upload_dir['basedir'] ) . '101-imports';
    if ( ! file_exists( $temp_dir ) ) {
        wp_mkdir_p( $temp_dir );
        // Add index.html and .htaccess for security
        file_put_contents( $temp_dir . '/index.html', '' );
        file_put_contents( $temp_dir . '/.htaccess', 'Deny from all' );
    }
    return $temp_dir;
}

/**
 * Main Controller & View Renderer for Assemblies Importer.
 */
function july3_render_assemblies_importer_page() {
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', '101-assemblies-import' ) );
    }

    $step = isset( $_REQUEST['step'] ) ? sanitize_text_field( $_REQUEST['step'] ) : 'step1';

    echo '<div class="wrap" style="max-width:1100px;">';
    echo '<h1 style="display:flex;align-items:center;gap:10px;font-size:24px;font-weight:800;color:#0f172a;margin-bottom:20px;">';
    echo '<span style="font-size:28px;">📥</span> ' . esc_html__( 'Assemblies &amp; Hubs CSV Importer', '101-assemblies-import' );
    echo '</h1>';

    if ( 'step2' === $step ) {
        july3_render_importer_step2();
    } elseif ( 'step3' === $step ) {
        july3_render_importer_step3();
    } else {
        july3_render_importer_step1();
    }

    echo '</div>';
}

/**
 * Step 1: Upload CSV file.
 */
function july3_render_importer_step1() {
    ?>
    <div style="background:#fff;border:1px solid #cbd5e1;border-radius:12px;padding:28px;box-shadow:0 4px 6px -1px rgba(0,0,0,0.05);margin-top:15px;">
        <div style="margin-bottom:24px;">
            <h2 style="margin:0 0 8px;font-size:18px;font-weight:700;color:#1e293b;"><?php esc_html_e( 'Step 1: Select CSV File to Import', '101-assemblies-import' ); ?></h2>
            <p style="margin:0;color:#64748b;font-size:14px;line-height:1.5;">
                <?php esc_html_e( 'Upload your CSV file containing assembly locations, town halls, or cities. In the next step, you can customize which columns map to the title, coordinates, and other details, as well as select the assembly type.', '101-assemblies-import' ); ?>
            </p>
        </div>

        <form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'edit.php?post_type=mobilization_hub&page=101-assemblies-import' ) ); ?>">
            <?php wp_nonce_field( '101_import_step1_action', 'import_nonce' ); ?>
            <input type="hidden" name="step" value="step2" />

            <div style="background:#f8fafc;border:2px dashed #cbd5e1;border-radius:10px;padding:32px;text-align:center;margin-bottom:24px;">
                <div style="font-size:36px;margin-bottom:12px;">📄</div>
                <label for="csv_file" style="display:block;font-weight:700;font-size:15px;color:#0f172a;margin-bottom:8px;">
                    <?php esc_html_e( 'Choose a CSV file from your computer', '101-assemblies-import' ); ?>
                </label>
                <input type="file" id="csv_file" name="csv_file" accept=".csv,.txt,.tsv" style="margin:10px auto;display:inline-block;" />
                <div style="color:#64748b;font-size:12.5px;margin-top:6px;">
                    <?php esc_html_e( 'Supported formats: .csv, .txt, .tsv (UTF-8 encoding recommended)', '101-assemblies-import' ); ?>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:20px;margin-bottom:28px;">
                <div>
                    <label for="delimiter" style="font-weight:700;display:block;margin-bottom:6px;color:#334155;"><?php esc_html_e( 'CSV Delimiter / Separator', '101-assemblies-import' ); ?></label>
                    <select id="delimiter" name="delimiter" style="width:100%;max-width:320px;padding:6px 10px;border-radius:6px;border:1px solid #cbd5e1;">
                        <option value="auto"><?php esc_html_e( 'Auto-Detect (Recommended)', '101-assemblies-import' ); ?></option>
                        <option value=";"><?php esc_html_e( 'Semicolon ( ; )', '101-assemblies-import' ); ?></option>
                        <option value=","><?php esc_html_e( 'Comma ( , )', '101-assemblies-import' ); ?></option>
                        <option value="\t"><?php esc_html_e( 'Tab ( \\t )', '101-assemblies-import' ); ?></option>
                        <option value="|"><?php esc_html_e( 'Pipe ( | )', '101-assemblies-import' ); ?></option>
                    </select>
                </div>
            </div>

            <div style="display:flex;justify-content:flex-end;">
                <button type="submit" class="button button-primary" style="padding:8px 24px;height:auto;font-size:14px;font-weight:700;border-radius:6px;background:#ea580c;border-color:#c2410c;">
                    <?php esc_html_e( 'Upload &amp; Continue to Field Mapping →', '101-assemblies-import' ); ?>
                </button>
            </div>
        </form>
    </div>
    <?php
}

/**
 * Step 2: Configure Column Mapping & Assembly Type.
 */
function july3_render_importer_step2() {
    if ( ! isset( $_POST['import_nonce'] ) || ! wp_verify_nonce( $_POST['import_nonce'], '101_import_step1_action' ) ) {
        echo '<div class="notice notice-error"><p>' . esc_html__( 'Security verification failed. Please try again.', '101-assemblies-import' ) . '</p></div>';
        return;
    }

    $temp_dir = july3_get_importer_temp_dir();
    $target_file = '';
    $original_name = '';

    if ( ! empty( $_FILES['csv_file']['tmp_name'] ) && is_uploaded_file( $_FILES['csv_file']['tmp_name'] ) ) {
        $token = 'import_' . wp_generate_password( 12, false ) . '.csv';
        $target_file = $temp_dir . '/' . $token;
        move_uploaded_file( $_FILES['csv_file']['tmp_name'], $target_file );
        $original_name = sanitize_file_name( $_FILES['csv_file']['name'] );
    }

    if ( empty( $target_file ) || ! file_exists( $target_file ) ) {
        echo '<div class="notice notice-error"><p>' . esc_html__( 'No CSV file was selected or uploaded. Please go back and choose a file.', '101-assemblies-import' ) . '</p></div>';
        echo '<p><a href="' . esc_url( admin_url( 'edit.php?post_type=mobilization_hub&page=101-assemblies-import' ) ) . '" class="button">← ' . esc_html__( 'Back', '101-assemblies-import' ) . '</a></p>';
        return;
    }

    $delimiter_choice = isset( $_POST['delimiter'] ) ? sanitize_text_field( $_POST['delimiter'] ) : 'auto';
    $delimiter = ( 'auto' === $delimiter_choice ) ? july3_detect_csv_delimiter( $target_file ) : ( ( '\t' === $delimiter_choice ) ? "\t" : $delimiter_choice );

    $preview = july3_get_csv_preview( $target_file, $delimiter, 5 );
    $headers = $preview['headers'];
    $rows = $preview['rows'];
    $total_rows = $preview['total_rows'];

    if ( empty( $headers ) ) {
        echo '<div class="notice notice-error"><p>' . esc_html__( 'Could not parse headers from the CSV file. Please verify the delimiter and format.', '101-assemblies-import' ) . '</p></div>';
        echo '<p><a href="' . esc_url( admin_url( 'edit.php?post_type=mobilization_hub&page=101-assemblies-import' ) ) . '" class="button">← ' . esc_html__( 'Back', '101-assemblies-import' ) . '</a></p>';
        return;
    }

    // Auto-detect mappings based on header names
    $auto_map = array(
        'title'     => '',
        'lat'       => '',
        'lng'       => '',
        'meetup'    => '',
        'next_date' => '',
        'chat_url'  => '',
        'type_col'  => '',
    );

    foreach ( $headers as $col_name ) {
        $lc = strtolower( trim( $col_name ) );
        if ( empty( $auto_map['title'] ) && in_array( $lc, array( 'name', 'title', 'stadt', 'city', 'gemeinde', 'hub_name', 'name_normalized' ), true ) ) {
            $auto_map['title'] = $col_name;
        }
        if ( empty( $auto_map['lat'] ) && in_array( $lc, array( 'lat', 'latitude', 'breitengrad', 'y', 'lat_dec' ), true ) ) {
            $auto_map['lat'] = $col_name;
        }
        if ( empty( $auto_map['lng'] ) && in_array( $lc, array( 'lon', 'lng', 'long', 'longitude', 'laengengrad', 'x', 'lon_dec' ), true ) ) {
            $auto_map['lng'] = $col_name;
        }
        if ( empty( $auto_map['meetup'] ) && in_array( $lc, array( 'meetup', 'address', 'adresse', 'treffpunkt', 'ort', 'location', 'venue' ), true ) ) {
            $auto_map['meetup'] = $col_name;
        }
        if ( empty( $auto_map['next_date'] ) && in_array( $lc, array( 'date', 'next_date', 'datum', 'termin', 'time', 'datetime' ), true ) ) {
            $auto_map['next_date'] = $col_name;
        }
        if ( empty( $auto_map['chat_url'] ) && in_array( $lc, array( 'chat', 'chat_url', 'url', 'link', 'website', 'rsvp', 'rsvp_url' ), true ) ) {
            $auto_map['chat_url'] = $col_name;
        }
        if ( empty( $auto_map['type_col'] ) && in_array( $lc, array( 'type', 'hub_type', 'assembly_type', 'kind', 'category' ), true ) ) {
            $auto_map['type_col'] = $col_name;
        }
    }
    ?>
    <div style="background:#fff;border:1px solid #cbd5e1;border-radius:12px;padding:28px;box-shadow:0 4px 6px -1px rgba(0,0,0,0.05);margin-top:15px;">
        <div style="margin-bottom:20px;border-bottom:1px solid #e2e8f0;padding-bottom:16px;">
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
                <div>
                    <h2 style="margin:0 0 4px;font-size:18px;font-weight:700;color:#1e293b;"><?php esc_html_e( 'Step 2: Customize Field Mapping &amp; Assembly Type', '101-assemblies-import' ); ?></h2>
                    <span style="color:#64748b;font-size:13.5px;">
                        <?php printf( esc_html__( 'File: %s | Total Rows: %d | Detected Delimiter: "%s"', '101-assemblies-import' ), '<strong>' . esc_html( $original_name ) . '</strong>', $total_rows, esc_html( $delimiter === "\t" ? '\t (Tab)' : $delimiter ) ); ?>
                    </span>
                </div>
                <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=mobilization_hub&page=101-assemblies-import' ) ); ?>" class="button button-secondary">
                    ← <?php esc_html_e( 'Upload Different File', '101-assemblies-import' ); ?>
                </a>
            </div>
        </div>

        <!-- Preview Table -->
        <div style="margin-bottom:28px;">
            <h3 style="margin:0 0 8px;font-size:14px;font-weight:700;color:#334155;text-transform:uppercase;letter-spacing:0.5px;"><?php esc_html_e( 'CSV Data Preview (First 5 Rows):', '101-assemblies-import' ); ?></h3>
            <div style="overflow-x:auto;max-height:220px;border:1px solid #cbd5e1;border-radius:8px;">
                <table class="widefat striped" style="border:none;font-size:12.5px;">
                    <thead>
                        <tr style="background:#f1f5f9;">
                            <?php foreach ( $headers as $col ) : ?>
                                <th style="font-weight:700;color:#0f172a;white-space:nowrap;"><?php echo esc_html( $col ); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $rows as $row ) : ?>
                            <tr>
                                <?php foreach ( $headers as $idx => $col ) : ?>
                                    <td style="white-space:nowrap;max-width:250px;overflow:hidden;text-overflow:ellipsis;">
                                        <?php echo esc_html( isset( $row[ $idx ] ) ? $row[ $idx ] : '' ); ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <form method="post" action="<?php echo esc_url( admin_url( 'edit.php?post_type=mobilization_hub&page=101-assemblies-import' ) ); ?>">
            <?php wp_nonce_field( '101_import_step2_action', 'import_nonce' ); ?>
            <input type="hidden" name="step" value="step3" />
            <input type="hidden" name="temp_file" value="<?php echo esc_attr( basename( $target_file ) ); ?>" />
            <input type="hidden" name="delimiter" value="<?php echo esc_attr( $delimiter ); ?>" />

            <!-- Field Mapping Section -->
            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:24px;margin-bottom:24px;">
                <h3 style="margin:0 0 16px;font-size:16px;font-weight:800;color:#0f172a;display:flex;align-items:center;gap:8px;">
                    <span>🗺️</span> <?php esc_html_e( 'Column Mapping (Match CSV Columns to Assembly Fields)', '101-assemblies-import' ); ?>
                </h3>

                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:18px;">
                    <!-- Title -->
                    <div>
                        <label for="map_title" style="font-weight:700;display:block;margin-bottom:4px;color:#0f172a;">
                            <?php esc_html_e( 'Assembly Name / Title', '101-assemblies-import' ); ?> <span style="color:#dc2626;">* (<?php esc_html_e( 'Required', '101-assemblies-import' ); ?>)</span>
                        </label>
                        <select id="map_title" name="map_title" required style="width:100%;padding:6px 10px;border-radius:6px;border:1px solid #cbd5e1;">
                            <option value=""><?php esc_html_e( '-- Select Column --', '101-assemblies-import' ); ?></option>
                            <?php foreach ( $headers as $col ) : ?>
                                <option value="<?php echo esc_attr( $col ); ?>" <?php selected( $auto_map['title'], $col ); ?>><?php echo esc_html( $col ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color:#64748b;display:block;margin-top:3px;"><?php esc_html_e( 'The post title on the map and listings (e.g. city name).', '101-assemblies-import' ); ?></small>
                    </div>

                    <!-- Latitude -->
                    <div>
                        <label for="map_lat" style="font-weight:700;display:block;margin-bottom:4px;color:#0f172a;">
                            <?php esc_html_e( 'Latitude (Breitengrad)', '101-assemblies-import' ); ?> <span style="color:#dc2626;">* (<?php esc_html_e( 'Required', '101-assemblies-import' ); ?>)</span>
                        </label>
                        <select id="map_lat" name="map_lat" required style="width:100%;padding:6px 10px;border-radius:6px;border:1px solid #cbd5e1;">
                            <option value=""><?php esc_html_e( '-- Select Column --', '101-assemblies-import' ); ?></option>
                            <?php foreach ( $headers as $col ) : ?>
                                <option value="<?php echo esc_attr( $col ); ?>" <?php selected( $auto_map['lat'], $col ); ?>><?php echo esc_html( $col ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color:#64748b;display:block;margin-top:3px;"><?php esc_html_e( 'Decimal latitude value (e.g. 52.5200).', '101-assemblies-import' ); ?></small>
                    </div>

                    <!-- Longitude -->
                    <div>
                        <label for="map_lng" style="font-weight:700;display:block;margin-bottom:4px;color:#0f172a;">
                            <?php esc_html_e( 'Longitude (Längengrad)', '101-assemblies-import' ); ?> <span style="color:#dc2626;">* (<?php esc_html_e( 'Required', '101-assemblies-import' ); ?>)</span>
                        </label>
                        <select id="map_lng" name="map_lng" required style="width:100%;padding:6px 10px;border-radius:6px;border:1px solid #cbd5e1;">
                            <option value=""><?php esc_html_e( '-- Select Column --', '101-assemblies-import' ); ?></option>
                            <?php foreach ( $headers as $col ) : ?>
                                <option value="<?php echo esc_attr( $col ); ?>" <?php selected( $auto_map['lng'], $col ); ?>><?php echo esc_html( $col ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color:#64748b;display:block;margin-top:3px;"><?php esc_html_e( 'Decimal longitude value (e.g. 13.4050).', '101-assemblies-import' ); ?></small>
                    </div>

                    <!-- Meetup Address -->
                    <div>
                        <label for="map_meetup" style="font-weight:700;display:block;margin-bottom:4px;color:#0f172a;">
                            <?php esc_html_e( 'Meeting Point / Address', '101-assemblies-import' ); ?> <span style="color:#64748b;font-weight:400;">(<?php esc_html_e( 'Optional', '101-assemblies-import' ); ?>)</span>
                        </label>
                        <select id="map_meetup" name="map_meetup" style="width:100%;padding:6px 10px;border-radius:6px;border:1px solid #cbd5e1;">
                            <option value=""><?php esc_html_e( '-- None / Leave Empty --', '101-assemblies-import' ); ?></option>
                            <?php foreach ( $headers as $col ) : ?>
                                <option value="<?php echo esc_attr( $col ); ?>" <?php selected( $auto_map['meetup'], $col ); ?>><?php echo esc_html( $col ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Next Date -->
                    <div>
                        <label for="map_next_date" style="font-weight:700;display:block;margin-bottom:4px;color:#0f172a;">
                            <?php esc_html_e( 'Next Meeting / Date &amp; Time', '101-assemblies-import' ); ?> <span style="color:#64748b;font-weight:400;">(<?php esc_html_e( 'Optional', '101-assemblies-import' ); ?>)</span>
                        </label>
                        <select id="map_next_date" name="map_next_date" style="width:100%;padding:6px 10px;border-radius:6px;border:1px solid #cbd5e1;">
                            <option value=""><?php esc_html_e( '-- None / Leave Empty --', '101-assemblies-import' ); ?></option>
                            <?php foreach ( $headers as $col ) : ?>
                                <option value="<?php echo esc_attr( $col ); ?>" <?php selected( $auto_map['next_date'], $col ); ?>><?php echo esc_html( $col ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Chat URL -->
                    <div>
                        <label for="map_chat_url" style="font-weight:700;display:block;margin-bottom:4px;color:#0f172a;">
                            <?php esc_html_e( 'Link / Chat / RSVP URL', '101-assemblies-import' ); ?> <span style="color:#64748b;font-weight:400;">(<?php esc_html_e( 'Optional', '101-assemblies-import' ); ?>)</span>
                        </label>
                        <select id="map_chat_url" name="map_chat_url" style="width:100%;padding:6px 10px;border-radius:6px;border:1px solid #cbd5e1;">
                            <option value=""><?php esc_html_e( '-- None / Leave Empty --', '101-assemblies-import' ); ?></option>
                            <?php foreach ( $headers as $col ) : ?>
                                <option value="<?php echo esc_attr( $col ); ?>" <?php selected( $auto_map['chat_url'], $col ); ?>><?php echo esc_html( $col ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Assembly Type Assignment Section -->
            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:24px;margin-bottom:24px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
                <h3 style="margin:0 0 12px;font-size:16px;font-weight:800;color:#0f172a;display:flex;align-items:center;gap:8px;">
                    <span>📍</span> <?php esc_html_e( 'Assembly Type / Marker Color Assignment', '101-assemblies-import' ); ?>
                </h3>
                <p style="margin:0 0 16px;color:#64748b;font-size:13.5px;">
                    <?php esc_html_e( 'Choose which assembly type will be assigned to all imported entries:', '101-assemblies-import' ); ?>
                </p>

                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px;margin-bottom:16px;">
                    <label style="display:flex;align-items:center;gap:10px;padding:12px 14px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;cursor:pointer;">
                        <input type="radio" name="assembly_type" value="prep" checked style="margin:0;" />
                        <span style="display:inline-block;width:14px;height:14px;border-radius:50%;background:#f59e0b;border:2.5px solid #1c130e;box-sizing:border-box;flex-shrink:0;"></span>
                        <span style="font-weight:700;color:#0f172a;font-size:13.5px;"><?php esc_html_e( 'Preparation Meeting (Yellow)', '101-assemblies-import' ); ?></span>
                    </label>

                    <label style="display:flex;align-items:center;gap:10px;padding:12px 14px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;cursor:pointer;">
                        <input type="radio" name="assembly_type" value="confirmed" style="margin:0;" />
                        <span style="display:inline-block;width:14px;height:14px;border-radius:50%;background:#16a34a;border:2.5px solid #1c130e;box-sizing:border-box;flex-shrink:0;"></span>
                        <span style="font-weight:700;color:#0f172a;font-size:13.5px;"><?php esc_html_e( 'Confirmed Assembly (Green)', '101-assemblies-import' ); ?></span>
                    </label>

                    <label style="display:flex;align-items:center;gap:10px;padding:12px 14px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;cursor:pointer;">
                        <input type="radio" name="assembly_type" value="capital" style="margin:0;" />
                        <span style="display:inline-block;width:16px;height:16px;border-radius:50%;background:#ea580c;border:2.5px solid #1c130e;box-sizing:border-box;flex-shrink:0;"></span>
                        <span style="font-weight:700;color:#0f172a;font-size:13.5px;"><?php esc_html_e( 'July 3 Capital Convergence (Orange / Large)', '101-assemblies-import' ); ?></span>
                    </label>

                    <label style="display:flex;align-items:center;gap:10px;padding:12px 14px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;cursor:pointer;">
                        <input type="radio" name="assembly_type" value="event" style="margin:0;" />
                        <span style="display:inline-block;width:14px;height:14px;border-radius:50%;background:#8b5cf6;border:2.5px solid #1c130e;box-sizing:border-box;flex-shrink:0;"></span>
                        <span style="font-weight:700;color:#0f172a;font-size:13.5px;"><?php esc_html_e( 'Talk, Town Hall &amp; Training (Purple)', '101-assemblies-import' ); ?></span>
                    </label>
                </div>

                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px 16px;">
                    <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                        <input type="radio" name="assembly_type" value="from_column" style="margin:0;" />
                        <span style="font-weight:700;color:#334155;font-size:13.5px;"><?php esc_html_e( 'Or read assembly type dynamically from a CSV column:', '101-assemblies-import' ); ?></span>
                        <select name="type_column" style="padding:4px 8px;border-radius:6px;border:1px solid #cbd5e1;margin-left:8px;">
                            <option value=""><?php esc_html_e( '-- Select Column --', '101-assemblies-import' ); ?></option>
                            <?php foreach ( $headers as $col ) : ?>
                                <option value="<?php echo esc_attr( $col ); ?>" <?php selected( $auto_map['type_col'], $col ); ?>><?php echo esc_html( $col ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
            </div>

            <!-- Import Settings & Duplicate Handling -->
            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:24px;margin-bottom:28px;">
                <h3 style="margin:0 0 14px;font-size:16px;font-weight:800;color:#0f172a;">⚙️ <?php esc_html_e( 'Import Settings &amp; Duplicate Handling', '101-assemblies-import' ); ?></h3>

                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;">
                    <div>
                        <label for="post_status" style="font-weight:700;display:block;margin-bottom:6px;color:#0f172a;"><?php esc_html_e( 'Post Status', '101-assemblies-import' ); ?></label>
                        <select id="post_status" name="post_status" style="width:100%;max-width:320px;padding:6px 10px;border-radius:6px;border:1px solid #cbd5e1;">
                            <option value="publish" selected><?php esc_html_e( 'Published (Visible on Map immediately)', '101-assemblies-import' ); ?></option>
                            <option value="draft"><?php esc_html_e( 'Draft (Hidden until published)', '101-assemblies-import' ); ?></option>
                        </select>
                    </div>

                    <div>
                        <label for="duplicate_handling" style="font-weight:700;display:block;margin-bottom:6px;color:#0f172a;"><?php esc_html_e( 'Duplicate Handling (Matching by Title)', '101-assemblies-import' ); ?></label>
                        <select id="duplicate_handling" name="duplicate_handling" style="width:100%;max-width:320px;padding:6px 10px;border-radius:6px;border:1px solid #cbd5e1;">
                            <option value="update" selected><?php esc_html_e( 'Update existing assembly (Recommended)', '101-assemblies-import' ); ?></option>
                            <option value="skip"><?php esc_html_e( 'Skip duplicates (Do not overwrite)', '101-assemblies-import' ); ?></option>
                            <option value="create"><?php esc_html_e( 'Always create new post (Allow duplicates)', '101-assemblies-import' ); ?></option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Submit -->
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=mobilization_hub&page=101-assemblies-import' ) ); ?>" class="button button-large">
                    ← <?php esc_html_e( 'Back', '101-assemblies-import' ); ?>
                </a>

                <button type="submit" class="button button-primary button-hero" style="font-weight:800;background:#16a34a;border-color:#15803d;padding:0 32px;border-radius:8px;">
                    <?php esc_html_e( 'Start Import Now →', '101-assemblies-import' ); ?>
                </button>
            </div>
        </form>
    </div>
    <?php
}

/**
 * Step 3: Process the Import & Display Results.
 */
function july3_render_importer_step3() {
    if ( ! isset( $_POST['import_nonce'] ) || ! wp_verify_nonce( $_POST['import_nonce'], '101_import_step2_action' ) ) {
        echo '<div class="notice notice-error"><p>' . esc_html__( 'Security verification failed. Please try again.', '101-assemblies-import' ) . '</p></div>';
        return;
    }

    $temp_file_name = isset( $_POST['temp_file'] ) ? sanitize_file_name( $_POST['temp_file'] ) : '';
    $temp_dir = july3_get_importer_temp_dir();
    $file_path = $temp_dir . '/' . $temp_file_name;

    if ( empty( $temp_file_name ) || ! file_exists( $file_path ) ) {
        echo '<div class="notice notice-error"><p>' . esc_html__( 'Temporary import file not found or expired. Please upload the CSV again.', '101-assemblies-import' ) . '</p></div>';
        echo '<p><a href="' . esc_url( admin_url( 'edit.php?post_type=mobilization_hub&page=101-assemblies-import' ) ) . '" class="button">← ' . esc_html__( 'Back to Upload', '101-assemblies-import' ) . '</a></p>';
        return;
    }

    $delimiter = isset( $_POST['delimiter'] ) ? $_POST['delimiter'] : ';';
    if ( $delimiter === '\t' ) {
        $delimiter = "\t";
    }

    $map_title     = isset( $_POST['map_title'] ) ? sanitize_text_field( $_POST['map_title'] ) : '';
    $map_lat       = isset( $_POST['map_lat'] ) ? sanitize_text_field( $_POST['map_lat'] ) : '';
    $map_lng       = isset( $_POST['map_lng'] ) ? sanitize_text_field( $_POST['map_lng'] ) : '';
    $map_meetup    = isset( $_POST['map_meetup'] ) ? sanitize_text_field( $_POST['map_meetup'] ) : '';
    $map_next_date = isset( $_POST['map_next_date'] ) ? sanitize_text_field( $_POST['map_next_date'] ) : '';
    $map_chat_url  = isset( $_POST['map_chat_url'] ) ? sanitize_text_field( $_POST['map_chat_url'] ) : '';

    $assembly_type      = isset( $_POST['assembly_type'] ) ? sanitize_text_field( $_POST['assembly_type'] ) : 'prep';
    $type_column        = isset( $_POST['type_column'] ) ? sanitize_text_field( $_POST['type_column'] ) : '';
    $post_status        = ( isset( $_POST['post_status'] ) && in_array( $_POST['post_status'], array( 'publish', 'draft' ), true ) ) ? $_POST['post_status'] : 'publish';
    $duplicate_handling = isset( $_POST['duplicate_handling'] ) ? sanitize_text_field( $_POST['duplicate_handling'] ) : 'update';

    if ( empty( $map_title ) || empty( $map_lat ) || empty( $map_lng ) ) {
        echo '<div class="notice notice-error"><p>' . esc_html__( 'Required field mappings (Title, Latitude, Longitude) were missing.', '101-assemblies-import' ) . '</p></div>';
        return;
    }

    // Process CSV
    $handle = fopen( $file_path, 'r' );
    if ( false === $handle ) {
        echo '<div class="notice notice-error"><p>' . esc_html__( 'Could not open CSV file for processing.', '101-assemblies-import' ) . '</p></div>';
        return;
    }

    // Skip BOM if present
    $bom = fread( $handle, 3 );
    if ( $bom !== "\xEF\xBB\xBF" ) {
        rewind( $handle );
    }

    $headers = fgetcsv( $handle, 0, $delimiter );
    if ( ! $headers ) {
        fclose( $handle );
        echo '<div class="notice notice-error"><p>' . esc_html__( 'Empty CSV file.', '101-assemblies-import' ) . '</p></div>';
        return;
    }

    $header_map = array();
    foreach ( $headers as $idx => $header_name ) {
        $header_map[ trim( $header_name ) ] = $idx;
    }

    $idx_title     = isset( $header_map[ $map_title ] ) ? $header_map[ $map_title ] : null;
    $idx_lat       = isset( $header_map[ $map_lat ] ) ? $header_map[ $map_lat ] : null;
    $idx_lng       = isset( $header_map[ $map_lng ] ) ? $header_map[ $map_lng ] : null;
    $idx_meetup    = ( ! empty( $map_meetup ) && isset( $header_map[ $map_meetup ] ) ) ? $header_map[ $map_meetup ] : null;
    $idx_next_date = ( ! empty( $map_next_date ) && isset( $header_map[ $map_next_date ] ) ) ? $header_map[ $map_next_date ] : null;
    $idx_chat_url  = ( ! empty( $map_chat_url ) && isset( $header_map[ $map_chat_url ] ) ) ? $header_map[ $map_chat_url ] : null;
    $idx_type      = ( $assembly_type === 'from_column' && ! empty( $type_column ) && isset( $header_map[ $type_column ] ) ) ? $header_map[ $type_column ] : null;

    $stats = array(
        'total'   => 0,
        'created' => 0,
        'updated' => 0,
        'skipped' => 0,
        'invalid' => 0,
    );

    $results_log = array();

    // Cache existing mobilization_hub posts by lowercased title for fast lookups
    $existing_posts = array();
    $existing_query = get_posts( array(
        'post_type'      => 'mobilization_hub',
        'posts_per_page' => -1,
        'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
        'fields'         => 'ids',
    ) );
    foreach ( $existing_query as $p_id ) {
        $p_title = get_the_title( $p_id );
        $existing_posts[ mb_strtolower( trim( $p_title ) ) ] = $p_id;
    }

    while ( ( $row = fgetcsv( $handle, 0, $delimiter ) ) !== false ) {
        $stats['total']++;

        $title = ( null !== $idx_title && isset( $row[ $idx_title ] ) ) ? sanitize_text_field( trim( $row[ $idx_title ] ) ) : '';
        $raw_lat = ( null !== $idx_lat && isset( $row[ $idx_lat ] ) ) ? trim( $row[ $idx_lat ] ) : '';
        $raw_lng = ( null !== $idx_lng && isset( $row[ $idx_lng ] ) ) ? trim( $row[ $idx_lng ] ) : '';

        // Standardize decimals (replace comma with dot)
        $lat = str_replace( ',', '.', $raw_lat );
        $lng = str_replace( ',', '.', $raw_lng );

        if ( empty( $title ) || ! is_numeric( $lat ) || ! is_numeric( $lng ) || abs( (float) $lat ) > 90 || abs( (float) $lng ) > 180 ) {
            $stats['invalid']++;
            $results_log[] = array(
                'title'  => $title ?: __( '(Untitled)', '101-assemblies-import' ),
                'lat'    => $raw_lat,
                'lng'    => $raw_lng,
                'type'   => '-',
                'status' => 'error',
                'msg'    => __( 'Invalid title or coordinates', '101-assemblies-import' ),
            );
            continue;
        }

        // Determine assembly type
        $row_type = 'prep';
        if ( $assembly_type === 'from_column' && null !== $idx_type && isset( $row[ $idx_type ] ) ) {
            $val = strtolower( trim( $row[ $idx_type ] ) );
            if ( in_array( $val, array( 'prep', 'confirmed', 'capital', 'event' ), true ) ) {
                $row_type = $val;
            } elseif ( strpos( $val, 'capital' ) !== false ) {
                $row_type = 'capital';
            } elseif ( strpos( $val, 'event' ) !== false || strpos( $val, 'talk' ) !== false || strpos( $val, 'training' ) !== false ) {
                $row_type = 'event';
            } elseif ( strpos( $val, 'confirm' ) !== false ) {
                $row_type = 'confirmed';
            } else {
                $row_type = 'prep';
            }
        } else {
            $row_type = in_array( $assembly_type, array( 'prep', 'confirmed', 'capital', 'event' ), true ) ? $assembly_type : 'prep';
        }

        $meetup    = ( null !== $idx_meetup && isset( $row[ $idx_meetup ] ) ) ? sanitize_text_field( trim( $row[ $idx_meetup ] ) ) : '';
        $next_date = ( null !== $idx_next_date && isset( $row[ $idx_next_date ] ) ) ? sanitize_text_field( trim( $row[ $idx_next_date ] ) ) : '';
        $chat_url  = ( null !== $idx_chat_url && isset( $row[ $idx_chat_url ] ) ) ? esc_url_raw( trim( $row[ $idx_chat_url ] ) ) : '';

        $title_key = mb_strtolower( $title );
        $existing_id = isset( $existing_posts[ $title_key ] ) ? $existing_posts[ $title_key ] : 0;

        if ( $existing_id && 'skip' === $duplicate_handling ) {
            $stats['skipped']++;
            $results_log[] = array(
                'title'   => $title,
                'lat'     => $lat,
                'lng'     => $lng,
                'type'    => $row_type,
                'status'  => 'skipped',
                'post_id' => $existing_id,
                'msg'     => __( 'Skipped (Duplicate)', '101-assemblies-import' ),
            );
            continue;
        }

        if ( $existing_id && 'update' === $duplicate_handling ) {
            // Update existing post
            wp_update_post( array(
                'ID'          => $existing_id,
                'post_status' => $post_status,
            ) );

            update_post_meta( $existing_id, '_hub_lat', $lat );
            update_post_meta( $existing_id, '_hub_lng', $lng );
            update_post_meta( $existing_id, '_hub_type', $row_type );
            if ( ! empty( $meetup ) ) {
                update_post_meta( $existing_id, '_hub_meetup', $meetup );
            }
            if ( ! empty( $next_date ) ) {
                update_post_meta( $existing_id, '_hub_next_date', $next_date );
            }
            if ( ! empty( $chat_url ) ) {
                update_post_meta( $existing_id, '_hub_chat_url', $chat_url );
            }

            $stats['updated']++;
            $results_log[] = array(
                'title'   => $title,
                'lat'     => $lat,
                'lng'     => $lng,
                'type'    => $row_type,
                'status'  => 'updated',
                'post_id' => $existing_id,
                'msg'     => __( 'Updated', '101-assemblies-import' ),
            );
        } else {
            // Create new post
            $new_post_id = wp_insert_post( array(
                'post_title'  => $title,
                'post_type'   => 'mobilization_hub',
                'post_status' => $post_status,
            ) );

            if ( ! is_wp_error( $new_post_id ) ) {
                update_post_meta( $new_post_id, '_hub_lat', $lat );
                update_post_meta( $new_post_id, '_hub_lng', $lng );
                update_post_meta( $new_post_id, '_hub_type', $row_type );
                if ( ! empty( $meetup ) ) {
                    update_post_meta( $new_post_id, '_hub_meetup', $meetup );
                }
                if ( ! empty( $next_date ) ) {
                    update_post_meta( $new_post_id, '_hub_next_date', $next_date );
                }
                if ( ! empty( $chat_url ) ) {
                    update_post_meta( $new_post_id, '_hub_chat_url', $chat_url );
                }

                $existing_posts[ $title_key ] = $new_post_id;
                $stats['created']++;
                $results_log[] = array(
                    'title'   => $title,
                    'lat'     => $lat,
                    'lng'     => $lng,
                    'type'    => $row_type,
                    'status'  => 'created',
                    'post_id' => $new_post_id,
                    'msg'     => __( 'Created', '101-assemblies-import' ),
                );
            } else {
                $stats['invalid']++;
                $results_log[] = array(
                    'title'  => $title,
                    'lat'    => $lat,
                    'lng'    => $lng,
                    'type'   => $row_type,
                    'status' => 'error',
                    'msg'    => $new_post_id->get_error_message(),
                );
            }
        }
    }

    fclose( $handle );

    // Clean up temporary file
    @unlink( $file_path );
    ?>
    <div style="background:#fff;border:1px solid #cbd5e1;border-radius:12px;padding:28px;box-shadow:0 4px 6px -1px rgba(0,0,0,0.05);margin-top:15px;">
        <div style="margin-bottom:24px;">
            <h2 style="margin:0 0 6px;font-size:20px;font-weight:800;color:#0f172a;">🎉 <?php esc_html_e( 'Import Completed Successfully!', '101-assemblies-import' ); ?></h2>
            <p style="margin:0;color:#64748b;font-size:14px;">
                <?php esc_html_e( 'Your CSV data has been processed and saved into the Assemblies database.', '101-assemblies-import' ); ?>
            </p>
        </div>

        <!-- Metric Badges -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin-bottom:28px;">
            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:16px;text-align:center;">
                <div style="font-size:24px;font-weight:800;color:#0f172a;"><?php echo esc_html( $stats['total'] ); ?></div>
                <div style="font-size:12.5px;color:#64748b;font-weight:600;text-transform:uppercase;margin-top:2px;"><?php esc_html_e( 'Total Processed', '101-assemblies-import' ); ?></div>
            </div>

            <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:16px;text-align:center;">
                <div style="font-size:24px;font-weight:800;color:#16a34a;"><?php echo esc_html( $stats['created'] ); ?></div>
                <div style="font-size:12.5px;color:#15803d;font-weight:600;text-transform:uppercase;margin-top:2px;"><?php esc_html_e( 'New Assemblies Created', '101-assemblies-import' ); ?></div>
            </div>

            <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:16px;text-align:center;">
                <div style="font-size:24px;font-weight:800;color:#2563eb;"><?php echo esc_html( $stats['updated'] ); ?></div>
                <div style="font-size:12.5px;color:#1d4ed8;font-weight:600;text-transform:uppercase;margin-top:2px;"><?php esc_html_e( 'Existing Updated', '101-assemblies-import' ); ?></div>
            </div>

            <?php if ( $stats['skipped'] > 0 ) : ?>
                <div style="background:#fefce8;border:1px solid #fef08a;border-radius:10px;padding:16px;text-align:center;">
                    <div style="font-size:24px;font-weight:800;color:#ca8a04;"><?php echo esc_html( $stats['skipped'] ); ?></div>
                    <div style="font-size:12.5px;color:#a16207;font-weight:600;text-transform:uppercase;margin-top:2px;"><?php esc_html_e( 'Skipped (Duplicates)', '101-assemblies-import' ); ?></div>
                </div>
            <?php endif; ?>

            <?php if ( $stats['invalid'] > 0 ) : ?>
                <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:16px;text-align:center;">
                    <div style="font-size:24px;font-weight:800;color:#dc2626;"><?php echo esc_html( $stats['invalid'] ); ?></div>
                    <div style="font-size:12.5px;color:#b91c1c;font-weight:600;text-transform:uppercase;margin-top:2px;"><?php esc_html_e( 'Invalid / Errors', '101-assemblies-import' ); ?></div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Results Table -->
        <div style="margin-bottom:28px;">
            <h3 style="margin:0 0 10px;font-size:15px;font-weight:700;color:#0f172a;"><?php esc_html_e( 'Import Log &amp; Results:', '101-assemblies-import' ); ?></h3>
            <div style="overflow-x:auto;max-height:350px;border:1px solid #cbd5e1;border-radius:8px;">
                <table class="widefat striped" style="border:none;font-size:13px;">
                    <thead>
                        <tr style="background:#f1f5f9;">
                            <th style="font-weight:700;"><?php esc_html_e( 'Assembly Name / Title', '101-assemblies-import' ); ?></th>
                            <th style="font-weight:700;"><?php esc_html_e( 'Coordinates (Lat / Lng)', '101-assemblies-import' ); ?></th>
                            <th style="font-weight:700;"><?php esc_html_e( 'Assigned Type', '101-assemblies-import' ); ?></th>
                            <th style="font-weight:700;"><?php esc_html_e( 'Result', '101-assemblies-import' ); ?></th>
                            <th style="font-weight:700;"><?php esc_html_e( 'Actions', '101-assemblies-import' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $type_badges = array(
                            'prep'      => array( 'bg' => '#fef3c7', 'color' => '#92400e', 'dot' => '#f59e0b', 'label' => __( 'Preparation Meeting', '101-assemblies-import' ) ),
                            'confirmed' => array( 'bg' => '#dcfce7', 'color' => '#166534', 'dot' => '#16a34a', 'label' => __( 'Confirmed Assembly', '101-assemblies-import' ) ),
                            'capital'   => array( 'bg' => '#ffedd5', 'color' => '#c2410c', 'dot' => '#ea580c', 'label' => __( 'Capital Convergence', '101-assemblies-import' ) ),
                            'event'     => array( 'bg' => '#f3e8ff', 'color' => '#6b21a8', 'dot' => '#8b5cf6', 'label' => __( 'Talk / Event', '101-assemblies-import' ) ),
                        );

                        foreach ( $results_log as $entry ) :
                            $type_info = isset( $type_badges[ $entry['type'] ] ) ? $type_badges[ $entry['type'] ] : null;
                            ?>
                            <tr>
                                <td style="font-weight:600;color:#0f172a;"><?php echo esc_html( $entry['title'] ); ?></td>
                                <td style="color:#64748b;font-family:monospace;"><?php echo esc_html( $entry['lat'] . ', ' . $entry['lng'] ); ?></td>
                                <td>
                                    <?php if ( $type_info ) : ?>
                                        <span style="display:inline-flex;align-items:center;gap:6px;padding:3px 8px;border-radius:99px;font-size:12px;font-weight:700;background:<?php echo esc_attr( $type_info['bg'] ); ?>;color:<?php echo esc_attr( $type_info['color'] ); ?>;">
                                            <span style="width:8px;height:8px;border-radius:50%;background:<?php echo esc_attr( $type_info['dot'] ); ?>;"></span>
                                            <?php echo esc_html( $type_info['label'] ); ?>
                                        </span>
                                    <?php else : ?>
                                        <span>-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ( 'created' === $entry['status'] ) : ?>
                                        <span style="color:#16a34a;font-weight:700;">✅ <?php echo esc_html( $entry['msg'] ); ?></span>
                                    <?php elseif ( 'updated' === $entry['status'] ) : ?>
                                        <span style="color:#2563eb;font-weight:700;">🔄 <?php echo esc_html( $entry['msg'] ); ?></span>
                                    <?php elseif ( 'skipped' === $entry['status'] ) : ?>
                                        <span style="color:#ca8a04;font-weight:700;">⚠️ <?php echo esc_html( $entry['msg'] ); ?></span>
                                    <?php else : ?>
                                        <span style="color:#dc2626;font-weight:700;">❌ <?php echo esc_html( $entry['msg'] ); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ( ! empty( $entry['post_id'] ) ) : ?>
                                        <a href="<?php echo esc_url( get_edit_post_link( $entry['post_id'] ) ); ?>" target="_blank" style="font-size:12px;font-weight:600;">
                                            <?php esc_html_e( 'Edit Post ↗', '101-assemblies-import' ); ?>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Action Links -->
        <div style="display:flex;gap:12px;flex-wrap:wrap;">
            <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=mobilization_hub' ) ); ?>" class="button button-primary" style="font-weight:700;padding:6px 18px;height:auto;">
                <?php esc_html_e( 'View All Assemblies (WordPress Admin) →', '101-assemblies-import' ); ?>
            </a>
            <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=mobilization_hub&page=101-assemblies-import' ) ); ?>" class="button button-secondary" style="padding:6px 16px;height:auto;">
                📥 <?php esc_html_e( 'Import Another CSV File', '101-assemblies-import' ); ?>
            </a>
        </div>
    </div>
    <?php
}
