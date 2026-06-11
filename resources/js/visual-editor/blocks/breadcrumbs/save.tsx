/**
 * Breadcrumbs — save component.
 *
 * Dynamic block: the trail is resolved server-side from the current
 * request (the renderers consume the stamped `_resolvedTrail` attribute).
 * Returning `null` from save is the Gutenberg convention for dynamic
 * blocks — only the block delimiter and attributes are persisted.
 */

export default function BreadcrumbsSave(): null {
    return null;
}
