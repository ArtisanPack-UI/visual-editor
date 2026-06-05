/**
 * Fallback markup for blocks that have no registered React renderer and that
 * the dynamic-block endpoint does not recognize either. Mirrors the
 * `<!-- visual-editor: no partial for ... -->` comment + `<div>` wrapper the
 * Blade renderer emits.
 */

import type { ReactNode } from 'react';

export interface UnknownBlockProps {
    name: string;
    children?: ReactNode;
}

export function UnknownBlock({ name, children }: UnknownBlockProps): JSX.Element {
    return (
        <div data-ve-unknown-block={name}>
            {children}
        </div>
    );
}
