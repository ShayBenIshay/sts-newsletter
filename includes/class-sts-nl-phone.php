<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class STS_NL_Phone {

    /**
     * Convert an Israeli phone number to E.164 format (972XXXXXXXXX, no + prefix).
     * "050-1234567" → "972501234567"
     */
    public static function normalize( string $phone ): string {
        $digits = preg_replace( '/\D/', '', $phone );
        if ( $digits === '' ) {
            return '';
        }
        if ( strncmp( $digits, '00', 2 ) === 0 ) {
            $digits = substr( $digits, 2 );
        }
        if ( strncmp( $digits, '972', 3 ) === 0 ) {
            return $digits;
        }
        if ( strncmp( $digits, '0', 1 ) === 0 ) {
            return '972' . substr( $digits, 1 );
        }
        return '972' . $digits;
    }
}
