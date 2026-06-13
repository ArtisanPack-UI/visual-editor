/**
 * Copyright — save component.
 *
 * Dynamic block: the line is built at render time by the Blade / React /
 * Vue renderers so the current year reflects the actual request, not the
 * post's last-saved timestamp. Returning `null` from save is the
 * Gutenberg convention for dynamic blocks — only the block delimiter
 * and attributes are persisted.
 */

export default function CopyrightSave(): null {
    return null;
}
