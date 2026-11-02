/**
 * Normalise a block's `postType` to the slugs the server expects.
 *
 * Blocks save and pass around a singular post type (`package`), but
 * saved content can carry the plural too. The query resolver wants the
 * singular slug, while `ResourceResolver` and the REST collections are
 * keyed by the resource slug, which the content-type registry publishes
 * as `plural`.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.20.0
 */

import { getContentTypes } from './content-type-registry';

const DEFAULT_COLLECTION = 'posts';

// Match the host's registered content-type slugs verbatim — anything
// outside `[a-z0-9_-]` (slashes, dots, path traversal characters)
// would compose into the REST URL we fetch from, so we never let an
// unvetted attribute through. Unknown-but-syntactically-clean slugs
// still pluralize so a host that hasn't published its content-types
// list yet (fallback registry) gets a best-effort fetch instead of
// hard-failing.
const SAFE_SLUG_PATTERN = /^[a-z0-9_-]+$/;

function normalize(rawType: string): string {
    return (rawType || 'post').trim().toLowerCase();
}

function findContentType(type: string) {
    return getContentTypes().find(
        (entry) => entry.slug === type || entry.plural === type
    );
}

/**
 * The resource slug for a post type: the key `ResourceResolver` and the
 * `/visual-editor/api/{resource}` collection routes look it up under.
 *
 * @since 1.20.0
 */
export function resolveCollection(rawType: string): string {
    const type = normalize(rawType);
    const match = findContentType(type);
    if (match !== undefined) {
        return match.plural;
    }

    if (!SAFE_SLUG_PATTERN.test(type)) {
        return DEFAULT_COLLECTION;
    }

    if (type.endsWith('s')) {
        return type;
    }
    if (type.endsWith('y')) {
        return `${type.slice(0, -1)}ies`;
    }
    return `${type}s`;
}

/**
 * The singular post type the query resolver expects. Blocks saved with
 * a plural `postType` (e.g. `posts`) map back through the registry,
 * matching the `Str::singular()` normalization `QueryInliner` applies
 * on render.
 *
 * @since 1.20.0
 */
export function resolvePostType(rawType: string): string {
    const type = normalize(rawType);
    const match = findContentType(type);
    if (match !== undefined) {
        return match.slug;
    }
    return SAFE_SLUG_PATTERN.test(type) ? type : 'post';
}
