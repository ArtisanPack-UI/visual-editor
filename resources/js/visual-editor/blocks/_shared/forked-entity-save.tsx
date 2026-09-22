/**
 * Forked entity-block save delegation.
 *
 * Every remaining Phase I5 entity fork is fully dynamic (save returns
 * `null`), so this helper's `getBlockType`/`CoreSave` lookup is currently
 * a no-op safety net. It stays wired because it preserves the shape the
 * fork cluster is built against, and because `core/navigation` was
 * previously the one exception — should any future fork need identical
 * serialized markup, the delegation pattern is already in place. Phase
 * I5 entity cluster (#413); nav exception reverted in #808.
 */

import type { ComponentType } from 'react';
import { getBlockType } from '@wordpress/blocks';

// eslint-disable-next-line @typescript-eslint/no-explicit-any
type AnyProps = Record<string, any>;

/**
 * Build a `save` component for an `artisanpack/*` entity fork that
 * delegates to its `core/*` counterpart's registered save.
 *
 * @param coreName Fully-qualified upstream block name, e.g. `core/navigation`.
 */
export function createForkedEntitySave(
    coreName: string
): ComponentType<AnyProps> {
    function ForkedEntitySave( props: AnyProps ): JSX.Element | null {
        const coreType = getBlockType( coreName );
        const CoreSave = coreType?.save as
            | ComponentType<AnyProps>
            | undefined;

        if ( CoreSave ) {
            return <CoreSave { ...props } />;
        }

        // Core block not registered (host stripped block-library, or unit
        // test in isolation): persist nothing rather than throw. The
        // server-side renderers reproduce the markup regardless.
        return null;
    }

    ForkedEntitySave.displayName = `ForkedEntitySave(${ coreName })`;

    return ForkedEntitySave;
}

export default createForkedEntitySave;
