<div class="newsletter-section">
    <?php if ( is_user_logged_in() ) : ?>
        <?php
        $current_user_id = get_current_user_id();
        $is_subscribed   = get_user_meta( $current_user_id, 'newsletter_subscribed', true ) === 'yes';
        $channel         = get_user_meta( $current_user_id, 'newsletter_channel', true );
        ?>

        <?php if ( $is_subscribed ) : ?>
            <!-- Already Subscribed -->
            <div class="text-center">
                <div class="inline-flex mb-3">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 1.5rem; height: 1.5rem;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <p class="text-sts text-lg">את/ה רשום/ה למועדון הלקוחות שלנו!</p>
                </div>
                <p class="text-sts mb-3">
                    מקבל/ת עדכונים ב-<?php
                        echo $channel === 'whatsapp' ? 'WhatsApp' :
                             ( $channel === 'email' ? 'אימייל' :
                               'WhatsApp ואימייל' );
                    ?>
                </p>
                <?php
                $profile_page = get_page_by_path( 'my-account' );
                if ( $profile_page ) :
                ?>
                <a href="<?php echo esc_url( get_permalink( $profile_page ) ); ?>"
                   class="inline-block text-sts underline hover:text-gray-700 transition-colors">
                    ערוך העדפות בפרופיל
                </a>
                <?php endif; ?>
            </div>
        <?php else : ?>
            <!-- Newsletter Signup Form for Logged In Users -->
            <div id="newsletter-form" class="w-full">
                <h3 class="h2-sts text-center mb-4">להצטרפות למועדון הלקוחות שלנו</h3>
                <p class="text-center text-sts mb-6">עדכונים על קולקציות חדשות, מבצעים מיוחדים וטיפים לטיפוח תכשיטים</p>

                <div class="mb-6">
                    <p class="text-sts mb-3 text-center">לקבלת עדכונים ב-</p>
                    <div class="flex gap-4 justify-center flex-wrap">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="newsletter_channel" value="whatsapp" checked class="text-black">
                            <span class="text-sts">WhatsApp</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="newsletter_channel" value="email" class="text-black">
                            <span class="text-sts">אימייל</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="newsletter_channel" value="both" class="text-black">
                            <span class="text-sts">שניהם</span>
                        </label>
                    </div>
                </div>

                <div id="newsletter-phone-field" class="mb-6" style="display:none;">
                    <input type="tel" id="newsletter_phone"
                           class="newsletter-phone-input"
                           placeholder="מספר טלפון (לדוגמה: 0501234567)"
                           value="<?php echo esc_attr( get_user_meta( get_current_user_id(), 'billing_phone', true ) ); ?>"
                           dir="ltr">
                    <span class="newsletter-phone-hint">נדרש לקבלת עדכוני WhatsApp</span>
                </div>

                <div class="flex items-center justify-center text-sts gap-2 mb-6">
                    <input type="checkbox" id="newsletter_consent" class="rounded text-black" checked>
                    <label for="newsletter_consent" class="text-right text-sts cursor-pointer">
                        ברצוני לקבל מידע על מבצעים וקולקציות חדשות
                    </label>
                </div>
                <p class="text-center text-sts" style="margin-bottom: 0.5rem;"> בהרשמה אני מאשר/ת קבלת עדכונים בהתאם ל
                    <a href="<?php echo esc_url( home_url( '/privacy-policy' ) ); ?>" target="_blank" rel="noopener noreferrer" class="text-sts" style="text-decoration: underline;">
                        מדיניות פרטיות
                    </a>
                </p>
                <button id="newsletter-signup-btn" class="btn btn-primary text-sts" style="display: block; justify-self: center;">
                    להצטרפות עכשיו
                </button>

                <div id="newsletter-message" class="mt-4 text-center hidden text-sts"></div>
            </div>
        <?php endif; ?>
    <?php else : ?>
        <!-- Not Logged In - Prompt to Login/Register -->
        <div class="text-center">
            <h3 class="h2-sts mb-3">להצטרפות למועדון הלקוחות</h3>
            <p class="text-sts mb-6">
                עדכונים על קולקציות חדשות, מבצעים מיוחדים והטבות בלעדיות<br>
                דרך WhatsApp או אימייל - הבחירה שלך!
            </p>
            <div class="flex gap-3 justify-center flex-wrap">
                <a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>"
                   class="bg-black text-white text-sts text-base px-6 py-3 inline-block hover:bg-gray-800 transition-colors rounded-md">
                    להתחברות ולהצטרפות
                </a>
                <a href="<?php echo esc_url( wp_registration_url() ); ?>"
                   class="bg-white text-black border-2 border-black text-sts text-base px-6 py-3 inline-block hover:bg-gray-100 transition-colors rounded-md">
                    הרשמה לאתר
                </a>
            </div>
            <p class="text-xs text-sts mt-4">
                כבר יש לך חשבון? התחברי כדי להירשם לניוזלטר
            </p>
        </div>
    <?php endif; ?>
</div>
