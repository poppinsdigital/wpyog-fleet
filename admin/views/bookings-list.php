<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local vars only: this file is exclusively include()'d inside WPYOGFLEET_Admin::render(), so these are function-scoped, not real globals.
// Pagination
define( 'WPYOGFLEET_LIST_PER_PAGE', 25 );
$paged = max( 1, absint( $_GET['paged'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

// phpcs:disable WordPress.Security.NonceVerification.Recommended
// Sort — a single "sort" param packs orderby + direction (e.g. "date_desc")
$valid_sorts = array( 'date_desc', 'date_asc', 'id_desc', 'id_asc' );
$sort        = sanitize_text_field( wp_unslash( $_GET['sort'] ?? 'date_desc' ) );
if ( ! in_array( $sort, $valid_sorts, true ) ) $sort = 'date_desc';
list( $sort_by, $sort_dir ) = explode( '_', $sort );

// Filters
$filters = array(
    'status'          => sanitize_text_field( wp_unslash( $_GET['status'] ?? '' ) ),
    'vehicle_id'      => absint( $_GET['vehicle_id'] ?? 0 ),
    'driver_id'       => absint( $_GET['driver_id'] ?? 0 ),
    'customer_mobile' => sanitize_text_field( wp_unslash( $_GET['mobile'] ?? '' ) ),
    'date_from'       => sanitize_text_field( wp_unslash( $_GET['date_from'] ?? '' ) ),
    'date_to'         => sanitize_text_field( wp_unslash( $_GET['date_to'] ?? '' ) ),
    'search'          => sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ),
    'pending_only'    => isset( $_GET['pending'] ) ? 1 : 0,
    'orderby'         => $sort_by,
    'order'           => $sort_dir,
    'limit'           => WPYOGFLEET_LIST_PER_PAGE,
    'offset'          => ( $paged - 1 ) * WPYOGFLEET_LIST_PER_PAGE,
);
// phpcs:enable WordPress.Security.NonceVerification.Recommended
$bookings    = WPYOGFLEET_DB::get_bookings( $filters );
$total_count = WPYOGFLEET_DB::count_bookings_filtered( $filters );
$total_pages = max( 1, ceil( $total_count / WPYOGFLEET_LIST_PER_PAGE ) );
$vehicles    = WPYOGFLEET_DB::get_vehicles( array( 'status' => 'active' ) );
$drivers     = WPYOGFLEET_DB::get_drivers( array( 'status' => 'active' ) );
$is_filter   = array_filter( array( $filters['status'], $filters['vehicle_id'], $filters['driver_id'], $filters['customer_mobile'], $filters['date_from'], $filters['date_to'], $filters['search'], $filters['pending_only'] ) );

// Build base URL for pagination links (preserve all current filters except paged)
$base_args = array_filter( array(
    'page'       => 'wpyogfleet-bookings',
    's'          => $filters['search'],
    'status'     => $filters['status'],
    'vehicle_id' => $filters['vehicle_id'] ?: '',
    'driver_id'  => $filters['driver_id'] ?: '',
    'mobile'     => $filters['customer_mobile'],
    'date_from'  => $filters['date_from'],
    'date_to'    => $filters['date_to'],
    'pending'    => $filters['pending_only'] ? '1' : '',
    'sort'       => $sort !== 'date_desc' ? $sort : '',
) );
$base_url = admin_url( 'admin.php?' . http_build_query( $base_args ) );
?>

<div class="jtb-wrap">
    <div class="jtb-page-header">
        <div class="jtb-page-header__left">
            <h1 class="jtb-page-title">Bookings</h1>
            <p class="jtb-page-subtitle">
                <?php echo esc_html( number_format( $total_count ) ); ?> booking<?php echo esc_html( $total_count !== 1 ? 's' : '' ); ?> found
                <?php if ( $total_pages > 1 ) : ?>
                · Page <?php echo esc_html( $paged ); ?> of <?php echo esc_html( $total_pages ); ?>
                <?php endif; ?>
            </p>
        </div>
        <div class="jtb-page-header__right">
            <div class="jtb-view-toggle" id="jtb-view-toggle">
                <button class="jtb-view-toggle__btn is-active" data-view="list" title="List View">
                    <span class="dashicons dashicons-list-view"></span>
                </button>
                <button class="jtb-view-toggle__btn" data-view="calendar" title="Calendar View">
                    <span class="dashicons dashicons-calendar-alt"></span>
                </button>
            </div>
            <button class="jtb-btn jtb-btn-secondary" id="jtb-export-btn">
                <span class="dashicons dashicons-download"></span> Export CSV
            </button>
            <a href="<?php echo esc_url( admin_url('admin.php?page=wpyogfleet-bookings&action=add') ); ?>" class="jtb-btn jtb-btn-primary">
                <span class="dashicons dashicons-plus-alt2"></span> New Booking
            </a>
        </div>
    </div>

    <?php
    // Note: a "saved=1" notice isn't shown here — booking save always redirects to the
    // booking-detail view (action=view), never back to this list, so there's nothing to check.
    $wpyogfleet_notice_ok = isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wpyogfleet_notice' );
    ?>
    <?php if ( $wpyogfleet_notice_ok && ! empty( $_GET['deleted'] ) ) : ?>
    <div class="jtb-notice jtb-notice-success">Booking deleted.</div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="jtb-card jtb-filter-card" id="jtb-list-filters">
        <form method="GET" action="" class="jtb-filter-form">
            <input type="hidden" name="page" value="wpyogfleet-bookings">
            <div class="jtb-filter-row">
                <div class="jtb-filter-item jtb-filter-search">
                    <input type="text" name="s" placeholder="Search name, mobile, booking #…" value="<?php echo esc_attr( $filters['search'] ); ?>" class="jtb-input">
                </div>
                <div class="jtb-filter-item">
                    <select name="status" class="jtb-select">
                        <option value="">All Statuses</option>
                        <?php foreach ( array('new','confirmed','ongoing','completed','closed','cancelled') as $s ) : ?>
                        <option value="<?php echo esc_attr( $s ); ?>" <?php selected( $filters['status'], $s ); ?>><?php echo esc_html( ucfirst($s) ); ?></option>
                        <?php endforeach; ?>
                        <?php if ( current_user_can('wpyogfleet_admin') ) : ?>
                        <option value="archived" <?php selected( $filters['status'], 'archived' ); ?> style="color:#ef4444">📦 Archived</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="jtb-filter-item">
                    <select name="vehicle_id" class="jtb-select">
                        <option value="">All Vehicles</option>
                        <?php foreach ( $vehicles as $v ) : ?>
                        <option value="<?php echo absint( $v->id ); ?>" <?php selected( $filters['vehicle_id'], $v->id ); ?>><?php echo esc_html($v->vehicle_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="jtb-filter-item">
                    <select name="driver_id" class="jtb-select">
                        <option value="">All Drivers</option>
                        <?php foreach ( $drivers as $d ) : ?>
                        <option value="<?php echo absint( $d->id ); ?>" <?php selected( $filters['driver_id'], $d->id ); ?>><?php echo esc_html($d->driver_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="jtb-filter-item">
                    <input type="date" name="date_from" value="<?php echo esc_attr($filters['date_from']); ?>" class="jtb-input" placeholder="From">
                </div>
                <div class="jtb-filter-item">
                    <input type="date" name="date_to" value="<?php echo esc_attr($filters['date_to']); ?>" class="jtb-input" placeholder="To">
                </div>
                <div class="jtb-filter-item jtb-filter-check">
                    <label>
                        <input type="checkbox" name="pending" value="1" <?php checked( $filters['pending_only'] ); ?>>
                        Pending Only
                    </label>
                </div>
                <div class="jtb-filter-item">
                    <select name="sort" class="jtb-select" onchange="this.form.submit()">
                        <option value="date_desc" <?php selected( $sort, 'date_desc' ); ?>>Booking Date (Newest)</option>
                        <option value="date_asc"  <?php selected( $sort, 'date_asc' ); ?>>Booking Date (Oldest)</option>
                        <option value="id_desc"   <?php selected( $sort, 'id_desc' ); ?>>Booking ID (Newest)</option>
                        <option value="id_asc"    <?php selected( $sort, 'id_asc' ); ?>>Booking ID (Oldest)</option>
                    </select>
                </div>
                <div class="jtb-filter-item">
                    <button type="submit" class="jtb-btn jtb-btn-primary">Filter</button>
                    <?php if ( $is_filter ) : ?>
                    <a href="<?php echo esc_url( admin_url('admin.php?page=wpyogfleet-bookings') ); ?>" class="jtb-btn jtb-btn-ghost">Clear</a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <!-- Table -->
    <div class="jtb-card" id="jtb-list-table">
        <div class="jtb-card__body jtb-card__body--flush">
        <?php if ( $bookings ) : ?>
        <table class="jtb-table jtb-table--hover">
            <thead>
                <tr>
                    <th>Booking #</th>
                    <th>Customer</th>
                    <th>Route</th>
                    <th>Dates</th>
                    <th>Vehicle / Driver</th>
                    <th>Amount</th>
                    <th>Received</th>
                    <th>Balance</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $bookings as $b ) : ?>
                <tr class="jtb-booking-row" data-id="<?php echo esc_html( $b->id ); ?>">
                    <td>
                        <a href="<?php echo esc_url( admin_url('admin.php?page=wpyogfleet-bookings&action=view&id=' . $b->id) ); ?>" class="jtb-booking-num">
                            <?php echo esc_html( $b->booking_number ); ?>
                        </a>
                        <a href="<?php echo esc_url( admin_url('admin.php?page=wpyogfleet-bookings&action=view&id=' . $b->id) ); ?>"
                           class="jtb-open-new-tab" target="_blank" rel="noopener" title="Open in new tab">
                            <span class="dashicons dashicons-external"></span>
                        </a>
                        <div class="jtb-text-muted"><?php echo esc_html( WPYOGFLEET_Admin::trip_type_label( $b->trip_type ) ); ?></div>
                    </td>
                    <td>
                        <?php if ( ! empty( $b->booking_type ) && $b->booking_type === 'corporate' ) : ?>
                            <span class="jtb-badge badge-blue" style="font-size:10px;padding:1px 5px">Corp</span>
                            <div class="jtb-customer-name"><?php echo esc_html( $b->company_name ?: $b->customer_name ); ?></div>
                            <?php if ( ! empty( $b->passenger_name ) ) : ?>
                            <div class="jtb-text-muted"><?php echo esc_html( $b->passenger_name ); ?></div>
                            <?php endif; ?>
                        <?php else : ?>
                            <div class="jtb-customer-name"><?php echo esc_html( $b->customer_name ); ?></div>
                            <div class="jtb-text-muted"><?php echo esc_html( $b->customer_mobile ); ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="jtb-route">
                            <span><?php echo esc_html( $b->pickup_location ?: '—' ); ?></span>
                            <span class="jtb-route__arrow">→</span>
                            <span><?php echo esc_html( $b->drop_location ?: '—' ); ?></span>
                        </div>
                    </td>
                    <td>
                        <div><?php echo esc_html( WPYOGFLEET_Admin::format_date( $b->start_date ) ); ?></div>
                        <?php if ( $b->end_date && $b->end_date !== $b->start_date ) : ?>
                        <div class="jtb-text-muted">to <?php echo esc_html( WPYOGFLEET_Admin::format_date( $b->end_date ) ); ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ( ! empty( $b->is_outsourced ) ) : ?>
                            <div><?php echo esc_html( $b->outsourced_vehicle_number ?: '—' ); ?> <span class="jtb-badge badge-amber" style="font-size:9px;vertical-align:middle">Outsourced</span></div>
                            <div class="jtb-text-muted"><?php echo esc_html( $b->outsourced_driver_name ?? '' ); ?></div>
                        <?php elseif ( ! empty( $b->registration_number ) ) : ?>
                            <div><strong><?php echo esc_html( $b->registration_number ); ?></strong></div>
                            <div class="jtb-text-muted"><?php echo esc_html( $b->vehicle_name ?? '' ); ?></div>
                        <?php else : ?>
                            <div><?php echo esc_html( $b->vehicle_name ?? '—' ); ?></div>
                        <?php endif; ?>
                        <?php if ( empty( $b->is_outsourced ) ) : ?>
                        <div class="jtb-text-muted"><?php echo esc_html( $b->driver_name ?? '' ); ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="jtb-amount"><strong><?php echo esc_html( WPYOGFLEET_Admin::format_currency( $b->booking_amount ) ); ?></strong></td>
                    <td class="jtb-amount jtb-text-green"><?php echo esc_html( WPYOGFLEET_Admin::format_currency( $b->total_paid ) ); ?></td>
                    <td class="jtb-amount <?php echo esc_attr( (float)$b->balance_amount > 0 ? 'jtb-text-red' : 'jtb-text-green' ); ?>">
                        <?php echo esc_html( WPYOGFLEET_Admin::format_currency( $b->balance_amount ) ); ?>
                    </td>
                    <td>
                        <?php echo wp_kses_post( WPYOGFLEET_Admin::status_badge( $b->status ) ); ?>
                        <?php if ( $b->is_deleted ) : ?>
                        <span class="jtb-badge badge-red" style="margin-left:4px;font-size:10px">Archived</span>
                        <?php endif; ?>
                        <?php if ( in_array( $b->status, array( 'completed', 'closed' ), true ) ) : ?>
                        <br>
                        <span class="jtb-badge <?php echo ! empty( $b->review_request_sent ) ? 'badge-green' : 'badge-amber'; ?>" style="margin-top:4px;font-size:10px">
                            <?php echo ! empty( $b->review_request_sent ) ? '✅ Review Sent' : '⚠️ Review Pending'; ?>
                        </span>
                        <?php endif; ?>
                    </td>
                    <td class="jtb-actions">
                        <a href="<?php echo esc_url( admin_url('admin.php?page=wpyogfleet-bookings&action=view&id=' . $b->id) ); ?>" class="jtb-btn jtb-btn-xs jtb-btn-secondary">View</a>
                        <a href="<?php echo esc_url( admin_url('admin.php?page=wpyogfleet-bookings&action=edit&id=' . $b->id) ); ?>" class="jtb-btn jtb-btn-xs jtb-btn-ghost">Edit</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else : ?>
        <div class="jtb-empty-state">
            <span class="dashicons dashicons-calendar-alt"></span>
            <p><?php echo esc_html( $is_filter ? 'No bookings match your filters.' : 'No bookings yet.' ); ?>
               <a href="<?php echo esc_url( admin_url('admin.php?page=wpyogfleet-bookings&action=add') ); ?>">Create one →</a>
            </p>
        </div>
        <?php endif; ?>
        </div>
    </div>

    <!-- Calendar View (hidden by default, shown via JS toggle) -->
    <div class="jtb-card" id="jtb-cal-view" style="display:none">
        <div class="jtb-card__body">
            <div class="jtb-cal-wrap" id="wpyogfleet-bookings-calendar"></div>
        </div>
    </div>

    <?php if ( $total_pages > 1 ) : ?>
    <div id="jtb-list-pagination-wrap">
    <div class="jtb-pagination">
        <?php if ( $paged > 1 ) : ?>
        <a href="<?php echo esc_url( $base_url . '&paged=' . ( $paged - 1 ) ); ?>" class="jtb-btn jtb-btn-ghost jtb-btn-sm">← Prev</a>
        <?php endif; ?>

        <?php
        // Show at most 7 page links centered around current page
        $start = max( 1, $paged - 3 );
        $end   = min( $total_pages, $paged + 3 );
        if ( $start > 1 ) {
            echo '<a href="' . esc_url( $base_url . '&paged=1' ) . '" class="jtb-btn jtb-btn-ghost jtb-btn-sm">1</a>';
            if ( $start > 2 ) echo '<span class="jtb-pagination__ellipsis">…</span>';
        }
        for ( $i = $start; $i <= $end; $i++ ) :
        ?>
        <a href="<?php echo esc_url( $base_url . '&paged=' . $i ); ?>"
           class="jtb-btn jtb-btn-sm <?php echo esc_attr( $i === $paged ? 'jtb-btn-primary' : 'jtb-btn-ghost' ); ?>">
            <?php echo esc_html( $i ); ?>
        </a>
        <?php endfor; ?>

        <?php
        if ( $end < $total_pages ) {
            if ( $end < $total_pages - 1 ) echo '<span class="jtb-pagination__ellipsis">…</span>';
            echo '<a href="' . esc_url( $base_url . '&paged=' . $total_pages ) . '" class="jtb-btn jtb-btn-ghost jtb-btn-sm">' . esc_html( $total_pages ) . '</a>';
        }
        ?>

        <?php if ( $paged < $total_pages ) : ?>
        <a href="<?php echo esc_url( $base_url . '&paged=' . ( $paged + 1 ) ); ?>" class="jtb-btn jtb-btn-ghost jtb-btn-sm">Next →</a>
        <?php endif; ?>
    </div>
    </div><!-- /#jtb-list-pagination-wrap -->
    <?php endif; ?>

</div>

<?php ob_start(); ?>
document.getElementById('jtb-export-btn')?.addEventListener('click', function() {
    var form   = document.querySelector('.jtb-filter-form');
    var params = new URLSearchParams( new FormData(form) );
    jQuery.post(WPYOGFLEET.ajax_url, {
        action: 'wpyogfleet_export_bookings',
        nonce:  WPYOGFLEET.nonce,
        date_from: <?php echo wp_json_encode( $filters['date_from'] ); ?>,
        date_to:   <?php echo wp_json_encode( $filters['date_to'] ); ?>,
        status:    <?php echo wp_json_encode( $filters['status'] ); ?>,
    }, function(res) {
        if (res.success) {
            var link = document.createElement('a');
            link.href = 'data:text/csv;base64,' + res.data.csv;
            link.download = res.data.filename;
            link.click();
        }
    });
});
<?php wp_add_inline_script( 'wpyogfleet-admin', ob_get_clean() ); ?>
