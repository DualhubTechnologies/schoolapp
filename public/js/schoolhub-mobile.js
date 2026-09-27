/*
 * Phone helpers for SchoolHub (loaded on every panel page):
 *   1. registers the service worker, so the app can be installed;
 *   2. the "add to home screen" tip (Android install prompt, or iPhone steps).
 */
(function () {
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
