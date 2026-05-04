/**
 * Site-editor entity registration — H6 (#431).
 *
 * Re-exports the H6-managed entity descriptors as an editor-bootable
 * list and provides a `registerSiteEditorEntities` helper that
 * idempotently merges them into the core-data shim's registry via
 * `dispatch('core').addEntities()`.
 *
 * The shim ships these descriptors in `DEFAULT_ENTITIES` already, so
 * the production boot path doesn't need to call this helper — the
 * registrations are present from store init. The helper exists so
 * host apps that have separately added a custom entity set via
 * `dispatch('core').addEntities([...])` (e.g. for a custom CPT) and
 * want to be sure the H6 descriptors are also present can opt in
 * with a single named call. It also gives the site-editor entry
 * point a stable surface if a future plan splits these descriptors
 * out of `DEFAULT_ENTITIES`. Note that the shim's
 * {@see configureCoreDataShim} accepts only `apiBase` + `fetcher`
 * — there is no entity-list override hook on the config object
 * itself; runtime entity registration happens through the
 * `addEntities` action.
 *
 * @see plan 14 §4.5 for the WP REST shape mirrored by each entity.
 *
 * @since 1.0.0
 */

import { dispatch } from '@wordpress/data';

import { type EntityConfig, DEFAULT_ENTITIES } from '../vendor/core-data-shim';

/**
 * Store id the shim registers with `@wordpress/data`. Mirrors the
 * `STORE_NAME` constant inside the shim — duplicated as a string
 * literal here so this module doesn't depend on a non-exported
 * internal.
 */
const CORE_DATA_STORE = 'core';

/**
 * Names of the five H6-managed entities. Used to filter
 * {@see DEFAULT_ENTITIES} down to the H6 subset and to detect
 * already-registered entries when merging.
 */
const SITE_EDITOR_ENTITY_NAMES: readonly string[] = Object.freeze([
    'wp_template',
    'wp_template_part',
    'wp_navigation',
    'wp_navigation_link',
    'wp_block',
] as const);

/**
 * The H6 entity descriptors as a readonly list, sourced from the
 * shim's DEFAULT_ENTITIES so the two sources can never drift out of
 * sync. Order mirrors the addEntities example in plan 14 §4.5
 * (templates, parts, patterns, navigation, global-styles).
 */
export const SITE_EDITOR_ENTITIES: readonly EntityConfig[] = Object.freeze(
    [
        ...DEFAULT_ENTITIES.filter((entity) =>
            SITE_EDITOR_ENTITY_NAMES.includes(entity.name)
        ),
        ...DEFAULT_ENTITIES.filter(
            (entity) => entity.kind === 'root' && entity.name === 'globalStyles'
        ),
    ].map((entity) => Object.freeze({ ...entity }))
);

/**
 * Idempotently register the H6 site-editor entities with the
 * core-data shim. Safe to call multiple times — the shim's
 * `ADD_ENTITIES` reducer overwrites any existing key with the same
 * `(kind, name)` pair, so re-registration just refreshes the entries.
 *
 * Production callers don't need to invoke this in the standard boot
 * path because {@see DEFAULT_ENTITIES} already includes these
 * descriptors. Host apps that have replaced or extended the registry
 * via runtime `dispatch('core').addEntities([...])` calls can call
 * this helper to ensure the H6 site-editor descriptors stay
 * registered without re-listing them by hand.
 *
 * @since 1.0.0
 */
export function registerSiteEditorEntities(): void {
    dispatch(CORE_DATA_STORE).addEntities(SITE_EDITOR_ENTITIES);
}
