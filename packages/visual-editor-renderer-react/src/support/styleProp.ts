/**
 * Empty-style guard for block wrappers — React renderer (#920).
 *
 * React drops a `style` prop whose value is `undefined`, so returning
 * `undefined` for an empty style object keeps a bare `style=""` out of the
 * markup and in step with Blade. The Vue renderer's `withStyle()` in
 * `blocks/shared.ts` plays the same role there.
 *
 * @package @artisanpack-ui/visual-editor-renderer-react
 * @since 1.15.3
 */

import type React from 'react';

/** A style object for a `style` prop, or `undefined` when it is empty. */
export function styleProp(style: Record<string, string>): React.CSSProperties | undefined {
    return Object.keys(style).length > 0 ? (style as React.CSSProperties) : undefined;
}
