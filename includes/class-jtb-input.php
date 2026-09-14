<?php
// phpcs:disable WordPress.Security.NonceVerification.Missing -- This class only reads/sanitizes $_POST values, it never processes or persists anything by itself. Every caller (admin form handlers, AJAX handlers, frontend AJAX handlers) verifies its nonce via check_admin_referer()/wp_verify_nonce()/check_ajax_referer() BEFORE calling any method here — see class-jtb-admin.php, class-jtb-ajax.php::verify(), class-jtb-frontend.php::fe_verify().
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Centralised $_POST readers.
 *
 * Every field is sanitized at the exact point it is read from $_POST,
 * using a WordPress core sanitizing function wrapped directly around the
 * superglobal access (sanitize_text_field( wp_unslash( $_POST['x'] ) ), etc.).
 * This is the pattern WordPress.org's automated and manual plugin reviews
 * both scan for — a suppression comment claiming "sanitized downstream" is
 * not accepted because the sniff (and a human reviewer) can't verify what
 * happens inside a separately-called function.
 *
 * WPYOGFLEET_DB::sanitize_*() still re-validates/casts every field
 * afterwards (whitelisting enums, absint() on IDs, etc.) — that layer is
 * unchanged and kept as defense-in-depth. This class only handles the
 * first pass: turning raw $_POST access into properly sanitized values.
 *
 * Only keys actually present in $_POST are added to the returned array,
 * so downstream `$data['field'] ?? $default` fallbacks keep working
 * exactly as before.
 */
class WPYOGFLEET_Input {

    /** Single-line text fields. */
    private static function fields( array $keys ) {
        $out = array();
        foreach ( $keys as $key ) {
            if ( isset( $_POST[ $key ] ) ) {
                $out[ $key ] = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
            }
        }
        return $out;
    }

    /** Multi-line text fields (notes, address) — preserves line breaks. */
    private static function textarea_fields( array $keys ) {
        $out = array();
        foreach ( $keys as $key ) {
            if ( isset( $_POST[ $key ] ) ) {
                $out[ $key ] = sanitize_textarea_field( wp_unslash( $_POST[ $key ] ) );
            }
        }
        return $out;
    }

    public static function vehicle() {
        return array_merge(
            self::fields( array( 'vehicle_name', 'vehicle_type', 'registration_number', 'owner_type', 'vendor_name', 'vendor_mobile', 'status' ) ),
            self::textarea_fields( array( 'notes' ) )
        );
    }

    public static function driver() {
        return array_merge(
            self::fields( array( 'driver_name', 'mobile', 'vehicle_id', 'driver_type', 'status' ) ),
            self::textarea_fields( array( 'notes' ) )
        );
    }

    public static function company() {
        return array_merge(
            self::fields( array( 'company_name', 'contact_name', 'contact_mobile', 'contact_email', 'gst_number', 'status' ) ),
            self::textarea_fields( array( 'address', 'notes' ) )
        );
    }

    public static function booking() {
        return array_merge(
            self::fields( array(
                'booking_type', 'booking_ref', 'corp_customer_name', 'customer_name',
                'corp_customer_mobile', 'customer_mobile', 'customer_email', 'company_id',
                'booked_by_name', 'booked_by_mobile', 'passenger_name', 'passenger_mobile',
                'pickup_location', 'drop_location', 'trip_type', 'start_date', 'start_time',
                'end_date', 'end_time', 'vehicle_id', 'driver_id', 'is_outsourced', 'vehicle_source',
                'outsourced_driver_name', 'outsourced_driver_mobile', 'outsourced_vehicle_number',
                'booking_amount', 'gst_applicable', 'advance_amount', 'advance_payment_mode', 'advance_received_by', 'status',
            ) ),
            self::textarea_fields( array( 'notes' ) )
        );
    }

    public static function payment() {
        return array_merge(
            self::fields( array( 'booking_id', 'payment_date', 'amount', 'payment_mode', 'received_by', 'transaction_ref' ) ),
            self::textarea_fields( array( 'notes' ) )
        );
    }

    public static function expense() {
        return array_merge(
            self::fields( array( 'booking_id', 'expense_date', 'expense_type', 'amount', 'paid_to', 'paid_by', 'payment_mode' ) ),
            self::textarea_fields( array( 'notes' ) )
        );
    }

    public static function settings() {
        return self::fields( array(
            'wpyogfleet_company_name', 'wpyogfleet_booking_prefix', 'wpyogfleet_ops_email',
            'wpyogfleet_ops_phone', 'wpyogfleet_google_review_link',
        ) );
    }
}
