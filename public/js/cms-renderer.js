/**
 * LicenseOS CMS Content Renderer
 * Handles popups/tooltips, countdown timers, and fullscreen content blocks
 */
(function () {
    'use strict';

    /* ===========================
       Popup / Tooltip Handler
       =========================== */
    function initPopups() {
        document.querySelectorAll('.cms-popup').forEach(function (el) {
            if (el.dataset.popupInit) return;
            el.dataset.popupInit = '1';

            var trigger = el.dataset.popupTrigger || 'hover';
            var content = el.dataset.popupContent || '';
            var popup = null;

            function showPopup() {
                if (popup) return;
                popup = document.createElement('div');
                popup.className = 'cms-popup-bubble';
                popup.innerHTML = content;
                popup.style.cssText = 'position:absolute;z-index:9999;background:#1f2937;color:#fff;padding:8px 14px;border-radius:8px;font-size:13px;line-height:1.5;max-width:320px;box-shadow:0 4px 16px rgba(0,0,0,0.15);pointer-events:auto;opacity:0;transition:opacity 0.2s;';
                document.body.appendChild(popup);

                var rect = el.getBoundingClientRect();
                var scrollTop = window.pageYOffset || document.documentElement.scrollTop;
                var scrollLeft = window.pageXOffset || document.documentElement.scrollLeft;

                popup.style.top = (rect.bottom + scrollTop + 8) + 'px';
                popup.style.left = (rect.left + scrollLeft) + 'px';

                requestAnimationFrame(function () { popup && (popup.style.opacity = '1'); });
            }

            function hidePopup() {
                if (!popup) return;
                var p = popup;
                popup = null;
                p.style.opacity = '0';
                setTimeout(function () { p.remove(); }, 200);
            }

            if (trigger === 'click') {
                el.addEventListener('click', function (e) {
                    e.preventDefault();
                    popup ? hidePopup() : showPopup();
                });
                document.addEventListener('click', function (e) {
                    if (popup && !el.contains(e.target) && !popup.contains(e.target)) hidePopup();
                });
            } else {
                el.addEventListener('mouseenter', showPopup);
                el.addEventListener('mouseleave', hidePopup);
            }
        });
    }

    /* ===========================
       Countdown Timer Handler
       =========================== */
    function initCountdowns() {
        document.querySelectorAll('.cms-countdown').forEach(function (el) {
            if (el.dataset.cdInit) return;
            el.dataset.cdInit = '1';

            var target = new Date(el.dataset.countdownTarget).getTime();
            if (isNaN(target)) return;

            var daysEl = el.querySelector('.cd-days');
            var hoursEl = el.querySelector('.cd-hours');
            var minsEl = el.querySelector('.cd-minutes');
            var secsEl = el.querySelector('.cd-seconds');

            function pad(n) { return n < 10 ? '0' + n : '' + n; }

            function tick() {
                var diff = target - Date.now();
                if (diff <= 0) {
                    daysEl && (daysEl.textContent = '00');
                    hoursEl && (hoursEl.textContent = '00');
                    minsEl && (minsEl.textContent = '00');
                    secsEl && (secsEl.textContent = '00');
                    return;
                }
                var d = Math.floor(diff / 86400000);
                var h = Math.floor((diff % 86400000) / 3600000);
                var m = Math.floor((diff % 3600000) / 60000);
                var s = Math.floor((diff % 60000) / 1000);

                daysEl && (daysEl.textContent = pad(d));
                hoursEl && (hoursEl.textContent = pad(h));
                minsEl && (minsEl.textContent = pad(m));
                secsEl && (secsEl.textContent = pad(s));

                requestAnimationFrame(function () { setTimeout(tick, 1000); });
            }
            tick();
        });
    }

    /* ===========================
       Responsive + Clickable Images
       =========================== */
    function initResponsiveImages() {
        document.querySelectorAll('.cms-content img, article img, .prose img').forEach(function (img) {
            /* Responsive */
            if (!img.style.maxWidth) {
                img.style.maxWidth = '100%';
                img.style.height = 'auto';
            }

            /* Read link URL from: data-link attr, hidden .cms-link-data span, or img src */
            var linkUrl = img.getAttribute('data-link') || '';
            if (!linkUrl) {
                var container = img.closest('figure') || img.parentElement;
                if (container) {
                    var marker = container.querySelector('.cms-link-data');
                    if (marker) linkUrl = marker.textContent.trim();
                }
            }
            if (!linkUrl) linkUrl = img.src;

            /* Find existing <a> ancestor */
            var ancestor = img.parentElement;
            while (ancestor && ancestor.tagName !== 'A') {
                ancestor = ancestor.parentElement;
                if (!ancestor || ancestor === document.body) { ancestor = null; break; }
            }

            if (ancestor && ancestor.tagName === 'A') {
                /* Already in <a>: force new tab + override href */
                ancestor.target = '_blank';
                ancestor.rel = 'noopener noreferrer';
                ancestor.href = linkUrl;
            } else {
                /* No <a>: wrap image in new link */
                var link = document.createElement('a');
                link.href = linkUrl;
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
                img.parentNode.insertBefore(link, img);
                link.appendChild(img);
            }

            /* Hide the marker span if visible */
            var c2 = img.closest('figure') || img.parentElement;
            if (c2) {
                var m2 = c2.querySelector('.cms-link-data');
                if (m2) m2.style.display = 'none';
            }
        });
    }

    /* ===========================
       Responsive Tables
       =========================== */
    function initResponsiveTables() {
        document.querySelectorAll('.cms-content table, article table').forEach(function (table) {
            if (table.parentElement.classList.contains('table-wrap')) return;
            var wrap = document.createElement('div');
            wrap.className = 'table-wrap';
            wrap.style.cssText = 'overflow-x:auto;margin:16px 0;';
            table.parentNode.insertBefore(wrap, table);
            wrap.appendChild(table);
            table.style.width = '100%';
        });
    }

    /* ===========================
       Hide File metadata text
       =========================== */
    function initHideMetadata() {
        document.querySelectorAll('.cms-content p, .cms-content figcaption, article p, article figcaption').forEach(function (el) {
            if (/^\s*[\w\-\.]+\.(png|jpg|jpeg|gif|webp|svg|mp4|webm|pdf|zip)\s+[\d.]+\s*(KB|MB|GB)\s*$/i.test(el.textContent)) {
                el.style.display = 'none';
            }
        });
    }

    /* ===========================
       Init All
       =========================== */
    function initAll() {
        initPopups();
        initCountdowns();
        initResponsiveImages();
        initResponsiveTables();
        initHideMetadata();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }
})();
