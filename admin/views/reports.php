<?php if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local vars only: this file is exclusively include()'d inside WPYOGFLEET_Admin::render(), so these are function-scoped, not real globals.
// phpcs:disable WordPress.Security.NonceVerification.Recommended
$report_type = sanitize_text_field( wp_unslash( $_GET['report'] ?? 'monthly' ) );
$year        = absint( $_GET['year']  ?? gmdate('Y') );
$month       = absint( $_GET['month'] ?? gmdate('n') );
// Default to month-to-date (1st of current month → today) when no date filter has been submitted yet.
// Once the form is submitted (even with blank fields to view all-time data), respect exactly what was sent.
$dates_submitted = isset( $_GET['date_from'] ) || isset( $_GET['date_to'] );
$default_date_from = gmdate('Y-m-01');
$default_date_to   = gmdate('Y-m-d');
$date_from   = $dates_submitted ? sanitize_text_field( wp_unslash( $_GET['date_from'] ?? '' ) ) : $default_date_from;
$date_to     = $dates_submitted ? sanitize_text_field( wp_unslash( $_GET['date_to'] ?? '' ) ) : $default_date_to;
$cust_type   = sanitize_text_field( wp_unslash( $_GET['type'] ?? 'all' ) );
if ( ! in_array( $cust_type, array( 'all', 'corporate', 'individual' ), true ) ) $cust_type = 'all';
// phpcs:enable WordPress.Security.NonceVerification.Recommended
?>

<div class="jtb-wrap">
    <div class="jtb-page-header">
        <div class="jtb-page-header__left">
            <h1 class="jtb-page-title">Reports</h1>
        </div>
    </div>

    <!-- Report Type Tabs -->
    <div class="jtb-tabs">
        <?php
        $tabs = array( 'monthly' => 'Monthly Summary', 'pending' => 'Pending Payments', 'driver' => 'Driver-wise', 'vehicle' => 'Vehicle-wise', 'customer' => 'By Customer/Booking Type' );
        foreach ( $tabs as $key => $label ) :
        ?>
        <a href="<?php echo esc_url( admin_url('admin.php?page=wpyogfleet-reports&report='.$key) ); ?>"
           class="jtb-tab <?php echo esc_attr( $report_type === $key ? 'is-active' : '' ); ?>">
            <?php echo esc_html( $label ); ?>
        </a>
        <?php endforeach; ?>
    </div>

    <?php if ( $report_type === 'monthly' ) : ?>
    <!-- MONTHLY SUMMARY -->
    <div class="jtb-card">
        <div class="jtb-card__header">
            <h2 class="jtb-card__title">Monthly Summary</h2>
            <form method="GET" class="jtb-inline-form">
                <input type="hidden" name="page" value="wpyogfleet-reports">
                <input type="hidden" name="report" value="monthly">
                <select name="month" class="jtb-select jtb-select-sm">
                    <?php for ( $m = 1; $m <= 12; $m++ ) : ?>
                    <option value="<?php echo esc_attr( $m ); ?>" <?php selected($month,$m); ?>><?php echo esc_html( gmdate('F', mktime(0,0,0,$m,1)) ); ?></option>
                    <?php endfor; ?>
                </select>
                <select name="year" class="jtb-select jtb-select-sm">
                    <?php for ( $y = gmdate('Y'); $y >= 2023; $y-- ) : ?>
                    <option value="<?php echo esc_attr( $y ); ?>" <?php selected($year,$y); ?>><?php echo esc_attr( $y ); ?></option>
                    <?php endfor; ?>
                </select>
                <button type="submit" class="jtb-btn jtb-btn-primary jtb-btn-sm">Go</button>
            </form>
        </div>
        <div class="jtb-card__body">
            <?php $data = WPYOGFLEET_DB::get_monthly_report($year, $month);
            $profit = (float)$data['received'] - (float)$data['expenses']; ?>
            <h3 class="jtb-report-period"><?php echo esc_html( gmdate('F Y', mktime(0,0,0,$month,1,$year)) ); ?></h3>
            <div class="jtb-stats-grid jtb-stats-grid--4">
                <div class="jtb-stat-card jtb-stat-blue">
                    <div class="jtb-stat-card__body">
                        <div class="jtb-stat-card__value"><?php echo esc_html( $data['bookings'] ); ?></div>
                        <div class="jtb-stat-card__label">Total Bookings</div>
                    </div>
                </div>
                <div class="jtb-stat-card jtb-stat-indigo">
                    <div class="jtb-stat-card__body">
                        <div class="jtb-stat-card__value"><?php echo esc_html( WPYOGFLEET_Admin::format_currency($data['booking_amount']) ); ?></div>
                        <div class="jtb-stat-card__label">Total Booking Value</div>
                    </div>
                </div>
                <div class="jtb-stat-card jtb-stat-green">
                    <div class="jtb-stat-card__body">
                        <div class="jtb-stat-card__value"><?php echo esc_html( WPYOGFLEET_Admin::format_currency($data['received']) ); ?></div>
                        <div class="jtb-stat-card__label">Revenue Collected</div>
                    </div>
                </div>
                <div class="jtb-stat-card jtb-stat-orange">
                    <div class="jtb-stat-card__body">
                        <div class="jtb-stat-card__value"><?php echo esc_html( WPYOGFLEET_Admin::format_currency($data['expenses']) ); ?></div>
                        <div class="jtb-stat-card__label">Total Expenses</div>
                    </div>
                </div>
            </div>
            <div class="jtb-profit-box <?php echo esc_attr( $profit >= 0 ? 'jtb-profit' : 'jtb-loss' ); ?>">
                <span class="jtb-profit-label">Estimated Profit (Received – Expenses)</span>
                <span class="jtb-profit-value"><?php echo esc_html( WPYOGFLEET_Admin::format_currency($profit) ); ?></span>
            </div>

            <?php
            // Point 9 — Corporate vs Individual breakdown
            $type_summary = WPYOGFLEET_DB::get_booking_type_summary($year, $month);
            $total_count  = $type_summary['individual_count']  + $type_summary['corporate_count'];
            $total_value  = $type_summary['individual_amount'] + $type_summary['corporate_amount'];
            ?>

            <div class="jtb-stats-grid jtb-stats-grid--2" style="margin-top:16px">
                <div class="jtb-stat-card jtb-stat-teal">
                    <div class="jtb-stat-card__body">
                        <div class="jtb-stat-card__value"><?php echo esc_html( WPYOGFLEET_Admin::format_currency($type_summary['corporate_amount']) ); ?></div>
                        <div class="jtb-stat-card__label">Total Booking – Corp Customer</div>
                    </div>
                </div>
                <div class="jtb-stat-card jtb-stat-amber">
                    <div class="jtb-stat-card__body">
                        <div class="jtb-stat-card__value"><?php echo esc_html( WPYOGFLEET_Admin::format_currency($type_summary['individual_amount']) ); ?></div>
                        <div class="jtb-stat-card__label">Total Booking – Individual Customer</div>
                    </div>
                </div>
            </div>

            <?php if ( $total_count > 0 ) : ?>
            <div style="margin-top:20px">
                <h4 style="font-size:13px;font-weight:600;color:#374151;margin:0 0 10px">Booking Type Breakdown</h4>
                <table class="jtb-table" style="max-width:480px">
                    <thead>
                        <tr><th>Type</th><th class="jtb-amount">Bookings</th><th class="jtb-amount">Booking Value</th></tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Individual</td>
                            <td class="jtb-amount"><?php echo esc_html( $type_summary['individual_count'] ); ?></td>
                            <td class="jtb-amount"><?php echo esc_html( WPYOGFLEET_Admin::format_currency($type_summary['individual_amount']) ); ?></td>
                        </tr>
                        <tr>
                            <td>Corporate</td>
                            <td class="jtb-amount"><?php echo esc_html( $type_summary['corporate_count'] ); ?></td>
                            <td class="jtb-amount"><?php echo esc_html( WPYOGFLEET_Admin::format_currency($type_summary['corporate_amount']) ); ?></td>
                        </tr>
                        <tr style="font-weight:600;border-top:2px solid #e5e7eb">
                            <td>Total</td>
                            <td class="jtb-amount"><?php echo esc_html( $total_count ); ?></td>
                            <td class="jtb-amount"><?php echo esc_html( WPYOGFLEET_Admin::format_currency($total_value) ); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Monthly Bookings Table -->
    <div class="jtb-card">
        <div class="jtb-card__header">
            <h2 class="jtb-card__title">Bookings this Month</h2>
        </div>
        <div class="jtb-card__body jtb-card__body--flush">
            <?php
            $m_s = sprintf('%04d-%02d-01', $year, $month);
            $m_e = gmdate('Y-m-t', strtotime($m_s));
            $bookings = WPYOGFLEET_DB::get_bookings(array('date_from'=>$m_s,'date_to'=>$m_e,'limit'=>500));
            if ($bookings) :
            ?>
            <table class="jtb-table">
                <thead>
                    <tr><th>Booking #</th><th>Customer</th><th>Route</th><th>Vehicle</th><th>Amount</th><th>Received</th><th>Balance</th><th>Status</th></tr>
                </thead>
                <tbody>
                <?php foreach ($bookings as $b) : ?>
                <tr>
                    <td><a href="<?php echo esc_url( admin_url('admin.php?page=wpyogfleet-bookings&action=view&id='.$b->id) ); ?>"><?php echo esc_html($b->booking_number); ?></a></td>
                    <td>
                        <?php echo esc_html($b->customer_name); ?><br>
                        <span class="jtb-text-muted"><?php echo esc_html($b->customer_mobile); ?></span>
                    </td>
                    <td><?php echo esc_html($b->pickup_location ?: '—'); ?> → <?php echo esc_html($b->drop_location ?: '—'); ?></td>
                    <td><?php echo esc_html($b->vehicle_name ?: '—'); ?></td>
                    <td class="jtb-amount"><?php echo esc_html( WPYOGFLEET_Admin::format_currency($b->booking_amount) ); ?></td>
                    <td class="jtb-amount jtb-text-green"><?php echo esc_html( WPYOGFLEET_Admin::format_currency($b->total_paid) ); ?></td>
                    <td class="jtb-amount <?php echo esc_attr( (float)$b->balance_amount > 0 ? 'jtb-text-red' : 'jtb-text-green' ); ?>"><?php echo esc_html( WPYOGFLEET_Admin::format_currency($b->balance_amount) ); ?></td>
                    <td><?php echo wp_kses_post( WPYOGFLEET_Admin::status_badge($b->status) ); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else : ?>
            <div class="jtb-empty-state"><p>No bookings for this period.</p></div>
            <?php endif; ?>
        </div>
    </div>

    <?php elseif ( $report_type === 'pending' ) : ?>
    <!-- PENDING PAYMENTS -->
    <div class="jtb-card">
        <div class="jtb-card__header">
            <h2 class="jtb-card__title">Pending Payments</h2>
            <button class="jtb-btn jtb-btn-sm jtb-btn-secondary" id="jtb-export-pending-btn">
                <span class="dashicons dashicons-download"></span> Export
            </button>
        </div>
        <div class="jtb-card__body jtb-card__body--flush">
            <?php $rows = WPYOGFLEET_DB::get_pending_payments_report(); ?>
            <?php if ($rows) : ?>
            <?php
            $total_pending = array_sum(array_column((array)$rows, 'balance'));
            ?>
            <div class="jtb-report-summary-bar">
                <span><?php echo absint( count($rows) ); ?> bookings with pending balance</span>
                <strong>Total Pending: <?php echo esc_html( WPYOGFLEET_Admin::format_currency($total_pending) ); ?></strong>
            </div>
            <table class="jtb-table">
                <thead>
                    <tr><th>Booking #</th><th>Customer</th><th>Mobile</th><th>Trip Date</th><th>Booking Amount</th><th>Received</th><th>Balance Pending</th><th>Action</th></tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r) : ?>
                <tr>
                    <td><a href="<?php echo esc_url( admin_url('admin.php?page=wpyogfleet-bookings&action=view&id='.$r->id) ); ?>"><?php echo esc_html($r->booking_number); ?></a></td>
                    <td><?php echo esc_html($r->customer_name); ?></td>
                    <td><?php echo esc_html($r->customer_mobile); ?></td>
                    <td><?php echo esc_html( WPYOGFLEET_Admin::format_date($r->start_date) ); ?></td>
                    <td class="jtb-amount"><?php echo esc_html( WPYOGFLEET_Admin::format_currency($r->booking_amount) ); ?></td>
                    <td class="jtb-amount jtb-text-green"><?php echo esc_html( WPYOGFLEET_Admin::format_currency($r->total_paid) ); ?></td>
                    <td class="jtb-amount jtb-text-red"><strong><?php echo esc_html( WPYOGFLEET_Admin::format_currency($r->balance) ); ?></strong></td>
                    <td><a href="<?php echo esc_url( admin_url('admin.php?page=wpyogfleet-bookings&action=view&id='.$r->id) ); ?>" class="jtb-btn jtb-btn-xs jtb-btn-primary">Add Payment</a></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else : ?>
            <div class="jtb-empty-state">
                <span class="dashicons dashicons-yes-alt" style="color:#16a34a;font-size:48px;height:48px;width:48px;"></span>
                <p>No pending payments. All bookings are fully paid!</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php elseif ( $report_type === 'driver' ) : ?>
    <!-- DRIVER-WISE -->
    <div class="jtb-card">
        <div class="jtb-card__header">
            <h2 class="jtb-card__title">Driver-wise Report</h2>
            <form method="GET" class="jtb-inline-form">
                <input type="hidden" name="page" value="wpyogfleet-reports">
                <input type="hidden" name="report" value="driver">
                <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>" class="jtb-input jtb-input-sm">
                <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>" class="jtb-input jtb-input-sm">
                <button type="submit" class="jtb-btn jtb-btn-primary jtb-btn-sm">Filter</button>
                <?php if ($date_from || $date_to) : ?>
                <a href="<?php echo esc_url( admin_url('admin.php?page=wpyogfleet-reports&report=driver') ); ?>" class="jtb-btn jtb-btn-ghost jtb-btn-sm">Clear</a>
                <?php endif; ?>
            </form>
        </div>
        <div class="jtb-card__body jtb-card__body--flush">
            <?php $rows = WPYOGFLEET_DB::get_driver_report($date_from ?: null, $date_to ?: null); ?>
            <?php if ($rows) : ?>
            <table class="jtb-table">
                <thead>
                    <tr><th>Driver</th><th>Mobile</th><th>Trips</th><th>Total Value</th><th>Received</th><th>Expenses</th><th>Est. Profit</th></tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r) : ?>
                <?php $rp = (float)$r->total_received - (float)$r->total_expenses; ?>
                <tr>
                    <td><strong><?php echo esc_html($r->driver_name); ?></strong></td>
                    <td><?php echo esc_html($r->mobile ?: '—'); ?></td>
                    <td><?php echo esc_html( $r->trips ); ?></td>
                    <td class="jtb-amount"><?php echo esc_html( WPYOGFLEET_Admin::format_currency($r->total_amount) ); ?></td>
                    <td class="jtb-amount jtb-text-green"><?php echo esc_html( WPYOGFLEET_Admin::format_currency($r->total_received) ); ?></td>
                    <td class="jtb-amount jtb-text-red"><?php echo esc_html( WPYOGFLEET_Admin::format_currency($r->total_expenses) ); ?></td>
                    <td class="jtb-amount <?php echo esc_attr( $rp >= 0 ? 'jtb-text-green' : 'jtb-text-red' ); ?>"><strong><?php echo esc_html( WPYOGFLEET_Admin::format_currency($rp) ); ?></strong></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else : ?>
            <div class="jtb-empty-state"><p>No data available.</p></div>
            <?php endif; ?>
        </div>
    </div>

    <?php elseif ( $report_type === 'vehicle' ) : ?>
    <!-- VEHICLE-WISE -->
    <div class="jtb-card">
        <div class="jtb-card__header">
            <h2 class="jtb-card__title">Vehicle-wise Report</h2>
            <form method="GET" class="jtb-inline-form">
                <input type="hidden" name="page" value="wpyogfleet-reports">
                <input type="hidden" name="report" value="vehicle">
                <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>" class="jtb-input jtb-input-sm">
                <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>" class="jtb-input jtb-input-sm">
                <button type="submit" class="jtb-btn jtb-btn-primary jtb-btn-sm">Filter</button>
                <?php if ($date_from || $date_to) : ?>
                <a href="<?php echo esc_url( admin_url('admin.php?page=wpyogfleet-reports&report=vehicle') ); ?>" class="jtb-btn jtb-btn-ghost jtb-btn-sm">Clear</a>
                <?php endif; ?>
            </form>
        </div>
        <div class="jtb-card__body jtb-card__body--flush">
            <?php $rows = WPYOGFLEET_DB::get_vehicle_report($date_from ?: null, $date_to ?: null); ?>
            <?php if ($rows) : ?>
            <table class="jtb-table">
                <thead>
                    <tr><th>Vehicle</th><th>Reg. Number</th><th>Trips</th><th>Total Value</th><th>Received</th><th>Expenses</th><th>Est. Profit</th></tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r) : ?>
                <?php $rp = (float)$r->total_received - (float)$r->total_expenses; ?>
                <tr>
                    <td><strong><?php echo esc_html($r->vehicle_name); ?></strong></td>
                    <td><?php echo esc_html($r->registration_number ?: '—'); ?></td>
                    <td><?php echo esc_html( $r->trips ); ?></td>
                    <td class="jtb-amount"><?php echo esc_html( WPYOGFLEET_Admin::format_currency($r->total_amount) ); ?></td>
                    <td class="jtb-amount jtb-text-green"><?php echo esc_html( WPYOGFLEET_Admin::format_currency($r->total_received) ); ?></td>
                    <td class="jtb-amount jtb-text-red"><?php echo esc_html( WPYOGFLEET_Admin::format_currency($r->total_expenses) ); ?></td>
                    <td class="jtb-amount <?php echo esc_attr( $rp >= 0 ? 'jtb-text-green' : 'jtb-text-red' ); ?>"><strong><?php echo esc_html( WPYOGFLEET_Admin::format_currency($rp) ); ?></strong></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else : ?>
            <div class="jtb-empty-state"><p>No data available.</p></div>
            <?php endif; ?>
        </div>
    </div>

    <?php elseif ( $report_type === 'customer' ) : ?>
    <!-- BY CUSTOMER / BOOKING TYPE -->
    <div class="jtb-card">
        <div class="jtb-card__header">
            <h2 class="jtb-card__title">By Customer / Booking Type</h2>
            <form method="GET" class="jtb-inline-form">
                <input type="hidden" name="page" value="wpyogfleet-reports">
                <input type="hidden" name="report" value="customer">
                <select name="type" class="jtb-select jtb-select-sm">
                    <option value="all" <?php selected($cust_type,'all'); ?>>All</option>
                    <option value="corporate" <?php selected($cust_type,'corporate'); ?>>Corporate</option>
                    <option value="individual" <?php selected($cust_type,'individual'); ?>>Individual</option>
                </select>
                <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>" class="jtb-input jtb-input-sm">
                <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>" class="jtb-input jtb-input-sm">
                <button type="submit" class="jtb-btn jtb-btn-primary jtb-btn-sm">Filter</button>
                <?php if ($date_from || $date_to || $cust_type !== 'all') : ?>
                <a href="<?php echo esc_url( admin_url('admin.php?page=wpyogfleet-reports&report=customer') ); ?>" class="jtb-btn jtb-btn-ghost jtb-btn-sm">Clear</a>
                <?php endif; ?>
            </form>
        </div>
        <div class="jtb-card__body jtb-card__body--flush">
            <?php
            $rows = WPYOGFLEET_DB::get_customer_type_report($date_from ?: null, $date_to ?: null, $cust_type);
            $grand_bookings = array_sum(array_column((array)$rows, 'bookings'));
            $grand_amount   = array_sum(array_column((array)$rows, 'total_amount'));
            ?>
            <?php if ($rows) : ?>
            <table class="jtb-table jtb-table--left-amounts">
                <thead>
                    <tr><th>Customer</th><th>Type</th><th class="jtb-amount">Bookings</th><th class="jtb-amount">Total Booking Amount</th></tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r) : ?>
                <tr>
                    <td><strong><?php echo esc_html($r->name); ?></strong></td>
                    <td><?php echo esc_html($r->type); ?></td>
                    <td class="jtb-amount"><?php echo esc_html($r->bookings); ?></td>
                    <td class="jtb-amount"><?php echo esc_html( WPYOGFLEET_Admin::format_currency($r->total_amount) ); ?></td>
                </tr>
                <?php endforeach; ?>
                <tr style="font-weight:600;border-top:2px solid #e5e7eb">
                    <td colspan="2">Total</td>
                    <td class="jtb-amount"><?php echo esc_html($grand_bookings); ?></td>
                    <td class="jtb-amount"><?php echo esc_html( WPYOGFLEET_Admin::format_currency($grand_amount) ); ?></td>
                </tr>
                </tbody>
            </table>
            <?php else : ?>
            <div class="jtb-empty-state"><p>No data available.</p></div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
wp_add_inline_script( 'wpyogfleet-admin', "document.getElementById('jtb-export-pending-btn')?.addEventListener('click', function() {
    jQuery.post(WPYOGFLEET.ajax_url, { action:'wpyogfleet_export_pending', nonce:WPYOGFLEET.nonce }, function(res) {
        if (res.success) {
            var link = document.createElement('a');
            link.href = 'data:text/csv;base64,' + res.data.csv;
            link.download = res.data.filename;
            link.click();
        }
    });
});" );
?>
