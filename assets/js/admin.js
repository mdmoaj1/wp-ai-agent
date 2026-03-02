/**
 * AI Content Generator — Admin JavaScript
 *
 * @package AITF
 */

(function ($) {
    'use strict';

    $(document).ready(function () {

        // ============================================================
        // Toggle password visibility
        // ============================================================
        $('.aitf-toggle-password').on('click', function () {
            var targetId = $(this).data('target');
            var $input = $('#' + targetId);
            var $icon = $(this).find('.dashicons');

            if ($input.attr('type') === 'password') {
                $input.attr('type', 'text');
                $icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
            } else {
                $input.attr('type', 'password');
                $icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
            }
        });

        // ============================================================
        // Model dropdown filtering based on provider
        // ============================================================
        var $providerSelect = $('#api_provider');
        var $modelSelect = $('#model');

        function filterModels() {
            var provider = $providerSelect.val();

            $modelSelect.find('optgroup').each(function () {
                var groupProvider = $(this).data('provider');
                if (groupProvider === provider) {
                    $(this).show();
                } else {
                    $(this).hide();
                    // Deselect if currently selected model belongs to hidden group.
                    $(this).find('option:selected').prop('selected', false);
                }
            });
        }

        if ($providerSelect.length) {
            filterModels();
            $providerSelect.on('change', filterModels);
        }

        // ============================================================
        // Run Now button — confirm and show loading
        // ============================================================
        $('#aitf-run-now').on('click', function (e) {
            if (!confirm('Run content generation now? This will fetch from all competitors and may take a few minutes.')) {
                e.preventDefault();
                return;
            }

            $(this).text(aitfAdmin.i18n.running)
                .prop('disabled', true)
                .css('opacity', '0.6');
        });

        // ============================================================
        // Auto-dismiss notices after 5 seconds
        // ============================================================
        setTimeout(function () {
            $('.aitf-wrap .notice.is-dismissible').fadeOut(400);
        }, 5000);

        // ============================================================
        // Update gradient opacity value display
        // ============================================================
        $('#image_gradient_opacity').on('input', function () {
            $('#opacity-value').text($(this).val() + '%');
        });

    });

})(jQuery);
