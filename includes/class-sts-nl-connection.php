<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class STS_NL_Connection {

    public static function register(): void {
        add_action( 'wp_ajax_sts_nl_connect_session',    [ self::class, 'connect' ] );
        add_action( 'wp_ajax_sts_nl_poll_session',       [ self::class, 'poll' ] );
        add_action( 'wp_ajax_sts_nl_disconnect_session', [ self::class, 'disconnect' ] );
        add_action( 'wp_ajax_sts_nl_test_connection',    [ self::class, 'test' ] );
        add_action( 'wp_ajax_sts_nl_campaign_status',    [ self::class, 'campaign_status' ] );
    }

    public static function connect(): void {
        self::verify( 'sts_nl_connect_session' );

        try {
            $existing_id  = (string) STS_NL_Settings::get( 'whatsapp_session_id', '' );
            $existing_key = STS_NL_Settings::whatsapp_session_key();

            if ( $existing_id !== '' && $existing_key !== '' ) {
                wp_send_json_success( [
                    'session_id' => $existing_id,
                    'message'    => 'סשן קיים — מעבר למצב סנכרון.',
                    'reused'     => true,
                ] );
                return;
            }

            if ( $existing_id !== '' ) {
                STS_NL_Settings::update( [ 'whatsapp_session_id' => '', 'whatsapp_session_key' => '' ] );
            }

            $webhook_url    = STS_NL_Secret::webhook_url();
            $webhook_secret = STS_NL_Secret::ensure();

            $result = STS_NL_WhatsAppClient::create_session( $webhook_url, $webhook_secret );

            STS_NL_Settings::update( [
                'whatsapp_session_id'  => $result['session_id'],
                'whatsapp_session_key' => STS_NL_Encryption::encrypt( $result['session_key'] ),
            ] );

            // Sync the daily cap to the API after session creation.
            $cap = (int) STS_NL_Settings::get( 'daily_cap', 50 );
            if ( $cap > 0 ) {
                try {
                    STS_NL_WhatsAppClient::update_daily_cap( $result['session_id'], $cap );
                } catch ( Throwable $e ) {
                    // Non-fatal — cap can be set later.
                }
            }

            wp_send_json_success( [
                'session_id' => $result['session_id'],
                'message'    => 'הסשן נוצר. ממתין לקוד QR.',
            ] );
        } catch ( Throwable $e ) {
            wp_send_json_error( [ 'message' => $e->getMessage() ] );
        }
    }

    public static function poll(): void {
        self::verify( 'sts_nl_poll_session' );

        $session_id  = (string) STS_NL_Settings::get( 'whatsapp_session_id', '' );
        $session_key = STS_NL_Settings::whatsapp_session_key();

        if ( $session_id === '' ) {
            wp_send_json_success( [ 'status' => 'no_session' ] );
            return;
        }

        if ( $session_key === '' ) {
            STS_NL_Settings::update( [ 'whatsapp_session_id' => '', 'whatsapp_session_key' => '' ] );
            wp_send_json_success( [ 'status' => 'gone' ] );
            return;
        }

        try {
            $result = STS_NL_WhatsAppClient::get_session_qr( $session_id );
        } catch ( Throwable $e ) {
            wp_send_json_error( [ 'message' => $e->getMessage() ] );
            return;
        }

        if ( $result['status'] === 'gone' ) {
            STS_NL_Settings::update( [ 'whatsapp_session_id' => '', 'whatsapp_session_key' => '' ] );
            wp_send_json_success( [ 'status' => 'gone' ] );
            return;
        }

        wp_send_json_success( [
            'status' => $result['status'],
            'qr'     => $result['qr'] ?? null,
        ] );
    }

    public static function disconnect(): void {
        self::verify( 'sts_nl_disconnect_session' );

        $session_id = (string) STS_NL_Settings::get( 'whatsapp_session_id', '' );

        try {
            if ( $session_id !== '' ) {
                STS_NL_WhatsAppClient::delete_session( $session_id );
            }
        } catch ( Throwable $e ) {
            // Still clear local state so the user can recover.
        }

        STS_NL_Settings::update( [ 'whatsapp_session_id' => '', 'whatsapp_session_key' => '' ] );
        wp_send_json_success( [ 'message' => 'הסשן נותק.' ] );
    }

    public static function test(): void {
        self::verify( 'sts_nl_test_connection' );
        $result = STS_NL_WhatsAppClient::test_connection();
        if ( $result['ok'] ) {
            wp_send_json_success( [ 'message' => $result['message'] ] );
        } else {
            wp_send_json_error( [ 'message' => $result['message'] ] );
        }
    }

    public static function campaign_status(): void {
        self::verify( 'sts_nl_campaign_status' );

        $campaign_id = sanitize_text_field( $_POST['campaign_id'] ?? '' );
        if ( $campaign_id === '' ) {
            wp_send_json_error( [ 'message' => 'campaign_id חסר.' ] );
            return;
        }

        try {
            $status = STS_NL_WhatsAppClient::get_campaign_status( $campaign_id );
            wp_send_json_success( $status );
        } catch ( Throwable $e ) {
            wp_send_json_error( [ 'message' => $e->getMessage() ] );
        }
    }

    private static function verify( string $action ): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'הרשאות לא מספיקות.' ], 403 );
        }
        check_admin_referer( $action, '_wpnonce' );
    }
}
