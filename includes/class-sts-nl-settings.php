<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class STS_NL_Settings {

    const OPTION = 'sts_newsletter_settings';

    private static function defaults(): array {
        return [
            'whatsapp_base_url'      => '',
            'whatsapp_api_key'       => '',
            'whatsapp_session_id'    => '',
            'whatsapp_session_key'   => '',
            'daily_cap'              => 50,
            'advertiser_name'        => '',
            'advertiser_contact'     => '',
            'operating_mode'         => 'dev',
            'dev_phone_override'     => '',
            'max_recipients_per_send' => 50,
        ];
    }

    public static function all(): array {
        return wp_parse_args( get_option( self::OPTION, [] ), self::defaults() );
    }

    public static function get( string $key, $default = null ) {
        $all = self::all();
        return $all[ $key ] ?? $default;
    }

    public static function operating_mode(): string {
        return self::get( 'operating_mode', 'dev' ) === 'live' ? 'live' : 'dev';
    }

    public static function is_live(): bool {
        return self::operating_mode() === 'live';
    }

    public static function dev_phone_override(): string {
        return trim( (string) self::get( 'dev_phone_override', '' ) );
    }

    public static function whatsapp_api_key(): string {
        return STS_NL_Encryption::decrypt( (string) self::get( 'whatsapp_api_key', '' ) );
    }

    public static function whatsapp_session_key(): string {
        return STS_NL_Encryption::decrypt( (string) self::get( 'whatsapp_session_key', '' ) );
    }

    /**
     * Merge $partial into the stored settings array without running the form
     * sanitize callback (which would re-encrypt already-encrypted values).
     */
    public static function update( array $partial ): void {
        $current = get_option( self::OPTION, [] );
        if ( ! is_array( $current ) ) {
            $current = [];
        }
        $merged = array_merge( $current, $partial );

        $hook     = 'sanitize_option_' . self::OPTION;
        $callback = [ 'STS_Newsletter_Admin', 'sanitize_settings' ];
        $priority = has_filter( $hook, $callback );

        if ( $priority !== false ) {
            remove_filter( $hook, $callback, $priority );
        }

        update_option( self::OPTION, $merged );

        if ( $priority !== false ) {
            add_filter( $hook, $callback, $priority );
        }
    }
}
