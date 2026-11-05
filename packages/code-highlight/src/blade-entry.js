/**
 * Blade renderer entry (#1044). Runs by itself once the page is ready, and
 * again on `ap:rebind`, the event `interactivity.js` also listens to.
 */

import { highlightElement } from './highlighter.js';

function init() {
    document.querySelectorAll('pre.wp-block-code > code[class*="language-"]').forEach(highlightElement);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
} else {
    init();
}

window.addEventListener('ap:rebind', init);
