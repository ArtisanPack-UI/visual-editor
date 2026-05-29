/**
 * Forked entity-block save delegation.
 *
 * Most Phase I5 entity forks are fully dynamic (save returns `null`).
 * `artisanpack/navigation` is the exception: `core/navigation` persists its
 * inner blocks in the saved markup, so the fork must serialize identically.
 * Rather than re-port the upstream save markup (and risk drift), the fork
 * delegates serialization to the registered `core/navigation` save — kept
 * registered by the forked-block cutover. Phase I5 entity cluster (#413).
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
