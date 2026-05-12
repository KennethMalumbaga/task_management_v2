<?php
if (defined('TM_LOADING_SCREEN_RENDERED')) {
    return;
}
define('TM_LOADING_SCREEN_RENDERED', true);

$tmShowInitialLoadingScreen = false;
if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['tm_show_login_loader'])) {
    $tmShowInitialLoadingScreen = true;
    unset($_SESSION['tm_show_login_loader']);
}
?>
<div class="tm-loading-screen<?= $tmShowInitialLoadingScreen ? '' : ' is-hidden' ?>" id="tmLoadingScreen" aria-hidden="<?= $tmShowInitialLoadingScreen ? 'false' : 'true' ?>">
    <div class="tm-loading-stage">
        <div class="tm-loading-logo-frame">
            <img src="img/logo2.png" alt="Nehemiah Solutions Loading..." class="tm-loading-logo">
        </div>
        <div class="tm-loading-lens" aria-hidden="true">
            <div class="tm-loading-lens-view">
                <div class="tm-loading-lens-scene">
                    <img src="img/logo2.png" alt="" class="tm-loading-logo tm-loading-logo--zoom">
                </div>
            </div>
            <span class="tm-loading-lens-gloss"></span>
        </div>
    </div>
</div>

<style>
    .tm-loading-screen {
        position: fixed;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        background: transparent;
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        z-index: 99999;
        pointer-events: auto;
        opacity: 1;
        visibility: visible;
        transition: opacity 0.24s ease, visibility 0.24s ease;
    }

    .tm-loading-screen.is-hidden {
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
    }

    .tm-loading-screen.is-hidden .tm-loading-lens {
        animation-play-state: paused;
    }

    .tm-loading-stage {
        position: relative;
        width: min(420px, 82vw);
        aspect-ratio: 471 / 269;
        --tm-lens-zoom: 1.08;
        --tm-lens-radius: 0px;
        --tm-stage-width: 100%;
        --tm-stage-height: 100%;
        --tm-lens-offset-x: 0px;
        --tm-lens-offset-y: 0px;
        --tm-lens-center-x: 50%;
        --tm-lens-center-y: 50%;
    }

    .tm-loading-logo-frame {
        position: absolute;
        inset: 0;
        overflow: visible;
        -webkit-mask-image: radial-gradient(
            circle var(--tm-lens-radius) at var(--tm-lens-center-x) var(--tm-lens-center-y),
            transparent 0,
            transparent calc(var(--tm-lens-radius) - 6px),
            #000 calc(var(--tm-lens-radius) - 1px)
        );
        mask-image: radial-gradient(
            circle var(--tm-lens-radius) at var(--tm-lens-center-x) var(--tm-lens-center-y),
            transparent 0,
            transparent calc(var(--tm-lens-radius) - 6px),
            #000 calc(var(--tm-lens-radius) - 1px)
        );
    }

    .tm-loading-logo {
        position: absolute;
        top: -48.3%;
        left: -2.35%;
        width: 106.2%;
        max-width: none;
        height: auto;
        display: block;
    }

    .tm-loading-lens {
        position: absolute;
        top: 3%;
        left: 2.8%;
        width: 47.8%;
        aspect-ratio: 1;
        overflow: hidden;
        border-radius: 50%;
        border: clamp(4px, 0.72vw, 5px) solid rgba(232, 235, 239, 0.9);
        background: rgba(255, 255, 255, 0.06);
        box-shadow: 0 0 0 1px rgba(246, 247, 249, 0.42) inset, 0 0 14px rgba(231, 234, 239, 0.22);
        pointer-events: none;
        will-change: transform, opacity;
        animation: tm-loading-lens-sweep 3.92s linear infinite;
    }

    .tm-loading-lens-view {
        position: absolute;
        inset: 0;
        overflow: hidden;
        border-radius: 50%;
    }

    .tm-loading-lens-scene {
        position: absolute;
        width: var(--tm-stage-width);
        height: var(--tm-stage-height);
        left: calc(-1 * var(--tm-lens-offset-x));
        top: calc(-1 * var(--tm-lens-offset-y));
        transform-origin: var(--tm-lens-center-x) var(--tm-lens-center-y);
        transform: scale(var(--tm-lens-zoom));
        filter: saturate(1.02);
        will-change: transform, left, top;
    }

    .tm-loading-logo--zoom {
        transform-origin: top left;
    }

    .tm-loading-lens-gloss {
        position: absolute;
        inset: 0;
        border-radius: 50%;
        background:
            radial-gradient(circle at 34% 30%, rgba(255, 255, 255, 0.34), rgba(255, 255, 255, 0.12) 42%, rgba(255, 255, 255, 0) 66%),
            linear-gradient(145deg, rgba(255, 255, 255, 0.18), rgba(255, 255, 255, 0) 48%);
        pointer-events: none;
    }

    @keyframes tm-loading-lens-sweep {
        0% {
            opacity: 0.96;
            transform: translate(0%, 0%);
        }
        8% {
            opacity: 0.96;
            transform: translate(3%, 0%);
        }
        16% {
            opacity: 0.97;
            transform: translate(20%, 4%);
        }
        24% {
            opacity: 0.98;
            transform: translate(38%, 9%);
        }
        31% {
            opacity: 0.98;
            transform: translate(51%, 12%);
        }
        38% {
            opacity: 0.96;
            transform: translate(63%, 15%);
        }
        41% {
            opacity: 0;
            transform: translate(72%, 16%);
        }
        100% {
            opacity: 0;
            transform: translate(72%, 16%);
        }
    }

    @media (max-width: 640px) {
        .tm-loading-stage {
            width: min(360px, 88vw);
        }
    }
</style>

<script>
    (function () {
        var loader = document.getElementById('tmLoadingScreen');
        if (!loader) {
            return;
        }

        var startedAt = Date.now();
        var minimumVisibleMs = 350;
        var maximumVisibleMs = 1200;
        var hidden = loader.classList.contains('is-hidden');
        var hideTimer = null;
        var lensTrackerId = 0;
        var prefetchedUrls = {};
        var stage = loader.querySelector('.tm-loading-stage');
        var lens = loader.querySelector('.tm-loading-lens');
        var lensScene = loader.querySelector('.tm-loading-lens-scene');

        function restartLensAnimation() {
            if (!lens) {
                return;
            }

            lens.style.animation = 'none';
            lens.offsetWidth;
            lens.style.animation = '';
        }

        function stopLensTracking() {
            if (!lensTrackerId) {
                return;
            }

            window.cancelAnimationFrame(lensTrackerId);
            lensTrackerId = 0;
        }

        function syncLensView() {
            if (!stage || !lens || !lensScene || hidden) {
                lensTrackerId = 0;
                return;
            }

            var stageRect = stage.getBoundingClientRect();
            var lensRect = lens.getBoundingClientRect();
            if (!stageRect.width || !stageRect.height || !lensRect.width || !lensRect.height) {
                lensTrackerId = window.requestAnimationFrame(syncLensView);
                return;
            }

            var lensOffsetX = lensRect.left - stageRect.left;
            var lensOffsetY = lensRect.top - stageRect.top;
            var lensCenterX = lensOffsetX + (lensRect.width / 2);
            var lensCenterY = lensOffsetY + (lensRect.height / 2);
            var lensRadius = lensRect.width / 2;

            stage.style.setProperty('--tm-stage-width', stageRect.width + 'px');
            stage.style.setProperty('--tm-stage-height', stageRect.height + 'px');
            stage.style.setProperty('--tm-lens-radius', lensRadius + 'px');
            stage.style.setProperty('--tm-lens-offset-x', lensOffsetX + 'px');
            stage.style.setProperty('--tm-lens-offset-y', lensOffsetY + 'px');
            stage.style.setProperty('--tm-lens-center-x', lensCenterX + 'px');
            stage.style.setProperty('--tm-lens-center-y', lensCenterY + 'px');

            lensTrackerId = window.requestAnimationFrame(syncLensView);
        }

        function startLensTracking() {
            stopLensTracking();
            lensTrackerId = window.requestAnimationFrame(syncLensView);
        }

        function showLoader() {
            if (hideTimer) {
                window.clearTimeout(hideTimer);
                hideTimer = null;
            }

            hidden = false;
            loader.setAttribute('aria-hidden', 'false');
            loader.classList.remove('is-hidden');
            restartLensAnimation();
            startLensTracking();
        }

        function hideLoader() {
            if (hidden) {
                return;
            }

            hidden = true;
            loader.setAttribute('aria-hidden', 'true');
            loader.classList.add('is-hidden');
            stopLensTracking();
        }

        function hideWhenReady(force) {
            var elapsed = Date.now() - startedAt;
            var wait = force === true ? 0 : Math.max(0, minimumVisibleMs - elapsed);
            if (hideTimer) {
                window.clearTimeout(hideTimer);
            }
            hideTimer = window.setTimeout(hideLoader, wait);
        }

        function shouldShowForLink(anchor, event) {
            if (!anchor || event.defaultPrevented) {
                return false;
            }

            if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                return false;
            }

            if (anchor.hasAttribute('download')) {
                return false;
            }

            var rawHref = (anchor.getAttribute('href') || '').trim();
            if (!rawHref || rawHref === '#' || rawHref.indexOf('javascript:') === 0) {
                return false;
            }

            var target = (anchor.getAttribute('target') || '').trim().toLowerCase();
            if (target && target !== '_self') {
                return false;
            }

            var url;
            try {
                url = new URL(anchor.href, window.location.href);
            } catch (error) {
                return false;
            }

            if (url.protocol !== 'http:' && url.protocol !== 'https:') {
                return false;
            }

            if (
                url.origin === window.location.origin
                && url.pathname === window.location.pathname
                && url.search === window.location.search
                && url.hash
            ) {
                return false;
            }

            return true;
        }

        function shouldPrefetchLink(anchor) {
            if (!anchor || anchor.hasAttribute('download')) {
                return false;
            }

            if (anchor.classList.contains('js-logout-link') || anchor.classList.contains('danger')) {
                return false;
            }

            var rawHref = (anchor.getAttribute('href') || '').trim();
            if (!rawHref || rawHref === '#' || rawHref.indexOf('javascript:') === 0) {
                return false;
            }

            var target = (anchor.getAttribute('target') || '').trim().toLowerCase();
            if (target && target !== '_self') {
                return false;
            }

            var url;
            try {
                url = new URL(anchor.href, window.location.href);
            } catch (error) {
                return false;
            }

            if (url.origin !== window.location.origin || url.protocol !== window.location.protocol) {
                return false;
            }

            if (url.pathname === window.location.pathname && url.search === window.location.search) {
                return false;
            }

            return /\.(?:pdf|zip|jpg|jpeg|png|gif|webp|mp4|docx?|xlsx?)$/i.test(url.pathname) === false;
        }

        function prefetchLink(anchor) {
            if (!shouldPrefetchLink(anchor)) {
                return;
            }

            var url = new URL(anchor.href, window.location.href);
            var key = url.href;
            if (prefetchedUrls[key]) {
                return;
            }

            prefetchedUrls[key] = true;
            var link = document.createElement('link');
            link.rel = 'prefetch';
            link.href = key;
            link.as = 'document';
            document.head.appendChild(link);
        }

        function isNavigationPrefetchAnchor(anchor) {
            if (!anchor) {
                return false;
            }

            return !!(
                anchor.classList.contains('dash-nav-item')
                || anchor.classList.contains('dash-top-profile-link')
                || anchor.classList.contains('mobile-msg-icon')
            );
        }

        if (!hidden && (document.readyState === 'interactive' || document.readyState === 'complete')) {
            hideWhenReady();
        }
        if (!hidden) {
            startLensTracking();
        }

        document.addEventListener('click', function (event) {
            var target = event.target;
            var anchor = target && target.closest ? target.closest('a[href]') : null;
            if (!anchor || (!anchor.hasAttribute('data-tm-show-loading') && !anchor.closest('.dash-nav, .dash-top-profile-dropdown, .mobile-top-profile-dropdown'))) {
                return;
            }

            if (anchor.classList.contains('js-logout-link') || anchor.classList.contains('danger')) {
                return;
            }

            if (!shouldShowForLink(anchor, event)) {
                return;
            }

            showLoader();
        });

        document.addEventListener('pointerenter', function (event) {
            var target = event.target;
            var anchor = target && target.closest ? target.closest('a[href]') : null;
            if (isNavigationPrefetchAnchor(anchor)) {
                prefetchLink(anchor);
            }
        }, true);

        document.addEventListener('touchstart', function (event) {
            var target = event.target;
            var anchor = target && target.closest ? target.closest('a[href]') : null;
            if (isNavigationPrefetchAnchor(anchor)) {
                prefetchLink(anchor);
            }
        }, { passive: true, capture: true });

        document.addEventListener('focusin', function (event) {
            var target = event.target;
            var anchor = target && target.closest ? target.closest('a[href]') : null;
            if (isNavigationPrefetchAnchor(anchor)) {
                prefetchLink(anchor);
            }
        });

        document.addEventListener('submit', function (event) {
            if (event.defaultPrevented) {
                return;
            }

            var form = event.target;
            if (!form || !form.hasAttribute || !form.hasAttribute('data-tm-show-loading')) {
                return;
            }

            showLoader();
        });

        window.addEventListener('resize', startLensTracking);
        if (!hidden) {
            document.addEventListener('DOMContentLoaded', hideWhenReady, { once: true });
            window.addEventListener('load', hideWhenReady, { once: true });
            window.addEventListener('pageshow', hideWhenReady, { once: true });
            window.setTimeout(function () {
                hideWhenReady(true);
            }, maximumVisibleMs);
        }

        window.__tmShowLoadingScreen = showLoader;
        window.__tmHideLoadingScreen = hideWhenReady;
    })();
</script>
