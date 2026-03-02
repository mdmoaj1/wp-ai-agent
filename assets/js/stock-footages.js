/**
 * Stock Footages — search Pixabay/Pexels via AJAX (no page reload).
 *
 * @package AITF
 */

(function ($) {
    'use strict';

    var $keyword, $btn, $loading, $message, $results;
    var searchTimeout;

    function init() {
        $keyword  = $('#aitf-stock-keyword');
        $btn      = $('#aitf-stock-search-btn');
        $loading  = $('#aitf-stock-loading');
        $message  = $('#aitf-stock-message');
        $results  = $('#aitf-stock-results');

        $btn.on('click', doSearch);
        $keyword.on('keypress', function (e) {
            if (e.which === 13) {
                e.preventDefault();
                doSearch();
            }
        });

        // Optional: search as you type (debounced).
        $keyword.on('input', function () {
            clearTimeout(searchTimeout);
            var q = $(this).val().trim();
            if (q.length < 2) {
                $results.empty();
                $message.text('');
                return;
            }
            searchTimeout = setTimeout(doSearch, 400);
        });
    }

    function doSearch() {
        var keyword = $keyword.val().trim();
        if (keyword.length < 2) {
            $message.text(typeof aitfStockFootages !== 'undefined' && aitfStockFootages.i18n.noResults
                ? 'Enter at least 2 characters.'
                : 'Enter at least 2 characters.').css('color', '#646970');
            $results.empty();
            return;
        }

        $btn.prop('disabled', true);
        $loading.show();
        $message.text(aitfStockFootages.i18n.searching).css('color', '');
        $results.empty();

        $.get(aitfStockFootages.ajaxUrl, {
            action:  'aitf_search_stock_photos',
            nonce:   aitfStockFootages.nonce,
            keyword: keyword,
            limit:   20
        })
            .done(function (res) {
                if (res.success && res.data && Array.isArray(res.data.results)) {
                    if (res.data.results.length === 0) {
                        $message.text(res.data.message || aitfStockFootages.i18n.noResults).css('color', '#d63638');
                    } else {
                        $message.text(res.data.results.length + ' image(s) found.').css('color', '#00a32a');
                        renderResults(res.data.results);
                    }
                } else {
                    $message.text(res.data && res.data.message ? res.data.message : aitfStockFootages.i18n.error).css('color', '#d63638');
                }
            })
            .fail(function () {
                $message.text(aitfStockFootages.i18n.error).css('color', '#d63638');
            })
            .always(function () {
                $btn.prop('disabled', false);
                $loading.hide();
            });
    }

    function renderResults(results) {
        var html = '';
        results.forEach(function (item) {
            if (!item.url) return;
            var thumb = item.thumbnail || item.url;
            var label = (item.source || '').toLowerCase();
            html += '<div class="aitf-stock-item" style="border:1px solid #c3c4c7; border-radius:4px; overflow:hidden; background:#fff;">';
            html += '<a href="' + escapeAttr(item.url) + '" target="_blank" rel="noopener" style="display:block; line-height:0;">';
            html += '<img src="' + escapeAttr(thumb) + '" alt="" style="width:100%; height:160px; object-fit:cover; display:block;">';
            html += '</a>';
            html += '<div style="padding:8px; font-size:12px; color:#646970;">' + escapeHtml(label) + '</div>';
            html += '</div>';
        });
        $results.html(html);
    }

    function escapeAttr(s) {
        if (!s) return '';
        var div = document.createElement('div');
        div.textContent = s;
        return div.innerHTML.replace(/"/g, '&quot;');
    }

    function escapeHtml(s) {
        if (!s) return '';
        var div = document.createElement('div');
        div.textContent = s;
        return div.innerHTML;
    }

    $(document).ready(init);
})(jQuery);
