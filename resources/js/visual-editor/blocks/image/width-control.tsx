/**
 * Image — width control (fork-specific extension).
 *
 * Adds a dedicated inspector panel for the image block's width:
 *
 *   - **Preset buttons** (S / M / L / Full) mapped to registered percentages.
 *   - **Custom width** numeric input with a px / % unit toggle.
 *   - **Keep aspect ratio** checkbox (defaults to on) — when off, the user
 *     can override height independently in the block's dimension controls.
 *
 * The three surfaces all write the same block attributes so the front-end
 * render (via `save.tsx`) is a single code path: `width` holds the full CSS
 * length string ("300px", "50%"), and `widthUnit` remembers the last selected
 * unit so the toggle state survives an editor reload.
 *
 * This component is not part of the upstream `core/image` port — it's a
 * fork extension. See `upstream-state.json#extensions`.
 */

import type { ReactElement } from 'react';
import {
    BaseControl,
    Button,
    ButtonGroup,
    CheckboxControl,
    Flex,
    FlexItem,
    __experimentalHStack as HStack,
    __experimentalNumberControl as NumberControl,
    __experimentalToggleGroupControl as ToggleGroupControl,
    __experimentalToggleGroupControlOption as ToggleGroupControlOption,
    __experimentalVStack as VStack,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import {
    DEFAULT_WIDTH_UNIT,
    WIDTH_PRESETS,
    type WidthUnit,
} from './constants';

interface WidthControlAttributes {
    readonly width?: string;
    readonly widthUnit?: WidthUnit;
    readonly keepAspectRatio?: boolean;
    readonly height?: string;
    readonly aspectRatio?: string;
}

interface WidthControlProps {
    readonly attributes: WidthControlAttributes;
    readonly setAttributes: (next: Partial<WidthControlAttributes>) => void;
}

/**
 * Parses a stored width string into a numeric value + unit.
 *
 * Accepts `"300px"`, `"50%"`, or a bare `"300"` (interpreted as px). Returns
 * `{ value: undefined, unit: DEFAULT_WIDTH_UNIT }` when the input is empty or
 * unparseable — the caller renders that as an unfilled input.
 */
export function parseWidth(
    width: string | undefined,
    storedUnit: WidthUnit | undefined
): { value: number | undefined; unit: WidthUnit } {
    if (!width) {
        return { value: undefined, unit: storedUnit ?? DEFAULT_WIDTH_UNIT };
    }

    const match = width.trim().match(/^(\d*\.?\d+)\s*(px|%)?$/);
    if (!match) {
        return { value: undefined, unit: storedUnit ?? DEFAULT_WIDTH_UNIT };
    }

    const numeric = Number.parseFloat(match[1]);
    if (!Number.isFinite(numeric)) {
        return { value: undefined, unit: storedUnit ?? DEFAULT_WIDTH_UNIT };
    }

    const parsedUnit = (match[2] as WidthUnit | undefined) ?? storedUnit ?? DEFAULT_WIDTH_UNIT;
    return { value: numeric, unit: parsedUnit };
}

/**
 * Composes a numeric width and unit back into a CSS length string.
 */
export function composeWidth(value: number, unit: WidthUnit): string {
    return `${value}${unit}`;
}

/**
 * Returns the preset slug whose percentage matches the stored width, or
 * `undefined` for a custom value.
 */
export function matchPreset(
    width: string | undefined
): string | undefined {
    if (!width || !width.includes('%')) {
        return undefined;
    }
    const { value, unit } = parseWidth(width, undefined);
    if (unit !== '%' || value === undefined) {
        return undefined;
    }
    return WIDTH_PRESETS.find((p) => p.percentage === value)?.slug;
}

export default function WidthControl({
    attributes,
    setAttributes,
}: WidthControlProps): ReactElement {
    const { width, widthUnit, keepAspectRatio = true } = attributes;
    const { value: parsedValue, unit } = parseWidth(width, widthUnit);
    const activePreset = matchPreset(width);

    function applyPreset(percentage: number): void {
        setAttributes({
            width: `${percentage}%`,
            widthUnit: '%',
            // Presets are proportional — always clear an explicit height so
            // the browser derives it from `aspectRatio` (if set) or the natural
            // ratio.
            height: undefined,
        });
    }

    function clearWidth(): void {
        setAttributes({
            width: undefined,
            height: undefined,
        });
    }

    function onCustomWidthChange(next: string | number | undefined): void {
        if (next === undefined || next === '' || next === null) {
            clearWidth();
            return;
        }
        const numeric = typeof next === 'number' ? next : Number.parseFloat(next);
        if (!Number.isFinite(numeric) || numeric <= 0) {
            clearWidth();
            return;
        }
        setAttributes({
            width: composeWidth(numeric, unit),
            widthUnit: unit,
            // If keepAspectRatio is on, height is derived — clear any stale
            // explicit height so the front-end render is deterministic.
            ...(keepAspectRatio ? { height: undefined } : {}),
        });
    }

    function onUnitChange(nextUnit: string): void {
        const cast = nextUnit as WidthUnit;
        if (cast !== 'px' && cast !== '%') {
            return;
        }
        setAttributes({
            widthUnit: cast,
            // Rewrite width with the new unit if there's a numeric value.
            ...(parsedValue !== undefined
                ? { width: composeWidth(parsedValue, cast) }
                : {}),
        });
    }

    function onKeepAspectRatioChange(checked: boolean): void {
        setAttributes({
            keepAspectRatio: checked,
            // Turning it back on clears any manually set height so ratio is
            // re-derived on the front end.
            ...(checked ? { height: undefined } : {}),
        });
    }

    return (
        <VStack spacing={4} className="artisanpack-image-width-control">
            <div>
                <HStack
                    justify="space-between"
                    alignment="center"
                    className="artisanpack-image-width-control__label-row"
                >
                    <BaseControl.VisualLabel>
                        {__('Preset size')}
                    </BaseControl.VisualLabel>
                    <Button
                        size="small"
                        variant="link"
                        onClick={clearWidth}
                        disabled={!width}
                    >
                        {__('Reset')}
                    </Button>
                </HStack>
                <ButtonGroup>
                    {WIDTH_PRESETS.map((preset) => (
                        <Button
                            key={preset.slug}
                            size="small"
                            variant={
                                activePreset === preset.slug
                                    ? 'primary'
                                    : 'secondary'
                            }
                            onClick={() => applyPreset(preset.percentage)}
                            aria-pressed={activePreset === preset.slug}
                            aria-label={`${preset.label} (${preset.percentage}%)`}
                        >
                            {preset.label}
                        </Button>
                    ))}
                </ButtonGroup>
            </div>

            <Flex gap={2} align="flex-end">
                <FlexItem isBlock>
                    <NumberControl
                        __next40pxDefaultSize
                        label={__('Custom width')}
                        min={0}
                        step={1}
                        value={parsedValue ?? ''}
                        onChange={onCustomWidthChange}
                    />
                </FlexItem>
                <FlexItem style={{ minWidth: '84px' }}>
                    <ToggleGroupControl
                        __next40pxDefaultSize
                        __nextHasNoMarginBottom
                        hideLabelFromVision
                        label={__('Unit')}
                        value={unit}
                        isBlock
                        onChange={(value) => onUnitChange(String(value))}
                    >
                        <ToggleGroupControlOption value="px" label="px" />
                        <ToggleGroupControlOption value="%" label="%" />
                    </ToggleGroupControl>
                </FlexItem>
            </Flex>

            <CheckboxControl
                __nextHasNoMarginBottom
                label={__('Keep aspect ratio')}
                help={__(
                    'When on, height follows width proportionally.'
                )}
                checked={keepAspectRatio}
                onChange={onKeepAspectRatioChange}
            />
        </VStack>
    );
}
