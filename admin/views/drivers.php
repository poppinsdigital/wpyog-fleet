<?php if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local vars only: this file is exclusively include()'d inside WPYOGFLEET_Admin::render(), so these are function-scoped, not real globals.
$drivers  = WPYOGFLEET_DB::get_drivers();
$vehicles = WPYOGFLEET_DB::get_vehicles( array('status' => 'active') );
$is_edit  = ! empty( $edit_driver );
?>

<div class="jtb-wrap">
    <div class="jtb-page-header">
        <div class="jtb-page-header__left">
            <h1 class="jtb-page-title">Drivers</h1>
            <p class="jtb-page-subtitle"><?php echo absint( count($drivers) ); ?> driver<?php echo esc_html( count($drivers) !== 1 ? 's' : '' ); ?> registered</p>
        </div>
        <div class="jtb-page-header__right">
            <?php if ( ! $is_edit ) : ?>
            <a href="<?php echo esc_url( admin_url('admin.php?page=wpyogfleet-drivers&action=add') ); ?>" class="jtb-btn jtb-btn-primary">
                <span class="dashicons dashicons-plus-alt2"></span> Add Driver
            </a>
            <?php endif; ?>
        </div>
    </div>

    <?php
    $wpyogfleet_notice_ok = isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wpyogfleet_notice' );
    ?>
    <?php if ( $wpyogfleet_notice_ok && ! empty( $_GET['saved'] ) ) : ?>
    <div class="jtb-notice jtb-notice-success">Driver saved successfully.</div>
    <?php endif; ?>
    <?php if ( $wpyogfleet_notice_ok && ! empty( $_GET['deleted'] ) ) : ?>
    <div class="jtb-notice jtb-notice-success">Driver deleted.</div>
    <?php endif; ?>

    <div class="jtb-two-col-layout">

        <!-- Driver List -->
        <div class="jtb-card">
            <div class="jtb-card__header"><h2 class="jtb-card__title">All Drivers</h2></div>
            <div class="jtb-card__body jtb-card__body--flush">
                <?php if ( $drivers ) : ?>
                <table class="jtb-table">
                    <thead>
                        <tr>
                            <th>Driver</th>
                            <th>Mobile</th>
                            <th>Vehicle</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $drivers as $d ) : ?>
                        <tr>
                            <td><strong><?php echo esc_html($d->driver_name); ?></strong></td>
                            <td><?php echo esc_html($d->mobile ?: '—'); ?></td>
                            <td><?php echo esc_html($d->vehicle_name ?: '—'); ?></td>
                            <td>
                                <span class="jtb-badge <?php echo esc_attr( $d->driver_type === 'own' ? 'badge-green' : 'badge-amber' ); ?>">
                                    <?php echo esc_html( $d->driver_type === 'own' ? 'Own' : 'Outsourced' ); ?>
                                </span>
                            </td>
                            <td>
                                <span class="jtb-badge <?php echo esc_attr( $d->status === 'active' ? 'badge-green' : 'badge-gray' ); ?>">
                                    <?php echo esc_html( ucfirst($d->status) ); ?>
                                </span>
                            </td>
                            <td class="jtb-actions">
                                <a href="<?php echo esc_url( admin_url('admin.php?page=wpyogfleet-drivers&action=edit&id='.$d->id) ); ?>" class="jtb-btn jtb-btn-xs jtb-btn-secondary">Edit</a>
                                <?php if ( current_user_can('wpyogfleet_admin') ) : ?>
                                <a href="<?php echo esc_url( wp_nonce_url( admin_url('admin.php?page=wpyogfleet-drivers&action=delete&id='.$d->id), 'wpyogfleet_delete_driver_'.$d->id ) ); ?>"
                                   class="jtb-btn jtb-btn-xs jtb-btn-danger"
                                   onclick="return confirm('Delete this driver?')">Delete</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else : ?>
                <div class="jtb-empty-state">
                    <span class="dashicons dashicons-businessman"></span>
                    <p>No drivers yet. Add your first driver.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Add / Edit Form -->
        <div class="jtb-card">
            <div class="jtb-card__header">
                <h2 class="jtb-card__title"><?php echo esc_html( $is_edit ? 'Edit Driver' : 'Add Driver' ); ?></h2>
                <?php if ($is_edit) : ?>
                <a href="<?php echo esc_url( admin_url('admin.php?page=wpyogfleet-drivers') ); ?>" class="jtb-link">Cancel</a>
                <?php endif; ?>
            </div>
            <div class="jtb-card__body">
                <form method="POST">
                    <?php wp_nonce_field('wpyogfleet_driver_save','wpyogfleet_driver_nonce'); ?>
                    <?php if ($is_edit) : ?>
                    <input type="hidden" name="driver_id" value="<?php echo esc_html( $edit_driver->id ); ?>">
                    <?php endif; ?>

                    <div class="jtb-field-row">
                        <div class="jtb-field">
                            <label class="jtb-label">Driver Name *</label>
                            <input type="text" name="driver_name" class="jtb-input" required
                                value="<?php echo esc_attr($edit_driver->driver_name ?? ''); ?>"
                                placeholder="Full name">
                        </div>
                        <div class="jtb-field">
                            <label class="jtb-label">Mobile Number</label>
                            <input type="tel" name="mobile" class="jtb-input"
                                value="<?php echo esc_attr($edit_driver->mobile ?? ''); ?>"
                                placeholder="10-digit mobile">
                        </div>
                    </div>
                    <div class="jtb-field">
                        <label class="jtb-label">Assigned Vehicle</label>
                        <select name="vehicle_id" class="jtb-select">
                            <option value="">— No vehicle assigned —</option>
                            <?php foreach ( $vehicles as $v ) : ?>
                            <option value="<?php echo absint( $v->id ); ?>" <?php selected($edit_driver->vehicle_id ?? 0, $v->id); ?>>
                                <?php echo esc_html($v->vehicle_name . ($v->registration_number ? ' ('.$v->registration_number.')' : '')); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="jtb-field">
                        <label class="jtb-label">Driver Type</label>
                        <div class="jtb-radio-group">
                            <label class="jtb-radio-label">
                                <input type="radio" name="driver_type" value="own" <?php checked($edit_driver->driver_type ?? 'own','own'); ?>> Own Driver
                            </label>
                            <label class="jtb-radio-label">
                                <input type="radio" name="driver_type" value="outsourced" <?php checked($edit_driver->driver_type ?? '','outsourced'); ?>> Outsourced
                            </label>
                        </div>
                    </div>
                    <div class="jtb-field">
                        <label class="jtb-label">Status</label>
                        <select name="status" class="jtb-select">
                            <option value="active" <?php selected($edit_driver->status ?? 'active','active'); ?>>Active</option>
                            <option value="inactive" <?php selected($edit_driver->status ?? '','inactive'); ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="jtb-field">
                        <label class="jtb-label">Notes</label>
                        <textarea name="notes" class="jtb-textarea" rows="2"><?php echo esc_textarea($edit_driver->notes ?? ''); ?></textarea>
                    </div>
                    <div class="jtb-form-actions">
                        <button type="submit" class="jtb-btn jtb-btn-primary jtb-btn-block"><?php echo esc_html( $is_edit ? 'Update Driver' : 'Add Driver' ); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
