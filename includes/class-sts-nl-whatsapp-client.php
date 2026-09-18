<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class STS_NL_WhatsAppClient {

    private static function base_url(): string {
        return rtrim( (string) STS_NL_Settings::get( 'whatsapp_base_url', '' ), '/' );
    }

    private const AUTH_BOOTSTRAP = 'bootstrap';
    private const AUTH_SESSION   = 'session';

    private static function auth_key( string $auth ): string {
        return $auth === self::AUTH_BOOTSTRAP
            ? STS_NL_Settings::whatsapp_api_key()
            : STS_NL_Settings::whatsapp_session_key();
    }

    /**
     * @return array{0:int, 1:array|null}
     */
    private static function request( string $method, string $url, string $auth, ?array $body = null ): array {
        $args = [
            'method'      => $method,
            'timeout'     => 15,
            'sslverify'   => true,
            'redirection' => 0,
            'headers'     => [
                'x-api-key'    => self::auth_key( $auth ),
                'Content-Type' => 'application/json',
            ],
        ];

        if ( $body !== null ) {
            $json = wp_json_encode( $body );
            if ( $json === false ) {
                throw new RuntimeException( 'Failed to encode request body as JSON.' );
            }
            $args['body'] = $json;
        }

        $response = wp_remote_request( $url, $args );
        if ( is_wp_error( $response ) ) {
            throw new RuntimeException( 'WhatsApp API error: ' . $response->get_error_message() );
        }

        $code    = (int) wp_remote_retrieve_response_code( $response );
        $raw     = wp_remote_retrieve_body( $response );
        $decoded = $raw !== '' ? json_decode( $raw, true ) : null;

        return [ $code, is_array( $decoded ) ? $decoded : null ];
    }

    private static function post( string $path, array $body ): array {
        $base    = self::base_url();
        $session = (string) STS_NL_Settings::get( 'whatsapp_session_id', '' );

        if ( ! $base || ! $session ) {
            throw new RuntimeException( 'WhatsApp API is not configured (base URL or session ID missing).' );
        }

        return self::request( 'POST', $base . '/sessions/' . rawurlencode( $session ) . $path, self::AUTH_SESSION, $body );
    }

    private static function assert_ok( int $code, string $type ): void {
        if ( $code >= 200 && $code < 300 ) {
            return;
        }
        if ( $code === 503 ) {
            throw new RuntimeException( "WhatsApp session disconnected (503) while sending $type." );
        }
        if ( $code === 429 ) {
            throw new RuntimeException( "WhatsApp rate-limited (429) while sending $type." );
        }
        throw new RuntimeException( "WhatsApp API returned HTTP $code for $type send." );
    }

    public static function test_connection(): array {
        $base    = self::base_url();
        $session = (string) STS_NL_Settings::get( 'whatsapp_session_id', '' );

        if ( ! $base || ! $session ) {
            return [ 'ok' => false, 'message' => 'כתובת בסיס או מזהה סשן אינם מוגדרים.' ];
        }

        try {
            [ $code, $body ] = self::request( 'GET', $base . '/sessions/' . rawurlencode( $session ), self::AUTH_SESSION );
        } catch ( Throwable $e ) {
            return [ 'ok' => false, 'message' => $e->getMessage() ];
        }

        if ( $code === 200 ) {
            $status = $body['status'] ?? 'unknown';
            return [ 'ok' => true, 'message' => "סטטוס סשן: $status", 'status' => $status ];
        }
        if ( $code === 404 ) {
            return [ 'ok' => false, 'message' => 'הסשן לא נמצא בשרת WhatsApp.', 'gone' => true ];
        }
        if ( $code === 401 ) {
            return [ 'ok' => false, 'message' => 'מפתח הסשן לא חוקי (401).' ];
        }
        return [ 'ok' => false, 'message' => "תגובה לא צפויה (HTTP $code) מ-WhatsApp API." ];
    }

    public static function create_session( string $webhook_url, string $webhook_secret ): array {
        $base = self::base_url();
        if ( ! $base ) {
            throw new RuntimeException( 'כתובת בסיס API אינה מוגדרת.' );
        }

        $payload = [ 'webhookUrl' => $webhook_url ];
        if ( $webhook_secret !== '' ) {
            $payload['webhookSecret'] = $webhook_secret;
        }

        [ $code, $body ] = self::request( 'POST', $base . '/sessions', self::AUTH_BOOTSTRAP, $payload );

        if ( $code === 201 && isset( $body['sessionId'], $body['sessionKey'] ) ) {
            return [
                'session_id'  => (string) $body['sessionId'],
                'session_key' => (string) $body['sessionKey'],
            ];
        }
        if ( $code === 401 ) {
            throw new RuntimeException( 'מפתח התקנה לא חוקי (401).' );
        }
        $msg = $body['error'] ?? "HTTP $code";
        throw new RuntimeException( 'יצירת סשן נכשלה: ' . $msg );
    }

    public static function get_session_qr( string $session_id ): array {
        $base = self::base_url();
        if ( ! $base || $session_id === '' ) {
            throw new RuntimeException( 'כתובת בסיס או מזהה סשן אינם מוגדרים.' );
        }

        [ $code, $body ] = self::request( 'GET', $base . '/sessions/' . rawurlencode( $session_id ) . '/qr', self::AUTH_SESSION );

        if ( $code === 404 ) {
            return [ 'status' => 'gone' ];
        }
        if ( $code === 401 ) {
            throw new RuntimeException( 'מפתח הסשן לא חוקי (401).' );
        }
        if ( $code !== 200 || ! is_array( $body ) ) {
            throw new RuntimeException( "תגובה לא צפויה (HTTP $code) בעת שליפת קוד QR." );
        }

        return [
            'status' => (string) ( $body['status'] ?? 'unknown' ),
            'qr'     => isset( $body['qr'] ) ? (string) $body['qr'] : null,
        ];
    }

    public static function delete_session( string $session_id ): void {
        $base = self::base_url();
        if ( ! $base || $session_id === '' ) {
            return;
        }

        [ $code ] = self::request( 'DELETE', $base . '/sessions/' . rawurlencode( $session_id ), self::AUTH_SESSION );
        if ( $code === 200 || $code === 204 || $code === 404 ) {
            return;
        }
        if ( $code === 401 ) {
            throw new RuntimeException( 'מפתח הסשן לא חוקי (401).' );
        }
        throw new RuntimeException( "מחיקת סשן נכשלה (HTTP $code)." );
    }

    public static function update_daily_cap( string $session_id, int $cap ): void {
        $base = self::base_url();
        if ( ! $base || $session_id === '' ) {
            return;
        }
        self::request( 'PATCH', $base . '/sessions/' . rawurlencode( $session_id ), self::AUTH_SESSION, [ 'dailyMarketingCap' => $cap ] );
    }

    /**
     * Send a newsletter campaign. Recipients must be fully composed (body includes footer).
     * Returns ['campaign_id' => string, 'accepted' => int].
     */
    public static function send_newsletter( array $recipients, string $message = '' ): array {
        [ $code, $resp ] = self::post( '/send/newsletter', [
            'message'    => $message,
            'recipients' => $recipients,
        ] );
        self::assert_ok( $code, 'newsletter' );
        return [
            'campaign_id' => (string) ( $resp['campaignId'] ?? '' ),
            'accepted'    => (int) ( $resp['accepted'] ?? 0 ),
        ];
    }

    /**
     * Get aggregate campaign send status.
     * Returns ['total','queued','sending','sent','dropped','failed'].
     */
    public static function get_campaign_status( string $campaign_id ): array {
        $base    = self::base_url();
        $session = (string) STS_NL_Settings::get( 'whatsapp_session_id', '' );

        if ( ! $base || ! $session ) {
            throw new RuntimeException( 'WhatsApp API is not configured.' );
        }

        [ $code, $body ] = self::request(
            'GET',
            $base . '/sessions/' . rawurlencode( $session ) . '/campaigns/' . rawurlencode( $campaign_id ),
            self::AUTH_SESSION
        );

        if ( $code !== 200 || ! is_array( $body ) ) {
            throw new RuntimeException( "Campaign status request failed (HTTP $code)." );
        }

        return $body;
    }
}
