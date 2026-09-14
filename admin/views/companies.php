<?php if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local vars only: this file is exclusively include()'d inside WPYOGFLEET_Admin::render(), so these are function-scoped, not real globals.
$companies    = WPYOGFLEET_DB::get_companies( array( 'include_inactive' => true ) );
$is_edit      = ! empty( $edit_company );
?>

<div class="jtb-wrap">
    <div class="jtb-page-header">
        <div class="jtb-page-header__left">
            <h1 class="jtb-page-title">Companies</h1>
            <p class="jtb-page-subtitle"><?php echo absint( count( $companies ) ); ?> compan<?php echo absint( count( $companies ) ) !== 1 ? 'ies' : 'y'; ?> registered</p>
        </div>
    </div>

    <?php
    $wpyogfleet_notice_ok = isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wpyogfleet_notice' );
    ?>
    <?php if ( $wpyogfleet_notice_ok && ! empty( $_GET['saved'] ) ) : ?>
    <div class="jtb-notice jtb-notice-success">Company saved successfully.</div>
    <?php endif; ?>
    <?php if ( $wpyogfleet_notice_ok && ! empty( $_GET['deleted'] ) ) : ?>
    <div class="jtb-notice jtb-notice-success">Company deleted.</div>
    <?php endif; ?>
    <?php if ( $wpyogfleet_notice_ok && isset( $_GET['error'] ) && 'duplicate' === $_GET['error'] ) : ?>
    <div class="jtb-notice jtb-notice-error">A company with this name already exists.</div>
    <?php endif; ?>

    <div class="jtb-two-col-layout">

        <!-- Company List -->
        <div class="jtb-card">
            <div class="jtb-card__header"><h2 class="jtb-card__title">All Companies</h2></div>
            <div class="jtb-card__body jtb-card__body--flush">
                <?php if ( $companies ) : ?>
                <table class="jtb-table">
                    <thead>
                        <tr>
                            <th>Company</th>
                            <th>Contact Person</th>
                            <th>Mobile</th>
                            <th>GST No.</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $companies as $co ) : ?>
                        <tr data-co-id="<?php echo esc_html( $co->id ); ?>">
                            <td>
                                <strong><?php echo esc_html( $co->company_name ); ?></strong>
                                <?php if ( $co->contact_email ) echo '<div class="jtb-text-muted">'.esc_html($co->contact_email).'</div>'; ?>
                            </td>
                            <td><?php echo esc_html( $co->contact_name ?: '—' ); ?></td>
                            <td><?php echo esc_html( $co->contact_mobile ?: '—' ); ?></td>
                            <td><?php echo esc_html( $co->gst_number ?: '—' ); ?></td>
                            <td>
                                <span class="jtb-badge <?php echo esc_attr( $co->status === 'active' ? 'badge-green' : 'badge-gray' ); ?>">
                                    <?php echo esc_html( ucfirst( $co->status ) ); ?>
                                </span>
                            </td>
                            <td class="jtb-actions">
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpyogfleet-companies&action=edit&id=' . $co->id ) ); ?>"
                                   class="jtb-btn jtb-btn-xs jtb-btn-secondary">Edit</a>
                                <?php if ( current_user_can( 'wpyogfleet_admin' ) ) : ?>
                                <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=wpyogfleet-companies&action=delete&id=' . $co->id ), 'wpyogfleet_delete_company_' . $co->id ) ); ?>"
                                   class="jtb-btn jtb-btn-xs jtb-btn-danger"
                                   onclick="return confirm('Delete this company? This cannot be undone.')">Delete</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else : ?>
                <div class="jtb-empty-state">
                    <span class="dashicons dashicons-building"></span>
                    <p>No companies yet. Add your first company using the form.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Add / Edit Form -->
        <div class="jtb-card">
            <div class="jtb-card__header">
                <h2 class="jtb-card__title"><?php echo esc_html( $is_edit ? 'Edit Company' : 'Add Company' ); ?></h2>
                <?php if ( $is_edit ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpyogfleet-companies' ) ); ?>" class="jtb-link">Cancel</a>
                <?php endif; ?>
            </div>
            <div class="jtb-card__body">
                <div id="jtb-company-notice" style="display:none;margin-bottom:12px"></div>
                <form id="jtb-company-form" method="POST">
                    <input type="hidden" name="action" value="wpyogfleet_save_company">
                    <input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce('wpyogfleet_nonce') ); ?>">
                    <input type="hidden" name="company_id" id="jtb-company-id" value="<?php echo $is_edit ? absint( $edit_company->id ) : 0; ?>">

                    <div class="jtb-field">
                        <label class="jtb-label">Company Name *</label>
                        <input type="text" name="company_name" class="jtb-input" required
                            value="<?php echo esc_attr( $edit_company->company_name ?? '' ); ?>"
                            placeholder="e.g. Acme Pvt. Ltd.">
                    </div>

                    <div class="jtb-field-row">
                        <div class="jtb-field">
                            <label class="jtb-label">Contact Person</label>
                            <input type="text" name="contact_name" class="jtb-input"
                                value="<?php echo esc_attr( $edit_company->contact_name ?? '' ); ?>"
                                placeholder="Full name">
                        </div>
                        <div class="jtb-field">
                            <label class="jtb-label">Contact Mobile</label>
                            <input type="tel" name="contact_mobile" class="jtb-input"
                                value="<?php echo esc_attr( $edit_company->contact_mobile ?? '' ); ?>"
                                placeholder="Mobile number">
                        </div>
                    </div>

                    <div class="jtb-field">
                        <label class="jtb-label">Contact Email</label>
                        <input type="email" name="contact_email" class="jtb-input"
                            value="<?php echo esc_attr( $edit_company->contact_email ?? '' ); ?>"
                            placeholder="email@company.com">
                    </div>

                    <div class="jtb-field">
                        <label class="jtb-label">GST Number</label>
                        <input type="text" name="gst_number" class="jtb-input"
                            value="<?php echo esc_attr( $edit_company->gst_number ?? '' ); ?>"
                            placeholder="e.g. 22AAAAA0000A1Z5">
                    </div>

                    <div class="jtb-field">
                        <label class="jtb-label">Address</label>
                        <textarea name="address" class="jtb-textarea" rows="2"
                            placeholder="Office address"><?php echo esc_textarea( $edit_company->address ?? '' ); ?></textarea>
                    </div>

                    <div class="jtb-field">
                        <label class="jtb-label">Notes</label>
                        <textarea name="notes" class="jtb-textarea" rows="2"><?php echo esc_textarea( $edit_company->notes ?? '' ); ?></textarea>
                    </div>

                    <div class="jtb-field">
                        <label class="jtb-label">Status</label>
                        <select name="status" class="jtb-select">
                            <option value="active"   <?php selected( $edit_company->status ?? 'active', 'active' ); ?>>Active</option>
                            <option value="inactive" <?php selected( $edit_company->status ?? '', 'inactive' ); ?>>Inactive</option>
                        </select>
                    </div>

                    <div class="jtb-form-actions">
                        <button type="submit" class="jtb-btn jtb-btn-primary jtb-btn-block">
                            <?php echo esc_html( $is_edit ? 'Update Company' : 'Add Company' ); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div><!-- .jtb-two-col-layout -->
</div><!-- .jtb-wrap -->

<?php ob_start(); ?>
(function ($) {
    // ── AJAX form submit ──────────────────────────────────────────
    $('#jtb-company-form').on('submit', function (e) {
        e.preventDefault();
        var $btn  = $(this).find('[type="submit"]');
        var $note = $('#jtb-company-notice');
        $btn.prop('disabled', true).text('Saving…');

        $.post(WPYOGFLEET.ajax_url, $(this).serializeArray(), function (res) {
            $btn.prop('disabled', false).text($('#jtb-company-id').val() > 0 ? 'Update Company' : 'Add Company');
            if (!res.success) {
                showNotice('error', res.data && res.data.message ? res.data.message : 'Save failed.');
                return;
            }
            showNotice('success', res.data.action === 'created' ? 'Company added.' : 'Company updated.');
            var co = res.data.company;

            if (res.data.action === 'created') {
                // Append new row to table; remove empty-state if present
                var $tbody = $('.jtb-table tbody');
                if (!$tbody.length) { location.reload(); return; }
                var rowHtml = buildRow(co);
                $tbody.prepend(rowHtml);
                // Reset form
                $('#jtb-company-id').val(0);
                $('#jtb-company-form')[0].reset();
                updateSubtitle(1);
            } else {
                // Update existing row in-place
                var $row = $('[data-co-id="' + co.id + '"]');
                if ($row.length) $row.replaceWith(buildRow(co));
            }
        }).fail(function () {
            $btn.prop('disabled', false);
            showNotice('error', 'Network error. Please try again.');
        });
    });

    // ── Edit button → populate form ───────────────────────────────
    $(document).on('click', '.jtb-co-edit', function (e) {
        e.preventDefault();
        var id = $(this).data('id');
        // Navigate to edit URL to load pre-filled form (standard WP pattern)
        location.href = $(this).attr('href');
    });

    // ── Helpers ───────────────────────────────────────────────────
    function showNotice(type, msg) {
        var $note = $('#jtb-company-notice');
        $note.attr('class', 'jtb-notice jtb-notice-' + (type === 'success' ? 'success' : 'error'))
             .text(msg).show();
        setTimeout(function () { $note.fadeOut(); }, 4000);
    }

    function updateSubtitle(delta) {
        var $sub = $('.jtb-page-subtitle');
        var m = $sub.text().match(/(\d+)/);
        if (m) {
            var n = parseInt(m[1]) + delta;
            $sub.text(n + ' compan' + (n !== 1 ? 'ies' : 'y') + ' registered');
        }
    }

    function buildRow(co) {
        var statusClass = co.status === 'active' ? 'badge-green' : 'badge-gray';
        var statusLabel = co.status.charAt(0).toUpperCase() + co.status.slice(1);
        var email = co.contact_email ? '<div class="jtb-text-muted">' + esc(co.contact_email) + '</div>' : '';
        var editUrl  = WPYOGFLEET.admin_url + 'admin.php?page=wpyogfleet-companies&action=edit&id=' + co.id;
        var delUrl   = WPYOGFLEET.admin_url + 'admin.php?page=wpyogfleet-companies&action=delete&id=' + co.id;
        var isAdmin  = <?php echo current_user_can( 'wpyogfleet_admin' ) ? 'true' : 'false'; ?>;
        var delBtn   = isAdmin
            ? '<a href="' + delUrl + '" class="jtb-btn jtb-btn-xs jtb-btn-danger" onclick="return confirm(\'Delete this company?\')">Delete</a>'
            : '';
        return '<tr data-co-id="' + co.id + '">'
            + '<td><strong>' + esc(co.company_name) + '</strong>' + email + '</td>'
            + '<td>' + esc(co.contact_name || '—') + '</td>'
            + '<td>' + esc(co.contact_mobile || '—') + '</td>'
            + '<td>' + esc(co.gst_number || '—') + '</td>'
            + '<td><span class="jtb-badge ' + statusClass + '">' + statusLabel + '</span></td>'
            + '<td class="jtb-actions">'
            +   '<a href="' + editUrl + '" class="jtb-btn jtb-btn-xs jtb-btn-secondary jtb-co-edit" data-id="' + co.id + '">Edit</a> '
            +   delBtn
            + '</td>'
            + '</tr>';
    }

    function esc(s) {
        return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
}(jQuery));
<?php wp_add_inline_script( 'wpyogfleet-admin', ob_get_clean() ); ?>
