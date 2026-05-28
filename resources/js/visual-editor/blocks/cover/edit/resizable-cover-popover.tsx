/**
 * Cover — resizable cover popover.
 *
 * Ported from
 * `@wordpress/block-library/src/cover/edit/resizable-cover-popover.js`
 * (v9.43.0). Upstream wraps the private
 * `ResizableBoxPopover` API (`unlock(blockEditorPrivateApis).ResizableBoxPopover`).
 * The fork inlines a `ResizableBox` wrapper rendered in-flow so the fork
 * does not depend on `lock-unlock` block-library internals. This is
 * documented under `knownDivergences` in `upstream-state.json`.
 */

import type { ReactElement } from 'react';
import clsx from 'clsx';
import { useState } from '@wordpress/element';
import { ResizableBox } from '@wordpress/components';

const RESIZABLE_BOX_ENABLE_OPTION = {
    top: false,
    right: false,
    bottom: true,
    left: false,
    topRight: false,
    bottomRight: false,
    bottomLeft: false,
    topLeft: false,
};

interface ResizableCoverPopoverProps {
    className?: string;
    height?: number;
    minHeight?: number | string;
    onResize: (value: number) => void;
    onResizeStart: (value: number) => void;
    onResizeStop: (value: number) => void;
    showHandle?: boolean;
    size: { height: number | string; width: number | string };
    width?: number;
    [key: string]: unknown;
}

export default function ResizableCoverPopover({
    className,
    onResize,
    onResizeStart,
    onResizeStop,
    showHandle,
    size,
}: ResizableCoverPopoverProps): ReactElement {
    const [isResizing, setIsResizing] = useState<boolean>(false);

    return (
        <ResizableBox
            className={clsx(className, { 'is-resizing': isResizing })}
            enable={RESIZABLE_BOX_ENABLE_OPTION}
            onResizeStart={(
                _event,
                _direction,
                elt: HTMLElement
            ) => {
                onResizeStart(elt.clientHeight);
                onResize(elt.clientHeight);
            }}
            onResize={(_event, _direction, elt: HTMLElement) => {
                onResize(elt.clientHeight);
                if (!isResizing) {
                    setIsResizing(true);
                }
            }}
            onResizeStop={(_event, _direction, elt: HTMLElement) => {
                onResizeStop(elt.clientHeight);
                setIsResizing(false);
            }}
            showHandle={showHandle}
            size={size as { height: number | string; width: number | string }}
        />
    );
}
