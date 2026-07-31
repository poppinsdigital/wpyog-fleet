/* WPYog Fleet — Admin JS
   Author: Poppins Digital (poppinsdigital.com) */
(function($) {
    'use strict';

    var NONCE = (window.WPYOGFLEET || {}).nonce || '';
    var AJAX  = (window.WPYOGFLEET || {}).ajax_url || ajaxurl;

    /* ------------------------------------------------------------------ */
    /* MODAL HELPERS                                                        */
    /* ------------------------------------------------------------------ */

    function openModal(id) {
        var $m = $('#' + id);
        $m.addClass('is-open');
        $('body').css('overflow', 'hidden');
        $m.find('input[type=text], input[type=number], input[type=tel], textarea').first().focus();
    }

    function closeModal(id) {
        var $m = id ? $('#' + id) : $('.jtb-modal.is-open');
        $m.removeClass('is-open');
        $('body').css('overflow', '');
    }

    function clearModal(id) {
        var $m = $('#' + id);
        $m.find('input[type=text], input[type=number], input[type=tel], textarea').val('');
        $m.find('select').each(function(){ this.selectedIndex = 0; });
        $m.find('input[type=date]').val(todayDate());
    }

    function todayDate() {
        var d = new Date();
        return d.toISOString().split('T')[0];
    }

    /* ------------------------------------------------------------------ */
    /* MODAL TRIGGERS                                                       */
    /* ------------------------------------------------------------------ */

    $(document).on('click', '#jtb-open-payment-modal', function() {
        clearModal('jtb-payment-modal');
        openModal('jtb-payment-modal');
    });

    $(document).on('click', '#jtb-open-expense-modal', function() {
        clearModal('jtb-expense-modal');
        openModal('jtb-expense-modal');
    });

    $(document).on('click', '.jtb-modal__close, .jtb-modal__overlay', function() {
        closeModal();
    });

    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') closeModal();
    });

    /* ------------------------------------------------------------------ */
    /* ADD PAYMENT                                                          */
    /* ------------------------------------------------------------------ */

    $(document).on('click', '#jtb-save-payment', function() {
        var $btn      = $(this);
        var bookingId = $btn.data('booking');
        var amount    = parseFloat($('#p-amount').val());

        if (!amount || amount <= 0) {
            alert('Please enter a valid amount.');
            return;
        }

        $btn.prop('disabled', true).text('Saving…');

        $.post(AJAX, {
            action:          'wpyogfleet_add_payment',
            nonce:           NONCE,
            booking_id:      bookingId,
            payment_date:    $('#p-date').val(),
            amount:          amount,
            payment_mode:    $('#p-mode').val(),
            received_by:     $('#p-received-by').val(),
            transaction_ref: $('#p-ref').val(),
            notes:           $('#p-notes').val(),
        }, function(res) {
            if (res.success) {
                $('#jtb-payments-area').html(res.data.payments_html);
                $('#jtb-summary-area').html(res.data.summary_html);
                closeModal('jtb-payment-modal');
                showToast('Payment saved successfully.');
            } else {
                alert(res.data.message || 'Error saving payment.');
            }
        }).fail(function() {
            alert('Network error. Please try again.');
        }).always(function() {
            $btn.prop('disabled', false).text('Save Payment');
        });
    });

    /* ------------------------------------------------------------------ */
    /* DELETE PAYMENT                                                       */
    /* ------------------------------------------------------------------ */

    $(document).on('click', '.jtb-delete-payment', function() {
        if (!confirm('Delete this payment? This cannot be undone.')) return;
        var $btn      = $(this);
        var paymentId = $btn.data('id');
        var bookingId = $btn.data('booking');

        $.post(AJAX, {
            action:     'wpyogfleet_delete_payment',
            nonce:      NONCE,
            payment_id: paymentId,
            booking_id: bookingId,
        }, function(res) {
            if (res.success) {
                $('#jtb-payments-area').html(res.data.payments_html);
                $('#jtb-summary-area').html(res.data.summary_html);
                showToast('Payment deleted.');
            } else {
                alert(res.data.message || 'Error deleting payment.');
            }
        });
    });

    /* ------------------------------------------------------------------ */
    /* ADD EXPENSE                                                          */
    /* ------------------------------------------------------------------ */

    // Show "other" text field when expense type is Other
    $(document).on('change', '#e-type', function() {
        var isOther = $(this).val() === 'other-expense';
        $('#e-type-other-wrap').toggle(isOther);
        if (isOther) $('#e-type-other').focus();
    });

    $(document).on('click', '#jtb-save-expense', function() {
        var $btn      = $(this);
        var bookingId = $btn.data('booking');
        var amount    = parseFloat($('#e-amount').val());
        var typeVal   = $('#e-type').val();
        var typeFinal = typeVal === 'other-expense' ? $('#e-type-other').val() : typeVal;

        if (!amount || amount <= 0) { alert('Please enter a valid amount.'); return; }
        if (!typeFinal)             { alert('Please select an expense type.'); return; }

        $btn.prop('disabled', true).text('Saving…');

        $.post(AJAX, {
            action:       'wpyogfleet_add_expense',
            nonce:        NONCE,
            booking_id:   bookingId,
            expense_date: $('#e-date').val(),
            expense_type: typeFinal,
            amount:       amount,
            payment_mode: $('#e-mode').val(),
            paid_to:      $('#e-paid-to').val(),
            paid_by:      $('#e-paid-by').val(),
            notes:        $('#e-notes').val(),
        }, function(res) {
            if (res.success) {
                $('#jtb-expenses-area').html(res.data.expenses_html);
                $('#jtb-summary-area').html(res.data.summary_html);
                closeModal('jtb-expense-modal');
                showToast('Expense saved successfully.');
            } else {
                alert(res.data.message || 'Error saving expense.');
            }
        }).fail(function() {
            alert('Network error. Please try again.');
        }).always(function() {
            $btn.prop('disabled', false).text('Save Expense');
        });
    });

    /* ------------------------------------------------------------------ */
    /* DELETE EXPENSE                                                       */
    /* ------------------------------------------------------------------ */

    $(document).on('click', '.jtb-delete-expense', function() {
        if (!confirm('Delete this expense? This cannot be undone.')) return;
        var $btn      = $(this);
        var expenseId = $btn.data('id');
        var bookingId = $btn.data('booking');

        $.post(AJAX, {
            action:     'wpyogfleet_delete_expense',
            nonce:      NONCE,
            expense_id: expenseId,
            booking_id: bookingId,
        }, function(res) {
            if (res.success) {
                $('#jtb-expenses-area').html(res.data.expenses_html);
                $('#jtb-summary-area').html(res.data.summary_html);
                showToast('Expense deleted.');
            } else {
                alert(res.data.message || 'Error deleting expense.');
            }
        });
    });

    /* ------------------------------------------------------------------ */
    /* QUICK STATUS CHANGE                                                  */
    /* ------------------------------------------------------------------ */

    // Snapshot the current value before it changes, so we can revert the dropdown
    // if the server rejects the change (e.g. "Closed" blocked by a balance due).
    $(document).on('mousedown focus', '#jtb-status-select', function() {
        $(this).data('prev-status', $(this).val());
    });

    $(document).on('change', '#jtb-status-select', function() {
        var $sel       = $(this);
        var bookingId  = $sel.data('booking');
        var status     = $sel.val();
        var prevStatus = $sel.data('prev-status') || status;

        $.post(AJAX, {
            action:     'wpyogfleet_update_booking_status',
            nonce:      NONCE,
            booking_id: bookingId,
            status:     status,
        }, function(res) {
            if (res.success) {
                $sel.data('prev-status', status);
                showToast('Status updated to: ' + status);
                // Update badge if present
                var badgeMap = {
                    'new':       ['New',       'badge-blue'],
                    'confirmed': ['Confirmed', 'badge-indigo'],
                    'ongoing':   ['Ongoing',   'badge-amber'],
                    'completed': ['Completed', 'badge-green'],
                    'closed':    ['Closed',    'badge-teal'],
                    'cancelled': ['Cancelled', 'badge-red'],
                };
                var info = badgeMap[status] || ['', 'badge-gray'];
                $('.jtb-page-title-group .jtb-badge')
                    .attr('class', 'jtb-badge ' + info[1])
                    .text(info[0]);

                // Ask for a Google review the first time a trip is marked Completed
                if (status === 'completed' && res.data.old_status !== 'completed' && res.data.booking) {
                    openReviewPopup(res.data.booking);
                }
                if (res.data.booking) updateReviewBadge(res.data.booking);
            } else {
                $sel.val(prevStatus);
                alert(res.data.message || 'Error updating status.');
            }
        });
    });

    /* ------------------------------------------------------------------ */
    /* GOOGLE REVIEW REQUEST POPUP                                          */
    /* ------------------------------------------------------------------ */

    function openReviewPopup(booking) {
        var $modal = $('#jtb-review-modal');
        if (!$modal.length) return;
        var name   = booking.passenger_name || booking.customer_name || '';
        var mobile = booking.passenger_mobile || booking.customer_mobile || '';
        var text   = (window.WPYOGFLEET_REVIEW_DATA && window.WPYOGFLEET_REVIEW_DATA.text) || '';

        $('#jtb-review-cust-name').text(name || '—');
        $('#jtb-review-cust-mobile').text(mobile || '—');
        $('#jtb-review-message').val(text);
        $('#jtb-review-sent-check').prop('checked', !!parseInt(booking.review_request_sent, 10));

        var phone = (mobile || '').replace(/\D/g, '');
        if (phone && !/^91/.test(phone) && phone.length === 10) phone = '91' + phone;
        $('#jtb-review-wa-btn').attr('href', phone
            ? ('https://api.whatsapp.com/send/?phone=' + phone + '&text=' + encodeURIComponent($('#jtb-review-message').val()) + '&type=phone_number&app_absent=0')
            : '#');

        $modal.data('booking-id', booking.id).addClass('is-open');
    }

    // Keep the WhatsApp link's text in sync if staff edits the message before sending
    $(document).on('input', '#jtb-review-message', function() {
        var $btn = $('#jtb-review-wa-btn');
        var href = $btn.attr('href');
        if (!href || href === '#') return;
        var base = href.split('&text=')[0];
        $btn.attr('href', base + '&text=' + encodeURIComponent($(this).val()) + '&type=phone_number&app_absent=0');
    });

    $(document).on('change', '#jtb-review-sent-check', function() {
        var $chk       = $(this);
        var bookingId  = $('#jtb-review-modal').data('booking-id');
        var checked    = $chk.is(':checked');
        $.post(AJAX, {
            action:     'wpyogfleet_mark_review_sent',
            nonce:      NONCE,
            booking_id: bookingId,
            sent:       checked ? 1 : 0,
        }, function(res) {
            if (res.success) {
                showToast(checked ? 'Review request marked as sent.' : 'Review request marked as pending.');
                if (res.data.booking) updateReviewBadge(res.data.booking);
            } else {
                $chk.prop('checked', !checked);
                alert((res.data && res.data.message) || 'Could not update.');
            }
        });
    });

    function updateReviewBadge(booking) {
        var isDone = booking.status === 'completed' || booking.status === 'closed';
        var $badge = $('.jtb-review-badge[data-booking="' + booking.id + '"]');
        if (!$badge.length) {
            // Booking just transitioned into completed/closed on this page load — the
            // badge wasn't rendered server-side yet, so add it now if it's needed.
            if (!isDone) return;
            $badge = $('<span class="jtb-badge jtb-review-badge" data-booking="' + booking.id + '" style="margin-left:6px"></span>');
            $('.jtb-page-title-group').append($badge);
        }
        if (!isDone) { $badge.hide(); return; }
        $badge.show();
        if (parseInt(booking.review_request_sent, 10)) {
            $badge.removeClass('badge-amber').addClass('badge-green').html('✅ Review Sent');
        } else {
            $badge.removeClass('badge-green').addClass('badge-amber').html('⚠️ Review Pending');
        }
    }
    window.updateReviewBadge = updateReviewBadge;

    /* ------------------------------------------------------------------ */
    /* TOAST NOTIFICATION                                                   */
    /* ------------------------------------------------------------------ */

    function showToast(msg, type) {
        type = type || 'success';
        var $t = $('<div class="jtb-toast jtb-toast-' + type + '">' + msg + '</div>');
        if (!$('#jtb-toast-container').length) {
            $('body').append('<div id="jtb-toast-container"></div>');
        }
        $('#jtb-toast-container').append($t);
        setTimeout(function() { $t.addClass('is-visible'); }, 10);
        setTimeout(function() {
            $t.removeClass('is-visible');
            setTimeout(function() { $t.remove(); }, 400);
        }, 3000);
    }

    /* ------------------------------------------------------------------ */
    /* AUTO-DISMISS NOTICES                                                 */
    /* ------------------------------------------------------------------ */

    setTimeout(function() {
        $('.jtb-notice').fadeOut(400);
    }, 4000);

    /* ------------------------------------------------------------------ */
    /* CALENDAR VIEW                                                        */
    /* ------------------------------------------------------------------ */

    var JTBCal = {
        MONTHS: ['January','February','March','April','May','June',
                 'July','August','September','October','November','December'],
        DAYS:   ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'],
        STATUS_LABEL: { new:'New', confirmed:'Confirmed', ongoing:'Ongoing', completed:'Completed', closed:'Closed', cancelled:'Cancelled' },

        // Public: create a calendar inside containerId
        init: function(containerId, adminUrl) {
            var self = this;
            self.container = document.getElementById(containerId);
            if (!self.container) return;
            self.adminUrl = adminUrl || (WPYOGFLEET.admin_url + 'admin.php');
            self.today    = new Date();
            self.curYear  = self.today.getFullYear();
            self.curMonth = self.today.getMonth() + 1; // 1-12
            self.events   = {};   // keyed by 'YYYY-MM-DD'
            self.loaded   = {};   // keyed by 'YYYY-MM'

            // Build popup overlay (once per page)
            if (!document.getElementById('jtb-cal-popup-overlay')) {
                var ov = document.createElement('div');
                ov.id        = 'jtb-cal-popup-overlay';
                ov.className = 'jtb-cal-popup-overlay';
                ov.addEventListener('click', function() { self.closePopup(); });
                document.body.appendChild(ov);

                var pop = document.createElement('div');
                pop.id        = 'jtb-cal-popup';
                pop.className = 'jtb-cal-popup';
                pop.innerHTML = '<div class="jtb-cal-popup__header">'
                              + '<h3 class="jtb-cal-popup__title" id="jtb-cal-popup-title"></h3>'
                              + '<button class="jtb-cal-popup__close" id="jtb-cal-popup-close">✕</button>'
                              + '</div>'
                              + '<div class="jtb-cal-popup__body" id="jtb-cal-popup-body"></div>';
                document.body.appendChild(pop);
                document.getElementById('jtb-cal-popup-close').addEventListener('click', function() { self.closePopup(); });
            }

            self.render();
            self.loadMonth(self.curYear, self.curMonth);
        },

        render: function() {
            var self = this;
            var html = '<div class="jtb-cal-nav">'
                     + '<button class="jtb-cal-nav__btn" id="' + self.container.id + '-prev">&#8592;</button>'
                     + '<button class="jtb-cal-today-btn" id="' + self.container.id + '-today">Today</button>'
                     + '<span class="jtb-cal-nav__month" id="' + self.container.id + '-label"></span>'
                     + '<button class="jtb-cal-nav__btn" id="' + self.container.id + '-next">&#8594;</button>'
                     + '</div>'
                     + '<div class="jtb-cal-grid" id="' + self.container.id + '-grid"></div>'
                     + '<div class="jtb-cal-legend">'
                     + self.legendHtml()
                     + '</div>';
            self.container.innerHTML = html;

            document.getElementById(self.container.id + '-prev').addEventListener('click', function() { self.navigate(-1); });
            document.getElementById(self.container.id + '-next').addEventListener('click', function() { self.navigate(1); });
            document.getElementById(self.container.id + '-today').addEventListener('click', function() {
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

            var key = self.monthKey(self.curYear, self.curMonth);
            var isLoading = !self.loaded[key];

            // Day headers
            var html = self.DAYS.map(function(d) {
                return '<div class="jtb-cal-header-cell">' + d + '</div>';
            }).join('');

            // Calendar cells
            var firstDay  = new Date(self.curYear, self.curMonth - 1, 1).getDay(); // 0=Sun
            var daysInMonth = new Date(self.curYear, self.curMonth, 0).getDate();
            var daysInPrev  = new Date(self.curYear, self.curMonth - 1, 0).getDate();
            var todayStr    = self.dateStr(self.today);

            // Leading blanks from prev month
            for (var i = firstDay - 1; i >= 0; i--) {
                var d = daysInPrev - i;
                var prevMonth = self.curMonth - 1 < 1 ? 12 : self.curMonth - 1;
                var prevYear  = prevMonth === 12 ? self.curYear - 1 : self.curYear;
                var dstr      = self.dateStr(new Date(prevYear, prevMonth - 1, d));
                html += self.cellHtml(d, dstr, true, todayStr, isLoading);
            }
            // Current month days
            for (var day = 1; day <= daysInMonth; day++) {
                var dstr2 = self.dateStr(new Date(self.curYear, self.curMonth - 1, day));
                html += self.cellHtml(day, dstr2, false, todayStr, isLoading);
            }
            // Trailing blanks for next month
            var totalCells = Math.ceil((firstDay + daysInMonth) / 7) * 7;
            var trailing   = totalCells - firstDay - daysInMonth;
            for (var t = 1; t <= trailing; t++) {
                var nextMonth = self.curMonth + 1 > 12 ? 1 : self.curMonth + 1;
                var nextYear  = nextMonth === 1 ? self.curYear + 1 : self.curYear;
                var dstr3     = self.dateStr(new Date(nextYear, nextMonth - 1, t));
                html += self.cellHtml(t, dstr3, true, todayStr, isLoading);
            }

            grid.innerHTML = html;

            // Attach click handlers to cells
            grid.querySelectorAll('.jtb-cal-cell.has-events').forEach(function(cell) {
                cell.addEventListener('click', function() {
                    self.openPopup(cell.getAttribute('data-date'));
                });
            });
            grid.querySelectorAll('.jtb-cal-event-chip, .jtb-cal-more').forEach(function(chip) {
                chip.addEventListener('click', function(e) {
                    e.stopPropagation();
                    var date = chip.closest('[data-date]').getAttribute('data-date');
                    self.openPopup(date);
                });
            });
        },

        cellHtml: function(day, dateStr, otherMonth, todayStr, isLoading) {
            var self    = this;
            var evList  = self.events[dateStr] || [];
            var classes = 'jtb-cal-cell';
            if (otherMonth)           classes += ' other-month';
            if (dateStr === todayStr) classes += ' is-today';
            if (evList.length > 0)    classes += ' has-events';

            var inner = '<div class="jtb-cal-day-num">' + day + '</div>';
            if (isLoading) {
                // Placeholder — will re-render after load
                inner += '<div class="jtb-cal-events"></div>';
            } else {
                inner += '<div class="jtb-cal-events">';
                var maxShow = 3;
                for (var i = 0; i < Math.min(evList.length, maxShow); i++) {
                    var ev       = evList[i];
                    var evName   = ev.passenger_name || ev.customer;
                    var evVeh    = ev.reg_number || ev.vehicle;
                    var evTitle  = evName + (evVeh ? ' · ' + evVeh : '') + ' · ' + ev.route;
                    var chipText = (evVeh ? evVeh + ' · ' : '') + evName;
                    inner += '<div class="jtb-cal-event-chip status-' + ev.status + '" title="' + self.esc(evTitle) + '">'
                           + self.esc(chipText)
                           + '</div>';
                }
                if (evList.length > maxShow) {
                    inner += '<div class="jtb-cal-more">+' + (evList.length - maxShow) + ' more</div>';
                }
                inner += '</div>';
            }
            return '<div class="' + classes + '" data-date="' + dateStr + '">' + inner + '</div>';
        },

        loadMonth: function(year, month) {
            var self = this;
            var key  = self.monthKey(year, month);
            if (self.loaded[key]) return;

            $.post(AJAX, {
                action: 'wpyogfleet_get_calendar_bookings',
                nonce:  NONCE,
                year:   year,
                month:  month
            }, function(res) {
                if (!res.success) return;
                self.loaded[key] = true;
                res.data.events.forEach(function(ev) {
                    var d = ev.date;
                    if (!self.events[d]) self.events[d] = [];
                    self.events[d].push(ev);
                });
                // Re-render grid to show events
                if (self.curYear === year && self.curMonth === month) {
                    self.renderGrid();
                }
            });
        },

        openPopup: function(dateStr) {
            var self   = this;
            var evList = self.events[dateStr] || [];
            var parts  = dateStr.split('-');
            var label  = parseInt(parts[2]) + ' ' + self.MONTHS[parseInt(parts[1]) - 1] + ' ' + parts[0];

            document.getElementById('jtb-cal-popup-title').textContent = label;

            var body = '';
            if (evList.length === 0) {
                body = '<p style="color:#94a3b8;font-size:13px;text-align:center;padding:20px 0">No bookings on this day.</p>';
            } else {
                evList.forEach(function(ev) {
                    var url     = self.adminUrl + '?page=wpyogfleet-bookings&action=view&id=' + ev.id;
                    var popName = ev.passenger_name || ev.customer;
                    var popVeh  = ev.reg_number
                                    ? ev.reg_number + (ev.vehicle ? ' (' + ev.vehicle + ')' : '')
                                    : ev.vehicle;
                    body += '<a href="' + url + '" class="jtb-cal-popup-item status-' + ev.status + '">'
                          + '<div class="jtb-cal-popup-item__num">' + self.esc(ev.number) + ' · ' + self.STATUS_LABEL[ev.status] + '</div>'
                          + '<div class="jtb-cal-popup-item__name">' + self.esc(popName) + '</div>'
                          + '<div class="jtb-cal-popup-item__meta">'
                          +   self.esc(ev.route) + '<br>'
                          +   (popVeh ? self.esc(popVeh) : '') + (ev.driver ? ' · ' + self.esc(ev.driver) : '')
                          + '</div>'
                          + '</a>';
                });
            }
            document.getElementById('jtb-cal-popup-body').innerHTML = body;

            document.getElementById('jtb-cal-popup').classList.add('is-open');
            document.getElementById('jtb-cal-popup-overlay').classList.add('is-open');
        },

        closePopup: function() {
            var el = document.getElementById('jtb-cal-popup');
            var ov = document.getElementById('jtb-cal-popup-overlay');
            if (el) el.classList.remove('is-open');
            if (ov) ov.classList.remove('is-open');
        },

        legendHtml: function() {
            var items = [
                { cls: 'status-new',       label: 'New' },
                { cls: 'status-confirmed', label: 'Confirmed' },
                { cls: 'status-ongoing',   label: 'Ongoing' },
                { cls: 'status-completed', label: 'Completed' },
                { cls: 'status-cancelled', label: 'Cancelled' },
            ];
            return items.map(function(it) {
                return '<div class="jtb-cal-legend-item">'
                     + '<div class="jtb-cal-legend-dot jtb-cal-event-chip ' + it.cls + '" style="width:12px;height:12px;display:inline-block"></div>'
                     + '<span>' + it.label + '</span>'
                     + '</div>';
            }).join('');
        },

        monthKey: function(y, m) { return y + '-' + (m < 10 ? '0' + m : m); },
        dateStr:  function(d) {
            return d.getFullYear() + '-'
                 + String(d.getMonth() + 1).padStart(2,'0') + '-'
                 + String(d.getDate()).padStart(2,'0');
        },
        esc: function(s) {
            return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        },
    };

    // ── Dashboard calendar ───────────────────────────────────────────────────
    if (document.getElementById('jtb-dash-calendar')) {
        JTBCal.init('jtb-dash-calendar', WPYOGFLEET.admin_url + 'admin.php');
    }

    // ── Bookings page calendar toggle ────────────────────────────────────────
    var $viewToggle   = $('#jtb-view-toggle');
    var $listFilters  = $('#jtb-list-filters');
    var $listTable    = $('#jtb-list-table');
    var $listPagWrap  = $('#jtb-list-pagination-wrap');
    var $calView      = $('#jtb-cal-view');
    var bookingsCalInited = false;

    // Check URL param to auto-open calendar view
    if ($viewToggle.length && window.location.search.indexOf('view=calendar') !== -1) {
        activateCalendarView();
    }

    $viewToggle.on('click', '.jtb-view-toggle__btn', function() {
        var view = $(this).data('view');
        $viewToggle.find('.jtb-view-toggle__btn').removeClass('is-active');
        $(this).addClass('is-active');
        if (view === 'calendar') {
            activateCalendarView();
        } else {
            $listFilters.show();
            $listTable.show();
            $listPagWrap.show();
            $calView.hide();
        }
    });

    function activateCalendarView() {
        $listFilters.hide();
        $listTable.hide();
        $listPagWrap.hide();
        $calView.show();
        $viewToggle.find('[data-view="list"]').removeClass('is-active');
        $viewToggle.find('[data-view="calendar"]').addClass('is-active');
        if (!bookingsCalInited) {
            bookingsCalInited = true;
            // Need a fresh instance — copy proto and init
            var bc = Object.create(JTBCal);
            bc.events = {};
            bc.loaded = {};
            bc.init('wpyogfleet-bookings-calendar', WPYOGFLEET.admin_url + 'admin.php');
        }
    }

    /* ------------------------------------------------------------------ */
    /* FILTER FORM — clear individual selects on double-click              */
    /* ------------------------------------------------------------------ */

    $(document).ready(function() {
        // Add toast container styles dynamically
        if (!document.getElementById('jtb-toast-styles')) {
            var style = document.createElement('style');
            style.id  = 'jtb-toast-styles';
            style.textContent = `
                #jtb-toast-container {
                    position: fixed;
                    bottom: 24px;
                    right: 24px;
                    z-index: 999999;
                    display: flex;
                    flex-direction: column;
                    gap: 8px;
                }
                .jtb-toast {
                    padding: 10px 16px;
                    border-radius: 6px;
                    font-size: 13px;
                    font-weight: 500;
                    box-shadow: 0 4px 12px rgba(0,0,0,.15);
                    opacity: 0;
                    transform: translateY(10px);
                    transition: all .25s ease;
                    max-width: 280px;
                }
                .jtb-toast.is-visible { opacity: 1; transform: translateY(0); }
                .jtb-toast-success { background: #15803d; color: #fff; }
                .jtb-toast-error   { background: #dc2626; color: #fff; }
            `;
            document.head.appendChild(style);
        }
    });

    /* ------------------------------------------------------------------ */
    /* BOOKING FORM — advance payment details show/hide                   */
    /* ------------------------------------------------------------------ */
    var $advAmt     = $('#jtb-advance-amount');
    var $advDetails = $('#jtb-advance-details');
    if ($advAmt.length && $advDetails.length) {
        function toggleAdvDetails() {
            var val = parseFloat($advAmt.val()) || 0;
            if (val > 0) {
                $advDetails.slideDown(150);
            } else {
                $advDetails.slideUp(150);
            }
        }
        $advAmt.on('input change', toggleAdvDetails);
        // Run on page load in case editing an existing booking
        toggleAdvDetails();
    }

})(jQuery);
