<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class STS_Newsletter_Admin {

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
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
    }

    public function enqueue_admin_scripts( $hook ) {
        if ( strpos( $hook, 'newsletter' ) === false ) {
            return;
        }
        wp_enqueue_editor();
    }

    public function register_menu() {
        add_menu_page(
            'מועדון לקוחות',
            'מועדון לקוחות',
            'manage_options',
            'newsletter-manager',
            array( $this, 'render_send_page' ),
            'dashicons-whatsapp',
            30
        );

        add_submenu_page(
            'newsletter-manager',
            'שלח ניוזלטר',
            'שלח ניוזלטר',
            'manage_options',
            'newsletter-manager',
            array( $this, 'render_send_page' )
        );

        add_submenu_page(
            'newsletter-manager',
            'מנויים',
            'מנויים',
            'manage_options',
            'newsletter-subscribers',
            array( $this, 'render_subscribers_page' )
        );

        add_submenu_page(
            'newsletter-manager',
            'הגדרות',
            'הגדרות',
            'manage_options',
            'newsletter-settings',
            array( $this, 'render_settings_page' )
        );
    }

    // -------------------------------------------------------------------------
    // SEND PAGE
    // -------------------------------------------------------------------------

    public function render_send_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $notice        = '';
        $notice_type   = 'info';
        $campaign_id   = '';
        $accepted      = 0;
        $is_live       = STS_NL_Settings::is_live();
        $session_id    = (string) STS_NL_Settings::get( 'whatsapp_session_id', '' );
        $session_key   = STS_NL_Settings::whatsapp_session_key();
        $base_url      = (string) STS_NL_Settings::get( 'whatsapp_base_url', '' );
        $is_configured = $session_id !== '' && $session_key !== '' && $base_url !== '';

        if ( isset( $_POST['send_newsletter'] ) ) {
            check_admin_referer( 'send_newsletter_action' );

            // Guard: session must be configured.
            if ( ! $is_configured ) {
                $notice      = 'יש להגדיר ולחבר סשן WhatsApp בהגדרות לפני השליחה.';
                $notice_type = 'error';
            } elseif ( ! $is_live && STS_NL_Settings::dev_phone_override() === '' ) {
                $notice      = 'מצב פיתוח פעיל אך מספר טלפון לפיתוח אינו מוגדר. הגדר "טלפון לפיתוח" בהגדרות לפני השליחה.';
                $notice_type = 'error';
            } else {
                $result = $this->process_send();
                $notice      = $result['notice'];
                $notice_type = $result['type'];
                $campaign_id = $result['campaign_id'] ?? '';
                $accepted    = $result['accepted'] ?? 0;
            }
        }

        // Gather subscriber list for checkbox table.
        $channel_filter = sanitize_text_field( $_GET['channel'] ?? 'whatsapp' );
        $topic_filter   = sanitize_text_field( $_GET['topic']   ?? '' );

        // 'whatsapp' query uses channel IN ('whatsapp','both'), so one call covers both groups.
        $eligible = array_values( $this->get_subscribers( 'whatsapp', $topic_filter ) );

        $max_cap = (int) STS_NL_Settings::get( 'max_recipients_per_send', 50 );

        $wa_count    = count( $this->get_subscribers( 'whatsapp' ) );
        $email_count = count( $this->get_subscribers( 'email' ) );
        $subscribers = $this->get_subscribers();

        ?>
        <div class="wrap" dir="rtl">
            <h1>שלח ניוזלטר WhatsApp</h1>

            <?php if ( ! $is_live ) : ?>
            <div class="notice notice-warning inline" style="margin:10px 0 20px;">
                <p>
                    <strong>⚠ מצב פיתוח (Dev)</strong> —
                    כל ההודעות יישלחו למספר הפיתוח
                    <?php
                    $dev = STS_NL_Settings::dev_phone_override();
                    echo $dev ? '(<code>' . esc_html( $dev ) . '</code>)' : '<strong style="color:#dc3232;">לא הוגדר!</strong>';
                    ?>
                    ולא ללקוחות האמיתיים.
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=newsletter-settings' ) ); ?>">שנה בהגדרות</a>
                </p>
            </div>
            <?php endif; ?>

            <?php if ( $notice ) : ?>
            <div class="notice notice-<?php echo esc_attr( $notice_type ); ?> is-dismissible">
                <p><?php echo esc_html( $notice ); ?></p>
            </div>
            <?php endif; ?>

            <?php if ( ! $is_configured ) : ?>
            <div class="notice notice-error">
                <p>סשן WhatsApp אינו מחובר. <a href="<?php echo esc_url( admin_url( 'admin.php?page=newsletter-settings' ) ); ?>">עבור להגדרות</a> כדי להתחבר.</p>
            </div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="card" style="max-width:800px;margin-top:20px;padding:20px;">
                <h2>סטטיסטיקות</h2>
                <table class="widefat" style="margin-top:10px;">
                    <tbody>
                        <tr><td><strong>סך הכל מנויים:</strong></td><td><?php echo count( $subscribers ); ?></td></tr>
                        <tr><td><strong>מנויי WhatsApp (כולל שניהם):</strong></td><td><?php echo $wa_count; ?></td></tr>
                        <tr><td><strong>מנויי אימייל בלבד:</strong></td><td><?php echo $email_count; ?></td></tr>
                    </tbody>
                </table>
            </div>

            <!-- Compose form -->
            <div class="card" style="max-width:900px;margin-top:20px;padding:20px;">
                <h2>הרכב ושלח הודעה</h2>

                <form method="post" action="" id="sts-nl-send-form">
                    <?php wp_nonce_field( 'send_newsletter_action' ); ?>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="message">הודעה</label></th>
                            <td>
                                <?php
                                $editor_content = isset( $_POST['message'] ) ? wp_kses_post( wp_unslash( $_POST['message'] ) ) : '';
                                wp_editor( $editor_content, 'message', array(
                                    'textarea_rows' => 8,
                                    'media_buttons' => false,
                                    'teeny'         => true,
                                    'quicktags'     => false,
                                ) );
                                ?>
                                <p class="description">הטקסט ישלח כהודעת WhatsApp פשוטה עם כותרת ציות בסוף.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="topic">נושא (סינון)</label></th>
                            <td>
                                <select name="topic" id="topic" onchange="var u=new URL(location.href);u.searchParams.set('topic',this.value);location.href=u.toString();">
                                    <option value="" <?php selected( $topic_filter, '' ); ?>>כל הנושאים</option>
                                    <option value="new_collections" <?php selected( $topic_filter, 'new_collections' ); ?>>קולקציות חדשות</option>
                                    <option value="sales"           <?php selected( $topic_filter, 'sales' ); ?>>מבצעים והנחות</option>
                                    <option value="tips"            <?php selected( $topic_filter, 'tips' ); ?>>טיפים לטיפוח תכשיטים</option>
                                    <option value="events"          <?php selected( $topic_filter, 'events' ); ?>>אירועים מיוחדים</option>
                                </select>
                            </td>
                        </tr>
                    </table>

                    <!-- Recipient checkbox table -->
                    <h3 style="margin-top:24px;">בחר נמענים
                        <span id="sts-nl-selected-count" style="font-weight:normal;font-size:13px;color:#555;margin-right:10px;">0 נבחרו</span>
                        <span style="font-size:12px;color:#888;">(מקסימום <?php echo esc_html( $max_cap ); ?>)</span>
                    </h3>

                    <?php if ( empty( $eligible ) ) : ?>
                        <p style="color:#888;">אין מנויי WhatsApp<?php echo $topic_filter ? ' לנושא זה' : ''; ?>.</p>
                    <?php else : ?>
                    <table class="wp-list-table widefat fixed striped" style="margin-top:8px;">
                        <thead>
                            <tr>
                                <th style="width:36px;">
                                    <input type="checkbox" id="sts-nl-select-all" title="בחר הכל">
                                </th>
                                <th>שם</th>
                                <th>אימייל</th>
                                <th>טלפון</th>
                                <th>ערוץ</th>
                                <th>נושאים</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $topic_labels = [
                            'new_collections' => 'קולקציות',
                            'sales'           => 'מבצעים',
                            'tips'            => 'טיפים',
                            'events'          => 'אירועים',
                        ];
                        foreach ( $eligible as $user ) :
                            $phone     = get_user_meta( $user->ID, 'billing_phone', true );
                            $norm      = STS_NL_Phone::normalize( (string) $phone );
                            $has_phone = $norm !== '';
                            $channel   = get_user_meta( $user->ID, 'newsletter_channel', true );
                            $topics    = get_user_meta( $user->ID, 'newsletter_topics', true );
                            $topic_str = '';
                            if ( is_array( $topics ) && ! empty( $topics ) ) {
                                $topic_str = implode( ', ', array_map( function( $t ) use ( $topic_labels ) {
                                    return $topic_labels[ $t ] ?? $t;
                                }, $topics ) );
                            }
                        ?>
                            <tr style="<?php echo ! $has_phone ? 'opacity:0.45;' : ''; ?>">
                                <td>
                                    <input type="checkbox" name="recipients[]" value="<?php echo esc_attr( $user->ID ); ?>"
                                           class="sts-nl-recipient"
                                           <?php echo ! $has_phone ? 'disabled title="אין מספר טלפון"' : ''; ?>>
                                </td>
                                <td><?php echo esc_html( $user->display_name ); ?></td>
                                <td><?php echo esc_html( $user->user_email ); ?></td>
                                <td><?php echo esc_html( $norm ?: '—' ); ?></td>
                                <td><?php echo $channel === 'both' ? 'שניהם' : 'WhatsApp'; ?></td>
                                <td><?php echo esc_html( $topic_str ?: '—' ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>

                    <p class="submit">
                        <input type="submit" name="send_newsletter" class="button button-primary button-large"
                               value="שלח ניוזלטר עכשיו"
                               <?php echo ! $is_configured ? 'disabled' : ''; ?>>
                    </p>
                </form>
            </div>

            <?php if ( $campaign_id ) : ?>
            <!-- Progress bar -->
            <div class="card" style="max-width:900px;margin-top:20px;padding:20px;" id="sts-nl-progress-card">
                <h3>התקדמות שליחה — <?php echo esc_html( $accepted ); ?> נמענים בתור</h3>
                <div style="background:#e0e0e0;border-radius:4px;height:20px;margin:12px 0;">
                    <div id="sts-nl-progress-bar" style="background:#46b450;height:100%;border-radius:4px;width:0%;transition:width 0.5s;"></div>
                </div>
                <p id="sts-nl-progress-text" style="font-size:13px;color:#555;">טוען…</p>
                <p style="font-size:12px;color:#888;">ההודעות נשלחות בהדרגה בהתאם לתזמון כדי להימנע מחסימה. הדף ניתן לסגירה.</p>
            </div>
            <script>
            (function($){
                var campaignId  = <?php echo wp_json_encode( $campaign_id ); ?>;
                var ajaxUrl     = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
                var nonce       = <?php echo wp_json_encode( wp_create_nonce( 'sts_nl_campaign_status' ) ); ?>;
                var pollTimer   = null;

                function poll() {
                    $.post(ajaxUrl, { action: 'sts_nl_campaign_status', _wpnonce: nonce, campaign_id: campaignId })
                        .done(function(res) {
                            if (!res || !res.success) return;
                            var d = res.data;
                            var done  = (d.sent || 0) + (d.dropped || 0) + (d.failed || 0);
                            var total = d.total || 1;
                            var pct   = Math.round(done / total * 100);
                            $('#sts-nl-progress-bar').css('width', pct + '%');
                            $('#sts-nl-progress-text').text(
                                'נשלחו: ' + (d.sent||0) + ' / ' + d.total +
                                (d.dropped ? ' | דולגו: ' + d.dropped : '') +
                                (d.failed  ? ' | נכשלו: ' + d.failed  : '') +
                                ' | בתור: ' + (d.queued||0)
                            );
                            if ((d.queued || 0) + (d.sending || 0) === 0) {
                                clearInterval(pollTimer);
                                $('#sts-nl-progress-text').append(' — <strong>הושלם!</strong>');
                            }
                        });
                }
                pollTimer = setInterval(poll, 3000);
                poll();
            })(jQuery);
            </script>
            <?php endif; ?>
        </div>

        <script>
        (function($){
            var $checkboxes = $('.sts-nl-recipient');
            var $counter    = $('#sts-nl-selected-count');
            var maxCap      = <?php echo (int) $max_cap; ?>;

            function updateCount() {
                var n = $checkboxes.filter(':checked').length;
                $counter.text(n + ' נבחרו');
            }
            $('#sts-nl-select-all').on('change', function() {
                $checkboxes.not(':disabled').prop('checked', this.checked);
                updateCount();
            });
            $checkboxes.on('change', function() {
                var n = $checkboxes.filter(':checked').length;
                if (n > maxCap) {
                    this.checked = false;
                    alert('לא ניתן לבחור יותר מ-' + maxCap + ' נמענים בשליחה אחת.');
                }
                updateCount();
                if (!this.checked) $('#sts-nl-select-all').prop('checked', false);
            });

            $('#sts-nl-send-form').on('submit', function(e) {
                var n      = $checkboxes.filter(':checked').length;
                var isLive = <?php echo $is_live ? 'true' : 'false'; ?>;
                if (n === 0) {
                    e.preventDefault();
                    alert('יש לבחור לפחות נמען אחד.');
                    return;
                }
                var msg = isLive
                    ? 'שליחה LIVE ל-' + n + ' נמענים. האם אתה בטוח?'
                    : 'שליחה במצב פיתוח ל-' + n + ' נמענים (יישלח למספר הפיתוח). האם אתה בטוח?';
                if (!confirm(msg)) { e.preventDefault(); }
            });
        })(jQuery);
        </script>
        <?php
    }

    private function process_send(): array {
        $message_html = isset( $_POST['message'] ) ? wp_kses_post( wp_unslash( $_POST['message'] ) ) : '';
        // Convert block/line elements to newlines before stripping — TinyMCE wraps paragraphs in <p>.
        $message_html = preg_replace( '/<br\s*\/?\s*>/i', "\n", $message_html );
        $message_html = preg_replace( '/<\/p\s*>/i', "\n\n", $message_html );
        $message      = trim( wp_strip_all_tags( $message_html ) );

        if ( trim( $message ) === '' ) {
            return [ 'notice' => 'יש להזין הודעה.', 'type' => 'error' ];
        }

        $posted_ids = isset( $_POST['recipients'] ) ? array_map( 'absint', (array) $_POST['recipients'] ) : [];
        if ( empty( $posted_ids ) ) {
            return [ 'notice' => 'יש לבחור לפחות נמען אחד.', 'type' => 'error' ];
        }

        $max_cap = (int) STS_NL_Settings::get( 'max_recipients_per_send', 50 );
        if ( count( $posted_ids ) > $max_cap ) {
            return [ 'notice' => "נבחרו יותר מ-{$max_cap} נמענים (מקסימום מותר). הקטן את הבחירה.", 'type' => 'error' ];
        }

        // Dev override check.
        $is_live   = STS_NL_Settings::is_live();
        $dev_phone = STS_NL_Settings::dev_phone_override();
        if ( ! $is_live && $dev_phone === '' ) {
            return [ 'notice' => 'מצב פיתוח פעיל אך מספר טלפון לפיתוח אינו מוגדר.', 'type' => 'error' ];
        }

        // 'whatsapp' query uses channel IN ('whatsapp','both') — one call covers both groups.
        $eligible_ids = [];
        foreach ( $this->get_subscribers( 'whatsapp' ) as $u ) {
            $eligible_ids[ $u->ID ] = $u;
        }

        $advertiser_name    = (string) STS_NL_Settings::get( 'advertiser_name', '' );
        $advertiser_contact = (string) STS_NL_Settings::get( 'advertiser_contact', '' );

        $recipients = [];
        $skipped    = 0;

        foreach ( $posted_ids as $uid ) {
            if ( ! isset( $eligible_ids[ $uid ] ) ) {
                continue;
            }
            $user = $eligible_ids[ $uid ];

            $raw_phone = get_user_meta( $user->ID, 'billing_phone', true );
            $phone     = STS_NL_Phone::normalize( (string) $raw_phone );

            if ( $phone === '' ) {
                $skipped++;
                continue;
            }

            // In dev mode, redirect all sends to the dev phone.
            $send_to = $is_live ? $phone : STS_NL_Phone::normalize( $dev_phone );

            $unsub_url = STS_NL_Unsubscribe::url_for( $user->ID );
            $identity  = $advertiser_name;
            if ( $advertiser_contact !== '' ) {
                $identity .= ' | ' . $advertiser_contact;
            }
            $footer = "\n\n—\nפרסומת" . ( $identity !== '' ? " | {$identity}" : '' ) . " | הסרה: {$unsub_url}";

            $recipients[] = [
                'phone' => $send_to,
                'name'  => $user->display_name,
                'body'  => $message . $footer,
            ];
        }

        if ( empty( $recipients ) ) {
            $notice = 'אין נמענים תקפים לשליחה.';
            if ( $skipped > 0 ) {
                $notice .= " (דולגו {$skipped} ללא מספר טלפון)";
            }
            return [ 'notice' => $notice, 'type' => 'error' ];
        }

        // In dev mode send only 1 test message regardless of how many were selected.
        $real_recipient_count = count( $recipients );
        if ( ! $is_live ) {
            $recipients = [ $recipients[0] ];
        }

        try {
            $result = STS_NL_WhatsAppClient::send_newsletter( $recipients );
        } catch ( Throwable $e ) {
            return [ 'notice' => 'שגיאה בשליחה: ' . $e->getMessage(), 'type' => 'error' ];
        }

        $campaign_id = $result['campaign_id'];
        $accepted    = $result['accepted'];

        set_transient( 'sts_nl_last_campaign', $campaign_id, DAY_IN_SECONDS );

        $notice = "הניוזלטר עבר לתור השליחה. נשלח ל-{$accepted} נמענים";
        if ( $skipped > 0 ) {
            $notice .= " (דולגו {$skipped} ללא טלפון)";
        }
        if ( ! $is_live ) {
            $notice .= " — מצב פיתוח: נשלחה הודעת בדיקה אחת למספר הפיתוח (מתוך {$real_recipient_count} נמענים שנבחרו).";
        }
        $notice .= '.';

        return [
            'notice'      => $notice,
            'type'        => 'success',
            'campaign_id' => $campaign_id,
            'accepted'    => $accepted,
        ];
    }

    // -------------------------------------------------------------------------
    // SUBSCRIBERS PAGE
    // -------------------------------------------------------------------------

    public function render_subscribers_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Show ALL users who ever had newsletter_subscribed set (active + unsubscribed).
        $all_contacts = get_users( [
            'meta_key'     => 'newsletter_subscribed',
            'meta_compare' => 'EXISTS',
        ] );

        if ( isset( $_POST['export_subscribers'] ) ) {
            check_admin_referer( 'export_subscribers_action' );

            header( 'Content-Type: text/csv; charset=utf-8' );
            header( 'Content-Disposition: attachment; filename=newsletter-subscribers-' . date( 'Y-m-d' ) . '.csv' );

            $output = fopen( 'php://output', 'w' );
            fputcsv( $output, array( 'שם', 'אימייל', 'טלפון', 'ערוץ מועדף', 'נושאים', 'סטטוס', 'תאריך הרשמה', 'תאריך הסרה' ) );

            foreach ( $all_contacts as $user ) {
                $channel       = get_user_meta( $user->ID, 'newsletter_channel', true );
                $topics        = get_user_meta( $user->ID, 'newsletter_topics', true );
                $consent_date  = get_user_meta( $user->ID, 'newsletter_consent_date', true );
                $unsub_date    = get_user_meta( $user->ID, 'newsletter_unsubscribe_date', true );
                $phone         = get_user_meta( $user->ID, 'billing_phone', true );
                $subscribed    = get_user_meta( $user->ID, 'newsletter_subscribed', true );

                fputcsv( $output, array(
                    $user->display_name,
                    $user->user_email,
                    $phone,
                    $channel,
                    is_array( $topics ) ? implode( ', ', $topics ) : '',
                    $subscribed === 'yes' ? 'פעיל' : 'הוסר',
                    $consent_date,
                    $unsub_date,
                ) );
            }

            fclose( $output );
            exit;
        }

        $topic_labels = array(
            'new_collections' => 'קולקציות',
            'sales'           => 'מבצעים',
            'tips'            => 'טיפים',
            'events'          => 'אירועים',
        );

        $active_count = count( array_filter( $all_contacts, function( $u ) {
            return get_user_meta( $u->ID, 'newsletter_subscribed', true ) === 'yes';
        } ) );

        ?>
        <div class="wrap" dir="rtl">
            <h1>מנויי Newsletter</h1>

            <p style="color:#555;margin-top:8px;">
                סה"כ ברשומות: <strong><?php echo count( $all_contacts ); ?></strong> |
                פעילים: <strong style="color:#46b450;"><?php echo $active_count; ?></strong> |
                הוסרו: <strong style="color:#dc3232;"><?php echo count( $all_contacts ) - $active_count; ?></strong>
            </p>

            <form method="post" style="margin: 16px 0;">
                <?php wp_nonce_field( 'export_subscribers_action' ); ?>
                <input type="submit" name="export_subscribers" class="button" value="ייצא לקובץ CSV">
            </form>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:120px;">סטטוס</th>
                        <th>שם</th>
                        <th>אימייל</th>
                        <th>טלפון</th>
                        <th>ערוץ</th>
                        <th>נושאים</th>
                        <th>תאריך הרשמה</th>
                        <th>תאריך הסרה</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $all_contacts ) ) : ?>
                        <tr>
                            <td colspan="8" style="text-align:center;">אין רשומות עדיין</td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ( $all_contacts as $user ) :
                            $subscribed   = get_user_meta( $user->ID, 'newsletter_subscribed', true );
                            $is_active    = $subscribed === 'yes';
                            $channel      = get_user_meta( $user->ID, 'newsletter_channel', true );
                            $topics       = get_user_meta( $user->ID, 'newsletter_topics', true );
                            $consent_date = get_user_meta( $user->ID, 'newsletter_consent_date', true );
                            $unsub_date   = get_user_meta( $user->ID, 'newsletter_unsubscribe_date', true );
                            $phone        = get_user_meta( $user->ID, 'billing_phone', true );
                            $topic_str    = '';
                            if ( is_array( $topics ) && ! empty( $topics ) ) {
                                $topic_str = implode( ', ', array_map( function( $t ) use ( $topic_labels ) {
                                    return $topic_labels[ $t ] ?? $t;
                                }, $topics ) );
                            }
                        ?>
                            <tr style="<?php echo ! $is_active ? 'opacity:0.6;' : ''; ?>">
                                <td>
                                    <?php if ( $is_active ) : ?>
                                        <span style="color:#46b450;font-weight:600;">● פעיל</span>
                                    <?php else : ?>
                                        <span style="color:#dc3232;font-weight:600;">● הוסר</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html( $user->display_name ?: $user->user_login ); ?></td>
                                <td><?php echo esc_html( $user->user_email ); ?></td>
                                <td><?php echo esc_html( $phone ?: '—' ); ?></td>
                                <td>
                                    <?php
                                    echo $channel === 'whatsapp' ? 'WhatsApp' :
                                         ( $channel === 'email' ? 'אימייל' :
                                         ( $channel === 'both' ? 'שניהם' : '—' ) );
                                    ?>
                                </td>
                                <td><?php echo esc_html( $topic_str ?: '—' ); ?></td>
                                <td><?php echo $consent_date ? esc_html( date( 'd/m/Y', strtotime( $consent_date ) ) ) : '—'; ?></td>
                                <td><?php echo $unsub_date ? esc_html( date( 'd/m/Y', strtotime( $unsub_date ) ) ) : '—'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // SETTINGS PAGE
    // -------------------------------------------------------------------------

    public static function sanitize_settings( $input ): array {
        $current = get_option( STS_NL_Settings::OPTION, [] );
        if ( ! is_array( $current ) ) {
            $current = [];
        }
        if ( ! is_array( $input ) ) {
            return $current;
        }

        $out = $current;

        $out['whatsapp_base_url']       = esc_url_raw( $input['whatsapp_base_url']       ?? '' );
        $out['advertiser_name']         = sanitize_text_field( $input['advertiser_name']         ?? '' );
        $out['advertiser_contact']      = sanitize_text_field( $input['advertiser_contact']      ?? '' );
        $out['daily_cap']               = max( 1, absint( $input['daily_cap']               ?? 50 ) );
        $out['max_recipients_per_send'] = max( 1, absint( $input['max_recipients_per_send'] ?? 50 ) );
        $out['dev_phone_override']      = sanitize_text_field( $input['dev_phone_override']      ?? '' );

        // Operating mode: require confirmation (modal-guarded on the front-end).
        $new_mode = ( isset( $input['operating_mode'] ) && $input['operating_mode'] === 'live' ) ? 'live' : 'dev';
        $out['operating_mode'] = $new_mode;

        // Bootstrap key: keep existing ciphertext when field is empty.
        if ( isset( $input['whatsapp_api_key'] ) && trim( $input['whatsapp_api_key'] ) !== '' ) {
            $out['whatsapp_api_key'] = STS_NL_Encryption::encrypt( trim( $input['whatsapp_api_key'] ) );
        } else {
            $out['whatsapp_api_key'] = $current['whatsapp_api_key'] ?? '';
        }

        // Session id/key are flow-managed — never overwritten from the form.
        $out['whatsapp_session_id']  = $current['whatsapp_session_id']  ?? '';
        $out['whatsapp_session_key'] = $current['whatsapp_session_key'] ?? '';

        return $out;
    }

    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( isset( $_POST['save_newsletter_settings'] ) ) {
            check_admin_referer( 'newsletter_settings_action' );
            $sanitized = self::sanitize_settings( $_POST[ STS_NL_Settings::OPTION ] ?? [] );
            update_option( STS_NL_Settings::OPTION, $sanitized );

            // Sync the daily cap to the API if a session is active.
            $sid = (string) STS_NL_Settings::get( 'whatsapp_session_id', '' );
            if ( $sid !== '' ) {
                try {
                    STS_NL_WhatsAppClient::update_daily_cap( $sid, (int) $sanitized['daily_cap'] );
                } catch ( Throwable $e ) {
                    // Non-fatal.
                }
            }

            echo '<div class="notice notice-success"><p>ההגדרות נשמרו בהצלחה!</p></div>';
        }

        $s    = STS_NL_Settings::all();
        $mode = $s['operating_mode'] ?? 'dev';

        $connection_state = $this->get_connection_state( $s );
        $session_id       = trim( (string) ( $s['whatsapp_session_id'] ?? '' ) );

        ?>
        <div class="wrap" dir="rtl">
            <h1>הגדרות Newsletter</h1>

            <?php if ( $mode === 'dev' ) : ?>
            <div class="notice notice-warning inline" style="margin:10px 0 20px;">
                <p><strong>מצב פיתוח (Dev)</strong> — הודעות יישלחו למספר הפיתוח בלבד. עבור למצב Live לשליחה ללקוחות אמיתיים.</p>
            </div>
            <?php endif; ?>

            <form method="post" action="">
                <?php wp_nonce_field( 'newsletter_settings_action' ); ?>

                <!-- WhatsApp API -->
                <div class="card" style="max-width:800px;margin-top:20px;padding:20px;">
                    <h2>הגדרות WhatsApp API</h2>
                    <table class="form-table">
                        <?php $this->settings_text_row( 'whatsapp_base_url', 'כתובת API', $s['whatsapp_base_url'] ?? '', 'https://wa.yourhost.com' ); ?>
                        <?php $this->settings_password_row( 'whatsapp_api_key', 'מפתח Bootstrap' ); ?>
                        <tr>
                            <th scope="row"><label for="daily_cap">מכסה יומית (הודעות)</label></th>
                            <td>
                                <input type="number" id="daily_cap" name="<?php echo esc_attr( STS_NL_Settings::OPTION ); ?>[daily_cap]"
                                       value="<?php echo esc_attr( $s['daily_cap'] ?? 50 ); ?>" min="1" class="small-text">
                                <p class="description">כמה הודעות WhatsApp ישלח השרת לכל היותר ביום. ברירת מחדל של שרת: 30.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="max_recipients_per_send">מקסימום נמענים בשליחה</label></th>
                            <td>
                                <input type="number" id="max_recipients_per_send" name="<?php echo esc_attr( STS_NL_Settings::OPTION ); ?>[max_recipients_per_send]"
                                       value="<?php echo esc_attr( $s['max_recipients_per_send'] ?? 50 ); ?>" min="1" class="small-text">
                                <p class="description">מגבלת בטיחות — לא ניתן לשלוח ביותר ממספר זה של נמענים בשליחה אחת.</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Advertiser info -->
                <div class="card" style="max-width:800px;margin-top:20px;padding:20px;">
                    <h2>פרטי המפרסם (לכותרת ציות)</h2>
                    <table class="form-table">
                        <?php $this->settings_text_row( 'advertiser_name', 'שם העסק', $s['advertiser_name'] ?? '', 'שם העסק שלך' ); ?>
                        <?php $this->settings_text_row( 'advertiser_contact', 'פרטי יצירת קשר', $s['advertiser_contact'] ?? '', 'טלפון / כתובת' ); ?>
                    </table>
                    <p class="description">יוצגו בסיום כל הודעת ניוזלטר: "* פרסומת * | מאת: [שם], [פרטי קשר]"</p>
                </div>

                <!-- Dev / Live mode -->
                <div class="card" style="max-width:800px;margin-top:20px;padding:20px;" id="sts-nl-mode-card">
                    <h2>מצב הפעלה</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">מצב</th>
                            <td>
                                <label style="margin-left:16px;">
                                    <input type="radio" name="<?php echo esc_attr( STS_NL_Settings::OPTION ); ?>[operating_mode]" value="dev" <?php checked( $mode, 'dev' ); ?>>
                                    פיתוח (Dev) — שולח למספר הפיתוח בלבד
                                </label>
                                <label>
                                    <input type="radio" name="<?php echo esc_attr( STS_NL_Settings::OPTION ); ?>[operating_mode]" value="live" <?php checked( $mode, 'live' ); ?>>
                                    Live — שולח ללקוחות האמיתיים
                                </label>
                            </td>
                        </tr>
                        <tr id="sts-nl-dev-phone-row" <?php echo $mode === 'live' ? 'hidden' : ''; ?>>
                            <th scope="row"><label for="dev_phone_override">טלפון לפיתוח</label></th>
                            <td>
                                <input type="text" id="dev_phone_override"
                                       name="<?php echo esc_attr( STS_NL_Settings::OPTION ); ?>[dev_phone_override]"
                                       value="<?php echo esc_attr( $s['dev_phone_override'] ?? '' ); ?>"
                                       placeholder="0501234567" class="regular-text">
                                <p class="description" style="color:#dc3232;">
                                    <strong>אזהרה:</strong> במצב פיתוח, כל ההודעות יישלחו למספר זה במקום ללקוחות האמיתיים. אם השדה ריק, השליחה תיחסם.
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <p class="submit" style="margin-top:20px;">
                    <input type="submit" name="save_newsletter_settings" class="button button-primary" value="שמור הגדרות">
                </p>
            </form>

            <!-- WhatsApp Connection Panel -->
            <div class="card" style="max-width:800px;margin-top:20px;padding:20px;">
                <h2>חיבור WhatsApp</h2>
                <?php $this->render_connection_panel( $s, $connection_state, $session_id ); ?>
            </div>
        </div>

        <script>
        (function($){
            // Dev/Live mode toggle — show/hide dev phone row.
            $('input[name="<?php echo esc_js( STS_NL_Settings::OPTION ); ?>[operating_mode]"]').on('change', function() {
                if ($(this).val() === 'live') {
                    $('#sts-nl-dev-phone-row').hide();
                    if (!confirm('מעבר למצב Live ישלח הודעות ללקוחות האמיתיים. האם אתה בטוח?')) {
                        $('input[name="<?php echo esc_js( STS_NL_Settings::OPTION ); ?>[operating_mode]"][value="dev"]').prop('checked', true);
                        return;
                    }
                } else {
                    $('#sts-nl-dev-phone-row').show();
                }
            });

            // ---- Connection state machine ----
            var $panel = $('.sts-nl-connection-panel');
            if (!$panel.length) return;

            var ajaxUrl     = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
            var nonces = {
                connect:    <?php echo wp_json_encode( wp_create_nonce( 'sts_nl_connect_session' ) ); ?>,
                poll:       <?php echo wp_json_encode( wp_create_nonce( 'sts_nl_poll_session' ) ); ?>,
                disconnect: <?php echo wp_json_encode( wp_create_nonce( 'sts_nl_disconnect_session' ) ); ?>,
                test:       <?php echo wp_json_encode( wp_create_nonce( 'sts_nl_test_connection' ) ); ?>
            };

            var POLL_QR_MS    = 1500;
            var POLL_ALIVE_MS = 30000;
            var pollTimer     = null;
            var currentState  = $panel.attr('data-state') || 'disconnected';

            var STATE_LABELS = {
                not_configured:     'לא מוגדר',
                disconnected:       'לא מחובר',
                initializing:       'מאתחל סשן…',
                qr_ready:           'ממתין לסריקה',
                connected:          'מחובר',
                disconnected_error: 'החיבור נותק'
            };

            function $btn(cls) { return $panel.find(cls); }
            function setError(msg) {
                var $e = $panel.find('.sts-nl-conn-error');
                if (msg) { $e.text(msg).show(); } else { $e.hide().text(''); }
            }
            function showQR(dataUrl) {
                if (!dataUrl) return;
                var $img = $panel.find('.sts-nl-qr-image');
                if ($img.attr('src') !== dataUrl) $img.attr('src', dataUrl);
                $panel.find('.sts-nl-qr-container').show();
            }
            function hideQR() {
                $panel.find('.sts-nl-qr-container').hide();
                $panel.find('.sts-nl-qr-image').attr('src', '');
            }
            function stopPolling() {
                if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
            }
            function startPolling(ms) {
                stopPolling();
                pollTimer = setInterval(poll, ms);
            }
            function setState(next) {
                currentState = next;
                $panel.attr('data-state', next);
                $panel.find('.sts-nl-status-label').text(STATE_LABELS[next] || next);

                var isConnected     = next === 'connected';
                var isQrFlow        = next === 'initializing' || next === 'qr_ready';
                var isDisconnectErr = next === 'disconnected_error';
                var isNotConfigured = next === 'not_configured';

                $btn('.sts-nl-connect-btn').toggle(next === 'disconnected' || isDisconnectErr || isNotConfigured);
                $btn('.sts-nl-connect-btn').prop('disabled', isNotConfigured);
                $btn('.sts-nl-cancel-btn').toggle(isQrFlow);
                $btn('.sts-nl-test-btn').toggle(isConnected);
                $btn('.sts-nl-disconnect-btn').toggle(isConnected);
                $panel.find('.sts-nl-session-id-row').toggle(isConnected);
                $btn('.sts-nl-connect-btn').text(isDisconnectErr ? 'התחבר מחדש' : 'התחבר ל-WhatsApp');

                if (!isQrFlow) hideQR();
                if (next !== 'disconnected_error') setError('');
                $panel.find('.sts-nl-test-result').text('').css('color', '');

                if (isQrFlow)       startPolling(POLL_QR_MS);
                else if (isConnected) startPolling(POLL_ALIVE_MS);
                else                stopPolling();
            }

            function applyPollResult(data) {
                var status = data && data.status;
                if      (status === 'qr_ready')    { showQR(data.qr); if (currentState !== 'qr_ready')    setState('qr_ready'); }
                else if (status === 'connected')   { if (currentState !== 'connected')   setState('connected'); }
                else if (status === 'initializing'){ if (currentState !== 'initializing') setState('initializing'); }
                else if (status === 'disconnected' || status === 'gone' || status === 'no_session') {
                    setState(status === 'no_session' ? 'disconnected' : 'disconnected_error');
                }
            }

            function poll() {
                if (document.hidden && currentState === 'connected') return;
                $.post(ajaxUrl, { action: 'sts_nl_poll_session', _wpnonce: nonces.poll })
                    .done(function(res) {
                        if (!res || !res.success) { setError((res && res.data && res.data.message) || 'שגיאת תקשורת'); return; }
                        applyPollResult(res.data);
                    })
                    .fail(function() { setError('הבקשה נכשלה'); });
            }

            $btn('.sts-nl-connect-btn').on('click', function() {
                var $b = $(this).prop('disabled', true);
                setError('');
                $.post(ajaxUrl, { action: 'sts_nl_connect_session', _wpnonce: nonces.connect })
                    .done(function(res) {
                        if (!res || !res.success) { setError((res && res.data && res.data.message) || 'יצירת סשן נכשלה'); return; }
                        if (res.data.session_id) {
                            $panel.find('.sts-nl-session-id').text(res.data.session_id);
                            $panel.find('.sts-nl-session-id-row').show();
                        }
                        setState('initializing');
                        poll();
                    })
                    .fail(function() { setError('הבקשה נכשלה'); })
                    .always(function() { $b.prop('disabled', false); });
            });

            $btn('.sts-nl-cancel-btn').on('click', function() {
                if (!confirm('לבטל את חיבור ה-WhatsApp? הסשן יימחק.')) return;
                doDisconnect();
            });
            $btn('.sts-nl-disconnect-btn').on('click', function() {
                if (!confirm('להתנתק מ-WhatsApp? יש לסרוק קוד QR מחדש כדי להתחבר.')) return;
                doDisconnect();
            });
            function doDisconnect() {
                stopPolling();
                $.post(ajaxUrl, { action: 'sts_nl_disconnect_session', _wpnonce: nonces.disconnect })
                    .done(function(res) {
                        if (res && res.success) {
                            $panel.find('.sts-nl-session-id').text('');
                            $panel.find('.sts-nl-session-id-row').hide();
                            setState('disconnected');
                        } else {
                            setError((res && res.data && res.data.message) || 'ניתוק נכשל');
                        }
                    })
                    .fail(function() { setError('הבקשה נכשלה'); });
            }
            $btn('.sts-nl-test-btn').on('click', function() {
                var $b = $(this).prop('disabled', true);
                var $r = $panel.find('.sts-nl-test-result');
                $r.text('בודק…').css('color', '');
                $.post(ajaxUrl, { action: 'sts_nl_test_connection', _wpnonce: nonces.test })
                    .done(function(res) {
                        $r.css('color', res.success ? 'green' : 'red').text((res.data && res.data.message) || (res.success ? 'OK' : 'שגיאה'));
                    })
                    .fail(function() { $r.css('color','red').text('הבקשה נכשלה'); })
                    .always(function() { $b.prop('disabled', false); });
            });

            setState(currentState);
            if (currentState === 'connected') poll();
        })(jQuery);
        </script>
        <?php
    }

    private function get_connection_state( array $s ): string {
        $base_url    = trim( (string) ( $s['whatsapp_base_url']    ?? '' ) );
        $api_key     = trim( (string) ( $s['whatsapp_api_key']     ?? '' ) );
        $session_id  = trim( (string) ( $s['whatsapp_session_id']  ?? '' ) );
        $session_key = trim( (string) ( $s['whatsapp_session_key'] ?? '' ) );

        if ( $base_url === '' || $api_key === '' ) {
            return 'not_configured';
        }
        if ( $session_id === '' || $session_key === '' ) {
            return 'disconnected';
        }
        return 'connected';
    }

    private function render_connection_panel( array $s, string $initial_state, string $session_id ): void {
        ?>
        <style>
            .sts-nl-connection-panel { background:#fff; border:1px solid #c3c4c7; border-radius:4px; padding:16px 20px; }
            .sts-nl-connection-panel .sts-nl-status-row { display:flex; align-items:center; gap:10px; font-size:14px; margin-bottom:8px; }
            .sts-nl-connection-panel .sts-nl-dot { display:inline-block; width:10px; height:10px; border-radius:50%; background:#bbb; }
            .sts-nl-connection-panel[data-state="connected"]          .sts-nl-dot { background:#46b450; }
            .sts-nl-connection-panel[data-state="disconnected_error"] .sts-nl-dot { background:#dc3232; }
            .sts-nl-connection-panel[data-state="qr_ready"]           .sts-nl-dot { background:#f0b849; }
            .sts-nl-connection-panel[data-state="initializing"]       .sts-nl-dot { background:#f0b849; }
            .sts-nl-connection-panel .sts-nl-meta { font-size:13px; color:#555; margin:4px 0; }
            .sts-nl-connection-panel .sts-nl-meta code { background:#f6f7f7; padding:2px 6px; border-radius:3px; }
            .sts-nl-connection-panel .sts-nl-qr-container { margin:14px 0; text-align:center; }
            .sts-nl-connection-panel .sts-nl-qr-image { width:260px; height:260px; border:1px solid #ddd; padding:8px; background:#fff; }
            .sts-nl-connection-panel .sts-nl-qr-hint { font-size:13px; color:#555; margin-top:8px; }
            .sts-nl-connection-panel .sts-nl-actions { margin-top:12px; display:flex; gap:8px; flex-wrap:wrap; }
            .sts-nl-connection-panel .sts-nl-conn-error { color:#dc3232; margin-top:10px; font-size:13px; }
            .sts-nl-connection-panel .sts-nl-test-result { margin-inline-start:8px; font-size:13px; }
        </style>
        <div class="sts-nl-connection-panel" data-state="<?php echo esc_attr( $initial_state ); ?>">
            <div class="sts-nl-status-row">
                <span>סטטוס:</span>
                <span class="sts-nl-dot"></span>
                <span class="sts-nl-status-label"></span>
            </div>
            <div class="sts-nl-meta sts-nl-session-id-row"<?php echo $initial_state === 'connected' ? '' : ' hidden'; ?>>
                <span>מזהה סשן:</span>
                <code class="sts-nl-session-id"><?php echo esc_html( $initial_state === 'connected' ? $session_id : '' ); ?></code>
            </div>
            <div class="sts-nl-meta">
                <span>כתובת Webhook:</span>
                <code><?php echo esc_html( STS_NL_Secret::webhook_url() ); ?></code>
            </div>

            <div class="sts-nl-qr-container" hidden>
                <img class="sts-nl-qr-image" alt="קוד QR" src="" />
                <p class="sts-nl-qr-hint">סרוק את הקוד מאפליקציית WhatsApp בטלפון:<br>הגדרות &larr; מכשירים מקושרים &larr; קישור מכשיר</p>
            </div>

            <div class="sts-nl-actions">
                <button type="button" class="button button-primary sts-nl-connect-btn">התחבר ל-WhatsApp</button>
                <button type="button" class="button sts-nl-cancel-btn" hidden>בטל</button>
                <button type="button" class="button sts-nl-test-btn" hidden>בדוק חיבור</button>
                <button type="button" class="button sts-nl-disconnect-btn" hidden>התנתק</button>
                <span class="sts-nl-test-result"></span>
            </div>

            <div class="sts-nl-conn-error" hidden></div>
        </div>
        <?php
    }

    private function settings_text_row( string $key, string $label, string $value, string $placeholder = '' ): void {
        $name = STS_NL_Settings::OPTION . '[' . esc_attr( $key ) . ']';
        echo '<tr><th scope="row"><label for="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th>';
        echo '<td><input type="text" id="' . esc_attr( $key ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $placeholder ) . '" class="regular-text"></td></tr>';
    }

    private function settings_password_row( string $key, string $label ): void {
        $name = STS_NL_Settings::OPTION . '[' . esc_attr( $key ) . ']';
        echo '<tr><th scope="row"><label for="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th>';
        echo '<td><input type="password" id="' . esc_attr( $key ) . '" name="' . esc_attr( $name ) . '" value="" placeholder="(ללא שינוי)" class="regular-text" autocomplete="new-password"></td></tr>';
    }

    // -------------------------------------------------------------------------
    // SUBSCRIBER QUERY HELPER
    // -------------------------------------------------------------------------

    public function get_subscribers( $channel = 'all', $topic = '' ) {
        $args = array(
            'meta_query' => array(
                array(
                    'key'     => 'newsletter_subscribed',
                    'value'   => 'yes',
                    'compare' => '=',
                ),
            ),
        );

        if ( 'all' !== $channel ) {
            $args['meta_query'][] = array(
                'key'     => 'newsletter_channel',
                'value'   => array( $channel, 'both' ),
                'compare' => 'IN',
            );
        }

        $users = get_users( $args );

        if ( ! empty( $topic ) ) {
            $users = array_filter( $users, function( $user ) use ( $topic ) {
                $topics = get_user_meta( $user->ID, 'newsletter_topics', true );
                return is_array( $topics ) && in_array( $topic, $topics, true );
            } );
        }

        return $users;
    }
}
