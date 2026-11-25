<?php
if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

/**
 * All database operations for WPYog Fleet — Booking Manager for Taxi, Cab & Travel Businesses.
 * Call WPYOGFLEET_DB::method() from anywhere.
 */
class WPYOGFLEET_DB {

    // -------------------------------------------------------------------------
    // BOOKING NUMBER
    // -------------------------------------------------------------------------

    public static function generate_booking_number() {
        global $wpdb;
        $year   = gmdate( 'Y' );
        $table  = $wpdb->prefix . 'jtb_bookings';
        $count  = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE YEAR(created_at) = %d", $year ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        );
        $prefix = WPYOGFLEET_Settings::get( WPYOGFLEET_Settings::BOOKING_PREFIX );
        return $prefix . '-' . $year . '-' . str_pad( $count + 1, 4, '0', STR_PAD_LEFT );
    }

    // -------------------------------------------------------------------------
    // VEHICLES
    // -------------------------------------------------------------------------

    public static function get_vehicles( $args = array() ) {
        global $wpdb;
        $table  = $wpdb->prefix . 'jtb_vehicles';
        $where  = 'WHERE 1=1';
        $params = array();

        if ( ! empty( $args['status'] ) ) {
            $where   .= ' AND status = %s';
            $params[] = $args['status'];
        }

        $sql = "SELECT * FROM {$table} {$where} ORDER BY vehicle_name ASC"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( $params ) {
            $sql = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }
        return $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    }

    public static function get_vehicle( $id ) {
        global $wpdb;
        return $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}jtb_vehicles WHERE id = %d", $id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        );
    }

    /**
     * Normalised key for comparing registration numbers:
     * upper-case with spaces, hyphens and dots removed ("mp 09-ab 1234" → "MP09AB1234").
     */
    public static function registration_key( $reg ) {
        return strtoupper( preg_replace( '/[\s\-.]+/', '', sanitize_text_field( (string) $reg ) ) );
    }

    /**
     * The matching id when another vehicle already uses this registration number.
     * Ignores case, spaces, hyphens and dots; [$exclude_id] lets an edit keep its own number.
     */
    public static function registration_number_exists( $reg, $exclude_id = 0 ) {
        global $wpdb;
        $key = self::registration_key( $reg );
        if ( '' === $key ) {
            return false;
        }
        $t  = $wpdb->prefix . 'jtb_vehicles';
        $id = $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            "SELECT id FROM {$t} WHERE UPPER(REPLACE(REPLACE(REPLACE(registration_number,' ',''),'-',''),'.','')) = %s AND id != %d LIMIT 1",
            $key,
            absint( $exclude_id )
        ) );
        return $id ? (int) $id : false;
    }

    /** Required + unique registration number check shared by insert and update. */
    private static function validate_vehicle( $data, $exclude_id = 0 ) {
        $reg = $data['registration_number'] ?? '';
        if ( '' === self::registration_key( $reg ) ) {
            return new WP_Error( 'missing_registration', 'Registration number is required.' );
        }
        if ( self::registration_number_exists( $reg, $exclude_id ) ) {
            return new WP_Error( 'duplicate_vehicle', 'A vehicle with this registration number already exists.' );
        }
        return true;
    }

    public static function insert_vehicle( $data ) {
        global $wpdb;
        $valid = self::validate_vehicle( $data );
        if ( is_wp_error( $valid ) ) {
            return $valid;
        }
        $wpdb->insert( $wpdb->prefix . 'jtb_vehicles', self::sanitize_vehicle( $data ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        return $wpdb->insert_id;
    }

    public static function update_vehicle( $id, $data ) {
        global $wpdb;
        $valid = self::validate_vehicle( $data, $id );
        if ( is_wp_error( $valid ) ) {
            return $valid;
        }
        return $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $wpdb->prefix . 'jtb_vehicles',
            self::sanitize_vehicle( $data ),
            array( 'id' => $id )
        );
    }

    public static function delete_vehicle( $id ) {
        global $wpdb;
        return $wpdb->delete( $wpdb->prefix . 'jtb_vehicles', array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    }

    private static function sanitize_vehicle( $data ) {
        $reg  = strtoupper( preg_replace( '/\s+/', ' ', trim( sanitize_text_field( $data['registration_number'] ?? '' ) ) ) );
        $name = sanitize_text_field( $data['vehicle_name'] ?? '' );
        return array(
            // Name is optional now; fall back to the registration number so lists/labels never show blank.
            'vehicle_name'        => '' !== $name ? $name : $reg,
            'vehicle_type'        => sanitize_text_field( $data['vehicle_type'] ?? '' ),
            'registration_number' => $reg,
            'owner_type'          => in_array( $data['owner_type'] ?? '', array( 'own', 'outsourced' ) ) ? $data['owner_type'] : 'own',
            'vendor_name'         => sanitize_text_field( $data['vendor_name'] ?? '' ),
            'vendor_mobile'       => sanitize_text_field( $data['vendor_mobile'] ?? '' ),
            'status'              => in_array( $data['status'] ?? '', array( 'active', 'inactive' ) ) ? $data['status'] : 'active',
            'notes'               => sanitize_textarea_field( $data['notes'] ?? '' ),
        );
    }

    // -------------------------------------------------------------------------
    // DRIVERS
    // -------------------------------------------------------------------------

    public static function get_drivers( $args = array() ) {
        global $wpdb;
        $table  = $wpdb->prefix . 'jtb_drivers';
        $where  = 'WHERE 1=1';
        $params = array();

        if ( ! empty( $args['status'] ) ) {
            $where   .= ' AND d.status = %s';
            $params[] = $args['status'];
        }

        $vtable = $wpdb->prefix . 'jtb_vehicles';
        $sql    = "SELECT d.*, v.vehicle_name FROM {$table} d
                   LEFT JOIN {$vtable} v ON v.id = d.vehicle_id
                   {$where}
                   ORDER BY d.driver_name ASC";
        if ( $params ) {
            $sql = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }
        return $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    }

    public static function get_driver( $id ) {
        global $wpdb;
        return $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}jtb_drivers WHERE id = %d", $id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        );
    }

    public static function insert_driver( $data ) {
        global $wpdb;
        $wpdb->insert( $wpdb->prefix . 'jtb_drivers', self::sanitize_driver( $data ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        return $wpdb->insert_id;
    }

    public static function update_driver( $id, $data ) {
        global $wpdb;
        return $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $wpdb->prefix . 'jtb_drivers',
            self::sanitize_driver( $data ),
            array( 'id' => $id )
        );
    }

    public static function delete_driver( $id ) {
        global $wpdb;
        return $wpdb->delete( $wpdb->prefix . 'jtb_drivers', array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    }

    private static function sanitize_driver( $data ) {
        return array(
            'driver_name' => sanitize_text_field( $data['driver_name'] ?? '' ),
            'mobile'      => sanitize_text_field( $data['mobile'] ?? '' ),
            'vehicle_id'  => ! empty( $data['vehicle_id'] ) ? absint( $data['vehicle_id'] ) : null,
            'driver_type' => in_array( $data['driver_type'] ?? '', array( 'own', 'outsourced' ) ) ? $data['driver_type'] : 'own',
            'status'      => in_array( $data['status'] ?? '', array( 'active', 'inactive' ) ) ? $data['status'] : 'active',
            'notes'       => sanitize_textarea_field( $data['notes'] ?? '' ),
        );
    }

    // -------------------------------------------------------------------------
    // COMPANIES (v2.7.0)
    // -------------------------------------------------------------------------

    public static function get_companies( $args = array() ) {
        global $wpdb;
        $table  = $wpdb->prefix . 'jtb_companies';
        $where  = 'WHERE 1=1';
        $params = array();

        if ( ! empty( $args['status'] ) ) {
            $where   .= ' AND status = %s';
            $params[] = $args['status'];
        }
        if ( ! empty( $args['search'] ) ) {
            $s        = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where   .= ' AND (company_name LIKE %s OR contact_name LIKE %s OR gst_number LIKE %s)';
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
        }

        $sql = "SELECT * FROM {$table} {$where} ORDER BY company_name ASC"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( $params ) {
            $sql = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }
        return $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    }

    public static function get_company( $id ) {
        global $wpdb;
        return $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}jtb_companies WHERE id = %d", $id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        );
    }

    /**
     * True (the matching id) when another company already uses this name.
     * Case-insensitive; [$exclude_id] lets an edit keep its own name.
     */
    public static function company_name_exists( $name, $exclude_id = 0 ) {
        global $wpdb;
        $name = trim( (string) $name );
        if ( '' === $name ) {
            return false;
        }
        $t  = $wpdb->prefix . 'jtb_companies';
        $id = $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            "SELECT id FROM {$t} WHERE LOWER(company_name) = LOWER(%s) AND id != %d LIMIT 1",
            $name,
            absint( $exclude_id )
        ) );
        return $id ? (int) $id : false;
    }

    public static function insert_company( $data ) {
        global $wpdb;
        if ( self::company_name_exists( $data['company_name'] ?? '' ) ) {
            return new WP_Error( 'duplicate_company', 'A company with this name already exists.' );
        }
        $row = self::sanitize_company( $data );
        $row['created_by'] = get_current_user_id();
        $wpdb->insert( $wpdb->prefix . 'jtb_companies', $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        return $wpdb->insert_id;
    }

    public static function update_company( $id, $data ) {
        global $wpdb;
        if ( self::company_name_exists( $data['company_name'] ?? '', $id ) ) {
            return new WP_Error( 'duplicate_company', 'A company with this name already exists.' );
        }
        return $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $wpdb->prefix . 'jtb_companies',
            self::sanitize_company( $data ),
            array( 'id' => $id )
        );
    }

    public static function delete_company( $id ) {
        global $wpdb;
        return $wpdb->delete( $wpdb->prefix . 'jtb_companies', array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    }

    private static function sanitize_company( $data ) {
        return array(
            'company_name'   => sanitize_text_field( $data['company_name'] ?? '' ),
            'contact_name'   => sanitize_text_field( $data['contact_name'] ?? '' ),
            'contact_mobile' => sanitize_text_field( $data['contact_mobile'] ?? '' ),
            'contact_email'  => sanitize_email( $data['contact_email'] ?? '' ),
            'gst_number'     => sanitize_text_field( $data['gst_number'] ?? '' ),
            'address'        => sanitize_textarea_field( $data['address'] ?? '' ),
            'notes'          => sanitize_textarea_field( $data['notes'] ?? '' ),
            'status'         => in_array( $data['status'] ?? '', array( 'active', 'inactive' ) ) ? $data['status'] : 'active',
        );
    }

    // -------------------------------------------------------------------------
    // BOOKINGS
    // -------------------------------------------------------------------------

    public static function get_bookings( $args = array() ) {
        global $wpdb;
        $bt  = $wpdb->prefix . 'jtb_bookings';
        $vt  = $wpdb->prefix . 'jtb_vehicles';
        $dt  = $wpdb->prefix . 'jtb_drivers';
        $pt  = $wpdb->prefix . 'jtb_payments';
        $et  = $wpdb->prefix . 'jtb_expenses';
        $ct  = $wpdb->prefix . 'jtb_companies';

        $params = array();

        // 'archived' is a virtual status — shows soft-deleted bookings
        if ( ! empty( $args['status'] ) && $args['status'] === 'archived' ) {
            $where = 'WHERE b.is_deleted = 1';
        } else {
            $where = 'WHERE b.is_deleted = 0';
            if ( ! empty( $args['status'] ) ) {
                $where   .= ' AND b.status = %s';
                $params[] = $args['status'];
            }
        }
        if ( ! empty( $args['vehicle_id'] ) ) {
            $where   .= ' AND b.vehicle_id = %d';
            $params[] = absint( $args['vehicle_id'] );
        }
        if ( ! empty( $args['driver_id'] ) ) {
            $where   .= ' AND b.driver_id = %d';
            $params[] = absint( $args['driver_id'] );
        }
        if ( ! empty( $args['customer_mobile'] ) ) {
            $where   .= ' AND b.customer_mobile LIKE %s';
            $params[] = '%' . $wpdb->esc_like( $args['customer_mobile'] ) . '%';
        }
        if ( ! empty( $args['date_from'] ) ) {
            $where   .= ' AND DATE(b.start_date) >= %s';
            $params[] = $args['date_from'];
        }
        if ( ! empty( $args['date_to'] ) ) {
            $where   .= ' AND DATE(b.start_date) <= %s';
            $params[] = $args['date_to'];
        }
        if ( ! empty( $args['search'] ) ) {
            $s        = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where   .= ' AND (b.customer_name LIKE %s OR b.customer_mobile LIKE %s OR b.booking_number LIKE %s OR c.company_name LIKE %s)';
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
        }
        if ( isset( $args['pending_only'] ) && $args['pending_only'] ) {
            $where .= ' AND (b.booking_amount + b.gst_amount) > COALESCE(p.total_paid,0)';
        }

        $limit  = ! empty( $args['limit'] ) ? absint( $args['limit'] ) : 500;
        $offset = ! empty( $args['offset'] ) ? absint( $args['offset'] ) : 0;

        // Sort — whitelisted so it's safe to interpolate directly.
        $orderby_map = array( 'id' => 'b.id', 'date' => 'b.start_date' );
        $orderby_key = $orderby_map[ $args['orderby'] ?? 'date' ] ?? 'b.start_date';
        $order_dir   = strtoupper( $args['order'] ?? 'DESC' ) === 'ASC' ? 'ASC' : 'DESC';
        $order_sql   = "{$orderby_key} {$order_dir}";

        $sql = "SELECT b.*,
                    v.vehicle_name, v.registration_number,
                    d.driver_name, d.mobile AS driver_mobile,
                    c.company_name,
                    COALESCE(p.total_paid, 0)  AS total_paid,
                    COALESCE(e.total_expenses, 0) AS total_expenses,
                    (b.booking_amount + b.gst_amount - COALESCE(p.total_paid, 0)) AS balance_amount
                FROM {$bt} b
                LEFT JOIN {$vt} v ON v.id = b.vehicle_id
                LEFT JOIN {$dt} d ON d.id = b.driver_id
                LEFT JOIN {$ct} c ON c.id = b.company_id
                LEFT JOIN (
                    SELECT booking_id, SUM(amount) AS total_paid
                    FROM {$pt} WHERE is_voided = 0
                    GROUP BY booking_id
                ) p ON p.booking_id = b.id
                LEFT JOIN (
                    SELECT booking_id, SUM(amount) AS total_expenses
                    FROM {$et} WHERE is_voided = 0
                    GROUP BY booking_id
                ) e ON e.booking_id = b.id
                {$where}
                ORDER BY {$order_sql}
                LIMIT %d OFFSET %d";

        $params[] = $limit;
        $params[] = $offset;

        return $wpdb->get_results( $wpdb->prepare( $sql, $params ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
    }

    /**
     * Count bookings matching the same filters as get_bookings().
     * Used for pagination.
     */
    public static function count_bookings_filtered( $args = array() ) {
        global $wpdb;
        $bt  = $wpdb->prefix . 'jtb_bookings';
        $pt  = $wpdb->prefix . 'jtb_payments';
        $ct  = $wpdb->prefix . 'jtb_companies';

        $params = array();

        if ( ! empty( $args['status'] ) && $args['status'] === 'archived' ) {
            $where = 'WHERE b.is_deleted = 1';
        } else {
            $where = 'WHERE b.is_deleted = 0';
            if ( ! empty( $args['status'] ) ) {
                $where   .= ' AND b.status = %s';
                $params[] = $args['status'];
            }
        }
        if ( ! empty( $args['vehicle_id'] ) ) {
            $where   .= ' AND b.vehicle_id = %d';
            $params[] = absint( $args['vehicle_id'] );
        }
        if ( ! empty( $args['driver_id'] ) ) {
            $where   .= ' AND b.driver_id = %d';
            $params[] = absint( $args['driver_id'] );
        }
        if ( ! empty( $args['customer_mobile'] ) ) {
            $where   .= ' AND b.customer_mobile LIKE %s';
            $params[] = '%' . $wpdb->esc_like( $args['customer_mobile'] ) . '%';
        }
        if ( ! empty( $args['date_from'] ) ) {
            $where   .= ' AND DATE(b.start_date) >= %s';
            $params[] = $args['date_from'];
        }
        if ( ! empty( $args['date_to'] ) ) {
            $where   .= ' AND DATE(b.start_date) <= %s';
            $params[] = $args['date_to'];
        }
        if ( ! empty( $args['search'] ) ) {
            $s        = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where   .= ' AND (b.customer_name LIKE %s OR b.customer_mobile LIKE %s OR b.booking_number LIKE %s OR c.company_name LIKE %s)';
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
        }

        $needs_payments_join = isset( $args['pending_only'] ) && $args['pending_only'];

        if ( $needs_payments_join ) {
            $sql = "SELECT COUNT(DISTINCT b.id)
                    FROM {$bt} b
                    LEFT JOIN {$ct} c ON c.id = b.company_id
                    LEFT JOIN (SELECT booking_id, SUM(amount) AS total_paid FROM {$pt} WHERE is_voided = 0 GROUP BY booking_id) p ON p.booking_id = b.id
                    {$where} AND (b.booking_amount + b.gst_amount) > COALESCE(p.total_paid,0)";
        } else {
            $sql = "SELECT COUNT(DISTINCT b.id)
                    FROM {$bt} b
                    LEFT JOIN {$ct} c ON c.id = b.company_id
                    {$where}";
        }

        if ( $params ) {
            $sql = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }
        return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    }

    public static function count_bookings( $args = array() ) {
        global $wpdb;
        $bt    = $wpdb->prefix . 'jtb_bookings';
        $where = 'WHERE is_deleted = 0';
        $p     = array();

        if ( ! empty( $args['status'] ) ) { $where .= ' AND status = %s'; $p[] = $args['status']; }
        if ( ! empty( $args['date_from'] ) ) { $where .= ' AND DATE(start_date) >= %s'; $p[] = $args['date_from']; }
        if ( ! empty( $args['date_to'] ) ) { $where .= ' AND DATE(start_date) <= %s'; $p[] = $args['date_to']; }

        $sql = "SELECT COUNT(*) FROM {$bt} {$where}"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( $p ) $sql = $wpdb->prepare( $sql, $p ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    }

    public static function get_booking( $id ) {
        global $wpdb;
        $bt = $wpdb->prefix . 'jtb_bookings';
        $vt = $wpdb->prefix . 'jtb_vehicles';
        $dt = $wpdb->prefix . 'jtb_drivers';
        $pt = $wpdb->prefix . 'jtb_payments';
        $et = $wpdb->prefix . 'jtb_expenses';
        $ct = $wpdb->prefix . 'jtb_companies';

        return $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            "SELECT b.*,
                v.vehicle_name, v.registration_number, v.owner_type as vehicle_owner_type,
                d.driver_name, d.mobile AS driver_mobile,
                c.company_name, c.contact_name AS company_contact_name, c.contact_mobile AS company_contact_mobile,
                COALESCE(p.total_paid, 0) AS total_paid,
                COALESCE(e.total_expenses, 0) AS total_expenses,
                (b.booking_amount + b.gst_amount - COALESCE(p.total_paid, 0)) AS balance_amount
             FROM {$bt} b
             LEFT JOIN {$vt} v ON v.id = b.vehicle_id
             LEFT JOIN {$dt} d ON d.id = b.driver_id
             LEFT JOIN {$ct} c ON c.id = b.company_id
             LEFT JOIN (SELECT booking_id, SUM(amount) AS total_paid FROM {$pt} WHERE is_voided = 0 GROUP BY booking_id) p ON p.booking_id = b.id
             LEFT JOIN (SELECT booking_id, SUM(amount) AS total_expenses FROM {$et} WHERE is_voided = 0 GROUP BY booking_id) e ON e.booking_id = b.id
             WHERE b.id = %d",
            $id
        ) );
    }

    public static function insert_booking( $data ) {
        global $wpdb;
        $row = self::sanitize_booking( $data );
        $row['booking_number'] = self::generate_booking_number();
        $row['created_by']     = get_current_user_id();
        // No advance date sent (older clients) → today, same as before.
        if ( $row['advance_amount'] > 0 && empty( $row['advance_date'] ) ) {
            $row['advance_date'] = current_time( 'Y-m-d' );
        }
        $wpdb->insert( $wpdb->prefix . 'jtb_bookings', $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $new_id = $wpdb->insert_id;

        // Auto-create a payment record for the advance amount so it appears
        // in Payment History and is counted in total_paid / balance_amount.
        // Its date is the day the advance was received.
        if ( $new_id && $row['advance_amount'] > 0 ) {
            $wpdb->insert( $wpdb->prefix . 'jtb_payments', array( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                'booking_id'      => $new_id,
                'payment_date'    => $row['advance_date'],
                'amount'          => $row['advance_amount'],
                'payment_mode'    => ! empty( $row['advance_payment_mode'] ) ? $row['advance_payment_mode'] : 'cash',
                'received_by'     => ! empty( $row['advance_received_by'] ) ? $row['advance_received_by'] : '',
                'transaction_ref' => '',
                'notes'           => 'Advance payment',
                'created_by'      => get_current_user_id(),
            ) );
        }

        return $new_id;
    }

    public static function update_booking( $id, $data ) {
        global $wpdb;
        $row    = self::sanitize_booking( $data );
        // Stored advance date before this save — used below to tell whether
        // staff actually changed it (vs. re-saving the booking unchanged).
        $old_advance_date = empty( $row['advance_date'] ) ? null : $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            "SELECT advance_date FROM {$wpdb->prefix}jtb_bookings WHERE id = %d",
            $id
        ) );
        $result = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $wpdb->prefix . 'jtb_bookings',
            $row,
            array( 'id' => $id )
        );

        // If advance_amount > 0 and no advance payment record exists yet, create one now.
        if ( $row['advance_amount'] > 0 ) {
            $pt    = $wpdb->prefix . 'jtb_payments';
            $count = $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT COUNT(*) FROM {$pt} WHERE booking_id = %d AND notes = 'Advance payment' AND is_voided = 0",
                $id
            ) );
            if ( $count && ! empty( $row['advance_date'] ) && $row['advance_date'] !== $old_advance_date ) {
                // Advance already recorded and staff changed its date on the
                // booking — move the Payment History entry to match. An
                // unchanged date never touches the payment, so a date edited
                // directly in Payment History is not overwritten.
                $wpdb->query( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    "UPDATE {$pt} SET payment_date = %s WHERE booking_id = %d AND notes = 'Advance payment' AND is_voided = 0 AND payment_date <> %s",
                    $row['advance_date'], $id, $row['advance_date']
                ) );
            }
            if ( ! $count ) {
                $advance_date = ! empty( $row['advance_date'] ) ? $row['advance_date'] : current_time( 'Y-m-d' );
                if ( empty( $row['advance_date'] ) ) {
                    // Caller sent no date: record today on the booking too, but
                    // never overwrite a date that is already stored.
                    $wpdb->query( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                        "UPDATE {$wpdb->prefix}jtb_bookings SET advance_date = %s WHERE id = %d AND advance_date IS NULL",
                        $advance_date, $id
                    ) );
                }
                $wpdb->insert( $pt, array( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                    'booking_id'      => $id,
                    'payment_date'    => $advance_date,
                    'amount'          => $row['advance_amount'],
                    'payment_mode'    => ! empty( $row['advance_payment_mode'] ) ? $row['advance_payment_mode'] : 'cash',
                    'received_by'     => ! empty( $row['advance_received_by'] ) ? $row['advance_received_by'] : '',
                    'transaction_ref' => '',
                    'notes'           => 'Advance payment',
                    'created_by'      => get_current_user_id(),
                ) );
            }
        }

        return $result;
    }

    /**
     * Targeted status-only update — NEVER passes through sanitize_booking()
     * so no other booking fields are touched.
     */
    public static function update_status( $id, $status ) {
        global $wpdb;
        $valid = array( 'new', 'confirmed', 'ongoing', 'completed', 'closed', 'cancelled' );
        if ( ! in_array( $status, $valid, true ) ) return false;
        return $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $wpdb->prefix . 'jtb_bookings',
            array( 'status' => $status ),
            array( 'id' => absint( $id ) ),
            array( '%s' ),
            array( '%d' )
        );
    }

    /**
     * Targeted update for the Google review-request tracking flag.
     * Never passes through sanitize_booking() — same reasoning as update_status().
     */
    public static function update_review_sent( $id, $sent ) {
        global $wpdb;
        $sent = $sent ? 1 : 0;
        return $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $wpdb->prefix . 'jtb_bookings',
            array(
                'review_request_sent'    => $sent,
                'review_request_sent_at' => $sent ? current_time( 'mysql' ) : null,
            ),
            array( 'id' => absint( $id ) ),
            array( '%d', '%s' ),
            array( '%d' )
        );
    }

    /**
     * Soft-delete a booking — marks is_deleted = 1 with reason, never removes rows.
     * Payments and expenses remain intact for financial records.
     */
    public static function archive_booking( $id, $reason = '' ) {
        global $wpdb;
        return $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $wpdb->prefix . 'jtb_bookings',
            array(
                'is_deleted'     => 1,
                'deleted_reason' => sanitize_textarea_field( $reason ),
                'deleted_by'     => get_current_user_id(),
                'deleted_at'     => current_time( 'mysql' ),
            ),
            array( 'id' => absint( $id ) ),
            array( '%d', '%s', '%d', '%s' ),
            array( '%d' )
        );
    }

    /** Hard delete — kept for internal use only (e.g. test cleanup). */
    public static function delete_booking( $id ) {
        global $wpdb;
        // Soft-delete preferred; use archive_booking() in production code.
        $wpdb->delete( $wpdb->prefix . 'jtb_payments', array( 'booking_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $wpdb->delete( $wpdb->prefix . 'jtb_expenses', array( 'booking_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        return $wpdb->delete( $wpdb->prefix . 'jtb_bookings', array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    }

    private static function sanitize_booking( $data ) {
        $valid_trip  = array( 'one-way', 'round-trip', 'local', 'outstation', 'yatra', 'other' );
        $valid_stat  = array( 'new', 'confirmed', 'ongoing', 'completed', 'closed', 'cancelled' );
        $valid_btype = array( 'individual', 'corporate' );
        $valid_mode  = array( 'cash', 'upi', 'bank-transfer', 'card', 'other' );
        $is_corp     = ( $data['booking_type'] ?? '' ) === 'corporate';
        // Accept either an explicit is_outsourced flag (frontend AJAX) or a
        // vehicle_source radio value of 'outsourced' (classic admin form POST).
        $is_out      = ! empty( $data['is_outsourced'] ) || ( ( $data['vehicle_source'] ?? '' ) === 'outsourced' );

        // Combine date + time if sent as separate fields (point 12)
        $start_date = ! empty( $data['start_date'] ) ? sanitize_text_field( $data['start_date'] ) : null;
        $end_date   = ! empty( $data['end_date'] )   ? sanitize_text_field( $data['end_date'] )   : null;
        if ( $start_date && ! empty( $data['start_time'] ) ) {
            $start_date = $start_date . ' ' . sanitize_text_field( $data['start_time'] ) . ':00';
        }
        if ( $end_date && ! empty( $data['end_time'] ) ) {
            $end_date = $end_date . ' ' . sanitize_text_field( $data['end_time'] ) . ':00';
        }

        // GST (point: add GST). booking_amount is the pre-GST sub-total entered
        // by staff. When GST applies, 5% is added on top; gst_amount is stored so
        // the payable total (booking_amount + gst_amount) never has to be
        // recomputed downstream.
        $booking_amount = (float) ( $data['booking_amount'] ?? 0 );
        $gst_applicable = ! empty( $data['gst_applicable'] ) ? 1 : 0;
        $gst_rate       = $gst_applicable ? 5.00 : 0.00;
        $gst_amount     = $gst_applicable ? round( $booking_amount * $gst_rate / 100, 2 ) : 0.00;

        $row = array(
            'booking_type'         => in_array( $data['booking_type'] ?? '', $valid_btype ) ? $data['booking_type'] : 'individual',
            'booking_ref'          => sanitize_text_field( $data['booking_ref'] ?? '' ),
            'customer_name'        => sanitize_text_field( $is_corp ? ( $data['corp_customer_name'] ?? $data['customer_name'] ?? '' ) : ( $data['customer_name'] ?? '' ) ),
            'customer_mobile'      => sanitize_text_field( $is_corp ? ( $data['corp_customer_mobile'] ?? $data['customer_mobile'] ?? '' ) : ( $data['customer_mobile'] ?? '' ) ),
            'customer_email'       => sanitize_email( $data['customer_email'] ?? '' ),
            'company_id'           => ! empty( $data['company_id'] ) ? absint( $data['company_id'] ) : null,
            'booked_by_name'       => sanitize_text_field( $data['booked_by_name'] ?? '' ),
            'booked_by_mobile'     => sanitize_text_field( $data['booked_by_mobile'] ?? '' ),
            'passenger_name'       => sanitize_text_field( $data['passenger_name'] ?? '' ),
            'passenger_mobile'     => sanitize_text_field( $data['passenger_mobile'] ?? '' ),
            'pickup_location'      => sanitize_text_field( $data['pickup_location'] ?? '' ),
            'drop_location'        => sanitize_text_field( $data['drop_location'] ?? '' ),
            'trip_type'            => in_array( $data['trip_type'] ?? '', $valid_trip ) ? $data['trip_type'] : 'one-way',
            'start_date'           => $start_date,
            'end_date'             => $end_date,
            'vehicle_id'                => ( $is_out || empty( $data['vehicle_id'] ) ) ? null : absint( $data['vehicle_id'] ),
            'driver_id'                 => ( $is_out || empty( $data['driver_id'] ) ) ? null : absint( $data['driver_id'] ),
            'is_outsourced'             => $is_out ? 1 : 0,
            'outsourced_driver_name'    => $is_out ? sanitize_text_field( $data['outsourced_driver_name'] ?? '' ) : '',
            'outsourced_driver_mobile'  => $is_out ? sanitize_text_field( $data['outsourced_driver_mobile'] ?? '' ) : '',
            'outsourced_vehicle_number' => $is_out ? sanitize_text_field( $data['outsourced_vehicle_number'] ?? '' ) : '',
            'booking_amount'       => $booking_amount,
            'gst_applicable'       => $gst_applicable,
            'gst_rate'             => $gst_rate,
            'gst_amount'           => $gst_amount,
            'advance_amount'       => (float) ( $data['advance_amount'] ?? 0 ),
            'advance_payment_mode' => in_array( $data['advance_payment_mode'] ?? '', $valid_mode ) ? $data['advance_payment_mode'] : 'cash',
            'advance_received_by'  => sanitize_text_field( $data['advance_received_by'] ?? '' ),
            'status'               => in_array( $data['status'] ?? '', $valid_stat ) ? $data['status'] : 'new',
            'notes'                => sanitize_textarea_field( $data['notes'] ?? '' ),
        );

        // advance_date (v3.6.0) is only written when the caller actually sent a
        // valid date with an advance. Callers that don't know about it (older
        // app builds, status-only flows) therefore never blank a stored date —
        // insert_booking() falls back to today, exactly as before.
        $advance_date = self::valid_date( $data['advance_date'] ?? '' );
        if ( $advance_date && $row['advance_amount'] > 0 ) {
            $row['advance_date'] = $advance_date;
        }

        return $row;
    }

    /**
     * Returns a Y-m-d string for a real calendar date, or null.
     */
    private static function valid_date( $value ) {
        $value = sanitize_text_field( (string) $value );
        if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) ) {
            return null;
        }
        return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ? $value : null;
    }

    // -------------------------------------------------------------------------
    // PAYMENTS
    // -------------------------------------------------------------------------

    public static function get_payments( $booking_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
            "SELECT * FROM {$wpdb->prefix}jtb_payments WHERE booking_id = %d ORDER BY payment_date ASC, id ASC",
            $booking_id
        ) );
    }

    public static function insert_payment( $data ) {
        global $wpdb;
        $valid_mode = array( 'cash', 'upi', 'bank-transfer', 'card', 'other' );
        $row = array(
            'booking_id'      => absint( $data['booking_id'] ),
            'payment_date'    => sanitize_text_field( $data['payment_date'] ?? gmdate('Y-m-d') ),
            'amount'          => (float) ( $data['amount'] ?? 0 ),
            'payment_mode'    => in_array( $data['payment_mode'] ?? '', $valid_mode ) ? $data['payment_mode'] : 'cash',
            'received_by'     => sanitize_text_field( $data['received_by'] ?? '' ),
            'transaction_ref' => sanitize_text_field( $data['transaction_ref'] ?? '' ),
            'notes'           => sanitize_textarea_field( $data['notes'] ?? '' ),
            'created_by'      => get_current_user_id(),
        );
        $wpdb->insert( $wpdb->prefix . 'jtb_payments', $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        return $wpdb->insert_id;
    }

    public static function delete_payment( $id ) {
        global $wpdb;
        return $wpdb->delete( $wpdb->prefix . 'jtb_payments', array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    }

    /**
     * Soft-void a payment (marks is_voided = 1, never removes row).
     */
    public static function void_payment( $id, $reason = '' ) {
        global $wpdb;
        return $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prefix . 'jtb_payments',
            array(
                'is_voided'   => 1,
                'void_reason' => sanitize_textarea_field( $reason ),
                'voided_by'   => get_current_user_id(),
                'voided_at'   => current_time( 'mysql' ),
            ),
            array( 'id' => absint( $id ) ),
            array( '%d', '%s', '%d', '%s' ),
            array( '%d' )
        );
    }

    /**
     * Edit an existing payment record.
     */
    public static function edit_payment( $id, $data ) {
        global $wpdb;
        $valid_mode = array( 'cash', 'upi', 'bank-transfer', 'card', 'other' );
        $row = array(
            'payment_date'    => sanitize_text_field( $data['payment_date'] ?? gmdate('Y-m-d') ),
            'amount'          => (float) ( $data['amount'] ?? 0 ),
            'payment_mode'    => in_array( $data['payment_mode'] ?? '', $valid_mode ) ? $data['payment_mode'] : 'cash',
            'received_by'     => sanitize_text_field( $data['received_by'] ?? '' ),
            'transaction_ref' => sanitize_text_field( $data['transaction_ref'] ?? '' ),
            'notes'           => sanitize_textarea_field( $data['notes'] ?? '' ),
        );
        $result = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prefix . 'jtb_payments',
            $row,
            array( 'id' => absint( $id ) )
        );

        // If this is the booking's advance payment, keep the booking's
        // advance_date in step so the booking form shows the same date.
        if ( false !== $result ) {
            $pt  = $wpdb->prefix . 'jtb_payments';
            $adv = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT booking_id, payment_date FROM {$pt} WHERE id = %d AND notes = 'Advance payment' AND is_voided = 0",
                absint( $id )
            ) );
            if ( $adv && self::valid_date( $adv->payment_date ) ) {
                $wpdb->query( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    "UPDATE {$wpdb->prefix}jtb_bookings SET advance_date = %s WHERE id = %d AND advance_amount > 0",
                    $adv->payment_date, (int) $adv->booking_id
                ) );
            }
        }

        return $result;
    }

    // -------------------------------------------------------------------------
    // EXPENSES
    // -------------------------------------------------------------------------

    public static function get_expenses( $booking_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
            "SELECT * FROM {$wpdb->prefix}jtb_expenses WHERE booking_id = %d ORDER BY expense_date ASC, id ASC",
            $booking_id
        ) );
    }

    public static function insert_expense( $data ) {
        global $wpdb;
        $valid_mode = array( 'cash', 'upi', 'bank-transfer', 'card', 'other' );
        $row = array(
            'booking_id'   => absint( $data['booking_id'] ),
            'expense_date' => sanitize_text_field( $data['expense_date'] ?? gmdate('Y-m-d') ),
            'expense_type' => sanitize_text_field( $data['expense_type'] ?? '' ),
            'amount'       => (float) ( $data['amount'] ?? 0 ),
            'paid_to'      => sanitize_text_field( $data['paid_to'] ?? '' ),
            'paid_by'      => sanitize_text_field( $data['paid_by'] ?? '' ),
            'payment_mode' => in_array( $data['payment_mode'] ?? '', $valid_mode ) ? $data['payment_mode'] : 'cash',
            'notes'        => sanitize_textarea_field( $data['notes'] ?? '' ),
            'created_by'   => get_current_user_id(),
        );
        $wpdb->insert( $wpdb->prefix . 'jtb_expenses', $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        return $wpdb->insert_id;
    }

    public static function delete_expense( $id ) {
        global $wpdb;
        return $wpdb->delete( $wpdb->prefix . 'jtb_expenses', array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    }

    /**
     * Soft-void an expense (marks is_voided = 1, never removes row).
     */
    public static function void_expense( $id, $reason = '' ) {
        global $wpdb;
        return $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prefix . 'jtb_expenses',
            array(
                'is_voided'   => 1,
                'void_reason' => sanitize_textarea_field( $reason ),
                'voided_by'   => get_current_user_id(),
                'voided_at'   => current_time( 'mysql' ),
            ),
            array( 'id' => absint( $id ) ),
            array( '%d', '%s', '%d', '%s' ),
            array( '%d' )
        );
    }

    /**
     * Edit an existing expense record.
     */
    public static function edit_expense( $id, $data ) {
        global $wpdb;
        $valid_mode = array( 'cash', 'upi', 'bank-transfer', 'card', 'other' );
        $row = array(
            'expense_date' => sanitize_text_field( $data['expense_date'] ?? gmdate('Y-m-d') ),
            'expense_type' => sanitize_text_field( $data['expense_type'] ?? '' ),
            'amount'       => (float) ( $data['amount'] ?? 0 ),
            'paid_to'      => sanitize_text_field( $data['paid_to'] ?? '' ),
            'paid_by'      => sanitize_text_field( $data['paid_by'] ?? '' ),
            'payment_mode' => in_array( $data['payment_mode'] ?? '', $valid_mode ) ? $data['payment_mode'] : 'cash',
            'notes'        => sanitize_textarea_field( $data['notes'] ?? '' ),
        );
        return $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prefix . 'jtb_expenses',
            $row,
            array( 'id' => absint( $id ) )
        );
    }

    // -------------------------------------------------------------------------
    // DASHBOARD STATS
    // -------------------------------------------------------------------------

    public static function get_dashboard_stats() {
        global $wpdb;
        $bt = $wpdb->prefix . 'jtb_bookings';
        $pt = $wpdb->prefix . 'jtb_payments';
        $et = $wpdb->prefix . 'jtb_expenses';

        $today     = gmdate( 'Y-m-d' );
        $month_s   = gmdate( 'Y-m-01' );
        $month_e   = gmdate( 'Y-m-t' );

        return array(
            // Trips whose start_date is today (regardless of booking creation date)
            'today_bookings'    => (int) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                "SELECT COUNT(*) FROM {$bt} WHERE DATE(start_date) = %s AND status NOT IN ('cancelled') AND is_deleted = 0", $today ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
            // Bookings with status = ongoing
            'ongoing'           => (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                "SELECT COUNT(*) FROM {$bt} WHERE status = 'ongoing' AND is_deleted = 0" ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
            // Confirmed/new trips scheduled AFTER today (strictly future)
            'upcoming'          => (int) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                "SELECT COUNT(*) FROM {$bt} WHERE status IN ('new','confirmed') AND DATE(start_date) > %s AND is_deleted = 0", $today ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
            'pending_payments'  => (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                "SELECT COUNT(*) FROM {$bt} b
                 LEFT JOIN (SELECT booking_id, SUM(amount) AS tp FROM {$pt} WHERE is_voided = 0 GROUP BY booking_id) p ON p.booking_id = b.id
                 WHERE b.status NOT IN ('cancelled') AND b.is_deleted = 0 AND (b.booking_amount + b.gst_amount - COALESCE(tp,0)) > 0"
            ),
            // Trips starting this calendar month
            'month_bookings'    => (int) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                "SELECT COUNT(*) FROM {$bt} WHERE DATE(start_date) BETWEEN %s AND %s AND status != 'cancelled' AND is_deleted = 0", $month_s, $month_e ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
            'month_revenue'     => (float) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                "SELECT COALESCE(SUM(p.amount),0) FROM {$pt} p INNER JOIN {$bt} b ON b.id = p.booking_id WHERE DATE(p.payment_date) BETWEEN %s AND %s AND b.is_deleted = 0", $month_s, $month_e ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
            'month_expenses'    => (float) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                "SELECT COALESCE(SUM(e.amount),0) FROM {$et} e INNER JOIN {$bt} b ON b.id = e.booking_id WHERE DATE(e.expense_date) BETWEEN %s AND %s AND b.is_deleted = 0", $month_s, $month_e ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
            'completed_month'   => (int) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                "SELECT COUNT(*) FROM {$bt} WHERE status = 'completed' AND DATE(updated_at) BETWEEN %s AND %s AND is_deleted = 0", $month_s, $month_e ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
        );
    }

    // -------------------------------------------------------------------------
    // REPORTS
    // -------------------------------------------------------------------------

    public static function get_pending_payments_report() {
        global $wpdb;
        $bt = $wpdb->prefix . 'jtb_bookings';
        $pt = $wpdb->prefix . 'jtb_payments';

        return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            "SELECT b.id, b.booking_number, b.customer_name, b.customer_mobile,
                    b.start_date, (b.booking_amount + b.gst_amount) AS booking_amount,
                    COALESCE(p.total_paid,0) AS total_paid,
                    (b.booking_amount + b.gst_amount - COALESCE(p.total_paid,0)) AS balance
             FROM {$bt} b
             LEFT JOIN (SELECT booking_id, SUM(amount) AS total_paid FROM {$pt} WHERE is_voided = 0 GROUP BY booking_id) p ON p.booking_id = b.id
             WHERE b.status NOT IN ('cancelled')
               AND b.is_deleted = 0
               AND (b.booking_amount + b.gst_amount - COALESCE(p.total_paid,0)) > 0
             ORDER BY b.start_date ASC"
        );
    }

    public static function get_monthly_report( $year, $month ) {
        global $wpdb;
        $bt  = $wpdb->prefix . 'jtb_bookings';
        $pt  = $wpdb->prefix . 'jtb_payments';
        $et  = $wpdb->prefix . 'jtb_expenses';
        $m_s = sprintf( '%04d-%02d-01', $year, $month );
        $m_e = gmdate( 'Y-m-t', strtotime( $m_s ) );

        return array(
            // NOTE: bookings/booking_amount are counted by trip START DATE (not created_at) so this
            // matches the Driver-wise, Vehicle-wise and By Customer/Booking Type reports, which all
            // filter on start_date. Keeping these on the same date field is what makes the totals
            // reconcile across report tabs for the same period.
            'bookings'       => $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$bt} WHERE DATE(start_date) BETWEEN %s AND %s AND status != 'cancelled' AND is_deleted = 0", $m_s, $m_e ) ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            'booking_amount' => $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(booking_amount + gst_amount),0) FROM {$bt} WHERE DATE(start_date) BETWEEN %s AND %s AND status != 'cancelled' AND is_deleted = 0", $m_s, $m_e ) ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            'received'       => $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(amount),0) FROM {$pt} WHERE DATE(payment_date) BETWEEN %s AND %s", $m_s, $m_e ) ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            'expenses'       => $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(amount),0) FROM {$et} WHERE DATE(expense_date) BETWEEN %s AND %s", $m_s, $m_e ) ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        );
    }

    public static function get_driver_report( $date_from = null, $date_to = null ) {
        global $wpdb;
        $bt = $wpdb->prefix . 'jtb_bookings';
        $dt = $wpdb->prefix . 'jtb_drivers';
        $pt = $wpdb->prefix . 'jtb_payments';
        $et = $wpdb->prefix . 'jtb_expenses';

        // Build WHERE clause (status + optional date range)
        $where  = "WHERE b.status != 'cancelled'";
        $params = array();
        if ( $date_from ) { $where .= ' AND DATE(b.start_date) >= %s'; $params[] = $date_from; }
        if ( $date_to )   { $where .= ' AND DATE(b.start_date) <= %s'; $params[] = $date_to; }

        $sql = "
            SELECT d.driver_name, d.mobile,
                COUNT(b.id)                        AS trips,
                COALESCE(SUM(b.booking_amount + b.gst_amount), 0) AS total_amount,
                COALESCE(SUM(py.tp), 0)            AS total_received,
                COALESCE(SUM(ex.te), 0)            AS total_expenses
            FROM {$dt} d
            INNER JOIN {$bt} b ON b.driver_id = d.id
            LEFT JOIN (
                SELECT booking_id, SUM(amount) AS tp FROM {$pt} GROUP BY booking_id
            ) py ON py.booking_id = b.id
            LEFT JOIN (
                SELECT booking_id, SUM(amount) AS te FROM {$et} GROUP BY booking_id
            ) ex ON ex.booking_id = b.id
            {$where}
            GROUP BY d.id, d.driver_name, d.mobile
            ORDER BY trips DESC
        ";

        if ( $params ) {
            $sql = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }

        $results = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return is_array( $results ) ? $results : array();
    }

    /**
     * Diagnostic counts — used when report returns empty to help debug data issues.
     */
    public static function get_driver_report_debug() {
        global $wpdb;
        $bt = $wpdb->prefix . 'jtb_bookings';
        $dt = $wpdb->prefix . 'jtb_drivers';
        return array(
            'drivers'           => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$dt}" ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            'eligible_bookings' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$bt} WHERE driver_id IS NOT NULL AND status != 'cancelled'" ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            'db_error'          => $wpdb->last_error ?: '',
        );
    }

    // -------------------------------------------------------------------------
    // ACTIVITY LOGS
    // -------------------------------------------------------------------------

    /**
     * Write a log entry. Call from AJAX handlers after any mutation.
     *
     * @param string $action       e.g. 'booking_created', 'payment_added', 'status_changed'
     * @param string $object_type  e.g. 'booking', 'payment', 'vehicle'
     * @param int    $object_id    Primary key of the affected row
     * @param string $object_label Human-readable label (booking number, customer name…)
     * @param string $details      Optional extra detail (e.g. old→new status)
     */
    public static function log_activity( $action, $object_type, $object_id, $object_label, $details = '' ) {
        global $wpdb;
        $user = wp_get_current_user();
        $wpdb->insert( $wpdb->prefix . 'jtb_activity_logs', array( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            'user_id'      => get_current_user_id() ?: null,
            'user_name'    => $user->display_name ?: $user->user_login ?: 'System',
            'action'       => sanitize_text_field( $action ),
            'object_type'  => sanitize_text_field( $object_type ),
            'object_id'    => $object_id ? absint( $object_id ) : null,
            'object_label' => sanitize_text_field( (string) $object_label ),
            'details'      => sanitize_textarea_field( (string) $details ),
            'ip_address'   => sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) ),
        ) );
    }

    public static function get_logs( $args = array() ) {
        global $wpdb;
        $lt     = $wpdb->prefix . 'jtb_activity_logs';
        $where  = 'WHERE 1=1';
        $params = array();

        if ( ! empty( $args['action'] ) ) {
            $where   .= ' AND action = %s';
            $params[] = $args['action'];
        }
        if ( ! empty( $args['object_type'] ) ) {
            $where   .= ' AND object_type = %s';
            $params[] = $args['object_type'];
        }
        if ( ! empty( $args['user_id'] ) ) {
            $where   .= ' AND user_id = %d';
            $params[] = absint( $args['user_id'] );
        }
        if ( ! empty( $args['date_from'] ) ) {
            $where   .= ' AND DATE(created_at) >= %s';
            $params[] = $args['date_from'];
        }
        if ( ! empty( $args['date_to'] ) ) {
            $where   .= ' AND DATE(created_at) <= %s';
            $params[] = $args['date_to'];
        }
        if ( ! empty( $args['search'] ) ) {
            $s        = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where   .= ' AND (object_label LIKE %s OR details LIKE %s OR user_name LIKE %s)';
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
        }

        $limit    = isset( $args['limit'] ) ? absint( $args['limit'] ) : 200;
        $offset   = isset( $args['offset'] ) ? absint( $args['offset'] ) : 0;
        $params[] = $limit;
        $params[] = $offset;

        return $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
            "SELECT * FROM {$lt} {$where} ORDER BY id DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
            $params
        ) );
    }

    public static function count_logs( $args = array() ) {
        global $wpdb;
        $lt    = $wpdb->prefix . 'jtb_activity_logs';
        $where = 'WHERE 1=1';
        $p     = array();
        if ( ! empty( $args['action'] ) )      { $where .= ' AND action = %s';      $p[] = $args['action']; }
        if ( ! empty( $args['object_type'] ) ) { $where .= ' AND object_type = %s'; $p[] = $args['object_type']; }
        if ( ! empty( $args['date_from'] ) )   { $where .= ' AND DATE(created_at) >= %s'; $p[] = $args['date_from']; }
        if ( ! empty( $args['date_to'] ) )     { $where .= ' AND DATE(created_at) <= %s'; $p[] = $args['date_to']; }
        $sql = "SELECT COUNT(*) FROM {$lt} {$where}"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( $p ) $sql = $wpdb->prepare( $sql, $p ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    }

    public static function clear_logs() {
        global $wpdb;
        return $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}jtb_activity_logs" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    }

    // -------------------------------------------------------------------------
    // (REPORTS continued)
    // -------------------------------------------------------------------------

    public static function get_vehicle_report( $date_from = null, $date_to = null ) {
        global $wpdb;
        $bt = $wpdb->prefix . 'jtb_bookings';
        $vt = $wpdb->prefix . 'jtb_vehicles';
        $pt = $wpdb->prefix . 'jtb_payments';
        $et = $wpdb->prefix . 'jtb_expenses';

        $where  = "WHERE b.status != 'cancelled'";
        $params = array();
        if ( $date_from ) { $where .= ' AND DATE(b.start_date) >= %s'; $params[] = $date_from; }
        if ( $date_to )   { $where .= ' AND DATE(b.start_date) <= %s'; $params[] = $date_to; }

        $sql = "
            SELECT v.vehicle_name, v.registration_number,
                COUNT(b.id)                        AS trips,
                COALESCE(SUM(b.booking_amount + b.gst_amount), 0) AS total_amount,
                COALESCE(SUM(py.tp), 0)            AS total_received,
                COALESCE(SUM(ex.te), 0)            AS total_expenses
            FROM {$vt} v
            INNER JOIN {$bt} b ON b.vehicle_id = v.id
            LEFT JOIN (
                SELECT booking_id, SUM(amount) AS tp FROM {$pt} GROUP BY booking_id
            ) py ON py.booking_id = b.id
            LEFT JOIN (
                SELECT booking_id, SUM(amount) AS te FROM {$et} GROUP BY booking_id
            ) ex ON ex.booking_id = b.id
            {$where}
            GROUP BY v.id, v.vehicle_name, v.registration_number
            ORDER BY trips DESC
        ";

        if ( $params ) {
            $sql = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }

        $results = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return is_array( $results ) ? $results : array();
    }

    public static function get_vehicle_report_debug() {
        global $wpdb;
        $bt = $wpdb->prefix . 'jtb_bookings';
        $vt = $wpdb->prefix . 'jtb_vehicles';
        return array(
            'vehicles'          => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$vt}" ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            'eligible_bookings' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$bt} WHERE vehicle_id IS NOT NULL AND status != 'cancelled'" ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            'db_error'          => $wpdb->last_error ?: '',
        );
    }

    /**
     * Corporate vs Individual booking value summary for the reports page (point 9).
     *
     * @param int $year  4-digit year
     * @param int $month 1-12
     * @return array  Keys: individual_count, individual_amount, corporate_count, corporate_amount
     */
    public static function get_booking_type_summary( $year, $month ) {
        global $wpdb;
        $bt  = $wpdb->prefix . 'jtb_bookings';
        $m_s = sprintf( '%04d-%02d-01', $year, $month );
        $m_e = gmdate( 'Y-m-t', strtotime( $m_s ) );

        // Filtered on trip start_date to match get_monthly_report() and get_customer_type_report().
        $rows = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            "SELECT booking_type,
                    COUNT(*) AS cnt,
                    COALESCE(SUM(booking_amount + gst_amount), 0) AS total_amount
             FROM {$bt}
             WHERE DATE(start_date) BETWEEN %s AND %s
               AND status != 'cancelled'
               AND is_deleted = 0
             GROUP BY booking_type",
            $m_s, $m_e
        ) );

        $result = array(
            'individual_count'  => 0,
            'individual_amount' => 0.0,
            'corporate_count'   => 0,
            'corporate_amount'  => 0.0,
        );
        foreach ( $rows as $row ) {
            if ( $row->booking_type === 'corporate' ) {
                $result['corporate_count']  = (int) $row->cnt;
                $result['corporate_amount'] = (float) $row->total_amount;
            } else {
                $result['individual_count']  = (int) $row->cnt;
                $result['individual_amount'] = (float) $row->total_amount;
            }
        }
        return $result;
    }

    /**
     * By Customer / Booking Type report — one row per corporate customer
     * (grouped by company), plus a single aggregated "Individual Customers"
     * row for all individual bookings.
     *
     * @param string|null $date_from    Y-m-d, filters on start_date
     * @param string|null $date_to      Y-m-d, filters on start_date
     * @param string      $type_filter  'all' | 'corporate' | 'individual'
     * @return array List of objects: { type, name, bookings, total_amount }
     */
    public static function get_customer_type_report( $date_from = null, $date_to = null, $type_filter = 'all' ) {
        global $wpdb;
        $bt = $wpdb->prefix . 'jtb_bookings';
        $ct = $wpdb->prefix . 'jtb_companies';

        if ( ! in_array( $type_filter, array( 'all', 'corporate', 'individual' ), true ) ) {
            $type_filter = 'all';
        }

        $where  = "WHERE b.status != 'cancelled' AND b.is_deleted = 0";
        $params = array();
        if ( $date_from ) { $where .= ' AND DATE(b.start_date) >= %s'; $params[] = $date_from; }
        if ( $date_to )   { $where .= ' AND DATE(b.start_date) <= %s'; $params[] = $date_to; }

        $rows = array();

        // Corporate bookings — one row per company (unassigned corporate bookings grouped together)
        if ( $type_filter === 'all' || $type_filter === 'corporate' ) {
            $corp_sql = "
                SELECT COALESCE(c.company_name, 'Corporate (no company set)') AS name,
                    COUNT(b.id)                        AS bookings,
                    COALESCE(SUM(b.booking_amount + b.gst_amount), 0) AS total_amount
                FROM {$bt} b
                LEFT JOIN {$ct} c ON c.id = b.company_id
                {$where} AND b.booking_type = 'corporate'
                GROUP BY COALESCE(c.id, 0), COALESCE(c.company_name, 'Corporate (no company set)')
                ORDER BY total_amount DESC
            ";
            if ( $params ) {
                $corp_sql = $wpdb->prepare( $corp_sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            }
            $corp_rows = $wpdb->get_results( $corp_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $corp_rows = is_array( $corp_rows ) ? $corp_rows : array();

            foreach ( $corp_rows as $r ) {
                $rows[] = (object) array(
                    'type'         => 'Corporate',
                    'name'         => $r->name,
                    'bookings'     => (int) $r->bookings,
                    'total_amount' => (float) $r->total_amount,
                );
            }
        }

        // Individual bookings — single aggregated row
        if ( $type_filter === 'all' || $type_filter === 'individual' ) {
            $ind_sql = "
                SELECT COUNT(b.id) AS bookings, COALESCE(SUM(b.booking_amount + b.gst_amount), 0) AS total_amount
                FROM {$bt} b
                {$where} AND b.booking_type = 'individual'
            ";
            if ( $params ) {
                $ind_sql = $wpdb->prepare( $ind_sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            }
            $ind = $wpdb->get_row( $ind_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

            $rows[] = (object) array(
                'type'         => 'Individual',
                'name'         => 'Individual Customers',
                'bookings'     => $ind ? (int) $ind->bookings : 0,
                'total_amount' => $ind ? (float) $ind->total_amount : 0.0,
            );
        }

        return $rows;
    }
}
