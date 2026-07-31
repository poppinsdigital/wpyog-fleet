<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local vars only: this file is exclusively include()'d inside WPYOGFLEET_Admin::render(), so these are function-scoped, not real globals.
// Setup page styles — attached to wpyogfleet-admin handle, output via wp_head().
$wpyogfleet_setup_css = <<<'SETUPCSS'
* { box-sizing: border-box; margin: 0; padding: 0; }
body { background: #f0f4ff; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; display: flex; align-items: flex-start; justify-content: center; min-height: 100vh; padding: 40px 16px; }
.wpyogfleet-setup { width: 100%; max-width: 560px; }
.jtb-setup__logo { text-align: center; margin-bottom: 28px; }
.jtb-setup__logo img { height: 48px; }
.jtb-setup__logo-text { font-size: 22px; font-weight: 800; color: #4f46e5; letter-spacing: -0.5px; }
.jtb-setup__card { background: #fff; border-radius: 16px; box-shadow: 0 4px 32px rgba(79,70,229,.10); padding: 36px 40px; }
.jtb-setup__step { display: flex; align-items: center; gap: 10px; margin-bottom: 28px; }
.jtb-setup__step-dot { width: 28px; height: 28px; border-radius: 50%; background: #4f46e5; color: #fff; font-size: 13px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.jtb-setup__step-text h1 { font-size: 20px; font-weight: 700; color: #0f172a; }
.jtb-setup__step-text p { font-size: 13px; color: #64748b; margin-top: 2px; }
.jtb-setup__field { margin-bottom: 20px; }
.jtb-setup__field label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px; }
.jtb-setup__field label span { color: #94a3b8; font-weight: 400; margin-left: 4px; }
.jtb-setup__field input { width: 100%; padding: 10px 14px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; color: #0f172a; outline: none; transition: border-color .15s; }
.jtb-setup__field input:focus { border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79,70,229,.1); }
.jtb-setup__field small { display: block; font-size: 12px; color: #94a3b8; margin-top: 5px; }
.jtb-setup__divider { border: none; border-top: 1px solid #f1f5f9; margin: 24px 0; }
.jtb-setup__btn { width: 100%; padding: 12px; background: #4f46e5; color: #fff; border: none; border-radius: 10px; font-size: 15px; font-weight: 700; cursor: pointer; transition: background .15s; margin-top: 8px; }
.jtb-setup__btn:hover { background: #4338ca; }
.jtb-setup__skip { text-align: center; margin-top: 14px; font-size: 13px; }
.jtb-setup__skip a { color: #94a3b8; text-decoration: none; }
.jtb-setup__skip a:hover { color: #4f46e5; }
.jtb-setup__errors { background: #fee2e2; border: 1px solid #fca5a5; border-radius: 8px; padding: 12px 16px; margin-bottom: 20px; font-size: 13px; color: #991b1b; }
.jtb-setup__errors li { margin-left: 16px; margin-top: 4px; }
SETUPCSS;
wp_add_inline_style( 'wpyogfleet-admin', $wpyogfleet_setup_css );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php esc_html_e( 'WPYog Fleet — Setup', 'wpyog-fleet' ); ?></title>
<?php wp_head(); ?>
</head>
<body>
<div class="wpyogfleet-setup">

    <div class="jtb-setup__logo">
        <div class="jtb-setup__logo-text">WPYog Fleet</div>
    </div>

    <div class="jtb-setup__card">
        <div class="jtb-setup__step">
            <div class="jtb-setup__step-dot">1</div>
            <div class="jtb-setup__step-text">
                <h1>Welcome! Let's set up your booking system.</h1>
                <p>Takes less than a minute. You can change these anytime in Settings.</p>
            </div>
        </div>

        <?php if ( ! empty( $errors ) ) : ?>
        <div class="jtb-setup__errors">
            <strong>Please fix the following:</strong>
            <ul>
                <?php foreach ( $errors as $e ) echo '<li>' . esc_html( $e ) . '</li>'; ?>
            </ul>
        </div>
        <?php endif; ?>

        <form method="post">
            <?php wp_nonce_field( 'wpyogfleet_save_setup', 'wpyogfleet_setup_nonce' ); ?>

            <div class="jtb-setup__field">
                <label for="wpyogfleet_company_name">Travel Company Name</label>
                <input type="text" id="wpyogfleet_company_name" name="wpyogfleet_company_name"
                    value="<?php echo esc_attr( WPYOGFLEET_Settings::get( WPYOGFLEET_Settings::COMPANY_NAME ) ); ?>"
                    placeholder="e.g. Jagat Travels" required>
                <small>This name appears on booking confirmations sent to customers.</small>
            </div>

            <div class="jtb-setup__field">
                <label for="wpyogfleet_booking_prefix">Booking Number Prefix <span>(letters/numbers only)</span></label>
                <input type="text" id="wpyogfleet_booking_prefix" name="wpyogfleet_booking_prefix"
                    value="<?php echo esc_attr( WPYOGFLEET_Settings::get( WPYOGFLEET_Settings::BOOKING_PREFIX ) ); ?>"
                    placeholder="e.g. JT" maxlength="6" pattern="[A-Za-z0-9]+" required>
                <small>Booking numbers will look like <strong id="jtb-prefix-preview"><?php echo esc_html( WPYOGFLEET_Settings::get( WPYOGFLEET_Settings::BOOKING_PREFIX ) ); ?></strong>-<?php echo esc_html( gmdate('Y') ); ?>-0001. Letters and numbers only.</small>
            </div>

            <hr class="jtb-setup__divider">
            <p style="font-size:12px;color:#94a3b8;margin-bottom:18px">Operations contact — used for internal booking notification emails.</p>

            <div class="jtb-setup__field">
                <label for="wpyogfleet_ops_email">Operations Email <span>(optional)</span></label>
                <input type="email" id="wpyogfleet_ops_email" name="wpyogfleet_ops_email"
                    value="<?php echo esc_attr( WPYOGFLEET_Settings::get( WPYOGFLEET_Settings::OPS_EMAIL ) ); ?>"
                    placeholder="ops@yourcompany.com">
            </div>

            <div class="jtb-setup__field">
                <label for="wpyogfleet_ops_phone">Operations Phone <span>(optional)</span></label>
                <input type="tel" id="wpyogfleet_ops_phone" name="wpyogfleet_ops_phone"
                    value="<?php echo esc_attr( WPYOGFLEET_Settings::get( WPYOGFLEET_Settings::OPS_PHONE ) ); ?>"
                    placeholder="+91 98000 00000">
            </div>

            <button type="submit" class="jtb-setup__btn">
                Save &amp; Go to Dashboard →
            </button>
        </form>

        <div class="jtb-setup__skip">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpyogfleet-dashboard' ) ); ?>" id="jtb-skip-setup"
               onclick="<?php echo esc_attr( "fetch('" . admin_url('admin-post.php') . "', {method:'POST',body:new URLSearchParams({action:'wpyogfleet_skip_setup',_wpnonce:'" . wp_create_nonce('wpyogfleet_skip_setup') . "'})});" ); ?>">
                Skip for now
            </a>
        </div>
    </div>
</div>
<?php
// Setup page JS — attached to wpyogfleet-admin handle, output via wp_footer().
$wpyogfleet_setup_js = <<<'SETUPJS'
var inp = document.getElementById('wpyogfleet_booking_prefix');
var prev = document.getElementById('jtb-prefix-preview');
if (inp && prev) {
    inp.addEventListener('input', function() {
        prev.textContent = this.value.toUpperCase().replace(/[^A-Z0-9]/g,'') || 'PREFIX';
    });
    // Auto-derive prefix from company name
    var comp = document.getElementById('wpyogfleet_company_name');
    if (comp && !inp.value) {
        comp.addEventListener('blur', function() {
            if (inp.value) return;
            var words = this.value.trim().split(/\s+/);
            var p = words.map(function(w){ return w[0] || ''; }).join('').toUpperCase();
            inp.value = p || this.value.substring(0,2).toUpperCase();
            prev.textContent = inp.value || 'PREFIX';
        });
    }
}
SETUPJS;
wp_add_inline_script( 'wpyogfleet-admin', $wpyogfleet_setup_js );
wp_footer();
?>
</body>
</html>
