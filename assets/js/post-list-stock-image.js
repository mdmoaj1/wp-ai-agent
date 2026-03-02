/**
 * Post list — Stock image: open modal, search, set featured image via AJAX (no reload).
 *
 * @package AITF
 */

(function ($) {
    'use strict';

    var $modal, $backdrop, $keyword, $searchBtn, $message, $results, $postIdInput;
    var cfg;

    function init() {
        cfg = window.aitfPostListStock;
        if (!cfg || !cfg.ajaxUrl) return;

        $modal = $('#aitf-stock-image-modal');
        $backdrop = $modal.find('.aitf-modal-backdrop');
        $keyword = $('#aitf-stock-image-keyword');
        $searchBtn = $('#aitf-stock-image-search-btn');
        $message = $('#aitf-stock-image-message');
        $results = $('#aitf-stock-image-results');
        $postIdInput = $('#aitf-stock-image-post-id');

        $(document).on('click', '.aitf-stock-image-link', openModal);
        $modal.find('.aitf-modal-close').add($backdrop).on('click', closeModal);
        $searchBtn.on('click', doSearch);
        $keyword.on('keypress', function (e) {
            if (e.which === 13) { e.preventDefault(); doSearch(); }
        });
    }

    function openModal(e) {
        e.preventDefault();
        var $link = $(e.currentTarget);
        var postId = $link.data('post-id');
        var postTitle = $link.data('post-title') || '';

        $postIdInput.val(postId);
        $keyword.val(postTitle.trim());
        $message.text('');
        $results.empty();
        $modal.show();

        if (postTitle.trim().length >= 2) {
            doSearch();
        }
    }

    function closeModal() {
        $modal.hide();
    }

    function doSearch() {
        var keyword = $keyword.val().trim();
        if (keyword.length < 2) {
            $message.text(cfg.i18n.keywordPlaceholder).css('color', '#646970');
            $results.empty();
            return;
        }

        $searchBtn.prop('disabled', true).text(cfg.i18n.searching);
        $message.text('');
        $results.empty();

        $.get(cfg.ajaxUrl, {
            action: 'aitf_search_stock_photos',
            nonce: cfg.nonce,
            keyword: keyword,
            limit: 20
        })
            .done(function (res) {
                if (res.success && res.data && Array.isArray(res.data.results)) {
                    if (res.data.results.length === 0) {
                        $message.text(res.data.message || cfg.i18n.noResults).css('color', '#d63638');
                    } else {
                        renderResults(res.data.results);
                    }
                } else {
                    $message.text(res.data && res.data.message ? res.data.message : cfg.i18n.noResults).css('color', '#d63638');
                }
            })
            .fail(function () {
                $message.text(cfg.i18n.error).css('color', '#d63638');
            })
            .always(function () {
                $searchBtn.prop('disabled', false).text(cfg.i18n.search);
            });
    }

    function renderResults(results) {
        var postId = $postIdInput.val();
        var html = '';
        results.forEach(function (item) {
            if (!item.url) return;
            var thumb = item.thumbnail || item.url;
            html += '<div class="aitf-stock-image-item">';
            html += '<img src="' + escapeAttr(thumb) + '" alt="" loading="lazy">';
            html += '<button type="button" class="button aitf-set-featured-btn" data-url="' + escapeAttr(item.url) + '">' + escapeHtml(cfg.i18n.setFeatured) + '</button>';
            html += '</div>';
        });
        $results.html(html);
        $results.off('click').on('click', '.aitf-set-featured-btn', function () {
            setFeatured(postId, $(this).data('url'), $(this));
        });
    }

    function setFeatured(postId, imageUrl, $btn) {
        $btn.prop('disabled', true).text(cfg.i18n.setting);

        $.post(cfg.ajaxUrl, {
            action: 'aitf_set_featured_from_url',
            nonce: cfg.nonce,
            post_id: postId,
            image_url: imageUrl
        })
            .done(function (res) {
                if (res.success && res.data && res.data.thumbnail_url) {
                    var $row = $('#post-' + postId);
                    var $img = $row.find('img').first();
                    if ($img.length) {
                        $img.attr('src', res.data.thumbnail_url);
                    }
                    $message.text(cfg.i18n.done).css('color', '#00a32a');
                    closeModal();
                } else {
                    $message.text(res.data && res.data.message ? res.data.message : cfg.i18n.error).css('color', '#d63638');
                    $btn.prop('disabled', false).text(cfg.i18n.setFeatured);
                }
            })
            .fail(function (xhr) {
                var msg = cfg.i18n.error;
                if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    msg = xhr.responseJSON.data.message;
                }
                $message.text(msg).css('color', '#d63638');
                $btn.prop('disabled', false).text(cfg.i18n.setFeatured);
            });
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
