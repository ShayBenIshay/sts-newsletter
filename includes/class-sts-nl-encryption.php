<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class STS_NL_Encryption {

    private static function key(): string {
        if ( ! defined( 'AUTH_KEY' ) || AUTH_KEY === '' ) {
            throw new RuntimeException( 'AUTH_KEY is not defined in wp-config.php' );
        }
        return hash( 'sha256', AUTH_KEY . 'sts-newsletter', true );
    }

    public static function encrypt( string $plaintext ): string {
        $key    = self::key();
        $iv     = random_bytes( 12 );
        $tag    = '';
        $cipher = openssl_encrypt( $plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16 );
        if ( $cipher === false ) {
            throw new RuntimeException( 'Encryption failed' );
        }
        return base64_encode( $iv . $tag . $cipher );
    }

    public static function decrypt( string $encoded ): string {
        if ( $encoded === '' ) {
            return '';
        }
        $raw = base64_decode( $encoded, true );
        if ( $raw === false || strlen( $raw ) < 28 ) {
            return '';
        }
        $key    = self::key();
        $iv     = substr( $raw, 0, 12 );
        $tag    = substr( $raw, 12, 16 );
        $cipher = substr( $raw, 28 );
        $plain  = openssl_decrypt( $cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
        return $plain === false ? '' : $plain;
    }
}
