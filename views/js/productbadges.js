(function () {
    'use strict';

    if (typeof window.pbConfig === 'undefined') { return; }

    var injected = {};

    function appendBadges(container, badges) {
        if (!container || container.querySelector('.pb-badges-wrapper')) { return; }
        var wrapper = document.createElement('div');
        wrapper.className = 'pb-badges-wrapper';
        badges.forEach(function (badge) {
            var span = document.createElement('span');
            span.className = 'pb-badge pb-pos-' + badge.position;
            span.style.backgroundColor = badge.bg_color;
            span.style.color = badge.text_color;
            span.textContent = badge.label;
            wrapper.appendChild(span);
        });
        container.appendChild(wrapper);
    }

    function injectFromData(data) {
        if (!data || typeof data !== 'object') { return; }

        // Product listing cards
        document.querySelectorAll('[data-id-product]').forEach(function (card) {
            var id = card.getAttribute('data-id-product');
            if (!data[id] || !data[id].length) { return; }
            var container = card.querySelector('.thumbnail-container')
                         || card.querySelector('.product-thumbnail')
                         || card.querySelector('.product-cover')
                         || card;
            appendBadges(container, data[id]);
            injected[id] = true;
        });

        // Product page: no data-id-product on image area — use form input
        var input = document.querySelector('input[name="id_product"]');
        if (input) {
            var id = input.value;
            if (data[id] && data[id].length) {
                var imgContainer = document.querySelector('.images-container')
                                || document.querySelector('.product-cover');
                appendBadges(imgContainer, data[id]);
                injected[id] = true;
            }
        }
    }

    function fetchMissing() {
        var ids = [];

        document.querySelectorAll('[data-id-product]').forEach(function (el) {
            var id = el.getAttribute('data-id-product');
            if (id && !injected[id]) { ids.push(id); }
        });

        // Product page fallback
        var input = document.querySelector('input[name="id_product"]');
        if (input && !injected[input.value]) { ids.push(input.value); }

        if (!ids.length) { return; }

        var body = new FormData();
        ids.forEach(function (id) { body.append('ids[]', id); });

        fetch(window.pbConfig.ajaxUrl, { method: 'POST', body: body })
            .then(function (r) { return r.json(); })
            .then(injectFromData)
            .catch(function () {});
    }

    function run() {
        // Inject data already available from PHP (category/search/product pages)
        if (window.pbData) {
            injectFromData(window.pbData);
        }
        // AJAX fallback for home widgets and any remaining products
        fetchMissing();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }
})();
