<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class STS_NL_Unsubscribe {

    const META_KEY = 'newsletter_unsub_token';

    // -------------------------------------------------------------------------
    // Short URL: /unsub/TOKEN  (registered via WP rewrite rules)
    // -------------------------------------------------------------------------

    public static function register_short_url(): void {
        add_rewrite_rule(
            '^unsub/([A-Za-z0-9_-]{16,64})/?$',
            'index.php?sts_unsub_token=$matches[1]',
            'top'
        );
    }

    public static function add_query_vars( array $vars ): array {
        $vars[] = 'sts_unsub_token';
        return $vars;
    }

    public static function handle_short_url(): void {
        $token = get_query_var( 'sts_unsub_token' );
        if ( ! $token ) {
            return;
        }

        if ( isset( $_SERVER['REQUEST_METHOD'] ) && $_SERVER['REQUEST_METHOD'] === 'POST' ) {
            self::execute_unsubscribe( $token );
        } else {
            self::show_confirm_page( $token );
        }
    }

    private static function show_confirm_page( string $token, string $action_url = '' ): void {
        if ( $action_url === '' ) {
            $action_url = home_url( '/unsub/' . rawurlencode( $token ) );
        }
        wp_die(
            '<div dir="rtl" style="font-family:sans-serif;max-width:480px;margin:60px auto;text-align:center;">' .
            '<h2>הסרה מרשימת תפוצה</h2>' .
            '<p>האם אתה בטוח שברצונך להסיר את עצמך מרשימת התפוצה?<br>לא תקבל הודעות שיווקיות נוספות מאיתנו.</p>' .
            '<form method="post" action="' . esc_url( $action_url ) . '">' .
            '<button type="submit" style="background:#dc3232;color:#fff;border:none;padding:12px 28px;font-size:16px;border-radius:4px;cursor:pointer;margin:8px;">כן, הסר אותי</button>' .
            '&nbsp;<a href="' . esc_url( home_url() ) . '" style="display:inline-block;padding:12px 20px;font-size:15px;color:#555;">ביטול</a>' .
            '</form>' .
            '</div>',
            'הסרה מרשימת תפוצה',
            [ 'response' => 200 ]
        );
    }

    private static function execute_unsubscribe( string $token ): void {
        $users = get_users( [
            'meta_key'   => self::META_KEY,
            'meta_value' => sanitize_text_field( $token ),
            'number'     => 1,
        ] );

        if ( ! empty( $users ) ) {
            $user = $users[0];
            update_user_meta( $user->ID, 'newsletter_subscribed', 'no' );
            update_user_meta( $user->ID, 'newsletter_unsubscribe_date', current_time( 'mysql' ) );
        }

        wp_die(
            '<div dir="rtl" style="font-family:sans-serif;max-width:480px;margin:60px auto;text-align:center;">' .
            '<h2>הוסרת מרשימת התפוצה</h2>' .
            '<p>בקשתך התקבלה. לא תקבל הודעות שיווקיות נוספות מאיתנו.</p>' .
            '<p><a href="' . esc_url( home_url() ) . '">חזרה לאתר</a></p>' .
            '</div>',
            'הסרה מרשימת תפוצה',
            [ 'response' => 200 ]
        );
    }

    // -------------------------------------------------------------------------
    // REST routes (kept for backward compatibility with already-sent messages)
    // -------------------------------------------------------------------------

    public static function register_routes(): void {
        register_rest_route( 'sts-newsletter/v1', '/unsubscribe/(?P<token>[A-Za-z0-9_-]{16,64})', [
            [
                'methods'             => 'GET',
                'callback'            => [ self::class, 'handle_get' ],
                'permission_callback' => '__return_true',
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ self::class, 'handle_post' ],
                'permission_callback' => '__return_true',
            ],
        ] );

        register_rest_route( 'sts-newsletter/v1', '/callback', [
            'methods'             => [ 'GET', 'POST' ],
            'callback'            => [ self::class, 'noop_callback' ],
            'permission_callback' => '__return_true',
        ] );
    }

    public static function handle_get( WP_REST_Request $request ): void {
        $token      = sanitize_text_field( $request->get_param( 'token' ) );
        $action_url = rest_url( 'sts-newsletter/v1/unsubscribe/' . rawurlencode( $token ) );
        self::show_confirm_page( $token, $action_url );
    }

    public static function handle_post( WP_REST_Request $request ): void {
        self::execute_unsubscribe( sanitize_text_field( $request->get_param( 'token' ) ) );
    }

    public static function noop_callback(): WP_REST_Response {
        return new WP_REST_Response( [ 'ok' => true ], 200 );
    }

    // -------------------------------------------------------------------------
    // Token helpers
    // -------------------------------------------------------------------------

    public static function token_for( int $user_id ): string {
        $existing = get_user_meta( $user_id, self::META_KEY, true );
        if ( $existing && strlen( $existing ) >= 16 ) {
            return $existing;
        }
        $token = rtrim( strtr( base64_encode( random_bytes( 18 ) ), '+/', '-_' ), '=' );
        update_user_meta( $user_id, self::META_KEY, $token );
        return $token;
    }

    public static function url_for( int $user_id ): string {
        // Fall back to REST URL on sites with plain permalinks where rewrite rules don't apply.
        if ( ! get_option( 'permalink_structure' ) ) {
            return rest_url( 'sts-newsletter/v1/unsubscribe/' . self::token_for( $user_id ) );
        }
        return home_url( '/unsub/' . self::token_for( $user_id ) );
    }
}
