/**
 * #808 H1 diagnostic — DELETE ONCE H1 IS RESOLVED.
 *
 * Imports the core-data shim via BOTH resolution paths:
 *   1. `@wordpress/core-data` (Vite alias → shim absolute path)
 *   2. `../vendor/core-data-shim` (direct relative path)
 *
 * If Vite serves them from one module instance, `__AP_SHIM_INSTANCE_ID__`
 * will match and the imported bindings will be `===`. If not, we've
 * confirmed the split-brain theory driving the `Store "core" is already
 * registered` error and the dead "Add menu item" click.
 *
 * Also logs `wp.data.select('core')` from the ambient global scope so we
 * can compare that reference against what the shim sees.
 */

import * as ViaAlias from '@wordpress/core-data';
import * as ViaRelative from '../vendor/core-data-shim';

export function runH1ShimIdentityProbe(): void {
    if (typeof window === 'undefined') {
        return;
    }

    const aliasId = (ViaAlias as unknown as { __AP_SHIM_INSTANCE_ID__?: string })
        .__AP_SHIM_INSTANCE_ID__;
    const relativeId = (
        ViaRelative as unknown as { __AP_SHIM_INSTANCE_ID__?: string }
    ).__AP_SHIM_INSTANCE_ID__;

    const sameModule = ViaAlias === ViaRelative;
    const sameUseEntityBlockEditor =
        (ViaAlias as unknown as Record<string, unknown>).useEntityBlockEditor ===
        (ViaRelative as unknown as Record<string, unknown>).useEntityBlockEditor;

    const w = window as unknown as {
        wp?: { data?: { select?: (name: string) => unknown; dispatch?: (name: string) => unknown } };
        __apShimInstances?: unknown[];
    };
    const ambientSelectCore = w.wp?.data?.select?.('core');
    const ambientDispatchCore = w.wp?.data?.dispatch?.('core');

    // eslint-disable-next-line no-console
    console.log('[AP #808 H1] shim identity probe', {
        aliasInstanceId: aliasId,
        relativeInstanceId: relativeId,
        idsMatch: aliasId === relativeId,
        sameModuleNamespace: sameModule,
        sameUseEntityBlockEditor,
        totalShimInstancesEvaluated: w.__apShimInstances?.length,
        shimInstances: w.__apShimInstances,
        ambientSelectCore,
        ambientDispatchCore,
    });
}
