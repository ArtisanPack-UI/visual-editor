/**
 * Forked entity-block edit delegation.
 *
 * The Phase I5 entity cluster (#413) forks 11 server-rendered `core/*`
 * blocks — `template-part`, the `post-*` family, the `site-*` family, and
 * `navigation` — into the `artisanpack/*` namespace. Unlike the content /
 * media / layout clusters, these blocks read live data through
 * `@wordpress/core-data`'s `useEntityRecord` / `useEntityProp` surface
 * (the package's core-data shim, wired by #395 / #399) and, for
 * `navigation` and `template-part`, drive the substantial V1 editor
 * surfaces the site editor already ships.
 *
 * Re-porting those edit components byte-for-byte would duplicate thousands
 * of lines of upstream code and risk regressing the V1 nav-editor and
 * template-part editing surfaces the issue explicitly protects. Instead,
 * each fork's `edit` delegates to the matching `core/*` block's edit
 * component, which is still registered (the forked-block cutover only sets
 * `inserter: false` on it — see `editor/forked-block-cutover.ts`). The fork
 * therefore renders the *same* edit surface upstream + V1 already provide,
 * against the same shim selectors, with zero divergence.
 *
 * Lookup happens at render time, not module-eval time: forks are discovered
 * and registered *after* `registerCoreBlocks()` runs (see
 * `editor/editor-app.tsx`), so the core block is always registered by the
 * time the fork's edit first renders.
 */

import type { ComponentType } from 'react';
import { getBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';

// eslint-disable-next-line @typescript-eslint/no-explicit-any
type AnyProps = Record<string, any>;

/**
 * Build an `edit` component for an `artisanpack/*` entity fork that
 * delegates to its `core/*` counterpart's registered edit.
 *
 * @param coreName Fully-qualified upstream block name, e.g. `core/post-title`.
 */
export function createForkedEntityEdit(
    coreName: string
): ComponentType<AnyProps> {
    function ForkedEntityEdit( props: AnyProps ): JSX.Element {
        const coreType = getBlockType( coreName );
        const CoreEdit = coreType?.edit as
            | ComponentType<AnyProps>
            | undefined;

        if ( CoreEdit ) {
            return <CoreEdit { ...props } />;
        }

        // The core block isn't registered (e.g. a host app stripped
        // `@wordpress/block-library`, or a unit test renders the fork in
        // isolation). Fall back to an empty block wrapper so the editor
        // still mounts the block without throwing.
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        const blockProps = ( useBlockProps as any )();

        return <div { ...blockProps } />;
    }

    ForkedEntityEdit.displayName = `ForkedEntityEdit(${ coreName })`;

    return ForkedEntityEdit;
}

export default createForkedEntityEdit;
