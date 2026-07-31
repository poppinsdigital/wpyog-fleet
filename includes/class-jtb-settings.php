<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Plugin-wide settings helper.
 * Use WPYOGFLEET_Settings::get( 'key' ) anywhere.
 */
class WPYOGFLEET_Settings {

    // Option keys
    const COMPANY_NAME    = 'wpyogfleet_company_name';
    const BOOKING_PREFIX  = 'wpyogfleet_booking_prefix';
    const OPS_EMAIL       = 'wpyogfleet_ops_email';
    const OPS_PHONE       = 'wpyogfleet_ops_phone';
    const GOOGLE_REVIEW_LINK = 'wpyogfleet_google_review_link';
    const SETUP_COMPLETE  = 'wpyogfleet_setup_complete';

    /**
     * Get a setting value with a sensible default.
     */
    public static function get( $key ) {
        switch ( $key ) {
            case self::COMPANY_NAME:
                return get_option( self::COMPANY_NAME, get_bloginfo( 'name' ) );
            case self::BOOKING_PREFIX:
                $prefix = get_option( self::BOOKING_PREFIX, '' );
                if ( ! $prefix ) {
                    // Auto-derive from company name: "Jagat Travels" → "JT"
                    $name   = self::get( self::COMPANY_NAME );
                    $words  = preg_split( '/\s+/', trim( $name ) );
                    $prefix = '';
                    foreach ( $words as $w ) {
                        $prefix .= strtoupper( substr( $w, 0, 1 ) );
                    }
                    if ( strlen( $prefix ) < 2 ) $prefix = strtoupper( substr( $name, 0, 2 ) );
                }
                return $prefix;
            case self::OPS_EMAIL:
                return get_option( self::OPS_EMAIL, get_option( 'admin_email', '' ) );
            case self::OPS_PHONE:
                return get_option( self::OPS_PHONE, '' );
            case self::GOOGLE_REVIEW_LINK:
                return get_option( self::GOOGLE_REVIEW_LINK, '' );
            default:
                return get_option( $key, '' );
        }
    }

    /**
     * Save all settings from a POST array.
     * Returns array of error messages (empty = success).
     */
    public static function save( $data ) {
        $errors = array();

        $company     = sanitize_text_field( $data['wpyogfleet_company_name'] ?? '' );
        $prefix      = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', $data['wpyogfleet_booking_prefix'] ?? '' ) );
        $email       = sanitize_email( $data['wpyogfleet_ops_email'] ?? '' );
        $phone       = sanitize_text_field( $data['wpyogfleet_ops_phone'] ?? '' );
        $review_link = esc_url_raw( trim( $data['wpyogfleet_google_review_link'] ?? '' ) );

        if ( ! $company ) $errors[] = 'Company name is required.';
        if ( ! $prefix )  $errors[] = 'Booking prefix is required (letters/numbers only).';
        if ( $email && ! is_email( $email ) ) $errors[] = 'Invalid operations email.';

        if ( empty( $errors ) ) {
            update_option( self::COMPANY_NAME,       $company );
            update_option( self::BOOKING_PREFIX,     $prefix );
            update_option( self::OPS_EMAIL,          $email );
            update_option( self::OPS_PHONE,          $phone );
            update_option( self::GOOGLE_REVIEW_LINK, $review_link );
        }

        return $errors;
    }
}
