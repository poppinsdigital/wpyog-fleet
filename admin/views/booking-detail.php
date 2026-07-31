<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local vars only: this file is exclusively include()'d inside WPYOGFLEET_Admin::render(), so these are function-scoped, not real globals.
$profit = (float)$booking->booking_amount - (float)$booking->total_expenses;
$balance = (float)$booking->balance_amount;
?>

<div class="jtb-wrap">
    <?php if ( $booking->is_deleted ) : ?>
    <div class="jtb-notice jtb-notice--warning" style="background:#fef3c7;border-left:4px solid #f59e0b;padding:12px 16px;border-radius:6px;margin-bottom:16px;display:flex;align-items:flex-start;gap:10px">
        <span style="font-size:18px">📦</span>
        <div>
            <strong>This booking is archived.</strong>
            <?php if ( $booking->deleted_reason ) : ?>
            <span style="color:#6b7280"> Reason: <?php echo esc_html( $booking->deleted_reason ); ?></span>
            <?php endif; ?>
            <div style="font-size:12px;color:#6b7280;margin-top:2px">Archived on <?php echo esc_html( WPYOGFLEET_Admin::format_datetime( $booking->deleted_at ) ); ?></div>
        </div>
    </div>
    <?php endif; ?>
    <div class="jtb-page-header">
        <div class="jtb-page-header__left">
            <a href="<?php echo esc_url( admin_url('admin.php?page=wpyogfleet-bookings') ); ?>" class="jtb-back-link">← All Bookings</a>
            <div class="jtb-page-title-group">
                <h1 class="jtb-page-title"><?php echo esc_html( $booking->booking_number ); ?></h1>
                <?php echo wp_kses_post( WPYOGFLEET_Admin::status_badge( $booking->status ) ); ?>
                <?php if ( ! empty( $booking->booking_type ) && $booking->booking_type === 'corporate' ) : ?>
                <span class="jtb-badge badge-blue" style="margin-left:6px">Corporate</span>
                <?php endif; ?>
                <?php if ( $booking->is_deleted ) : ?>
                <span class="jtb-badge badge-red" style="margin-left:6px">Archived</span>
                <?php endif; ?>
                <?php if ( in_array( $booking->status, array( 'completed', 'closed' ), true ) ) : ?>
                <span class="jtb-badge <?php echo ! empty( $booking->review_request_sent ) ? 'badge-green' : 'badge-amber'; ?> jtb-review-badge"
                      data-booking="<?php echo esc_attr( $booking->id ); ?>" style="margin-left:6px">
                    <?php echo ! empty( $booking->review_request_sent ) ? '✅ Review Sent' : '⚠️ Review Pending'; ?>
                </span>
                <?php endif; ?>
            </div>
            <p class="jtb-page-subtitle">
                <?php if ( ! empty( $booking->booking_type ) && $booking->booking_type === 'corporate' && ! empty( $booking->company_name ) ) : ?>
                <?php echo esc_html( $booking->company_name ); ?> &middot;
                <?php endif; ?>
                <?php echo esc_html( $booking->customer_name ); ?> &middot;
                <?php echo esc_html( $booking->customer_mobile ); ?>
                <?php if ( ! empty( $booking->customer_email ) ) : ?>
                &middot; <?php echo esc_html( $booking->customer_email ); ?>
                <?php endif; ?>
            </p>
        </div>
        <div class="jtb-page-header__right">
            <?php
            $wpyogfleet_notice_ok = isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wpyogfleet_notice' );
            ?>
            <?php if ( $wpyogfleet_notice_ok && ! empty( $_GET['saved'] ) ) : ?>
            <span class="jtb-inline-notice">✓ Saved</span>
            <?php endif; ?>

            <?php if ( ! $booking->is_deleted ) : ?>
            <!-- Quick status changer -->
            <div class="jtb-status-changer">
                <select id="jtb-status-select" class="jtb-select jtb-select-sm" data-booking="<?php echo esc_html( $booking->id ); ?>">
                    <?php foreach ( array('new','confirmed','ongoing','completed','closed','cancelled') as $s ) : ?>
                    <option value="<?php echo esc_attr( $s ); ?>" <?php selected($booking->status,$s); ?>><?php echo esc_html( ucfirst($s) ); ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="jtb-status-hint">Change Status</span>
            </div>

            <a href="<?php echo esc_url( admin_url('admin.php?page=wpyogfleet-bookings&action=edit&id='.$booking->id) ); ?>" class="jtb-btn jtb-btn-secondary">
                <span class="dashicons dashicons-edit"></span> Edit
            </a>
            <?php if ( current_user_can('wpyogfleet_admin') ) : ?>
            <button class="jtb-btn jtb-btn-danger" id="jtb-open-archive-modal">
                Archive Booking
            </button>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="jtb-summary-grid" id="jtb-summary-area">
        <div class="jtb-summary-item">
            <span class="label">Booking Amount</span>
            <span class="value"><?php echo esc_html( WPYOGFLEET_Admin::format_currency($booking->booking_amount) ); ?></span>
        </div>
        <div class="jtb-summary-item jtb-received">
            <span class="label">Total Received</span>
            <span class="value"><?php echo esc_html( WPYOGFLEET_Admin::format_currency($booking->total_paid) ); ?></span>
        </div>
        <div class="jtb-summary-item <?php echo esc_attr( $balance > 0 ? 'jtb-pending' : 'jtb-clear' ); ?>">
            <span class="label">Balance Pending</span>
            <span class="value"><?php echo esc_html( WPYOGFLEET_Admin::format_currency($balance) ); ?></span>
        </div>
        <div class="jtb-summary-item jtb-expense">
            <span class="label">Total Expenses</span>
            <span class="value"><?php echo esc_html( WPYOGFLEET_Admin::format_currency($booking->total_expenses) ); ?></span>
        </div>
        <div class="jtb-summary-item <?php echo esc_attr( $profit >= 0 ? 'jtb-profit' : 'jtb-loss' ); ?>">
            <span class="label">Gross Profit</span>
            <span class="value"><?php echo esc_html( WPYOGFLEET_Admin::format_currency($profit) ); ?></span>
        </div>
    </div>

    <div class="jtb-detail-grid">

        <!-- LEFT: Booking Info -->
        <div class="jtb-detail-col jtb-detail-col--left">

            <!-- Trip Info -->
            <div class="jtb-card">
                <div class="jtb-card__header"><h2 class="jtb-card__title">Trip Details</h2></div>
                <div class="jtb-card__body">
                    <div class="jtb-info-grid">
                        <div class="jtb-info-item">
                            <span class="jtb-info-label">Trip Type</span>
                            <span class="jtb-info-value"><?php echo esc_html( WPYOGFLEET_Admin::trip_type_label($booking->trip_type) ); ?></span>
                        </div>
                        <div class="jtb-info-item">
                            <span class="jtb-info-label">From</span>
                            <span class="jtb-info-value"><?php echo esc_html($booking->pickup_location ?: '—'); ?></span>
                        </div>
                        <div class="jtb-info-item">
                            <span class="jtb-info-label">To</span>
                            <span class="jtb-info-value"><?php echo esc_html($booking->drop_location ?: '—'); ?></span>
                        </div>
                        <div class="jtb-info-item">
                            <span class="jtb-info-label">Start</span>
                            <span class="jtb-info-value"><?php echo esc_html( WPYOGFLEET_Admin::format_datetime($booking->start_date) ); ?></span>
                        </div>
                        <div class="jtb-info-item">
                            <span class="jtb-info-label">End</span>
                            <span class="jtb-info-value"><?php echo esc_html( WPYOGFLEET_Admin::format_datetime($booking->end_date) ); ?></span>
                        </div>
                        <?php if ( $booking->notes ) : ?>
                        <div class="jtb-info-item jtb-info-item--full">
                            <span class="jtb-info-label">Notes</span>
                            <span class="jtb-info-value"><?php echo nl2br(esc_html($booking->notes)); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Vehicle & Driver -->
            <div class="jtb-card">
                <div class="jtb-card__header"><h2 class="jtb-card__title">Vehicle & Driver</h2></div>
                <div class="jtb-card__body">
                    <div class="jtb-info-grid">
                        <?php if ( ! empty( $booking->is_outsourced ) ) : ?>
                        <div class="jtb-info-item">
                            <span class="jtb-info-label">Vehicle</span>
                            <span class="jtb-info-value"><?php echo $booking->outsourced_vehicle_number ? esc_html($booking->outsourced_vehicle_number) : '—'; ?></span>
                        </div>
                        <div class="jtb-info-item">
                            <span class="jtb-info-label">Driver</span>
                            <span class="jtb-info-value">
                                <?php echo $booking->outsourced_driver_name ? esc_html($booking->outsourced_driver_name) : '—'; ?>
                                <?php if ($booking->outsourced_driver_mobile) : ?>
                                <span class="jtb-reg-num"><?php echo esc_html($booking->outsourced_driver_mobile); ?></span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="jtb-info-item">
                            <span class="jtb-info-label">Source</span>
                            <span class="jtb-info-value"><span class="jtb-badge badge-amber">Outsourced</span></span>
                        </div>
                        <?php else : ?>
                        <div class="jtb-info-item">
                            <span class="jtb-info-label">Vehicle</span>
                            <span class="jtb-info-value">
                                <?php echo $booking->vehicle_name ? esc_html($booking->vehicle_name) : '—'; ?>
                                <?php if ($booking->registration_number) : ?>
                                <span class="jtb-reg-num"><?php echo esc_html($booking->registration_number); ?></span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="jtb-info-item">
                            <span class="jtb-info-label">Driver</span>
                            <span class="jtb-info-value"><?php echo $booking->driver_name ? esc_html($booking->driver_name) : '—'; ?></span>
                        </div>
                        <?php if ( $booking->vehicle_owner_type === 'outsourced' ) : ?>
                        <div class="jtb-info-item">
                            <span class="jtb-info-label">Vehicle Type</span>
                            <span class="jtb-info-value"><span class="jtb-badge badge-amber">Outsourced</span></span>
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Booking Meta -->
            <div class="jtb-card">
                <div class="jtb-card__header"><h2 class="jtb-card__title">Booking Info</h2></div>
                <div class="jtb-card__body">
                    <div class="jtb-info-grid">
                        <div class="jtb-info-item">
                            <span class="jtb-info-label">Booking Type</span>
                            <span class="jtb-info-value">
                                <?php echo esc_html( WPYOGFLEET_Admin::booking_type_label( $booking->booking_type ?? 'individual' ) ); ?>
                            </span>
                        </div>
                        <?php if ( ( $booking->booking_type ?? '' ) === 'corporate' ) : ?>
                            <?php if ( ! empty( $booking->company_name ) ) : ?>
                            <div class="jtb-info-item">
                                <span class="jtb-info-label">Company</span>
                                <span class="jtb-info-value">
                                    <a href="<?php echo esc_url( admin_url('admin.php?page=wpyogfleet-companies&action=edit&id='.absint($booking->company_id) )); ?>">
                                        <?php echo esc_html( $booking->company_name ); ?>
                                    </a>
                                </span>
                            </div>
                            <?php endif; ?>
                            <?php if ( ! empty( $booking->booking_ref ) ) : ?>
                            <div class="jtb-info-item">
                                <span class="jtb-info-label">Booking ID / Ref</span>
                                <span class="jtb-info-value"><?php echo esc_html( $booking->booking_ref ); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ( ! empty( $booking->booked_by_name ) ) : ?>
                            <div class="jtb-info-item">
                                <span class="jtb-info-label">Booked By</span>
                                <span class="jtb-info-value">
                                    <?php echo esc_html( $booking->booked_by_name ); ?>
                                    <?php if ( ! empty( $booking->booked_by_mobile ) ) : ?>
                                    <span class="jtb-text-muted"> · <?php echo esc_html( $booking->booked_by_mobile ); ?></span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <?php endif; ?>
                            <?php if ( ! empty( $booking->passenger_name ) ) : ?>
                            <div class="jtb-info-item">
                                <span class="jtb-info-label">Passenger</span>
                                <span class="jtb-info-value">
                                    <?php echo esc_html( $booking->passenger_name ); ?>
                                    <?php if ( ! empty( $booking->passenger_mobile ) ) : ?>
                                        &nbsp;<a href="tel:<?php echo esc_attr( $booking->passenger_mobile ); ?>" style="font-weight:400;color:#6366f1"><?php echo esc_html( $booking->passenger_mobile ); ?></a>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <?php endif; ?>
                        <?php else : ?>
                            <!-- Individual: always show customer name + mobile -->
                            <div class="jtb-info-item">
                                <span class="jtb-info-label">Customer</span>
                                <span class="jtb-info-value">
                                    <?php echo esc_html( $booking->customer_name ); ?>
                                    <a href="tel:<?php echo esc_attr( $booking->customer_mobile ); ?>" style="font-weight:400;color:#6366f1;margin-left:6px"><?php echo esc_html( $booking->customer_mobile ); ?></a>
                                </span>
                            </div>
                        <?php endif; ?>
                        <?php if ( ! empty( $booking->customer_email ) ) : ?>
                        <div class="jtb-info-item">
                            <span class="jtb-info-label">Email</span>
                            <span class="jtb-info-value"><?php echo esc_html( $booking->customer_email ); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="jtb-info-item">
                            <span class="jtb-info-label">Created On</span>
                            <span class="jtb-info-value"><?php echo esc_html( WPYOGFLEET_Admin::format_datetime($booking->created_at) ); ?></span>
                        </div>
                        <div class="jtb-info-item">
                            <span class="jtb-info-label">Advance Paid</span>
                            <span class="jtb-info-value"><?php echo esc_html( WPYOGFLEET_Admin::format_currency($booking->advance_amount) ); ?></span>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- RIGHT: Payments + Expenses -->
        <div class="jtb-detail-col jtb-detail-col--right">

            <!-- Payments -->
            <div class="jtb-card">
                <div class="jtb-card__header">
                    <h2 class="jtb-card__title">Payment History</h2>
                    <button class="jtb-btn jtb-btn-sm jtb-btn-primary" id="jtb-open-payment-modal">
                        <span class="dashicons dashicons-plus-alt2"></span> Add Payment
                    </button>
                </div>
                <div class="jtb-card__body jtb-card__body--flush" id="jtb-payments-area">
                    <?php
                    // Use the same renderer as the AJAX response so void/edit buttons are consistent
                    $wpyogfleet_ajax_obj = new WPYOGFLEET_Ajax();
                    echo $wpyogfleet_ajax_obj->render_payments_table_public( $payments, $booking ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every dynamic value is escaped internally (esc_html()/esc_attr()/absint()) inside render_payments_table() in class-jtb-ajax.php.
                    ?>
                </div>
            </div>

            <!-- Expenses -->
            <div class="jtb-card">
                <div class="jtb-card__header">
                    <h2 class="jtb-card__title">Expenses</h2>
                    <button class="jtb-btn jtb-btn-sm jtb-btn-primary" id="jtb-open-expense-modal">
                        <span class="dashicons dashicons-plus-alt2"></span> Add Expense
                    </button>
                </div>
                <div class="jtb-card__body jtb-card__body--flush" id="jtb-expenses-area">
                    <?php echo $wpyogfleet_ajax_obj->render_expenses_table_public( $expenses ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every dynamic value is escaped internally (esc_html()/esc_attr()/absint()) inside render_expenses_table() in class-jtb-ajax.php. ?>
                </div>
            </div>

            <!-- Send / Share -->
            <div class="jtb-card">
                <div class="jtb-card__header"><h2 class="jtb-card__title">Send / Share</h2></div>
                <div class="jtb-card__body">

                    <p class="jtb-share-hint">Send booking details to customer, driver, or your operations team.</p>

                    <?php
                    // WA SVG inline shorthand
                    $_wa_icon = '<svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>';
                    $_ops_phone = preg_replace('/\D/', '', WPYOGFLEET_Settings::get( WPYOGFLEET_Settings::OPS_PHONE ) );
                    ?>
                    <!-- WhatsApp -->
                    <div class="jtb-share-section">
                        <div class="jtb-share-section__label">📱 WhatsApp</div>
                        <div class="jtb-share-btn-row">
                            <a href="#"
                               class="jtb-btn jtb-btn-wa jtb-wa-link"
                               data-phone="<?php echo esc_attr( preg_replace('/\D/','',$booking->customer_mobile) ); ?>"
                               data-for="customer"
                               target="_blank">
                                <?php echo $_wa_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static hardcoded SVG icon markup defined above, contains no dynamic or user-supplied data. ?>
                                Customer
                            </a>
                            <?php
                            $_driver_mobile = ! empty( $booking->is_outsourced ) ? $booking->outsourced_driver_mobile : $booking->driver_mobile;
                            $_has_driver    = ! empty( $booking->is_outsourced ) ? $booking->outsourced_driver_name : $booking->driver_name;
                            ?>
                            <?php if ( $_has_driver && $_driver_mobile ) : ?>
                            <a href="#"
                               class="jtb-btn jtb-btn-wa jtb-wa-link"
                               data-phone="<?php echo esc_attr( preg_replace('/\D/','',$_driver_mobile) ); ?>"
                               data-for="driver"
                               target="_blank">
                                <?php echo $_wa_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static hardcoded SVG icon markup defined above, contains no dynamic or user-supplied data. ?>
                                Driver
                            </a>
                            <?php endif; ?>
                            <?php if ( $_ops_phone ) : ?>
                            <a href="#"
                               class="jtb-btn jtb-btn-wa jtb-wa-link"
                               data-phone="<?php echo esc_attr( $_ops_phone ); ?>"
                               data-for="ops"
                               target="_blank">
                                <?php echo $_wa_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static hardcoded SVG icon markup defined above, contains no dynamic or user-supplied data. ?>
                                Ops
                            </a>
                            <?php endif; ?>
                            <?php
                            $_review_wa_phone = preg_replace( '/\D/', '', WPYOGFLEET_Admin::review_request_mobile( $booking ) );
                            ?>
                            <?php if ( $_review_wa_phone ) : ?>
                            <a href="#"
                               class="jtb-btn jtb-btn-wa jtb-wa-link"
                               data-phone="<?php echo esc_attr( $_review_wa_phone ); ?>"
                               data-for="review"
                               target="_blank">
                                <?php echo $_wa_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static hardcoded SVG icon markup defined above, contains no dynamic or user-supplied data. ?>
                                ⭐ Google Review
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Email -->
                    <div class="jtb-share-section">
                        <div class="jtb-share-section__label">✉️ Email</div>
                        <div class="jtb-share-btn-row">
                            <?php if ( $booking->customer_email ) : ?>
                            <button class="jtb-btn jtb-btn-secondary jtb-open-email-modal"
                                data-to="<?php echo esc_attr($booking->customer_email); ?>"
                                data-for="customer"
                                data-booking="<?php echo esc_html( $booking->id ); ?>">
                                Customer
                            </button>
                            <?php else : ?>
                            <span class="jtb-share-no-email">No customer email on file</span>
                            <?php endif; ?>
                            <button class="jtb-btn jtb-btn-secondary jtb-open-email-modal"
                                data-to="<?php echo esc_attr(get_option('admin_email')); ?>"
                                data-for="ops"
                                data-booking="<?php echo esc_html( $booking->id ); ?>">
                                Operations
                            </button>
                        </div>
                    </div>

                    <!-- Copy -->
                    <div class="jtb-share-section">
                        <div class="jtb-share-section__label">📋 Copy Text</div>
                        <div class="jtb-share-btn-row">
                            <button class="jtb-btn jtb-btn-ghost jtb-copy-booking" data-for="customer">Customer</button>
                            <button class="jtb-btn jtb-btn-ghost jtb-copy-booking" data-for="driver">Driver</button>
                            <button class="jtb-btn jtb-btn-ghost jtb-copy-booking" data-for="ops">Ops</button>
                            <button class="jtb-btn jtb-btn-ghost jtb-copy-booking" data-for="review">⭐ Google Review</button>
                        </div>
                        <div id="jtb-copy-feedback" style="font-size:12px;color:#059669;margin-top:6px;display:none">✓ Copied to clipboard!</div>
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>

<!-- EMAIL COMPOSE MODAL -->
<?php
// Pre-generate message texts for use in JS
$_msg_customer  = WPYOGFLEET_Admin::booking_message_text( $booking, 'customer' );
$_msg_driver    = WPYOGFLEET_Admin::booking_message_text( $booking, 'driver' );
$_msg_ops       = WPYOGFLEET_Admin::booking_message_text( $booking, 'ops' );
$_html_customer = WPYOGFLEET_Admin::booking_email_html( $booking, 'customer' );
$_html_ops      = WPYOGFLEET_Admin::booking_email_html( $booking, 'ops' );
$_subj_customer = WPYOGFLEET_Admin::booking_email_subject( $booking, 'customer' );
$_subj_ops      = WPYOGFLEET_Admin::booking_email_subject( $booking, 'ops' );
$_msg_review    = WPYOGFLEET_Admin::review_request_message( $booking );
$_review_mobile = preg_replace( '/\D/', '', WPYOGFLEET_Admin::review_request_mobile( $booking ) );
?>
<div class="jtb-modal" id="jtb-email-modal">
    <div class="jtb-modal__overlay"></div>
    <div class="jtb-modal__box jtb-modal__box--wide">
        <div class="jtb-modal__header">
            <h3>Send Email</h3>
            <button class="jtb-modal__close">✕</button>
        </div>
        <div class="jtb-modal__body">
            <div class="jtb-field">
                <label class="jtb-label">To</label>
                <input type="email" id="em-to" class="jtb-input" placeholder="recipient@example.com">
            </div>
            <div class="jtb-field">
                <label class="jtb-label">Subject</label>
                <input type="text" id="em-subject" class="jtb-input">
            </div>
            <div class="jtb-field">
                <label class="jtb-label">Message</label>
                <div class="jtb-rte-toolbar">
                    <button type="button" class="jtb-rte-btn" data-cmd="bold"><b>B</b></button>
                    <button type="button" class="jtb-rte-btn" data-cmd="italic"><i>I</i></button>
                </div>
                <div id="em-body" class="jtb-rte-body" contenteditable="true"></div>
            </div>
            <div id="em-result" style="display:none;margin-top:8px;font-size:13px;padding:8px 12px;border-radius:6px"></div>
        </div>
        <div class="jtb-modal__footer">
            <button class="jtb-btn jtb-btn-ghost jtb-modal__close">Cancel</button>
            <button class="jtb-btn jtb-btn-primary" id="em-send"
                data-booking="<?php echo esc_attr($booking->id); ?>">
                Send Email
            </button>
        </div>
    </div>
</div>

<?php ob_start(); ?>
(function($){
    var DATA = {
        customer: {
            subject: <?php echo wp_json_encode( $_subj_customer ); ?>,
            html:    <?php echo wp_json_encode( $_html_customer ); ?>,
            text:    <?php echo wp_json_encode( $_msg_customer ); ?>,
        },
        driver: {
            text: <?php echo wp_json_encode( $_msg_driver ); ?>,
        },
        ops: {
            subject: <?php echo wp_json_encode( $_subj_ops ); ?>,
            html:    <?php echo wp_json_encode( $_html_ops ); ?>,
            text:    <?php echo wp_json_encode( $_msg_ops ); ?>,
        },
        review: {
            text:   <?php echo wp_json_encode( $_msg_review ); ?>,
            phone:  <?php echo wp_json_encode( $_review_mobile ); ?>,
        },
    };
    window.WPYOGFLEET_REVIEW_DATA = {
        bookingId: <?php echo (int) $booking->id; ?>,
        customerName: <?php echo wp_json_encode( ! empty( $booking->passenger_name ) ? $booking->passenger_name : $booking->customer_name ); ?>,
        mobile: <?php echo wp_json_encode( $_review_mobile ); ?>,
        text: <?php echo wp_json_encode( $_msg_review ); ?>,
        alreadySent: <?php echo ! empty( $booking->review_request_sent ) ? 'true' : 'false'; ?>,
    };

    function textToEditorHtml(text) {
        if (!text) return '';
        var html = '';
        text.split('\n').forEach(function(line) {
            var t = line.trim();
            if (!t) return;
            if (t.charAt(0) === '*' && t.charAt(t.length - 1) === '*') {
                html += '<p><strong>' + t.slice(1, -1) + '</strong></p>';
            } else {
                var ci = t.indexOf(':');
                if (ci > 0 && ci < 25) {
                    html += '<p><strong>' + t.slice(0, ci) + ':</strong>' + t.slice(ci + 1) + '</p>';
                } else {
                    html += '<p>' + t + '</p>';
                }
            }
        });
        return html;
    }

    // ── WhatsApp links ────────────────────────────────────────────
    $(document).on('click', '.jtb-wa-link', function(e){
        e.preventDefault();
        var phone = $(this).data('phone');
        var forW  = $(this).data('for');
        var text  = forW === 'driver' ? DATA.driver.text
                  : forW === 'ops'    ? DATA.ops.text
                  : forW === 'review' ? DATA.review.text
                  : DATA.customer.text;
        // Ensure phone has country code (default 91 for India)
        if (phone && !/^91/.test(phone) && phone.length === 10) phone = '91' + phone;
        var url = 'https://api.whatsapp.com/send/?phone=' + phone
                + '&text=' + encodeURIComponent(text)
                + '&type=phone_number&app_absent=0';
        window.open(url, '_blank');
    });

    // ── Rich text toolbar ─────────────────────────────────────────
    $(document).on('click', '.jtb-rte-btn', function(e){
        e.preventDefault();
        document.execCommand($(this).data('cmd'), false, null);
        $('#em-body').focus();
    });

    // ── Email modal ───────────────────────────────────────────────
    $(document).on('click', '.jtb-open-email-modal', function(){
        var $btn = $(this);
        var forW = $btn.data('for');
        $('#em-to').val($btn.data('to'));
        $('#em-subject').val(forW === 'ops' ? DATA.ops.subject : DATA.customer.subject);
        $('#em-body').html(textToEditorHtml(forW === 'ops' ? DATA.ops.text : DATA.customer.text));
        $('#em-result').hide().text('');
        $('#jtb-email-modal').addClass('is-open');
        $('body').css('overflow','hidden');
        $('#em-to').focus();
    });

    $('#em-send').on('click', function(){
        var $btn = $(this);
        var to   = $.trim($('#em-to').val());
        var subj = $.trim($('#em-subject').val());
        var body = $('#em-body').html();
        if (!to || !subj) { alert('Please fill in To and Subject.'); return; }
        $btn.prop('disabled', true).text('Sending…');
        $.post(WPYOGFLEET.ajax_url, {
            action:     'wpyogfleet_send_booking_email',
            nonce:      WPYOGFLEET.nonce,
            booking_id: $btn.data('booking'),
            to:         to,
            subject:    subj,
            message:    body,
        }, function(res){
            $btn.prop('disabled', false).text('Send Email');
            var $r = $('#em-result');
            if (res.success) {
                $r.css({background:'#d1fae5',color:'#065f46'}).text('✓ Email sent to ' + res.data.to).show();
                setTimeout(function(){ $('#jtb-email-modal').removeClass('is-open'); $('body').css('overflow',''); }, 2000);
            } else {
                $r.css({background:'#fee2e2',color:'#991b1b'}).text((res.data && res.data.message) || 'Send failed.').show();
            }
        }).fail(function(){
            $btn.prop('disabled', false).text('Send Email');
            $('#em-result').css({background:'#fee2e2',color:'#991b1b'}).text('Network error. Please try again.').show();
        });
    });

    // ── Copy to clipboard ─────────────────────────────────────────
    $(document).on('click', '.jtb-copy-booking', function(){
        var forW = $(this).data('for');
        var text = forW === 'driver' ? DATA.driver.text
                 : forW === 'ops'    ? DATA.ops.text
                 : forW === 'review' ? DATA.review.text
                 : DATA.customer.text;
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(function(){
                $('#jtb-copy-feedback').show().delay(2500).fadeOut();
            });
        } else {
            var $ta = $('<textarea>').val(text).appendTo('body').select();
            document.execCommand('copy');
            $ta.remove();
            $('#jtb-copy-feedback').show().delay(2500).fadeOut();
        }
    });

    // ── Void Payment ──────────────────────────────────────────────
    $(document).on('click', '.jtb-void-payment', function(){
        var pid = $(this).data('id');
        var bid = $(this).data('booking');
        $('#void-p-id').val(pid);
        $('#void-p-booking').val(bid);
        $('#void-p-reason').val('');
        $('#jtb-void-payment-modal').addClass('is-open');
    });
    $('#jtb-confirm-void-payment').on('click', function(){
        var reason = $.trim($('#void-p-reason').val());
        if (!reason){ alert('Please enter a reason.'); return; }
        var $btn = $(this).prop('disabled',true).text('Voiding…');
        $.post(WPYOGFLEET.ajax_url, {
            action: 'wpyogfleet_void_payment', nonce: WPYOGFLEET.nonce,
            payment_id: $('#void-p-id').val(), booking_id: $('#void-p-booking').val(), reason: reason
        }, function(res){
            $btn.prop('disabled',false).text('Void Payment');
            $('#jtb-void-payment-modal').removeClass('is-open');
            if (res.success){ $('#jtb-payments-area').html(res.data.payments_html); $('#jtb-summary-area').html(res.data.summary_html); }
            else { alert(res.data.message||'Could not void payment.'); }
        });
    });

    // ── Edit Payment ──────────────────────────────────────────────
    $(document).on('click', '.jtb-edit-payment', function(){
        var $b = $(this);
        $('#edit-p-id').val($b.data('id'));
        $('#edit-p-booking').val($b.data('booking'));
        $('#edit-p-date').val($b.data('date'));
        $('#edit-p-amount').val($b.data('amount'));
        $('#edit-p-mode').val($b.data('mode'));
        $('#edit-p-received').val($b.data('received'));
        $('#edit-p-ref').val($b.data('ref'));
        $('#edit-p-notes').val($b.data('notes'));
        $('#jtb-edit-payment-modal').addClass('is-open');
    });
    $('#jtb-save-edit-payment').on('click', function(){
        var $btn = $(this).prop('disabled',true).text('Saving…');
        $.post(WPYOGFLEET.ajax_url, {
            action: 'wpyogfleet_edit_payment', nonce: WPYOGFLEET.nonce,
            payment_id: $('#edit-p-id').val(), booking_id: $('#edit-p-booking').val(),
            payment_date: $('#edit-p-date').val(), amount: $('#edit-p-amount').val(),
            payment_mode: $('#edit-p-mode').val(), received_by: $('#edit-p-received').val(),
            transaction_ref: $('#edit-p-ref').val(), notes: $('#edit-p-notes').val()
        }, function(res){
            $btn.prop('disabled',false).text('Save Changes');
            $('#jtb-edit-payment-modal').removeClass('is-open');
            if (res.success){ $('#jtb-payments-area').html(res.data.payments_html); $('#jtb-summary-area').html(res.data.summary_html); }
            else { alert(res.data.message||'Could not save changes.'); }
        });
    });

    // ── Void Expense ──────────────────────────────────────────────
    $(document).on('click', '.jtb-void-expense', function(){
        var eid = $(this).data('id');
        var bid = $(this).data('booking');
        $('#void-e-id').val(eid);
        $('#void-e-booking').val(bid);
        $('#void-e-reason').val('');
        $('#jtb-void-expense-modal').addClass('is-open');
    });
    $('#jtb-confirm-void-expense').on('click', function(){
        var reason = $.trim($('#void-e-reason').val());
        if (!reason){ alert('Please enter a reason.'); return; }
        var $btn = $(this).prop('disabled',true).text('Voiding…');
        $.post(WPYOGFLEET.ajax_url, {
            action: 'wpyogfleet_void_expense', nonce: WPYOGFLEET.nonce,
            expense_id: $('#void-e-id').val(), booking_id: $('#void-e-booking').val(), reason: reason
        }, function(res){
            $btn.prop('disabled',false).text('Void Expense');
            $('#jtb-void-expense-modal').removeClass('is-open');
            if (res.success){ $('#jtb-expenses-area').html(res.data.expenses_html); $('#jtb-summary-area').html(res.data.summary_html); }
            else { alert(res.data.message||'Could not void expense.'); }
        });
    });

    // ── Edit Expense ──────────────────────────────────────────────
    $(document).on('click', '.jtb-edit-expense', function(){
        var $b = $(this);
        $('#edit-e-id').val($b.data('id'));
        $('#edit-e-booking').val($b.data('booking'));
        $('#edit-e-date').val($b.data('date'));
        $('#edit-e-type').val($b.data('type'));
        $('#edit-e-amount').val($b.data('amount'));
        $('#edit-e-paidto').val($b.data('paidto'));
        $('#edit-e-paidby').val($b.data('paidby'));
        $('#edit-e-mode').val($b.data('mode'));
        $('#edit-e-notes').val($b.data('notes'));
        $('#jtb-edit-expense-modal').addClass('is-open');
    });
    $('#jtb-save-edit-expense').on('click', function(){
        var $btn = $(this).prop('disabled',true).text('Saving…');
        $.post(WPYOGFLEET.ajax_url, {
            action: 'wpyogfleet_edit_expense', nonce: WPYOGFLEET.nonce,
            expense_id: $('#edit-e-id').val(), booking_id: $('#edit-e-booking').val(),
            expense_date: $('#edit-e-date').val(), expense_type: $('#edit-e-type').val(),
            amount: $('#edit-e-amount').val(), paid_to: $('#edit-e-paidto').val(),
            paid_by: $('#edit-e-paidby').val(), payment_mode: $('#edit-e-mode').val(),
            notes: $('#edit-e-notes').val()
        }, function(res){
            $btn.prop('disabled',false).text('Save Changes');
            $('#jtb-edit-expense-modal').removeClass('is-open');
            if (res.success){ $('#jtb-expenses-area').html(res.data.expenses_html); $('#jtb-summary-area').html(res.data.summary_html); }
            else { alert(res.data.message||'Could not save changes.'); }
        });
    });
})(jQuery);
<?php wp_add_inline_script( 'wpyogfleet-admin', ob_get_clean() ); ?>

<!-- PAYMENT MODAL -->
<div class="jtb-modal" id="jtb-payment-modal">
    <div class="jtb-modal__overlay"></div>
    <div class="jtb-modal__box">
        <div class="jtb-modal__header">
            <h3>Add Payment</h3>
            <button class="jtb-modal__close">✕</button>
        </div>
        <div class="jtb-modal__body">
            <div class="jtb-field-row">
                <div class="jtb-field">
                    <label class="jtb-label">Payment Date *</label>
                    <input type="date" id="p-date" class="jtb-input" value="<?php echo esc_html( gmdate('Y-m-d') ); ?>">
                </div>
                <div class="jtb-field">
                    <label class="jtb-label">Amount (₹) *</label>
                    <input type="number" id="p-amount" class="jtb-input" min="1" step="0.01" placeholder="0.00">
                </div>
            </div>
            <div class="jtb-field-row">
                <div class="jtb-field">
                    <label class="jtb-label">Payment Mode</label>
                    <select id="p-mode" class="jtb-select">
                        <option value="cash">Cash</option>
                        <option value="upi">UPI</option>
                        <option value="bank-transfer">Bank Transfer</option>
                        <option value="card">Card</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="jtb-field">
                    <label class="jtb-label">Received By</label>
                    <input type="text" id="p-received-by" class="jtb-input" placeholder="Name">
                </div>
            </div>
            <div class="jtb-field">
                <label class="jtb-label">Transaction Reference</label>
                <input type="text" id="p-ref" class="jtb-input" placeholder="UPI ID / Transaction ID">
            </div>
            <div class="jtb-field">
                <label class="jtb-label">Notes</label>
                <textarea id="p-notes" class="jtb-textarea" rows="2"></textarea>
            </div>
        </div>
        <div class="jtb-modal__footer">
            <button class="jtb-btn jtb-btn-ghost jtb-modal__close">Cancel</button>
            <button class="jtb-btn jtb-btn-primary" id="jtb-save-payment" data-booking="<?php echo esc_html( $booking->id ); ?>">
                Save Payment
            </button>
        </div>
    </div>
</div>

<!-- EXPENSE MODAL -->
<div class="jtb-modal" id="jtb-expense-modal">
    <div class="jtb-modal__overlay"></div>
    <div class="jtb-modal__box">
        <div class="jtb-modal__header">
            <h3>Add Expense</h3>
            <button class="jtb-modal__close">✕</button>
        </div>
        <div class="jtb-modal__body">
            <div class="jtb-field-row">
                <div class="jtb-field">
                    <label class="jtb-label">Expense Date *</label>
                    <input type="date" id="e-date" class="jtb-input" value="<?php echo esc_html( gmdate('Y-m-d') ); ?>">
                </div>
                <div class="jtb-field">
                    <label class="jtb-label">Expense Type *</label>
                    <select id="e-type" class="jtb-select">
                        <option value="">— Select Type —</option>
                        <option>Driver Allowance</option>
                        <option>Fuel</option>
                        <option>Toll</option>
                        <option>Parking</option>
                        <option>Vehicle Cleaning</option>
                        <option>Commission</option>
                        <option>Outsourced Taxi Payment</option>
                        <option value="other-expense">Other</option>
                    </select>
                </div>
            </div>
            <div class="jtb-field" id="e-type-other-wrap" style="display:none">
                <label class="jtb-label">Specify Expense Type</label>
                <input type="text" id="e-type-other" class="jtb-input" placeholder="Enter type">
            </div>
            <div class="jtb-field-row">
                <div class="jtb-field">
                    <label class="jtb-label">Amount (₹) *</label>
                    <input type="number" id="e-amount" class="jtb-input" min="1" step="0.01" placeholder="0.00">
                </div>
                <div class="jtb-field">
                    <label class="jtb-label">Payment Mode</label>
                    <select id="e-mode" class="jtb-select">
                        <option value="cash">Cash</option>
                        <option value="upi">UPI</option>
                        <option value="bank-transfer">Bank Transfer</option>
                        <option value="card">Card</option>
                        <option value="other">Other</option>
                    </select>
                </div>
            </div>
            <div class="jtb-field-row">
                <div class="jtb-field">
                    <label class="jtb-label">Paid To</label>
                    <input type="text" id="e-paid-to" class="jtb-input" placeholder="Vendor / person name">
                </div>
                <div class="jtb-field">
                    <label class="jtb-label">Paid By</label>
                    <input type="text" id="e-paid-by" class="jtb-input" placeholder="Staff name">
                </div>
            </div>
            <div class="jtb-field">
                <label class="jtb-label">Notes</label>
                <textarea id="e-notes" class="jtb-textarea" rows="2"></textarea>
            </div>
        </div>
        <div class="jtb-modal__footer">
            <button class="jtb-btn jtb-btn-ghost jtb-modal__close">Cancel</button>
            <button class="jtb-btn jtb-btn-primary" id="jtb-save-expense" data-booking="<?php echo esc_html( $booking->id ); ?>">
                Save Expense
            </button>
        </div>
    </div>
</div>

<!-- REVIEW REQUEST MODAL -->
<div class="jtb-modal" id="jtb-review-modal">
    <div class="jtb-modal__overlay"></div>
    <div class="jtb-modal__box">
        <div class="jtb-modal__header"><h3>⭐ Send Review Request?</h3><button class="jtb-modal__close">✕</button></div>
        <div class="jtb-modal__body">
            <p style="color:#6b7280;font-size:13px;margin:0 0 14px">This trip was just marked Completed. Ask the customer for a Google review while the experience is fresh.</p>
            <div class="jtb-info-grid" style="margin-bottom:14px">
                <div class="jtb-info-item">
                    <span class="jtb-info-label">Customer</span>
                    <span class="jtb-info-value" id="jtb-review-cust-name">—</span>
                </div>
                <div class="jtb-info-item">
                    <span class="jtb-info-label">Mobile</span>
                    <span class="jtb-info-value" id="jtb-review-cust-mobile">—</span>
                </div>
            </div>
            <div class="jtb-field">
                <label class="jtb-label">Message</label>
                <textarea id="jtb-review-message" class="jtb-textarea" rows="7"></textarea>
            </div>
            <label style="display:flex;align-items:center;gap:8px;margin-top:12px;font-size:13px;color:#374151;cursor:pointer">
                <input type="checkbox" id="jtb-review-sent-check">
                Review Request Sent
            </label>
        </div>
        <div class="jtb-modal__footer">
            <button class="jtb-btn jtb-btn-ghost jtb-modal__close">Close</button>
            <a href="#" class="jtb-btn jtb-btn-wa" id="jtb-review-wa-btn" target="_blank">📱 Open WhatsApp</a>
        </div>
    </div>
</div>

<!-- VOID PAYMENT MODAL -->
<div class="jtb-modal" id="jtb-void-payment-modal">
    <div class="jtb-modal__overlay"></div>
    <div class="jtb-modal__box">
        <div class="jtb-modal__header"><h3>Void Payment</h3><button class="jtb-modal__close">✕</button></div>
        <div class="jtb-modal__body">
            <p style="color:#6b7280;font-size:13px;margin:0 0 12px">The payment will be marked voided and excluded from totals. This cannot be undone.</p>
            <input type="hidden" id="void-p-id">
            <input type="hidden" id="void-p-booking">
            <div class="jtb-field">
                <label class="jtb-label">Reason <span style="color:#ef4444">*</span></label>
                <textarea id="void-p-reason" class="jtb-textarea" rows="3" placeholder="e.g. Entered by mistake, duplicate entry…"></textarea>
            </div>
        </div>
        <div class="jtb-modal__footer">
            <button class="jtb-btn jtb-btn-ghost jtb-modal__close">Cancel</button>
            <button class="jtb-btn jtb-btn-danger" id="jtb-confirm-void-payment">Void Payment</button>
        </div>
    </div>
</div>

<!-- EDIT PAYMENT MODAL -->
<div class="jtb-modal" id="jtb-edit-payment-modal">
    <div class="jtb-modal__overlay"></div>
    <div class="jtb-modal__box">
        <div class="jtb-modal__header"><h3>Edit Payment</h3><button class="jtb-modal__close">✕</button></div>
        <div class="jtb-modal__body">
            <input type="hidden" id="edit-p-id">
            <input type="hidden" id="edit-p-booking">
            <div class="jtb-field-row">
                <div class="jtb-field"><label class="jtb-label">Date</label><input type="date" id="edit-p-date" class="jtb-input"></div>
                <div class="jtb-field"><label class="jtb-label">Amount (₹)</label><input type="number" id="edit-p-amount" class="jtb-input" min="0.01" step="0.01"></div>
            </div>
            <div class="jtb-field-row">
                <div class="jtb-field"><label class="jtb-label">Mode</label>
                    <select id="edit-p-mode" class="jtb-select">
                        <option value="cash">Cash</option><option value="upi">UPI</option>
                        <option value="bank-transfer">Bank Transfer</option><option value="card">Card</option><option value="other">Other</option>
                    </select>
                </div>
                <div class="jtb-field"><label class="jtb-label">Received By</label><input type="text" id="edit-p-received" class="jtb-input" placeholder="Name"></div>
            </div>
            <div class="jtb-field"><label class="jtb-label">Reference</label><input type="text" id="edit-p-ref" class="jtb-input"></div>
            <div class="jtb-field"><label class="jtb-label">Notes</label><textarea id="edit-p-notes" class="jtb-textarea" rows="2"></textarea></div>
        </div>
        <div class="jtb-modal__footer">
            <button class="jtb-btn jtb-btn-ghost jtb-modal__close">Cancel</button>
            <button class="jtb-btn jtb-btn-primary" id="jtb-save-edit-payment">Save Changes</button>
        </div>
    </div>
</div>

<!-- VOID EXPENSE MODAL -->
<div class="jtb-modal" id="jtb-void-expense-modal">
    <div class="jtb-modal__overlay"></div>
    <div class="jtb-modal__box">
        <div class="jtb-modal__header"><h3>Void Expense</h3><button class="jtb-modal__close">✕</button></div>
        <div class="jtb-modal__body">
            <p style="color:#6b7280;font-size:13px;margin:0 0 12px">The expense will be marked voided and excluded from totals. This cannot be undone.</p>
            <input type="hidden" id="void-e-id">
            <input type="hidden" id="void-e-booking">
            <div class="jtb-field">
                <label class="jtb-label">Reason <span style="color:#ef4444">*</span></label>
                <textarea id="void-e-reason" class="jtb-textarea" rows="3" placeholder="e.g. Entered by mistake…"></textarea>
            </div>
        </div>
        <div class="jtb-modal__footer">
            <button class="jtb-btn jtb-btn-ghost jtb-modal__close">Cancel</button>
            <button class="jtb-btn jtb-btn-danger" id="jtb-confirm-void-expense">Void Expense</button>
        </div>
    </div>
</div>

<!-- EDIT EXPENSE MODAL -->
<div class="jtb-modal" id="jtb-edit-expense-modal">
    <div class="jtb-modal__overlay"></div>
    <div class="jtb-modal__box">
        <div class="jtb-modal__header"><h3>Edit Expense</h3><button class="jtb-modal__close">✕</button></div>
        <div class="jtb-modal__body">
            <input type="hidden" id="edit-e-id">
            <input type="hidden" id="edit-e-booking">
            <div class="jtb-field-row">
                <div class="jtb-field"><label class="jtb-label">Date</label><input type="date" id="edit-e-date" class="jtb-input"></div>
                <div class="jtb-field"><label class="jtb-label">Expense Type</label><input type="text" id="edit-e-type" class="jtb-input" placeholder="e.g. Fuel"></div>
            </div>
            <div class="jtb-field-row">
                <div class="jtb-field"><label class="jtb-label">Amount (₹)</label><input type="number" id="edit-e-amount" class="jtb-input" min="0.01" step="0.01"></div>
                <div class="jtb-field"><label class="jtb-label">Mode</label>
                    <select id="edit-e-mode" class="jtb-select">
                        <option value="cash">Cash</option><option value="upi">UPI</option>
                        <option value="bank-transfer">Bank Transfer</option><option value="card">Card</option><option value="other">Other</option>
                    </select>
                </div>
            </div>
            <div class="jtb-field-row">
                <div class="jtb-field"><label class="jtb-label">Paid To</label><input type="text" id="edit-e-paidto" class="jtb-input"></div>
                <div class="jtb-field"><label class="jtb-label">Paid By</label><input type="text" id="edit-e-paidby" class="jtb-input"></div>
            </div>
            <div class="jtb-field"><label class="jtb-label">Notes</label><textarea id="edit-e-notes" class="jtb-textarea" rows="2"></textarea></div>
        </div>
        <div class="jtb-modal__footer">
            <button class="jtb-btn jtb-btn-ghost jtb-modal__close">Cancel</button>
            <button class="jtb-btn jtb-btn-primary" id="jtb-save-edit-expense">Save Changes</button>
        </div>
    </div>
</div>

<!-- ARCHIVE BOOKING MODAL -->
<?php if ( current_user_can('wpyogfleet_admin') ) : ?>
<div class="jtb-modal" id="jtb-archive-modal">
    <div class="jtb-modal__overlay"></div>
    <div class="jtb-modal__box">
        <div class="jtb-modal__header">
            <h3>Archive Booking</h3>
            <button class="jtb-modal__close">✕</button>
        </div>
        <div class="jtb-modal__body">
            <p style="margin:0 0 12px;color:#6b7280;font-size:13px;">
                This booking will be moved to the archive. All payment and expense records will be preserved.
                The booking will no longer appear in active lists or counts.
            </p>
            <div class="jtb-field">
                <label class="jtb-label">Reason for archiving <span style="color:#ef4444">*</span></label>
                <textarea id="jtb-archive-reason" class="jtb-textarea" rows="3"
                    placeholder="e.g. Customer cancelled, Duplicate entry, Trip completed offline…"></textarea>
            </div>
        </div>
        <div class="jtb-modal__footer">
            <button class="jtb-btn jtb-btn-ghost jtb-modal__close">Cancel</button>
            <button class="jtb-btn jtb-btn-danger" id="jtb-confirm-archive"
                data-booking="<?php echo esc_attr( $booking->id ); ?>"
                data-nonce="<?php echo esc_attr( wp_create_nonce('wpyogfleet_nonce') ); ?>">
                Archive Booking
            </button>
        </div>
    </div>
</div>

<?php ob_start(); ?>
(function($){
    // Open archive modal
    $('#jtb-open-archive-modal').on('click', function(){
        $('#jtb-archive-reason').val('');
        $('#jtb-archive-modal').addClass('is-open');
    });

    // Confirm archive
    $('#jtb-confirm-archive').on('click', function(){
        var reason = $.trim($('#jtb-archive-reason').val());
        if ( ! reason ) {
            alert('Please enter a reason before archiving.');
            $('#jtb-archive-reason').focus();
            return;
        }
        var $btn = $(this).prop('disabled', true).text('Archiving…');
        $.post(ajaxurl, {
            action:     'wpyogfleet_archive_booking',
            booking_id: $(this).data('booking'),
            reason:     reason,
            nonce:      $(this).data('nonce')
        }, function(res){
            if ( res.success ) {
                window.location.href = <?php echo wp_json_encode( admin_url( 'admin.php?page=wpyogfleet-bookings' ) ); ?>;
            } else {
                alert(res.data.message || 'Could not archive booking.');
                $btn.prop('disabled', false).text('Archive Booking');
            }
        }).fail(function(){
            alert('Server error. Please try again.');
            $btn.prop('disabled', false).text('Archive Booking');
        });
    });
})(jQuery);
<?php wp_add_inline_script( 'wpyogfleet-admin', ob_get_clean() ); ?>
<?php endif; ?>
