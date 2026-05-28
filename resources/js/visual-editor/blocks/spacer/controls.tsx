/**
 * Spacer — inspector controls.
 *
 * Ported from `@wordpress/block-library/src/spacer/controls.js` (v9.43.0).
 * Uses `@wordpress/*` internals via `as any` casts where the public types
 * don't expose the experimental APIs we need.
 */

import type { ReactElement } from 'react';
import { __ } from '@wordpress/i18n';
import {
    InspectorControls,
    useSettings,
} from '@wordpress/block-editor';
import {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    __experimentalUseCustomUnits as useCustomUnits,
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    __experimentalUnitControl as UnitControl,
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    __experimentalParseQuantityAndUnitFromRawValue as parseQuantityAndUnitFromRawValue,
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    __experimentalToolsPanel as ToolsPanel,
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    __experimentalToolsPanelItem as ToolsPanelItem,
} from '@wordpress/components';
import { useInstanceId } from '@wordpress/compose';

import { MIN_SPACER_SIZE } from './constants';

// Upstream pulls these from the private/unlocked surface; the fork stays
// out of the private-API channel and uses plain UnitControl-based inputs.
const useSpacingSizes = (): ReadonlyArray<unknown> => [];
const SpacingSizesControl = (): null => null;
function isValueSpacingPreset(value?: string): boolean {
    return typeof value === 'string' && /^var:preset\|spacing\|/.test(value);
}

interface DimensionInputProps {
    label: string;
    onChange: (value: string) => void;
    isResizing: boolean;
    value?: string;
}

function DimensionInput({
    label,
    onChange,
    isResizing,
    value = '',
}: DimensionInputProps): ReactElement {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const inputId = useInstanceId(
        UnitControl as unknown as React.ComponentType,
        'block-spacer-height-input'
    );
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const spacingSizes: unknown[] = (useSpacingSizes as any)() ?? [];
    const [spacingUnits] = useSettings('spacing.units') as [
        string[] | undefined,
    ];

    const availableUnits = spacingUnits
        ? spacingUnits.filter((unit) => unit !== '%')
        : ['px', 'em', 'rem', 'vw', 'vh'];

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const units = (useCustomUnits as any)({
        availableUnits,
        defaultValues: { px: 100, em: 10, rem: 10, vw: 10, vh: 25 },
    });

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const [parsedQuantity, parsedUnit] = (
        parseQuantityAndUnitFromRawValue as any
    )(value);
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const computedValue = (isValueSpacingPreset as any)(value)
        ? value
        : [parsedQuantity, isResizing ? 'px' : parsedUnit].join('');

    return (
        <>
            {spacingSizes?.length < 2 ? (
                // eslint-disable-next-line @typescript-eslint/no-explicit-any
                <UnitControl
                    id={inputId}
                    isResetValueOnUnitChange
                    min={MIN_SPACER_SIZE}
                    onChange={onChange}
                    value={computedValue}
                    units={units}
                    label={label}
                    // @ts-expect-error - upstream prop
                    __next40pxDefaultSize
                />
            ) : (
                // eslint-disable-next-line @typescript-eslint/no-explicit-any
                <div className="tools-panel-item-spacing">
                    {/* eslint-disable-next-line @typescript-eslint/no-explicit-any */}
                    <SpacingSizesControl
                        values={{ all: computedValue }}
                        onChange={({ all }: { all: string }) => {
                            onChange(all);
                        }}
                        label={label}
                        sides={['all']}
                        units={units}
                        allowReset={false}
                        splitOnAxis={false}
                        showSideInLabel={false}
                    />
                </div>
            )}
        </>
    );
}

interface SpacerControlsProps {
    setAttributes: (attrs: Record<string, unknown>) => void;
    orientation?: string;
    height?: string;
    width?: string;
    isResizing: boolean;
}

export default function SpacerControls({
    setAttributes,
    orientation,
    height,
    width,
    isResizing,
}: SpacerControlsProps): ReactElement {
    return (
        <InspectorControls>
            {/* eslint-disable-next-line @typescript-eslint/no-explicit-any */}
            <ToolsPanel
                label={__('Settings')}
                resetAll={() => {
                    setAttributes({
                        width: undefined,
                        height: '100px',
                    });
                }}
            >
                {orientation === 'horizontal' && (
                    // eslint-disable-next-line @typescript-eslint/no-explicit-any
                    <ToolsPanelItem
                        label={__('Width')}
                        isShownByDefault
                        hasValue={() => width !== undefined}
                        onDeselect={() =>
                            setAttributes({ width: undefined })
                        }
                    >
                        <DimensionInput
                            label={__('Width')}
                            value={width}
                            onChange={(nextWidth: string) =>
                                setAttributes({ width: nextWidth })
                            }
                            isResizing={isResizing}
                        />
                    </ToolsPanelItem>
                )}
                {orientation !== 'horizontal' && (
                    // eslint-disable-next-line @typescript-eslint/no-explicit-any
                    <ToolsPanelItem
                        label={__('Height')}
                        isShownByDefault
                        hasValue={() => height !== '100px'}
                        onDeselect={() =>
                            setAttributes({ height: '100px' })
                        }
                    >
                        <DimensionInput
                            label={__('Height')}
                            value={height}
                            onChange={(nextHeight: string) =>
                                setAttributes({ height: nextHeight })
                            }
                            isResizing={isResizing}
                        />
                    </ToolsPanelItem>
                )}
            </ToolsPanel>
        </InspectorControls>
    );
}
