/**
 * Test helper — load the real `@wordpress/blocks` / `@wordpress/hooks`
 * through their CommonJS builds.
 *
 * The ESM build of `@wordpress/blocks` imports a JSON file without an
 * import attribute, which Node 22+ refuses to load when Vitest externalizes
 * the package. The CJS build `require()`s the same JSON without issue.
 * `@wordpress/hooks` must come from the same (CJS) module graph so filters
 * added by source code (`addFilter`) are the ones `registerBlockType`
 * applies.
 *
 * Use from a `vi.mock()` factory:
 *
 *     vi.mock('@wordpress/blocks', () => requireCjs('@wordpress/blocks'));
 *
 * @since 1.12.0
 */

import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);

/**
 * `require()` a package through Node's CommonJS resolver.
 *
 * @since 1.12.0
 *
 * @param id Package specifier.
 * @return The CommonJS module exports.
 */
export function requireCjs<T = Record<string, unknown>>(id: string): T {
    return require(id) as T;
}

/**
 * Stand-in for an upstream `@wordpress/block-library` block entrypoint.
 * `init()` performs the same unguarded `registerBlockType` call upstream's
 * `initBlock` makes, using the block's real `block.json`, with a no-op
 * edit/save.
 *
 * @since 1.12.0
 *
 * @param name Fully-qualified block name, e.g. `core/navigation`.
 * @return Module shape exposing `init`.
 */
export function fakeUpstreamInit(name: string): { init: () => unknown } {
    const slug = name.replace('core/', '');

    return {
        init: () => {
            const { registerBlockType } = requireCjs<{
                registerBlockType: (...args: unknown[]) => unknown;
            }>('@wordpress/blocks');
            const metadata = requireCjs<Record<string, unknown>>(
                `@wordpress/block-library/build-module/${slug}/block.json`,
            );

            return registerBlockType(
                { ...metadata, name },
                { edit: () => null, save: () => null },
            );
        },
    };
}
