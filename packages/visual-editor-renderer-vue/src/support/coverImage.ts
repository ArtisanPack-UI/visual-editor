/**
 * The background image URL a Cover renders, mirroring `cover.blade.php`.
 *
 * With "Use featured image" on, the cover shows the stamped
 * `_resolvedImageUrl` (the current post's featured image) and ignores its
 * saved `url`, as in WordPress (#942).
 *
 * @since 1.15.2
 */

import { attrBoolean, attrString } from './attributes';
import { safeUrl } from './urlSanitizer';

export function coverImageUrl(attributes: Record<string, unknown>): string {
    return attrBoolean(attributes.useFeaturedImage)
        ? safeUrl(attrString(attributes._resolvedImageUrl))
        : safeUrl(attributes.url);
}
