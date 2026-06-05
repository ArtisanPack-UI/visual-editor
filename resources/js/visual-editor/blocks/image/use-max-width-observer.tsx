/**
 * Image — max width observer hook.
 *
 * Ported from `@wordpress/block-library/src/image/use-max-width-observer.js`
 * (v9.43.0). Vendored so the fork does not reach into the upstream package
 * internals (blocked by its `exports` field).
 */

import type { ReactElement } from 'react';
import { useRef } from '@wordpress/element';
import { useResizeObserver } from '@wordpress/compose';

export function useMaxWidthObserver(): readonly [ReactElement, number | undefined] {
    const [contentResizeListener, { width }] = useResizeObserver();
    const observerRef = useRef<HTMLDivElement | null>(null);

    const maxWidthObserver = (
        <div
            // Some themes set max-width on blocks.
            className="wp-block"
            aria-hidden="true"
            style={{
                position: 'absolute',
                inset: 0,
                width: '100%',
                height: 0,
                margin: 0,
            }}
            ref={observerRef}
        >
            {contentResizeListener}
        </div>
    );

    return [maxWidthObserver, width] as const;
}

export default useMaxWidthObserver;
