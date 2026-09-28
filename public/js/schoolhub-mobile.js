/*
 * Helpers for SchoolHub (loaded on every panel page):
 *   1. registers the service worker, so the app can be installed;
 *   2. the "add to home screen" tip (Android install prompt, or iPhone steps);
 *   3. calmer handling of failed background requests, and a clear notice
 *      when the phone loses its internet connection (see below).
 */
(function () {
    /*
     * Filament shows "Error while loading page" for any background request
     * that fails. These everyday cases are not real errors:
     *   - moving to another page while this one is still loading (common on
     *     phones with slow data): the unfinished request is simply dropped;
     *   - the login has expired after a long idle spell (419): reload, which
     *     leads to the sign-in page;
     *   - SchoolHub is updating for a minute (503): a plain banner says so;
     *   - the connection dropped: "You are offline" / "Connection problem".
     */
    let savedNotifications = null;
    const leaving = () => {
        if (window.filamentErrorNotifications) {
            savedNotifications = window.filamentErrorNotifications;
            window.filamentErrorNotifications = undefined;
        }
    };
    window.addEventListener('pagehide', leaving);
    window.addEventListener('beforeunload', leaving);
    window.addEventListener('pageshow', () => {
        if (savedNotifications) {
            window.filamentErrorNotifications = savedNotifications;
            savedNotifications = null;
        }
    });
    document.addEventListener('click', (event) => {
        const link = event.target.closest && event.target.closest('a[href]');
        if (link && !link.target && link.origin === location.origin && !event.defaultPrevented && !link.hasAttribute('download')) {
            leaving();
            // If the click did not actually leave the page, restore.
            setTimeout(() => {
                if (savedNotifications && document.visibilityState === 'visible') {
                    window.filamentErrorNotifications = savedNotifications;
                    savedNotifications = null;
                }
            }, 8000);
        }
    }, true);

    // Plain banners: unlike Filament's notices they need no request to the
    // server, which is exactly what fails while SchoolHub is updating or
    // the phone has lost its connection.
    const BANNER_TONES = {
        dark: 'background:#0d1f38;color:#fff',
        warn: 'background:#fff7ed;color:#9a3412;border:1px solid #fdba74',
        good: 'background:#f0fdf4;color:#166534;border:1px solid #86efac',
    };
    let banner = null;
    let bannerTimer = null;

    function showBanner(tone, title, text, hideAfterMs) {
        if (!banner) {
            banner = document.createElement('div');
            banner.id = 'sh-status-banner';
            banner.setAttribute('role', 'status');
            banner.setAttribute('aria-live', 'polite');
            document.body.appendChild(banner);
        }
        banner.style.cssText = 'position:fixed;left:50%;top:14px;transform:translateX(-50%);z-index:9999;width:min(92vw,26rem);box-sizing:border-box;'
            + 'padding:.8rem 1rem;border-radius:.8rem;font:600 .88rem/1.4 system-ui,sans-serif;text-align:center;'
            + 'box-shadow:0 12px 30px -10px rgba(13,31,56,.45);' + BANNER_TONES[tone];
        banner.innerHTML = title + (text ? '<br><span style="font-weight:400;opacity:.85">' + text + '</span>' : '');
        banner.style.display = 'block';
        clearTimeout(bannerTimer);
        if (hideAfterMs) {
            bannerTimer = setTimeout(hideBanner, hideAfterMs);
        }
    }

    function hideBanner() {
        if (banner) banner.style.display = 'none';
    }

    const OFFLINE_TITLE = 'You are offline';
    const OFFLINE_TEXT = 'Check your data or Wi-Fi. Anything you save now will not go through until the connection is back.';

    window.addEventListener('offline', () => showBanner('warn', OFFLINE_TITLE, OFFLINE_TEXT, 0));
    window.addEventListener('online', () => showBanner('good', 'Back online', 'You can carry on where you left off.', 4000));
    document.addEventListener('DOMContentLoaded', () => {
        if (navigator.onLine === false) showBanner('warn', OFFLINE_TITLE, OFFLINE_TEXT, 0);
    });

    document.addEventListener('livewire:init', () => {
        window.Livewire.interceptRequest(({ onError, onFailure }) => {
            onError(({ response, preventDefault }) => {
                const status = response ? response.status : 0;

                if (status === 419) {
                    preventDefault();
                    window.location.reload();
                } else if (status === 503) {
                    preventDefault();
                    showBanner('dark', 'SchoolHub is being updated.', 'This takes about a minute. Please try again shortly — nothing you entered has been lost.', 12000);
                }
            });

            // The request never reached SchoolHub (no connection, or it
            // dropped mid-way). Say so plainly instead of Filament's
            // "Error while loading page", which itself needs the server.
            onFailure(() => {
                if (!window.filamentErrorNotifications) {
                    return; // leaving the page: nothing to report
                }

                const notifications = window.filamentErrorNotifications;
                window.filamentErrorNotifications = undefined;
                setTimeout(() => { window.filamentErrorNotifications = notifications; }, 0);

                if (navigator.onLine === false) {
                    showBanner('warn', OFFLINE_TITLE, OFFLINE_TEXT, 0);
                } else {
                    showBanner('warn', 'Connection problem', 'That did not reach SchoolHub, so it was not saved. Please try again.', 10000);
                }
            });
        });
    });

    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
    }

    let deferredPrompt = null;
    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        deferredPrompt = event;
        window.dispatchEvent(new CustomEvent('sh-can-install'));
    });

    const KEY = 'sh-install-tip-dismissed';
    const standalone = () => window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    const isPhone = () => window.matchMedia('(max-width: 768px)').matches;
    const isIos = () => /iphone|ipad|ipod/i.test(navigator.userAgent);
    const dismissed = () => { try { return localStorage.getItem(KEY) === '1'; } catch (e) { return true; } };

    document.addEventListener('alpine:init', () => {
        window.Alpine.data('shInstallTip', () => ({
            show: false,
            canPrompt: false,
            init() {
                if (standalone() || !isPhone() || dismissed()) return;
                if (deferredPrompt) { this.canPrompt = true; this.show = true; }
                window.addEventListener('sh-can-install', () => { this.canPrompt = true; this.show = true; });
                if (isIos()) { this.show = true; }
            },
            async install() {
                if (!deferredPrompt) return;
                deferredPrompt.prompt();
                await deferredPrompt.userChoice;
                deferredPrompt = null;
                this.dismiss();
            },
            dismiss() {
                this.show = false;
                try { localStorage.setItem(KEY, '1'); } catch (e) {}
            },
        }));
    });
})();
