<?php
/**
 * Handles bulk observer import from CSV files exported from Excel.
 */

class PollMonitor_Observer_Importer {

    public static function import_csv( $file_path ) {
        $result = array(
            'headers' => array(),
            'created' => 0,
            'updated' => 0,
            'errors'  => 0,
            'rows'    => array(),
        );

        $handle = fopen( $file_path, 'r' );
        if ( ! $handle ) {
            return new WP_Error( 'import_open_failed', 'Unable to open the uploaded CSV file.' );
        }

        $headers = fgetcsv( $handle );
        if ( false === $headers || empty( $headers ) ) {
            fclose( $handle );
            return new WP_Error( 'import_empty', 'The CSV file is empty or has no header row.' );
        }

        $normalized_headers = array_map( array( __CLASS__, 'normalize_header' ), $headers );
        $result['headers'] = $normalized_headers;

        if ( ! in_array( 'email', $normalized_headers, true ) ) {
            fclose( $handle );
            return new WP_Error( 'missing_email_column', 'The CSV must include an email column.' );
        }

        $row_number = 1;
        while ( false !== ( $row = fgetcsv( $handle ) ) ) {
            $row_number++;

            if ( self::row_is_empty( $row ) ) {
                continue;
            }

            $assoc = array();
            foreach ( $normalized_headers as $index => $header ) {
                if ( '' === $header ) {
                    continue;
                }

                $assoc[ $header ] = isset( $row[ $index ] ) ? trim( $row[ $index ] ) : '';
            }

            $row_result = self::import_row( $assoc, $row_number );
            $result['rows'][] = $row_result;

            if ( 'created' === $row_result['status'] ) {
                $result['created']++;
            } elseif ( 'updated' === $row_result['status'] ) {
                $result['updated']++;
            } else {
                $result['errors']++;
            }
        }

        fclose( $handle );
        return $result;
    }

    protected static function import_row( $row, $row_number ) {
        $email = isset( $row['email'] ) ? sanitize_email( $row['email'] ) : '';
        if ( empty( $email ) || ! is_email( $email ) ) {
            return self::build_row_result( $row_number, 'error', 'Invalid or missing email address.' );
        }

        $user = get_user_by( 'email', $email );
        $is_new_user = ! $user;

        $username = self::determine_username( $row, $email, $is_new_user ? 0 : $user->ID );
        if ( empty( $username ) ) {
            return self::build_row_result( $row_number, 'error', 'Unable to determine a valid username.', $email );
        }

        $name_parts = self::extract_name_parts( $row );
        $assigned_station_ids = self::parse_station_ids_from_row( $row );
        $observer_id = self::determine_observer_id( $row, $is_new_user ? 0 : $user->ID );
        $phone = self::get_first_non_empty( $row, array( 'phone', 'phone_number', 'mobile' ) );

        if ( $is_new_user ) {
            $password = self::get_first_non_empty( $row, array( 'password' ) );
            if ( empty( $password ) ) {
                $password = wp_generate_password( 12, true, true );
            }

            $user_id = wp_insert_user(
                array(
                    'user_login'   => $username,
                    'user_pass'    => $password,
                    'user_email'   => $email,
                    'display_name' => $name_parts['display_name'],
                    'first_name'   => $name_parts['first_name'],
                    'last_name'    => $name_parts['last_name'],
                    'role'         => 'pollmonitor_observer',
                )
            );

            if ( is_wp_error( $user_id ) ) {
                return self::build_row_result( $row_number, 'error', $user_id->get_error_message(), $email );
            }

            $user = get_user_by( 'id', $user_id );
            $status = 'created';
            $generated_password = $password;
        } else {
            $user_id = $user->ID;

            $update_data = array(
                'ID'           => $user_id,
                'user_email'   => $email,
                'display_name' => $name_parts['display_name'],
                'first_name'   => $name_parts['first_name'],
                'last_name'    => $name_parts['last_name'],
            );

            if ( ! empty( $username ) && $username !== $user->user_login && ! username_exists( $username ) ) {
                $update_data['user_login'] = $username;
            }

            $updated = wp_update_user( $update_data );
            if ( is_wp_error( $updated ) ) {
                return self::build_row_result( $row_number, 'error', $updated->get_error_message(), $email );
            }

            if ( ! user_can( $user_id, 'manage_options' ) && ! user_can( $user_id, 'pollmonitor_validate' ) ) {
                $wp_user = new WP_User( $user_id );
                $wp_user->set_role( 'pollmonitor_observer' );
            }

            $status = 'updated';
            $generated_password = '';
        }

        update_user_meta( $user_id, 'pollmonitor_observer_id', $observer_id );
        update_user_meta( $user_id, 'pollmonitor_assigned_station_ids', $assigned_station_ids );
        if ( '' !== $phone ) {
            update_user_meta( $user_id, 'pollmonitor_phone', sanitize_text_field( $phone ) );
        }

        if ( class_exists( 'PollMonitor_DB' ) ) {
            PollMonitor_DB::log_action(
                'observer_import_' . $status,
                get_current_user_id(),
                $user_id,
                array(
                    'email'        => $email,
                    'observer_id'  => $observer_id,
                    'station_ids'  => $assigned_station_ids,
                )
            );
        }

        return array(
            'row_number'            => $row_number,
            'status'                => $status,
            'message'               => ucfirst( $status ) . ' observer successfully.',
            'email'                 => $email,
            'username'              => $username,
            'observer_id'           => $observer_id,
            'assigned_station_ids'  => $assigned_station_ids,
            'password'              => $generated_password,
        );
    }

    public static function generate_observer_id() {
        $sequence = (int) get_option( 'pollmonitor_observer_sequence', 0 );

        do {
            $sequence++;
            $candidate = sprintf( 'PM-OBS-%05d', $sequence );
        } while ( self::observer_id_exists( $candidate ) );

        update_option( 'pollmonitor_observer_sequence', $sequence, false );
        return $candidate;
    }

    protected static function determine_observer_id( $row, $user_id ) {
        $provided = sanitize_text_field( self::get_first_non_empty( $row, array( 'observer_id', 'observer_code' ) ) );
        if ( '' !== $provided ) {
            if ( self::observer_id_exists( $provided, $user_id ) ) {
                return self::generate_observer_id();
            }

            return $provided;
        }

        if ( $user_id ) {
            $existing = get_user_meta( $user_id, 'pollmonitor_observer_id', true );
            if ( ! empty( $existing ) ) {
                return $existing;
            }
        }

        return self::generate_observer_id();
    }

    protected static function observer_id_exists( $observer_id, $exclude_user_id = 0 ) {
        $users = get_users(
            array(
                'meta_key'    => 'pollmonitor_observer_id',
                'meta_value'  => $observer_id,
                'number'      => 1,
                'fields'      => 'ids',
                'exclude'     => $exclude_user_id ? array( (int) $exclude_user_id ) : array(),
            )
        );

        return ! empty( $users );
    }

    protected static function determine_username( $row, $email, $existing_user_id ) {
        $provided = self::get_first_non_empty( $row, array( 'username', 'user_login' ) );
        if ( ! empty( $provided ) ) {
            $provided = sanitize_user( $provided, true );
            if ( ! empty( $provided ) ) {
                if ( ! username_exists( $provided ) ) {
                    return $provided;
                }

                if ( $existing_user_id ) {
                    $user = get_user_by( 'login', $provided );
                    if ( $user && (int) $user->ID === (int) $existing_user_id ) {
                        return $provided;
                    }
                }
            }
        }

        if ( $existing_user_id ) {
            $user = get_user_by( 'id', $existing_user_id );
            if ( $user ) {
                return $user->user_login;
            }
        }

        $base = sanitize_user( current( explode( '@', $email ) ), true );
        if ( empty( $base ) ) {
            $base = 'observer';
        }

        return self::make_unique_username( $base );
    }

    protected static function extract_name_parts( $row ) {
        $full_name = self::get_first_non_empty( $row, array( 'full_name', 'name', 'display_name' ) );
        $first_name = self::get_first_non_empty( $row, array( 'first_name', 'firstname' ) );
        $last_name = self::get_first_non_empty( $row, array( 'last_name', 'lastname', 'surname' ) );

        if ( empty( $first_name ) && empty( $last_name ) && ! empty( $full_name ) ) {
            $parts = preg_split( '/\s+/', trim( $full_name ) );
            $first_name = array_shift( $parts );
            $last_name = implode( ' ', $parts );
        }

        if ( empty( $full_name ) ) {
            $full_name = trim( $first_name . ' ' . $last_name );
        }

        return array(
            'display_name' => ! empty( $full_name ) ? sanitize_text_field( $full_name ) : sanitize_text_field( $first_name ),
            'first_name'   => sanitize_text_field( $first_name ),
            'last_name'    => sanitize_text_field( $last_name ),
        );
    }

    protected static function parse_station_ids_from_row( $row ) {
        $raw = self::get_first_non_empty( $row, array( 'assigned_station_ids', 'station_ids', 'poll_station_ids', 'assigned_stations' ) );
        if ( '' === $raw ) {
            return array();
        }

        $pieces = preg_split( '/[\s,;|]+/', $raw );
        $station_ids = array();

        foreach ( $pieces as $piece ) {
            $station_id = intval( $piece );
            if ( $station_id > 0 && 'poll_station' === get_post_type( $station_id ) ) {
                $station_ids[] = $station_id;
            }
        }

        return array_values( array_unique( $station_ids ) );
    }

    protected static function get_first_non_empty( $row, $keys ) {
        foreach ( $keys as $key ) {
            if ( isset( $row[ $key ] ) && '' !== trim( (string) $row[ $key ] ) ) {
                return trim( (string) $row[ $key ] );
            }
        }

        return '';
    }

    protected static function normalize_header( $header ) {
        $header = strtolower( trim( (string) $header ) );
        $header = preg_replace( '/[^a-z0-9]+/', '_', $header );
        return trim( $header, '_' );
    }

    protected static function row_is_empty( $row ) {
        foreach ( $row as $value ) {
            if ( '' !== trim( (string) $value ) ) {
                return false;
            }
        }

        return true;
    }

    protected static function build_row_result( $row_number, $status, $message, $email = '' ) {
        return array(
            'row_number'           => $row_number,
            'status'               => $status,
            'message'              => $message,
            'email'                => $email,
            'username'             => '',
            'observer_id'          => '',
            'assigned_station_ids' => array(),
            'password'             => '',
        );
    }

    protected static function make_unique_username( $base ) {
        $base = sanitize_user( $base, true );
        if ( empty( $base ) ) {
            $base = 'observer';
        }

        $candidate = $base;
        $suffix = 1;

        while ( username_exists( $candidate ) ) {
            $candidate = $base . $suffix;
            $suffix++;
        }

        return $candidate;
    }
}
