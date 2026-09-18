(function ($) {
    'use strict';

    function needsPhone(channel) {
        return channel === 'whatsapp' || channel === 'both';
    }

    function initNewsletterToggle() {
        var $checkbox = $('#newsletter_signup');
        if (!$checkbox.length) return;

        var $panel = $('#newsletter-channel-select, #newsletter_channel_field');
        if (!$panel.length) return;

        function updatePhoneField() {
            var $phoneField = $('#newsletter_phone_field');
            if (!$phoneField.length) return;
            var channel = $('input[name="newsletter_channel"]:checked').val();
            if ($checkbox.is(':checked') && needsPhone(channel)) {
                $phoneField.slideDown(150);
            } else {
                $phoneField.slideUp(150);
            }
        }

        $checkbox.on('change', function () {
            if ($(this).is(':checked')) {
                $panel.slideDown();
                updatePhoneField();
            } else {
                $panel.slideUp();
                $('#newsletter_phone_field').slideUp(150);
            }
        });

        $('input[name="newsletter_channel"]').on('change', updatePhoneField);

        if ($checkbox.is(':checked')) {
            $panel.show();
            updatePhoneField();
        }
    }

    $(document).ready(initNewsletterToggle);

}(jQuery));
