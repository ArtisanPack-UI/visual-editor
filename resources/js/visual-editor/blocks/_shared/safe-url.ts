/**
 * Editor-side URL scheme allow-list for iframe / src destinations (#761).
 *
 * The four business-info block edit views embed a `mapEmbedUrl` composed
 * by the host's `ap.visualEditor.businessInfo` filter directly into an
 * `<iframe src>` on the editor canvas. A host filter that returned
 * `javascript:` (or `data:` / `vbscript:`) would execute under the editor
 * origin. The Blade / React / Vue renderers all funnel `mapEmbedUrl`
 * through their own sanitizers before rendering; this helper mirrors
 * that guarantee for the editor bundle.
 *
 * Kept in the editor bundle (rather than imported from a renderer
 * package) because `resources/js/visual-editor/blocks/**` must not
 * depend on `packages/visual-editor-renderer-*`.
 *
 * Allow-list is `http:` and `https:` only — map embed URLs from both
 * OpenStreetMap and Google Maps are always https. A relative URL is
 * NOT allowed here — a map iframe from a relative host URL is not a
 * valid use case, and denying them keeps the check strict.
 */
const ALLOWED_SCHEMES: ReadonlyArray<string> = ['http:', 'https:'];

export function safeIframeUrl(url: unknown): string {
    if (typeof url !== 'string') {
        return '';
    }

    const trimmed = url.trim();

    if ('' === trimmed) {
        return '';
    }

    const match = /^([a-z][a-z0-9+\-.]*:)/i.exec(trimmed);

    if (null === match) {
        return '';
    }

    return ALLOWED_SCHEMES.includes(match[1].toLowerCase()) ? trimmed : '';
}
