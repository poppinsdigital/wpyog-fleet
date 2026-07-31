<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local vars only: this file is exclusively include()'d inside WPYOGFLEET_Admin::render(), so these are function-scoped, not real globals.
$stats = WPYOGFLEET_DB::get_dashboard_stats();
?>

<div class="jtb-wrap">
    <div class="jtb-page-header">
        <div class="jtb-page-header__left">
            <h1 class="jtb-page-title"><span class="jtb-brand">WPYog</span> Fleet</h1>
            <p class="jtb-page-subtitle"><?php echo esc_html( gmdate( 'l, d F Y' ) ); ?></p>
        </div>
        <div class="jtb-page-header__right">
            <a href="<?php echo esc_url( admin_url('admin.php?page=wpyogfleet-bookings&action=add') ); ?>" class="jtb-btn jtb-btn-primary">
                <span class="dashicons dashicons-plus-alt2"></span> New Booking
            </a>
        </div>
    </div>

    <!-- Stats Row 1: Activity -->
    <div class="jtb-stats-grid">
        <div class="jtb-stat-card jtb-stat-blue">
            <div class="jtb-stat-card__icon"><span class="dashicons dashicons-calendar-alt"></span></div>
            <div class="jtb-stat-card__body">
                <div class="jtb-stat-card__value"><?php echo esc_html( $stats['today_bookings'] ); ?></div>
                <div class="jtb-stat-card__label">Today's Bookings</div>
            </div>
        </div>
        <div class="jtb-stat-card jtb-stat-amber">
            <div class="jtb-stat-card__icon"><span class="dashicons dashicons-car"></span></div>
            <div class="jtb-stat-card__body">
                <div class="jtb-stat-card__value"><?php echo esc_html( $stats['ongoing'] ); ?></div>
                <div class="jtb-stat-card__label">Ongoing Trips</div>
            </div>
        </div>
        <div class="jtb-stat-card jtb-stat-indigo">
            <div class="jtb-stat-card__icon"><span class="dashicons dashicons-clock"></span></div>
            <div class="jtb-stat-card__body">
                <div class="jtb-stat-card__value"><?php echo esc_html( $stats['upcoming'] ); ?></div>
                <div class="jtb-stat-card__label">Upcoming Bookings</div>
            </div>
        </div>
        <div class="jtb-stat-card jtb-stat-red">
            <div class="jtb-stat-card__icon"><span class="dashicons dashicons-warning"></span></div>
            <div class="jtb-stat-card__body">
                <div class="jtb-stat-card__value"><?php echo esc_html( $stats['pending_payments'] ); ?></div>
                <div class="jtb-stat-card__label">Pending Payments</div>
            </div>
        </div>
    </div>

    <!-- Stats Row 2: Monthly -->
    <div class="jtb-section-title">This Month — <?php echo esc_html( gmdate('F Y') ); ?></div>
    <div class="jtb-stats-grid jtb-stats-grid--3">
        <div class="jtb-stat-card jtb-stat-teal">
            <div class="jtb-stat-card__icon"><span class="dashicons dashicons-tickets-alt"></span></div>
            <div class="jtb-stat-card__body">
                <div class="jtb-stat-card__value"><?php echo esc_html( $stats['month_bookings'] ); ?></div>
                <div class="jtb-stat-card__label">Total Bookings</div>
            </div>
        </div>
        <div class="jtb-stat-card jtb-stat-green">
            <div class="jtb-stat-card__icon"><span class="dashicons dashicons-money-alt"></span></div>
            <div class="jtb-stat-card__body">
                <div class="jtb-stat-card__value"><?php echo esc_html( WPYOGFLEET_Admin::format_currency( $stats['month_revenue'] ) ); ?></div>
                <div class="jtb-stat-card__label">Revenue Collected</div>
            </div>
        </div>
        <div class="jtb-stat-card jtb-stat-orange">
            <div class="jtb-stat-card__icon"><span class="dashicons dashicons-chart-bar"></span></div>
            <div class="jtb-stat-card__body">
                <?php $profit = $stats['month_revenue'] - $stats['month_expenses']; ?>
                <div class="jtb-stat-card__value"><?php echo esc_html( WPYOGFLEET_Admin::format_currency( $profit ) ); ?></div>
                <div class="jtb-stat-card__label">Est. Profit (Revenue – Expenses)</div>
            </div>
        </div>
    </div>

    <!-- Calendar Widget -->
    <div class="jtb-card jtb-cal-card" id="jtb-dash-cal-card">
        <div class="jtb-card__header">
            <h2 class="jtb-card__title">Bookings Calendar</h2>
            <a href="<?php echo esc_url( admin_url('admin.php?page=wpyogfleet-bookings&view=calendar') ); ?>" class="jtb-link">Open in Bookings →</a>
        </div>
        <div class="jtb-card__body" id="jtb-dash-cal-body">
            <div class="jtb-cal-wrap" id="jtb-dash-calendar"></div>
        </div>
    </div>

    <!-- Recent Bookings -->
    <div class="jtb-card">
        <div class="jtb-card__header">
            <h2 class="jtb-card__title">Recent Bookings</h2>
            <a href="<?php echo esc_url( admin_url('admin.php?page=wpyogfleet-bookings') ); ?>" class="jtb-link">View All →</a>
        </div>
        <div class="jtb-card__body jtb-card__body--flush">
            <?php
            $recent = WPYOGFLEET_DB::get_bookings( array( 'limit' => 10 ) );
            if ( $recent ) : ?>
            <table class="jtb-table">
                <thead>
                    <tr>
                        <th>Booking #</th>
                        <th>Customer</th>
                        <th>Route</th>
                        <th>Dates</th>
                        <th>Vehicle</th>
                        <th>Amount</th>
                        <th>Balance</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $recent as $b ) : ?>
                    <tr>
                        <td><strong><?php echo esc_html( $b->booking_number ); ?></strong></td>
                        <td>
                            <?php if ( ! empty( $b->booking_type ) && $b->booking_type === 'corporate' ) : ?>
                            <div><?php echo esc_html( $b->company_name ?: $b->customer_name ); ?></div>
                            <?php if ( ! empty( $b->passenger_name ) ) : ?>
                            <div class="jtb-text-muted"><?php echo esc_html( $b->passenger_name ); ?></div>
                            <?php endif; ?>
                            <?php else : ?>
                            <div><?php echo esc_html( $b->customer_name ); ?></div>
                            <div class="jtb-text-muted"><?php echo esc_html( $b->customer_mobile ); ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="jtb-route">
                                <span><?php echo esc_html( $b->pickup_location ); ?></span>
                                <span class="jtb-route__arrow">→</span>
                                <span><?php echo esc_html( $b->drop_location ); ?></span>
                            </div>
                        </td>
                        <td>
                            <div><?php echo esc_html( WPYOGFLEET_Admin::format_date( $b->start_date ) ); ?></div>
                            <?php if ( $b->end_date && $b->end_date !== $b->start_date ) : ?>
                            <div class="jtb-text-muted">to <?php echo esc_html( WPYOGFLEET_Admin::format_date( $b->end_date ) ); ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo ! empty( $b->is_outsourced ) ? esc_html( ( $b->outsourced_vehicle_number ?: '—' ) . ' (Outsourced)' ) : esc_html( $b->vehicle_name ?? '—' ); ?></td>
                        <td class="jtb-amount"><?php echo esc_html( WPYOGFLEET_Admin::format_currency( $b->booking_amount ) ); ?></td>
                        <td class="jtb-amount <?php echo esc_attr( (float)$b->balance_amount > 0 ? 'jtb-text-red' : 'jtb-text-green' ); ?>">
                            <?php echo esc_html( WPYOGFLEET_Admin::format_currency( $b->balance_amount ) ); ?>
                        </td>
                        <td><?php echo wp_kses_post( WPYOGFLEET_Admin::status_badge( $b->status ) ); ?></td>
                        <td>
                            <a href="<?php echo esc_url( admin_url('admin.php?page=wpyogfleet-bookings&action=view&id=' . $b->id) ); ?>" class="jtb-btn jtb-btn-xs jtb-btn-secondary">View</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else : ?>
            <div class="jtb-empty-state">
                <span class="dashicons dashicons-calendar-alt"></span>
                <p>No bookings yet. <a href="<?php echo esc_url( admin_url('admin.php?page=wpyogfleet-bookings&action=add') ); ?>">Create your first booking →</a></p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
