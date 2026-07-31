<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<?php // phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local vars only: this file is exclusively include()'d inside WPYOGFLEET_Admin::render(), so these are function-scoped, not real globals. ?>

<div class="wrap wpyogfleet-settings-wrap">
    <h1><?php esc_html_e( 'WPYog Fleet — Settings', 'wpyog-fleet' ); ?></h1>

    <?php if ( $saved ) : ?>
    <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'wpyog-fleet' ); ?></p></div>
    <?php endif; ?>

    <?php if ( ! empty( $errors ) ) : ?>
    <div class="notice notice-error">
        <p><strong><?php esc_html_e( 'Please fix the following:', 'wpyog-fleet' ); ?></strong></p>
        <ul style="list-style:disc;margin-left:20px">
            <?php foreach ( $errors as $e ) : ?>
            <li><?php echo esc_html( $e ); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <form method="post" style="max-width:680px;margin-top:20px">
        <?php wp_nonce_field( 'wpyogfleet_save_settings', 'wpyogfleet_settings_nonce' ); ?>

        <table class="form-table" role="presentation">
            <tbody>

            <tr>
                <th scope="row">
                    <label for="wpyogfleet_company_name"><?php esc_html_e( 'Travel Company Name', 'wpyog-fleet' ); ?></label>
                </th>
                <td>
                    <input type="text" id="wpyogfleet_company_name" name="wpyogfleet_company_name" class="regular-text"
                        value="<?php echo esc_attr( WPYOGFLEET_Settings::get( WPYOGFLEET_Settings::COMPANY_NAME ) ); ?>"
                        required>
                    <p class="description"><?php esc_html_e( 'Shown on booking confirmations and emails sent to customers.', 'wpyog-fleet' ); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="wpyogfleet_booking_prefix"><?php esc_html_e( 'Booking Number Prefix', 'wpyog-fleet' ); ?></label>
                </th>
                <td>
                    <input type="text" id="wpyogfleet_booking_prefix" name="wpyogfleet_booking_prefix" class="small-text"
                        value="<?php echo esc_attr( WPYOGFLEET_Settings::get( WPYOGFLEET_Settings::BOOKING_PREFIX ) ); ?>"
                        maxlength="6" pattern="[A-Za-z0-9]+" required>
                    <p class="description">
                        <?php esc_html_e( 'Letters and numbers only (max 6 chars). Preview: ', 'wpyog-fleet' ); ?>
                        <strong id="jtb-prefix-preview"><?php echo esc_html( WPYOGFLEET_Settings::get( WPYOGFLEET_Settings::BOOKING_PREFIX ) ); ?></strong>-<?php echo esc_html( gmdate( 'Y' ) ); ?>-0001
                    </p>
                </td>
            </tr>

            </tbody>
        </table>

        <h2 class="title" style="margin-top:28px"><?php esc_html_e( 'Operations Contact', 'wpyog-fleet' ); ?></h2>
        <p class="description" style="margin-bottom:12px"><?php esc_html_e( 'Used for internal booking notification emails.', 'wpyog-fleet' ); ?></p>

        <table class="form-table" role="presentation">
            <tbody>

            <tr>
                <th scope="row">
                    <label for="wpyogfleet_ops_email"><?php esc_html_e( 'Operations Email', 'wpyog-fleet' ); ?></label>
                </th>
                <td>
                    <input type="email" id="wpyogfleet_ops_email" name="wpyogfleet_ops_email" class="regular-text"
                        value="<?php echo esc_attr( WPYOGFLEET_Settings::get( WPYOGFLEET_Settings::OPS_EMAIL ) ); ?>"
                        placeholder="ops@yourcompany.com">
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="wpyogfleet_ops_phone"><?php esc_html_e( 'Operations Phone', 'wpyog-fleet' ); ?></label>
                </th>
                <td>
                    <input type="tel" id="wpyogfleet_ops_phone" name="wpyogfleet_ops_phone" class="regular-text"
                        value="<?php echo esc_attr( WPYOGFLEET_Settings::get( WPYOGFLEET_Settings::OPS_PHONE ) ); ?>"
                        placeholder="+91 98000 00000">
                </td>
            </tr>

            </tbody>
        </table>

        <h2 class="title" style="margin-top:28px"><?php esc_html_e( 'Google Review Requests', 'wpyog-fleet' ); ?></h2>
        <p class="description" style="margin-bottom:12px"><?php esc_html_e( 'Used for the review-request popup shown when a booking is marked Completed, and for the "Google Review" copy-text option.', 'wpyog-fleet' ); ?></p>

        <table class="form-table" role="presentation">
            <tbody>

            <tr>
                <th scope="row">
                    <label for="wpyogfleet_google_review_link"><?php esc_html_e( 'Google Review Link', 'wpyog-fleet' ); ?></label>
                </th>
                <td>
                    <input type="url" id="wpyogfleet_google_review_link" name="wpyogfleet_google_review_link" class="regular-text"
                        value="<?php echo esc_attr( WPYOGFLEET_Settings::get( WPYOGFLEET_Settings::GOOGLE_REVIEW_LINK ) ); ?>"
                        placeholder="https://g.page/r/xxxxxxxxxxxxx/review">
                    <p class="description"><?php esc_html_e( 'Find this under your Google Business Profile → Ask for reviews.', 'wpyog-fleet' ); ?></p>
                </td>
            </tr>

            </tbody>
        </table>

        <?php submit_button( __( 'Save Settings', 'wpyog-fleet' ) ); ?>
    </form>
</div>

<?php
wp_add_inline_script( 'wpyogfleet-admin', '(function(){
    var inp  = document.getElementById(\'wpyogfleet_booking_prefix\');
    var prev = document.getElementById(\'jtb-prefix-preview\');
    if ( inp && prev ) {
        inp.addEventListener(\'input\', function() {
            prev.textContent = this.value.toUpperCase().replace(/[^A-Z0-9]/g, \'\') || \'PREFIX\';
        });
    }
})();' );
?>
