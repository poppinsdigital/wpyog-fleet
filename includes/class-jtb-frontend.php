<?php
// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce is verified at the entry of each AJAX handler via check_ajax_referer()
/**
 * Frontend Staff Panel — shortcode [wpyog_fleet]
 * Place this shortcode on any WordPress page to give staff a full booking management UI.
 * The page is protected: only users with the wpyogfleet_manage capability can see the panel.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class WPYOGFLEET_Frontend {

    public function init() {
        add_shortcode( 'wpyog_fleet', array( $this, 'render_shortcode' ) );
        add_action( 'wp_enqueue_scripts',       array( $this, 'enqueue_assets' ) );
        add_filter( 'body_class',               array( $this, 'add_body_class' ) );

        // AJAX handlers for frontend (same as admin but using nopriv-safe nonce)
        $actions = array(
            'wpyogfleet_fe_get_bookings',
            'wpyogfleet_fe_get_booking',
            'wpyogfleet_fe_save_booking',
            'wpyogfleet_fe_add_payment',
            'wpyogfleet_fe_void_payment',
            'wpyogfleet_fe_edit_payment',
            'wpyogfleet_fe_delete_payment',
            'wpyogfleet_fe_add_expense',
            'wpyogfleet_fe_void_expense',
            'wpyogfleet_fe_edit_expense',
            'wpyogfleet_fe_delete_expense',
            'wpyogfleet_fe_update_status',
            'wpyogfleet_fe_mark_review_sent',
            'wpyogfleet_fe_get_vehicles',
            'wpyogfleet_fe_get_drivers',
            'wpyogfleet_fe_get_stats',
            // Fleet management
            'wpyogfleet_fe_get_vehicle_list',
            'wpyogfleet_fe_save_vehicle',
            'wpyogfleet_fe_delete_vehicle',
            'wpyogfleet_fe_get_driver_list',
            'wpyogfleet_fe_save_driver',
            'wpyogfleet_fe_delete_driver',
            // Reports
            'wpyogfleet_fe_get_reports',
            // Companies
            'wpyogfleet_fe_get_companies',
            'wpyogfleet_fe_save_company',
            'wpyogfleet_fe_delete_company',
            // Share / Email
            'wpyogfleet_fe_send_booking_email',
            // Calendar
            'wpyogfleet_fe_get_calendar_bookings',
        );
        foreach ( $actions as $action ) {
            add_action( 'wp_ajax_' . $action, array( $this, str_replace( 'wpyogfleet_fe_', 'handle_', $action ) ) );
        }
    }

    // -------------------------------------------------------------------------
    // BODY CLASS — adds 'jtbfe-page' when the shortcode page is rendered,
    // so CSS can suppress the theme header/footer template parts.
    // -------------------------------------------------------------------------

    public function add_body_class( $classes ) {
        if ( ! is_singular() ) return $classes;
        global $post;
        if ( $post && has_shortcode( $post->post_content, 'wpyog_fleet' ) ) {
            $classes[] = 'jtbfe-page';
        }
        return $classes;
    }

    // -------------------------------------------------------------------------
    // ASSETS
    // -------------------------------------------------------------------------

    public function enqueue_assets() {
        if ( ! is_singular() ) return;
        global $post;
        if ( ! $post || strpos( $post->post_content, 'wpyog_fleet' ) === false ) return;

        wp_enqueue_style(
            'wpyogfleet-frontend',
            WPYOGFLEET_PLUGIN_URL . 'public/css/jtb-frontend.css',
            array(),
            WPYOGFLEET_VERSION
        );
        wp_enqueue_script(
            'wpyogfleet-frontend',
            WPYOGFLEET_PLUGIN_URL . 'public/js/jtb-frontend.js',
            array( 'jquery' ),
            WPYOGFLEET_VERSION,
            true
        );
        wp_localize_script( 'wpyogfleet-frontend', 'WPYOGFLEETFE', array(
            'ajax_url'   => admin_url( 'admin-ajax.php' ),
            'nonce'      => wp_create_nonce( 'wpyogfleet_fe_nonce' ),
            'currency'   => '₹',
            'user'       => wp_get_current_user()->display_name,
            'can_delete' => current_user_can( 'wpyogfleet_admin' ) ? '1' : '0',
            'can_edit'   => '1',
            'site_name'  => WPYOGFLEET_Settings::get( WPYOGFLEET_Settings::COMPANY_NAME ),
            'ops_email'  => WPYOGFLEET_Settings::get( WPYOGFLEET_Settings::OPS_EMAIL ),
            'ops_phone'  => WPYOGFLEET_Settings::get( WPYOGFLEET_Settings::OPS_PHONE ),
            'review_link' => WPYOGFLEET_Settings::get( WPYOGFLEET_Settings::GOOGLE_REVIEW_LINK ),
        ) );
    }

    // -------------------------------------------------------------------------
    // SHORTCODE
    // -------------------------------------------------------------------------

    public function render_shortcode( $atts ) {
        // Not logged in — show login form
        if ( ! is_user_logged_in() ) {
            return $this->render_login_form( false );
        }
        // Logged in but no access — show insufficient-permissions message
        if ( ! current_user_can( 'wpyogfleet_manage' ) ) {
            return $this->render_login_form( true );
        }

        ob_start();
        $this->render_panel();
        return ob_get_clean();
    }

    // -------------------------------------------------------------------------
    // LOGIN FORM — shown when user is not logged in or has no wpyogfleet_manage cap
    // -------------------------------------------------------------------------

    private function render_login_form( $no_access = false ) {
        $redirect = get_permalink();

        // Login form styles — attached to wpyogfleet-frontend handle, output via wp_head().
        wp_add_inline_style( 'wpyogfleet-frontend',
            'html, body { height: 100%; margin: 0; padding: 0; }' .
            'body.jtbfe-page { background: #f0f4ff !important; }' .
            'body.jtbfe-page .site-header,' .
            'body.jtbfe-page header,' .
            'body.jtbfe-page .site-footer,' .
            'body.jtbfe-page footer { display: none !important; }' .
            '.jtbfe-login-wrap {' .
            '    display: flex !important;' .
            '    align-items: center !important;' .
            '    justify-content: center !important;' .
            '    min-height: 100vh !important;' .
            '    min-height: 100dvh !important;' .
            '    padding: 32px 16px !important;' .
            '    background: #f0f4ff !important;' .
            '    box-sizing: border-box !important;' .
            '}'
        );

        ob_start();
        ?>
        <div class="jtbfe-login-wrap">
            <div class="jtbfe-login-card">
                <div class="jtbfe-login-logo">
                    <svg viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg" width="32" height="32">
                        <g transform="translate(0,512) scale(1,-1)">
                            <path fill="currentColor" d="M255 501c-135-1-244-111-244-246 1-135 111-244 246-244 135 1 244 111 244 246-1 135-111 244-246 244z M67 255c0 27 22 49 50 49 27 1 49-21 49-49 0-27-22-49-49-49-27 0-50 22-50 49z M372 421c-5-6-11-16-16-29l-89-236-18-48c-7-18-15-31-23-39-11-9-25-14-43-14-11 0-20 3-27 8-7 6-11 14-11 25 0 8 3 15 8 21 5 5 12 8 21 8 9 0 15-3 20-7 6-4 8-11 8-19 1-11-4-19-14-25 1 0 2 0 3 0 20 0 36 14 46 43l20 50-101 239c-6 13-13 21-19 25-6 4-12 6-19 6l0 12c18-3 35-4 54-4 16 0 40 2 72 4l0-11c-9 0-16-1-21-1-5-1-9-3-13-6-3-2-5-7-5-13 0-6 3-15 7-27l67-163 60 159c4 11 6 19 6 25 0 9-3 15-10 19-6 4-17 7-31 7l0 11c19-1 35-1 46-1 16 0 30 1 42 2l0-11c-7-1-14-5-20-10z"/>
                        </g>
                    </svg>
                </div>
                <h2 class="jtbfe-login-title"><?php echo esc_html( WPYOGFLEET_Settings::get( WPYOGFLEET_Settings::COMPANY_NAME ) ); ?></h2>
                <p class="jtbfe-login-sub"><?php esc_html_e( 'Booking Staff Panel', 'wpyog-fleet' ); ?></p>

                <?php if ( $no_access ) : ?>
                    <div class="jtbfe-login-notice jtbfe-login-notice--error">
                        <?php esc_html_e( 'Your account does not have booking staff access. Please contact the administrator.', 'wpyog-fleet' ); ?>
                    </div>
                    <p style="text-align:center;margin-top:16px">
                        <a href="<?php echo esc_url( wp_logout_url( get_permalink() ) ); ?>" class="jtbfe-btn-secondary">
                            <?php esc_html_e( 'Log out', 'wpyog-fleet' ); ?>
                        </a>
                    </p>
                <?php else : ?>
                    <?php
                    // Show any login error message from the redirect
                    if ( isset( $_GET['login'] ) && $_GET['login'] === 'failed' ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                        echo '<div class="jtbfe-login-notice jtbfe-login-notice--error">'
                            . esc_html__( 'Invalid username or password. Please try again.', 'wpyog-fleet' )
                            . '</div>';
                    }
                    wp_login_form( array(
                        'redirect'       => esc_url( $redirect ),
                        'form_id'        => 'jtbfe-login-form',
                        'label_username' => __( 'Username or Email', 'wpyog-fleet' ),
                        'label_password' => __( 'Password', 'wpyog-fleet' ),
                        'label_remember' => __( 'Remember Me', 'wpyog-fleet' ),
                        'label_log_in'   => __( 'Log In', 'wpyog-fleet' ),
                        'remember'       => true,
                    ) );
                    ?>
                    <p class="jtbfe-login-forgot">
                        <a href="<?php echo esc_url( wp_lostpassword_url( get_permalink() ) ); ?>">
                            <?php esc_html_e( 'Forgot password?', 'wpyog-fleet' ); ?>
                        </a>
                    </p>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_panel() {
        $user = wp_get_current_user();
        ?>
        <div id="jtbfe-app" class="jtbfe-app">

            <!-- SIDEBAR -->
            <aside class="jtbfe-sidebar" id="jtbfe-sidebar">
                <div class="jtbfe-sidebar__brand">
                    <div class="jtbfe-brand-logo">
                        <svg viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg" width="28" height="28">
                            <g transform="translate(0,512) scale(1,-1)">
                                <path fill="currentColor" d="M255 501c-135-1-244-111-244-246 1-135 111-244 246-244 135 1 244 111 244 246-1 135-111 244-246 244z M67 255c0 27 22 49 50 49 27 1 49-21 49-49 0-27-22-49-49-49-27 0-50 22-50 49z M372 421c-5-6-11-16-16-29l-89-236-18-48c-7-18-15-31-23-39-11-9-25-14-43-14-11 0-20 3-27 8-7 6-11 14-11 25 0 8 3 15 8 21 5 5 12 8 21 8 9 0 15-3 20-7 6-4 8-11 8-19 1-11-4-19-14-25 1 0 2 0 3 0 20 0 36 14 46 43l20 50-101 239c-6 13-13 21-19 25-6 4-12 6-19 6l0 12c18-3 35-4 54-4 16 0 40 2 72 4l0-11c-9 0-16-1-21-1-5-1-9-3-13-6-3-2-5-7-5-13 0-6 3-15 7-27l67-163 60 159c4 11 6 19 6 25 0 9-3 15-10 19-6 4-17 7-31 7l0 11c19-1 35-1 46-1 16 0 30 1 42 2l0-11c-7-1-14-5-20-10z"/>
                            </g>
                        </svg>
                    </div>
                    <div class="jtbfe-brand-text">
                        <span class="jtbfe-brand-name"><?php echo esc_html( WPYOGFLEET_Settings::get( WPYOGFLEET_Settings::COMPANY_NAME ) ); ?></span>
                        <span class="jtbfe-brand-sub">Fleet</span>
                    </div>
                    <button class="jtbfe-sidebar-toggle" id="jtbfe-sidebar-close" aria-label="Close menu">✕</button>
                </div>

                <nav class="jtbfe-nav">
                    <a class="jtbfe-nav__item is-active" data-view="dashboard" href="#">
                        <svg class="jtbfe-nav__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                        Dashboard
                    </a>
                    <a class="jtbfe-nav__item" data-view="bookings" href="#">
                        <svg class="jtbfe-nav__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10,9 9,9 8,9"/></svg>
                        Bookings
                    </a>
                    <a class="jtbfe-nav__item" data-view="new-booking" href="#">
                        <svg class="jtbfe-nav__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                        New Booking
                    </a>
                    <a class="jtbfe-nav__item" data-view="pending" href="#">
                        <svg class="jtbfe-nav__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12,6 12,12 16,14"/></svg>
                        Pending Payments
                        <span class="jtbfe-nav__badge" id="jtbfe-pending-badge"></span>
                    </a>
                    <div class="jtbfe-nav__section">Fleet</div>

                    <a class="jtbfe-nav__item" data-view="vehicles" href="#">
                        <svg class="jtbfe-nav__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13" rx="2"/><path d="M16 8h4l3 5v3h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                        Vehicles
                    </a>
                    <a class="jtbfe-nav__item" data-view="drivers" href="#">
                        <svg class="jtbfe-nav__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        Drivers
                    </a>
                    <a class="jtbfe-nav__item" data-view="companies" href="#">
                        <svg class="jtbfe-nav__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9,22 9,12 15,12 15,22"/></svg>
                        Companies
                    </a>
                    <a class="jtbfe-nav__item" data-view="reports" href="#">
                        <svg class="jtbfe-nav__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                        Reports
                    </a>
                </nav>

                <div class="jtbfe-sidebar__footer">
                    <div class="jtbfe-user">
                        <div class="jtbfe-user__avatar"><?php echo esc_html( strtoupper( substr( $user->display_name, 0, 1 ) ) ); ?></div>
                        <div class="jtbfe-user__info">
                            <div class="jtbfe-user__name"><?php echo esc_html( $user->display_name ); ?></div>
                            <div class="jtbfe-user__role">Booking Staff</div>
                        </div>
                    </div>
                </div>
            </aside>

            <!-- MOBILE OVERLAY -->
            <div class="jtbfe-overlay" id="jtbfe-overlay"></div>

            <!-- MAIN CONTENT -->
            <main class="jtbfe-main">
                <!-- Top bar -->
                <header class="jtbfe-topbar">
                    <button class="jtbfe-menu-btn" id="jtbfe-menu-btn" aria-label="Open menu">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="22" height="22"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                    </button>
                    <div class="jtbfe-topbar__title" id="jtbfe-view-title">Dashboard</div>
                    <div class="jtbfe-topbar__actions">
                        <span class="jtbfe-topbar__date"><?php echo esc_html( gmdate('d M Y') ); ?></span>
                    </div>
                </header>

                <!-- Views -->
                <div class="jtbfe-content">

                    <!-- DASHBOARD -->
                    <section class="jtbfe-view is-active" id="jtbfe-view-dashboard">
                        <div id="jtbfe-stats-area">
                            <div class="jtbfe-skeleton-grid">
                                <div class="jtbfe-skeleton"></div>
                                <div class="jtbfe-skeleton"></div>
                                <div class="jtbfe-skeleton"></div>
                                <div class="jtbfe-skeleton"></div>
                            </div>
                        </div>
                        <!-- Dashboard calendar widget -->
                        <div class="jtbfe-card" id="jtbfe-dash-cal-card" style="margin-top:16px">
                            <div class="jtbfe-card__header">
                                <h3 class="jtbfe-card__title">Bookings Calendar</h3>
                                <button class="jtbfe-text-btn" id="jtbfe-dash-cal-full-btn">Full View →</button>
                            </div>
                            <div class="jtbfe-card__body" style="padding:20px">
                                <div id="jtbfe-dash-calendar-wrap"></div>
                            </div>
                        </div>
                        <div class="jtbfe-card">
                            <div class="jtbfe-card__header">
                                <h3 class="jtbfe-card__title">Recent Bookings</h3>
                                <button class="jtbfe-text-btn" data-view="bookings">View All →</button>
                            </div>
                            <div class="jtbfe-card__body p-0" id="jtbfe-recent-bookings">
                                <div class="jtbfe-loading"><div class="jtbfe-spinner"></div></div>
                            </div>
                        </div>
                    </section>

                    <!-- BOOKINGS LIST -->
                    <section class="jtbfe-view" id="jtbfe-view-bookings">
                        <div class="jtbfe-toolbar">
                            <div class="jtbfe-search-wrap" id="jtbfe-bookings-search-wrap">
                                <svg class="jtbfe-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                <input type="text" class="jtbfe-search" id="jtbfe-search" placeholder="Search name, mobile, booking #…">
                            </div>
                            <select class="jtbfe-filter-select" id="jtbfe-filter-status">
                                <option value="">All Status</option>
                                <option value="new">New</option>
                                <option value="confirmed">Confirmed</option>
                                <option value="ongoing">Ongoing</option>
                                <option value="completed">Completed</option>
                                <option value="closed">Closed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                            <select class="jtbfe-filter-select" id="jtbfe-sort-select">
                                <option value="date_desc">Booking Date (Newest)</option>
                                <option value="date_asc">Booking Date (Oldest)</option>
                                <option value="id_desc">Booking ID (Newest)</option>
                                <option value="id_asc">Booking ID (Oldest)</option>
                            </select>
                            <button class="jtbfe-btn jtbfe-btn-primary" data-view="new-booking">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="14" height="14"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                New
                            </button>
                            <!-- List / Calendar toggle -->
                            <div class="jtbfe-view-toggle" id="jtbfe-bookings-toggle">
                                <button class="jtbfe-view-toggle__btn is-active" data-bview="list" title="List view">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                                </button>
                                <button class="jtbfe-view-toggle__btn" data-bview="calendar" title="Calendar view">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                </button>
                            </div>
                        </div>
                        <div id="jtbfe-bookings-list">
                            <div class="jtbfe-loading"><div class="jtbfe-spinner"></div></div>
                        </div>
                        <div id="jtbfe-bookings-cal-wrap" style="display:none">
                            <div class="jtbfe-card">
                                <div class="jtbfe-card__body" style="padding:20px">
                                    <div id="jtbfe-bookings-calendar-wrap"></div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- NEW / EDIT BOOKING -->
                    <section class="jtbfe-view" id="jtbfe-view-new-booking">
                        <div class="jtbfe-form-header">
                            <button class="jtbfe-back-btn" id="jtbfe-form-back">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><polyline points="15,18 9,12 15,6"/></svg>
                                Back
                            </button>
                            <h2 class="jtbfe-form-title" id="jtbfe-form-heading">New Booking</h2>
                        </div>
                        <?php $this->render_booking_form(); ?>
                    </section>

                    <!-- BOOKING DETAIL -->
                    <section class="jtbfe-view" id="jtbfe-view-booking-detail">
                        <div class="jtbfe-form-header">
                            <button class="jtbfe-back-btn" id="jtbfe-detail-back">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><polyline points="15,18 9,12 15,6"/></svg>
                                Back
                            </button>
                            <div>
                                <h2 class="jtbfe-form-title" id="jtbfe-detail-title">Booking Detail</h2>
                            </div>
                        </div>
                        <div id="jtbfe-detail-content">
                            <div class="jtbfe-loading"><div class="jtbfe-spinner"></div></div>
                        </div>
                    </section>

                    <!-- CALENDAR -->
                    <section class="jtbfe-view" id="jtbfe-view-calendar">
                        <div class="jtbfe-card">
                            <div class="jtbfe-card__body" style="padding:20px">
                                <div id="jtbfe-calendar-wrap"></div>
                            </div>
                        </div>
                    </section>

                    <!-- PENDING PAYMENTS -->
                    <section class="jtbfe-view" id="jtbfe-view-pending">
                        <div id="jtbfe-pending-list">
                            <div class="jtbfe-loading"><div class="jtbfe-spinner"></div></div>
                        </div>
                    </section>

                    <!-- VEHICLES -->
                    <section class="jtbfe-view" id="jtbfe-view-vehicles">
                        <div class="jtbfe-toolbar">
                            <h2 class="jtbfe-section-title">Fleet Vehicles</h2>
                            <button class="jtbfe-btn jtbfe-btn-primary" id="jtbfe-add-vehicle">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="14" height="14"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                Add Vehicle
                            </button>
                        </div>
                        <div id="jtbfe-vehicles-grid">
                            <div class="jtbfe-loading"><div class="jtbfe-spinner"></div></div>
                        </div>
                    </section>

                    <!-- DRIVERS -->
                    <section class="jtbfe-view" id="jtbfe-view-drivers">
                        <div class="jtbfe-toolbar">
                            <h2 class="jtbfe-section-title">Drivers</h2>
                            <button class="jtbfe-btn jtbfe-btn-primary" id="jtbfe-add-driver">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="14" height="14"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                Add Driver
                            </button>
                        </div>
                        <div id="jtbfe-drivers-grid">
                            <div class="jtbfe-loading"><div class="jtbfe-spinner"></div></div>
                        </div>
                    </section>

                    <!-- COMPANIES -->
                    <section class="jtbfe-view" id="jtbfe-view-companies">
                        <div class="jtbfe-toolbar">
                            <h2 class="jtbfe-section-title">Companies</h2>
                            <button class="jtbfe-btn jtbfe-btn-primary" id="jtbfe-add-company">+ Add Company</button>
                        </div>
                        <div id="jtbfe-companies-list">
                            <div class="jtbfe-loading"><div class="jtbfe-spinner"></div></div>
                        </div>
                    </section>

                    <!-- REPORTS -->
                    <section class="jtbfe-view" id="jtbfe-view-reports">
                        <div class="jtbfe-report-header">
                            <h2 class="jtbfe-section-title">Reports</h2>
                            <div class="jtbfe-tabs" id="jtbfe-report-tabs">
                                <button class="jtbfe-tab is-active" data-tab="monthly" onclick="jtbfeTab(this);return false;">Monthly</button>
                                <button class="jtbfe-tab" data-tab="drivers" onclick="jtbfeTab(this);return false;">By Driver</button>
                                <button class="jtbfe-tab" data-tab="vehicles" onclick="jtbfeTab(this);return false;">By Vehicle</button>
                                <button class="jtbfe-tab" data-tab="customer_type" onclick="jtbfeTab(this);return false;">By Customer/Booking Type</button>
                            </div>
                        </div>
                        <div class="jtbfe-report-controls" id="jtbfe-report-month-ctrl">
                            <div class="jtbfe-field" style="flex-direction:row;align-items:center;gap:8px">
                                <label>Month:</label>
                                <input type="month" id="jtbfe-report-month" class="jtbfe-input" style="width:160px" value="<?php echo esc_html( gmdate('Y-m') ); ?>">
                                <button class="jtbfe-btn jtbfe-btn-secondary" id="jtbfe-report-go">View</button>
                            </div>
                        </div>
                        <div class="jtbfe-report-controls jtbfe-hidden" id="jtbfe-report-range-ctrl">
                            <div class="jtbfe-field" style="flex-direction:row;align-items:center;gap:8px">
                                <label>From:</label>
                                <input type="date" id="jtbfe-report-from" class="jtbfe-input" style="width:140px" value="<?php echo esc_attr( gmdate('Y-m-01') ); ?>">
                                <label>To:</label>
                                <input type="date" id="jtbfe-report-to" class="jtbfe-input" style="width:140px" value="<?php echo esc_attr( gmdate('Y-m-d') ); ?>">
                                <span id="jtbfe-report-type-ctrl" class="jtbfe-hidden" style="display:inline-flex;align-items:center;gap:8px">
                                    <label>Type:</label>
                                    <select id="jtbfe-report-cust-type" class="jtbfe-input" style="width:130px">
                                        <option value="all">All</option>
                                        <option value="corporate">Corporate</option>
                                        <option value="individual">Individual</option>
                                    </select>
                                </span>
                                <button class="jtbfe-btn jtbfe-btn-secondary" id="jtbfe-report-go-range">View</button>
                            </div>
                        </div>
                        <div id="jtbfe-reports-content">
                            <div class="jtbfe-loading"><div class="jtbfe-spinner"></div></div>
                        </div>
                    </section>

                </div>
            </main>

            <!-- PAYMENT MODAL -->
            <div class="jtbfe-modal" id="jtbfe-payment-modal">
                <div class="jtbfe-modal__backdrop"></div>
                <div class="jtbfe-modal__dialog">
                    <div class="jtbfe-modal__head">
                        <h3>Add Payment</h3>
                        <button class="jtbfe-modal__close" data-modal="jtbfe-payment-modal">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                    <div class="jtbfe-modal__body">
                        <div class="jtbfe-frow">
                            <div class="jtbfe-field">
                                <label>Date</label>
                                <input type="date" id="pfe-date" class="jtbfe-input">
                            </div>
                            <div class="jtbfe-field">
                                <label>Amount (₹) <span class="req">*</span></label>
                                <input type="number" id="pfe-amount" class="jtbfe-input" min="1" step="0.01" placeholder="0.00">
                            </div>
                        </div>
                        <div class="jtbfe-frow">
                            <div class="jtbfe-field">
                                <label>Mode</label>
                                <select id="pfe-mode" class="jtbfe-select">
                                    <option value="cash">Cash</option>
                                    <option value="upi">UPI</option>
                                    <option value="bank-transfer">Bank Transfer</option>
                                    <option value="card">Card</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="jtbfe-field">
                                <label>Received By</label>
                                <input type="text" id="pfe-received-by" class="jtbfe-input" placeholder="Your name">
                            </div>
                        </div>
                        <div class="jtbfe-field">
                            <label>Reference / Transaction ID</label>
                            <input type="text" id="pfe-ref" class="jtbfe-input" placeholder="UPI ref, NEFT ref…">
                        </div>
                        <div class="jtbfe-field">
                            <label>Notes</label>
                            <textarea id="pfe-notes" class="jtbfe-textarea" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="jtbfe-modal__foot">
                        <button class="jtbfe-btn jtbfe-btn-ghost" data-modal="jtbfe-payment-modal">Cancel</button>
                        <button class="jtbfe-btn jtbfe-btn-primary" id="jtbfe-save-payment">Save Payment</button>
                    </div>
                </div>
            </div>

            <!-- REVIEW REQUEST MODAL -->
            <div class="jtbfe-modal" id="jtbfe-review-modal">
                <div class="jtbfe-modal__backdrop"></div>
                <div class="jtbfe-modal__dialog">
                    <div class="jtbfe-modal__head">
                        <h3>⭐ Send Review Request?</h3>
                        <button class="jtbfe-modal__close" data-modal="jtbfe-review-modal">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                    <div class="jtbfe-modal__body">
                        <p style="color:#6b7280;font-size:13px;margin:0 0 14px">This trip was just marked Completed. Ask the customer for a Google review while the experience is fresh.</p>
                        <div class="jtbfe-frow">
                            <div class="jtbfe-field">
                                <label>Customer</label>
                                <div id="rvfe-name" style="font-weight:600;padding:8px 0">—</div>
                            </div>
                            <div class="jtbfe-field">
                                <label>Mobile</label>
                                <div id="rvfe-mobile" style="font-weight:600;padding:8px 0">—</div>
                            </div>
                        </div>
                        <div class="jtbfe-field">
                            <label>Message</label>
                            <textarea id="rvfe-message" class="jtbfe-textarea" rows="7"></textarea>
                        </div>
                        <label style="display:flex;align-items:center;gap:8px;margin-top:12px;font-size:13px;color:var(--c-text);cursor:pointer">
                            <input type="checkbox" id="rvfe-sent-check">
                            Review Request Sent
                        </label>
                    </div>
                    <div class="jtbfe-modal__footer">
                        <button class="jtbfe-btn jtbfe-btn-ghost" data-modal="jtbfe-review-modal">Close</button>
                        <a href="#" class="jtbfe-btn jtbfe-btn-wa" id="rvfe-wa-btn" target="_blank">📱 Open WhatsApp</a>
                    </div>
                </div>
            </div>

            <!-- EXPENSE MODAL -->
            <div class="jtbfe-modal" id="jtbfe-expense-modal">
                <div class="jtbfe-modal__backdrop"></div>
                <div class="jtbfe-modal__dialog">
                    <div class="jtbfe-modal__head">
                        <h3>Add Expense</h3>
                        <button class="jtbfe-modal__close" data-modal="jtbfe-expense-modal">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                    <div class="jtbfe-modal__body">
                        <div class="jtbfe-frow">
                            <div class="jtbfe-field">
                                <label>Date</label>
                                <input type="date" id="efe-date" class="jtbfe-input">
                            </div>
                            <div class="jtbfe-field">
                                <label>Type <span class="req">*</span></label>
                                <select id="efe-type" class="jtbfe-select">
                                    <option value="">— Select —</option>
                                    <option>Driver Allowance</option>
                                    <option>Fuel</option>
                                    <option>Toll</option>
                                    <option>Parking</option>
                                    <option>Vehicle Cleaning</option>
                                    <option>Commission</option>
                                    <option>Outsourced Taxi Payment</option>
                                    <option value="__other">Other…</option>
                                </select>
                            </div>
                        </div>
                        <div class="jtbfe-field" id="efe-type-other-wrap" style="display:none">
                            <label>Specify Type</label>
                            <input type="text" id="efe-type-other" class="jtbfe-input" placeholder="Enter expense type">
                        </div>
                        <div class="jtbfe-frow">
                            <div class="jtbfe-field">
                                <label>Amount (₹) <span class="req">*</span></label>
                                <input type="number" id="efe-amount" class="jtbfe-input" min="1" step="0.01" placeholder="0.00">
                            </div>
                            <div class="jtbfe-field">
                                <label>Mode</label>
                                <select id="efe-mode" class="jtbfe-select">
                                    <option value="cash">Cash</option>
                                    <option value="upi">UPI</option>
                                    <option value="bank-transfer">Bank Transfer</option>
                                    <option value="card">Card</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="jtbfe-frow">
                            <div class="jtbfe-field">
                                <label>Paid To</label>
                                <input type="text" id="efe-paid-to" class="jtbfe-input" placeholder="Vendor / person">
                            </div>
                            <div class="jtbfe-field">
                                <label>Paid By</label>
                                <input type="text" id="efe-paid-by" class="jtbfe-input" placeholder="Staff name">
                            </div>
                        </div>
                        <div class="jtbfe-field">
                            <label>Notes</label>
                            <textarea id="efe-notes" class="jtbfe-textarea" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="jtbfe-modal__foot">
                        <button class="jtbfe-btn jtbfe-btn-ghost" data-modal="jtbfe-expense-modal">Cancel</button>
                        <button class="jtbfe-btn jtbfe-btn-primary" id="jtbfe-save-expense">Save Expense</button>
                    </div>
                </div>
            </div>

            <!-- VOID PAYMENT MODAL -->
            <div class="jtbfe-modal" id="jtbfe-void-pay-modal">
                <div class="jtbfe-modal__backdrop"></div>
                <div class="jtbfe-modal__dialog" style="max-width:420px">
                    <div class="jtbfe-modal__head">
                        <h3>Void Payment</h3>
                        <button class="jtbfe-modal__close" data-modal="jtbfe-void-pay-modal">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                    <div class="jtbfe-modal__body">
                        <input type="hidden" id="jtbfe-void-pay-id">
                        <div class="jtbfe-field">
                            <label class="jtbfe-label">Reason for Voiding <span class="req">*</span></label>
                            <textarea id="jtbfe-void-pay-reason" class="jtbfe-textarea" rows="3" placeholder="Explain why this payment is being voided…"></textarea>
                        </div>
                    </div>
                    <div class="jtbfe-modal__foot">
                        <button class="jtbfe-btn jtbfe-btn-ghost" data-modal="jtbfe-void-pay-modal">Cancel</button>
                        <button class="jtbfe-btn jtbfe-btn-danger" id="jtbfe-confirm-void-pay">Void Payment</button>
                    </div>
                </div>
            </div>

            <!-- EDIT PAYMENT MODAL -->
            <div class="jtbfe-modal" id="jtbfe-edit-pay-modal">
                <div class="jtbfe-modal__backdrop"></div>
                <div class="jtbfe-modal__dialog">
                    <div class="jtbfe-modal__head">
                        <h3>Edit Payment</h3>
                        <button class="jtbfe-modal__close" data-modal="jtbfe-edit-pay-modal">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                    <div class="jtbfe-modal__body">
                        <input type="hidden" id="jtbfe-edit-pay-id">
                        <div class="jtbfe-frow">
                            <div class="jtbfe-field"><label>Date</label><input type="date" id="jtbfe-epay-date" class="jtbfe-input"></div>
                            <div class="jtbfe-field"><label>Amount (₹)</label><input type="number" id="jtbfe-epay-amount" class="jtbfe-input" min="0" step="0.01"></div>
                        </div>
                        <div class="jtbfe-frow">
                            <div class="jtbfe-field"><label>Mode</label>
                                <select id="jtbfe-epay-mode" class="jtbfe-select">
                                    <option value="cash">Cash</option><option value="upi">UPI</option>
                                    <option value="bank-transfer">Bank Transfer</option><option value="card">Card</option><option value="other">Other</option>
                                </select>
                            </div>
                            <div class="jtbfe-field"><label>Received By</label><input type="text" id="jtbfe-epay-received" class="jtbfe-input"></div>
                        </div>
                        <div class="jtbfe-field"><label>Transaction Ref</label><input type="text" id="jtbfe-epay-ref" class="jtbfe-input"></div>
                        <div class="jtbfe-field"><label>Notes</label><textarea id="jtbfe-epay-notes" class="jtbfe-textarea" rows="2"></textarea></div>
                    </div>
                    <div class="jtbfe-modal__foot">
                        <button class="jtbfe-btn jtbfe-btn-ghost" data-modal="jtbfe-edit-pay-modal">Cancel</button>
                        <button class="jtbfe-btn jtbfe-btn-primary" id="jtbfe-save-edit-pay">Save Changes</button>
                    </div>
                </div>
            </div>

            <!-- VOID EXPENSE MODAL -->
            <div class="jtbfe-modal" id="jtbfe-void-exp-modal">
                <div class="jtbfe-modal__backdrop"></div>
                <div class="jtbfe-modal__dialog" style="max-width:420px">
                    <div class="jtbfe-modal__head">
                        <h3>Void Expense</h3>
                        <button class="jtbfe-modal__close" data-modal="jtbfe-void-exp-modal">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                    <div class="jtbfe-modal__body">
                        <input type="hidden" id="jtbfe-void-exp-id">
                        <div class="jtbfe-field">
                            <label class="jtbfe-label">Reason for Voiding <span class="req">*</span></label>
                            <textarea id="jtbfe-void-exp-reason" class="jtbfe-textarea" rows="3" placeholder="Explain why this expense is being voided…"></textarea>
                        </div>
                    </div>
                    <div class="jtbfe-modal__foot">
                        <button class="jtbfe-btn jtbfe-btn-ghost" data-modal="jtbfe-void-exp-modal">Cancel</button>
                        <button class="jtbfe-btn jtbfe-btn-danger" id="jtbfe-confirm-void-exp">Void Expense</button>
                    </div>
                </div>
            </div>

            <!-- EDIT EXPENSE MODAL -->
            <div class="jtbfe-modal" id="jtbfe-edit-exp-modal">
                <div class="jtbfe-modal__backdrop"></div>
                <div class="jtbfe-modal__dialog">
                    <div class="jtbfe-modal__head">
                        <h3>Edit Expense</h3>
                        <button class="jtbfe-modal__close" data-modal="jtbfe-edit-exp-modal">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                    <div class="jtbfe-modal__body">
                        <input type="hidden" id="jtbfe-edit-exp-id">
                        <div class="jtbfe-frow">
                            <div class="jtbfe-field"><label>Date</label><input type="date" id="jtbfe-eexp-date" class="jtbfe-input"></div>
                            <div class="jtbfe-field"><label>Type</label><input type="text" id="jtbfe-eexp-type" class="jtbfe-input" placeholder="Fuel, toll, etc."></div>
                        </div>
                        <div class="jtbfe-frow">
                            <div class="jtbfe-field"><label>Amount (₹)</label><input type="number" id="jtbfe-eexp-amount" class="jtbfe-input" min="0" step="0.01"></div>
                            <div class="jtbfe-field"><label>Mode</label>
                                <select id="jtbfe-eexp-mode" class="jtbfe-select">
                                    <option value="cash">Cash</option><option value="upi">UPI</option>
                                    <option value="bank-transfer">Bank Transfer</option><option value="card">Card</option><option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="jtbfe-frow">
                            <div class="jtbfe-field"><label>Paid To</label><input type="text" id="jtbfe-eexp-paidto" class="jtbfe-input"></div>
                            <div class="jtbfe-field"><label>Paid By</label><input type="text" id="jtbfe-eexp-paidby" class="jtbfe-input"></div>
                        </div>
                        <div class="jtbfe-field"><label>Notes</label><textarea id="jtbfe-eexp-notes" class="jtbfe-textarea" rows="2"></textarea></div>
                    </div>
                    <div class="jtbfe-modal__foot">
                        <button class="jtbfe-btn jtbfe-btn-ghost" data-modal="jtbfe-edit-exp-modal">Cancel</button>
                        <button class="jtbfe-btn jtbfe-btn-primary" id="jtbfe-save-edit-exp">Save Changes</button>
                    </div>
                </div>
            </div>

            <!-- VEHICLE MODAL -->
            <div class="jtbfe-modal" id="jtbfe-vehicle-modal">
                <div class="jtbfe-modal__backdrop"></div>
                <div class="jtbfe-modal__dialog">
                    <div class="jtbfe-modal__head">
                        <h3 id="jtbfe-vehicle-modal-title">Add Vehicle</h3>
                        <button class="jtbfe-modal__close" data-modal="jtbfe-vehicle-modal">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                    <div class="jtbfe-modal__body">
                        <input type="hidden" id="vfe-id">
                        <div class="jtbfe-frow">
                            <div class="jtbfe-field">
                                <label>Vehicle Name <span class="req">*</span></label>
                                <input type="text" id="vfe-name" class="jtbfe-input" placeholder="e.g. Toyota Innova Crysta">
                            </div>
                            <div class="jtbfe-field">
                                <label>Type</label>
                                <select id="vfe-type" class="jtbfe-select">
                                    <option value="">— Select —</option>
                                    <option>Sedan</option><option>Hatchback</option><option>SUV</option><option>MUV</option>
                                    <option>Tempo Traveller</option><option>Bus</option><option>Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="jtbfe-frow">
                            <div class="jtbfe-field">
                                <label>Registration Number</label>
                                <input type="text" id="vfe-reg" class="jtbfe-input" placeholder="MP 09 XX 0000">
                            </div>
                            <div class="jtbfe-field">
                                <label>Owner Type</label>
                                <select id="vfe-owner" class="jtbfe-select">
                                    <option value="own">Own</option>
                                    <option value="outsourced">Outsourced</option>
                                </select>
                            </div>
                        </div>
                        <div id="vfe-vendor-wrap" style="display:none">
                            <div class="jtbfe-frow">
                                <div class="jtbfe-field">
                                    <label>Vendor Name</label>
                                    <input type="text" id="vfe-vendor-name" class="jtbfe-input" placeholder="Vendor / owner name">
                                </div>
                                <div class="jtbfe-field">
                                    <label>Vendor Mobile</label>
                                    <input type="tel" id="vfe-vendor-mobile" class="jtbfe-input" placeholder="Mobile number">
                                </div>
                            </div>
                        </div>
                        <div class="jtbfe-frow">
                            <div class="jtbfe-field">
                                <label>Status</label>
                                <select id="vfe-status" class="jtbfe-select">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                        <div class="jtbfe-field">
                            <label>Notes</label>
                            <textarea id="vfe-notes" class="jtbfe-textarea" rows="2" placeholder="Optional notes…"></textarea>
                        </div>
                    </div>
                    <div class="jtbfe-modal__foot">
                        <button class="jtbfe-btn jtbfe-btn-ghost" id="jtbfe-delete-vehicle" style="display:none;margin-right:auto;color:#ef4444">🗑 Delete</button>
                        <button class="jtbfe-btn jtbfe-btn-ghost" data-modal="jtbfe-vehicle-modal">Cancel</button>
                        <button class="jtbfe-btn jtbfe-btn-primary" id="jtbfe-save-vehicle">Save Vehicle</button>
                    </div>
                </div>
            </div>

            <!-- DRIVER MODAL -->
            <div class="jtbfe-modal" id="jtbfe-driver-modal">
                <div class="jtbfe-modal__backdrop"></div>
                <div class="jtbfe-modal__dialog">
                    <div class="jtbfe-modal__head">
                        <h3 id="jtbfe-driver-modal-title">Add Driver</h3>
                        <button class="jtbfe-modal__close" data-modal="jtbfe-driver-modal">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                    <div class="jtbfe-modal__body">
                        <input type="hidden" id="dfe-id">
                        <div class="jtbfe-frow">
                            <div class="jtbfe-field">
                                <label>Driver Name <span class="req">*</span></label>
                                <input type="text" id="dfe-name" class="jtbfe-input" placeholder="Full name">
                            </div>
                            <div class="jtbfe-field">
                                <label>Mobile <span class="req">*</span></label>
                                <input type="tel" id="dfe-mobile" class="jtbfe-input" placeholder="10-digit number">
                            </div>
                        </div>
                        <div class="jtbfe-frow">
                            <div class="jtbfe-field">
                                <label>Assigned Vehicle</label>
                                <select id="dfe-vehicle" class="jtbfe-select">
                                    <option value="">— None —</option>
                                </select>
                            </div>
                            <div class="jtbfe-field">
                                <label>Driver Type</label>
                                <select id="dfe-type" class="jtbfe-select">
                                    <option value="own">Own (on payroll)</option>
                                    <option value="outsourced">Outsourced</option>
                                </select>
                            </div>
                        </div>
                        <div class="jtbfe-frow">
                            <div class="jtbfe-field">
                                <label>Status</label>
                                <select id="dfe-status" class="jtbfe-select">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                        <div class="jtbfe-field">
                            <label>Notes</label>
                            <textarea id="dfe-notes" class="jtbfe-textarea" rows="2" placeholder="License details, experience…"></textarea>
                        </div>
                    </div>
                    <div class="jtbfe-modal__foot">
                        <button class="jtbfe-btn jtbfe-btn-ghost" id="jtbfe-delete-driver" style="display:none;margin-right:auto;color:#ef4444">🗑 Delete</button>
                        <button class="jtbfe-btn jtbfe-btn-ghost" data-modal="jtbfe-driver-modal">Cancel</button>
                        <button class="jtbfe-btn jtbfe-btn-primary" id="jtbfe-save-driver">Save Driver</button>
                    </div>
                </div>
            </div>

            <!-- COMPANY MODAL -->
            <div class="jtbfe-modal" id="jtbfe-company-modal">
                <div class="jtbfe-modal__backdrop"></div>
                <div class="jtbfe-modal__dialog">
                    <div class="jtbfe-modal__head">
                        <h3 id="jtbfe-company-modal-title">Add Company</h3>
                        <button class="jtbfe-modal__close" data-modal="jtbfe-company-modal">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                    <div class="jtbfe-modal__body">
                        <input type="hidden" id="cfe-id">
                        <div class="jtbfe-field">
                            <label class="jtbfe-label jtbfe-label--req">Company Name</label>
                            <input type="text" id="cfe-name" class="jtbfe-input" placeholder="ABC Travels Pvt Ltd">
                        </div>
                        <div class="jtbfe-field-group jtbfe-field-group--2">
                            <div class="jtbfe-field">
                                <label class="jtbfe-label">Contact Person</label>
                                <input type="text" id="cfe-contact-name" class="jtbfe-input" placeholder="Full name">
                            </div>
                            <div class="jtbfe-field">
                                <label class="jtbfe-label">Mobile</label>
                                <input type="tel" id="cfe-contact-mobile" class="jtbfe-input" placeholder="10-digit number">
                            </div>
                        </div>
                        <div class="jtbfe-field-group jtbfe-field-group--2">
                            <div class="jtbfe-field">
                                <label class="jtbfe-label">Email</label>
                                <input type="email" id="cfe-contact-email" class="jtbfe-input" placeholder="contact@company.com">
                            </div>
                            <div class="jtbfe-field">
                                <label class="jtbfe-label">GST Number</label>
                                <input type="text" id="cfe-gst" class="jtbfe-input" placeholder="GST / Tax ID">
                            </div>
                        </div>
                        <div class="jtbfe-field">
                            <label class="jtbfe-label">Notes</label>
                            <textarea id="cfe-notes" class="jtbfe-input" rows="2" placeholder="Optional notes"></textarea>
                        </div>
                    </div>
                    <div class="jtbfe-modal__foot">
                        <button class="jtbfe-btn jtbfe-btn-ghost" id="jtbfe-delete-company" style="display:none;margin-right:auto;color:#ef4444">&#x1F5D1; Delete</button>
                        <button class="jtbfe-btn jtbfe-btn-ghost" data-modal="jtbfe-company-modal">Cancel</button>
                        <button class="jtbfe-btn jtbfe-btn-primary" id="jtbfe-save-company">Save Company</button>
                    </div>
                </div>
            </div>

            <!-- TOAST CONTAINER -->
            <div id="jtbfe-toasts"></div>
        </div>
        <?php
    }

    private function render_booking_form() {
        ?>
        <form id="jtbfe-booking-form" class="jtbfe-booking-form" novalidate>
            <input type="hidden" id="bf-booking-id" name="booking_id" value="">

            <div class="jtbfe-form-grid">
                <!-- Customer -->
                <div class="jtbfe-card">
                    <div class="jtbfe-card__header"><h3 class="jtbfe-card__title">Customer</h3></div>
                    <div class="jtbfe-card__body">

                        <!-- Booking Type -->
                        <div class="jtbfe-field" style="margin-bottom:14px">
                            <label>Booking Type</label>
                            <div class="jtbfe-chip-group" id="bf-type-group">
                                <label class="jtbfe-chip is-selected">
                                    <input type="radio" name="booking_type" value="individual" checked> Individual
                                </label>
                                <label class="jtbfe-chip">
                                    <input type="radio" name="booking_type" value="corporate"> Corporate
                                </label>
                            </div>
                        </div>

                        <!-- Individual fields -->
                        <div id="bf-individual-fields">
                            <div class="jtbfe-frow">
                                <div class="jtbfe-field">
                                    <label>Full Name <span class="req">*</span></label>
                                    <input type="text" id="bf-name" name="customer_name" class="jtbfe-input" placeholder="Customer's full name">
                                </div>
                                <div class="jtbfe-field">
                                    <label>Mobile <span class="req">*</span></label>
                                    <input type="tel" id="bf-mobile" name="customer_mobile" class="jtbfe-input" placeholder="10-digit number">
                                </div>
                            </div>
                        </div>

                        <!-- Corporate fields -->
                        <div id="bf-corporate-fields" style="display:none">
                            <div class="jtbfe-field">
                                <label>Company <span class="req">*</span></label>
                                <select id="bf-company" name="company_id" class="jtbfe-select">
                                    <option value="">— Select Company —</option>
                                </select>
                            </div>
                            <div class="jtbfe-frow">
                                <div class="jtbfe-field">
                                    <label>Booked By</label>
                                    <input type="text" id="bf-booked-by" name="booked_by_name" class="jtbfe-input" placeholder="Auto-filled from company">
                                </div>
                                <div class="jtbfe-field">
                                    <label>Booked By Mobile</label>
                                    <input type="tel" id="bf-booked-by-mobile" name="booked_by_mobile" class="jtbfe-input" placeholder="Auto-filled">
                                </div>
                            </div>
                            <div class="jtbfe-frow">
                                <div class="jtbfe-field">
                                    <label>Passenger Name</label>
                                    <input type="text" id="bf-passenger" name="passenger_name" class="jtbfe-input" placeholder="Name of the traveller">
                                </div>
                                <div class="jtbfe-field">
                                    <label>Passenger Mobile</label>
                                    <input type="tel" id="bf-pax-mobile" name="passenger_mobile" class="jtbfe-input" placeholder="Traveller's phone">
                                </div>
                            </div>
                            <div class="jtbfe-field" style="margin-top:8px">
                                <label>Booking ID / PO Reference</label>
                                <input type="text" id="bf-booking-ref" name="booking_ref" class="jtbfe-input" placeholder="PO number or reference">
                            </div>
                            <!-- Hidden customer_name / customer_mobile for corporate -->
                            <input type="hidden" id="bf-corp-name"   name="customer_name"   value="">
                            <input type="hidden" id="bf-corp-mobile" name="customer_mobile" value="">
                        </div>

                        <!-- Email (always shown) -->
                        <div class="jtbfe-field" style="margin-top:12px">
                            <label>Email Address</label>
                            <input type="email" id="bf-email" name="customer_email" class="jtbfe-input" placeholder="email@example.com">
                        </div>

                    </div>
                </div>

                <!-- Trip -->
                <div class="jtbfe-card">
                    <div class="jtbfe-card__header"><h3 class="jtbfe-card__title">Trip Details</h3></div>
                    <div class="jtbfe-card__body">
                        <div class="jtbfe-field">
                            <label>Trip Type</label>
                            <div class="jtbfe-chip-group" id="bf-trip-type-group">
                                <?php
                                $types = ['one-way'=>'One Way','round-trip'=>'Round Trip','local'=>'Local','outstation'=>'Outstation','yatra'=>'Yatra / Package','other'=>'Other'];
                                foreach ($types as $v => $l) :
                                ?>
                                <label class="jtbfe-chip <?php echo $v === 'one-way' ? 'is-selected' : ''; ?>">
                                    <input type="radio" name="trip_type" value="<?php echo esc_html( $v ); ?>" <?php echo $v === 'one-way' ? 'checked' : ''; ?>>
                                    <?php echo esc_html( $l ); ?>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="jtbfe-frow">
                            <div class="jtbfe-field">
                                <label>From</label>
                                <input type="text" id="bf-from" name="pickup_location" class="jtbfe-input" placeholder="Pickup city / address">
                            </div>
                            <div class="jtbfe-field">
                                <label>To</label>
                                <input type="text" id="bf-to" name="drop_location" class="jtbfe-input" placeholder="Drop city / address">
                            </div>
                        </div>
                        <div class="jtbfe-frow">
                            <div class="jtbfe-field">
                                <label>Start Date <span class="req">*</span></label>
                                <input type="date" id="bf-start-date" name="start_date" class="jtbfe-input">
                            </div>
                            <div class="jtbfe-field">
                                <label>Start Time <span style="color:#94a3b8;font-size:11px">(optional)</span></label>
                                <input type="time" id="bf-start-time" name="start_time" class="jtbfe-input">
                            </div>
                        </div>
                        <div class="jtbfe-frow">
                            <div class="jtbfe-field">
                                <label>End Date</label>
                                <input type="date" id="bf-end-date" name="end_date" class="jtbfe-input">
                            </div>
                            <div class="jtbfe-field">
                                <label>End Time <span style="color:#94a3b8;font-size:11px">(optional)</span></label>
                                <input type="time" id="bf-end-time" name="end_time" class="jtbfe-input">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Vehicle & Driver -->
                <div class="jtbfe-card">
                    <div class="jtbfe-card__header"><h3 class="jtbfe-card__title">Vehicle & Driver</h3></div>
                    <div class="jtbfe-card__body">
                        <div class="jtbfe-field" style="margin-bottom:14px">
                            <div class="jtbfe-chip-group" id="bf-source-group">
                                <label class="jtbfe-chip is-selected">
                                    <input type="radio" name="vehicle_source" value="fleet" checked> Own Fleet
                                </label>
                                <label class="jtbfe-chip">
                                    <input type="radio" name="vehicle_source" value="outsourced"> Outsourced
                                </label>
                            </div>
                        </div>

                        <!-- Own fleet: pick from vehicle/driver database -->
                        <div id="bf-fleet-fields">
                            <div class="jtbfe-frow">
                                <div class="jtbfe-field">
                                    <label>Vehicle</label>
                                    <select id="bf-vehicle" name="vehicle_id" class="jtbfe-select">
                                        <option value="">— Select Vehicle —</option>
                                    </select>
                                </div>
                                <div class="jtbfe-field">
                                    <label>Driver</label>
                                    <select id="bf-driver" name="driver_id" class="jtbfe-select">
                                        <option value="">— Select Driver —</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Outsourced: typed in manually, not saved to the vehicle/driver database -->
                        <div id="bf-outsourced-fields" style="display:none">
                            <div class="jtbfe-frow">
                                <div class="jtbfe-field">
                                    <label>Driver Name</label>
                                    <input type="text" id="bf-out-driver-name" name="outsourced_driver_name" class="jtbfe-input" placeholder="Driver's name">
                                </div>
                                <div class="jtbfe-field">
                                    <label>Driver Mobile <span style="color:#94a3b8;font-size:11px">(optional)</span></label>
                                    <input type="tel" id="bf-out-driver-mobile" name="outsourced_driver_mobile" class="jtbfe-input" placeholder="10-digit number">
                                </div>
                            </div>
                            <div class="jtbfe-field">
                                <label>Vehicle Number</label>
                                <input type="text" id="bf-out-vehicle-number" name="outsourced_vehicle_number" class="jtbfe-input" placeholder="e.g. JK02AB1234">
                            </div>
                            <p style="font-size:12px;color:#94a3b8;margin-top:6px">This vehicle/driver won't be added to your fleet database — it's used for this booking and the customer/driver messages only.</p>
                        </div>
                    </div>
                </div>

                <!-- Amount & Status -->
                <div class="jtbfe-card">
                    <div class="jtbfe-card__header"><h3 class="jtbfe-card__title">Amount & Status</h3></div>
                    <div class="jtbfe-card__body">
                        <div class="jtbfe-frow">
                            <div class="jtbfe-field">
                                <label>Total Amount (₹) <span class="req">*</span></label>
                                <input type="number" id="bf-amount" name="booking_amount" class="jtbfe-input" min="0" step="0.01" placeholder="0.00" required>
                            </div>
                            <div class="jtbfe-field">
                                <label>Advance (₹)</label>
                                <input type="number" id="bf-advance" name="advance_amount" class="jtbfe-input" min="0" step="0.01" placeholder="0.00">
                            </div>
                        </div>
                        <div id="bf-advance-details" style="display:none;margin-top:12px">
                            <div class="jtbfe-frow">
                                <div class="jtbfe-field">
                                    <label>Payment Mode</label>
                                    <select id="bf-advance-mode" name="advance_payment_mode" class="jtbfe-select">
                                        <option value="cash">Cash</option>
                                        <option value="upi">UPI</option>
                                        <option value="bank-transfer">Bank Transfer</option>
                                        <option value="card">Card</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                <div class="jtbfe-field">
                                    <label>Received By</label>
                                    <input type="text" id="bf-advance-received" name="advance_received_by" class="jtbfe-input" placeholder="Staff name">
                                </div>
                            </div>
                        </div>
                        <div class="jtbfe-balance-bar" id="bf-balance-bar">
                            <span>Balance Due</span>
                            <strong id="bf-balance-val">₹0.00</strong>
                        </div>
                        <div class="jtbfe-field" style="margin-top:14px">
                            <label>Status</label>
                            <div class="jtbfe-chip-group" id="bf-status-group">
                                <?php foreach (['new'=>'New','confirmed'=>'Confirmed','ongoing'=>'Ongoing','completed'=>'Completed','closed'=>'Closed','cancelled'=>'Cancelled'] as $v=>$l): ?>
                                <label class="jtbfe-chip jtbfe-status-<?php echo esc_html( $v ); ?> <?php echo $v==='new'?'is-selected':''; ?>">
                                    <input type="radio" name="status" value="<?php echo esc_html( $v ); ?>" <?php echo $v==='new'?'checked':''; ?>>
                                    <?php echo esc_html( $l ); ?>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Notes -->
                <div class="jtbfe-card jtbfe-card--full">
                    <div class="jtbfe-card__header"><h3 class="jtbfe-card__title">Notes</h3></div>
                    <div class="jtbfe-card__body">
                        <textarea id="bf-notes" name="notes" class="jtbfe-textarea" rows="3" placeholder="Special instructions, passenger details, pick-up notes…"></textarea>
                    </div>
                </div>
            </div>

            <div class="jtbfe-form-footer">
                <button type="button" class="jtbfe-btn jtbfe-btn-ghost" id="jtbfe-form-cancel">Cancel</button>
                <button type="submit" class="jtbfe-btn jtbfe-btn-primary jtbfe-btn-lg" id="jtbfe-form-submit">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16" height="16"><polyline points="20,6 9,17 4,12"/></svg>
                    Save Booking
                </button>
            </div>
        </form>
        <?php
    }

    // -------------------------------------------------------------------------
    // AJAX HANDLERS
    // -------------------------------------------------------------------------

    private function fe_verify() {
        if ( ! check_ajax_referer( 'wpyogfleet_fe_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed.' ), 403 );
        }
        if ( ! current_user_can( 'wpyogfleet_manage' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 );
        }
    }

    public function handle_get_stats() {
        $this->fe_verify();
        $stats = WPYOGFLEET_DB::get_dashboard_stats();
        wp_send_json_success( $stats );
    }

    public function handle_get_bookings() {
        $this->fe_verify();
        $per_page = 25;
        $page     = max( 1, absint( $_POST['page'] ?? 1 ) );
        $args = array(
            'search'       => sanitize_text_field( wp_unslash( $_POST['search'] ?? '' ) ),
            'status'       => sanitize_text_field( wp_unslash( $_POST['status'] ?? '' ) ),
            'pending_only' => ! empty( $_POST['pending_only'] ) ? 1 : 0,
            'orderby'      => sanitize_text_field( wp_unslash( $_POST['orderby'] ?? 'date' ) ),
            'order'        => sanitize_text_field( wp_unslash( $_POST['order'] ?? 'desc' ) ),
            'limit'        => $per_page,
            'offset'       => ( $page - 1 ) * $per_page,
        );
        $bookings    = WPYOGFLEET_DB::get_bookings( $args );
        $total       = WPYOGFLEET_DB::count_bookings_filtered( $args );
        // Sanitize for JSON
        $out = array();
        foreach ( $bookings as $b ) {
            $out[] = array(
                'id'              => (int) $b->id,
                'booking_number'  => $b->booking_number,
                'booking_type'    => $b->booking_type ?? 'individual',
                'customer_name'   => $b->customer_name,
                'customer_mobile' => $b->customer_mobile,
                'customer_email'  => $b->customer_email ?? '',
                'company_name'    => $b->company_name ?? '',
                'booked_by_name'  => $b->booked_by_name ?? '',
                'passenger_name'   => $b->passenger_name ?? '',
                'passenger_mobile' => $b->passenger_mobile ?? '',
                'pickup_location'  => $b->pickup_location,
                'drop_location'   => $b->drop_location,
                'trip_type'       => $b->trip_type,
                'start_date'      => $b->start_date,
                'end_date'        => $b->end_date,
                'vehicle_name'        => $b->vehicle_name ?? '',
                'registration_number' => $b->registration_number ?? '',
                'booking_ref'         => $b->booking_ref ?? '',
                'driver_name'         => $b->driver_name ?? '',
                'driver_mobile'       => $b->driver_mobile ?? '',
                'is_outsourced'             => (int) ( $b->is_outsourced ?? 0 ),
                'outsourced_driver_name'    => $b->outsourced_driver_name ?? '',
                'outsourced_driver_mobile'  => $b->outsourced_driver_mobile ?? '',
                'outsourced_vehicle_number' => $b->outsourced_vehicle_number ?? '',
                'review_request_sent' => (int) ( $b->review_request_sent ?? 0 ),
                'booking_amount'  => (float) $b->booking_amount,
                'total_paid'      => (float) $b->total_paid,
                'balance_amount'  => (float) $b->balance_amount,
                'total_expenses'  => (float) $b->total_expenses,
                'status'          => $b->status,
            );
        }
        wp_send_json_success( array(
            'items'    => $out,
            'total'    => (int) $total,
            'page'     => $page,
            'per_page' => $per_page,
        ) );
    }

    public function handle_get_companies() {
        $this->fe_verify();
        $companies = WPYOGFLEET_DB::get_companies( array( 'status' => 'active' ) );
        $out = array();
        foreach ( $companies as $c ) {
            $out[] = array(
                'id'             => (int) $c->id,
                'company_name'   => $c->company_name,
                'contact_name'   => $c->contact_name ?? '',
                'contact_mobile' => $c->contact_mobile ?? '',
                'contact_email'  => $c->contact_email ?? '',
            );
        }
        wp_send_json_success( $out );
    }

    public function handle_save_company() {
        $this->fe_verify();
        $id = absint( $_POST['company_id'] ?? 0 );
        if ( $id ) {
            WPYOGFLEET_DB::update_company( $id, WPYOGFLEET_Input::company() );
            $co = WPYOGFLEET_DB::get_company( $id );
            WPYOGFLEET_DB::log_activity( 'company_updated', 'company', $id,
                $co->company_name ?? 'Company #' . $id, 'Updated via frontend panel' );
            wp_send_json_success( array(
                'id'     => $id,
                'action' => 'updated',
                'company' => array(
                    'id'             => $id,
                    'company_name'   => $co->company_name,
                    'contact_name'   => $co->contact_name  ?? '',
                    'contact_mobile' => $co->contact_mobile ?? '',
                    'contact_email'  => $co->contact_email  ?? '',
                    'gst_number'     => $co->gst_number     ?? '',
                    'notes'          => $co->notes           ?? '',
                ),
            ) );
        } else {
            $new_id = WPYOGFLEET_DB::insert_company( WPYOGFLEET_Input::company() );
            if ( ! $new_id ) {
                wp_send_json_error( array( 'message' => 'Could not save company.' ) );
            }
            $co = WPYOGFLEET_DB::get_company( $new_id );
            WPYOGFLEET_DB::log_activity( 'company_created', 'company', $new_id,
                $co->company_name ?? 'Company #' . $new_id, 'Created via frontend panel' );
            wp_send_json_success( array(
                'id'     => $new_id,
                'action' => 'created',
                'company' => array(
                    'id'             => $new_id,
                    'company_name'   => $co->company_name,
                    'contact_name'   => $co->contact_name  ?? '',
                    'contact_mobile' => $co->contact_mobile ?? '',
                    'contact_email'  => $co->contact_email  ?? '',
                    'gst_number'     => $co->gst_number     ?? '',
                    'notes'          => $co->notes           ?? '',
                ),
            ) );
        }
    }

    public function handle_delete_company() {
        $this->fe_verify();
        if ( ! current_user_can( 'wpyogfleet_admin' ) ) {
            wp_send_json_error( array( 'message' => 'Only admins can delete companies.' ) );
        }
        $id = absint( $_POST['company_id'] ?? 0 );
        $co = WPYOGFLEET_DB::get_company( $id );
        WPYOGFLEET_DB::log_activity( 'company_deleted', 'company', $id,
            $co->company_name ?? 'Company #' . $id, 'Deleted via frontend panel' );
        WPYOGFLEET_DB::delete_company( $id );
        wp_send_json_success( array( 'deleted' => true ) );
    }

    public function handle_send_booking_email() {
        $this->fe_verify();
        $booking_id = absint( $_POST['booking_id'] ?? 0 );
        $to         = sanitize_email( wp_unslash( $_POST['to'] ?? '' ) );
        $subject    = sanitize_text_field( wp_unslash( $_POST['subject'] ?? '' ) );
        $message    = wp_kses_post( wp_unslash( $_POST['message'] ?? '' ) );

        if ( ! $booking_id || ! is_email( $to ) || ! $subject ) {
            wp_send_json_error( array( 'message' => 'Missing required fields.' ) );
        }

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo( 'name' ) . ' <' . get_option( 'admin_email' ) . '>',
        );
        $sent = wp_mail( $to, $subject, $message, $headers );

        if ( $sent ) {
            $b = WPYOGFLEET_DB::get_booking( $booking_id );
            WPYOGFLEET_DB::log_activity( 'email_sent', 'booking', $booking_id,
                $b->booking_number ?? 'Booking #' . $booking_id,
                'Email sent to ' . $to . ' — ' . $subject );
            wp_send_json_success( array( 'sent' => true, 'to' => $to ) );
        } else {
            wp_send_json_error( array( 'message' => 'Could not send email. Contact administrator.' ) );
        }
    }

    public function handle_get_booking() {
        $this->fe_verify();
        $id      = absint( $_POST['id'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
        $booking  = WPYOGFLEET_DB::get_booking( $id );
        $payments = WPYOGFLEET_DB::get_payments( $id );
        $expenses = WPYOGFLEET_DB::get_expenses( $id );

        if ( ! $booking ) { wp_send_json_error( array('message'=>'Not found') ); }

        wp_send_json_success( array(
            'booking'  => $booking,
            'payments' => $payments,
            'expenses' => $expenses,
        ) );
    }

    /* handle_save_booking() is defined below with activity logging */

    public function handle_add_payment() {
        $this->fe_verify();
        $id = WPYOGFLEET_DB::insert_payment( WPYOGFLEET_Input::payment() );
        if ( ! $id ) { wp_send_json_error( array('message'=>'Error saving payment.') ); }
        $booking  = WPYOGFLEET_DB::get_booking( absint( $_POST['booking_id'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
        WPYOGFLEET_DB::log_activity( 'payment_added', 'payment', $id,
            $booking->booking_number ?? 'Booking #' . absint( $_POST['booking_id'] ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
            '₹' . number_format( (float) $_POST['amount'], 2 ) . ' via ' . sanitize_text_field( wp_unslash( $_POST['payment_mode'] ?? 'cash' ) ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
        );
        $payments = WPYOGFLEET_DB::get_payments( absint( $_POST['booking_id'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
        wp_send_json_success( array(
            'payment_id' => $id,
            'booking'    => $booking,
            'payments'   => $payments,
        ) );
    }

    public function handle_delete_payment() {
        $this->fe_verify();
        if ( ! current_user_can('wpyogfleet_admin') ) { wp_send_json_error(array('message'=>'Admins only.')); }
        WPYOGFLEET_DB::delete_payment( absint($_POST['payment_id']) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
        $booking_id = absint($_POST['booking_id']); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
        wp_send_json_success( array(
            'booking'  => WPYOGFLEET_DB::get_booking($booking_id),
            'payments' => WPYOGFLEET_DB::get_payments($booking_id),
        ) );
    }

    public function handle_add_expense() {
        $this->fe_verify();
        $id = WPYOGFLEET_DB::insert_expense( WPYOGFLEET_Input::expense() );
        if ( ! $id ) { wp_send_json_error( array('message'=>'Error saving expense.') ); }
        $booking_id = absint($_POST['booking_id']); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
        $booking    = WPYOGFLEET_DB::get_booking( $booking_id );
        WPYOGFLEET_DB::log_activity( 'expense_added', 'expense', $id,
            $booking->booking_number ?? 'Booking #' . $booking_id,
            sanitize_text_field( wp_unslash( $_POST['expense_type'] ?? '' ) ) . ' — ₹' . number_format( (float) $_POST['amount'], 2 ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
        );
        wp_send_json_success( array(
            'expense_id' => $id,
            'booking'    => WPYOGFLEET_DB::get_booking($booking_id),
            'expenses'   => WPYOGFLEET_DB::get_expenses($booking_id),
        ) );
    }

    public function handle_delete_expense() {
        $this->fe_verify();
        if ( ! current_user_can('wpyogfleet_admin') ) { wp_send_json_error(array('message'=>'Admins only.')); }
        WPYOGFLEET_DB::delete_expense( absint($_POST['expense_id']) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
        $booking_id = absint($_POST['booking_id']); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
        wp_send_json_success( array(
            'booking'  => WPYOGFLEET_DB::get_booking($booking_id),
            'expenses' => WPYOGFLEET_DB::get_expenses($booking_id),
        ) );
    }

    public function handle_void_payment() {
        $this->fe_verify();
        $pid    = absint( $_POST['payment_id'] ?? 0 ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
        $reason = sanitize_textarea_field( wp_unslash( $_POST['reason'] ?? '' ) );
        $bid    = absint( $_POST['booking_id'] ?? 0 ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
        WPYOGFLEET_DB::void_payment( $pid, $reason );
        $booking = WPYOGFLEET_DB::get_booking( $bid );
        WPYOGFLEET_DB::log_activity( 'payment_voided', 'payment', $pid, $booking->booking_number ?? 'Booking #' . $bid, 'Voided: ' . $reason );
        wp_send_json_success( array( 'booking' => $booking, 'payments' => WPYOGFLEET_DB::get_payments( $bid ) ) );
    }

    public function handle_edit_payment() {
        $this->fe_verify();
        $pid = absint( $_POST['payment_id'] ?? 0 ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
        $bid = absint( $_POST['booking_id']  ?? 0 ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
        WPYOGFLEET_DB::edit_payment( $pid, WPYOGFLEET_Input::payment() );
        $booking = WPYOGFLEET_DB::get_booking( $bid );
        WPYOGFLEET_DB::log_activity( 'payment_edited', 'payment', $pid, $booking->booking_number ?? 'Booking #' . $bid, 'Payment edited via frontend' );
        wp_send_json_success( array( 'booking' => $booking, 'payments' => WPYOGFLEET_DB::get_payments( $bid ) ) );
    }

    public function handle_void_expense() {
        $this->fe_verify();
        $eid    = absint( $_POST['expense_id'] ?? 0 ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
        $reason = sanitize_textarea_field( wp_unslash( $_POST['reason'] ?? '' ) );
        $bid    = absint( $_POST['booking_id'] ?? 0 ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
        WPYOGFLEET_DB::void_expense( $eid, $reason );
        $booking = WPYOGFLEET_DB::get_booking( $bid );
        WPYOGFLEET_DB::log_activity( 'expense_voided', 'expense', $eid, $booking->booking_number ?? 'Booking #' . $bid, 'Voided: ' . $reason );
        wp_send_json_success( array( 'booking' => $booking, 'expenses' => WPYOGFLEET_DB::get_expenses( $bid ) ) );
    }

    public function handle_edit_expense() {
        $this->fe_verify();
        $eid = absint( $_POST['expense_id'] ?? 0 ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
        $bid = absint( $_POST['booking_id']  ?? 0 ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
        WPYOGFLEET_DB::edit_expense( $eid, WPYOGFLEET_Input::expense() );
        $booking = WPYOGFLEET_DB::get_booking( $bid );
        WPYOGFLEET_DB::log_activity( 'expense_edited', 'expense', $eid, $booking->booking_number ?? 'Booking #' . $bid, 'Expense edited via frontend' );
        wp_send_json_success( array( 'booking' => $booking, 'expenses' => WPYOGFLEET_DB::get_expenses( $bid ) ) );
    }

    public function handle_update_status() {
        $this->fe_verify();
        $id     = absint($_POST['booking_id']); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
        $status = sanitize_text_field( wp_unslash( $_POST['status'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
        $valid  = array('new','confirmed','ongoing','completed','closed','cancelled');
        if ( ! in_array($status, $valid, true) ) { wp_send_json_error(array('message'=>'Invalid status.')); }
        $old = WPYOGFLEET_DB::get_booking($id);
        // "Closed" means the trip is done AND fully paid — block it while a balance is due.
        if ( $status === 'closed' && $old && (float) $old->balance_amount > 0.01 ) {
            wp_send_json_error( array( 'message' => 'Cannot mark as Closed — balance of ₹' . number_format( (float) $old->balance_amount, 2 ) . ' is still due. Settle the payment first.' ) );
        }
        // Use targeted update — avoids sanitize_booking() wiping all other fields
        WPYOGFLEET_DB::update_status($id, $status);
        $booking = WPYOGFLEET_DB::get_booking($id);
        WPYOGFLEET_DB::log_activity( 'status_changed', 'booking', $id,
            $booking->booking_number ?? 'Booking #'.$id,
            ( $old->status ?? '—' ) . ' → ' . $status );
        wp_send_json_success(array('status'=>$status, 'old_status' => $old->status ?? '', 'booking'=>$booking));
    }

    public function handle_mark_review_sent() {
        $this->fe_verify();
        $id   = absint( $_POST['booking_id'] ?? 0 ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
        $sent = ! empty( $_POST['sent'] ) ? 1 : 0;
        if ( ! $id ) { wp_send_json_error( array( 'message' => 'Missing booking ID.' ) ); }
        WPYOGFLEET_DB::update_review_sent( $id, $sent );
        $booking = WPYOGFLEET_DB::get_booking( $id );
        WPYOGFLEET_DB::log_activity( 'review_request_' . ( $sent ? 'sent' : 'unsent' ), 'booking', $id,
            $booking->booking_number ?? 'Booking #' . $id,
            $sent ? 'Marked review request as sent' : 'Marked review request as pending'
        );
        wp_send_json_success( array( 'booking' => $booking ) );
    }

    public function handle_get_vehicles() {
        $this->fe_verify();
        wp_send_json_success( WPYOGFLEET_DB::get_vehicles(array('status'=>'active')) );
    }

    public function handle_get_drivers() {
        $this->fe_verify();
        wp_send_json_success( WPYOGFLEET_DB::get_drivers(array('status'=>'active')) );
    }

    // ──────────────────────────────────────────────────────────────────────────
    // VEHICLES — full list (all statuses, for management view)
    // ──────────────────────────────────────────────────────────────────────────

    public function handle_get_vehicle_list() {
        $this->fe_verify();
        wp_send_json_success( WPYOGFLEET_DB::get_vehicles() );
    }

    public function handle_save_vehicle() {
        $this->fe_verify();
        $id = absint( $_POST['vehicle_id'] ?? 0 );
        if ( $id ) {
            WPYOGFLEET_DB::update_vehicle( $id, WPYOGFLEET_Input::vehicle() );
            $v = WPYOGFLEET_DB::get_vehicle( $id );
            WPYOGFLEET_DB::log_activity( 'vehicle_updated', 'vehicle', $id,
                $v->vehicle_name ?? 'Vehicle #'.$id, 'Updated via frontend' );
            wp_send_json_success( array( 'id' => $id, 'action' => 'updated' ) );
        } else {
            $new_id = WPYOGFLEET_DB::insert_vehicle( WPYOGFLEET_Input::vehicle() );
            if ( ! $new_id ) { wp_send_json_error( array('message'=>'Could not save vehicle.') ); }
            $v = WPYOGFLEET_DB::get_vehicle( $new_id );
            WPYOGFLEET_DB::log_activity( 'vehicle_created', 'vehicle', $new_id,
                $v->vehicle_name ?? 'Vehicle #'.$new_id,
                sanitize_text_field( wp_unslash( $_POST['vehicle_type'] ?? '' ) ) . ' — ' . sanitize_text_field( wp_unslash( $_POST['registration_number'] ?? '' ) ) );
            wp_send_json_success( array( 'id' => $new_id, 'action' => 'created' ) );
        }
    }

    public function handle_delete_vehicle() {
        $this->fe_verify();
        if ( ! current_user_can('wpyogfleet_admin') ) {
            wp_send_json_error( array('message'=>'Only admins can delete vehicles.') );
        }
        $id = absint( $_POST['vehicle_id'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
        $v  = WPYOGFLEET_DB::get_vehicle( $id );
        WPYOGFLEET_DB::log_activity( 'vehicle_deleted', 'vehicle', $id,
            $v->vehicle_name ?? 'Vehicle #'.$id, 'Deleted via frontend' );
        WPYOGFLEET_DB::delete_vehicle( $id );
        wp_send_json_success( array('deleted' => true) );
    }

    // ──────────────────────────────────────────────────────────────────────────
    // DRIVERS — full list (all statuses, for management view)
    // ──────────────────────────────────────────────────────────────────────────

    public function handle_get_driver_list() {
        $this->fe_verify();
        wp_send_json_success( WPYOGFLEET_DB::get_drivers() );
    }

    public function handle_save_driver() {
        $this->fe_verify();
        $id = absint( $_POST['driver_id'] ?? 0 );
        if ( $id ) {
            WPYOGFLEET_DB::update_driver( $id, WPYOGFLEET_Input::driver() );
            $d = WPYOGFLEET_DB::get_driver( $id );
            WPYOGFLEET_DB::log_activity( 'driver_updated', 'driver', $id,
                $d->driver_name ?? 'Driver #'.$id, 'Updated via frontend' );
            wp_send_json_success( array( 'id' => $id, 'action' => 'updated' ) );
        } else {
            $new_id = WPYOGFLEET_DB::insert_driver( WPYOGFLEET_Input::driver() );
            if ( ! $new_id ) { wp_send_json_error( array('message'=>'Could not save driver.') ); }
            $d = WPYOGFLEET_DB::get_driver( $new_id );
            WPYOGFLEET_DB::log_activity( 'driver_created', 'driver', $new_id,
                $d->driver_name ?? 'Driver #'.$new_id,
                sanitize_text_field( wp_unslash( $_POST['mobile'] ?? '' ) ) );
            wp_send_json_success( array( 'id' => $new_id, 'action' => 'created' ) );
        }
    }

    public function handle_delete_driver() {
        $this->fe_verify();
        if ( ! current_user_can('wpyogfleet_admin') ) {
            wp_send_json_error( array('message'=>'Only admins can delete drivers.') );
        }
        $id = absint( $_POST['driver_id'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
        $d  = WPYOGFLEET_DB::get_driver( $id );
        WPYOGFLEET_DB::log_activity( 'driver_deleted', 'driver', $id,
            $d->driver_name ?? 'Driver #'.$id, 'Deleted via frontend' );
        WPYOGFLEET_DB::delete_driver( $id );
        wp_send_json_success( array('deleted' => true) );
    }

    // ──────────────────────────────────────────────────────────────────────────
    // REPORTS
    // ──────────────────────────────────────────────────────────────────────────

    public function handle_get_reports() {
        global $wpdb;
        $this->fe_verify();
        $tab = sanitize_text_field( wp_unslash( $_POST['tab'] ?? 'monthly' ) );

        if ( $tab === 'monthly' ) {
            $m    = sanitize_text_field( wp_unslash( $_POST['month'] ?? gmdate('Y-m') ) );
            $parts = explode( '-', $m );
            $year  = (int) ( $parts[0] ?? gmdate('Y') );
            $month = (int) ( $parts[1] ?? gmdate('n') );
            $data = WPYOGFLEET_DB::get_monthly_report( $year, $month );
            $type_summary = WPYOGFLEET_DB::get_booking_type_summary( $year, $month );
            $data['corporate_amount']  = $type_summary['corporate_amount'];
            $data['individual_amount'] = $type_summary['individual_amount'];
            wp_send_json_success( array( 'tab' => 'monthly', 'data' => $data ) );

        } elseif ( $tab === 'drivers' ) {
            $from = sanitize_text_field( wp_unslash( $_POST['date_from'] ?? '' ) );
            $to   = sanitize_text_field( wp_unslash( $_POST['date_to']   ?? '' ) );
            $data = WPYOGFLEET_DB::get_driver_report( $from ?: null, $to ?: null );
            $debug = empty( $data ) ? WPYOGFLEET_DB::get_driver_report_debug() : null;
            wp_send_json_success( array( 'tab' => 'drivers', 'data' => $data, 'debug' => $debug ) );

        } elseif ( $tab === 'vehicles' ) {
            $from = sanitize_text_field( wp_unslash( $_POST['date_from'] ?? '' ) );
            $to   = sanitize_text_field( wp_unslash( $_POST['date_to']   ?? '' ) );
            $data = WPYOGFLEET_DB::get_vehicle_report( $from ?: null, $to ?: null );
            $debug = empty( $data ) ? WPYOGFLEET_DB::get_vehicle_report_debug() : null;
            wp_send_json_success( array( 'tab' => 'vehicles', 'data' => $data, 'debug' => $debug ) );

        } elseif ( $tab === 'customer_type' ) {
            $from = sanitize_text_field( wp_unslash( $_POST['date_from'] ?? '' ) );
            $to   = sanitize_text_field( wp_unslash( $_POST['date_to']   ?? '' ) );
            $ctype = sanitize_text_field( wp_unslash( $_POST['customer_type'] ?? 'all' ) );
            $data = WPYOGFLEET_DB::get_customer_type_report( $from ?: null, $to ?: null, $ctype );
            wp_send_json_success( array( 'tab' => 'customer_type', 'data' => $data ) );
        }

        wp_send_json_error( array( 'message' => 'Invalid report tab.' ) );
    }

    // Add logging to frontend booking/payment/expense saves
    public function handle_save_booking() {
        $this->fe_verify();
        $id = absint( $_POST['booking_id'] ?? 0 );
        if ( $id ) {
            WPYOGFLEET_DB::update_booking( $id, WPYOGFLEET_Input::booking() );
            $b = WPYOGFLEET_DB::get_booking( $id );
            WPYOGFLEET_DB::log_activity( 'booking_updated', 'booking', $id,
                $b->booking_number ?? 'Booking #'.$id, 'Updated via frontend' );
            wp_send_json_success( array( 'id' => $id, 'action' => 'updated' ) );
        } else {
            $new_id = WPYOGFLEET_DB::insert_booking( WPYOGFLEET_Input::booking() );
            if ( ! $new_id ) { wp_send_json_error( array('message'=>'Could not save booking.') ); }
            $b = WPYOGFLEET_DB::get_booking( $new_id );
            WPYOGFLEET_DB::log_activity( 'booking_created', 'booking', $new_id,
                $b->booking_number ?? 'Booking #'.$new_id,
                sanitize_text_field( wp_unslash( $_POST['customer_name'] ?? '' ) ) . ' — ' . sanitize_text_field( wp_unslash( $_POST['pickup_location'] ?? '' ) ) . ' → ' . sanitize_text_field( wp_unslash( $_POST['drop_location'] ?? '' ) )
            );
            wp_send_json_success( array( 'id' => $new_id, 'action' => 'created' ) );
        }
    }

    // -------------------------------------------------------------------------
    // CALENDAR
    // -------------------------------------------------------------------------

    public function handle_get_calendar_bookings() {
        $this->fe_verify();

        $year  = absint( $_POST['year']  ?? gmdate( 'Y' ) );
        $month = absint( $_POST['month'] ?? gmdate( 'n' ) );

        if ( $year < 2000 || $year > 2100 ) $year  = (int) gmdate( 'Y' );
        if ( $month < 1 || $month > 12 )   $month = (int) gmdate( 'n' );

        $date_from = sprintf( '%04d-%02d-01', $year, $month );
        $date_to   = gmdate( 'Y-m-t', strtotime( $date_from ) );

        $bookings = WPYOGFLEET_DB::get_bookings( array(
            'date_from' => $date_from,
            'date_to'   => $date_to,
            'limit'     => 500,
        ) );

        $events = array();
        foreach ( $bookings as $b ) {
            $events[] = array(
                'id'             => (int) $b->id,
                'number'         => $b->booking_number,
                'booking_type'   => $b->booking_type ?? 'individual',
                'customer'       => $b->customer_name,
                'company_name'   => $b->company_name ?? '',
                'passenger_name' => $b->passenger_name ?? '',
                'route'          => ( $b->pickup_location ?: '?' ) . ' → ' . ( $b->drop_location ?: '?' ),
                'date'           => substr( $b->start_date, 0, 10 ),
                'end_date'       => $b->end_date ? substr( $b->end_date, 0, 10 ) : null,
                'status'         => $b->status,
                'vehicle'        => $b->vehicle_name ?: '',
                'reg_number'     => $b->registration_number ?? '',
                'driver'         => $b->driver_name ?: '',
                'driver_mobile'  => $b->driver_mobile ?? '',
                'amount'         => (float) $b->booking_amount,
                'balance'        => (float) $b->balance_amount,
            );
        }

        wp_send_json_success( array( 'events' => $events ) );
    }
}
