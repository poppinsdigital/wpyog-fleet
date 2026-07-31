<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local vars only: this file is exclusively include()'d inside WPYOGFLEET_Admin::render(), so these are function-scoped, not real globals.
// Filters from GET
// phpcs:disable WordPress.Security.NonceVerification.Recommended
$filter_type   = sanitize_text_field( wp_unslash( $_GET['filter_type']   ?? '' ) );
$filter_entity = sanitize_text_field( wp_unslash( $_GET['filter_entity'] ?? '' ) );
$filter_from   = sanitize_text_field( wp_unslash( $_GET['filter_from']   ?? '' ) );
$filter_to     = sanitize_text_field( wp_unslash( $_GET['filter_to']     ?? '' ) );
$filter_search = sanitize_text_field( wp_unslash( $_GET['s']             ?? '' ) );

$per_page = 50;
$page     = max( 1, absint( $_GET['paged'] ?? 1 ) );
$offset   = ( $page - 1 ) * $per_page;
// phpcs:enable WordPress.Security.NonceVerification.Recommended

$args = array(
    'limit'       => $per_page,
    'offset'      => $offset,
    'action'      => $filter_type,
    'object_type' => $filter_entity,
    'date_from'   => $filter_from,
    'date_to'     => $filter_to,
    'search'      => $filter_search,
);

$logs  = WPYOGFLEET_DB::get_logs( $args );
$total = WPYOGFLEET_DB::count_logs( $args );
$pages = ceil( $total / $per_page );

// Action → label + colour map
$wpyogfleet_action_map = array(
    'booking_created'  => array( 'Booking Created',    'badge-indigo' ),
    'booking_updated'  => array( 'Booking Updated',    'badge-blue'   ),
    'booking_deleted'  => array( 'Booking Deleted',    'badge-red'    ),
    'status_changed'   => array( 'Status Changed',     'badge-amber'  ),
    'payment_added'    => array( 'Payment Added',      'badge-green'  ),
    'payment_deleted'  => array( 'Payment Deleted',    'badge-red'    ),
    'expense_added'    => array( 'Expense Added',      'badge-amber'  ),
    'expense_deleted'  => array( 'Expense Deleted',    'badge-red'    ),
    'vehicle_created'  => array( 'Vehicle Added',      'badge-teal'   ),
    'vehicle_updated'  => array( 'Vehicle Updated',    'badge-blue'   ),
    'vehicle_deleted'  => array( 'Vehicle Deleted',    'badge-red'    ),
    'driver_created'   => array( 'Driver Added',       'badge-teal'   ),
    'driver_updated'   => array( 'Driver Updated',     'badge-blue'   ),
    'driver_deleted'   => array( 'Driver Deleted',     'badge-red'    ),
);

function wpyogfleet_log_badge( $action ) {
    global $wpyogfleet_action_map;
    $info = $wpyogfleet_action_map[ $action ] ?? array( str_replace('_', ' ', ucfirst($action)), 'badge-gray' );
    return '<span class="jtb-badge ' . esc_attr($info[1]) . '" style="font-size:11px">' . esc_html($info[0]) . '</span>';
}

$base_url = admin_url( 'admin.php?page=wpyogfleet-logs' );
?>

<div class="jtb-wrap">
    <div class="jtb-page-header">
        <div class="jtb-page-header__left">
            <h1 class="jtb-page-title"><span class="jtb-brand">WPYog</span> Activity Logs</h1>
            <p class="jtb-page-subtitle">Track all actions made by staff and admins</p>
        </div>
        <?php if ( current_user_can( 'wpyogfleet_admin' ) ) : ?>
        <div class="jtb-page-header__right">
            <form method="POST" style="display:inline" onsubmit="return confirm('Clear ALL activity logs? This cannot be undone.')">
                <?php wp_nonce_field( 'wpyogfleet_clear_logs', 'wpyogfleet_clear_logs_nonce' ); ?>
                <button type="submit" class="jtb-btn jtb-btn-danger">
                    <span class="dashicons dashicons-trash" style="font-size:16px;vertical-align:middle;margin-right:4px"></span> Clear All Logs
                </button>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <?php
    $wpyogfleet_notice_ok = isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wpyogfleet_notice' );
    ?>
    <?php if ( $wpyogfleet_notice_ok && isset( $_GET['cleared'] ) ) : ?>
    <div class="notice notice-success is-dismissible"><p>✅ Activity logs cleared.</p></div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="jtb-card" style="margin-bottom:16px">
        <div class="jtb-card__body">
            <form method="GET" class="jtb-filter-form" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
                <input type="hidden" name="page" value="wpyogfleet-logs">
                <div class="jtb-form-group">
                    <label class="jtb-label">Action Type</label>
                    <select name="filter_type" class="jtb-select">
                        <option value="">All Actions</option>
                        <optgroup label="Bookings">
                            <option value="booking_created"  <?php echo selected($filter_type,'booking_created',false) ?>>Booking Created</option>
                            <option value="booking_updated"  <?php echo selected($filter_type,'booking_updated',false) ?>>Booking Updated</option>
                            <option value="booking_deleted"  <?php echo selected($filter_type,'booking_deleted',false) ?>>Booking Deleted</option>
                            <option value="status_changed"   <?php echo selected($filter_type,'status_changed',false) ?>>Status Changed</option>
                        </optgroup>
                        <optgroup label="Payments">
                            <option value="payment_added"    <?php echo selected($filter_type,'payment_added',false) ?>>Payment Added</option>
                            <option value="payment_deleted"  <?php echo selected($filter_type,'payment_deleted',false) ?>>Payment Deleted</option>
                        </optgroup>
                        <optgroup label="Expenses">
                            <option value="expense_added"    <?php echo selected($filter_type,'expense_added',false) ?>>Expense Added</option>
                            <option value="expense_deleted"  <?php echo selected($filter_type,'expense_deleted',false) ?>>Expense Deleted</option>
                        </optgroup>
                        <optgroup label="Vehicles & Drivers">
                            <option value="vehicle_created"  <?php echo selected($filter_type,'vehicle_created',false) ?>>Vehicle Added</option>
                            <option value="vehicle_updated"  <?php echo selected($filter_type,'vehicle_updated',false) ?>>Vehicle Updated</option>
                            <option value="driver_created"   <?php echo selected($filter_type,'driver_created',false) ?>>Driver Added</option>
                            <option value="driver_updated"   <?php echo selected($filter_type,'driver_updated',false) ?>>Driver Updated</option>
                        </optgroup>
                    </select>
                </div>
                <div class="jtb-form-group">
                    <label class="jtb-label">Entity</label>
                    <select name="filter_entity" class="jtb-select">
                        <option value="">All</option>
                        <option value="booking" <?php echo selected($filter_entity,'booking',false) ?>>Booking</option>
                        <option value="payment" <?php echo selected($filter_entity,'payment',false) ?>>Payment</option>
                        <option value="expense" <?php echo selected($filter_entity,'expense',false) ?>>Expense</option>
                        <option value="vehicle" <?php echo selected($filter_entity,'vehicle',false) ?>>Vehicle</option>
                        <option value="driver"  <?php echo selected($filter_entity,'driver',false) ?>>Driver</option>
                    </select>
                </div>
                <div class="jtb-form-group">
                    <label class="jtb-label">From Date</label>
                    <input type="date" name="filter_from" class="jtb-input" value="<?php echo esc_attr($filter_from) ?>">
                </div>
                <div class="jtb-form-group">
                    <label class="jtb-label">To Date</label>
                    <input type="date" name="filter_to" class="jtb-input" value="<?php echo esc_attr($filter_to) ?>">
                </div>
                <div class="jtb-form-group">
                    <label class="jtb-label">Search</label>
                    <input type="text" name="s" class="jtb-input" value="<?php echo esc_attr($filter_search) ?>" placeholder="Booking #, customer…" style="width:200px">
                </div>
                <button type="submit" class="jtb-btn jtb-btn-secondary">Filter</button>
                <?php if ( $filter_type || $filter_entity || $filter_from || $filter_to || $filter_search ) : ?>
                <a href="<?php echo esc_url($base_url) ?>" class="jtb-btn jtb-btn-ghost">Clear</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Log Table -->
    <div class="jtb-card">
        <div class="jtb-card__header">
            <h2 class="jtb-card__title">Log Entries <span class="jtb-badge badge-gray" style="font-weight:400;font-size:12px"><?php echo esc_html( number_format($total) ); ?> total</span></h2>
        </div>
        <div class="jtb-card__body jtb-card__body--flush">
            <?php if ( $logs ) : ?>
            <table class="jtb-table">
                <thead>
                    <tr>
                        <th style="width:160px">Date & Time</th>
                        <th style="width:120px">Action</th>
                        <th>Entity</th>
                        <th>Detail</th>
                        <th style="width:130px">By</th>
                        <th style="width:110px">IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $logs as $log ) : ?>
                    <tr>
                        <td>
                            <div><?php echo esc_html( WPYOGFLEET_Admin::format_date( $log->created_at ) ); ?></div>
                            <div class="jtb-text-muted" style="font-size:11px"><?php echo esc_html( gmdate( 'h:i A', strtotime( $log->created_at ) ) ) ?></div>
                        </td>
                        <td><?php echo wp_kses_post( wpyogfleet_log_badge( $log->action ) ); ?></td>
                        <td>
                            <div>
                                <span class="jtb-badge badge-gray" style="font-size:10px;text-transform:uppercase;margin-right:4px"><?php echo esc_html( $log->object_type ) ?></span>
                                <?php if ( $log->object_type === 'booking' && $log->object_id ) : ?>
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpyogfleet-bookings&action=view&id=' . $log->object_id ) ) ?>" class="jtb-link">
                                        <?php echo esc_html( $log->object_label ?: '#' . $log->object_id ) ?>
                                    </a>
                                <?php else : ?>
                                    <?php echo esc_html( $log->object_label ?: '—' ) ?>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <?php if ( $log->details ) : ?>
                            <span class="jtb-text-muted" style="font-size:12px"><?php echo esc_html( $log->details ) ?></span>
                            <?php else : ?>—<?php endif; ?>
                        </td>
                        <td>
                            <div style="font-weight:500"><?php echo esc_html( $log->user_name ?: '—' ) ?></div>
                        </td>
                        <td>
                            <span class="jtb-text-muted" style="font-size:11px;font-family:monospace"><?php echo esc_html( $log->ip_address ?: '—' ) ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ( $pages > 1 ) : ?>
            <div style="padding:14px 20px;border-top:1px solid var(--jtb-border);display:flex;justify-content:space-between;align-items:center">
                <span class="jtb-text-muted">
                    Showing <?php echo esc_html( absint( $offset ) + 1 ); ?>–<?php echo esc_html( absint( min( $offset + $per_page, $total ) ) ); ?> of <?php echo esc_html( number_format($total) ); ?>
                </span>
                <div style="display:flex;gap:6px">
                    <?php for ( $i = max(1,$page-2); $i <= min($pages,$page+2); $i++ ) : ?>
                    <?php
                    // Built from the already-sanitized filter vars above (not a fresh $_GET read),
                    // so this carries current filters into each page-number link.
                    $wpyogfleet_page_url = add_query_arg( array_filter( array(
                        'page'          => 'wpyogfleet-logs',
                        'paged'         => $i,
                        'filter_type'   => $filter_type,
                        'filter_entity' => $filter_entity,
                        'filter_from'   => $filter_from,
                        'filter_to'     => $filter_to,
                        's'             => $filter_search,
                    ) ) );
                    ?>
                    <a href="<?php echo esc_url( $wpyogfleet_page_url ); ?>"
                       class="jtb-btn jtb-btn-xs <?php echo esc_attr( $i===$page ? 'jtb-btn-primary' : 'jtb-btn-ghost' ) ?>">
                        <?php echo absint( $i ) ?>
                    </a>
                    <?php endfor; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php else : ?>
            <div class="jtb-empty-state">
                <span class="dashicons dashicons-list-view" style="font-size:48px;width:48px;height:48px;color:#cbd5e1"></span>
                <p>No activity logs found<?php echo $filter_type || $filter_entity || $filter_from || $filter_to || $filter_search ? ' for the selected filters' : ' yet' ?>.</p>
                <?php if ( $filter_type || $filter_entity || $filter_from || $filter_to || $filter_search ) : ?>
                <a href="<?php echo esc_url($base_url) ?>" class="jtb-btn jtb-btn-secondary" style="margin-top:8px">Clear Filters</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
