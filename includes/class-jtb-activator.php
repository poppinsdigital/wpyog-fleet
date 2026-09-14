<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WPYOGFLEET_Activator {

    public static function activate() {
        self::create_tables();
        self::migrate_columns();
        self::create_roles();
        self::migrate_legacy_prefix();
        flush_rewrite_rules();
        // Redirect to setup wizard on first activation
        if ( ! get_option( WPYOGFLEET_Settings::SETUP_COMPLETE ) ) {
            set_transient( 'wpyogfleet_activation_redirect', true, 30 );
        }
    }

    public static function deactivate() {
        flush_rewrite_rules();
    }

    private static function create_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $sql = array();

        // Vehicles
        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}jtb_vehicles (
            id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            vehicle_name        VARCHAR(200) NOT NULL,
            vehicle_type        VARCHAR(100) DEFAULT '',
            registration_number VARCHAR(50)  DEFAULT '',
            owner_type          ENUM('own','outsourced') NOT NULL DEFAULT 'own',
            vendor_name         VARCHAR(200) DEFAULT '',
            vendor_mobile       VARCHAR(20)  DEFAULT '',
            status              ENUM('active','inactive') NOT NULL DEFAULT 'active',
            notes               TEXT,
            created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset;";

        // Drivers
        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}jtb_drivers (
            id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            driver_name VARCHAR(200) NOT NULL,
            mobile      VARCHAR(20)  DEFAULT '',
            vehicle_id  BIGINT(20) UNSIGNED DEFAULT NULL,
            driver_type ENUM('own','outsourced') NOT NULL DEFAULT 'own',
            status      ENUM('active','inactive') NOT NULL DEFAULT 'active',
            notes       TEXT,
            created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset;";

        // Companies (new in v2.7.0)
        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}jtb_companies (
            id             BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            company_name   VARCHAR(200) NOT NULL,
            contact_name   VARCHAR(200) DEFAULT '',
            contact_mobile VARCHAR(20)  DEFAULT '',
            contact_email  VARCHAR(200) DEFAULT '',
            gst_number     VARCHAR(50)  DEFAULT '',
            address        TEXT,
            notes          TEXT,
            status         ENUM('active','inactive') NOT NULL DEFAULT 'active',
            created_by     BIGINT(20) UNSIGNED DEFAULT NULL,
            created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset;";

        // Bookings
        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}jtb_bookings (
            id               BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            booking_number   VARCHAR(30)  NOT NULL,
            booking_type     ENUM('individual','corporate') NOT NULL DEFAULT 'individual',
            customer_name    VARCHAR(200) NOT NULL,
            customer_mobile  VARCHAR(20)  NOT NULL,
            customer_email   VARCHAR(200) DEFAULT '',
            company_id       BIGINT(20) UNSIGNED DEFAULT NULL,
            booked_by_name   VARCHAR(200) DEFAULT '',
            booked_by_mobile VARCHAR(20)  DEFAULT '',
            passenger_name   VARCHAR(200) DEFAULT '',
            passenger_mobile VARCHAR(20)  DEFAULT '',
            pickup_location  TEXT,
            drop_location    TEXT,
            trip_type        ENUM('one-way','round-trip','local','outstation','yatra','other') NOT NULL DEFAULT 'one-way',
            start_date       DATETIME,
            end_date         DATETIME,
            vehicle_id       BIGINT(20) UNSIGNED DEFAULT NULL,
            driver_id        BIGINT(20) UNSIGNED DEFAULT NULL,
            is_outsourced             TINYINT(1) NOT NULL DEFAULT 0,
            outsourced_driver_name    VARCHAR(200) DEFAULT '',
            outsourced_driver_mobile  VARCHAR(20)  DEFAULT '',
            outsourced_vehicle_number VARCHAR(50)  DEFAULT '',
            booking_amount   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            advance_amount        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            advance_payment_mode  ENUM('cash','upi','bank-transfer','card','other') NOT NULL DEFAULT 'cash',
            advance_received_by   VARCHAR(200) DEFAULT '',
            gst_applicable        TINYINT(1) NOT NULL DEFAULT 0,
            gst_rate              DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            gst_amount            DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            booking_ref           VARCHAR(100) DEFAULT '',
            status                ENUM('new','confirmed','ongoing','completed','closed','cancelled') NOT NULL DEFAULT 'new',
            review_request_sent    TINYINT(1) NOT NULL DEFAULT 0,
            review_request_sent_at DATETIME DEFAULT NULL,
            notes            TEXT,
            created_by       BIGINT(20) UNSIGNED DEFAULT NULL,
            created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            is_deleted       TINYINT(1) NOT NULL DEFAULT 0,
            deleted_reason   TEXT,
            deleted_by       BIGINT(20) UNSIGNED DEFAULT NULL,
            deleted_at       DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY booking_number (booking_number),
            KEY is_deleted (is_deleted)
        ) $charset;";

        // Payments
        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}jtb_payments (
            id              BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            booking_id      BIGINT(20) UNSIGNED NOT NULL,
            payment_date    DATE NOT NULL,
            amount          DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            payment_mode    ENUM('cash','upi','bank-transfer','card','other') NOT NULL DEFAULT 'cash',
            received_by     VARCHAR(200) DEFAULT '',
            transaction_ref VARCHAR(200) DEFAULT '',
            notes           TEXT,
            created_by      BIGINT(20) UNSIGNED DEFAULT NULL,
            created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            is_voided       TINYINT(1) NOT NULL DEFAULT 0,
            void_reason     TEXT,
            voided_by       BIGINT(20) UNSIGNED DEFAULT NULL,
            voided_at       DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            KEY booking_id (booking_id)
        ) $charset;";

        // Expenses
        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}jtb_expenses (
            id           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            booking_id   BIGINT(20) UNSIGNED NOT NULL,
            expense_date DATE NOT NULL,
            expense_type VARCHAR(100) NOT NULL DEFAULT '',
            amount       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            paid_to      VARCHAR(200) DEFAULT '',
            paid_by      VARCHAR(200) DEFAULT '',
            payment_mode ENUM('cash','upi','bank-transfer','card','other') NOT NULL DEFAULT 'cash',
            notes        TEXT,
            created_by   BIGINT(20) UNSIGNED DEFAULT NULL,
            created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            is_voided    TINYINT(1) NOT NULL DEFAULT 0,
            void_reason  TEXT,
            voided_by    BIGINT(20) UNSIGNED DEFAULT NULL,
            voided_at    DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            KEY booking_id (booking_id)
        ) $charset;";

        // Activity Logs
        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}jtb_activity_logs (
            id           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id      BIGINT(20) UNSIGNED DEFAULT NULL,
            user_name    VARCHAR(200) DEFAULT '',
            action       VARCHAR(100) NOT NULL,
            object_type  VARCHAR(50)  NOT NULL DEFAULT '',
            object_id    BIGINT(20) UNSIGNED DEFAULT NULL,
            object_label VARCHAR(300) DEFAULT '',
            details      TEXT,
            ip_address   VARCHAR(45)  DEFAULT '',
            created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY created_at (created_at),
            KEY object_type (object_type),
            KEY user_id (user_id)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach ( $sql as $query ) {
            dbDelta( $query );
        }

        // Store DB version
        update_option( 'wpyogfleet_db_version', WPYOGFLEET_VERSION );
    }

    /**
     * Explicitly add new columns to existing tables.
     * Safer than relying on dbDelta for ALTER TABLE operations.
     */
    private static function migrate_columns() {
        global $wpdb;
        $table = $wpdb->prefix . 'jtb_bookings';

        // Get existing column names
        $existing = $wpdb->get_col( "DESCRIBE {$table}", 0 ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if ( empty( $existing ) ) return; // table doesn't exist yet — dbDelta will create it

        $additions = array(
            // v2.6.0 soft-delete columns
            'is_deleted'     => "ALTER TABLE {$table} ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0 AFTER updated_at",
            'deleted_reason' => "ALTER TABLE {$table} ADD COLUMN deleted_reason TEXT DEFAULT NULL AFTER is_deleted",
            'deleted_by'     => "ALTER TABLE {$table} ADD COLUMN deleted_by BIGINT(20) UNSIGNED DEFAULT NULL AFTER deleted_reason",
            'deleted_at'     => "ALTER TABLE {$table} ADD COLUMN deleted_at DATETIME DEFAULT NULL AFTER deleted_by",
            // v2.7.0 booking type + corporate columns
            'booking_type'     => "ALTER TABLE {$table} ADD COLUMN booking_type ENUM('individual','corporate') NOT NULL DEFAULT 'individual' AFTER booking_number",
            'company_id'       => "ALTER TABLE {$table} ADD COLUMN company_id BIGINT(20) UNSIGNED DEFAULT NULL AFTER customer_mobile",
            'booked_by_name'   => "ALTER TABLE {$table} ADD COLUMN booked_by_name VARCHAR(200) DEFAULT '' AFTER company_id",
            'booked_by_mobile' => "ALTER TABLE {$table} ADD COLUMN booked_by_mobile VARCHAR(20) DEFAULT '' AFTER booked_by_name",
            'passenger_name'   => "ALTER TABLE {$table} ADD COLUMN passenger_name VARCHAR(200) DEFAULT '' AFTER booked_by_mobile",
            'passenger_mobile' => "ALTER TABLE {$table} ADD COLUMN passenger_mobile VARCHAR(20) DEFAULT '' AFTER passenger_name",
            'customer_email'       => "ALTER TABLE {$table} ADD COLUMN customer_email VARCHAR(200) DEFAULT '' AFTER customer_mobile",
            // v3.0.6 corporate booking reference + advance payment details
            'booking_ref'          => "ALTER TABLE {$table} ADD COLUMN booking_ref VARCHAR(100) DEFAULT '' AFTER booking_number",
            'advance_payment_mode' => "ALTER TABLE {$table} ADD COLUMN advance_payment_mode ENUM('cash','upi','bank-transfer','card','other') NOT NULL DEFAULT 'cash' AFTER advance_amount",
            'advance_received_by'  => "ALTER TABLE {$table} ADD COLUMN advance_received_by VARCHAR(200) DEFAULT '' AFTER advance_payment_mode",
            // v3.3.0 outsourced vehicle/driver (typed in by staff, not from the internal fleet)
            'is_outsourced'             => "ALTER TABLE {$table} ADD COLUMN is_outsourced TINYINT(1) NOT NULL DEFAULT 0 AFTER driver_id",
            'outsourced_driver_name'    => "ALTER TABLE {$table} ADD COLUMN outsourced_driver_name VARCHAR(200) DEFAULT '' AFTER is_outsourced",
            'outsourced_driver_mobile'  => "ALTER TABLE {$table} ADD COLUMN outsourced_driver_mobile VARCHAR(20) DEFAULT '' AFTER outsourced_driver_name",
            'outsourced_vehicle_number' => "ALTER TABLE {$table} ADD COLUMN outsourced_vehicle_number VARCHAR(50) DEFAULT '' AFTER outsourced_driver_mobile",
            // v3.4.0 Google review request tracking
            'review_request_sent'    => "ALTER TABLE {$table} ADD COLUMN review_request_sent TINYINT(1) NOT NULL DEFAULT 0 AFTER status",
            'review_request_sent_at' => "ALTER TABLE {$table} ADD COLUMN review_request_sent_at DATETIME DEFAULT NULL AFTER review_request_sent",
            // v3.5.8 GST on booking amount — booking_amount stays the pre-GST
            // sub-total; gst_amount is the 5% added on top when applicable.
            'gst_applicable' => "ALTER TABLE {$table} ADD COLUMN gst_applicable TINYINT(1) NOT NULL DEFAULT 0 AFTER advance_received_by",
            'gst_rate'       => "ALTER TABLE {$table} ADD COLUMN gst_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER gst_applicable",
            'gst_amount'     => "ALTER TABLE {$table} ADD COLUMN gst_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER gst_rate",
        );

        foreach ( $additions as $col => $sql ) {
            if ( ! in_array( $col, $existing, true ) ) {
                $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            }
        }

        // v3.3.0 — add 'closed' to the status ENUM (Completed + fully paid, admin-confirmed).
        // Existing columns can't be widened via the $additions loop above (it only ADDs
        // new columns), so check the live column definition and MODIFY it if needed.
        $status_col = $wpdb->get_row( "SHOW COLUMNS FROM {$table} WHERE Field = 'status'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if ( $status_col && strpos( $status_col->Type, "'closed'" ) === false ) {
            $wpdb->query( "ALTER TABLE {$table} MODIFY COLUMN status ENUM('new','confirmed','ongoing','completed','closed','cancelled') NOT NULL DEFAULT 'new'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
        }

        // ── Payments void columns ─────────────────────────────────────────────
        $pt = $wpdb->prefix . 'jtb_payments';
        $pt_existing = $wpdb->get_col( "DESCRIBE {$pt}", 0 ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if ( ! empty( $pt_existing ) ) {
            $pt_additions = array(
                'is_voided'   => "ALTER TABLE {$pt} ADD COLUMN is_voided TINYINT(1) NOT NULL DEFAULT 0 AFTER notes",
                'void_reason' => "ALTER TABLE {$pt} ADD COLUMN void_reason TEXT DEFAULT NULL AFTER is_voided",
                'voided_by'   => "ALTER TABLE {$pt} ADD COLUMN voided_by BIGINT(20) UNSIGNED DEFAULT NULL AFTER void_reason",
                'voided_at'   => "ALTER TABLE {$pt} ADD COLUMN voided_at DATETIME DEFAULT NULL AFTER voided_by",
            );
            foreach ( $pt_additions as $col => $sql ) {
                if ( ! in_array( $col, $pt_existing, true ) ) {
                    $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                }
            }
        }

        // ── Expenses void columns ─────────────────────────────────────────────
        $et = $wpdb->prefix . 'jtb_expenses';
        $et_existing = $wpdb->get_col( "DESCRIBE {$et}", 0 ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if ( ! empty( $et_existing ) ) {
            $et_additions = array(
                'is_voided'   => "ALTER TABLE {$et} ADD COLUMN is_voided TINYINT(1) NOT NULL DEFAULT 0 AFTER notes",
                'void_reason' => "ALTER TABLE {$et} ADD COLUMN void_reason TEXT DEFAULT NULL AFTER is_voided",
                'voided_by'   => "ALTER TABLE {$et} ADD COLUMN voided_by BIGINT(20) UNSIGNED DEFAULT NULL AFTER void_reason",
                'voided_at'   => "ALTER TABLE {$et} ADD COLUMN voided_at DATETIME DEFAULT NULL AFTER voided_by",
            );
            foreach ( $et_additions as $col => $sql ) {
                if ( ! in_array( $col, $et_existing, true ) ) {
                    $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                }
            }
        }

        // Update all existing NULL is_deleted rows to 0
        $wpdb->query( "UPDATE {$table} SET is_deleted = 0 WHERE is_deleted IS NULL" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        // Default booking_type for existing rows
        $wpdb->query( "UPDATE {$table} SET booking_type = 'individual' WHERE booking_type IS NULL OR booking_type = ''" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    }

    private static function create_roles() {
        // Booking Staff role
        if ( ! get_role( 'wpyogfleet_staff' ) ) {
            add_role( 'wpyogfleet_staff', 'Booking Staff', array(
                'read'           => true,
                'wpyogfleet_manage'     => true,
            ) );
        }
        // Give admin the cap too
        $admin = get_role( 'administrator' );
        if ( $admin ) {
            $admin->add_cap( 'wpyogfleet_manage' );
            $admin->add_cap( 'wpyogfleet_admin' );
        }
    }

    /**
     * One-time migration from the pre-3.5.0 "jtb_*" identifier scheme to the
     * "wpyogfleet_*" scheme (renamed to satisfy WordPress.org's plugin prefix
     * requirements). Carries forward existing settings and staff access on
     * sites that were already running an earlier version of the plugin, so
     * nothing is lost on upgrade.
     */
    private static function migrate_legacy_prefix() {
        // ── Settings options ────────────────────────────────────────────────
        $option_map = array(
            'jtb_company_name'       => WPYOGFLEET_Settings::COMPANY_NAME,
            'jtb_booking_prefix'     => WPYOGFLEET_Settings::BOOKING_PREFIX,
            'jtb_ops_email'          => WPYOGFLEET_Settings::OPS_EMAIL,
            'jtb_ops_phone'          => WPYOGFLEET_Settings::OPS_PHONE,
            'jtb_google_review_link' => WPYOGFLEET_Settings::GOOGLE_REVIEW_LINK,
            'jtb_setup_complete'     => WPYOGFLEET_Settings::SETUP_COMPLETE,
        );
        foreach ( $option_map as $old_key => $new_key ) {
            $old_value = get_option( $old_key, null );
            if ( null === $old_value ) continue;
            if ( false === get_option( $new_key, false ) ) {
                update_option( $new_key, $old_value );
            }
            delete_option( $old_key );
        }
        delete_option( 'jtb_db_version' ); // superseded by wpyogfleet_db_version

        // ── Roles & capabilities ────────────────────────────────────────────
        // Move any user still holding the old role/caps over to the new ones
        // before the legacy role definition is removed below.
        $all_users = get_users( array( 'fields' => array( 'ID' ) ) );
        foreach ( $all_users as $u ) {
            $user = new WP_User( $u->ID );
            if ( in_array( 'jtb_staff', (array) $user->roles, true ) ) {
                $user->add_role( 'wpyogfleet_staff' );
                $user->remove_role( 'jtb_staff' );
            }
            if ( isset( $user->caps['jtb_admin'] ) ) {
                $user->add_cap( 'wpyogfleet_admin' );
                $user->remove_cap( 'jtb_admin' );
            }
            if ( isset( $user->caps['jtb_manage'] ) ) {
                $user->add_cap( 'wpyogfleet_manage' );
                $user->remove_cap( 'jtb_manage' );
            }
        }

        if ( get_role( 'jtb_staff' ) ) {
            remove_role( 'jtb_staff' );
        }
        $admin_role = get_role( 'administrator' );
        if ( $admin_role ) {
            $admin_role->remove_cap( 'jtb_manage' );
            $admin_role->remove_cap( 'jtb_admin' );
        }
    }
}
