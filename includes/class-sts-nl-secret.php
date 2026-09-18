<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class STS_NL_Secret {

    const OPTION = 'sts_newsletter_webhook_secret';

    public static function get(): string {
        return (string) get_option( self::OPTION, '' );
    }

    public static function generate(): string {
        $secret = bin2hex( random_bytes( 32 ) );
        update_option( self::OPTION, $secret, false );
        return $secret;
    }

    public static function ensure(): string {
        $existing = self::get();
        if ( $existing !== '' ) {
            return $existing;
        }
        return self::generate();
    }

    public static function verify( string $candidate ): bool {
        $stored = self::get();
        if ( $stored === '' ) {
            return false;
        }
        return hash_equals( $stored, $candidate );
    }

    public static function webhook_url(): string {
        return rest_url( 'sts-newsletter/v1/callback' );
    }
}
