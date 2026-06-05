/**
 * PostAuthorBiography — save component.
 *
 * Dynamic block: the saved markup is just the block delimiter and the HTML
 * is produced server-side by the Blade / React / Vue renderers from the
 * stamped `_resolvedAuthorBio` attribute. Returning `null` from save is
 * the Gutenberg convention for dynamic blocks. Author family fork (#518).
 */

export default function PostAuthorBiographySave(): null {
    return null;
}
