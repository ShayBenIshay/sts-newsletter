(function () {
    'use strict';

    if (typeof jQuery === 'undefined') return;

    jQuery(document).ready(function ($) {

        function needsPhone(channel) {
            return channel === 'whatsapp' || channel === 'both';
        }

        function updatePhoneVisibility() {
            var channel = $('input[name="newsletter_channel"]:checked').val();
            var $field  = $('#newsletter-phone-field');
            if (needsPhone(channel)) {
                $field.slideDown(150);
            } else {
                $field.slideUp(150);
            }
        }

        $('input[name="newsletter_channel"]').on('change', updatePhoneVisibility);
        updatePhoneVisibility();

        $('#newsletter-signup-btn').on('click', function (e) {
            e.preventDefault();

            var consent  = $('#newsletter_consent').is(':checked');
            var channel  = $('input[name="newsletter_channel"]:checked').val();
            var phone    = $('#newsletter_phone').val().trim();
            var $button  = $(this);
            var $message = $('#newsletter-message');

            if (!consent) {
                $message.removeClass('hidden text-green-600').addClass('text-red-600')
                        .text('יש לאשר קבלת עדכונים');
                return;
            }

            if (needsPhone(channel) && phone === '') {
                $message.removeClass('hidden text-green-600').addClass('text-red-600')
                        .text('יש להזין מספר טלפון לקבלת עדכוני WhatsApp');
                $('#newsletter_phone').focus();
                return;
            }

            $button.prop('disabled', true).text('נרשם...');

            $.ajax({
                url:  newsletterData.ajaxUrl,
                type: 'POST',
                data: {
                    action:  'newsletter_signup',
                    nonce:   newsletterData.nonce,
                    channel: channel,
                    phone:   phone,
                },
                success: function (response) {
                    if (response.success) {
                        $message.removeClass('hidden text-red-600').addClass('text-green-600')
                                .text(response.data.message);
                        setTimeout(function () { location.reload(); }, 2000);
                    } else {
                        $message.removeClass('hidden text-green-600').addClass('text-red-600')
                                .text(response.data.message);
                        $button.prop('disabled', false).text('הצטרפי עכשיו');
                    }
                },
                error: function () {
                    $message.removeClass('hidden text-green-600').addClass('text-red-600')
                            .text('אירעה שגיאה, אנא נסה שנית');
                    $button.prop('disabled', false).text('הצטרפי עכשיו');
                },
            });
        });
    });
})();
