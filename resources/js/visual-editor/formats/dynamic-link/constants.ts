/**
 * Shared constants for the Dynamic-Content-aware link format (#662).
 *
 * Kept in a leaf module so `register.ts`, `edit.tsx`, and `value.ts` can
 * share them without an import cycle (register → edit → register would
 * otherwise read `LINK_FORMAT_NAME` in its temporal dead zone).
 *
 * @since 1.7.0
 */

export const LINK_FORMAT_NAME = 'core/link';

/**
 * The `core/link` attribute → HTML-attribute mapping, copied verbatim
 * from `@wordpress/format-library` so `create()`/`toHTMLString()` keep
 * mapping `<a>` tags to and from the same format shape.
 */
export const LINK_ATTRIBUTES = {
    url: 'href',
    type: 'data-type',
    id: 'data-id',
    _id: 'id',
    target: 'target',
    rel: 'rel',
    class: 'class',
} as const;
