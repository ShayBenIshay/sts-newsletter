<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class STS_Newsletter {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'plugins_loaded', array( $this, 'init' ) );
    }

    public function init() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

        add_action( 'wp_ajax_newsletter_signup',        array( $this, 'handle_ajax_signup' ) );
        add_action( 'wp_ajax_nopriv_newsletter_signup', array( $this, 'handle_ajax_signup' ) );

        add_action( 'show_user_profile',        array( $this, 'add_profile_fields' ) );
        add_action( 'edit_user_profile',        array( $this, 'add_profile_fields' ) );
        add_action( 'personal_options_update',  array( $this, 'save_profile_fields' ) );
        add_action( 'edit_user_profile_update', array( $this, 'save_profile_fields' ) );

        add_action( 'sts_newsletter_footer_form', array( $this, 'render_footer_form' ) );

        if ( class_exists( 'WooCommerce' ) ) {
            add_action( 'woocommerce_after_checkout_billing_form', array( $this, 'add_checkout_field' ) );
            add_action( 'woocommerce_checkout_update_user_meta',   array( $this, 'save_checkout_field' ) );
            add_action( 'woocommerce_register_form',               array( $this, 'add_registration_field' ) );
            add_action( 'woocommerce_created_customer',            array( $this, 'save_registration_field' ) );
        }
    }

    public function enqueue_scripts() {
        wp_enqueue_script(
            'sts-newsletter-checkout',
            STS_NL_PLUGIN_URL . 'assets/js/newsletter-checkout.js',
            array( 'jquery' ),
            STS_NL_VERSION,
            true
        );

        wp_enqueue_script(
            'sts-newsletter-footer',
            STS_NL_PLUGIN_URL . 'assets/js/newsletter-footer.js',
            array( 'jquery' ),
            STS_NL_VERSION,
            true
        );

        wp_localize_script( 'sts-newsletter-footer', 'newsletterData', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'newsletter_signup_nonce' ),
        ) );
    }

    public function add_checkout_field( $checkout ) {
        if ( is_user_logged_in() && get_user_meta( get_current_user_id(), 'newsletter_subscribed', true ) === 'yes' ) {
            return;
        }

        echo '<div class="newsletter-checkout-section" style="display: flex; flex-direction: column; gap: 0.75rem; margin-top: 0.75rem; border-radius: 5px;">';

        $checked = $checkout->get_value( 'newsletter_signup' ) ? 'checked' : '';
        echo '<div class="newsletter-checkbox-row" style="display: flex; flex-direction: row; align-items: center; gap: 0.5rem;">';
        echo '<input type="checkbox" name="newsletter_signup" id="newsletter_signup" value="1" ' . $checked . '>';
        echo '<label for="newsletter_signup" style="margin: 0;">הצטרפו למועדון הלקוחות שלנו! קבלו עדכונים על קולקציות חדשות ומבצעים</label>';
        echo '</div>';

        echo '<div id="newsletter-channel-select" style="margin-top: 10px; display: none;">';
        echo '<p class="text-sts"><strong>איך תרצו לקבל עדכונים?</strong></p>';

        $saved_channel   = $checkout->get_value( 'newsletter_channel' );
        $default_channel = ! empty( $saved_channel ) ? $saved_channel : 'both';

        echo '<div class="newsletter-channel-options" style="display: flex; flex-direction: column; gap: 0.5rem; margin-top: 0.5rem;">';

        echo '<div class="newsletter-channel-option" style="display: flex; flex-direction: row; align-items: center; justify-content: flex-start; gap: 0.5rem;">';
        echo '<input type="radio" class="input-radio" value="whatsapp" name="newsletter_channel" id="newsletter_channel_whatsapp" style="width: auto !important;" ' . checked( $default_channel, 'whatsapp', false ) . '>';
        echo '<label for="newsletter_channel_whatsapp" class="radio" style="margin: 0;">וואטסאפ</label>';
        echo '</div>';

        echo '<div class="newsletter-channel-option" style="display: flex; flex-direction: row; align-items: center; justify-content: flex-start; gap: 0.5rem;">';
        echo '<input type="radio" class="input-radio" value="email" name="newsletter_channel" id="newsletter_channel_email" style="width: auto !important;" ' . checked( $default_channel, 'email', false ) . '>';
        echo '<label for="newsletter_channel_email" class="radio" style="margin: 0;">אימייל</label>';
        echo '</div>';

        echo '<div class="newsletter-channel-option" style="display: flex; flex-direction: row; align-items: center; justify-content: flex-start; gap: 0.5rem;">';
        echo '<input type="radio" class="input-radio" value="both" name="newsletter_channel" id="newsletter_channel_both" style="width: auto !important;" ' . checked( $default_channel, 'both', false ) . '>';
        echo '<label for="newsletter_channel_both" class="radio" style="margin: 0;">שניהם - וואטסאפ ואימייל</label>';
        echo '</div>';

        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    public function save_checkout_field( $user_id ) {
        if ( isset( $_POST['newsletter_signup'] ) && $_POST['newsletter_signup'] ) {
            $channel = isset( $_POST['newsletter_channel'] ) ? sanitize_text_field( $_POST['newsletter_channel'] ) : 'both';

            update_user_meta( $user_id, 'newsletter_subscribed', 'yes' );
            update_user_meta( $user_id, 'newsletter_channel', $channel );
            update_user_meta( $user_id, 'newsletter_consent_date', current_time( 'mysql' ) );
            update_user_meta( $user_id, 'newsletter_topics', array( 'new_collections', 'sales' ) );
            delete_user_meta( $user_id, 'newsletter_unsubscribe_date' );
        }
    }

    public function add_registration_field() {
        ?>
        <p class="form-row form-row-wide" style="margin-top: 15px;">
            <label class="woocommerce-form__label woocommerce-billing-fields woocommerce-form__label-for-checkbox checkbox">
                <input class="woocommerce-form__input woocommerce-form__input-checkbox"
                       type="checkbox" name="newsletter_signup" id="newsletter_signup" value="1">
                <span>ברצוני לקבל עדכונים על מוצרים חדשים ומבצעים</span>
            </label>
        </p>
        <p class="form-row form-row-wide" id="newsletter_channel_field" style="display: none;">
            <label>איך תרצה/י לקבל עדכונים?</label><br>
            <label style="display: inline-block; margin-right: 10px;">
                <input type="radio" name="newsletter_channel" value="email" checked> אימייל
            </label>
            <label style="display: inline-block; margin-right: 10px;">
                <input type="radio" name="newsletter_channel" value="whatsapp"> WhatsApp
            </label>
            <label style="display: inline-block;">
                <input type="radio" name="newsletter_channel" value="both"> שניהם
            </label>
        </p>
        <p class="form-row form-row-wide" id="newsletter_phone_field" style="display: none;">
            <label for="newsletter_phone_reg" class="sts-form-label">מספר טלפון <span class="required">*</span></label>
            <input type="tel" name="newsletter_phone" id="newsletter_phone_reg"
                   class="newsletter-phone-input"
                   placeholder="לדוגמה: 0501234567" dir="ltr">
            <span class="newsletter-phone-hint">נדרש לקבלת עדכוני WhatsApp</span>
        </p>
        <?php
    }

    public function save_registration_field( $user_id ) {
        if ( isset( $_POST['newsletter_signup'] ) && $_POST['newsletter_signup'] ) {
            $channel = isset( $_POST['newsletter_channel'] ) ? sanitize_text_field( $_POST['newsletter_channel'] ) : 'email';

            update_user_meta( $user_id, 'newsletter_subscribed', 'yes' );
            update_user_meta( $user_id, 'newsletter_channel', $channel );
            update_user_meta( $user_id, 'newsletter_consent_date', current_time( 'mysql' ) );
            update_user_meta( $user_id, 'newsletter_topics', array( 'new_collections', 'sales' ) );
            delete_user_meta( $user_id, 'newsletter_unsubscribe_date' );

            $phone = isset( $_POST['newsletter_phone'] ) ? sanitize_text_field( $_POST['newsletter_phone'] ) : '';
            if ( $channel === 'whatsapp' || $channel === 'both' ) {
                $normalized = STS_NL_Phone::normalize( $phone );
                if ( $normalized === '' ) {
                    update_user_meta( $user_id, 'newsletter_channel', 'email' );
                } else {
                    update_user_meta( $user_id, 'billing_phone', $normalized );
                }
            }
        }
    }

    public function handle_ajax_signup() {
        check_ajax_referer( 'newsletter_signup_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array(
                'message'   => 'עליך להתחבר כדי להירשם לניוזלטר',
                'login_url' => wp_login_url( $_SERVER['HTTP_REFERER'] ),
            ) );
        }

        $user_id = get_current_user_id();
        $channel = isset( $_POST['channel'] ) ? sanitize_text_field( $_POST['channel'] ) : 'both';
        $topics  = isset( $_POST['topics'] ) ? array_map( 'sanitize_text_field', (array) $_POST['topics'] ) : array( 'new_collections', 'sales' );
        $phone   = isset( $_POST['phone'] )  ? sanitize_text_field( $_POST['phone'] )  : '';

        $normalized_phone = '';
        if ( $channel === 'whatsapp' || $channel === 'both' ) {
            $normalized_phone = STS_NL_Phone::normalize( $phone );
            if ( $normalized_phone === '' ) {
                wp_send_json_error( array( 'message' => 'יש להזין מספר טלפון תקין לקבלת עדכוני WhatsApp' ) );
            }
        }

        update_user_meta( $user_id, 'newsletter_subscribed', 'yes' );
        update_user_meta( $user_id, 'newsletter_channel', $channel );
        update_user_meta( $user_id, 'newsletter_topics', $topics );
        update_user_meta( $user_id, 'newsletter_consent_date', current_time( 'mysql' ) );
        delete_user_meta( $user_id, 'newsletter_unsubscribe_date' );

        if ( $normalized_phone !== '' ) {
            update_user_meta( $user_id, 'billing_phone', $normalized_phone );
        }

        wp_send_json_success( array(
            'message' => 'נרשמת בהצלחה לניוזלטר! 🎉',
        ) );
    }

    public function add_profile_fields( $user ) {
        $subscribed = get_user_meta( $user->ID, 'newsletter_subscribed', true );
        $channel    = get_user_meta( $user->ID, 'newsletter_channel', true );
        $topics     = get_user_meta( $user->ID, 'newsletter_topics', true );

        if ( ! is_array( $topics ) ) {
            $topics = array();
        }
        ?>
        <h3 style="font-family: Alef, sans-serif; font-size: 20px; margin-top: 20px;">העדפות ניוזלטר</h3>
        <table class="form-table">
            <tr>
                <th><label for="newsletter_subscribed">הרשמה לניוזלטר</label></th>
                <td>
                    <input type="checkbox" name="newsletter_subscribed" id="newsletter_subscribed"
                           value="yes" <?php checked( $subscribed, 'yes' ); ?>>
                    <label for="newsletter_subscribed">כן, ברצוני לקבל עדכונים</label>
                    <?php
                    if ( $subscribed === 'yes' ) {
                        $consent_date = get_user_meta( $user->ID, 'newsletter_consent_date', true );
                        if ( $consent_date ) {
                            echo '<p class="description">נרשמת בתאריך: ' . esc_html( date( 'd/m/Y H:i', strtotime( $consent_date ) ) ) . '</p>';
                        }
                    }
                    ?>
                </td>
            </tr>
            <tr>
                <th><label>ערוץ תקשורת מועדף</label></th>
                <td>
                    <label style="display: block; margin-bottom: 10px;">
                        <input type="radio" name="newsletter_channel" value="whatsapp" <?php checked( $channel, 'whatsapp' ); ?>>
                        WhatsApp בלבד
                    </label>
                    <label style="display: block; margin-bottom: 10px;">
                        <input type="radio" name="newsletter_channel" value="email" <?php checked( $channel, 'email' ); ?>>
                        אימייל בלבד
                    </label>
                    <label style="display: block;">
                        <input type="radio" name="newsletter_channel" value="both" <?php checked( $channel, 'both' ); ?>>
                        שניהם (WhatsApp ואימייל)
                    </label>
                </td>
            </tr>
            <tr>
                <th><label>נושאים מעניינים</label></th>
                <td>
                    <label style="display: block; margin-bottom: 8px;">
                        <input type="checkbox" name="newsletter_topics[]" value="new_collections"
                               <?php checked( in_array( 'new_collections', $topics, true ) ); ?>>
                        קולקציות חדשות
                    </label>
                    <label style="display: block; margin-bottom: 8px;">
                        <input type="checkbox" name="newsletter_topics[]" value="sales"
                               <?php checked( in_array( 'sales', $topics, true ) ); ?>>
                        מבצעים והנחות
                    </label>
                    <label style="display: block; margin-bottom: 8px;">
                        <input type="checkbox" name="newsletter_topics[]" value="tips"
                               <?php checked( in_array( 'tips', $topics, true ) ); ?>>
                        טיפים לטיפוח תכשיטים
                    </label>
                    <label style="display: block;">
                        <input type="checkbox" name="newsletter_topics[]" value="events"
                               <?php checked( in_array( 'events', $topics, true ) ); ?>>
                        אירועים מיוחדים
                    </label>
                </td>
            </tr>
        </table>
        <?php
    }

    public function save_profile_fields( $user_id ) {
        if ( ! current_user_can( 'edit_user', $user_id ) ) {
            return false;
        }

        $subscribed = isset( $_POST['newsletter_subscribed'] ) ? 'yes' : 'no';
        $channel    = isset( $_POST['newsletter_channel'] ) ? sanitize_text_field( $_POST['newsletter_channel'] ) : 'email';
        $topics     = isset( $_POST['newsletter_topics'] ) ? array_map( 'sanitize_text_field', (array) $_POST['newsletter_topics'] ) : array();

        update_user_meta( $user_id, 'newsletter_subscribed', $subscribed );
        update_user_meta( $user_id, 'newsletter_channel', $channel );
        update_user_meta( $user_id, 'newsletter_topics', $topics );

        if ( $subscribed === 'yes' ) {
            if ( '' === get_user_meta( $user_id, 'newsletter_consent_date', true ) ) {
                update_user_meta( $user_id, 'newsletter_consent_date', current_time( 'mysql' ) );
            }
            delete_user_meta( $user_id, 'newsletter_unsubscribe_date' );
        } else {
            update_user_meta( $user_id, 'newsletter_unsubscribe_date', current_time( 'mysql' ) );
        }
    }

    public function render_footer_form() {
        include STS_NL_PLUGIN_DIR . 'includes/templates/footer-form.php';
    }
}
