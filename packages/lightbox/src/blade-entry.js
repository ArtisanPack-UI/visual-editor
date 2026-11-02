/**
 * Blade renderer entry (#1046). A host may re-run this script, so a second copy
 * leaves the first one's listeners in place rather than adding its own.
 */

import { bindLightbox } from './lightbox.js';

if (window.apLightboxBound !== true) {
    window.apLightboxBound = true;
    bindLightbox(document);
}
