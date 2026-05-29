/**
 * SiteTitle — save component.
 *
 * Dynamic block: the saved markup is just the block delimiter and the HTML
 * is produced server-side by the Blade / React / Vue renderers from the
 * stamped `_resolved*` attributes. Returning `null` from save is the
 * Gutenberg convention for dynamic blocks. Phase I5 entity cluster (#413).
 */

export default function SiteTitleSave(): null {
    return null;
}
