<?php
// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce is verified by $this->verify() which calls check_ajax_referer() at the start of every public handler
if ( ! defined( 'ABSPATH' ) ) exit;

class WPYOGFLEET_Ajax {

    public function init() {
        $actions = array(
            'wpyogfleet_add_payment',
            'wpyogfleet_delete_payment',
            'wpyogfleet_void_payment',
            'wpyogfleet_edit_payment',
            'wpyogfleet_add_expense',
            'wpyogfleet_delete_expense',
            'wpyogfleet_void_expense',
            'wpyogfleet_edit_expense',
            'wpyogfleet_update_booking_status',
            'wpyogfleet_mark_review_sent',
            'wpyogfleet_archive_booking',
            'wpyogfleet_export_bookings',
            'wpyogfleet_export_pending',
            'wpyogfleet_save_company',
            'wpyogfleet_delete_company',
            'wpyogfleet_get_companies',
            'wpyogfleet_send_booking_email',
            'wpyogfleet_get_calendar_bookings',
        );

        foreach ( $actions as $action ) {
            add_action( 'wp_ajax_' . $action, array( $this, str_replace( 'wpyogfleet_', 'handle_', $action ) ) );
        }
    }

    private function verify() {
        if ( ! check_ajax_referer( 'wpyogfleet_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed.' ), 403 );
        }
        if ( ! current_user_can( 'wpyogfleet_manage' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 );
        }
    }

    // -------------------------------------------------------------------------
    // PAYMENTS
    // -------------------------------------------------------------------------

    public function handle_add_payment() {
        $this->verify();
        $id = WPYOGFLEET_DB::insert_payment( WPYOGFLEET_Input::payment() );
        if ( ! $id ) {
            wp_send_json_error( array( 'message' => 'Could not save payment.' ) );
        }
        $booking = WPYOGFLEET_DB::get_booking( absint( $_POST['booking_id'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
        WPYOGFLEET_DB::log_activity( 'payment_added', 'payment', $id,
            $booking->booking_number ?? 'Booking #' . absint( $_POST['booking_id'] ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
            '₹' . number_format( (float) $_POST['amount'], 2 ) . ' via ' . sanitize_text_field( wp_unslash( $_POST['payment_mode'] ?? 'cash' ) ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
        );
        $payments = WPYOGFLEET_DB::get_payments( absint( $_POST['booking_id'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
        wp_send_json_success( array(
            'payment_id'     => $id,
            'total_paid'     => (float) $booking->total_paid,
            'balance_amount' => (float) $booking->balance_amount,
            'booking_amount' => (float) $booking->booking_amount,
            'payments_html'  => $this->render_payments_table( $payments, $booking ),
            'summary_html'   => $this->render_summary( $booking ),
        ) );
    }

    public function handle_delete_payment() {
        $this->verify();
        if ( ! current_user_can( 'wpyogfleet_admin' ) ) {
            wp_send_json_error( array( 'message' => 'Only admins can delete payments.' ) );
        }
        $payment_id = absint( $_POST['payment_id'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
        $booking_id = absint( $_POST['booking_id'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
        $booking  = WPYOGFLEET_DB::get_booking( $booking_id );
        WPYOGFLEET_DB::log_activity( 'payment_deleted', 'payment', $payment_id,
            $booking->booking_number ?? 'Booking #' . $booking_id, 'Payment deleted' );
        WPYOGFLEET_DB::delete_payment( $payment_id );
        $booking  = WPYOGFLEET_DB::get_booking( $booking_id );
        $payments = WPYOGFLEET_DB::get_payments( $booking_id );
        wp_send_json_success( array(
            'payments_html' => $this->render_payments_table( $payments, $booking ),
            'summary_html'  => $this->render_summary( $booking ),
        ) );
    }

    public function handle_void_payment() {
        $this->verify();
        $payment_id = absint( $_POST['payment_id'] ?? 0 ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
        $booking_id = absint( $_POST['booking_id'] ?? 0 ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
        $reason     = sanitize_textarea_field( wp_unslash( $_POST['reason'] ?? '' ) );
        if ( ! $payment_id || ! $booking_id ) {
            wp_send_json_error( array( 'message' => 'Missing payment or booking ID.' ) );
        }
        if ( ! $reason ) {
            wp_send_json_error( array( 'message' => 'Please provide a reason for voiding this payment.' ) );
        }
        $booking = WPYOGFLEET_DB::get_booking( $booking_id );
        WPYOGFLEET_DB::void_payment( $payment_id, $reason );
        WPYOGFLEET_DB::log_activity( 'payment_voided', 'payment', $payment_id,
            $booking->booking_number ?? 'Booking #' . $booking_id, 'Voided — ' . $reason );
        $booking  = WPYOGFLEET_DB::get_booking( $booking_id );
        $payments = WPYOGFLEET_DB::get_payments( $booking_id );
        wp_send_json_success( array(
            'payments_html' => $this->render_payments_table( $payments, $booking ),
            'summary_html'  => $this->render_summary( $booking ),
        ) );
    }

    public function handle_edit_payment() {
        $this->verify();
        $payment_id = absint( $_POST['payment_id'] ?? 0 ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
        $booking_id = absint( $_POST['booking_id'] ?? 0 ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
        if ( ! $payment_id || ! $booking_id ) {
            wp_send_json_error( array( 'message' => 'Missing payment or booking ID.' ) );
        }
        WPYOGFLEET_DB::edit_payment( $payment_id, WPYOGFLEET_Input::payment() );
        $booking  = WPYOGFLEET_DB::get_booking( $booking_id );
        WPYOGFLEET_DB::log_activity( 'payment_edited', 'payment', $payment_id,
            $booking->booking_number ?? 'Booking #' . $booking_id,
            '₹' . number_format( (float) wp_unslash( $_POST['amount'] ?? 0 ), 2 ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
        );
        $payments = WPYOGFLEET_DB::get_payments( $booking_id );
        wp_send_json_success( array(
            'payments_html' => $this->render_payments_table( $payments, $booking ),
            'summary_html'  => $this->render_summary( $booking ),
        ) );
    }

    // -------------------------------------------------------------------------
    // EXPENSES
    // -------------------------------------------------------------------------

    public function handle_add_expense() {
        $this->verify();
        $id = WPYOGFLEET_DB::insert_expense( WPYOGFLEET_Input::expense() );
        if ( ! $id ) {
            wp_send_json_error( array( 'message' => 'Could not save expense.' ) );
        }
        $booking  = WPYOGFLEET_DB::get_booking( absint( $_POST['booking_id'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
        WPYOGFLEET_DB::log_activity( 'expense_added', 'expense', $id,
            $booking->booking_number ?? 'Booking #' . absint( $_POST['booking_id'] ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
            sanitize_text_field( wp_unslash( $_POST['expense_type'] ?? '' ) ) . ' — ₹' . number_format( (float) $_POST['amount'], 2 ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
        );
        $expenses = WPYOGFLEET_DB::get_expenses( absint( $_POST['booking_id'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
        wp_send_json_success( array(
            'expense_id'      => $id,
            'expenses_html'   => $this->render_expenses_table( $expenses ),
            'summary_html'    => $this->render_summary( $booking ),
        ) );
    }

    public function handle_delete_expense() {
        $this->verify();
        if ( ! current_user_can( 'wpyogfleet_admin' ) ) {
            wp_send_json_error( array( 'message' => 'Only admins can delete expenses.' ) );
        }
        $expense_id = absint( $_POST['expense_id'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
        $booking_id = absint( $_POST['booking_id'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
        $booking  = WPYOGFLEET_DB::get_booking( $booking_id );
        WPYOGFLEET_DB::log_activity( 'expense_deleted', 'expense', $expense_id,
            $booking->booking_number ?? 'Booking #' . $booking_id, 'Expense deleted' );
        WPYOGFLEET_DB::delete_expense( $expense_id );
        $booking  = WPYOGFLEET_DB::get_booking( $booking_id );
        $expenses = WPYOGFLEET_DB::get_expenses( $booking_id );
        wp_send_json_success( array(
            'expenses_html' => $this->render_expenses_table( $expenses ),
            'summary_html'  => $this->render_summary( $booking ),
        ) );
    }

    public function handle_void_expense() {
        $this->verify();
        $expense_id = absint( $_POST['expense_id'] ?? 0 ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
        $booking_id = absint( $_POST['booking_id'] ?? 0 ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
        $reason     = sanitize_textarea_field( wp_unslash( $_POST['reason'] ?? '' ) );
        if ( ! $expense_id || ! $booking_id ) {
            wp_send_json_error( array( 'message' => 'Missing expense or booking ID.' ) );
        }
        if ( ! $reason ) {
            wp_send_json_error( array( 'message' => 'Please provide a reason for voiding this expense.' ) );
        }
        $booking = WPYOGFLEET_DB::get_booking( $booking_id );
        WPYOGFLEET_DB::void_expense( $expense_id, $reason );
        WPYOGFLEET_DB::log_activity( 'expense_voided', 'expense', $expense_id,
            $booking->booking_number ?? 'Booking #' . $booking_id, 'Voided — ' . $reason );
        $booking  = WPYOGFLEET_DB::get_booking( $booking_id );
        $expenses = WPYOGFLEET_DB::get_expenses( $booking_id );
        wp_send_json_success( array(
            'expenses_html' => $this->render_expenses_table( $expenses ),
            'summary_html'  => $this->render_summary( $booking ),
        ) );
    }

    public function handle_edit_expense() {
        $this->verify();
        $expense_id = absint( $_POST['expense_id'] ?? 0 ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
        $booking_id = absint( $_POST['booking_id'] ?? 0 ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
        if ( ! $expense_id || ! $booking_id ) {
            wp_send_json_error( array( 'message' => 'Missing expense or booking ID.' ) );
        }
        WPYOGFLEET_DB::edit_expense( $expense_id, WPYOGFLEET_Input::expense() );
        $booking  = WPYOGFLEET_DB::get_booking( $booking_id );
        WPYOGFLEET_DB::log_activity( 'expense_edited', 'expense', $expense_id,
            $booking->booking_number ?? 'Booking #' . $booking_id,
            sanitize_text_field( wp_unslash( $_POST['expense_type'] ?? '' ) ) . ' — ₹' . number_format( (float) wp_unslash( $_POST['amount'] ?? 0 ), 2 ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
        );
        $expenses = WPYOGFLEET_DB::get_expenses( $booking_id );
        wp_send_json_success( array(
            'expenses_html' => $this->render_expenses_table( $expenses ),
            'summary_html'  => $this->render_summary( $booking ),
        ) );
    }

    // -------------------------------------------------------------------------
    // BOOKING STATUS UPDATE
    // -------------------------------------------------------------------------

    public function handle_update_booking_status() {
        $this->verify();
        $id     = absint( $_POST['booking_id'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
        $status = sanitize_text_field( wp_unslash( $_POST['status'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
        $valid  = array( 'new', 'confirmed', 'ongoing', 'completed', 'closed', 'cancelled' );
        if ( ! in_array( $status, $valid, true ) ) {
            wp_send_json_error( array( 'message' => 'Invalid status.' ) );
        }
        $old_booking = WPYOGFLEET_DB::get_booking( $id );
        $old_status  = $old_booking ? $old_booking->status : '—';
        // "Closed" means the trip is done AND fully paid — block it while a balance is due.
        if ( $status === 'closed' && $old_booking && (float) $old_booking->balance_amount > 0.01 ) {
            wp_send_json_error( array( 'message' => 'Cannot mark as Closed — balance of ₹' . number_format( (float) $old_booking->balance_amount, 2 ) . ' is still due. Settle the payment first.' ) );
        }
        // Use targeted update — never passes through sanitize_booking() which would wipe fields
        WPYOGFLEET_DB::update_status( $id, $status );
        $booking = WPYOGFLEET_DB::get_booking( $id );
        WPYOGFLEET_DB::log_activity( 'status_changed', 'booking', $id,
            $booking->booking_number ?? 'Booking #' . $id,
            $old_status . ' → ' . $status
        );
        wp_send_json_success( array( 'status' => $status, 'old_status' => $old_status, 'booking' => $booking ) );
    }

    public function handle_mark_review_sent() {
        $this->verify();
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

    public function handle_archive_booking() {
        $this->verify();
        if ( ! current_user_can( 'wpyogfleet_admin' ) ) {
            wp_send_json_error( array( 'message' => 'Only admins can archive bookings.' ) );
        }
        $id     = absint( $_POST['booking_id'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
        $reason = sanitize_textarea_field( wp_unslash( $_POST['reason'] ?? '' ) );
        if ( ! $reason ) {
            wp_send_json_error( array( 'message' => 'Please provide a reason for archiving.' ) );
        }
        $booking = WPYOGFLEET_DB::get_booking( $id );
        if ( ! $booking ) {
            wp_send_json_error( array( 'message' => 'Booking not found.' ) );
        }
        WPYOGFLEET_DB::archive_booking( $id, $reason );
        WPYOGFLEET_DB::log_activity( 'booking_archived', 'booking', $id,
            $booking->booking_number ?? 'Booking #' . $id,
            'Archived — Reason: ' . $reason
        );
        wp_send_json_success( array( 'archived' => true ) );
    }

    // -------------------------------------------------------------------------
    // COMPANIES
    // -------------------------------------------------------------------------

    public function handle_save_company() {
        $this->verify();
        $id = absint( $_POST['company_id'] ?? 0 );
        if ( $id ) {
            WPYOGFLEET_DB::update_company( $id, WPYOGFLEET_Input::company() );
            $co = WPYOGFLEET_DB::get_company( $id );
            WPYOGFLEET_DB::log_activity( 'company_updated', 'company', $id,
                $co->company_name ?? 'Company #' . $id, 'Updated via admin AJAX' );
            wp_send_json_success( array( 'id' => $id, 'action' => 'updated', 'company' => $co ) );
        } else {
            $new_id = WPYOGFLEET_DB::insert_company( WPYOGFLEET_Input::company() );
            if ( ! $new_id ) {
                wp_send_json_error( array( 'message' => 'Could not save company.' ) );
            }
            $co = WPYOGFLEET_DB::get_company( $new_id );
            WPYOGFLEET_DB::log_activity( 'company_created', 'company', $new_id,
                $co->company_name ?? 'Company #' . $new_id, 'Created via admin AJAX' );
            wp_send_json_success( array( 'id' => $new_id, 'action' => 'created', 'company' => $co ) );
        }
    }

    public function handle_delete_company() {
        $this->verify();
        if ( ! current_user_can( 'wpyogfleet_admin' ) ) {
            wp_send_json_error( array( 'message' => 'Only admins can delete companies.' ) );
        }
        $id = absint( $_POST['company_id'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
        $co = WPYOGFLEET_DB::get_company( $id );
        WPYOGFLEET_DB::log_activity( 'company_deleted', 'company', $id,
            $co->company_name ?? 'Company #' . $id, 'Deleted via admin AJAX' );
        WPYOGFLEET_DB::delete_company( $id );
        wp_send_json_success( array( 'deleted' => true ) );
    }

    public function handle_get_companies() {
        $this->verify();
        $companies = WPYOGFLEET_DB::get_companies( array( 'status' => 'active' ) );
        wp_send_json_success( $companies );
    }

    // -------------------------------------------------------------------------
    // SEND EMAIL
    // -------------------------------------------------------------------------

    public function handle_send_booking_email() {
        $this->verify();
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
            wp_send_json_error( array( 'message' => 'WordPress could not send the email. Check your mail settings.' ) );
        }
    }

    // -------------------------------------------------------------------------
    // EXPORT
    // -------------------------------------------------------------------------

    public function handle_export_bookings() {
        $this->verify();
        $args = array(
            'date_from' => sanitize_text_field( wp_unslash( $_POST['date_from'] ?? '' ) ),
            'date_to'   => sanitize_text_field( wp_unslash( $_POST['date_to'] ?? '' ) ),
            'status'    => sanitize_text_field( wp_unslash( $_POST['status'] ?? '' ) ),
            'limit'     => 5000,
        );
        $bookings = WPYOGFLEET_DB::get_bookings( $args );

        ob_start();
        $out = fopen( 'php://output', 'w' );
        fputcsv( $out, array( 'Booking #', 'Customer', 'Mobile', 'Pickup', 'Drop', 'Trip Type', 'Start Date', 'End Date', 'Vehicle', 'Driver', 'Booking Amount', 'Advance', 'Total Received', 'Balance', 'Expenses', 'Status' ) );
        foreach ( $bookings as $b ) {
            fputcsv( $out, array(
                $b->booking_number,
                $b->customer_name,
                $b->customer_mobile,
                $b->pickup_location,
                $b->drop_location,
                WPYOGFLEET_Admin::trip_type_label( $b->trip_type ),
                $b->start_date,
                $b->end_date,
                $b->vehicle_name ?? '',
                $b->driver_name ?? '',
                $b->booking_amount,
                $b->advance_amount,
                $b->total_paid,
                $b->balance_amount,
                $b->total_expenses,
                $b->status,
            ) );
        }
        fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        $csv = ob_get_clean();

        wp_send_json_success( array( 'csv' => base64_encode( $csv ), 'filename' => 'bookings-' . gmdate('Y-m-d') . '.csv' ) );
    }

    public function handle_export_pending() {
        $this->verify();
        $rows = WPYOGFLEET_DB::get_pending_payments_report();

        ob_start();
        $out = fopen( 'php://output', 'w' );
        fputcsv( $out, array( 'Booking #', 'Customer', 'Mobile', 'Start Date', 'Booking Amount', 'Total Received', 'Balance Pending' ) );
        foreach ( $rows as $r ) {
            fputcsv( $out, array( $r->booking_number, $r->customer_name, $r->customer_mobile, $r->start_date, $r->booking_amount, $r->total_paid, $r->balance ) );
        }
        fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        $csv = ob_get_clean();

        wp_send_json_success( array( 'csv' => base64_encode( $csv ), 'filename' => 'pending-payments-' . gmdate('Y-m-d') . '.csv' ) );
    }

    // -------------------------------------------------------------------------
    // RENDER HELPERS (HTML fragments returned to JS)
    // -------------------------------------------------------------------------

    /** Public wrapper so booking-detail.php can render the initial table consistently */
    public function render_payments_table_public( $payments, $booking ) {
        return $this->render_payments_table( $payments, $booking );
    }

    /** Public wrapper so booking-detail.php can render the initial table consistently */
    public function render_expenses_table_public( $expenses ) {
        return $this->render_expenses_table( $expenses );
    }

    private function render_payments_table( $payments, $booking ) {
        ob_start();
        if ( empty( $payments ) ) {
            echo '<p class="jtb-empty">No payments recorded yet.</p>';
        } else {
            $can_edit = current_user_can( 'wpyogfleet_manage' );
            $is_admin = current_user_can( 'wpyogfleet_admin' );
            echo '<table class="jtb-table"><thead><tr>
                <th>Date</th><th>Amount</th><th>Mode</th><th>Received By</th><th>Reference</th><th>Notes</th><th></th>
              </tr></thead><tbody>';
            foreach ( $payments as $p ) {
                $voided   = ! empty( $p->is_voided );
                $row_cls  = $voided ? ' class="jtb-voided-row"' : '';
                $amt_wrap = $voided ? '<s>' . esc_html( WPYOGFLEET_Admin::format_currency( $p->amount ) ) . '</s> <span class="jtb-badge-voided">VOIDED</span>' : esc_html( WPYOGFLEET_Admin::format_currency( $p->amount ) );
                // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
                if ( $voided ) {
                    $actions = $is_admin ? '<button class="jtb-btn-icon jtb-delete-payment" data-id="' . absint( $p->id ) . '" data-booking="' . absint( $p->booking_id ) . '" title="Delete"><span class="dashicons dashicons-trash"></span></button>' : '';
                } else {
                    $actions = '';
                    if ( $can_edit ) {
                        $actions .= '<button class="jtb-btn-icon jtb-edit-payment" data-id="' . absint( $p->id ) . '" data-booking="' . absint( $p->booking_id ) . '"'
                            . ' data-date="' . esc_attr( $p->payment_date ) . '"'
                            . ' data-amount="' . esc_attr( $p->amount ) . '"'
                            . ' data-mode="' . esc_attr( $p->payment_mode ) . '"'
                            . ' data-received="' . esc_attr( $p->received_by ) . '"'
                            . ' data-ref="' . esc_attr( $p->transaction_ref ) . '"'
                            . ' data-notes="' . esc_attr( $p->notes ) . '"'
                            . ' title="Edit"><span class="dashicons dashicons-edit"></span></button>';
                        $actions .= '<button class="jtb-btn-icon jtb-void-payment" data-id="' . absint( $p->id ) . '" data-booking="' . absint( $p->booking_id ) . '" title="Void"><span class="dashicons dashicons-dismiss"></span></button>';
                    }
                    if ( $is_admin ) {
                        $actions .= '<button class="jtb-btn-icon jtb-delete-payment" data-id="' . absint( $p->id ) . '" data-booking="' . absint( $p->booking_id ) . '" title="Delete"><span class="dashicons dashicons-trash"></span></button>';
                    }
                }
                echo '<tr' . $row_cls . '>
                    <td>' . esc_html( WPYOGFLEET_Admin::format_date( $p->payment_date ) ) . '</td>
                    <td class="jtb-amount">' . $amt_wrap . '</td>
                    <td>' . esc_html( WPYOGFLEET_Admin::payment_mode_label( $p->payment_mode ) ) . '</td>
                    <td>' . esc_html( $p->received_by ) . '</td>
                    <td>' . esc_html( $p->transaction_ref ) . '</td>
                    <td>' . esc_html( $p->notes ) . ( $voided && ! empty( $p->void_reason ) ? ' <em class="jtb-void-reason">(' . esc_html( $p->void_reason ) . ')</em>' : '' ) . '</td>
                    <td class="jtb-row-actions">' . $actions . '</td>
                  </tr>';
                // phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
            }
            echo '</tbody></table>';
        }
        return ob_get_clean();
    }

    private function render_expenses_table( $expenses ) {
        ob_start();
        if ( empty( $expenses ) ) {
            echo '<p class="jtb-empty">No expenses recorded yet.</p>';
        } else {
            $can_edit = current_user_can( 'wpyogfleet_manage' );
            $is_admin = current_user_can( 'wpyogfleet_admin' );
            echo '<table class="jtb-table"><thead><tr>
                <th>Date</th><th>Type</th><th>Amount</th><th>Paid To</th><th>Paid By</th><th>Mode</th><th>Notes</th><th></th>
              </tr></thead><tbody>';
            foreach ( $expenses as $e ) {
                $voided   = ! empty( $e->is_voided );
                $row_cls  = $voided ? ' class="jtb-voided-row"' : '';
                $amt_wrap = $voided ? '<s>' . esc_html( WPYOGFLEET_Admin::format_currency( $e->amount ) ) . '</s> <span class="jtb-badge-voided">VOIDED</span>' : esc_html( WPYOGFLEET_Admin::format_currency( $e->amount ) );
                // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
                if ( $voided ) {
                    $actions = $is_admin ? '<button class="jtb-btn-icon jtb-delete-expense" data-id="' . absint( $e->id ) . '" data-booking="' . absint( $e->booking_id ) . '" title="Delete"><span class="dashicons dashicons-trash"></span></button>' : '';
                } else {
                    $actions = '';
                    if ( $can_edit ) {
                        $actions .= '<button class="jtb-btn-icon jtb-edit-expense" data-id="' . absint( $e->id ) . '" data-booking="' . absint( $e->booking_id ) . '"'
                            . ' data-date="' . esc_attr( $e->expense_date ) . '"'
                            . ' data-type="' . esc_attr( $e->expense_type ) . '"'
                            . ' data-amount="' . esc_attr( $e->amount ) . '"'
                            . ' data-paidto="' . esc_attr( $e->paid_to ) . '"'
                            . ' data-paidby="' . esc_attr( $e->paid_by ) . '"'
                            . ' data-mode="' . esc_attr( $e->payment_mode ) . '"'
                            . ' data-notes="' . esc_attr( $e->notes ) . '"'
                            . ' title="Edit"><span class="dashicons dashicons-edit"></span></button>';
                        $actions .= '<button class="jtb-btn-icon jtb-void-expense" data-id="' . absint( $e->id ) . '" data-booking="' . absint( $e->booking_id ) . '" title="Void"><span class="dashicons dashicons-dismiss"></span></button>';
                    }
                    if ( $is_admin ) {
                        $actions .= '<button class="jtb-btn-icon jtb-delete-expense" data-id="' . absint( $e->id ) . '" data-booking="' . absint( $e->booking_id ) . '" title="Delete"><span class="dashicons dashicons-trash"></span></button>';
                    }
                }
                echo '<tr' . $row_cls . '>
                    <td>' . esc_html( WPYOGFLEET_Admin::format_date( $e->expense_date ) ) . '</td>
                    <td>' . esc_html( $e->expense_type ) . '</td>
                    <td class="jtb-amount">' . $amt_wrap . '</td>
                    <td>' . esc_html( $e->paid_to ) . '</td>
                    <td>' . esc_html( $e->paid_by ) . '</td>
                    <td>' . esc_html( WPYOGFLEET_Admin::payment_mode_label( $e->payment_mode ) ) . '</td>
                    <td>' . esc_html( $e->notes ) . ( $voided && ! empty( $e->void_reason ) ? ' <em class="jtb-void-reason">(' . esc_html( $e->void_reason ) . ')</em>' : '' ) . '</td>
                    <td class="jtb-row-actions">' . $actions . '</td>
                  </tr>';
                // phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
            }
            echo '</tbody></table>';
        }
        return ob_get_clean();
    }

    private function render_summary( $booking ) {
        $profit = (float)$booking->booking_amount - (float)$booking->total_expenses;
        ob_start(); ?>
        <div class="jtb-summary-grid">
            <div class="jtb-summary-item">
                <span class="label">Booking Amount</span>
                <span class="value"><?php echo esc_html( WPYOGFLEET_Admin::format_currency( $booking->booking_amount ) ); ?></span>
            </div>
            <div class="jtb-summary-item jtb-received">
                <span class="label">Total Received</span>
                <span class="value"><?php echo esc_html( WPYOGFLEET_Admin::format_currency( $booking->total_paid ) ); ?></span>
            </div>
            <div class="jtb-summary-item <?php echo esc_attr( (float)$booking->balance_amount > 0 ? 'jtb-pending' : 'jtb-clear' ); ?>">
                <span class="label">Balance Pending</span>
                <span class="value"><?php echo esc_html( WPYOGFLEET_Admin::format_currency( $booking->balance_amount ) ); ?></span>
            </div>
            <div class="jtb-summary-item jtb-expense">
                <span class="label">Total Expenses</span>
                <span class="value"><?php echo esc_html( WPYOGFLEET_Admin::format_currency( $booking->total_expenses ) ); ?></span>
            </div>
            <div class="jtb-summary-item <?php echo esc_attr( $profit >= 0 ? 'jtb-profit' : 'jtb-loss' ); ?>">
                <span class="label">Gross Profit</span>
                <span class="value"><?php echo esc_html( WPYOGFLEET_Admin::format_currency( $profit ) ); ?></span>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    // -------------------------------------------------------------------------
    // CALENDAR BOOKINGS
    // -------------------------------------------------------------------------

    public function handle_get_calendar_bookings() {
        $this->verify();

        $year  = absint( $_POST['year']  ?? gmdate( 'Y' ) );
        $month = absint( $_POST['month'] ?? gmdate( 'n' ) );

        // Clamp to valid range
        if ( $year < 2000 || $year > 2100 ) $year = (int) gmdate( 'Y' );
        if ( $month < 1 || $month > 12 )   $month = (int) gmdate( 'n' );

        $date_from = sprintf( '%04d-%02d-01', $year, $month );
        $date_to   = gmdate( 'Y-m-t', strtotime( $date_from ) ); // last day of month

        $bookings = WPYOGFLEET_DB::get_bookings( array(
            'date_from' => $date_from,
            'date_to'   => $date_to,
            'limit'     => 500,
        ) );

        $events = array();
        foreach ( $bookings as $b ) {
            // Passenger name: use passenger_name for corporate, customer_name otherwise
            $passenger = ! empty( $b->passenger_name ) ? $b->passenger_name : $b->customer_name;
            $events[]  = array(
                'id'             => (int) $b->id,
                'number'         => $b->booking_number,
                'customer'       => $b->customer_name,
                'passenger_name' => $passenger,
                'company_name'   => $b->company_name ?: '',
                'booking_type'   => $b->booking_type,
                'route'          => ( $b->pickup_location ?: '?' ) . ' → ' . ( $b->drop_location ?: '?' ),
                'date'           => substr( $b->start_date, 0, 10 ),
                'end_date'       => $b->end_date ? substr( $b->end_date, 0, 10 ) : null,
                'status'         => $b->status,
                'vehicle'        => $b->vehicle_name ?: '',
                'reg_number'     => $b->registration_number ?: '',
                'driver'         => $b->driver_name ?: '',
                'driver_mobile'  => $b->driver_mobile ?: '',
                'amount'         => (float) $b->booking_amount,
                'balance'        => (float) $b->balance_amount,
            );
        }

        wp_send_json_success( array( 'events' => $events ) );
    }
}
