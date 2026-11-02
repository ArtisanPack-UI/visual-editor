/**
 * Blade renderer entry (#1046). Runs by itself once the page is ready, and
 * again on `ap:rebind`, the event `interactivity.js` also listens to, and after
 * Livewire or Turbo navigation, which replace the body without re-running this
 * script. A host may re-run it anyway, so a second copy uses the first one's
 * binding rather than opening a second overlay on each click.
 */

import { bindLightbox } from './lightbox.js';

const isFirstRun = window.apLightboxBind === undefined;

if (isFirstRun) {
    window.apLightboxBind = bindLightbox;
}

function init() {
    window.apLightboxBind(document);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
} else {
    init();
}

if (isFirstRun) {
    window.addEventListener('ap:rebind', init);
    document.addEventListener('livewire:navigated', init);
    document.addEventListener('turbo:load', init);
}
