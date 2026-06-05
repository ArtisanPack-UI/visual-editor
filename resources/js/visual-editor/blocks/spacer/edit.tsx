/**
 * Spacer — edit component.
 *
 * Ported from `@wordpress/block-library/src/spacer/edit.js` (v9.43.0).
 * Adds an explicit `wp-block-spacer` class to `useBlockProps` so the editor
 * canvas matches the front-end without depending on the
 * `__experimentalSelector` internals. Upstream's `unlock` of
 * `blockEditorPrivateApis` is replaced with a defensive accessor since the
 * private-API key isn't exposed to fork consumers.
 */

import type { ReactElement } from 'react';
import clsx from 'clsx';
import {
    useBlockProps,
    store as blockEditorStore,
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    useBlockEditingMode,
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
} from '@wordpress/block-editor';
import { ResizableBox } from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';

import SpacerControls from './controls';
import { MIN_SPACER_SIZE } from './constants';

// Upstream pulls `getCustomValueFromPreset`, `getSpacingPresetCssVar`, and
// `useSpacingSizes` from the private/unlocked surface of
// `@wordpress/block-editor`. The fork stays out of the private-API channel:
// theme spacing presets resolve through the standard CSS-var name when the
// stored attribute already has a `var:preset|spacing|*` shape, otherwise the
// raw value is passed through.
function getSpacingPresetCssVar(value?: string): string | undefined {
    if (!value) {
        return undefined;
    }
    const match = value.match(/^var:preset\|spacing\|(.+)$/);
    if (match) {
        return `var(--wp--preset--spacing--${match[1]})`;
    }
    return value;
}
function getCustomValueFromPreset(
    value?: string,
    _presets?: ReadonlyArray<unknown>
): string | undefined {
    return value;
}
const useSpacingSizes = (): ReadonlyArray<unknown> => [];

interface ResizableSpacerProps {
    orientation: string;
    onResizeStart: (val: string) => void;
    onResize: (val: string) => void;
    onResizeStop: (val: string) => void;
    isSelected: boolean;
    isResizing: boolean;
    setIsResizing: (v: boolean) => void;
    [key: string]: unknown;
}

function ResizableSpacer({
    orientation,
    onResizeStart,
    onResize,
    onResizeStop,
    isSelected,
    isResizing,
    setIsResizing,
    ...props
}: ResizableSpacerProps): ReactElement {
    const getCurrentSize = (elt: HTMLElement): number => {
        return orientation === 'horizontal'
            ? elt.clientWidth
            : elt.clientHeight;
    };

    const getNextVal = (elt: HTMLElement): string => {
        return `${getCurrentSize(elt)}px`;
    };

    return (
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        <ResizableBox
            className={clsx('block-library-spacer__resize-container', {
                'resize-horizontal': orientation === 'horizontal',
                'is-resizing': isResizing,
                'is-selected': isSelected,
            })}
            onResizeStart={(
                _event: unknown,
                _direction: unknown,
                elt: HTMLElement
            ) => {
                const nextVal = getNextVal(elt);
                onResizeStart(nextVal);
                onResize(nextVal);
            }}
            onResize={(
                _event: unknown,
                _direction: unknown,
                elt: HTMLElement
            ) => {
                onResize(getNextVal(elt));
                if (!isResizing) {
                    setIsResizing(true);
                }
            }}
            onResizeStop={(
                _event: unknown,
                _direction: unknown,
                elt: HTMLElement
            ) => {
                const nextVal = getCurrentSize(elt);
                onResizeStop(`${nextVal}px`);
                setIsResizing(false);
            }}
            // @ts-expect-error - upstream experimental prop
            __experimentalShowTooltip
            __experimentalTooltipProps={{
                axis: orientation === 'horizontal' ? 'x' : 'y',
                position: 'corner',
                isVisible: isResizing,
            }}
            showHandle={isSelected}
            {...props}
        />
    );
}

interface SpacerLayout {
    selfStretch?: string;
    flexSize?: string;
}

interface SpacerStyle {
    layout?: SpacerLayout;
    [key: string]: unknown;
}

interface SpacerAttributes {
    readonly height?: string;
    readonly width?: string;
    readonly style?: SpacerStyle;
}

interface SpacerContext {
    orientation?: string;
}

interface ParentLayout {
    orientation?: string;
    type?: string;
    default?: { type?: string };
}

interface SpacerEditProps {
    attributes: SpacerAttributes;
    isSelected: boolean;
    setAttributes: (attrs: Partial<SpacerAttributes>) => void;
    toggleSelection: (v: boolean) => void;
    context: SpacerContext;
    __unstableParentLayout?: ParentLayout;
    className?: string;
}

export default function SpacerEdit({
    attributes,
    isSelected,
    setAttributes,
    toggleSelection,
    context,
    __unstableParentLayout: parentLayout,
    className,
}: SpacerEditProps): ReactElement {
    const disableCustomSpacingSizes = useSelect((select) => {
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        const editorSettings = (select as any)(blockEditorStore).getSettings();
        return editorSettings?.disableCustomSpacingSizes;
    }, []);
    const { orientation } = context;
    const {
        orientation: parentOrientation,
        type,
        default: { type: defaultType } = {},
    } = parentLayout || {};
    const isFlexLayout =
        type === 'flex' || (!type && defaultType === 'flex');
    const inheritedOrientation =
        !parentOrientation && isFlexLayout
            ? 'horizontal'
            : parentOrientation || orientation;
    const { height, width, style: blockStyle = {} } = attributes;

    const { layout = {} } = blockStyle as SpacerStyle;
    const { selfStretch, flexSize } = layout;

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const spacingSizes = (useSpacingSizes as any)();

    const [isResizing, setIsResizing] = useState(false);
    const [temporaryHeight, setTemporaryHeight] = useState<string | null>(null);
    const [temporaryWidth, setTemporaryWidth] = useState<string | null>(null);

    const onResizeStart = (): void => toggleSelection(false);
    const onResizeStop = (): void => toggleSelection(true);

    const { __unstableMarkNextChangeAsNotPersistent } = useDispatch(
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        blockEditorStore
    ) as unknown as { __unstableMarkNextChangeAsNotPersistent: () => void };

    const handleOnVerticalResizeStop = (newHeight: string): void => {
        onResizeStop();

        if (isFlexLayout) {
            setAttributes({
                style: {
                    ...blockStyle,
                    layout: {
                        ...layout,
                        flexSize: newHeight,
                        selfStretch: 'fixed',
                    },
                },
            });
        }

        setAttributes({ height: newHeight });
        setTemporaryHeight(null);
    };

    const handleOnHorizontalResizeStop = (newWidth: string): void => {
        onResizeStop();

        if (isFlexLayout) {
            setAttributes({
                style: {
                    ...blockStyle,
                    layout: {
                        ...layout,
                        flexSize: newWidth,
                        selfStretch: 'fixed',
                    },
                },
            });
        }

        setAttributes({ width: newWidth });
        setTemporaryWidth(null);
    };

    const getHeightForVerticalBlocks = (): string | undefined => {
        if (isFlexLayout) {
            return undefined;
        }
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        return (
            temporaryHeight ||
            (getSpacingPresetCssVar as any)(height) ||
            undefined
        );
    };

    const getWidthForHorizontalBlocks = (): string | undefined => {
        if (isFlexLayout) {
            return undefined;
        }
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        return (
            temporaryWidth ||
            (getSpacingPresetCssVar as any)(width) ||
            undefined
        );
    };

    const sizeConditionalOnOrientation =
        inheritedOrientation === 'horizontal'
            ? temporaryWidth || flexSize
            : temporaryHeight || flexSize;

    const style: Record<string, unknown> = {
        height:
            inheritedOrientation === 'horizontal'
                ? 24
                : getHeightForVerticalBlocks(),
        width:
            inheritedOrientation === 'horizontal'
                ? getWidthForHorizontalBlocks()
                : undefined,
        minWidth:
            inheritedOrientation === 'vertical' && isFlexLayout
                ? 48
                : undefined,
        flexBasis: isFlexLayout ? sizeConditionalOnOrientation : undefined,
        flexGrow: isFlexLayout && isResizing ? 0 : undefined,
    };

    const resizableBoxWithOrientation = (
        blockOrientation: string | undefined
    ): ReactElement => {
        if (blockOrientation === 'horizontal') {
            return (
                <ResizableSpacer
                    minWidth={MIN_SPACER_SIZE}
                    enable={{
                        top: false,
                        right: true,
                        bottom: false,
                        left: false,
                        topRight: false,
                        bottomRight: false,
                        bottomLeft: false,
                        topLeft: false,
                    }}
                    orientation={blockOrientation}
                    onResizeStart={onResizeStart}
                    onResize={(v: string) => setTemporaryWidth(v)}
                    onResizeStop={handleOnHorizontalResizeStop}
                    isSelected={isSelected}
                    isResizing={isResizing}
                    setIsResizing={setIsResizing}
                />
            );
        }

        return (
            <>
                <ResizableSpacer
                    minHeight={MIN_SPACER_SIZE}
                    enable={{
                        top: false,
                        right: false,
                        bottom: true,
                        left: false,
                        topRight: false,
                        bottomRight: false,
                        bottomLeft: false,
                        topLeft: false,
                    }}
                    orientation={blockOrientation ?? 'vertical'}
                    onResizeStart={onResizeStart}
                    onResize={(v: string) => setTemporaryHeight(v)}
                    onResizeStop={handleOnVerticalResizeStop}
                    isSelected={isSelected}
                    isResizing={isResizing}
                    setIsResizing={setIsResizing}
                />
            </>
        );
    };

    useEffect(() => {
        const setAttributesCovertly = (
            nextAttributes: Partial<SpacerAttributes>
        ): void => {
            __unstableMarkNextChangeAsNotPersistent();
            setAttributes(nextAttributes);
        };

        if (
            isFlexLayout &&
            selfStretch !== 'fill' &&
            selfStretch !== 'fit' &&
            flexSize === undefined
        ) {
            if (inheritedOrientation === 'horizontal') {
                // eslint-disable-next-line @typescript-eslint/no-explicit-any
                const newSize =
                    (getCustomValueFromPreset as any)(width, spacingSizes) ||
                    (getCustomValueFromPreset as any)(height, spacingSizes) ||
                    '100px';
                setAttributesCovertly({
                    width: '0px',
                    style: {
                        ...blockStyle,
                        layout: {
                            ...layout,
                            flexSize: newSize,
                            selfStretch: 'fixed',
                        },
                    },
                });
            } else {
                // eslint-disable-next-line @typescript-eslint/no-explicit-any
                const newSize =
                    (getCustomValueFromPreset as any)(height, spacingSizes) ||
                    (getCustomValueFromPreset as any)(width, spacingSizes) ||
                    '100px';
                setAttributesCovertly({
                    height: '0px',
                    style: {
                        ...blockStyle,
                        layout: {
                            ...layout,
                            flexSize: newSize,
                            selfStretch: 'fixed',
                        },
                    },
                });
            }
        } else if (
            isFlexLayout &&
            (selfStretch === 'fill' || selfStretch === 'fit')
        ) {
            setAttributesCovertly(
                inheritedOrientation === 'horizontal'
                    ? { width: undefined }
                    : { height: undefined }
            );
        } else if (!isFlexLayout && (selfStretch || flexSize)) {
            setAttributesCovertly({
                ...(inheritedOrientation === 'horizontal'
                    ? { width: flexSize }
                    : { height: flexSize }),
                style: {
                    ...blockStyle,
                    layout: {
                        ...layout,
                        flexSize: undefined,
                        selfStretch: undefined,
                    },
                },
            });
        }
    }, [
        blockStyle,
        flexSize,
        height,
        inheritedOrientation,
        isFlexLayout,
        layout,
        selfStretch,
        setAttributes,
        spacingSizes,
        width,
        __unstableMarkNextChangeAsNotPersistent,
    ]);

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const blockEditingMode = (useBlockEditingMode as any)();

    return (
        <>
            {/* eslint-disable-next-line @typescript-eslint/no-explicit-any */}
            <div
                {...(useBlockProps({
                    style,
                    className: clsx('wp-block-spacer', className, {
                        'custom-sizes-disabled': disableCustomSpacingSizes,
                    }),
                    // eslint-disable-next-line @typescript-eslint/no-explicit-any
                }) as any)}
            >
                {blockEditingMode === 'default' &&
                    resizableBoxWithOrientation(inheritedOrientation)}
            </div>
            {!isFlexLayout && (
                <SpacerControls
                    setAttributes={
                        setAttributes as (
                            attrs: Record<string, unknown>
                        ) => void
                    }
                    height={temporaryHeight || height}
                    width={temporaryWidth || width}
                    orientation={inheritedOrientation}
                    isResizing={isResizing}
                />
            )}
        </>
    );
}
