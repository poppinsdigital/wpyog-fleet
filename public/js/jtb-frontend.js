/* WPYog Fleet — Frontend Staff Panel JS
   Author: Poppins Digital (poppinsdigital.com) */
(function ($) {
    'use strict';

    var CFG = window.WPYOGFLEETFE || {};
    var AJAX = CFG.ajax_url || '';
    var NONCE = CFG.nonce || '';
    var CAN_DELETE = CFG.can_delete === '1';
    var CAN_EDIT   = CFG.can_edit   === '1';

    // Current state
    var state = {
        view: 'dashboard',
        prevView: null,
        bookings: [],
        currentBookingId: null,
        vehicles: [],
        drivers: [],
        companies: [],
        allVehicles: [],
        allDrivers: [],
        reportTab: 'monthly',
        bookingsPage: 1,
        bookingsTotal: 0,
    };

    /* -------------------------------------------------------- */
    /* INIT                                                      */
    /* -------------------------------------------------------- */

    $(function () {
        loadVehiclesAndDrivers();
        loadCompanies();
        // Deep link: a "?wpyogfleet_booking=123" URL (from the open-in-new-tab icon) opens
        // straight into that booking's detail view instead of the dashboard.
        var urlBookingId = parseInt(new URLSearchParams(window.location.search).get('wpyogfleet_booking'), 10);
        if (urlBookingId) {
            navigate('booking-detail', { bookingId: urlBookingId });
        } else {
            navigate('dashboard');
        }
        bindEvents();
        window._jtbfeRenderReport = renderReport;
    });

    // Shareable URL for a booking's detail view — used by the "open in new tab" icon.
    function bookingDetailUrl(id) {
        var url = new URL(window.location.href);
        url.searchParams.set('wpyogfleet_booking', id);
        return url.toString();
    }

    /* -------------------------------------------------------- */
    /* NAVIGATION                                                */
    /* -------------------------------------------------------- */

    function navigate(view, opts) {
        opts = opts || {};
        state.prevView = state.view;
        state.view = view;

        $('.jtbfe-view').removeClass('is-active');
        $('#jtbfe-view-' + view).addClass('is-active');

        // Update nav
        $('.jtbfe-nav__item').removeClass('is-active');
        $('.jtbfe-nav__item[data-view="' + view + '"]').addClass('is-active');

        // Update title
        var titles = {
            dashboard: 'Dashboard',
            bookings: 'All Bookings',
            'new-booking': opts.editId ? 'Edit Booking' : 'New Booking',
            'booking-detail': 'Booking Detail',
            pending: 'Pending Payments',
            calendar: 'Bookings Calendar',
            vehicles: 'Fleet Vehicles',
            drivers: 'Drivers',
            reports: 'Reports',
        };
        $('#jtbfe-view-title').text(titles[view] || '');

        // Close mobile sidebar
        closeSidebar();

        // Load data
        switch (view) {
            case 'dashboard':
                loadDashboard();
                break;
            case 'bookings':
                setBookingsView('list');
                loadBookings();
                break;
            case 'new-booking':
                setupBookingForm(opts.editId || null);
                break;
            case 'booking-detail':
                if (opts.bookingId) loadBookingDetail(opts.bookingId);
                break;
            case 'pending':
                loadPending();
                break;
            case 'vehicles':
                loadVehicleList();
                break;
            case 'drivers':
                loadDriverList();
                break;
            case 'companies':
                loadCompaniesView();
                break;
            case 'calendar':
                loadCalendar();
                break;
            case 'reports':
                loadReports();
                break;
        }
    }

    /* -------------------------------------------------------- */
    /* EVENTS                                                    */
    /* -------------------------------------------------------- */

    function bindEvents() {
        // Nav links
        $(document).on('click', '.jtbfe-nav__item', function (e) {
            e.preventDefault();
            var view = $(this).data('view');
            if (view) navigate(view);
        });

        // Data-view buttons
        $(document).on('click', '[data-view]', function (e) {
            if ($(this).hasClass('jtbfe-nav__item')) return;
            e.preventDefault();
            var view = $(this).data('view');
            if (view) navigate(view);
        });

        // Mobile sidebar
        $('#jtbfe-menu-btn').on('click', openSidebar);
        $('#jtbfe-sidebar-close, #jtbfe-overlay').on('click', closeSidebar);

        // Back buttons
        $('#jtbfe-form-back, #jtbfe-form-cancel').on('click', function () {
            navigate(state.prevView || 'bookings');
        });
        $('#jtbfe-detail-back').on('click', function () {
            navigate(state.prevView || 'bookings');
        });

        // Search & filter (reset to page 1 on new search/filter)
        var searchTimer;
        $('#jtbfe-search').on('input', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function () { loadBookings(1); }, 350);
        });
        $('#jtbfe-filter-status').on('change', function () { loadBookings(1); });
        $('#jtbfe-sort-select').on('change', function () { loadBookings(1); });

        // Pagination buttons (delegated — rendered dynamically)
        $(document).on('click', '.jtbfe-page-btn', function () {
            loadBookings(parseInt($(this).data('page'), 10));
        });

        // Booking type toggle
        $(document).on('change', '[name="booking_type"]', function () {
            resetChips($('#bf-type-group'), $(this).val());
            switchBookingTypeUI($(this).val());
        });

        // Vehicle source toggle (own fleet vs outsourced)
        $(document).on('change', '[name="vehicle_source"]', function () {
            resetChips($('#bf-source-group'), $(this).val());
            switchVehicleSourceUI($(this).val());
        });

        // Company select → auto-fill booked_by fields
        $(document).on('change', '#bf-company', function () {
            var $opt = $(this).find('option:selected');
            if (!$opt.val()) return;
            var contact = $opt.data('contact') || '';
            var mobile  = $opt.data('mobile')  || '';
            if (!$('#bf-booked-by').val())        $('#bf-booked-by').val(contact);
            if (!$('#bf-booked-by-mobile').val()) $('#bf-booked-by-mobile').val(mobile);
            // Sync hidden customer fields
            $('#bf-corp-name').val($opt.text());
            $('#bf-corp-mobile').val(mobile);
        });

        // Booking form
        $('#jtbfe-booking-form').on('submit', submitBookingForm);

        // Balance calculator
        $('#bf-amount, #bf-advance').on('input', calcBalance);
        $(document).on('input change', '#bf-advance', function () {
            var v = parseFloat($(this).val()) || 0;
            v > 0 ? $('#bf-advance-details').slideDown(150) : $('#bf-advance-details').slideUp(150);
        });

        // Trip type & status chips
        $(document).on('click', '.jtbfe-chip', function () {
            var $group = $(this).closest('.jtbfe-chip-group');
            $group.find('.jtbfe-chip').removeClass('is-selected');
            $(this).addClass('is-selected');
        });

        // Modals
        $(document).on('click', '.jtbfe-modal__backdrop, .jtbfe-modal__close, [data-modal]', function () {
            var modalId = $(this).data('modal') || $(this).closest('.jtbfe-modal').attr('id');
            if (modalId) closeModal(modalId);
        });
        $(document).on('keydown', function (e) {
            if (e.key === 'Escape') closeModal();
        });

        // Add payment button
        $(document).on('click', '#jtbfe-open-payment', function () {
            $('#pfe-date').val(today());
            openModal('jtbfe-payment-modal');
        });
        $('#jtbfe-save-payment').on('click', savePayment);

        // Add expense button
        $(document).on('click', '#jtbfe-open-expense', function () {
            $('#efe-date').val(today());
            openModal('jtbfe-expense-modal');
        });
        $('#jtbfe-save-expense').on('click', saveExpense);

        // Expense type — show "other" field
        $('#efe-type').on('change', function () {
            $('#efe-type-other-wrap').toggle($(this).val() === '__other');
        });

        // Delete payment / expense
        $(document).on('click', '.jtbfe-delete-payment', function () {
            if (!CAN_DELETE) { toast('Only admins can delete payments.', 'error'); return; }
            if (!confirm('Delete this payment?')) return;
            var pid = $(this).data('id'), bid = state.currentBookingId;
            post('wpyogfleet_fe_delete_payment', { payment_id: pid, booking_id: bid }, function (res) {
                if (res.success) {
                    updateDetailPayments(res.data.booking, res.data.payments);
                    toast('Payment deleted.');
                }
            });
        });
        $(document).on('click', '.jtbfe-delete-expense', function () {
            if (!CAN_DELETE) { toast('Only admins can delete expenses.', 'error'); return; }
            if (!confirm('Delete this expense?')) return;
            var eid = $(this).data('id'), bid = state.currentBookingId;
            post('wpyogfleet_fe_delete_expense', { expense_id: eid, booking_id: bid }, function (res) {
                if (res.success) {
                    updateDetailExpenses(res.data.booking, res.data.expenses);
                    toast('Expense deleted.');
                }
            });
        });

        // ── Void payment ──────────────────────────────────────────
        $(document).on('click', '.jtbfe-void-payment', function () {
            var pid = $(this).data('id');
            $('#jtbfe-void-pay-id').val(pid);
            $('#jtbfe-void-pay-reason').val('');
            openModal('jtbfe-void-pay-modal');
        });
        $(document).on('click', '#jtbfe-confirm-void-pay', function () {
            var pid    = $('#jtbfe-void-pay-id').val();
            var reason = $('#jtbfe-void-pay-reason').val();
            if (!reason.trim()) { toast('Please enter a reason for voiding.', 'error'); return; }
            post('wpyogfleet_fe_void_payment', { payment_id: pid, booking_id: state.currentBookingId, reason: reason }, function (res) {
                if (res.success) { updateDetailPayments(res.data.booking, res.data.payments); closeModal('jtbfe-void-pay-modal'); toast('Payment voided.'); }
            });
        });

        // ── Edit payment ──────────────────────────────────────────
        $(document).on('click', '.jtbfe-edit-payment', function () {
            var $b = $(this);
            $('#jtbfe-edit-pay-id').val($b.data('id'));
            $('#jtbfe-epay-date').val(($b.data('date') || '').substring(0, 10));
            $('#jtbfe-epay-amount').val($b.data('amount'));
            $('#jtbfe-epay-mode').val($b.data('mode'));
            $('#jtbfe-epay-received').val($b.data('received'));
            $('#jtbfe-epay-ref').val($b.data('ref'));
            $('#jtbfe-epay-notes').val($b.data('notes'));
            openModal('jtbfe-edit-pay-modal');
        });
        $(document).on('click', '#jtbfe-save-edit-pay', function () {
            var pid = $('#jtbfe-edit-pay-id').val();
            post('wpyogfleet_fe_edit_payment', {
                payment_id: pid, booking_id: state.currentBookingId,
                payment_date: $('#jtbfe-epay-date').val(), amount: $('#jtbfe-epay-amount').val(),
                payment_mode: $('#jtbfe-epay-mode').val(), received_by: $('#jtbfe-epay-received').val(),
                transaction_ref: $('#jtbfe-epay-ref').val(), notes: $('#jtbfe-epay-notes').val(),
            }, function (res) {
                if (res.success) { updateDetailPayments(res.data.booking, res.data.payments); closeModal('jtbfe-edit-pay-modal'); toast('Payment updated.'); }
            });
        });

        // ── Void expense ──────────────────────────────────────────
        $(document).on('click', '.jtbfe-void-expense', function () {
            var eid = $(this).data('id');
            $('#jtbfe-void-exp-id').val(eid);
            $('#jtbfe-void-exp-reason').val('');
            openModal('jtbfe-void-exp-modal');
        });
        $(document).on('click', '#jtbfe-confirm-void-exp', function () {
            var eid    = $('#jtbfe-void-exp-id').val();
            var reason = $('#jtbfe-void-exp-reason').val();
            if (!reason.trim()) { toast('Please enter a reason for voiding.', 'error'); return; }
            post('wpyogfleet_fe_void_expense', { expense_id: eid, booking_id: state.currentBookingId, reason: reason }, function (res) {
                if (res.success) { updateDetailExpenses(res.data.booking, res.data.expenses); closeModal('jtbfe-void-exp-modal'); toast('Expense voided.'); }
            });
        });

        // ── Edit expense ──────────────────────────────────────────
        $(document).on('click', '.jtbfe-edit-expense', function () {
            var $b = $(this);
            $('#jtbfe-edit-exp-id').val($b.data('id'));
            $('#jtbfe-eexp-date').val(($b.data('date') || '').substring(0, 10));
            $('#jtbfe-eexp-type').val($b.data('type'));
            $('#jtbfe-eexp-amount').val($b.data('amount'));
            $('#jtbfe-eexp-paidto').val($b.data('paidto'));
            $('#jtbfe-eexp-paidby').val($b.data('paidby'));
            $('#jtbfe-eexp-mode').val($b.data('mode'));
            $('#jtbfe-eexp-notes').val($b.data('notes'));
            openModal('jtbfe-edit-exp-modal');
        });
        $(document).on('click', '#jtbfe-save-edit-exp', function () {
            var eid = $('#jtbfe-edit-exp-id').val();
            post('wpyogfleet_fe_edit_expense', {
                expense_id: eid, booking_id: state.currentBookingId,
                expense_date: $('#jtbfe-eexp-date').val(), expense_type: $('#jtbfe-eexp-type').val(),
                amount: $('#jtbfe-eexp-amount').val(), paid_to: $('#jtbfe-eexp-paidto').val(),
                paid_by: $('#jtbfe-eexp-paidby').val(), payment_mode: $('#jtbfe-eexp-mode').val(),
                notes: $('#jtbfe-eexp-notes').val(),
            }, function (res) {
                if (res.success) { updateDetailExpenses(res.data.booking, res.data.expenses); closeModal('jtbfe-edit-exp-modal'); toast('Expense updated.'); }
            });
        });

        // Snapshot current value before it changes, to revert if the server rejects it
        // (e.g. "Closed" blocked because a balance is still due).
        $(document).on('mousedown focus', '#jtbfe-status-sel', function () {
            $(this).data('prev-status', $(this).val());
        });

        // Status change in detail
        $(document).on('change', '#jtbfe-status-sel', function () {
            var $sel       = $(this);
            var status     = $sel.val();
            var prevStatus = $sel.data('prev-status') || status;
            post('wpyogfleet_fe_update_status', { booking_id: state.currentBookingId, status: status }, function (res) {
                if (res.success) {
                    $sel.data('prev-status', status);
                    toast('Status updated.');
                    updateDetailSummary(res.data.booking);
                    // Ask for a Google review the first time a trip is marked Completed
                    if (status === 'completed' && res.data.old_status !== 'completed' && res.data.booking) {
                        openReviewPopup(res.data.booking);
                    }
                } else {
                    $sel.val(prevStatus);
                    toast((res.data && res.data.message) || 'Error updating status.', 'error');
                }
            });
        });

        // Review request popup
        $(document).on('input', '#rvfe-message', function () {
            var $btn = $('#rvfe-wa-btn');
            var href = $btn.attr('href');
            if (!href || href === '#') return;
            var base = href.split('&text=')[0];
            $btn.attr('href', base + '&text=' + encodeURIComponent($(this).val()) + '&type=phone_number&app_absent=0');
        });
        $(document).on('change', '#rvfe-sent-check', function () {
            var $chk    = $(this);
            var checked = $chk.is(':checked');
            var bookingId = $('#jtbfe-review-modal').data('booking-id');
            post('wpyogfleet_fe_mark_review_sent', { booking_id: bookingId, sent: checked ? 1 : 0 }, function (res) {
                if (res.success) {
                    toast(checked ? 'Review request marked as sent.' : 'Review request marked as pending.');
                    if (res.data.booking) updateReviewBadge(res.data.booking);
                } else {
                    $chk.prop('checked', !checked);
                    toast((res.data && res.data.message) || 'Could not update.', 'error');
                }
            });
        });

        // Booking item click → detail (same tab)
        $(document).on('click', '.jtbfe-booking-item', function () {
            var id = $(this).data('id');
            if (id) navigate('booking-detail', { bookingId: id });
        });

        // "Open in new tab" icon — let the browser follow the link (new tab) without
        // also triggering the row's same-tab click handler above.
        $(document).on('click', '.jtbfe-open-new-tab', function (e) {
            e.stopPropagation();
        });

        // ── Vehicles ──────────────────────────────────────────────────────────
        $(document).on('click', '#jtbfe-add-vehicle', function () {
            openVehicleModal(null);
        });
        $(document).on('click', '.jtbfe-fleet-card[data-vehicle-id]', function () {
            var id = $(this).data('vehicle-id');
            openVehicleModal(id);
        });
        $('#jtbfe-save-vehicle').on('click', saveVehicle);
        $(document).on('click', '#jtbfe-delete-vehicle', function () {
            var id = $('#vfe-id').val();
            if (!id) return;
            if (!CAN_DELETE) { toast('Only admins can delete vehicles.', 'error'); return; }
            if (!confirm('Delete this vehicle? This cannot be undone.')) return;
            post('wpyogfleet_fe_delete_vehicle', { vehicle_id: id }, function (res) {
                if (res.success) {
                    closeModal('jtbfe-vehicle-modal');
                    toast('Vehicle deleted.');
                    loadVehicleList();
                    loadVehiclesAndDrivers();
                } else { toast(res.data.message || 'Error.', 'error'); }
            });
        });
        $('#vfe-owner').on('change', function () {
            $('#vfe-vendor-wrap').toggle($(this).val() === 'outsourced');
        });

        // ── Drivers ──────────────────────────────────────────────────────────
        $(document).on('click', '#jtbfe-add-driver', function () {
            openDriverModal(null);
        });
        $(document).on('click', '.jtbfe-fleet-card[data-driver-id]', function () {
            var id = $(this).data('driver-id');
            openDriverModal(id);
        });
        $('#jtbfe-save-driver').on('click', saveDriver);
        $(document).on('click', '#jtbfe-delete-driver', function () {
            var id = $('#dfe-id').val();
            if (!id) return;
            if (!CAN_DELETE) { toast('Only admins can delete drivers.', 'error'); return; }
            if (!confirm('Delete this driver?')) return;
            post('wpyogfleet_fe_delete_driver', { driver_id: id }, function (res) {
                if (res.success) {
                    closeModal('jtbfe-driver-modal');
                    toast('Driver deleted.');
                    loadDriverList();
                    loadVehiclesAndDrivers();
                } else { toast(res.data.message || 'Error.', 'error'); }
            });
        });

        // ── Companies ─────────────────────────────────────────────────────────
        $(document).on('click', '#jtbfe-add-company', function () { openCompanyModal(); });
        $(document).on('click', '.jtbfe-fleet-card[data-company-id]', function () { openCompanyModal(parseInt($(this).data('company-id'), 10)); });
        $('#jtbfe-save-company').on('click', saveCompany);
        $(document).on('click', '#jtbfe-delete-company', function () {
            var id = $('#cfe-id').val();
            if (!id) return;
            if (!CAN_DELETE) { toast('Only admins can delete companies.', 'error'); return; }
            if (!confirm('Delete this company? This cannot be undone.')) return;
            post('wpyogfleet_fe_delete_company', { company_id: id }, function (res) {
                if (res.success) {
                    closeModal('jtbfe-company-modal');
                    toast('Company deleted.');
                    loadCompaniesView();
                    loadCompanies();
                } else { toast((res.data && res.data.message) || 'Error.', 'error'); }
            });
        });

        // ── Bookings view toggle ──────────────────────────────────────────────
        $(document).on('click', '#jtbfe-bookings-toggle .jtbfe-view-toggle__btn', function () {
            var view = $(this).data('bview');
            if (view) setBookingsView(view);
        });

        // Dashboard "Full View →" → bookings tab, calendar active
        $(document).on('click', '#jtbfe-dash-cal-full-btn', function () {
            navigate('bookings');
            setTimeout(function () { setBookingsView('calendar'); }, 50);
        });

        // ── Reports ───────────────────────────────────────────────────────────
        // Direct binding on elements (not document delegation) to bypass
        // stopPropagation() from Elementor/theme JS swallowing the click.
        $('#jtbfe-report-tabs .jtbfe-tab').on('click', function () {
            var tab = $(this).data('tab');
            if (!tab) return;
            $('#jtbfe-report-tabs .jtbfe-tab').removeClass('is-active');
            $(this).addClass('is-active');
            state.reportTab = tab;
            if (tab === 'monthly') {
                $('#jtbfe-report-month-ctrl').show();
                $('#jtbfe-report-range-ctrl').addClass('jtbfe-hidden');
            } else {
                $('#jtbfe-report-month-ctrl').hide();
                $('#jtbfe-report-range-ctrl').removeClass('jtbfe-hidden');
            }
            if (tab === 'customer_type') {
                $('#jtbfe-report-type-ctrl').removeClass('jtbfe-hidden');
            } else {
                $('#jtbfe-report-type-ctrl').addClass('jtbfe-hidden');
            }
            loadReportTab(tab);
        });
        $('#jtbfe-report-go').on('click', function () {
            loadReportTab('monthly');
        });
        $('#jtbfe-report-go-range').on('click', function () {
            loadReportTab(state.reportTab);
        });
    }

    /* -------------------------------------------------------- */
    /* SIDEBAR                                                   */
    /* -------------------------------------------------------- */

    function openSidebar() {
        $('#jtbfe-sidebar').addClass('is-open');
        $('#jtbfe-overlay').addClass('is-visible');
    }
    function closeSidebar() {
        $('#jtbfe-sidebar').removeClass('is-open');
        $('#jtbfe-overlay').removeClass('is-visible');
    }

    /* -------------------------------------------------------- */
    /* DASHBOARD                                                 */
    /* -------------------------------------------------------- */

    function loadDashboard() {
        initDashCalendar();
        post('wpyogfleet_fe_get_stats', {}, function (res) {
            if (!res.success) return;
            var s = res.data;
            var html = '<div class="jtbfe-stats">'
                + stat('📅', s.today_bookings, "Today's Bookings", 'blue')
                + stat('🚗', s.ongoing, 'Ongoing Trips', 'amber')
                + stat('⏰', s.upcoming, 'Upcoming', 'indigo')
                + stat('⚠️', s.pending_payments, 'Pending Payments', 'red')
                + '</div>'
                + '<div class="jtbfe-stats" style="grid-template-columns:repeat(3,1fr);margin-top:-10px">'
                + stat('🎫', s.month_bookings, 'Month Bookings', 'blue')
                + stat('💰', fmt(s.month_revenue), 'Revenue Collected', 'green')
                + stat('📈', fmt(s.month_revenue - s.month_expenses), 'Est. Profit', 'green')
                + '</div>';
            $('#jtbfe-stats-area').html(html);

            // Update pending badge
            if (s.pending_payments > 0) {
                $('#jtbfe-pending-badge').text(s.pending_payments).show();
            }
        });

        // Recent bookings (page 1, per_page=25, take first 8)
        post('wpyogfleet_fe_get_bookings', { page: 1 }, function (res) {
            if (!res.success) return;
            var items = (res.data.items || []).slice(0, 8);
            $('#jtbfe-recent-bookings').html(renderBookingCards(items));
        });
    }

    function stat(icon, value, label, color) {
        return '<div class="jtbfe-stat jtbfe-stat--' + color + '">'
            + '<div class="jtbfe-stat__icon">' + icon + '</div>'
            + '<div>'
            + '<div class="jtbfe-stat__val">' + value + '</div>'
            + '<div class="jtbfe-stat__label">' + label + '</div>'
            + '</div></div>';
    }

    /* -------------------------------------------------------- */
    /* BOOKINGS LIST                                             */
    /* -------------------------------------------------------- */

    function loadBookings(page) {
        if (page !== undefined) state.bookingsPage = page;
        $('#jtbfe-bookings-list').html('<div class="jtbfe-loading"><div class="jtbfe-spinner"></div></div>');
        var sortVal = ($('#jtbfe-sort-select').val() || 'date_desc').split('_');
        post('wpyogfleet_fe_get_bookings', {
            search:  $('#jtbfe-search').val(),
            status:  $('#jtbfe-filter-status').val(),
            orderby: sortVal[0],
            order:   sortVal[1],
            page:    state.bookingsPage || 1,
        }, function (res) {
            if (!res.success) return;
            var data  = res.data;
            var items = data.items || [];
            state.bookings      = items;
            state.bookingsTotal = data.total || 0;
            var html = items.length
                ? renderBookingCards(items) + renderFePagination(data)
                : '<div class="jtbfe-empty"><div class="jtbfe-empty__icon">📋</div><p>No bookings found.</p></div>';
            $('#jtbfe-bookings-list').html(html);
        });
    }

    function renderFePagination(data) {
        var total    = data.total || 0;
        var perPage  = data.per_page || 25;
        var page     = data.page || 1;
        var pages    = Math.ceil(total / perPage);
        if (pages <= 1) return '';
        var html = '<div class="jtbfe-pagination">';
        if (page > 1) html += '<button class="jtbfe-btn jtbfe-btn-sm jtbfe-btn-ghost jtbfe-page-btn" data-page="' + (page - 1) + '">← Prev</button>';
        for (var i = 1; i <= pages; i++) {
            html += '<button class="jtbfe-btn jtbfe-btn-sm jtbfe-page-btn ' + (i === page ? 'jtbfe-btn-primary' : 'jtbfe-btn-ghost') + '" data-page="' + i + '">' + i + '</button>';
        }
        if (page < pages) html += '<button class="jtbfe-btn jtbfe-btn-sm jtbfe-btn-ghost jtbfe-page-btn" data-page="' + (page + 1) + '">Next →</button>';
        html += '<span class="jtbfe-pagination__info">' + total + ' total</span>';
        html += '</div>';
        return html;
    }

    function renderBookingCards(items) {
        if (!items || !items.length) return '<div class="jtbfe-empty"><div class="jtbfe-empty__icon">📋</div><p>No bookings yet.</p></div>';
        return items.map(function (b) {
            var bal = b.balance_amount || 0;
            var balClass = bal <= 0 ? 'is-paid' : 'is-pending';
            var balText = bal <= 0 ? '✓ Paid' : 'Due: ' + fmt(bal);
            return '<div class="jtbfe-booking-item" data-id="' + b.id + '">'
                + '<div class="jtbfe-booking-item__main">'
                + '<div class="jtbfe-booking-item__num">' + esc(b.booking_number) + '</div>'
                + '<div class="jtbfe-booking-item__customer">'
                + (b.booking_type === 'corporate' && b.company_name
                    ? '<span class="jtbfe-badge jtbfe-badge--confirmed" style="background:#dbeafe;color:#1d4ed8;font-size:10px;margin-right:4px">Corp</span>' + esc(b.company_name)
                      + (b.passenger_name ? ' <span style="color:#6b7280;font-size:12px">· ' + esc(b.passenger_name) + '</span>' : '')
                    : esc(b.customer_name) + '<span class="jtbfe-booking-item__mobile">' + esc(b.customer_mobile) + '</span>')
                + badge(b.status)
                + (['completed', 'closed'].indexOf(b.status) !== -1
                    ? (parseInt(b.review_request_sent, 10)
                        ? '<span class="jtbfe-badge jtbfe-badge--completed" style="font-size:10px">✅ Review Sent</span>'
                        : '<span class="jtbfe-badge jtbfe-badge--ongoing" style="font-size:10px">⚠️ Review Pending</span>')
                    : '')
                + '</div>'
                + '<div class="jtbfe-booking-item__route">'
                + esc(b.pickup_location || '—') + ' <span>→</span> ' + esc(b.drop_location || '—')
                + '</div>'
                + '<div class="jtbfe-booking-item__meta">'
                + (parseInt(b.is_outsourced, 10)
                    ? (b.outsourced_vehicle_number ? '<span class="jtbfe-booking-item__meta-item">🚗 ' + esc(b.outsourced_vehicle_number) + ' (Outsourced)</span>' : '')
                      + (b.outsourced_driver_name ? '<span class="jtbfe-booking-item__meta-item">👤 ' + esc(b.outsourced_driver_name) + '</span>' : '')
                    : (b.registration_number || b.vehicle_name ? '<span class="jtbfe-booking-item__meta-item">🚗 ' + esc(b.registration_number || b.vehicle_name) + '</span>' : '')
                      + (b.driver_name ? '<span class="jtbfe-booking-item__meta-item">👤 ' + esc(b.driver_name) + '</span>' : ''))
                + '</div>'
                + '</div>'
                + '<div class="jtbfe-booking-item__right">'
                + '<a class="jtbfe-open-new-tab" href="' + esc(bookingDetailUrl(b.id)) + '" target="_blank" rel="noopener" title="Open in new tab" aria-label="Open booking in new tab">'
                + '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" width="16" height="16"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>'
                + '</a>'
                + (b.start_date ? '<div class="jtbfe-booking-item__date">' + fmtDate(b.start_date) + '</div>' : '')
                + '<div class="jtbfe-booking-item__amount">' + fmt(b.booking_amount) + '</div>'
                + '<div class="jtbfe-booking-item__balance ' + balClass + '">' + balText + '</div>'
                + '</div>'
                + '</div>';
        }).join('');
    }

    /* -------------------------------------------------------- */
    /* BOOKING FORM                                              */
    /* -------------------------------------------------------- */

    function setupBookingForm(editId) {
        var $form = $('#jtbfe-booking-form');
        $form[0].reset();
        $('#bf-booking-id').val('');
        $('#bf-balance-val').text('₹0.00');
        resetChips($('#bf-trip-type-group'), 'one-way');
        resetChips($('#bf-status-group'), 'new');
        resetChips($('#bf-type-group'), 'individual');
        switchBookingTypeUI('individual');
        resetChips($('#bf-source-group'), 'fleet');
        switchVehicleSourceUI('fleet');
        populateSelects();

        if (editId) {
            $('#jtbfe-form-heading').text('Edit Booking');
            post('wpyogfleet_fe_get_booking', { id: editId }, function (res) {
                if (!res.success) return;
                var b = res.data.booking;
                $('#bf-booking-id').val(b.id);
                // Booking type
                var btype = b.booking_type || 'individual';
                resetChips($('#bf-type-group'), btype);
                switchBookingTypeUI(btype);
                // Individual fields
                $('#bf-name').val(b.customer_name);
                $('#bf-mobile').val(b.customer_mobile);
                // Corporate fields
                if (b.company_id) $('#bf-company').val(b.company_id);
                $('#bf-booked-by').val(b.booked_by_name || '');
                $('#bf-booked-by-mobile').val(b.booked_by_mobile || '');
                $('#bf-passenger').val(b.passenger_name || '');
                $('#bf-pax-mobile').val(b.passenger_mobile || '');
                $('#bf-booking-ref').val(b.booking_ref || '');
                $('#bf-corp-name').val(b.customer_name || '');
                $('#bf-corp-mobile').val(b.customer_mobile || '');
                // Shared
                $('#bf-email').val(b.customer_email || '');
                $('#bf-from').val(b.pickup_location);
                $('#bf-to').val(b.drop_location);
                $('#bf-amount').val(b.booking_amount);
                $('#bf-advance').val(b.advance_amount);
                if (b.advance_payment_mode) $('#bf-advance-mode').val(b.advance_payment_mode);
                $('#bf-advance-received').val(b.advance_received_by || '');
                $('#bf-notes').val(b.notes);
                // Date + time split
                if (b.start_date) {
                    $('#bf-start-date').val(b.start_date.substring(0, 10));
                    var sTime = b.start_date.substring(11, 16);
                    if (sTime && sTime !== '00:00') $('#bf-start-time').val(sTime);
                }
                if (b.end_date) {
                    $('#bf-end-date').val(b.end_date.substring(0, 10));
                    var eTime = b.end_date.substring(11, 16);
                    if (eTime && eTime !== '00:00') $('#bf-end-time').val(eTime);
                }
                resetChips($('#bf-trip-type-group'), b.trip_type);
                resetChips($('#bf-status-group'), b.status);
                $('#bf-vehicle').val(b.vehicle_id);
                $('#bf-driver').val(b.driver_id);
                // Vehicle source (own fleet vs outsourced)
                var isOut = !!(parseInt(b.is_outsourced, 10));
                resetChips($('#bf-source-group'), isOut ? 'outsourced' : 'fleet');
                switchVehicleSourceUI(isOut ? 'outsourced' : 'fleet');
                $('#bf-out-driver-name').val(b.outsourced_driver_name || '');
                $('#bf-out-driver-mobile').val(b.outsourced_driver_mobile || '');
                $('#bf-out-vehicle-number').val(b.outsourced_vehicle_number || '');
                // Show advance details if advance > 0
                if (parseFloat(b.advance_amount) > 0) $('#bf-advance-details').show();
                calcBalance();
            });
        } else {
            $('#jtbfe-form-heading').text('New Booking');
        }
    }

    function switchBookingTypeUI(type) {
        var isCorp = type === 'corporate';
        $('#bf-individual-fields').toggle(!isCorp);
        $('#bf-corporate-fields').toggle(isCorp);
        // required attributes
        $('#bf-name').prop('required', !isCorp);
        $('#bf-mobile').prop('required', !isCorp);
        $('#bf-company').prop('required', isCorp);
    }

    function switchVehicleSourceUI(source) {
        var isOut = source === 'outsourced';
        $('#bf-fleet-fields').toggle(!isOut);
        $('#bf-outsourced-fields').toggle(isOut);
        if (isOut) {
            // Outsourced vehicle/driver aren't in our database — clear any fleet picks
            $('#bf-vehicle').val('');
            $('#bf-driver').val('');
        } else {
            $('#bf-out-driver-name').val('');
            $('#bf-out-driver-mobile').val('');
            $('#bf-out-vehicle-number').val('');
        }
        $('#bf-out-driver-name').prop('required', isOut);
        $('#bf-out-vehicle-number').prop('required', isOut);
    }

    function populateSelects() {
        var $v = $('#bf-vehicle'), $d = $('#bf-driver'), $c = $('#bf-company');
        $v.find('option:not(:first)').remove();
        $d.find('option:not(:first)').remove();
        $c.find('option:not(:first)').remove();
        state.vehicles.forEach(function (v) {
            $v.append('<option value="' + v.id + '">' + esc(v.vehicle_name + (v.registration_number ? ' (' + v.registration_number + ')' : '')) + '</option>');
        });
        state.drivers.forEach(function (d) {
            $d.append('<option value="' + d.id + '">' + esc(d.driver_name + (d.mobile ? ' (' + d.mobile + ')' : '')) + '</option>');
        });
        state.companies.forEach(function (co) {
            $c.append('<option value="' + co.id + '" data-contact="' + esc(co.contact_name) + '" data-mobile="' + esc(co.contact_mobile) + '">' + esc(co.company_name) + '</option>');
        });
    }

    function submitBookingForm(e) {
        e.preventDefault();
        var $btn   = $('#jtbfe-form-submit');
        var btype  = $('[name="booking_type"]:checked').val() || 'individual';
        var isCorp = btype === 'corporate';

        // Validation
        if (!isCorp && (!$('#bf-name').val().trim() || !$('#bf-mobile').val().trim())) {
            toast('Please fill in customer name and mobile.', 'error');
            return;
        }
        if (isCorp && !$('#bf-company').val()) {
            toast('Please select a company.', 'error');
            return;
        }
        if (!$('#bf-amount').val()) {
            toast('Please enter the booking amount.', 'error');
            return;
        }
        var isOutsourced = ($('[name="vehicle_source"]:checked').val() || 'fleet') === 'outsourced';
        if (isOutsourced && !$('#bf-out-driver-name').val().trim()) {
            toast('Please enter the outsourced driver name.', 'error');
            return;
        }
        if (isOutsourced && !$('#bf-out-vehicle-number').val().trim()) {
            toast('Please enter the outsourced vehicle number.', 'error');
            return;
        }
        // End date must not be before start date
        var startDate = $('#bf-start-date').val();
        var startTime = $('#bf-start-time').val();
        var endDate   = $('#bf-end-date').val();
        var endTime   = $('#bf-end-time').val();
        var startVal  = startDate ? (startDate + (startTime ? 'T' + startTime : 'T00:00')) : '';
        var endVal    = endDate   ? (endDate   + (endTime   ? 'T' + endTime   : 'T00:00')) : '';
        if (startVal && endVal && new Date(endVal) < new Date(startVal)) {
            toast('End date/time cannot be before start date/time.', 'error');
            $('#bf-end-date').focus();
            return;
        }

        // For corporate, sync hidden customer_name / customer_mobile from selected company
        if (isCorp) {
            var $opt = $('#bf-company option:selected');
            if ($opt.val() && !$('#bf-corp-name').val()) {
                $('#bf-corp-name').val($opt.text());
            }
            if ($opt.val() && !$('#bf-corp-mobile').val()) {
                $('#bf-corp-mobile').val($opt.data('mobile') || '');
            }
        }

        $btn.prop('disabled', true).html('<div class="jtbfe-spinner" style="width:16px;height:16px;border-width:2px;display:inline-block;margin-right:4px"></div>Saving…');

        var data = {
            booking_id:      $('#bf-booking-id').val(),
            booking_type:    btype,
            customer_name:   isCorp ? $('#bf-corp-name').val()   : $('#bf-name').val(),
            customer_mobile: isCorp ? $('#bf-corp-mobile').val() : $('#bf-mobile').val(),
            customer_email:       $('#bf-email').val(),
            company_id:           isCorp ? $('#bf-company').val() : '',
            booked_by_name:       isCorp ? $('#bf-booked-by').val() : '',
            booked_by_mobile:     isCorp ? $('#bf-booked-by-mobile').val() : '',
            passenger_name:       isCorp ? $('#bf-passenger').val() : '',
            passenger_mobile:     isCorp ? $('#bf-pax-mobile').val() : '',
            booking_ref:          isCorp ? $('#bf-booking-ref').val() : '',
            pickup_location:      $('#bf-from').val(),
            drop_location:        $('#bf-to').val(),
            trip_type:            $('[name="trip_type"]:checked').val() || 'one-way',
            status:               $('[name="status"]:checked').val() || 'new',
            start_date:           startDate,
            start_time:           startTime,
            end_date:             endDate,
            end_time:             endTime,
            vehicle_id:                isOutsourced ? '' : $('#bf-vehicle').val(),
            driver_id:                 isOutsourced ? '' : $('#bf-driver').val(),
            is_outsourced:             isOutsourced ? 1 : 0,
            outsourced_driver_name:    isOutsourced ? $('#bf-out-driver-name').val()    : '',
            outsourced_driver_mobile:  isOutsourced ? $('#bf-out-driver-mobile').val()  : '',
            outsourced_vehicle_number: isOutsourced ? $('#bf-out-vehicle-number').val() : '',
            booking_amount:       $('#bf-amount').val(),
            advance_amount:       $('#bf-advance').val() || 0,
            advance_payment_mode: $('#bf-advance-mode').val() || 'cash',
            advance_received_by:  $('#bf-advance-received').val(),
            notes:                $('#bf-notes').val(),
        };

        post('wpyogfleet_fe_save_booking', data, function (res) {
            if (res.success) {
                toast(res.data.action === 'created' ? 'Booking created!' : 'Booking updated!');
                navigate('booking-detail', { bookingId: res.data.id });
            } else {
                toast(res.data.message || 'Error saving booking.', 'error');
            }
        }, function () {
            $btn.prop('disabled', false).html('<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16" height="16"><polyline points="20,6 9,17 4,12"/></svg>Save Booking');
        });
    }

    /* -------------------------------------------------------- */
    /* BOOKING DETAIL                                            */
    /* -------------------------------------------------------- */

    function loadBookingDetail(id) {
        state.currentBookingId = id;
        $('#jtbfe-detail-content').html('<div class="jtbfe-loading"><div class="jtbfe-spinner"></div></div>');
        post('wpyogfleet_fe_get_booking', { id: id }, function (res) {
            if (!res.success) return;
            renderDetail(res.data.booking, res.data.payments, res.data.expenses);
        });
    }

    function renderDetail(b, payments, expenses) {
        var bal = parseFloat(b.balance_amount) || 0;
        var profit = parseFloat(b.booking_amount) - parseFloat(b.total_expenses || 0);

        $('#jtbfe-detail-title').text(b.booking_number);

        var html = '';

        // Summary strip
        html += '<div class="jtbfe-detail-summary" id="jtbfe-ds">'
            + ds('Booking Amount', fmt(b.booking_amount), '')
            + ds('Total Received', fmt(b.total_paid || 0), 'is-received')
            + ds('Balance Pending', fmt(bal), bal > 0 ? 'is-pending' : 'is-clear')
            + ds('Expenses', fmt(b.total_expenses || 0), 'is-expense')
            + ds('Gross Profit', fmt(profit), profit >= 0 ? 'is-profit' : 'is-loss')
            + '</div>';

        // Two-col layout
        html += '<div class="jtbfe-detail-grid">';

        // Left: info
        html += '<div>';

        // Status + actions card
        html += '<div class="jtbfe-card">'
            + '<div class="jtbfe-card__header"><h3 class="jtbfe-card__title">Booking</h3>'
            + '<div style="display:flex;gap:8px;align-items:center">'
            + '<select id="jtbfe-status-sel" class="jtbfe-status-select">'
            + ['new','confirmed','ongoing','completed','closed','cancelled'].map(function (s) {
                return '<option value="' + s + '"' + (b.status === s ? ' selected' : '') + '>' + ucfirst(s) + '</option>';
            }).join('')
            + '</select>'
            + '<button class="jtbfe-btn jtbfe-btn-sm jtbfe-btn-ghost" onclick="navigate_to_edit(' + b.id + ')">Edit</button>'
            + '</div></div>'
            + '<div class="jtbfe-card__body"><div class="jtbfe-info-rows">'
            + '<div class="jtbfe-info-row jtbfe-review-badge-row" id="jtbfe-review-badge-row" data-booking="' + b.id + '" style="display:' + (['completed', 'closed'].indexOf(b.status) !== -1 ? '' : 'none') + '">'
            + '<span class="jtbfe-info-row__label">Review Request</span>'
            + '<span class="jtbfe-info-row__value">' + (parseInt(b.review_request_sent, 10)
                ? '<span class="jtbfe-badge jtbfe-badge--completed">✅ Review Sent</span>'
                : '<span class="jtbfe-badge jtbfe-badge--ongoing">⚠️ Review Pending</span>')
            + '</span></div>'
            + row('Type', (b.booking_type === 'corporate' ? '<span class="jtbfe-badge jtbfe-badge--confirmed" style="background:#dbeafe;color:#1d4ed8">Corporate</span>' : 'Individual'))
            + (b.booking_type === 'corporate' && b.company_name ? row('Company', esc(b.company_name)) : '')
            + (b.booking_type === 'corporate' && b.booking_ref ? row('Booking Ref', esc(b.booking_ref)) : '')
            + (b.booking_type === 'corporate' && b.booked_by_name ? row('Booked By', esc(b.booked_by_name) + (b.booked_by_mobile ? ' · ' + esc(b.booked_by_mobile) : '')) : '')
            + (b.booking_type === 'corporate' && b.passenger_name ? row('Passenger', esc(b.passenger_name) + (b.passenger_mobile ? ' · ' + esc(b.passenger_mobile) : '')) : '')
            + (b.booking_type !== 'corporate' ? row('Customer', esc(b.customer_name) + (b.customer_mobile ? ' · <a href="tel:' + esc(b.customer_mobile) + '">' + esc(b.customer_mobile) + '</a>' : '')) : '')
            + (b.customer_email ? row('Email', esc(b.customer_email)) : '')
            + row('Trip Type', ucfirst((b.trip_type || '').replace('-', ' ')))
            + row('From → To', esc(b.pickup_location || '—') + ' → ' + esc(b.drop_location || '—'))
            + row('Start', b.start_date ? fmtDatetime(b.start_date) : '—')
            + row('End', b.end_date ? fmtDatetime(b.end_date) : '—')
            + (parseInt(b.is_outsourced, 10)
                ? row('Vehicle', esc(b.outsourced_vehicle_number || '—') + ' <span class="jtbfe-badge jtbfe-badge--ongoing" style="margin-left:4px">Outsourced</span>')
                  + row('Driver', esc(b.outsourced_driver_name || '—') + (b.outsourced_driver_mobile ? ' · ' + esc(b.outsourced_driver_mobile) : ''))
                : row('Vehicle', (b.registration_number ? '<strong>' + esc(b.registration_number) + '</strong>' + (b.vehicle_name ? ' <span style="color:#94a3b8;font-size:12px">(' + esc(b.vehicle_name) + ')</span>' : '') : esc(b.vehicle_name || '—')))
                  + row('Driver', esc(b.driver_name || '—')))
            + row('Advance Paid', fmt(b.advance_amount))
            + (b.notes ? row('Notes', '<em>' + esc(b.notes) + '</em>') : '')
            + '</div></div></div>';

        html += '</div>'; // end left col

        // Right: payments + expenses
        html += '<div>';

        // Payments
        html += '<div class="jtbfe-card">'
            + '<div class="jtbfe-card__header">'
            + '<h3 class="jtbfe-card__title">Payments</h3>'
            + '<button class="jtbfe-btn jtbfe-btn-sm jtbfe-btn-primary" id="jtbfe-open-payment">+ Add Payment</button>'
            + '</div>'
            + '<div class="jtbfe-card__body p-0" id="jtbfe-detail-payments">'
            + renderPaymentsTable(payments)
            + '</div></div>';

        // Expenses
        html += '<div class="jtbfe-card">'
            + '<div class="jtbfe-card__header">'
            + '<h3 class="jtbfe-card__title">Expenses</h3>'
            + '<button class="jtbfe-btn jtbfe-btn-sm jtbfe-btn-primary" id="jtbfe-open-expense">+ Add Expense</button>'
            + '</div>'
            + '<div class="jtbfe-card__body p-0" id="jtbfe-detail-expenses">'
            + renderExpensesTable(expenses)
            + '</div></div>';

        // Share / Send card
        html += renderShareCard(b);

        html += '</div></div>'; // end right col + grid

        // Email compose overlay (hidden by default)
        html += renderEmailOverlay(b);

        $('#jtbfe-detail-content').html(html);

        // Share event handlers — namespaced so they unbind cleanly on re-render
        $(document).off('click.share')
        .on('click.share', '.jtbfe-copy-detail', function () {
            var text = buildShareText(b, $(this).data('for'));
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(function () { $('#jtbfe-copy-ok').show().delay(2500).fadeOut(); });
            } else {
                var $ta = $('<textarea>').val(text).appendTo('body').select();
                document.execCommand('copy'); $ta.remove();
                $('#jtbfe-copy-ok').show().delay(2500).fadeOut();
            }
        })
        .on('click.share', '.jtbfe-rte-btn', function (e) {
            e.preventDefault();
            document.execCommand($(this).data('cmd'), false, null);
            $('#jtbfe-em-body').focus();
        })
        .on('click.share', '.jtbfe-open-email', function () {
            var forW = $(this).data('for');
            var to   = $(this).data('to') || '';
            var site = (window.WPYOGFLEETFE && WPYOGFLEETFE.site_name) || 'Jagat Travels';
            var subj = forW === 'ops'
                ? '[' + site + '] New Booking: ' + b.booking_number
                : '[' + site + '] Booking Confirmation: ' + b.booking_number;
            $('#jtbfe-em-to').val(to);
            $('#jtbfe-em-subject').val(subj);
            $('#jtbfe-em-body').html(buildEmailBodyHtml(b, forW === 'driver' ? 'driver' : (forW === 'passenger' ? 'passenger' : 'customer')));
            $('#jtbfe-em-result').hide();
            $('#jtbfe-email-overlay').css('display', 'flex');
        })
        .on('click.share', '#jtbfe-email-close, #jtbfe-email-close2', function () {
            $('#jtbfe-email-overlay').hide();
        })
        .on('click.share', '#jtbfe-email-overlay', function (e) {
            if ($(e.target).is('#jtbfe-email-overlay')) $('#jtbfe-email-overlay').hide();
        })
        .on('click.share', '#jtbfe-em-send', function () {
            var $btn    = $(this);
            var to      = $.trim($('#jtbfe-em-to').val());
            var subject = $.trim($('#jtbfe-em-subject').val());
            var message = $('#jtbfe-em-body').html() || buildEmailBodyHtml(b, 'customer');
            if (!to || !subject) { alert('Fill in To and Subject.'); return; }
            $btn.prop('disabled', true).text('Sending…');
            post('wpyogfleet_fe_send_booking_email', {
                booking_id: $btn.data('booking'),
                to: to, subject: subject, message: message,
            }, function (res) {
                $btn.prop('disabled', false).text('Send');
                var $r = $('#jtbfe-em-result');
                if (res.success) {
                    $r.css({ background: '#d1fae5', color: '#065f46' }).text('✓ Sent to ' + res.data.to).show();
                    setTimeout(function () { $('#jtbfe-email-overlay').hide(); }, 2000);
                } else {
                    $r.css({ background: '#fee2e2', color: '#991b1b' }).text((res.data && res.data.message) || 'Send failed.').show();
                }
            });
        });
    }

    /* -------------------------------------------------------- */
    /* SHARE / SEND HELPERS                                      */
    /* -------------------------------------------------------- */

    // Google review request message — mirrors WPYOGFLEET_Admin::review_request_message() in PHP.
    function buildReviewMessage(b) {
        var company = (window.WPYOGFLEETFE && WPYOGFLEETFE.site_name) || 'us';
        var link    = (window.WPYOGFLEETFE && WPYOGFLEETFE.review_link) || '';
        var name    = b.passenger_name || b.customer_name;

        var parts = [
            'Hi ' + name + ',',
            'Thank you for choosing ' + company + '. We hope you had a comfortable and pleasant journey.',
            'If you were happy with our service, we\'d really appreciate it if you could take a minute to share your experience on Google. Your review helps other travellers choose us with confidence.',
        ];
        if (link) parts.push('⭐ Leave a review: ' + link);
        parts.push('Thank you for your support. We look forward to serving you again!');
        parts.push('– Team ' + company);

        return parts.join('\n\n');
    }

    function reviewMobile(b) {
        return b.passenger_mobile || b.customer_mobile || '';
    }

    function openReviewPopup(booking) {
        var name   = booking.passenger_name || booking.customer_name || '';
        var mobile = reviewMobile(booking);
        var text   = buildReviewMessage(booking);

        $('#rvfe-name').text(name || '—');
        $('#rvfe-mobile').text(mobile || '—');
        $('#rvfe-message').val(text);
        $('#rvfe-sent-check').prop('checked', !!parseInt(booking.review_request_sent, 10));

        var phone = (mobile || '').replace(/\D/g, '');
        if (phone && !/^91/.test(phone) && phone.length === 10) phone = '91' + phone;
        $('#rvfe-wa-btn').attr('href', phone
            ? ('https://api.whatsapp.com/send/?phone=' + phone + '&text=' + encodeURIComponent(text) + '&type=phone_number&app_absent=0')
            : '#');

        $('#jtbfe-review-modal').data('booking-id', booking.id);
        openModal('jtbfe-review-modal');
    }

    function updateReviewBadge(booking) {
        var isDone = booking.status === 'completed' || booking.status === 'closed';
        var $row = $('#jtbfe-review-badge-row[data-booking="' + booking.id + '"]');
        if (!$row.length) return;
        if (!isDone) { $row.hide(); return; }
        $row.show();
        var html = parseInt(booking.review_request_sent, 10)
            ? '<span class="jtbfe-badge jtbfe-badge--completed">✅ Review Sent</span>'
            : '<span class="jtbfe-badge jtbfe-badge--ongoing">⚠️ Review Pending</span>';
        $row.find('.jtbfe-info-row__value').html(html);
    }

    function buildShareText(b, forW) {
        if (forW === 'review') return buildReviewMessage(b);

        var site = (window.WPYOGFLEETFE && WPYOGFLEETFE.site_name) || 'Jagat Travels';
        var num  = b.booking_number;

        // Pickup datetime — show time if non-midnight
        var pickupDt = '—';
        if (b.start_date) {
            var ts = new Date(b.start_date.replace(' ', 'T'));
            var h = ts.getHours(), m = ts.getMinutes();
            if (h !== 0 || m !== 0) {
                var ampm = h >= 12 ? 'PM' : 'AM';
                var h12  = h % 12 || 12;
                var mStr = m < 10 ? '0' + m : m;
                pickupDt = fmtDate(b.start_date) + ', ' + h12 + ':' + mStr + ' ' + ampm;
            } else {
                pickupDt = fmtDate(b.start_date);
            }
        }

        var route    = (b.pickup_location || '?') + ' → ' + (b.drop_location || '?');
        var pickupLoc = b.pickup_location || '—';
        var dropLoc   = b.drop_location   || '—';

        // Passenger
        var paxName   = b.passenger_name   || b.customer_name;
        var paxMobile = b.passenger_mobile || b.customer_mobile;
        var paxLine   = paxName + (paxMobile ? ' (' + paxMobile + ')' : '');

        // Driver / Vehicle — outsourced bookings use the manually-typed fields
        var isOutBooking = !!(parseInt(b.is_outsourced, 10));
        var drvLine, vehLine;
        if (isOutBooking) {
            var oDrvName   = b.outsourced_driver_name ? b.outsourced_driver_name.toUpperCase() : 'TO BE ASSIGNED';
            var oDrvMobile = b.outsourced_driver_mobile || '';
            drvLine = oDrvName + (oDrvMobile ? ' (' + oDrvMobile + ')' : '') + ' [Outsourced]';
            vehLine = (b.outsourced_vehicle_number || '—') + ' [Outsourced]';
        } else {
            // Driver — uppercase
            var drvName   = b.driver_name ? b.driver_name.toUpperCase() : 'TO BE ASSIGNED';
            var drvMobile = b.driver_mobile || '';
            drvLine   = drvName + (drvMobile ? ' (' + drvMobile + ')' : '');

            // Vehicle — reg primary
            var vehName = b.vehicle_name || '—';
            var vehReg  = b.registration_number || '';
            vehLine = vehReg ? vehName + ' (' + vehReg + ')' : vehName;
        }

        var statusMap = {new:'New',confirmed:'Confirmed',ongoing:'Ongoing',completed:'Completed',closed:'Closed',cancelled:'Cancelled'};
        var statusStr = statusMap[b.status] || ucfirst(b.status);

        var amt = fmt(b.booking_amount);
        var paid = fmt(b.total_paid);
        var due  = fmt(b.balance_amount > 0 ? b.balance_amount : 0);

        var lines;
        if (forW === 'driver') {
            lines = [
                '*Booking Details*',
                'Booking #' + num,
                'Passenger: ' + paxLine,
                'Driver: '    + drvLine,
                'Vehicle: '   + vehLine,
                'Route: '     + route,
                'Pickup Datetime: ' + pickupDt,
                'Pickup Location: ' + pickupLoc,
                'Drop Location: '   + dropLoc,
            ];
            if (b.notes) lines.push('Instructions: ' + b.notes);
            lines.push('Status: ' + statusStr);
            lines.push('-- Sent by ' + site);

        } else if (forW === 'ops') {
            lines = ['*Booking Details*', 'Booking #' + num];
            if (b.company_name) lines.push('Corporate: ' + b.company_name);
            if (b.booking_ref)  lines.push('Booking ID (Ref): ' + b.booking_ref);
            lines.push(
                'Passenger: ' + paxLine,
                'Driver: '    + drvLine,
                'Vehicle: '   + vehLine,
                'Route: '     + route,
                'Pickup Datetime: ' + pickupDt,
                'Pickup Location: ' + pickupLoc,
                'Drop Location: '   + dropLoc
            );
            if (b.notes) lines.push('Instructions: ' + b.notes);
            lines.push('Status: ' + statusStr);
            lines.push('Total: ' + amt + ' | Paid: ' + paid + ' | Balance: ' + due);
            lines.push('Thank you for choosing ' + site + '.');

        } else {
            // customer (default)
            lines = [
                '*Booking Details*',
                'Booking #' + num,
                'Passenger: ' + paxLine,
                'Driver: '    + drvLine,
                'Vehicle: '   + vehLine,
                'Route: '     + route,
                'Pickup Datetime: ' + pickupDt,
                'Pickup Location: ' + pickupLoc,
                'Drop Location: '   + dropLoc,
            ];
            if (b.notes) lines.push('Instructions: ' + b.notes);
            lines.push('Status: ' + statusStr);
            lines.push('Thank you for choosing ' + site + '.');
        }
        return lines.join('\n');
    }

    function buildEmailBodyHtml(b, forW) {
        var site  = (window.WPYOGFLEETFE && WPYOGFLEETFE.site_name) || 'Jagat Travels';
        var date  = b.start_date ? fmtDatetime(b.start_date) : '—';
        var route = esc(b.pickup_location || '?') + ' → ' + esc(b.drop_location || '?');
        var amt   = fmt(b.booking_amount);
        var paid  = fmt(b.total_paid);
        var due   = fmt(b.balance_amount > 0 ? b.balance_amount : 0);
        var paxName   = b.passenger_name   || b.customer_name;
        var paxMobile = b.passenger_mobile || b.customer_mobile;
        var isOutEmail = !!(parseInt(b.is_outsourced, 10));
        var vehDisplay = isOutEmail ? (esc(b.outsourced_vehicle_number || '—') + ' (Outsourced)') : esc(b.vehicle_name || '—');
        var drvDisplay = isOutEmail
            ? esc(b.outsourced_driver_name || 'To be assigned') + (b.outsourced_driver_mobile ? ' | ' + b.outsourced_driver_mobile : '') + ' (Outsourced)'
            : esc(b.driver_name || 'To be assigned') + (b.driver_mobile ? ' | ' + b.driver_mobile : '');
        var title, rows, closing;
        if (forW === 'driver') {
            title   = 'Trip Assignment — ' + esc(site);
            rows    = [
                ['Booking #', b.booking_number],
                ['Date',      date],
                ['Route',     route],
                ['Passenger', esc(paxName) + (paxMobile ? ' | ' + paxMobile : '')],
                ['Vehicle',   vehDisplay],
                ['Amount',    amt + ' | Advance: ' + paid + ' | Due: ' + due],
            ];
            closing = 'Please confirm your availability. Thank you!';
        } else if (forW === 'passenger') {
            title   = 'Trip Confirmation — ' + esc(site);
            rows    = [
                ['Booking #', b.booking_number],
                ['Date',      date],
                ['Route',     route],
                ['Vehicle',   vehDisplay],
                ['Driver',    drvDisplay],
            ];
            closing = 'Have a safe journey!';
        } else {
            title   = 'Booking Confirmation — ' + esc(site);
            rows    = [
                ['Booking #', b.booking_number],
                ['Date',      date],
                ['Route',     route],
                ['Vehicle',   vehDisplay],
                ['Driver',    drvDisplay],
                ['Total',     amt + ' | Paid: ' + paid + ' | Balance: ' + due],
                ['Status',    ucfirst(b.status)],
            ];
            if (b.passenger_name) {
                rows.push(['Passenger', esc(b.passenger_name) + (b.passenger_mobile ? ' | ' + b.passenger_mobile : '')]);
            }
            closing = 'Thank you for choosing ' + esc(site) + '!';
        }
        var html = '<p><strong>' + title + '</strong></p>';
        rows.forEach(function(r) { html += '<p><strong>' + r[0] + ':</strong> ' + r[1] + '</p>'; });
        html += '<p>' + closing + '</p><p><em>— ' + esc(site) + '</em></p>';
        return html;
    }

    function waLink(phone, text) {
        var p = (phone || '').replace(/\D/g, '');
        if (p.length === 10) p = '91' + p;
        return 'https://api.whatsapp.com/send/?phone=' + p + '&text=' + encodeURIComponent(text) + '&type=phone_number&app_absent=0';
    }

    function renderShareCard(b) {
        var waIcon = '<svg viewBox="0 0 24 24" width="13" height="13" fill="currentColor" style="vertical-align:-2px"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>';
        var isCorp   = b.booking_type === 'corporate';
        var hasPax   = isCorp && b.passenger_name;
        var custTxt  = buildShareText(b, 'customer');
        var drvTxt   = buildShareText(b, 'driver');
        var opsTxt   = buildShareText(b, 'ops');
        // Passenger text = customer message (same content, sent to passenger's number)
        var paxTxt   = custTxt;
        var custWa   = waLink(b.customer_mobile, custTxt);
        var drvPhone = parseInt(b.is_outsourced, 10) ? b.outsourced_driver_mobile : b.driver_mobile;
        var drvWa    = drvPhone ? waLink(drvPhone, drvTxt) : '';
        var paxWa    = (hasPax && b.passenger_mobile) ? waLink(b.passenger_mobile, paxTxt) : '';
        var opsPhone = (window.WPYOGFLEETFE && WPYOGFLEETFE.ops_phone) || '';
        var opsWa    = opsPhone ? waLink(opsPhone, opsTxt) : '';
        var reviewTxt   = buildShareText(b, 'review');
        var reviewPhone = reviewMobile(b);
        var reviewWa    = reviewPhone ? waLink(reviewPhone, reviewTxt) : '';

        var driverBtn = drvWa
            ? '<a class="jtbfe-btn jtbfe-btn-wa" href="' + drvWa + '" target="_blank">' + waIcon + ' Driver</a>'
            : '';
        var paxWaBtn  = paxWa
            ? '<a class="jtbfe-btn jtbfe-btn-wa" href="' + paxWa + '" target="_blank">' + waIcon + ' Passenger</a>'
            : '';
        var opsWaBtn  = opsWa
            ? '<a class="jtbfe-btn jtbfe-btn-wa" href="' + opsWa + '" target="_blank">' + waIcon + ' Operations</a>'
            : '';
        var reviewWaBtn = reviewWa
            ? '<a class="jtbfe-btn jtbfe-btn-wa" href="' + reviewWa + '" target="_blank">' + waIcon + ' ⭐ Google Review</a>'
            : '';

        var emailBtn = b.customer_email
            ? '<button class="jtbfe-btn jtbfe-btn-secondary jtbfe-open-email" data-for="customer" data-to="' + esc(b.customer_email) + '" data-booking="' + b.id + '">Email Customer</button>'
            : '';
        // Passenger email — same body as customer, blank To so staff can enter
        var paxEmailBtn = hasPax
            ? '<button class="jtbfe-btn jtbfe-btn-secondary jtbfe-open-email" data-for="passenger" data-to="" data-booking="' + b.id + '">Email Passenger</button>'
            : '';
        var opsEmail = (window.WPYOGFLEETFE && WPYOGFLEETFE.ops_email) || '';
        var opsEmailBtn = '<button class="jtbfe-btn jtbfe-btn-secondary jtbfe-open-email" data-for="ops" data-to="' + esc(opsEmail) + '" data-booking="' + b.id + '">Email Ops</button>';

        var paxCopyBtn = hasPax
            ? '<button class="jtbfe-btn jtbfe-btn-ghost jtbfe-copy-detail" data-for="passenger">Passenger Text</button>'
            : '';

        return '<div class="jtbfe-card">'
            + '<div class="jtbfe-card__header"><h3 class="jtbfe-card__title">Send / Share</h3></div>'
            + '<div class="jtbfe-card__body">'
            + '<div class="jtbfe-share-section">'
            + '<div class="jtbfe-share-label">📱 WhatsApp</div>'
            + '<div class="jtbfe-share-row">'
            + '<a class="jtbfe-btn jtbfe-btn-wa" href="' + custWa + '" target="_blank">' + waIcon + ' Customer</a>'
            + driverBtn
            + paxWaBtn
            + opsWaBtn
            + reviewWaBtn
            + '</div></div>'
            + '<div class="jtbfe-share-section">'
            + '<div class="jtbfe-share-label">✉️ Email</div>'
            + '<div class="jtbfe-share-row">'
            + (emailBtn || '<span class="jtbfe-share-muted">No customer email</span>')
            + paxEmailBtn
            + opsEmailBtn
            + '</div></div>'
            + '<div class="jtbfe-share-section">'
            + '<div class="jtbfe-share-label">📋 Copy</div>'
            + '<div class="jtbfe-share-row">'
            + '<button class="jtbfe-btn jtbfe-btn-ghost jtbfe-copy-detail" data-for="customer">Customer Text</button>'
            + '<button class="jtbfe-btn jtbfe-btn-ghost jtbfe-copy-detail" data-for="driver">Driver Text</button>'
            + paxCopyBtn
            + '<button class="jtbfe-btn jtbfe-btn-ghost jtbfe-copy-detail" data-for="ops">Ops Text</button>'
            + '<button class="jtbfe-btn jtbfe-btn-ghost jtbfe-copy-detail" data-for="review">⭐ Google Review</button>'
            + '</div>'
            + '<div id="jtbfe-copy-ok" style="display:none;font-size:12px;color:#059669;margin-top:6px">✓ Copied!</div>'
            + '</div>'
            + '</div></div>';
    }

    function renderEmailOverlay(b) {
        return '<div id="jtbfe-email-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center">'
            + '<div style="background:#fff;border-radius:12px;width:min(620px,96vw);max-height:90vh;overflow-y:auto;display:flex;flex-direction:column;box-shadow:0 20px 60px rgba(0,0,0,.25)">'
            + '<div style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid #e5e7eb">'
            + '<strong style="font-size:15px">Send Email</strong>'
            + '<button id="jtbfe-email-close" style="background:none;border:none;font-size:18px;cursor:pointer;color:#6b7280;line-height:1">&#x2715;</button>'
            + '</div>'
            + '<div style="padding:20px;flex:1">'
            + '<div class="jtbfe-field"><label>To</label><input id="jtbfe-em-to" type="email" class="jtbfe-input" placeholder="recipient@example.com"></div>'
            + '<div class="jtbfe-field"><label>Subject</label><input id="jtbfe-em-subject" type="text" class="jtbfe-input"></div>'
            + '<div class="jtbfe-field"><label>Message</label>'
            + '<div class="jtbfe-rte-toolbar">'
            + '<button type="button" class="jtbfe-rte-btn" data-cmd="bold"><b>B</b></button>'
            + '<button type="button" class="jtbfe-rte-btn" data-cmd="italic"><i>I</i></button>'
            + '</div>'
            + '<div id="jtbfe-em-body" class="jtbfe-rte-body" contenteditable="true"></div>'
            + '</div>'
            + '<div id="jtbfe-em-result" style="display:none;margin-top:8px;font-size:13px;padding:8px 12px;border-radius:6px"></div>'
            + '</div>'
            + '<div style="display:flex;justify-content:flex-end;gap:10px;padding:14px 20px;border-top:1px solid #e5e7eb">'
            + '<button class="jtbfe-btn jtbfe-btn-ghost" id="jtbfe-email-close2">Cancel</button>'
            + '<button class="jtbfe-btn jtbfe-btn-primary" id="jtbfe-em-send" data-booking="' + b.id + '">Send</button>'
            + '</div>'
            + '</div>'
            + '</div>';
    }

    function ds(label, value, cls) {
        return '<div class="jtbfe-ds-item ' + cls + '">'
            + '<div class="jtbfe-ds-item__label">' + label + '</div>'
            + '<div class="jtbfe-ds-item__value">' + value + '</div>'
            + '</div>';
    }

    function row(label, value) {
        return '<div class="jtbfe-info-row">'
            + '<span class="jtbfe-info-row__label">' + label + '</span>'
            + '<span class="jtbfe-info-row__value">' + value + '</span>'
            + '</div>';
    }

    function renderPaymentsTable(payments) {
        if (!payments || !payments.length) return '<p style="padding:16px;color:#94a3b8;font-size:13px;font-style:italic">No payments yet.</p>';
        var actCol = (CAN_EDIT || CAN_DELETE) ? '<th></th>' : '';
        var html = '<table class="jtbfe-txn-table"><thead><tr><th>Date</th><th>Amount</th><th>Mode</th><th>Received By</th><th>Ref</th><th>Notes</th>' + actCol + '</tr></thead><tbody>';
        payments.forEach(function (p) {
            var isVoided = parseInt(p.is_voided) === 1;
            var amtHtml  = isVoided
                ? '<span style="text-decoration:line-through;color:#94a3b8">' + fmt(p.amount) + '</span> <span style="font-size:10px;background:#fee2e2;color:#dc2626;padding:1px 5px;border-radius:3px">VOID</span>'
                : '<span style="color:#059669">' + fmt(p.amount) + '</span>';
            var notesHtml = esc(p.notes || '');
            if (isVoided && p.void_reason) notesHtml += (notesHtml ? ' · ' : '') + '<em style="color:#dc2626">Void: ' + esc(p.void_reason) + '</em>';
            var actBtns = '';
            if ((CAN_EDIT || CAN_DELETE) && !isVoided) {
                actBtns = '<td style="white-space:nowrap">';
                if (CAN_EDIT) {
                    actBtns += '<button class="jtbfe-del-btn jtbfe-edit-payment" style="margin-right:4px" data-id="' + p.id + '" data-date="' + esc(p.payment_date) + '" data-amount="' + p.amount + '" data-mode="' + esc(p.payment_mode) + '" data-received="' + esc(p.received_by||'') + '" data-ref="' + esc(p.transaction_ref||'') + '" data-notes="' + esc(p.notes||'') + '" title="Edit">✏️</button>'
                        + '<button class="jtbfe-del-btn jtbfe-void-payment" style="margin-right:4px;color:#f59e0b" data-id="' + p.id + '" title="Void">⊘</button>';
                }
                if (CAN_DELETE) {
                    actBtns += '<button class="jtbfe-del-btn jtbfe-delete-payment" data-id="' + p.id + '" title="Delete">🗑</button>';
                }
                actBtns += '</td>';
            } else if (CAN_EDIT || CAN_DELETE) {
                actBtns = '<td></td>';
            }
            html += '<tr' + (isVoided ? ' style="opacity:.6"' : '') + '>'
                + '<td>' + fmtDate(p.payment_date) + '</td>'
                + '<td class="jtbfe-amount">' + amtHtml + '</td>'
                + '<td>' + ucfirst(p.payment_mode) + '</td>'
                + '<td>' + esc(p.received_by || '—') + '</td>'
                + '<td style="color:#94a3b8;font-size:11px">' + esc(p.transaction_ref || '—') + '</td>'
                + '<td style="color:#6b7280;font-size:11px">' + (notesHtml || '—') + '</td>'
                + actBtns + '</tr>';
        });
        return html + '</tbody></table>';
    }

    function renderExpensesTable(expenses) {
        if (!expenses || !expenses.length) return '<p style="padding:16px;color:#94a3b8;font-size:13px;font-style:italic">No expenses yet.</p>';
        var actCol = (CAN_EDIT || CAN_DELETE) ? '<th></th>' : '';
        var html = '<table class="jtbfe-txn-table"><thead><tr><th>Date</th><th>Type</th><th>Amount</th><th>Paid To</th><th>By</th><th>Mode</th><th>Notes</th>' + actCol + '</tr></thead><tbody>';
        expenses.forEach(function (e) {
            var isVoided = parseInt(e.is_voided) === 1;
            var amtHtml  = isVoided
                ? '<span style="text-decoration:line-through;color:#94a3b8">' + fmt(e.amount) + '</span> <span style="font-size:10px;background:#fee2e2;color:#dc2626;padding:1px 5px;border-radius:3px">VOID</span>'
                : '<span style="color:#f59e0b">' + fmt(e.amount) + '</span>';
            var notesHtml = esc(e.notes || '');
            if (isVoided && e.void_reason) notesHtml += (notesHtml ? ' · ' : '') + '<em style="color:#dc2626">Void: ' + esc(e.void_reason) + '</em>';
            var actBtns = '';
            if ((CAN_EDIT || CAN_DELETE) && !isVoided) {
                actBtns = '<td style="white-space:nowrap">';
                if (CAN_EDIT) {
                    actBtns += '<button class="jtbfe-del-btn jtbfe-edit-expense" style="margin-right:4px" data-id="' + e.id + '" data-date="' + esc(e.expense_date) + '" data-type="' + esc(e.expense_type) + '" data-amount="' + e.amount + '" data-paidto="' + esc(e.paid_to||'') + '" data-paidby="' + esc(e.paid_by||'') + '" data-mode="' + esc(e.payment_mode||'cash') + '" data-notes="' + esc(e.notes||'') + '" title="Edit">✏️</button>'
                        + '<button class="jtbfe-del-btn jtbfe-void-expense" style="margin-right:4px;color:#f59e0b" data-id="' + e.id + '" title="Void">⊘</button>';
                }
                if (CAN_DELETE) {
                    actBtns += '<button class="jtbfe-del-btn jtbfe-delete-expense" data-id="' + e.id + '" title="Delete">🗑</button>';
                }
                actBtns += '</td>';
            } else if (CAN_EDIT || CAN_DELETE) {
                actBtns = '<td></td>';
            }
            html += '<tr' + (isVoided ? ' style="opacity:.6"' : '') + '>'
                + '<td>' + fmtDate(e.expense_date) + '</td>'
                + '<td>' + esc(e.expense_type) + '</td>'
                + '<td class="jtbfe-amount">' + amtHtml + '</td>'
                + '<td>' + esc(e.paid_to || '—') + '</td>'
                + '<td>' + esc(e.paid_by || '—') + '</td>'
                + '<td>' + ucfirst(e.payment_mode || 'cash') + '</td>'
                + '<td style="color:#6b7280;font-size:11px">' + (notesHtml || '—') + '</td>'
                + actBtns + '</tr>';
        });
        return html + '</tbody></table>';
    }

    function updateDetailSummary(b) {
        var bal = parseFloat(b.balance_amount) || 0;
        var profit = parseFloat(b.booking_amount) - parseFloat(b.total_expenses || 0);
        $('#jtbfe-ds').html(
            ds('Booking Amount', fmt(b.booking_amount), '')
            + ds('Total Received', fmt(b.total_paid || 0), 'is-received')
            + ds('Balance Pending', fmt(bal), bal > 0 ? 'is-pending' : 'is-clear')
            + ds('Expenses', fmt(b.total_expenses || 0), 'is-expense')
            + ds('Gross Profit', fmt(profit), profit >= 0 ? 'is-profit' : 'is-loss')
        );
    }

    function updateDetailPayments(b, payments) {
        updateDetailSummary(b);
        $('#jtbfe-detail-payments').html(renderPaymentsTable(payments));
    }

    function updateDetailExpenses(b, expenses) {
        updateDetailSummary(b);
        $('#jtbfe-detail-expenses').html(renderExpensesTable(expenses));
    }

    // Expose for inline onclick
    window.navigate_to_edit = function (id) {
        navigate('new-booking', { editId: id });
    };

    /* -------------------------------------------------------- */
    /* PAYMENTS                                                  */
    /* -------------------------------------------------------- */

    function savePayment() {
        var amount = parseFloat($('#pfe-amount').val());
        if (!amount || amount <= 0) { toast('Enter a valid amount.', 'error'); return; }
        var $btn = $('#jtbfe-save-payment').prop('disabled', true).text('Saving…');

        post('wpyogfleet_fe_add_payment', {
            booking_id:      state.currentBookingId,
            payment_date:    $('#pfe-date').val(),
            amount:          amount,
            payment_mode:    $('#pfe-mode').val(),
            received_by:     $('#pfe-received-by').val(),
            transaction_ref: $('#pfe-ref').val(),
            notes:           $('#pfe-notes').val(),
        }, function (res) {
            if (res.success) {
                updateDetailPayments(res.data.booking, res.data.payments);
                closeModal('jtbfe-payment-modal');
                clearModal('jtbfe-payment-modal');
                toast('Payment saved!');
            } else { toast(res.data.message || 'Error.', 'error'); }
        }, function () { $btn.prop('disabled', false).text('Save Payment'); });
    }

    /* -------------------------------------------------------- */
    /* EXPENSES                                                  */
    /* -------------------------------------------------------- */

    function saveExpense() {
        var type = $('#efe-type').val() === '__other' ? $('#efe-type-other').val() : $('#efe-type').val();
        var amount = parseFloat($('#efe-amount').val());
        if (!type) { toast('Select an expense type.', 'error'); return; }
        if (!amount || amount <= 0) { toast('Enter a valid amount.', 'error'); return; }
        var $btn = $('#jtbfe-save-expense').prop('disabled', true).text('Saving…');

        post('wpyogfleet_fe_add_expense', {
            booking_id:   state.currentBookingId,
            expense_date: $('#efe-date').val(),
            expense_type: type,
            amount:       amount,
            payment_mode: $('#efe-mode').val(),
            paid_to:      $('#efe-paid-to').val(),
            paid_by:      $('#efe-paid-by').val(),
            notes:        $('#efe-notes').val(),
        }, function (res) {
            if (res.success) {
                updateDetailExpenses(res.data.booking, res.data.expenses);
                closeModal('jtbfe-expense-modal');
                clearModal('jtbfe-expense-modal');
                toast('Expense saved!');
            } else { toast(res.data.message || 'Error.', 'error'); }
        }, function () { $btn.prop('disabled', false).text('Save Expense'); });
    }

    /* -------------------------------------------------------- */
    /* PENDING PAYMENTS                                          */
    /* -------------------------------------------------------- */

    function loadPending() {
        $('#jtbfe-pending-list').html('<div class="jtbfe-loading"><div class="jtbfe-spinner"></div></div>');
        post('wpyogfleet_fe_get_bookings', { pending_only: 1, page: 1 }, function (res) {
            if (!res.success) return;
            var items = res.data.items || [];
            if (!items.length) {
                $('#jtbfe-pending-list').html('<div class="jtbfe-empty"><div class="jtbfe-empty__icon">✅</div><p>All bookings are fully paid!</p></div>');
                return;
            }
            var total = items.reduce(function (s, b) { return s + parseFloat(b.balance_amount); }, 0);
            var html = '<div class="jtbfe-pending-total">'
                + '<span class="jtbfe-pending-total__label">' + (res.data.total || items.length) + ' bookings with pending balance</span>'
                + '<span class="jtbfe-pending-total__amount">' + fmt(total) + '</span>'
                + '</div>';
            html += renderBookingCards(items);
            $('#jtbfe-pending-list').html(html);
        });
    }

    /* -------------------------------------------------------- */
    /* VEHICLES & DRIVERS                                        */
    /* -------------------------------------------------------- */

    function loadVehiclesAndDrivers() {
        post('wpyogfleet_fe_get_vehicles', {}, function (res) { if (res.success) state.vehicles = res.data; });
        post('wpyogfleet_fe_get_drivers', {}, function (res) { if (res.success) state.drivers = res.data; });
    }

    function loadCompanies() {
        post('wpyogfleet_fe_get_companies', {}, function (res) { if (res.success) state.companies = res.data; });
    }

    function loadCompaniesView() {
        var $el = $('#jtbfe-companies-list');
        $el.html('<div class="jtbfe-loading"><div class="jtbfe-spinner"></div></div>');
        post('wpyogfleet_fe_get_companies', {}, function (res) {
            if (res.success) {
                state.companies = res.data;
                $el.html(renderCompanyCards(res.data));
            } else {
                $el.html('<div class="jtbfe-empty"><p>Could not load companies.</p></div>');
            }
        });
    }

    function renderCompanyCards(items) {
        if (!items || !items.length) {
            return '<div class="jtbfe-empty"><div class="jtbfe-empty__icon">🏢</div><p>No companies yet. Click <strong>+ Add Company</strong> to get started.</p></div>';
        }
        return '<div class="jtbfe-fleet-grid">' + items.map(function (c) {
            var sub = c.contact_name
                ? esc(c.contact_name) + (c.contact_mobile ? ' · ' + esc(c.contact_mobile) : '')
                : (c.contact_mobile ? esc(c.contact_mobile) : '');
            return '<div class="jtbfe-fleet-card" data-company-id="' + c.id + '">'
                + '<div class="jtbfe-fleet-card__icon">🏢</div>'
                + '<div class="jtbfe-fleet-card__body">'
                + '<div class="jtbfe-fleet-card__name">' + esc(c.company_name) + '</div>'
                + (sub ? '<div class="jtbfe-fleet-card__reg">' + sub + '</div>' : '')
                + (c.contact_email ? '<div class="jtbfe-fleet-card__vendor">' + esc(c.contact_email) + '</div>' : '')
                + (c.gst_number ? '<div class="jtbfe-fleet-card__vendor">GST: ' + esc(c.gst_number) + '</div>' : '')
                + '</div>'
                + '<div class="jtbfe-fleet-card__edit">✏️</div>'
                + '</div>';
        }).join('') + '</div>';
    }

    function openCompanyModal(id) {
        // Reset form
        $('#cfe-id').val('');
        $('#cfe-name,#cfe-contact-name,#cfe-contact-mobile,#cfe-contact-email,#cfe-gst,#cfe-notes').val('');
        $('#jtbfe-delete-company').hide();
        $('#jtbfe-company-modal-title').text('Add Company');

        if (id) {
            // Find in state first for instant populate, then confirm with server
            var cached = null;
            state.companies.forEach(function(c) { if (c.id === id) cached = c; });
            if (cached) {
                $('#cfe-id').val(cached.id);
                $('#cfe-name').val(cached.company_name);
                $('#cfe-contact-name').val(cached.contact_name || '');
                $('#cfe-contact-mobile').val(cached.contact_mobile || '');
                $('#cfe-contact-email').val(cached.contact_email || '');
                $('#cfe-gst').val(cached.gst_number || '');
                $('#cfe-notes').val(cached.notes || '');
                $('#jtbfe-company-modal-title').text('Edit Company');
                if (CAN_DELETE) $('#jtbfe-delete-company').show();
            }
        }
        openModal('jtbfe-company-modal');
    }

    function saveCompany() {
        var name = $('#cfe-name').val().trim();
        if (!name) { toast('Company name is required.', 'error'); return; }
        var $btn = $('#jtbfe-save-company').prop('disabled', true).text('Saving…');
        post('wpyogfleet_fe_save_company', {
            company_id:      $('#cfe-id').val(),
            company_name:    name,
            contact_name:    $('#cfe-contact-name').val().trim(),
            contact_mobile:  $('#cfe-contact-mobile').val().trim(),
            contact_email:   $('#cfe-contact-email').val().trim(),
            gst_number:      $('#cfe-gst').val().trim(),
            notes:           $('#cfe-notes').val().trim(),
            status:          'active',
        }, function (res) {
            if (res.success) {
                closeModal('jtbfe-company-modal');
                toast(res.data.action === 'created' ? 'Company added!' : 'Company updated!');
                loadCompaniesView();
                loadCompanies(); // refresh dropdown in booking form
            } else {
                toast((res.data && res.data.message) || 'Save failed.', 'error');
            }
        }, function () {
            $btn.prop('disabled', false).text('Save Company');
        });
    }

    /* -------------------------------------------------------- */
    /* HELPERS                                                   */
    /* -------------------------------------------------------- */

    function post(action, data, success, always) {
        data = $.extend({ action: action, nonce: NONCE }, data);
        $.ajax({
            url:      AJAX,
            type:     'POST',
            data:     data,
            dataType: 'json',
            success: function (res) {
                if (success) success(res);
            },
            error: function (xhr) {
                // Surface AJAX errors so loading spinners don't hang forever
                var msg = 'Request failed (HTTP ' + xhr.status + ').';
                try {
                    var parsed = JSON.parse(xhr.responseText);
                    if (parsed && parsed.data && parsed.data.message) msg = parsed.data.message;
                } catch (e) {}
                if (success) success({ success: false, data: { message: msg } });
            },
            complete: function () {
                if (always) always();
            }
        });
    }

    function calcBalance() {
        var a = parseFloat($('#bf-amount').val()) || 0;
        var d = parseFloat($('#bf-advance').val()) || 0;
        var b = a - d;
        $('#bf-balance-val').text(fmt(b)).css('color', b > 0 ? '#ef4444' : '#10b981');
    }

    function resetChips($group, val) {
        $group.find('.jtbfe-chip').removeClass('is-selected');
        $group.find('input[value="' + val + '"]').closest('.jtbfe-chip').addClass('is-selected').find('input').prop('checked', true);
    }

    function openModal(id) {
        $('#' + id).addClass('is-open');
        $('body').css('overflow', 'hidden');
    }
    function closeModal(id) {
        var $m = id ? $('#' + id) : $('.jtbfe-modal.is-open');
        $m.removeClass('is-open');
        if (!$('.jtbfe-modal.is-open').length) $('body').css('overflow', '');
    }
    function clearModal(id) {
        var $m = $('#' + id);
        $m.find('input[type=text],input[type=number],textarea').val('');
        $m.find('select').prop('selectedIndex', 0);
        $m.find('input[type=date]').val(today());
        $('#efe-type-other-wrap').hide();
    }

    function badge(status) {
        return '<span class="jtbfe-badge jtbfe-badge--' + status + '">' + ucfirst(status) + '</span>';
    }

    function fmt(amount) {
        return '₹' + parseFloat(amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function fmtDate(d) {
        if (!d) return '—';
        var dt = new Date(d);
        return dt.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
    }

    function fmtDatetime(d) {
        if (!d) return '—';
        var dt = new Date(d.replace(' ', 'T'));
        return dt.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' })
            + ', ' + dt.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' });
    }

    function today() {
        return new Date().toISOString().split('T')[0];
    }

    function esc(str) {
        if (!str) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function ucfirst(str) {
        if (!str) return '';
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    function toast(msg, type) {
        type = type || 'success';
        var $t = $('<div class="jtbfe-toast jtbfe-toast-' + type + '">' + msg + '</div>');
        $('#jtbfe-toasts').append($t);
        setTimeout(function () { $t.addClass('is-in'); }, 10);
        setTimeout(function () {
            $t.removeClass('is-in');
            setTimeout(function () { $t.remove(); }, 300);
        }, 3200);
    }

    /* -------------------------------------------------------- */
    /* VEHICLES                                                  */
    /* -------------------------------------------------------- */

    function loadVehicleList() {
        $('#jtbfe-vehicles-grid').html('<div class="jtbfe-loading"><div class="jtbfe-spinner"></div></div>');
        post('wpyogfleet_fe_get_vehicle_list', {}, function (res) {
            if (!res.success) return;
            state.allVehicles = res.data;
            if (!res.data.length) {
                $('#jtbfe-vehicles-grid').html('<div class="jtbfe-empty"><div class="jtbfe-empty__icon">🚗</div><p>No vehicles yet. Add your first vehicle!</p></div>');
                return;
            }
            var html = '<div class="jtbfe-fleet-grid">';
            res.data.forEach(function (v) {
                var typeTag = v.vehicle_type ? '<span class="jtbfe-fleet-tag">' + esc(v.vehicle_type) + '</span>' : '';
                var ownerTag = v.owner_type === 'outsourced' ? '<span class="jtbfe-fleet-tag jtbfe-fleet-tag--outsourced">Outsourced</span>' : '<span class="jtbfe-fleet-tag jtbfe-fleet-tag--own">Own</span>';
                var statusDot = v.status === 'active' ? '<span class="jtbfe-status-dot active"></span>' : '<span class="jtbfe-status-dot inactive"></span>';
                html += '<div class="jtbfe-fleet-card" data-vehicle-id="' + v.id + '">'
                    + '<div class="jtbfe-fleet-card__icon">🚗</div>'
                    + '<div class="jtbfe-fleet-card__body">'
                    + '<div class="jtbfe-fleet-card__name">' + statusDot + esc(v.vehicle_name) + '</div>'
                    + '<div class="jtbfe-fleet-card__reg">' + esc(v.registration_number || '—') + '</div>'
                    + '<div class="jtbfe-fleet-card__tags">' + typeTag + ownerTag + '</div>'
                    + (v.vendor_name ? '<div class="jtbfe-fleet-card__vendor">Vendor: ' + esc(v.vendor_name) + '</div>' : '')
                    + '</div>'
                    + '<div class="jtbfe-fleet-card__edit">✏️</div>'
                    + '</div>';
            });
            html += '</div>';
            $('#jtbfe-vehicles-grid').html(html);
        });
    }

    function openVehicleModal(id) {
        // Reset form
        $('#vfe-id').val('');
        $('#vfe-name,#vfe-reg,#vfe-vendor-name,#vfe-vendor-mobile,#vfe-notes').val('');
        $('#vfe-type,#vfe-status').prop('selectedIndex', 0);
        $('#vfe-owner').val('own');
        $('#vfe-vendor-wrap').hide();
        $('#jtbfe-delete-vehicle').hide();
        $('#jtbfe-vehicle-modal-title').text('Add Vehicle');

        if (id) {
            // Load existing
            var v = state.allVehicles.find(function (x) { return x.id == id; });
            if (v) {
                $('#vfe-id').val(v.id);
                $('#vfe-name').val(v.vehicle_name);
                $('#vfe-type').val(v.vehicle_type);
                $('#vfe-reg').val(v.registration_number);
                $('#vfe-owner').val(v.owner_type);
                $('#vfe-vendor-name').val(v.vendor_name);
                $('#vfe-vendor-mobile').val(v.vendor_mobile);
                $('#vfe-status').val(v.status);
                $('#vfe-notes').val(v.notes);
                $('#vfe-vendor-wrap').toggle(v.owner_type === 'outsourced');
                $('#jtbfe-vehicle-modal-title').text('Edit Vehicle');
                if (CAN_DELETE) $('#jtbfe-delete-vehicle').show();
            }
        }
        openModal('jtbfe-vehicle-modal');
    }

    function saveVehicle() {
        var name = $('#vfe-name').val().trim();
        if (!name) { toast('Vehicle name is required.', 'error'); return; }
        var $btn = $('#jtbfe-save-vehicle').prop('disabled', true).text('Saving…');

        post('wpyogfleet_fe_save_vehicle', {
            vehicle_id:          $('#vfe-id').val(),
            vehicle_name:        name,
            vehicle_type:        $('#vfe-type').val(),
            registration_number: $('#vfe-reg').val(),
            owner_type:          $('#vfe-owner').val(),
            vendor_name:         $('#vfe-vendor-name').val(),
            vendor_mobile:       $('#vfe-vendor-mobile').val(),
            status:              $('#vfe-status').val(),
            notes:               $('#vfe-notes').val(),
        }, function (res) {
            if (res.success) {
                closeModal('jtbfe-vehicle-modal');
                toast(res.data.action === 'created' ? 'Vehicle added!' : 'Vehicle updated!');
                loadVehicleList();
                loadVehiclesAndDrivers();
            } else { toast(res.data.message || 'Error.', 'error'); }
        }, function () { $btn.prop('disabled', false).text('Save Vehicle'); });
    }

    /* -------------------------------------------------------- */
    /* DRIVERS                                                   */
    /* -------------------------------------------------------- */

    function loadDriverList() {
        $('#jtbfe-drivers-grid').html('<div class="jtbfe-loading"><div class="jtbfe-spinner"></div></div>');
        post('wpyogfleet_fe_get_driver_list', {}, function (res) {
            if (!res.success) return;
            state.allDrivers = res.data;
            if (!res.data.length) {
                $('#jtbfe-drivers-grid').html('<div class="jtbfe-empty"><div class="jtbfe-empty__icon">👤</div><p>No drivers yet. Add your first driver!</p></div>');
                return;
            }
            var html = '<div class="jtbfe-fleet-grid">';
            res.data.forEach(function (d) {
                var typeTag = d.driver_type === 'outsourced'
                    ? '<span class="jtbfe-fleet-tag jtbfe-fleet-tag--outsourced">Outsourced</span>'
                    : '<span class="jtbfe-fleet-tag jtbfe-fleet-tag--own">Own</span>';
                var statusDot = d.status === 'active' ? '<span class="jtbfe-status-dot active"></span>' : '<span class="jtbfe-status-dot inactive"></span>';
                html += '<div class="jtbfe-fleet-card" data-driver-id="' + d.id + '">'
                    + '<div class="jtbfe-fleet-card__icon">👤</div>'
                    + '<div class="jtbfe-fleet-card__body">'
                    + '<div class="jtbfe-fleet-card__name">' + statusDot + esc(d.driver_name) + '</div>'
                    + '<div class="jtbfe-fleet-card__reg">📱 ' + esc(d.mobile || '—') + '</div>'
                    + '<div class="jtbfe-fleet-card__tags">'
                    + typeTag
                    + (d.vehicle_name ? '<span class="jtbfe-fleet-tag">🚗 ' + esc(d.vehicle_name) + '</span>' : '')
                    + '</div>'
                    + '</div>'
                    + '<div class="jtbfe-fleet-card__edit">✏️</div>'
                    + '</div>';
            });
            html += '</div>';
            $('#jtbfe-drivers-grid').html(html);
        });
    }

    function openDriverModal(id) {
        $('#dfe-id').val('');
        $('#dfe-name,#dfe-mobile,#dfe-notes').val('');
        $('#dfe-vehicle,#dfe-type,#dfe-status').prop('selectedIndex', 0);
        $('#jtbfe-delete-driver').hide();
        $('#jtbfe-driver-modal-title').text('Add Driver');

        // Populate vehicle dropdown
        var $dv = $('#dfe-vehicle');
        $dv.find('option:not(:first)').remove();
        state.allVehicles.forEach(function (v) {
            if (v.status === 'active') {
                $dv.append('<option value="' + v.id + '">' + esc(v.vehicle_name + (v.registration_number ? ' (' + v.registration_number + ')' : '')) + '</option>');
            }
        });

        if (id) {
            var d = state.allDrivers.find(function (x) { return x.id == id; });
            if (d) {
                $('#dfe-id').val(d.id);
                $('#dfe-name').val(d.driver_name);
                $('#dfe-mobile').val(d.mobile);
                $('#dfe-vehicle').val(d.vehicle_id);
                $('#dfe-type').val(d.driver_type);
                $('#dfe-status').val(d.status);
                $('#dfe-notes').val(d.notes);
                $('#jtbfe-driver-modal-title').text('Edit Driver');
                if (CAN_DELETE) $('#jtbfe-delete-driver').show();
            }
        }
        openModal('jtbfe-driver-modal');
    }

    function saveDriver() {
        var name = $('#dfe-name').val().trim();
        var mobile = $('#dfe-mobile').val().trim();
        if (!name) { toast('Driver name is required.', 'error'); return; }
        if (!mobile) { toast('Mobile number is required.', 'error'); return; }
        var $btn = $('#jtbfe-save-driver').prop('disabled', true).text('Saving…');

        post('wpyogfleet_fe_save_driver', {
            driver_id:   $('#dfe-id').val(),
            driver_name: name,
            mobile:      mobile,
            vehicle_id:  $('#dfe-vehicle').val(),
            driver_type: $('#dfe-type').val(),
            status:      $('#dfe-status').val(),
            notes:       $('#dfe-notes').val(),
        }, function (res) {
            if (res.success) {
                closeModal('jtbfe-driver-modal');
                toast(res.data.action === 'created' ? 'Driver added!' : 'Driver updated!');
                loadDriverList();
                loadVehiclesAndDrivers();
            } else { toast(res.data.message || 'Error.', 'error'); }
        }, function () { $btn.prop('disabled', false).text('Save Driver'); });
    }

    /* -------------------------------------------------------- */
    /* REPORTS                                                   */
    /* -------------------------------------------------------- */

    function loadReports() {
        state.reportTab = 'monthly';
        $('#jtbfe-report-tabs .jtbfe-tab').removeClass('is-active');
        $('#jtbfe-report-tabs .jtbfe-tab[data-tab="monthly"]').addClass('is-active');
        $('#jtbfe-report-month-ctrl').show();
        $('#jtbfe-report-range-ctrl').addClass('jtbfe-hidden');
        loadReportTab('monthly');
    }

    function loadReportTab(tab) {
        state.reportTab = tab;
        $('#jtbfe-reports-content').html('<div class="jtbfe-loading"><div class="jtbfe-spinner"></div></div>');
        var data = { tab: tab };
        if (tab === 'monthly') {
            data.month = $('#jtbfe-report-month').val() || new Date().toISOString().substring(0, 7);
        } else {
            data.date_from = $('#jtbfe-report-from').val();
            data.date_to   = $('#jtbfe-report-to').val();
        }
        if (tab === 'customer_type') {
            data.customer_type = $('#jtbfe-report-cust-type').val() || 'all';
        }
        post('wpyogfleet_fe_get_reports', data, function (res) {
            if (!res.success) { $('#jtbfe-reports-content').html('<p style="padding:24px;color:#ef4444">Error loading report: ' + ((res.data && res.data.message) || 'unknown') + '</p>'); return; }
            renderReport(res.data);
        });
    }

    function renderReport(data) {
        var html = '';
        if (data.tab === 'monthly') {
            var d = data.data;
            var profit = parseFloat(d.received || 0) - parseFloat(d.expenses || 0);
            html = '<div class="jtbfe-report-stats">'
                + rStat('🎫', d.bookings, 'Total Bookings', 'blue')
                + rStat('💼', fmt(d.booking_amount || 0), 'Booking Value', 'indigo')
                + rStat('💰', fmt(d.received || 0), 'Amount Collected', 'green')
                + rStat('🧾', fmt(d.expenses || 0), 'Total Expenses', 'amber')
                + rStat('📈', fmt(profit), 'Gross Profit', profit >= 0 ? 'green' : 'red')
                + '</div>'
                + '<div class="jtbfe-report-stats">'
                + rStat('🏢', fmt(d.corporate_amount || 0), 'Total Booking – Corp Customer', 'indigo')
                + rStat('🙋', fmt(d.individual_amount || 0), 'Total Booking – Individual Customer', 'amber')
                + '</div>';
        } else if (data.tab === 'drivers') {
            html = renderReportTable(data.data,
                ['Driver', 'Mobile', 'Trips', 'Booking Value', 'Collected', 'Expenses'],
                function (r) {
                    return '<td>' + esc(r.driver_name) + '</td>'
                        + '<td>' + esc(r.mobile || '—') + '</td>'
                        + '<td><strong>' + r.trips + '</strong></td>'
                        + '<td>' + fmt(r.total_amount) + '</td>'
                        + '<td style="color:#059669">' + fmt(r.total_received) + '</td>'
                        + '<td style="color:#f59e0b">' + fmt(r.total_expenses) + '</td>';
                }, '👤 No driver data for selected period.', data.debug);
        } else if (data.tab === 'vehicles') {
            html = renderReportTable(data.data,
                ['Vehicle', 'Reg No.', 'Trips', 'Booking Value', 'Collected', 'Expenses'],
                function (r) {
                    return '<td>' + esc(r.vehicle_name) + '</td>'
                        + '<td style="color:#94a3b8;font-size:12px">' + esc(r.registration_number || '—') + '</td>'
                        + '<td><strong>' + r.trips + '</strong></td>'
                        + '<td>' + fmt(r.total_amount) + '</td>'
                        + '<td style="color:#059669">' + fmt(r.total_received) + '</td>'
                        + '<td style="color:#f59e0b">' + fmt(r.total_expenses) + '</td>';
                }, '🚗 No vehicle data for selected period.', data.debug);
        } else if (data.tab === 'customer_type') {
            var grandBookings = (data.data || []).reduce(function (sum, r) { return sum + (parseInt(r.bookings, 10) || 0); }, 0);
            var grandAmount   = (data.data || []).reduce(function (sum, r) { return sum + (parseFloat(r.total_amount) || 0); }, 0);
            html = renderReportTable(data.data,
                ['Customer', 'Type', 'Bookings', 'Total Booking Amount'],
                function (r) {
                    return '<td>' + esc(r.name) + '</td>'
                        + '<td>' + esc(r.type) + '</td>'
                        + '<td><strong>' + r.bookings + '</strong></td>'
                        + '<td>' + fmt(r.total_amount) + '</td>';
                }, '👥 No customer data for selected period.', null,
                (data.data && data.data.length)
                    ? '<td colspan="2">Total</td><td><strong>' + grandBookings + '</strong></td><td>' + fmt(grandAmount) + '</td>'
                    : null);
        }
        $('#jtbfe-reports-content').html(html);
    }

    function rStat(icon, value, label, color) {
        return '<div class="jtbfe-stat jtbfe-stat--' + color + '">'
            + '<div class="jtbfe-stat__icon">' + icon + '</div>'
            + '<div><div class="jtbfe-stat__val">' + value + '</div>'
            + '<div class="jtbfe-stat__label">' + label + '</div></div></div>';
    }

    function renderReportTable(rows, headers, rowFn, emptyMsg, debug, footerRowHtml) {
        if (!rows || !rows.length) {
            var dbgHtml = '';
            if (debug) {
                var parts = [];
                if (typeof debug.drivers !== 'undefined')  parts.push('Drivers in DB: ' + debug.drivers);
                if (typeof debug.vehicles !== 'undefined') parts.push('Vehicles in DB: ' + debug.vehicles);
                if (typeof debug.eligible_bookings !== 'undefined') parts.push('Eligible bookings: ' + debug.eligible_bookings);
                if (debug.db_error) parts.push('<span style="color:#ef4444">DB error: ' + esc(debug.db_error) + '</span>');
                if (parts.length) dbgHtml = '<p style="font-size:12px;color:#94a3b8;margin-top:8px">' + parts.join(' · ') + '</p>';
            }
            return '<div class="jtbfe-empty"><div class="jtbfe-empty__icon">📊</div><p>' + emptyMsg + '</p>' + dbgHtml + '</div>';
        }
        var html = '<div class="jtbfe-card"><div class="jtbfe-card__body p-0"><table class="jtbfe-txn-table"><thead><tr>';
        headers.forEach(function (h) { html += '<th>' + h + '</th>'; });
        html += '</tr></thead><tbody>';
        rows.forEach(function (r) { html += '<tr>' + rowFn(r) + '</tr>'; });
        if (footerRowHtml) {
            html += '<tr class="jtbfe-txn-table__total">' + footerRowHtml + '</tr>';
        }
        html += '</tbody></table></div></div>';
        return html;
    }

    /* -------------------------------------------------------- */
    /* CALENDAR                                                  */
    /* -------------------------------------------------------- */

    var JTBFECal = {
        MONTHS: ['January','February','March','April','May','June',
                 'July','August','September','October','November','December'],
        DAYS:   ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'],
        STATUS_LABEL: { new:'New', confirmed:'Confirmed', ongoing:'Ongoing', completed:'Completed', closed:'Closed', cancelled:'Cancelled' },

        init: function(containerId) {
            var self = this;
            self.container = document.getElementById(containerId);
            if (!self.container) return;
            self.today    = new Date();
            self.curYear  = self.today.getFullYear();
            self.curMonth = self.today.getMonth() + 1;
            self.events   = {};
            self.loaded   = {};

            // Build popup overlay (once per page)
            if (!document.getElementById('jtbfe-cal-popup-overlay')) {
                var ov = document.createElement('div');
                ov.id        = 'jtbfe-cal-popup-overlay';
                ov.className = 'jtbfe-cal-popup-overlay';
                ov.addEventListener('click', function() { self.closePopup(); });
                document.body.appendChild(ov);

                var pop = document.createElement('div');
                pop.id        = 'jtbfe-cal-popup';
                pop.className = 'jtbfe-cal-popup';
                pop.innerHTML = '<div class="jtbfe-cal-popup__header">'
                              + '<h3 class="jtbfe-cal-popup__title" id="jtbfe-cal-popup-title"></h3>'
                              + '<button class="jtbfe-cal-popup__close" id="jtbfe-cal-popup-close">&#x2715;</button>'
                              + '</div>'
                              + '<div class="jtbfe-cal-popup__body" id="jtbfe-cal-popup-body"></div>';
                document.body.appendChild(pop);
                document.getElementById('jtbfe-cal-popup-close').addEventListener('click', function() { self.closePopup(); });
            }

            self.render();
            self.loadMonth(self.curYear, self.curMonth);
        },

        render: function() {
            var self = this;
            var id   = self.container.id;
            self.container.innerHTML =
                '<div class="jtbfe-cal-nav">'
              + '<button class="jtbfe-cal-nav__btn" id="' + id + '-prev">&#8592;</button>'
              + '<button class="jtbfe-cal-today-btn" id="' + id + '-today">Today</button>'
              + '<span class="jtbfe-cal-nav__month" id="' + id + '-label"></span>'
              + '<button class="jtbfe-cal-nav__btn" id="' + id + '-next">&#8594;</button>'
              + '</div>'
              + '<div class="jtbfe-cal-grid" id="' + id + '-grid"></div>'
              + '<div class="jtbfe-cal-legend" id="' + id + '-legend">' + self.legendHtml() + '</div>';

            document.getElementById(id + '-prev').addEventListener('click', function() { self.navigate(-1); });
            document.getElementById(id + '-next').addEventListener('click', function() { self.navigate(1); });
            document.getElementById(id + '-today').addEventListener('click', function() {
                self.curYear  = self.today.getFullYear();
                self.curMonth = self.today.getMonth() + 1;
                self.renderGrid();
                self.loadMonth(self.curYear, self.curMonth);
            });
            self.renderGrid();
        },

        navigate: function(delta) {
            var self = this;
            self.curMonth += delta;
            if (self.curMonth > 12) { self.curMonth = 1;  self.curYear++; }
            if (self.curMonth < 1)  { self.curMonth = 12; self.curYear--; }
            self.renderGrid();
            self.loadMonth(self.curYear, self.curMonth);
        },

        renderGrid: function() {
            var self = this;
            var grid = document.getElementById(self.container.id + '-grid');
            var lbl  = document.getElementById(self.container.id + '-label');
            if (!grid) return;

            lbl.textContent = self.MONTHS[self.curMonth - 1] + ' ' + self.curYear;

            var key       = self.monthKey(self.curYear, self.curMonth);
            var isLoading = !self.loaded[key];
            var todayStr  = self.dateStr(self.today);

            var html = self.DAYS.map(function(d) {
                return '<div class="jtbfe-cal-header-cell">' + d + '</div>';
            }).join('');

            var firstDay    = new Date(self.curYear, self.curMonth - 1, 1).getDay();
            var daysInMonth = new Date(self.curYear, self.curMonth, 0).getDate();
            var daysInPrev  = new Date(self.curYear, self.curMonth - 1, 0).getDate();

            for (var i = firstDay - 1; i >= 0; i--) {
                var pm = self.curMonth - 1 < 1 ? 12 : self.curMonth - 1;
                var py = pm === 12 ? self.curYear - 1 : self.curYear;
                html += self.cellHtml(daysInPrev - i, self.dateStr(new Date(py, pm - 1, daysInPrev - i)), true, todayStr, isLoading);
            }
            for (var day = 1; day <= daysInMonth; day++) {
                html += self.cellHtml(day, self.dateStr(new Date(self.curYear, self.curMonth - 1, day)), false, todayStr, isLoading);
            }
            var totalCells = Math.ceil((firstDay + daysInMonth) / 7) * 7;
            var trailing   = totalCells - firstDay - daysInMonth;
            for (var t = 1; t <= trailing; t++) {
                var nm = self.curMonth + 1 > 12 ? 1 : self.curMonth + 1;
                var ny = nm === 1 ? self.curYear + 1 : self.curYear;
                html += self.cellHtml(t, self.dateStr(new Date(ny, nm - 1, t)), true, todayStr, isLoading);
            }

            grid.innerHTML = html;

            grid.querySelectorAll('.jtbfe-cal-cell.has-events').forEach(function(cell) {
                cell.addEventListener('click', function() {
                    self.openPopup(cell.getAttribute('data-date'));
                });
            });
            grid.querySelectorAll('.jtbfe-cal-event-chip, .jtbfe-cal-more').forEach(function(chip) {
                chip.addEventListener('click', function(e) {
                    e.stopPropagation();
                    self.openPopup(chip.closest('[data-date]').getAttribute('data-date'));
                });
            });
        },

        cellHtml: function(day, dStr, otherMonth, todayStr, isLoading) {
            var self    = this;
            var evList  = self.events[dStr] || [];
            var classes = 'jtbfe-cal-cell';
            if (otherMonth)          classes += ' other-month';
            if (dStr === todayStr)   classes += ' is-today';
            if (evList.length > 0)   classes += ' has-events';

            var inner = '<div class="jtbfe-cal-day-num">' + day + '</div>';
            inner += '<div class="jtbfe-cal-events">';
            if (!isLoading) {
                var maxShow = 3;
                for (var i = 0; i < Math.min(evList.length, maxShow); i++) {
                    var ev      = evList[i];
                    var evName  = ev.passenger_name || ev.customer;
                    var evVeh   = ev.reg_number || ev.vehicle;
                    var evTitle = evName + (evVeh ? ' · ' + evVeh : '') + ' · ' + ev.route;
                    var chipTxt = (evVeh ? evVeh + ' · ' : '') + evName;
                    inner += '<div class="jtbfe-cal-event-chip status-' + ev.status + '" title="' + self.esc(evTitle) + '">'
                           + self.esc(chipTxt) + '</div>';
                }
                if (evList.length > maxShow) {
                    inner += '<div class="jtbfe-cal-more">+' + (evList.length - maxShow) + ' more</div>';
                }
            }
            inner += '</div>';
            return '<div class="' + classes + '" data-date="' + dStr + '">' + inner + '</div>';
        },

        loadMonth: function(year, month) {
            var self = this;
            var key  = self.monthKey(year, month);
            if (self.loaded[key]) return;

            post('wpyogfleet_fe_get_calendar_bookings', { year: year, month: month }, function(res) {
                if (!res.success) return;
                self.loaded[key] = true;
                (res.data.events || []).forEach(function(ev) {
                    if (!self.events[ev.date]) self.events[ev.date] = [];
                    self.events[ev.date].push(ev);
                });
                if (self.curYear === year && self.curMonth === month) {
                    self.renderGrid();
                }
            });
        },

        openPopup: function(dStr) {
            var self   = this;
            var evList = self.events[dStr] || [];
            var parts  = dStr.split('-');
            var label  = parseInt(parts[2], 10) + ' ' + self.MONTHS[parseInt(parts[1], 10) - 1] + ' ' + parts[0];

            document.getElementById('jtbfe-cal-popup-title').textContent = label;

            var body = '';
            if (!evList.length) {
                body = '<p style="color:var(--c-text-2,#94a3b8);font-size:13px;text-align:center;padding:20px 0">No bookings on this day.</p>';
            } else {
                evList.forEach(function(ev) {
                    var popName = ev.passenger_name || ev.customer;
                    var popVeh  = ev.reg_number
                                    ? ev.reg_number + (ev.vehicle ? ' (' + ev.vehicle + ')' : '')
                                    : ev.vehicle;
                    body += '<div class="jtbfe-cal-popup-item status-' + ev.status + '" data-booking-id="' + ev.id + '" style="cursor:pointer">'
                          + '<div class="jtbfe-cal-popup-item__num">' + self.esc(ev.number) + ' · ' + (self.STATUS_LABEL[ev.status] || ev.status) + '</div>'
                          + '<div class="jtbfe-cal-popup-item__name">' + self.esc(popName) + '</div>'
                          + '<div class="jtbfe-cal-popup-item__meta">'
                          + self.esc(ev.route)
                          + (popVeh ? '<br>' + self.esc(popVeh) : '')
                          + (ev.driver ? ' · ' + self.esc(ev.driver) : '')
                          + '</div>'
                          + '</div>';
                });
            }
            document.getElementById('jtbfe-cal-popup-body').innerHTML = body;

            // Bind click → navigate
            document.querySelectorAll('#jtbfe-cal-popup-body .jtbfe-cal-popup-item').forEach(function(el) {
                el.addEventListener('click', function() {
                    var bid = parseInt(el.getAttribute('data-booking-id'), 10);
                    self.closePopup();
                    navigate('booking-detail', { bookingId: bid });
                });
            });

            document.getElementById('jtbfe-cal-popup').classList.add('is-open');
            document.getElementById('jtbfe-cal-popup-overlay').classList.add('is-open');
        },

        closePopup: function() {
            var el = document.getElementById('jtbfe-cal-popup');
            var ov = document.getElementById('jtbfe-cal-popup-overlay');
            if (el) el.classList.remove('is-open');
            if (ov) ov.classList.remove('is-open');
        },

        legendHtml: function() {
            return [
                { cls: 'status-new',       label: 'New' },
                { cls: 'status-confirmed', label: 'Confirmed' },
                { cls: 'status-ongoing',   label: 'Ongoing' },
                { cls: 'status-completed', label: 'Completed' },
                { cls: 'status-cancelled', label: 'Cancelled' },
            ].map(function(it) {
                return '<div class="jtbfe-cal-legend-item">'
                     + '<div class="jtbfe-cal-event-chip ' + it.cls + '" style="width:12px;height:12px;border-radius:3px;display:inline-block;vertical-align:middle"></div>'
                     + '<span>' + it.label + '</span>'
                     + '</div>';
            }).join('');
        },

        monthKey: function(y, m) { return y + '-' + (m < 10 ? '0' + m : m); },
        dateStr:  function(d) {
            return d.getFullYear() + '-'
                 + String(d.getMonth() + 1).padStart(2, '0') + '-'
                 + String(d.getDate()).padStart(2, '0');
        },
        esc: function(s) {
            return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        },
    };

    // ── Calendar instances ────────────────────────────────────────────────────

    // Dashboard widget
    var _dashCalInited = false;
    function initDashCalendar() {
        if (!_dashCalInited && document.getElementById('jtbfe-dash-calendar-wrap')) {
            _dashCalInited = true;
            JTBFECal.init('jtbfe-dash-calendar-wrap');
        }
    }

    // Bookings tab calendar (separate instance via Object.create)
    var _bookingsCalInited = false;
    var JTBFECalBookings = Object.create(JTBFECal);
    JTBFECalBookings.events = {};
    JTBFECalBookings.loaded = {};

    function initBookingsCalendar() {
        if (!_bookingsCalInited && document.getElementById('jtbfe-bookings-calendar-wrap')) {
            _bookingsCalInited = true;
            JTBFECalBookings.init('jtbfe-bookings-calendar-wrap');
        }
    }

    // Bookings toggle state
    var _bookingsView = 'list'; // 'list' | 'calendar'

    function setBookingsView(view) {
        _bookingsView = view;
        var $toggle = $('#jtbfe-bookings-toggle');
        $toggle.find('.jtbfe-view-toggle__btn').removeClass('is-active');
        $toggle.find('[data-bview="' + view + '"]').addClass('is-active');

        if (view === 'calendar') {
            $('#jtbfe-bookings-list').hide();
            $('#jtbfe-bookings-search-wrap').hide();
            $('#jtbfe-filter-status').closest('select').hide();
            $('#jtbfe-bookings-cal-wrap').show();
            initBookingsCalendar();
        } else {
            $('#jtbfe-bookings-cal-wrap').hide();
            $('#jtbfe-bookings-list').show();
            $('#jtbfe-bookings-search-wrap').show();
            $('#jtbfe-filter-status').show();
        }
    }

    // Stub to satisfy the navigate 'calendar' case (no-op now — kept for safety)
    function loadCalendar() { /* calendar is now embedded in the bookings tab */ }

})(jQuery);

/**
 * Global tab handler — called via inline onclick on each tab button.
 * Defined outside the IIFE so nothing can block it.
 */
window.jtbfeTab = function (el) {
    var tab = el.getAttribute('data-tab');
    if (!tab) return;
    var $ = window.jQuery;
    $('#jtbfe-report-tabs .jtbfe-tab').removeClass('is-active');
    $(el).addClass('is-active');
    if (tab === 'monthly') {
        $('#jtbfe-report-month-ctrl').show();
        $('#jtbfe-report-range-ctrl').addClass('jtbfe-hidden');
    } else {
        $('#jtbfe-report-month-ctrl').hide();
        $('#jtbfe-report-range-ctrl').removeClass('jtbfe-hidden');
    }
    if (tab === 'customer_type') {
        $('#jtbfe-report-type-ctrl').removeClass('jtbfe-hidden');
    } else {
        $('#jtbfe-report-type-ctrl').addClass('jtbfe-hidden');
    }
    // Fire the same AJAX load used internally
    var data = { tab: tab };
    if (tab === 'monthly') {
        data.month = $('#jtbfe-report-month').val() || new Date().toISOString().substring(0, 7);
    } else {
        data.date_from = $('#jtbfe-report-from').val();
        data.date_to   = $('#jtbfe-report-to').val();
    }
    if (tab === 'customer_type') {
        data.customer_type = $('#jtbfe-report-cust-type').val() || 'all';
    }
    var cfg = window.WPYOGFLEETFE || {};
    $('#jtbfe-reports-content').html('<div class="jtbfe-loading"><div class="jtbfe-spinner"></div></div>');
    $.ajax({
        url:      cfg.ajax_url,
        type:     'POST',
        data:     $.extend({ action: 'wpyogfleet_fe_get_reports', nonce: cfg.nonce }, data),
        dataType: 'json',
        success: function (res) {
            if (!res || !res.success) {
                $('#jtbfe-reports-content').html('<p style="padding:24px;color:#ef4444">Error: ' + ((res && res.data && res.data.message) || 'unknown') + '</p>');
                return;
            }
            // Re-use the renderReport function if available, otherwise inline render
            if (typeof window._jtbfeRenderReport === 'function') {
                window._jtbfeRenderReport(res.data);
            }
        },
        error: function (xhr) {
            $('#jtbfe-reports-content').html('<p style="padding:24px;color:#ef4444">HTTP ' + xhr.status + ' error.</p>');
        }
    });
};
