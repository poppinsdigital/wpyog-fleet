<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<?php // phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local vars only: this file is exclusively include()'d inside WPYOGFLEET_Admin::render(), so these are function-scoped, not real globals. ?>

<div class="wrap">
    <h1><?php esc_html_e( 'Booking Staff Users', 'wpyog-fleet' ); ?></h1>

    <?php if ( $msg === 'added' ) : ?>
    <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'User added as Booking Staff.', 'wpyog-fleet' ); ?></p></div>
    <?php elseif ( $msg === 'removed' ) : ?>
    <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'User removed from Booking Staff.', 'wpyog-fleet' ); ?></p></div>
    <?php endif; ?>

    <p style="max-width:680px;margin-top:8px;color:#555">
        <?php esc_html_e( 'Booking Staff can log into the frontend booking panel. Administrators always have full access.', 'wpyog-fleet' ); ?>
    </p>

    <!-- Current staff table -->
    <h2 style="margin-top:28px"><?php esc_html_e( 'Current Staff', 'wpyog-fleet' ); ?></h2>

    <?php if ( empty( $staff ) ) : ?>
        <p><?php esc_html_e( 'No staff users yet.', 'wpyog-fleet' ); ?></p>
    <?php else : ?>
    <table class="wp-list-table widefat fixed striped" style="max-width:780px;margin-top:12px">
        <thead>
            <tr>
                <th style="width:40px"><?php esc_html_e( 'ID', 'wpyog-fleet' ); ?></th>
                <th><?php esc_html_e( 'Name', 'wpyog-fleet' ); ?></th>
                <th><?php esc_html_e( 'Username', 'wpyog-fleet' ); ?></th>
                <th><?php esc_html_e( 'Email', 'wpyog-fleet' ); ?></th>
                <th><?php esc_html_e( 'Role', 'wpyog-fleet' ); ?></th>
                <th style="width:100px"><?php esc_html_e( 'Action', 'wpyog-fleet' ); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( $staff as $u ) :
            $is_admin = user_can( $u->ID, 'wpyogfleet_admin' );
            $role_label = $is_admin ? __( 'Administrator', 'wpyog-fleet' ) : __( 'Booking Staff', 'wpyog-fleet' );
        ?>
            <tr>
                <td><?php echo esc_html( $u->ID ); ?></td>
                <td>
                    <a href="<?php echo esc_url( get_edit_user_link( $u->ID ) ); ?>"><strong><?php echo esc_html( $u->display_name ); ?></strong></a>
                </td>
                <td><?php echo esc_html( $u->user_login ); ?></td>
                <td><?php echo esc_html( $u->user_email ); ?></td>
                <td><?php echo esc_html( $role_label ); ?></td>
                <td>
                    <?php if ( ! $is_admin ) : ?>
                    <a href="<?php echo esc_url( wp_nonce_url(
                        admin_url( 'admin.php?page=wpyogfleet-staff&remove_staff=' . $u->ID ),
                        'wpyogfleet_remove_staff'
                    ) ); ?>"
                       onclick="return confirm('<?php esc_attr_e( 'Remove this user from Booking Staff?', 'wpyog-fleet' ); ?>')"
                       class="button button-small button-link-delete">
                        <?php esc_html_e( 'Remove', 'wpyog-fleet' ); ?>
                    </a>
                    <?php else : ?>
                    <span style="color:#999;font-size:12px"><?php esc_html_e( '—', 'wpyog-fleet' ); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <!-- Add staff form -->
    <h2 style="margin-top:36px"><?php esc_html_e( 'Add Staff User', 'wpyog-fleet' ); ?></h2>
    <p style="color:#555;font-size:13px"><?php esc_html_e( 'Select an existing WordPress user to give Booking Staff access.', 'wpyog-fleet' ); ?></p>

    <?php if ( empty( $all_users ) ) : ?>
        <p><?php esc_html_e( 'All WordPress users already have staff access.', 'wpyog-fleet' ); ?></p>
    <?php else : ?>
    <form method="post" style="margin-top:12px">
        <?php wp_nonce_field( 'wpyogfleet_manage_staff', 'wpyogfleet_staff_nonce' ); ?>
        <table class="form-table" role="presentation" style="max-width:680px">
            <tr>
                <th scope="row"><label for="wpyogfleet_staff_user_id"><?php esc_html_e( 'User', 'wpyog-fleet' ); ?></label></th>
                <td>
                    <select name="user_id" id="wpyogfleet_staff_user_id" class="regular-text">
                        <option value=""><?php esc_html_e( '— Select a user —', 'wpyog-fleet' ); ?></option>
                        <?php foreach ( $all_users as $u ) : ?>
                        <option value="<?php echo esc_attr( $u->ID ); ?>">
                            <?php echo esc_html( $u->display_name . ' (' . $u->user_email . ')' ); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        </table>
        <?php submit_button( __( 'Add as Booking Staff', 'wpyog-fleet' ), 'primary', 'submit', false ); ?>
    </form>
    <?php endif; ?>

</div>
