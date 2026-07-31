<?php if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local vars only: this file is exclusively include()'d inside WPYOGFLEET_Admin::render(), so these are function-scoped, not real globals.
$vehicles = WPYOGFLEET_DB::get_vehicles();
$is_edit  = ! empty( $edit_vehicle );
?>

<div class="jtb-wrap">
    <div class="jtb-page-header">
        <div class="jtb-page-header__left">
            <h1 class="jtb-page-title">Vehicles</h1>
            <p class="jtb-page-subtitle"><?php echo absint( count($vehicles) ); ?> vehicle<?php echo esc_html( count($vehicles) !== 1 ? 's' : '' ); ?> registered</p>
        </div>
        <div class="jtb-page-header__right">
            <?php if ( ! $is_edit ) : ?>
            <a href="<?php echo esc_url( admin_url('admin.php?page=wpyogfleet-vehicles&action=add') ); ?>" class="jtb-btn jtb-btn-primary">
                <span class="dashicons dashicons-plus-alt2"></span> Add Vehicle
            </a>
            <?php endif; ?>
        </div>
    </div>

    <?php
    $wpyogfleet_notice_ok = isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wpyogfleet_notice' );
    ?>
    <?php if ( $wpyogfleet_notice_ok && ! empty( $_GET['saved'] ) ) : ?>
    <div class="jtb-notice jtb-notice-success">Vehicle saved successfully.</div>
    <?php endif; ?>
    <?php if ( $wpyogfleet_notice_ok && ! empty( $_GET['deleted'] ) ) : ?>
    <div class="jtb-notice jtb-notice-success">Vehicle deleted.</div>
    <?php endif; ?>

    <div class="jtb-two-col-layout">

        <!-- Vehicle List -->
        <div class="jtb-card">
            <div class="jtb-card__header"><h2 class="jtb-card__title">All Vehicles</h2></div>
            <div class="jtb-card__body jtb-card__body--flush">
                <?php if ( $vehicles ) : ?>
                <table class="jtb-table">
                    <thead>
                        <tr>
                            <th>Vehicle</th>
                            <th>Reg. Number</th>
                            <th>Type</th>
                            <th>Owner</th>
                            <th>Vendor</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $vehicles as $v ) : ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($v->vehicle_name); ?></strong>
                                <?php if ($v->vehicle_type) echo '<div class="jtb-text-muted">'.esc_html($v->vehicle_type).'</div>'; ?>
                            </td>
                            <td><?php echo esc_html($v->registration_number ?: '—'); ?></td>
                            <td>
                                <span class="jtb-badge <?php echo esc_attr( $v->owner_type === 'own' ? 'badge-green' : 'badge-amber' ); ?>">
                                    <?php echo esc_html( $v->owner_type === 'own' ? 'Own' : 'Outsourced' ); ?>
                                </span>
                            </td>
                            <td><?php echo $v->owner_type === 'outsourced' ? esc_html($v->vendor_name ?: '—') : '—'; ?></td>
                            <td><?php echo $v->owner_type === 'outsourced' ? esc_html($v->vendor_mobile ?: '—') : '—'; ?></td>
                            <td>
                                <span class="jtb-badge <?php echo esc_attr( $v->status === 'active' ? 'badge-green' : 'badge-gray' ); ?>">
                                    <?php echo esc_html( ucfirst($v->status) ); ?>
                                </span>
                            </td>
                            <td class="jtb-actions">
                                <a href="<?php echo esc_url( admin_url('admin.php?page=wpyogfleet-vehicles&action=edit&id='.$v->id) ); ?>" class="jtb-btn jtb-btn-xs jtb-btn-secondary">Edit</a>
                                <?php if ( current_user_can('wpyogfleet_admin') ) : ?>
                                <a href="<?php echo esc_url( wp_nonce_url( admin_url('admin.php?page=wpyogfleet-vehicles&action=delete&id='.$v->id), 'wpyogfleet_delete_vehicle_'.$v->id ) ); ?>"
                                   class="jtb-btn jtb-btn-xs jtb-btn-danger"
                                   onclick="return confirm('Delete this vehicle?')">Delete</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else : ?>
                <div class="jtb-empty-state">
                    <span class="dashicons dashicons-car"></span>
                    <p>No vehicles yet. Add your first vehicle.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Add / Edit Form -->
        <div class="jtb-card">
            <div class="jtb-card__header">
                <h2 class="jtb-card__title"><?php echo esc_html( $is_edit ? 'Edit Vehicle' : 'Add Vehicle' ); ?></h2>
                <?php if ($is_edit) : ?>
                <a href="<?php echo esc_url( admin_url('admin.php?page=wpyogfleet-vehicles') ); ?>" class="jtb-link">Cancel</a>
                <?php endif; ?>
            </div>
            <div class="jtb-card__body">
                <form method="POST">
                    <?php wp_nonce_field('wpyogfleet_vehicle_save','wpyogfleet_vehicle_nonce'); ?>
                    <?php if ($is_edit) : ?>
                    <input type="hidden" name="vehicle_id" value="<?php echo esc_html( $edit_vehicle->id ); ?>">
                    <?php endif; ?>

                    <div class="jtb-field-row">
                        <div class="jtb-field">
                            <label class="jtb-label">Vehicle Name *</label>
                            <input type="text" name="vehicle_name" class="jtb-input" required
                                value="<?php echo esc_attr($edit_vehicle->vehicle_name ?? ''); ?>"
                                placeholder="e.g. Toyota Innova">
                        </div>
                        <div class="jtb-field">
                            <label class="jtb-label">Vehicle Type</label>
                            <select name="vehicle_type" class="jtb-select">
                                <option value="">— Select —</option>
                                <?php foreach ( array('Sedan','Hatchback','SUV','MUV','Tempo Traveller','Bus','Other') as $t ) : ?>
                                <option value="<?php echo esc_attr( $t ); ?>" <?php selected($edit_vehicle->vehicle_type ?? '', $t); ?>><?php echo esc_attr( $t ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="jtb-field">
                        <label class="jtb-label">Registration Number</label>
                        <input type="text" name="registration_number" class="jtb-input"
                            value="<?php echo esc_attr($edit_vehicle->registration_number ?? ''); ?>"
                            placeholder="e.g. MP 09 AB 1234">
                    </div>
                    <div class="jtb-field">
                        <label class="jtb-label">Owner Type</label>
                        <div class="jtb-radio-group">
                            <label class="jtb-radio-label">
                                <input type="radio" name="owner_type" value="own" <?php checked($edit_vehicle->owner_type ?? 'own','own'); ?>> Own Vehicle
                            </label>
                            <label class="jtb-radio-label">
                                <input type="radio" name="owner_type" value="outsourced" <?php checked($edit_vehicle->owner_type ?? '','outsourced'); ?>> Outsourced
                            </label>
                        </div>
                    </div>
                    <div id="jtb-vendor-fields" style="<?php echo ($edit_vehicle->owner_type ?? '') === 'outsourced' ? '' : 'display:none'; ?>">
                        <div class="jtb-field-row">
                            <div class="jtb-field">
                                <label class="jtb-label">Vendor / Owner Name</label>
                                <input type="text" name="vendor_name" class="jtb-input"
                                    value="<?php echo esc_attr($edit_vehicle->vendor_name ?? ''); ?>">
                            </div>
                            <div class="jtb-field">
                                <label class="jtb-label">Vendor Mobile</label>
                                <input type="tel" name="vendor_mobile" class="jtb-input"
                                    value="<?php echo esc_attr($edit_vehicle->vendor_mobile ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                    <div class="jtb-field-row">
                        <div class="jtb-field">
                            <label class="jtb-label">Status</label>
                            <select name="status" class="jtb-select">
                                <option value="active" <?php selected($edit_vehicle->status ?? 'active','active'); ?>>Active</option>
                                <option value="inactive" <?php selected($edit_vehicle->status ?? '','inactive'); ?>>Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="jtb-field">
                        <label class="jtb-label">Notes</label>
                        <textarea name="notes" class="jtb-textarea" rows="2"><?php echo esc_textarea($edit_vehicle->notes ?? ''); ?></textarea>
                    </div>
                    <div class="jtb-form-actions">
                        <button type="submit" class="jtb-btn jtb-btn-primary jtb-btn-block"><?php echo esc_html( $is_edit ? 'Update Vehicle' : 'Add Vehicle' ); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
wp_add_inline_script( 'wpyogfleet-admin', '(function(){
    var radios  = document.querySelectorAll(\'input[name="owner_type"]\');
    var wrap    = document.getElementById(\'jtb-vendor-fields\');
    radios.forEach(function(r){
        r.addEventListener(\'change\', function(){
            wrap.style.display = r.value === \'outsourced\' ? \'\' : \'none\';
        });
    });
})();' );
?>
