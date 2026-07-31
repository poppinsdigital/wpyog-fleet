<?php
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- All $_GET reads here are read-only display routing parameters, not data-modifying operations
if ( ! defined( 'ABSPATH' ) ) exit;

class WPYOGFLEET_Admin {

    public function init() {
        add_action( 'admin_menu',            array( $this, 'register_menus' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'admin_init',            array( $this, 'handle_admin_posts' ) );
        add_action( 'admin_init',            array( $this, 'maybe_redirect_to_setup' ) );
        add_action( 'admin_post_wpyogfleet_skip_setup', array( $this, 'handle_skip_setup' ) );
    }

    public function maybe_redirect_to_setup() {
        if ( get_transient( 'wpyogfleet_activation_redirect' ) ) {
            delete_transient( 'wpyogfleet_activation_redirect' );
            if ( ! get_option( WPYOGFLEET_Settings::SETUP_COMPLETE ) ) {
                wp_safe_redirect( admin_url( 'admin.php?page=wpyogfleet-setup' ) );
                exit;
            }
        }
    }

    public function handle_skip_setup() {
        check_admin_referer( 'wpyogfleet_skip_setup' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to perform this action.', 'wpyog-fleet' ) );
        }
        update_option( WPYOGFLEET_Settings::SETUP_COMPLETE, '1' );
        wp_safe_redirect( admin_url( 'admin.php?page=wpyogfleet-dashboard' ) );
        exit;
    }

    // -------------------------------------------------------------------------
    // WPYOG ICON FONT
    // -------------------------------------------------------------------------

    /**
     * Build a data-URI SVG from the wpyog glyph so WordPress can use it
     * as a native menu icon (no external image needed).
     */
    private function wpyog_icon_svg() {
        // Glyph path extracted from wpyog.svg, y-flipped for SVG coordinate space.
        $path = 'M255 501c-135-1-244-111-244-246 1-135 111-244 246-244 135 1 244 111 244 246-1 135-111 244-246 244z'
              . ' M67 255c0 27 22 49 50 49 27 1 49-21 49-49 0-27-22-49-49-49-27 0-50 22-50 49z'
              . ' M372 421c-5-6-11-16-16-29l-89-236-18-48c-7-18-15-31-23-39-11-9-25-14-43-14'
              . '-11 0-20 3-27 8-7 6-11 14-11 25 0 8 3 15 8 21 5 5 12 8 21 8 9 0 15-3 20-7'
              . ' 6-4 8-11 8-19 1-11-4-19-14-25 1 0 2 0 3 0 20 0 36 14 46 43l20 50-101 239'
              . ' c-6 13-13 21-19 25-6 4-12 6-19 6l0 12c18-3 35-4 54-4 16 0 40 2 72 4l0-11'
              . ' c-9 0-16-1-21-1-5-1-9-3-13-6-3-2-5-7-5-13 0-6 3-15 7-27l67-163 60 159'
              . ' c4 11 6 19 6 25 0 9-3 15-10 19-6 4-17 7-31 7l0 11c19-1 35-1 46-1 16 0 30 1 42 2l0-11'
              . ' c-7-1-14-5-20-10z';

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">'
             . '<g transform="translate(0,512) scale(1,-1)">'
             . '<path fill="%23a0a5aa" d="' . $path . '"/>'
             . '</g></svg>';

        return 'data:image/svg+xml;base64,' . base64_encode( $svg );
    }

    /**
     * Icon font styles are now added via wp_add_inline_style() in enqueue_assets().
     * Method kept for compatibility; no longer used directly.
     */
    public function inline_icon_styles() {}

    // -------------------------------------------------------------------------
    // MENUS
    // -------------------------------------------------------------------------

    public function register_menus() {
        $cap = 'wpyogfleet_manage';

        add_menu_page(
            'WPYog Fleet',
            'Fleet',
            $cap,
            'wpyogfleet-dashboard',
            array( $this, 'page_dashboard' ),
            $this->wpyog_icon_svg(),
            80
        );

        add_submenu_page( 'wpyogfleet-dashboard', 'Dashboard',  'Dashboard',  $cap, 'wpyogfleet-dashboard',  array( $this, 'page_dashboard' ) );
        add_submenu_page( 'wpyogfleet-dashboard', 'Bookings',   'Bookings',   $cap, 'wpyogfleet-bookings',   array( $this, 'page_bookings' ) );
        add_submenu_page( 'wpyogfleet-dashboard', 'Companies',  'Companies',  $cap, 'wpyogfleet-companies',  array( $this, 'page_companies' ) );
        add_submenu_page( 'wpyogfleet-dashboard', 'Vehicles',   'Vehicles',   $cap, 'wpyogfleet-vehicles',   array( $this, 'page_vehicles' ) );
        add_submenu_page( 'wpyogfleet-dashboard', 'Drivers',    'Drivers',    $cap, 'wpyogfleet-drivers',    array( $this, 'page_drivers' ) );
        add_submenu_page( 'wpyogfleet-dashboard', 'Reports',      'Reports',       $cap,        'wpyogfleet-reports',    array( $this, 'page_reports' ) );
        add_submenu_page( 'wpyogfleet-dashboard', 'Activity Logs','Activity Logs', 'wpyogfleet_admin', 'wpyogfleet-logs',       array( $this, 'page_logs' ) );
        add_submenu_page( 'wpyogfleet-dashboard', 'Staff Users',  'Staff Users',   'wpyogfleet_admin', 'wpyogfleet-staff',      array( $this, 'page_staff' ) );
        add_submenu_page( 'wpyogfleet-dashboard', 'Settings',     'Settings',      'wpyogfleet_admin', 'wpyogfleet-settings',   array( $this, 'page_settings' ) );
        // Setup wizard — hidden from nav, accessible by URL
        add_submenu_page( null,            'Setup',        'Setup',         'wpyogfleet_admin', 'wpyogfleet-setup',      array( $this, 'page_setup' ) );
    }

    // -------------------------------------------------------------------------
    // ASSETS
    // -------------------------------------------------------------------------

    public function enqueue_assets( $hook ) {
        // Only load on our pages
        if ( strpos( $hook, 'wpyogfleet-' ) === false ) {
            return;
        }

        wp_enqueue_style(
            'wpyogfleet-admin',
            WPYOGFLEET_PLUGIN_URL . 'admin/css/jtb-admin.css',
            array(),
            WPYOGFLEET_VERSION
        );

        // Icon font — registered as inline style attached to wpyogfleet-admin handle.
        $font_url  = WPYOGFLEET_PLUGIN_URL . 'admin/css/fonts/';
        $icon_css  = '@font-face{font-family:"wpyog";';
        $icon_css .= 'src:url("' . esc_url( $font_url . 'wpyog.eot' ) . '");';
        $icon_css .= 'src:url("' . esc_url( $font_url . 'wpyog.eot?#iefix' ) . '") format("embedded-opentype"),';
        $icon_css .= 'url("' . esc_url( $font_url . 'wpyog.woff' ) . '") format("woff"),';
        $icon_css .= 'url("' . esc_url( $font_url . 'wpyog.ttf' ) . '") format("truetype"),';
        $icon_css .= 'url("' . esc_url( $font_url . 'wpyog.svg#wpyog' ) . '") format("svg");';
        $icon_css .= 'font-weight:normal;font-style:normal;}';
        $icon_css .= '.jtb-wpyog-icon:before{font-family:"wpyog"!important;content:"a";font-style:normal;}';
        wp_add_inline_style( 'wpyogfleet-admin', $icon_css );

        wp_enqueue_script(
            'wpyogfleet-admin',
            WPYOGFLEET_PLUGIN_URL . 'admin/js/jtb-admin.js',
            array( 'jquery' ),
            WPYOGFLEET_VERSION,
            true
        );

        wp_localize_script( 'wpyogfleet-admin', 'WPYOGFLEET', array(
            'ajax_url'  => admin_url( 'admin-ajax.php' ),
            'admin_url' => admin_url(),
            'nonce'     => wp_create_nonce( 'wpyogfleet_nonce' ),
            'currency'  => '₹',
        ) );
    }

    // -------------------------------------------------------------------------
    // PAGE HANDLERS
    // -------------------------------------------------------------------------

    public function page_dashboard() {
        $this->render( 'dashboard' );
    }

    public function page_bookings() {
        // Redirects (add/edit/delete) are handled in handle_admin_posts() on admin_init.
        // Here we only render views.
        $action = sanitize_text_field( wp_unslash( $_GET['action'] ?? 'list' ) );

        switch ( $action ) {
            case 'add':
                $this->render( 'booking-form', array( 'booking' => null ) );
                break;

            case 'edit':
                $id      = absint( $_GET['id'] ?? 0 );
                $booking = WPYOGFLEET_DB::get_booking( $id );
                if ( ! $booking ) { wp_die( 'Booking not found.' ); }
                $this->render( 'booking-form', array( 'booking' => $booking ) );
                break;

            case 'view':
                $id      = absint( $_GET['id'] ?? 0 );
                $booking = WPYOGFLEET_DB::get_booking( $id );
                if ( ! $booking ) { wp_die( 'Booking not found.' ); }
                $payments = WPYOGFLEET_DB::get_payments( $id );
                $expenses = WPYOGFLEET_DB::get_expenses( $id );
                $this->render( 'booking-detail', compact( 'booking', 'payments', 'expenses' ) );
                break;

            default:
                $this->render( 'bookings-list' );
        }
    }

    /**
     * Handle form POSTs and GET-based deletes for Vehicles, Drivers, Companies.
     * Runs on admin_init — before any output — so wp_redirect() works correctly.
     */
    public function handle_admin_posts() {
        if ( ! is_admin() ) return;
        $page = sanitize_text_field( wp_unslash( $_GET['page'] ?? '' ) );

        // ── Vehicles ──────────────────────────────────────────────────────────
        if ( $page === 'wpyogfleet-vehicles' ) {
            if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['wpyogfleet_vehicle_nonce'] ) ) {
                check_admin_referer( 'wpyogfleet_vehicle_save', 'wpyogfleet_vehicle_nonce' );
                $id = absint( $_POST['vehicle_id'] ?? 0 );
                if ( $id ) {
                    WPYOGFLEET_DB::update_vehicle( $id, WPYOGFLEET_Input::vehicle() );
                } else {
                    WPYOGFLEET_DB::insert_vehicle( WPYOGFLEET_Input::vehicle() );
                }
                wp_safe_redirect( admin_url( 'admin.php?page=wpyogfleet-vehicles&saved=1&_wpnonce=' . wp_create_nonce( 'wpyogfleet_notice' ) ) );
                exit;
            }
            $action = sanitize_text_field( wp_unslash( $_GET['action'] ?? '' ) );
            if ( $action === 'delete' && current_user_can( 'wpyogfleet_admin' ) ) {
                $vid = absint( $_GET['id'] ?? 0 );
                check_admin_referer( 'wpyogfleet_delete_vehicle_' . $vid );
                WPYOGFLEET_DB::delete_vehicle( $vid );
                wp_safe_redirect( admin_url( 'admin.php?page=wpyogfleet-vehicles&deleted=1&_wpnonce=' . wp_create_nonce( 'wpyogfleet_notice' ) ) );
                exit;
            }
        }

        // ── Drivers ───────────────────────────────────────────────────────────
        if ( $page === 'wpyogfleet-drivers' ) {
            if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['wpyogfleet_driver_nonce'] ) ) {
                check_admin_referer( 'wpyogfleet_driver_save', 'wpyogfleet_driver_nonce' );
                $id = absint( $_POST['driver_id'] ?? 0 );
                if ( $id ) {
                    WPYOGFLEET_DB::update_driver( $id, WPYOGFLEET_Input::driver() );
                } else {
                    WPYOGFLEET_DB::insert_driver( WPYOGFLEET_Input::driver() );
                }
                wp_safe_redirect( admin_url( 'admin.php?page=wpyogfleet-drivers&saved=1&_wpnonce=' . wp_create_nonce( 'wpyogfleet_notice' ) ) );
                exit;
            }
            $action = sanitize_text_field( wp_unslash( $_GET['action'] ?? '' ) );
            if ( $action === 'delete' && current_user_can( 'wpyogfleet_admin' ) ) {
                $did = absint( $_GET['id'] ?? 0 );
                check_admin_referer( 'wpyogfleet_delete_driver_' . $did );
                WPYOGFLEET_DB::delete_driver( $did );
                wp_safe_redirect( admin_url( 'admin.php?page=wpyogfleet-drivers&deleted=1&_wpnonce=' . wp_create_nonce( 'wpyogfleet_notice' ) ) );
                exit;
            }
        }

        // ── Bookings (add/edit redirect + delete) ────────────────────────────
        if ( $page === 'wpyogfleet-bookings' ) {
            $action = sanitize_text_field( wp_unslash( $_GET['action'] ?? '' ) );

            if ( $action === 'add' && isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD']
                 && isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'wpyogfleet_booking_save' ) ) {
                $id = WPYOGFLEET_DB::insert_booking( WPYOGFLEET_Input::booking() );
                if ( $id ) {
                    $b = WPYOGFLEET_DB::get_booking( $id );
                    WPYOGFLEET_DB::log_activity( 'booking_created', 'booking', $id,
                        $b->booking_number ?? 'Booking #' . $id,
                        sanitize_text_field( wp_unslash( $_POST['customer_name'] ?? '' ) ) . ' — '
                        . sanitize_text_field( wp_unslash( $_POST['pickup_location'] ?? '' ) ) . ' → '
                        . sanitize_text_field( wp_unslash( $_POST['drop_location'] ?? '' ) ) );
                    wp_safe_redirect( admin_url( 'admin.php?page=wpyogfleet-bookings&action=view&id=' . $id . '&saved=1&_wpnonce=' . wp_create_nonce( 'wpyogfleet_notice' ) ) );
                    exit;
                }
            }

            if ( $action === 'edit' && isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD']
                 && isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'wpyogfleet_booking_save' ) ) {
                $id = absint( $_GET['id'] ?? 0 );
                if ( $id ) {
                    WPYOGFLEET_DB::update_booking( $id, WPYOGFLEET_Input::booking() );
                    $b = WPYOGFLEET_DB::get_booking( $id );
                    WPYOGFLEET_DB::log_activity( 'booking_updated', 'booking', $id,
                        $b->booking_number ?? 'Booking #' . $id, 'Booking details updated' );
                    wp_safe_redirect( admin_url( 'admin.php?page=wpyogfleet-bookings&action=view&id=' . $id . '&saved=1&_wpnonce=' . wp_create_nonce( 'wpyogfleet_notice' ) ) );
                    exit;
                }
            }

            if ( $action === 'delete' && current_user_can( 'wpyogfleet_admin' ) ) {
                $del_id = absint( $_GET['id'] ?? 0 );
                if ( $del_id && check_admin_referer( 'wpyogfleet_delete_booking_' . $del_id ) ) {
                    $del_b = WPYOGFLEET_DB::get_booking( $del_id );
                    WPYOGFLEET_DB::log_activity( 'booking_deleted', 'booking', $del_id,
                        $del_b->booking_number ?? 'Booking #' . $del_id,
                        'Booking deleted — ' . ( $del_b->customer_name ?? '' ) );
                    WPYOGFLEET_DB::delete_booking( $del_id );
                    wp_safe_redirect( admin_url( 'admin.php?page=wpyogfleet-bookings&deleted=1&_wpnonce=' . wp_create_nonce( 'wpyogfleet_notice' ) ) );
                    exit;
                }
            }
        }

        // ── Companies (POST form; AJAX is the primary path but this is the fallback) ─
        if ( $page === 'wpyogfleet-companies' ) {
            if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['wpyogfleet_company_nonce'] ) ) {
                check_admin_referer( 'wpyogfleet_company_save', 'wpyogfleet_company_nonce' );
                $id = absint( $_POST['company_id'] ?? 0 );
                if ( $id ) {
                    WPYOGFLEET_DB::update_company( $id, WPYOGFLEET_Input::company() );
                    WPYOGFLEET_DB::log_activity( 'company_updated', 'company', $id,
                        sanitize_text_field( wp_unslash( $_POST['company_name'] ?? '' ) ), 'Company updated' );
                } else {
                    $new_id = WPYOGFLEET_DB::insert_company( WPYOGFLEET_Input::company() );
                    WPYOGFLEET_DB::log_activity( 'company_created', 'company', $new_id,
                        sanitize_text_field( wp_unslash( $_POST['company_name'] ?? '' ) ), 'Company created' );
                }
                wp_safe_redirect( admin_url( 'admin.php?page=wpyogfleet-companies&saved=1&_wpnonce=' . wp_create_nonce( 'wpyogfleet_notice' ) ) );
                exit;
            }
            $action = sanitize_text_field( wp_unslash( $_GET['action'] ?? '' ) );
            if ( $action === 'delete' && current_user_can( 'wpyogfleet_admin' ) ) {
                $cid = absint( $_GET['id'] ?? 0 );
                check_admin_referer( 'wpyogfleet_delete_company_' . $cid );
                $co = WPYOGFLEET_DB::get_company( $cid );
                WPYOGFLEET_DB::log_activity( 'company_deleted', 'company', $cid,
                    $co->company_name ?? 'Company #' . $cid, 'Company deleted' );
                WPYOGFLEET_DB::delete_company( $cid );
                wp_safe_redirect( admin_url( 'admin.php?page=wpyogfleet-companies&deleted=1&_wpnonce=' . wp_create_nonce( 'wpyogfleet_notice' ) ) );
                exit;
            }
        }

        // ── Setup wizard ──────────────────────────────────────────────────────
        if ( $page === 'wpyogfleet-setup' && isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['wpyogfleet_setup_nonce'] ) ) {
            check_admin_referer( 'wpyogfleet_save_setup', 'wpyogfleet_setup_nonce' );
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'You do not have permission to perform this action.', 'wpyog-fleet' ) );
            }
            $errors = WPYOGFLEET_Settings::save( WPYOGFLEET_Input::settings() );
            if ( empty( $errors ) ) {
                update_option( WPYOGFLEET_Settings::SETUP_COMPLETE, '1' );
                wp_safe_redirect( admin_url( 'admin.php?page=wpyogfleet-dashboard&setup=done' ) );
                exit;
            }
            // Errors — store in transient so page_setup() can display them
            set_transient( 'wpyogfleet_setup_errors_' . get_current_user_id(), $errors, 60 );
            wp_safe_redirect( admin_url( 'admin.php?page=wpyogfleet-setup&setup_error=1' ) );
            exit;
        }

        // ── Settings ──────────────────────────────────────────────────────────
        if ( $page === 'wpyogfleet-settings' && isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['wpyogfleet_settings_nonce'] ) ) {
            check_admin_referer( 'wpyogfleet_save_settings', 'wpyogfleet_settings_nonce' );
            $errors = WPYOGFLEET_Settings::save( WPYOGFLEET_Input::settings() );
            if ( empty( $errors ) ) {
                wp_safe_redirect( admin_url( 'admin.php?page=wpyogfleet-settings&saved=1&_wpnonce=' . wp_create_nonce( 'wpyogfleet_notice' ) ) );
                exit;
            }
            set_transient( 'wpyogfleet_settings_errors_' . get_current_user_id(), $errors, 60 );
            wp_safe_redirect( admin_url( 'admin.php?page=wpyogfleet-settings&settings_error=1' ) );
            exit;
        }

        // ── Staff users ───────────────────────────────────────────────────────
        if ( $page === 'wpyogfleet-staff' ) {
            if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['wpyogfleet_staff_nonce'] ) ) {
                check_admin_referer( 'wpyogfleet_manage_staff', 'wpyogfleet_staff_nonce' );
                $user_id = absint( $_POST['user_id'] ?? 0 );
                if ( $user_id ) {
                    $user = get_userdata( $user_id );
                    if ( $user ) $user->set_role( 'wpyogfleet_staff' );
                }
                wp_safe_redirect( admin_url( 'admin.php?page=wpyogfleet-staff&staff_msg=added' ) );
                exit;
            }
            if ( isset( $_GET['remove_staff'] ) && isset( $_GET['_wpnonce'] ) ) {
                if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wpyogfleet_remove_staff' ) ) {
                    $user_id = absint( $_GET['remove_staff'] );
                    $user    = get_userdata( $user_id );
                    if ( $user && ! user_can( $user_id, 'wpyogfleet_admin' ) ) {
                        $user->remove_role( 'wpyogfleet_staff' );
                    }
                    wp_safe_redirect( admin_url( 'admin.php?page=wpyogfleet-staff&staff_msg=removed' ) );
                    exit;
                }
            }
        }

        // ── Activity Logs (clear) ─────────────────────────────────────────────
        if ( $page === 'wpyogfleet-logs' && isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['wpyogfleet_clear_logs_nonce'] ) ) {
            check_admin_referer( 'wpyogfleet_clear_logs', 'wpyogfleet_clear_logs_nonce' );
            if ( current_user_can( 'wpyogfleet_admin' ) ) {
                WPYOGFLEET_DB::clear_logs();
                wp_safe_redirect( admin_url( 'admin.php?page=wpyogfleet-logs&cleared=1&_wpnonce=' . wp_create_nonce( 'wpyogfleet_notice' ) ) );
                exit;
            }
        }
    }

    public function page_companies() {
        $action = sanitize_text_field( wp_unslash( $_GET['action'] ?? 'list' ) );
        $edit_company = null;
        if ( $action === 'edit' ) {
            $edit_company = WPYOGFLEET_DB::get_company( absint( $_GET['id'] ?? 0 ) );
        }
        $this->render( 'companies', array( 'edit_company' => $edit_company ) );
    }

    public function page_vehicles() {
        $action = sanitize_text_field( wp_unslash( $_GET['action'] ?? 'list' ) );
        $edit_vehicle = null;
        if ( $action === 'edit' ) {
            $edit_vehicle = WPYOGFLEET_DB::get_vehicle( absint( $_GET['id'] ?? 0 ) );
        }
        $this->render( 'vehicles', array( 'edit_vehicle' => $edit_vehicle ) );
    }

    public function page_drivers() {
        $action = sanitize_text_field( wp_unslash( $_GET['action'] ?? 'list' ) );
        $edit_driver = null;
        if ( $action === 'edit' ) {
            $edit_driver = WPYOGFLEET_DB::get_driver( absint( $_GET['id'] ?? 0 ) );
        }
        $this->render( 'drivers', array( 'edit_driver' => $edit_driver ) );
    }

    public function page_reports() {
        $this->render( 'reports' );
    }

    public function page_logs() {
        if ( ! current_user_can( 'wpyogfleet_admin' ) ) {
            wp_die( 'You do not have permission to view activity logs.', 'Access Denied', array( 'response' => 403 ) );
        }
        $this->render( 'logs' );
    }

    public function page_settings() {
        if ( ! current_user_can( 'wpyogfleet_admin' ) ) wp_die( 'Access denied.', '', array( 'response' => 403 ) );
        $saved  = isset( $_GET['saved'], $_GET['_wpnonce'] ) && '1' === $_GET['saved']
            && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wpyogfleet_notice' );
        $errors = get_transient( 'wpyogfleet_settings_errors_' . get_current_user_id() ) ?: array();
        if ( ! empty( $errors ) ) delete_transient( 'wpyogfleet_settings_errors_' . get_current_user_id() );
        $this->render( 'settings', compact( 'saved', 'errors' ) );
    }

    public function page_setup() {
        if ( ! current_user_can( 'wpyogfleet_admin' ) ) wp_die( 'Access denied.', '', array( 'response' => 403 ) );
        $errors = get_transient( 'wpyogfleet_setup_errors_' . get_current_user_id() ) ?: array();
        if ( ! empty( $errors ) ) delete_transient( 'wpyogfleet_setup_errors_' . get_current_user_id() );
        $this->render( 'setup', compact( 'errors' ) );
    }

    public function page_staff() {
        if ( ! current_user_can( 'wpyogfleet_admin' ) ) wp_die( 'Access denied.', '', array( 'response' => 403 ) );
        $msg = sanitize_text_field( wp_unslash( $_GET['staff_msg'] ?? '' ) );
        // Get all staff users (wpyogfleet_staff role + wpyogfleet_manage cap)
        $staff = get_users( array(
            'role__in'   => array( 'wpyogfleet_staff', 'administrator' ),
            'orderby'    => 'display_name',
        ) );
        // All non-staff WP users for the add dropdown
        $all_users = get_users( array(
            'role__not_in' => array( 'wpyogfleet_staff' ),
            'orderby'      => 'display_name',
            'number'       => 200,
        ) );
        $this->render( 'staff-users', compact( 'staff', 'all_users', 'msg' ) );
    }

    // -------------------------------------------------------------------------
    // RENDER HELPER
    // -------------------------------------------------------------------------

    private function render( $view, $data = array() ) {
        extract( $data );
        $file = WPYOGFLEET_PLUGIN_DIR . 'admin/views/' . $view . '.php';
        if ( file_exists( $file ) ) {
            include $file;
        } else {
            echo '<div class="jtb-wrap"><p>View not found: ' . esc_html( $view ) . '</p></div>';
        }
    }

    // -------------------------------------------------------------------------
    // HELPERS (used in views)
    // -------------------------------------------------------------------------

    public static function status_badge( $status ) {
        $map = array(
            'new'       => array( 'New',       'badge-blue'   ),
            'confirmed' => array( 'Confirmed', 'badge-indigo' ),
            'ongoing'   => array( 'Ongoing',   'badge-amber'  ),
            'completed' => array( 'Completed', 'badge-green'  ),
            'closed'    => array( 'Closed',    'badge-teal'   ),
            'cancelled' => array( 'Cancelled', 'badge-red'    ),
        );
        $info = $map[ $status ] ?? array( ucfirst( $status ), 'badge-gray' );
        return '<span class="jtb-badge ' . esc_attr( $info[1] ) . '">' . esc_html( $info[0] ) . '</span>';
    }

    public static function format_currency( $amount ) {
        return '₹' . number_format( (float) $amount, 2 );
    }

    public static function format_date( $date ) {
        if ( ! $date ) return '—';
        return gmdate( 'd M Y', strtotime( $date ) );
    }

    public static function format_datetime( $dt ) {
        if ( ! $dt ) return '—';
        return gmdate( 'd M Y, h:i A', strtotime( $dt ) );
    }

    public static function trip_type_label( $type ) {
        $map = array(
            'one-way'     => 'One Way',
            'round-trip'  => 'Round Trip',
            'local'       => 'Local',
            'outstation'  => 'Outstation',
            'yatra'       => 'Yatra / Package',
            'other'       => 'Other',
        );
        return $map[ $type ] ?? ucfirst( $type );
    }

    public static function booking_type_label( $type ) {
        $map = array(
            'individual' => 'Individual',
            'corporate'  => 'Corporate',
        );
        return $map[ $type ] ?? ucfirst( $type );
    }

    public static function payment_mode_label( $mode ) {
        $map = array(
            'cash'          => 'Cash',
            'upi'           => 'UPI',
            'bank-transfer' => 'Bank Transfer',
            'card'          => 'Card',
            'other'         => 'Other',
        );
        return $map[ $mode ] ?? ucfirst( $mode );
    }

    // -------------------------------------------------------------------------
    // SHARE / NOTIFY HELPERS
    // -------------------------------------------------------------------------

    /**
     * Plain-text booking summary for WhatsApp / clipboard.
     * $for: 'customer' | 'driver' | 'ops'
     *
     * Format per v3.0.6 spec:
     *  customer — Booking#, Passenger, Driver, Vehicle, Route, Pickup DT, Pickup Loc, Drop Loc, Instructions, Status, thank-you
     *  driver   — Same minus financials, footer "-- Sent by Jagat Travels"
     *  ops      — Adds Corporate Name + Booking ID after Booking#, adds financials line, thank-you
     */
    public static function booking_message_text( $b, $for = 'customer' ) {
        // ── Common fields ──────────────────────────────────────────────────
        $company_name = WPYOGFLEET_Settings::get( WPYOGFLEET_Settings::COMPANY_NAME );
        $num          = $b->booking_number;

        // Datetime: show as "DD MMM YYYY, HH:MM" if time part is non-midnight, else just date
        $pickup_dt = '';
        if ( ! empty( $b->start_date ) ) {
            $ts   = strtotime( $b->start_date );
            $has_time = ( gmdate( 'H:i', $ts ) !== '00:00' );
            $pickup_dt = $has_time
                ? gmdate( 'd M Y', $ts ) . ', ' . gmdate( 'h:i A', $ts )
                : gmdate( 'd M Y', $ts );
        }

        $route     = trim( $b->pickup_location ?: '' ) . ' → ' . trim( $b->drop_location ?: '' );
        $pickup_loc = trim( $b->pickup_location ?: '—' );
        $drop_loc   = trim( $b->drop_location ?: '—' );
        $notes      = trim( $b->notes ?: '' );

        // Passenger: use passenger_name if set, else customer_name
        $pax_name   = ! empty( $b->passenger_name )  ? $b->passenger_name  : $b->customer_name;
        $pax_mobile = ! empty( $b->passenger_mobile ) ? $b->passenger_mobile : $b->customer_mobile;
        $pax_line   = $pax_name . ( $pax_mobile ? ' (' . $pax_mobile . ')' : '' );

        // Driver / Vehicle — outsourced bookings use the manually-typed fields instead
        // of the internal fleet database.
        $is_outsourced = ! empty( $b->is_outsourced );
        if ( $is_outsourced ) {
            $drv_name   = ! empty( $b->outsourced_driver_name ) ? strtoupper( $b->outsourced_driver_name ) : 'TO BE ASSIGNED';
            $drv_mobile = $b->outsourced_driver_mobile ?? '';
            $drv_line   = $drv_name . ( $drv_mobile ? ' (' . $drv_mobile . ')' : '' ) . ' [Outsourced]';
            $veh_line   = ( $b->outsourced_vehicle_number ?: '—' ) . ' [Outsourced]';
        } else {
            // Driver: UPPERCASE NAME (mobile)
            $drv_name   = ! empty( $b->driver_name ) ? strtoupper( $b->driver_name ) : 'TO BE ASSIGNED';
            $drv_mobile = $b->driver_mobile ?? '';
            $drv_line   = $drv_name . ( $drv_mobile ? ' (' . $drv_mobile . ')' : '' );

            // Vehicle: Name (RegNo) — show reg if available
            $veh_name = $b->vehicle_name ?: '—';
            $veh_reg  = $b->registration_number ?? '';
            $veh_line = $veh_reg ? $veh_name . ' (' . $veh_reg . ')' : $veh_name;
        }

        // Status
        $status_map = array( 'new' => 'New', 'confirmed' => 'Confirmed', 'ongoing' => 'Ongoing', 'completed' => 'Completed', 'closed' => 'Closed', 'cancelled' => 'Cancelled' );
        $status_str = $status_map[ $b->status ] ?? ucfirst( $b->status );

        // Financials
        $amt  = '₹' . number_format( (float) $b->booking_amount, 0 );
        $paid = '₹' . number_format( (float) $b->total_paid, 0 );
        $due  = '₹' . number_format( max( 0, (float) $b->balance_amount ), 0 );

        // ── Build message ─────────────────────────────────────────────────
        if ( $for === 'driver' ) {
            // Point 7: no financials, footer "-- Sent by Jagat Travels"
            $lines = array(
                '*Booking Details*',
                'Booking #' . $num,
                'Passenger: ' . $pax_line,
                'Driver: ' . $drv_line,
                'Vehicle: ' . $veh_line,
                'Route: ' . $route,
                'Pickup Datetime: ' . ( $pickup_dt ?: '—' ),
                'Pickup Location: ' . $pickup_loc,
                'Drop Location: ' . $drop_loc,
            );
            if ( $notes ) $lines[] = 'Instructions: ' . $notes;
            $lines[] = 'Status: ' . $status_str;
            $lines[] = '-- Sent by ' . $company_name;

        } elseif ( $for === 'ops' ) {
            // Point 8: adds Corporate Name + Booking ID, adds financials
            $lines = array( '*Booking Details*' );
            $lines[] = 'Booking #' . $num;
            if ( ! empty( $b->company_name ) ) {
                $lines[] = 'Corporate: ' . $b->company_name;
            }
            if ( ! empty( $b->booking_ref ) ) {
                $lines[] = 'Booking ID (Ref): ' . $b->booking_ref;
            }
            $lines[] = 'Passenger: ' . $pax_line;
            $lines[] = 'Driver: ' . $drv_line;
            $lines[] = 'Vehicle: ' . $veh_line;
            $lines[] = 'Route: ' . $route;
            $lines[] = 'Pickup Datetime: ' . ( $pickup_dt ?: '—' );
            $lines[] = 'Pickup Location: ' . $pickup_loc;
            $lines[] = 'Drop Location: ' . $drop_loc;
            if ( $notes ) $lines[] = 'Instructions: ' . $notes;
            $lines[] = 'Status: ' . $status_str;
            $lines[] = 'Total: ' . $amt . ' | Paid: ' . $paid . ' | Balance: ' . $due;
            $lines[] = 'Thank you for choosing ' . $company_name . '.';

        } else {
            // Point 6: customer (default)
            $lines = array(
                '*Booking Details*',
                'Booking #' . $num,
                'Passenger: ' . $pax_line,
                'Driver: ' . $drv_line,
                'Vehicle: ' . $veh_line,
                'Route: ' . $route,
                'Pickup Datetime: ' . ( $pickup_dt ?: '—' ),
                'Pickup Location: ' . $pickup_loc,
                'Drop Location: ' . $drop_loc,
            );
            if ( $notes ) $lines[] = 'Instructions: ' . $notes;
            $lines[] = 'Status: ' . $status_str;
            $lines[] = 'Thank you for choosing ' . $company_name . '.';
        }

        return implode( "\n", $lines );
    }

    /**
     * Google review request message — sent to the customer after a trip is
     * marked Completed. Uses passenger name if set (the actual traveller),
     * else the booking's customer name.
     */
    public static function review_request_message( $b ) {
        $company_name = WPYOGFLEET_Settings::get( WPYOGFLEET_Settings::COMPANY_NAME );
        $review_link  = WPYOGFLEET_Settings::get( WPYOGFLEET_Settings::GOOGLE_REVIEW_LINK );
        $name         = ! empty( $b->passenger_name ) ? $b->passenger_name : $b->customer_name;

        $lines = array(
            'Hi ' . $name . ',',
            'Thank you for choosing ' . $company_name . '. We hope you had a comfortable and pleasant journey.',
            "If you were happy with our service, we'd really appreciate it if you could take a minute to share your experience on Google. Your review helps other travellers choose us with confidence.",
        );
        if ( $review_link ) {
            $lines[] = '⭐ Leave a review: ' . $review_link;
        }
        $lines[] = 'Thank you for your support. We look forward to serving you again!';
        $lines[] = '– Team ' . $company_name;

        return implode( "\n\n", $lines );
    }

    /**
     * Mobile number the review request should go to — passenger's mobile if
     * set (the actual traveller), else the booking's customer mobile.
     */
    public static function review_request_mobile( $b ) {
        return ! empty( $b->passenger_mobile ) ? $b->passenger_mobile : $b->customer_mobile;
    }

    /**
     * HTML email body for booking notification.
     * $for: 'customer' | 'driver' | 'ops'
     */
    public static function booking_email_html( $b, $for = 'customer' ) {
        $site  = get_bloginfo( 'name' );
        $num   = $b->booking_number;
        $date  = self::format_datetime( $b->start_date );
        $route = esc_html( ( $b->pickup_location ?: '?' ) . ' → ' . ( $b->drop_location ?: '?' ) );
        $amt   = '₹' . number_format( (float) $b->booking_amount, 2 );
        $paid  = '₹' . number_format( (float) $b->total_paid, 2 );
        $due   = '₹' . number_format( max( 0, (float) $b->balance_amount ), 2 );
        $status_map = array( 'new'=>'New','confirmed'=>'Confirmed','ongoing'=>'Ongoing','completed'=>'Completed','closed'=>'Closed','cancelled'=>'Cancelled' );
        $status = $status_map[ $b->status ] ?? ucfirst( $b->status );

        $heading = $for === 'driver'
            ? 'Trip Assignment'
            : ( $for === 'ops' ? 'New Booking Alert' : 'Booking Confirmation' );

        $rows = array();
        $rows[] = array( 'Booking #',  esc_html( $num ) );
        $rows[] = array( 'Date',       esc_html( $date ) );
        $rows[] = array( 'Route',      $route );
        $rows[] = array( 'Customer',   esc_html( $b->customer_name ) . ' · ' . esc_html( $b->customer_mobile ) );
        if ( $b->customer_email ) $rows[] = array( 'Email', esc_html( $b->customer_email ) );
        if ( ! empty( $b->passenger_name ) ) {
            $pax_val = esc_html( $b->passenger_name );
            if ( ! empty( $b->passenger_mobile ) ) $pax_val .= ' · ' . esc_html( $b->passenger_mobile );
            $rows[] = array( 'Passenger', $pax_val );
        }
        if ( ! empty( $b->is_outsourced ) ) {
            $rows[] = array( 'Vehicle', esc_html( $b->outsourced_vehicle_number ?: '—' ) . ' <span style="color:#6b7280;font-weight:400">(Outsourced)</span>' );
            $rows[] = array( 'Driver',  esc_html( $b->outsourced_driver_name ?: 'TBA' ) . ( $b->outsourced_driver_mobile ? ' · ' . esc_html( $b->outsourced_driver_mobile ) : '' ) . ' <span style="color:#6b7280;font-weight:400">(Outsourced)</span>' );
        } else {
            $rows[] = array( 'Vehicle', esc_html( $b->vehicle_name ?: '—' ) );
            $rows[] = array( 'Driver',  esc_html( $b->driver_name ?: 'TBA' ) . ( $b->driver_mobile ? ' · ' . esc_html( $b->driver_mobile ) : '' ) );
        }
        $rows[] = array( 'Total',      esc_html( $amt ) );
        $rows[] = array( 'Paid',       esc_html( $paid ) );
        $rows[] = array( 'Balance',    esc_html( $due ) );
        $rows[] = array( 'Status',     esc_html( $status ) );
        if ( $b->notes ) $rows[] = array( 'Notes', esc_html( $b->notes ) );

        $table = '';
        foreach ( $rows as $r ) {
            $table .= '<tr><td style="padding:6px 12px 6px 0;color:#6b7280;font-size:13px;white-space:nowrap;vertical-align:top">' . $r[0] . '</td>'
                    . '<td style="padding:6px 0;font-size:13px;font-weight:600;color:#111827">' . $r[1] . '</td></tr>';
        }

        $detail_url = admin_url( 'admin.php?page=wpyogfleet-bookings&action=view&id=' . absint( $b->id ) );

        return '<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;background:#f3f4f6;margin:0;padding:20px">'
             . '<div style="max-width:520px;margin:0 auto;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.1)">'
             . '<div style="background:#4f46e5;padding:20px 24px"><h2 style="color:#fff;margin:0;font-size:18px">' . esc_html( $heading ) . '</h2>'
             . '<p style="color:#c7d2fe;margin:4px 0 0;font-size:13px">' . esc_html( $site ) . '</p></div>'
             . '<div style="padding:24px">'
             . '<table cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse">' . $table . '</table>'
             . ( $for === 'ops' ? '<p style="margin:20px 0 0"><a href="' . esc_url( $detail_url ) . '" style="display:inline-block;background:#4f46e5;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;font-size:13px">View Booking</a></p>' : '' )
             . '</div>'
             . '<div style="padding:12px 24px;background:#f9fafb;border-top:1px solid #e5e7eb;font-size:11px;color:#9ca3af">'
             . esc_html( $site ) . ' — automated notification</div>'
             . '</div></body></html>';
    }

    public static function booking_email_subject( $b, $for = 'customer' ) {
        $site = get_bloginfo( 'name' );
        $num  = $b->booking_number;
        if ( $for === 'driver' )   return '[' . $site . '] Trip Assignment: ' . $num;
        if ( $for === 'ops' )      return '[' . $site . '] New Booking: ' . $num;
        return '[' . $site . '] Your Booking Confirmation: ' . $num;
    }
}
