<?php if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local vars only: this file is exclusively include()'d inside WPYOGFLEET_Admin::render(), so these are function-scoped, not real globals.
$is_edit   = ! empty( $booking );
$title     = $is_edit ? 'Edit Booking — ' . esc_html( $booking->booking_number ) : 'New Booking';
$vehicles  = WPYOGFLEET_DB::get_vehicles( array( 'status' => 'active' ) );
$drivers   = WPYOGFLEET_DB::get_drivers( array( 'status' => 'active' ) );
$companies = WPYOGFLEET_DB::get_companies( array( 'status' => 'active' ) );
$back_url  = $is_edit
    ? admin_url( 'admin.php?page=wpyogfleet-bookings&action=view&id=' . $booking->id )
    : admin_url( 'admin.php?page=wpyogfleet-bookings' );

$btype = $booking->booking_type ?? 'individual';
?>

<div class="jtb-wrap">
    <div class="jtb-page-header">
        <div class="jtb-page-header__left">
            <a href="<?php echo esc_url( $back_url ); ?>" class="jtb-back-link">← Back</a>
            <h1 class="jtb-page-title"><?php echo esc_html( $title ); ?></h1>
        </div>
    </div>

    <form method="POST" class="jtb-form">
        <?php wp_nonce_field( 'wpyogfleet_booking_save' ); ?>
        <?php if ( $is_edit ) : ?>
        <input type="hidden" name="id" value="<?php echo esc_html( $booking->id ); ?>">
        <?php endif; ?>

        <div class="jtb-form-grid">

            <!-- LEFT COLUMN -->
            <div class="jtb-form-col">

                <!-- Customer / Booking Info -->
                <div class="jtb-card">
                    <div class="jtb-card__header">
                        <h2 class="jtb-card__title">Customer Details</h2>
                    </div>
                    <div class="jtb-card__body">

                        <!-- Booking Type Toggle -->
                        <div class="jtb-field">
                            <label class="jtb-label">Booking Type</label>
                            <div class="jtb-radio-group jtb-booking-type-group">
                                <label class="jtb-radio-label <?php echo esc_attr( $btype === 'individual' ? 'is-selected' : '' ); ?>">
                                    <input type="radio" name="booking_type" value="individual"
                                        <?php checked( $btype, 'individual' ); ?>> Individual
                                </label>
                                <label class="jtb-radio-label <?php echo esc_attr( $btype === 'corporate' ? 'is-selected' : '' ); ?>">
                                    <input type="radio" name="booking_type" value="corporate"
                                        <?php checked( $btype, 'corporate' ); ?>> Corporate
                                </label>
                            </div>
                        </div>

                        <!-- INDIVIDUAL FIELDS (shown for individual) -->
                        <div id="jtb-individual-fields" <?php echo $btype === 'corporate' ? 'style="display:none"' : ''; ?>>
                            <div class="jtb-field-row">
                                <div class="jtb-field">
                                    <label class="jtb-label">Customer Name <span class="jtb-req">*</span></label>
                                    <input type="text" name="customer_name" class="jtb-input" id="jtb-customer-name"
                                        value="<?php echo esc_attr( $booking->customer_name ?? '' ); ?>"
                                        placeholder="Full name">
                                </div>
                                <div class="jtb-field">
                                    <label class="jtb-label">Mobile <span class="jtb-req">*</span></label>
                                    <input type="tel" name="customer_mobile" class="jtb-input" id="jtb-customer-mobile"
                                        value="<?php echo esc_attr( $booking->customer_mobile ?? '' ); ?>"
                                        placeholder="10-digit mobile">
                                </div>
                            </div>
                        </div>

                        <!-- CORPORATE FIELDS (shown for corporate) -->
                        <div id="jtb-corporate-fields" <?php echo $btype !== 'corporate' ? 'style="display:none"' : ''; ?>>
                            <div class="jtb-field">
                                <label class="jtb-label">Company <span class="jtb-req">*</span></label>
                                <select name="company_id" class="jtb-select" id="jtb-company-select">
                                    <option value="">— Select Company —</option>
                                    <?php foreach ( $companies as $co ) : ?>
                                    <option value="<?php echo esc_html( $co->id ); ?>"
                                        data-contact="<?php echo esc_attr( $co->contact_name ); ?>"
                                        data-mobile="<?php echo esc_attr( $co->contact_mobile ); ?>"
                                        data-name="<?php echo esc_attr( $co->company_name ); ?>"
                                        <?php selected( ( $booking->company_id ?? 0 ), $co->id ); ?>>
                                        <?php echo esc_html( $co->company_name ); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpyogfleet-companies' ) ); ?>" class="jtb-field-link" target="_blank">Manage Companies ↗</a>
                            </div>
                            <div class="jtb-field">
                                <label class="jtb-label">Booking ID / PO Reference</label>
                                <input type="text" name="booking_ref" class="jtb-input"
                                    value="<?php echo esc_attr( $booking->booking_ref ?? '' ); ?>"
                                    placeholder="Client's PO / reference number">
                            </div>
                            <div class="jtb-field-row">
                                <div class="jtb-field">
                                    <label class="jtb-label">Booked By</label>
                                    <input type="text" name="booked_by_name" class="jtb-input" id="jtb-booked-by-name"
                                        value="<?php echo esc_attr( $booking->booked_by_name ?? '' ); ?>"
                                        placeholder="Auto-filled from company">
                                </div>
                                <div class="jtb-field">
                                    <label class="jtb-label">Booked By Mobile</label>
                                    <input type="tel" name="booked_by_mobile" class="jtb-input" id="jtb-booked-by-mobile"
                                        value="<?php echo esc_attr( $booking->booked_by_mobile ?? '' ); ?>"
                                        placeholder="Auto-filled from company">
                                </div>
                            </div>
                            <div class="jtb-field-row">
                                <div class="jtb-field">
                                    <label class="jtb-label">Passenger Name</label>
                                    <input type="text" name="passenger_name" class="jtb-input" id="jtb-passenger-name"
                                        value="<?php echo esc_attr( $booking->passenger_name ?? '' ); ?>"
                                        placeholder="Name of the traveller">
                                </div>
                                <div class="jtb-field">
                                    <label class="jtb-label">Passenger Mobile</label>
                                    <input type="tel" name="passenger_mobile" class="jtb-input" id="jtb-passenger-mobile"
                                        value="<?php echo esc_attr( $booking->passenger_mobile ?? '' ); ?>"
                                        placeholder="Traveller's phone number">
                                </div>
                            </div>
                            <!-- Hidden corp_customer_name / corp_customer_mobile for corporate (set via JS) -->
                            <input type="hidden" id="jtb-corp-customer-name"   name="corp_customer_name"   value="<?php echo esc_attr( $booking->customer_name ?? '' ); ?>">
                            <input type="hidden" id="jtb-corp-customer-mobile" name="corp_customer_mobile" value="<?php echo esc_attr( $booking->customer_mobile ?? '' ); ?>">
                        </div>

                        <!-- EMAIL (always visible) -->
                        <div class="jtb-field" style="margin-top:12px">
                            <label class="jtb-label">Email Address</label>
                            <input type="email" name="customer_email" class="jtb-input"
                                value="<?php echo esc_attr( $booking->customer_email ?? '' ); ?>"
                                placeholder="email@example.com">
                        </div>

                    </div>
                </div>

                <!-- Trip Details -->
                <div class="jtb-card">
                    <div class="jtb-card__header">
                        <h2 class="jtb-card__title">Trip Details</h2>
                    </div>
                    <div class="jtb-card__body">
                        <div class="jtb-field">
                            <label class="jtb-label">Trip Type</label>
                            <select name="trip_type" class="jtb-select">
                                <?php
                                $types = array( 'one-way' => 'One Way', 'round-trip' => 'Round Trip', 'local' => 'Local', 'outstation' => 'Outstation', 'yatra' => 'Yatra / Package', 'other' => 'Other' );
                                foreach ( $types as $val => $label ) :
                                ?>
                                <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $booking->trip_type ?? 'one-way', $val ); ?>><?php echo esc_attr( $label ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="jtb-field-row">
                            <div class="jtb-field">
                                <label class="jtb-label">Pickup Location</label>
                                <input type="text" name="pickup_location" class="jtb-input"
                                    value="<?php echo esc_attr( $booking->pickup_location ?? '' ); ?>"
                                    placeholder="City / Address">
                            </div>
                            <div class="jtb-field">
                                <label class="jtb-label">Drop Location</label>
                                <input type="text" name="drop_location" class="jtb-input"
                                    value="<?php echo esc_attr( $booking->drop_location ?? '' ); ?>"
                                    placeholder="City / Address">
                            </div>
                        </div>
                        <?php
                        // Point 12: split datetime into date + optional time
                        $sd_date = $booking && $booking->start_date ? gmdate( 'Y-m-d', strtotime( $booking->start_date ) ) : '';
                        $sd_time = $booking && $booking->start_date && gmdate( 'H:i', strtotime( $booking->start_date ) ) !== '00:00'
                                    ? gmdate( 'H:i', strtotime( $booking->start_date ) ) : '';
                        $ed_date = $booking && $booking->end_date ? gmdate( 'Y-m-d', strtotime( $booking->end_date ) ) : '';
                        $ed_time = $booking && $booking->end_date && gmdate( 'H:i', strtotime( $booking->end_date ) ) !== '00:00'
                                    ? gmdate( 'H:i', strtotime( $booking->end_date ) ) : '';
                        ?>
                        <div class="jtb-field-row">
                            <div class="jtb-field">
                                <label class="jtb-label">Start Date <span class="jtb-req">*</span></label>
                                <input type="date" name="start_date" id="jtb-start-date" class="jtb-input"
                                    value="<?php echo esc_attr( $sd_date ); ?>">
                            </div>
                            <div class="jtb-field">
                                <label class="jtb-label">Start Time <span class="jtb-field-optional">(optional)</span></label>
                                <input type="time" name="start_time" id="jtb-start-time" class="jtb-input"
                                    value="<?php echo esc_attr( $sd_time ); ?>">
                            </div>
                        </div>
                        <div class="jtb-field-row">
                            <div class="jtb-field">
                                <label class="jtb-label">End Date</label>
                                <input type="date" name="end_date" id="jtb-end-date" class="jtb-input"
                                    value="<?php echo esc_attr( $ed_date ); ?>">
                            </div>
                            <div class="jtb-field">
                                <label class="jtb-label">End Time <span class="jtb-field-optional">(optional)</span></label>
                                <input type="time" name="end_time" id="jtb-end-time" class="jtb-input"
                                    value="<?php echo esc_attr( $ed_time ); ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Notes -->
                <div class="jtb-card">
                    <div class="jtb-card__header">
                        <h2 class="jtb-card__title">Notes / Instructions</h2>
                    </div>
                    <div class="jtb-card__body">
                        <textarea name="notes" class="jtb-textarea" rows="4"
                            placeholder="Any special instructions, notes about the booking…"><?php echo esc_textarea( $booking->notes ?? '' ); ?></textarea>
                    </div>
                </div>

            </div>

            <!-- RIGHT COLUMN -->
            <div class="jtb-form-col">

                <!-- Vehicle & Driver -->
                <?php $is_out = ! empty( $booking->is_outsourced ); ?>
                <div class="jtb-card">
                    <div class="jtb-card__header">
                        <h2 class="jtb-card__title">Vehicle & Driver</h2>
                    </div>
                    <div class="jtb-card__body">
                        <div class="jtb-field">
                            <div class="jtb-radio-group jtb-source-group">
                                <label class="jtb-radio-label <?php echo esc_attr( ! $is_out ? 'is-selected' : '' ); ?>">
                                    <input type="radio" name="vehicle_source" value="fleet" <?php checked( ! $is_out ); ?>> Own Fleet
                                </label>
                                <label class="jtb-radio-label <?php echo esc_attr( $is_out ? 'is-selected' : '' ); ?>">
                                    <input type="radio" name="vehicle_source" value="outsourced" <?php checked( $is_out ); ?>> Outsourced
                                </label>
                            </div>
                        </div>

                        <div id="jtb-fleet-fields" <?php echo $is_out ? 'style="display:none"' : ''; ?>>
                            <div class="jtb-field">
                                <label class="jtb-label">Vehicle</label>
                                <select name="vehicle_id" class="jtb-select" id="jtb-vehicle-select">
                                    <option value="">— Select Vehicle —</option>
                                    <?php foreach ( $vehicles as $v ) : ?>
                                    <option value="<?php echo absint( $v->id ); ?>" <?php selected( ( $booking->vehicle_id ?? 0 ), $v->id ); ?>>
                                        <?php echo esc_html( $v->vehicle_name . ( $v->registration_number ? ' (' . $v->registration_number . ')' : '' ) ); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpyogfleet-vehicles' ) ); ?>" class="jtb-field-link" target="_blank">Manage Vehicles ↗</a>
                            </div>
                            <div class="jtb-field">
                                <label class="jtb-label">Driver</label>
                                <select name="driver_id" class="jtb-select">
                                    <option value="">— Select Driver —</option>
                                    <?php foreach ( $drivers as $d ) : ?>
                                    <option value="<?php echo absint( $d->id ); ?>" <?php selected( ( $booking->driver_id ?? 0 ), $d->id ); ?>>
                                        <?php echo esc_html( $d->driver_name . ( $d->mobile ? ' (' . $d->mobile . ')' : '' ) ); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpyogfleet-drivers' ) ); ?>" class="jtb-field-link" target="_blank">Manage Drivers ↗</a>
                            </div>
                        </div>

                        <div id="jtb-outsourced-fields" <?php echo $is_out ? '' : 'style="display:none"'; ?>>
                            <div class="jtb-field-row">
                                <div class="jtb-field">
                                    <label class="jtb-label">Driver Name</label>
                                    <input type="text" name="outsourced_driver_name" class="jtb-input" id="jtb-out-driver-name"
                                        value="<?php echo esc_attr( $booking->outsourced_driver_name ?? '' ); ?>" placeholder="Driver's name">
                                </div>
                                <div class="jtb-field">
                                    <label class="jtb-label">Driver Mobile <span style="color:#94a3b8;font-size:11px">(optional)</span></label>
                                    <input type="tel" name="outsourced_driver_mobile" class="jtb-input"
                                        value="<?php echo esc_attr( $booking->outsourced_driver_mobile ?? '' ); ?>" placeholder="10-digit number">
                                </div>
                            </div>
                            <div class="jtb-field">
                                <label class="jtb-label">Vehicle Number</label>
                                <input type="text" name="outsourced_vehicle_number" class="jtb-input" id="jtb-out-vehicle-number"
                                    value="<?php echo esc_attr( $booking->outsourced_vehicle_number ?? '' ); ?>" placeholder="e.g. JK02AB1234">
                            </div>
                            <p style="font-size:12px;color:#94a3b8;margin-top:4px">Not saved to your vehicle/driver database — used for this booking and its messages only.</p>
                        </div>
                    </div>
                </div>

                <!-- Payment Info -->
                <div class="jtb-card">
                    <div class="jtb-card__header">
                        <h2 class="jtb-card__title">Amount & Advance</h2>
                    </div>
                    <div class="jtb-card__body">
                        <div class="jtb-field-row">
                            <div class="jtb-field">
                                <label class="jtb-label">Total Amount (₹) <span class="jtb-req">*</span></label>
                                <input type="number" name="booking_amount" class="jtb-input jtb-input-currency" required min="0" step="0.01"
                                    value="<?php echo esc_attr( $booking->booking_amount ?? '' ); ?>"
                                    placeholder="0.00" id="jtb-booking-amount">
                            </div>
                            <div class="jtb-field">
                                <label class="jtb-label">Advance Received (₹)</label>
                                <input type="number" name="advance_amount" class="jtb-input jtb-input-currency" min="0" step="0.01"
                                    value="<?php echo esc_attr( $booking->advance_amount ?? '' ); ?>"
                                    placeholder="0.00" id="jtb-advance-amount">
                            </div>
                        </div>
                        <div class="jtb-balance-preview" id="jtb-balance-preview">
                            <span class="jtb-balance-label">Balance Due:</span>
                            <span class="jtb-balance-value" id="jtb-balance-value">₹0.00</span>
                        </div>
                        <!-- Advance payment details — shown when advance_amount > 0 -->
                        <div id="jtb-advance-details" style="<?php echo ( ! $is_edit || (float)( $booking->advance_amount ?? 0 ) <= 0 ) ? 'display:none;' : ''; ?>margin-top:12px;padding-top:12px;border-top:1px solid #e5e7eb">
                            <div class="jtb-field-row">
                                <div class="jtb-field">
                                    <label class="jtb-label">Advance Payment Mode</label>
                                    <select name="advance_payment_mode" class="jtb-select">
                                        <?php
                                        $modes  = array( 'cash' => 'Cash', 'upi' => 'UPI', 'bank-transfer' => 'Bank Transfer', 'card' => 'Card', 'other' => 'Other' );
                                        $cur_mode = $booking->advance_payment_mode ?? 'cash';
                                        foreach ( $modes as $v => $l ) :
                                        ?>
                                        <option value="<?php echo esc_attr( $v ); ?>" <?php selected( $cur_mode, $v ); ?>><?php echo esc_html( $l ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="jtb-field">
                                    <label class="jtb-label">Received By</label>
                                    <input type="text" name="advance_received_by" class="jtb-input"
                                        value="<?php echo esc_attr( $booking->advance_received_by ?? '' ); ?>"
                                        placeholder="Staff name">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Status -->
                <div class="jtb-card">
                    <div class="jtb-card__header">
                        <h2 class="jtb-card__title">Booking Status</h2>
                    </div>
                    <div class="jtb-card__body">
                        <div class="jtb-status-pills">
                            <?php
                            $statuses = array( 'new' => 'New', 'confirmed' => 'Confirmed', 'ongoing' => 'Ongoing', 'completed' => 'Completed', 'closed' => 'Closed', 'cancelled' => 'Cancelled' );
                            $current  = $booking->status ?? 'new';
                            foreach ( $statuses as $val => $label ) :
                            ?>
                            <label class="jtb-status-pill jtb-status-<?php echo esc_html( $val ); ?> <?php echo $current === $val ? 'is-active' : ''; ?>">
                                <input type="radio" name="status" value="<?php echo esc_attr( $val ); ?>" <?php checked( $current, $val ); ?>>
                                <?php echo esc_html( $label ); ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Submit -->
                <div class="jtb-form-actions">
                    <a href="<?php echo esc_url( $back_url ); ?>" class="jtb-btn jtb-btn-ghost">Cancel</a>
                    <button type="submit" class="jtb-btn jtb-btn-primary jtb-btn-lg">
                        <?php echo esc_html( $is_edit ? 'Save Changes' : 'Create Booking' ); ?>
                    </button>
                </div>

            </div>
        </div>
    </form>
</div>

<?php ob_start(); ?>
(function() {
    // ---- Balance calculator ----
    var amtEl = document.getElementById('jtb-booking-amount');
    var advEl = document.getElementById('jtb-advance-amount');
    var balEl = document.getElementById('jtb-balance-value');

    function updateBalance() {
        var amt = parseFloat(amtEl.value) || 0;
        var adv = parseFloat(advEl.value) || 0;
        var bal = amt - adv;
        balEl.textContent = '₹' + bal.toLocaleString('en-IN', {minimumFractionDigits:2, maximumFractionDigits:2});
        balEl.style.color = bal > 0 ? '#dc2626' : '#16a34a';
    }
    amtEl && amtEl.addEventListener('input', updateBalance);
    advEl && advEl.addEventListener('input', updateBalance);
    updateBalance();

    // ---- Status pill toggle ----
    document.querySelectorAll('.jtb-status-pill input').forEach(function(radio) {
        radio.addEventListener('change', function() {
            document.querySelectorAll('.jtb-status-pill').forEach(function(p){ p.classList.remove('is-active'); });
            radio.closest('.jtb-status-pill').classList.add('is-active');
        });
    });

    // ---- Date validation ----
    document.querySelector('form.jtb-form').addEventListener('submit', function(e) {
        var startVal = document.querySelector('[name="start_date"]').value;
        var endVal   = document.querySelector('[name="end_date"]').value;
        if (startVal && endVal && new Date(endVal) < new Date(startVal)) {
            e.preventDefault();
            alert('End date/time cannot be before start date/time.');
            document.querySelector('[name="end_date"]').focus();
        }
    });

    // ---- Booking type toggle ----
    var indFields  = document.getElementById('jtb-individual-fields');
    var corpFields = document.getElementById('jtb-corporate-fields');
    var nameInput  = document.getElementById('jtb-customer-name');    // individual name
    var mobileInput = document.getElementById('jtb-customer-mobile'); // individual mobile

    function switchBookingType(type) {
        var isCorpRate = type === 'corporate';
        indFields.style.display  = isCorpRate ? 'none' : '';
        corpFields.style.display = isCorpRate ? '' : 'none';

        // Toggle required attribute
        if (nameInput)   nameInput.required   = !isCorpRate;
        if (mobileInput) mobileInput.required = !isCorpRate;

        // Update pill selection style
        document.querySelectorAll('.jtb-booking-type-group .jtb-radio-label').forEach(function(lbl) {
            lbl.classList.remove('is-selected');
        });
        var checked = document.querySelector('[name="booking_type"]:checked');
        if (checked) checked.closest('.jtb-radio-label').classList.add('is-selected');
    }

    document.querySelectorAll('[name="booking_type"]').forEach(function(radio) {
        radio.addEventListener('change', function() {
            switchBookingType(this.value);
        });
    });

    // Init on page load
    var currentType = document.querySelector('[name="booking_type"]:checked');
    if (currentType) switchBookingType(currentType.value);

    // ---- Vehicle source toggle (own fleet vs outsourced) ----
    var fleetFields = document.getElementById('jtb-fleet-fields');
    var outFields   = document.getElementById('jtb-outsourced-fields');
    var outDriverNameInput = document.getElementById('jtb-out-driver-name');
    var outVehicleNumInput = document.getElementById('jtb-out-vehicle-number');

    function switchVehicleSource(source) {
        var isOut = source === 'outsourced';
        if (fleetFields) fleetFields.style.display = isOut ? 'none' : '';
        if (outFields)   outFields.style.display   = isOut ? '' : 'none';

        if (outDriverNameInput) outDriverNameInput.required = isOut;
        if (outVehicleNumInput) outVehicleNumInput.required = isOut;

        document.querySelectorAll('.jtb-source-group .jtb-radio-label').forEach(function(lbl) {
            lbl.classList.remove('is-selected');
        });
        var checkedSource = document.querySelector('[name="vehicle_source"]:checked');
        if (checkedSource) checkedSource.closest('.jtb-radio-label').classList.add('is-selected');
    }

    document.querySelectorAll('[name="vehicle_source"]').forEach(function(radio) {
        radio.addEventListener('change', function() {
            switchVehicleSource(this.value);
        });
    });

    var currentSource = document.querySelector('[name="vehicle_source"]:checked');
    if (currentSource) switchVehicleSource(currentSource.value);

    // ---- Company auto-fill ----
    var companySelect    = document.getElementById('jtb-company-select');
    var bookedByName     = document.getElementById('jtb-booked-by-name');
    var bookedByMobile   = document.getElementById('jtb-booked-by-mobile');
    var corpNameHidden   = document.getElementById('jtb-corp-customer-name');
    var corpMobileHidden = document.getElementById('jtb-corp-customer-mobile');

    if (companySelect) {
        companySelect.addEventListener('change', function() {
            var opt = this.options[this.selectedIndex];
            if (!opt || !opt.value) return;

            var contact = opt.getAttribute('data-contact') || '';
            var mobile  = opt.getAttribute('data-mobile')  || '';
            var name    = opt.getAttribute('data-name')    || '';

            // Only auto-fill if fields are empty (don't overwrite user edits)
            if (bookedByName && !bookedByName.value)     bookedByName.value   = contact;
            if (bookedByMobile && !bookedByMobile.value) bookedByMobile.value = mobile;

            // Always sync hidden customer_name / customer_mobile with company name & contact mobile
            if (corpNameHidden)   corpNameHidden.value   = name;
            if (corpMobileHidden) corpMobileHidden.value = mobile;
        });

        // Trigger on load if company already selected (edit mode)
        if (companySelect.value) {
            var opt = companySelect.options[companySelect.selectedIndex];
            if (opt) {
                if (corpNameHidden && !corpNameHidden.value)
                    corpNameHidden.value = opt.getAttribute('data-name') || '';
                if (corpMobileHidden && !corpMobileHidden.value)
                    corpMobileHidden.value = opt.getAttribute('data-mobile') || '';
            }
        }
    }
})();
<?php wp_add_inline_script( 'wpyogfleet-admin', ob_get_clean() ); ?>
