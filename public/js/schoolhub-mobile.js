/*
 * Helpers for SchoolHub (loaded on every panel page):
 *   1. registers the service worker, so the app can be installed;
 *   2. the "add to home screen" tip (Android install prompt, or iPhone steps);
 *   3. calmer handling of failed background requests (see below).
 */
(function () {
    /*
     * Filament shows "Error while loading page" for any background request
     * that fails. Three everyday cases are not real errors:
     *   - moving to another page while this one is still loading (common on
     *     phones with slow data): the unfinished request is simply dropped;
     *   - the login has expired after a long idle spell (419): reload, which
     *     leads to the sign-in page;
     *   - SchoolHub is updating for a minute (503): a plain banner says so.
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

    // A plain banner: unlike Filament's notices it needs no request to the
    // server, which is exactly what fails while SchoolHub is updating.
    let bannerTimer = null;
    function showUpdatingBanner() {
        let banner = document.getElementById('sh-updating');
        if (!banner) {
            banner = document.createElement('div');
            banner.id = 'sh-updating';
            banner.setAttribute('role', 'status');
            banner.style.cssText = 'position:fixed;left:50%;top:14px;transform:translateX(-50%);z-index:9999;width:min(92vw,26rem);box-sizing:border-box;'
                + 'padding:.8rem 1rem;border-radius:.8rem;background:#0d1f38;color:#fff;font:600 .88rem/1.4 system-ui,sans-serif;'
                + 'box-shadow:0 12px 30px -10px rgba(13,31,56,.6);text-align:center';
            banner.innerHTML = 'SchoolHub is being updated.<br><span style="font-weight:400;color:#cbd5e1">This takes about a minute. Please try again shortly — nothing you entered has been lost.</span>';
            document.body.appendChild(banner);
        }
        banner.style.display = 'block';
        clearTimeout(bannerTimer);
        bannerTimer = setTimeout(() => { banner.style.display = 'none'; }, 12000);
    }

    document.addEventListener('livewire:init', () => {
        window.Livewire.interceptRequest(({ onError }) => {
            onError(({ response, preventDefault }) => {
                const status = response ? response.status : 0;

                if (status === 419) {
                    preventDefault();
                    window.location.reload();
                } else if (status === 503) {
                    preventDefault();
                    showUpdatingBanner();
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
