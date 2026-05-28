/**
 * Code — escape helpers.
 *
 * Ported from `@wordpress/block-library/src/code/utils.js` (v9.43.0).
 * Two transforms in series: escape opening square brackets so shortcodes
 * don't execute on the PHP side, and rewrite isolated-URL protocol
 * slashes so embeds don't auto-resolve.
 */

function escapeOpeningSquareBrackets(content: string): string {
    return content.replace(/\[/g, '&#91;');
}

function escapeProtocolInIsolatedUrls(content: string): string {
    return content.replace(
        /^(\s*https?:)\/\/([^\s<>"]+\s*)$/m,
        '$1&#47;&#47;$2'
    );
}

export function escape(content: string): string {
    return escapeProtocolInIsolatedUrls(
        escapeOpeningSquareBrackets(content || '')
    );
}
