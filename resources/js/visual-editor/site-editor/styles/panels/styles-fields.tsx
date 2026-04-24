/**
 * Shared styles-panel field renderers.
 *
 * Blocks-detail and Elements-detail both render the same core set of
 * property controls — color (palette + custom picker), font family /
 * size (preset dropdown + custom escape-hatch), size (number + unit).
 * Centralizing them here keeps the two panels focused on their
 * scope-specific wiring and guarantees the WP-component primitives they
 * render stay identical.
 */

import {
    ColorPalette,
    SelectControl,
    TextControl,
    // `UnitControl` is still marked experimental upstream — its export
    // name, props, and emitted value shape may shift between Gutenberg
    // releases. Keep the alias centralized here so a rename is a single
    // diff.
    __experimentalUnitControl as UnitControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { type ReactElement } from 'react';

import { TEXT_DOMAIN } from '../../../vendor/i18n';
import type { ValidationErrors } from '../../api-client';
import type { UseGlobalStylesEditorResult } from '../use-global-styles-editor';
import { StyleControlRow } from './panel-controls';
import type { StylePresets } from './use-preset-data';

export type StyleFieldKind =
    | 'color'
    | 'font-family'
    | 'font-size'
    | 'font-weight'
    | 'size';

export interface StyleFieldDescriptor {
    label: string;
    testId: string;
    kind: StyleFieldKind;
}

const CUSTOM_SENTINEL = '__custom__';
const PRESET_VAR_PATTERN = /^var\(--wp--preset--color--([a-z0-9-]+)\)$/i;

const FONT_WEIGHT_PRESETS = [
    { value: '300', label: 'Light · 300' },
    { value: '400', label: 'Regular · 400' },
    { value: '500', label: 'Medium · 500' },
    { value: '600', label: 'Semibold · 600' },
    { value: '700', label: 'Bold · 700' },
];

const SIZE_UNITS = [
    { value: 'px', label: 'px', default: 0 },
    { value: 'rem', label: 'rem', default: 0 },
    { value: 'em', label: 'em', default: 0 },
    { value: '%', label: '%', default: 0 },
];

function stringOrEmpty(value: unknown): string {
    return typeof value === 'string' ? value : '';
}

function resolveColorForPalette(
    value: string,
    palette: ReadonlyArray<{ slug: string; color: string }>
): string | undefined {
    const match = PRESET_VAR_PATTERN.exec(value);

    if (match !== null) {
        const slug = match[1] ?? '';
        const entry = palette.find((swatch) => swatch.slug === slug);

        if (entry !== undefined) {
            return entry.color;
        }
    }

    return value !== '' ? value : undefined;
}

function paletteRefFromColor(
    color: string,
    palette: ReadonlyArray<{ slug: string; color: string }>
): string {
    const match = palette.find(
        (entry) => entry.color.toLowerCase() === color.toLowerCase()
    );

    if (match !== undefined) {
        return `var(--wp--preset--color--${match.slug})`;
    }

    return color;
}

export interface RenderFieldOptions {
    editor: UseGlobalStylesEditorResult;
    validationErrors: ValidationErrors | null;
    presets: StylePresets;
    descriptor: StyleFieldDescriptor;
    path: readonly string[];
}

export function renderStyleField(
    options: RenderFieldOptions
): ReactElement {
    const { editor, validationErrors, presets, descriptor, path } = options;
    const value = stringOrEmpty(editor.readValue(path));
    const baseValue = stringOrEmpty(editor.readBaseValue(path));
    const error = validationErrors?.[path.join('.')]?.[0] ?? null;
    const isCustomized = editor.isPathCustomized(path);

    const rowProps = {
        testId: descriptor.testId,
        isCustomized,
        onReset: (): void => editor.resetPath(path),
        baseValue: baseValue === '' ? undefined : baseValue,
        error,
    };

    if (descriptor.kind === 'color') {
        const paletteOptions = presets.palette.map((entry) => ({
            slug: entry.slug,
            name: entry.name,
            color: entry.color,
        }));
        const resolved = resolveColorForPalette(value, paletteOptions);

        return (
            <StyleControlRow key={descriptor.testId} {...rowProps}>
                <div
                    className="ap-site-editor__style-color-field"
                    data-testid={`ap-site-editor-style-swatches-${descriptor.testId}`}
                >
                    <span className="ap-site-editor__style-color-field-label">
                        {__(descriptor.label, TEXT_DOMAIN)}
                    </span>
                    <ColorPalette
                        colors={paletteOptions}
                        value={resolved}
                        clearable={true}
                        enableAlpha={false}
                        onChange={(next?: string) => {
                            if (next === undefined || next === '') {
                                editor.resetPath(path);
                                return;
                            }

                            editor.setValue(
                                path,
                                paletteRefFromColor(next, paletteOptions)
                            );
                        }}
                    />
                </div>
            </StyleControlRow>
        );
    }

    if (descriptor.kind === 'font-family' || descriptor.kind === 'font-size') {
        const presetList =
            descriptor.kind === 'font-family'
                ? presets.fontFamilies
                : presets.fontSizes;
        const options = [
            ...presetList.map((preset) => ({
                value: preset.value,
                label: preset.label,
            })),
            { value: CUSTOM_SENTINEL, label: __('Custom…', TEXT_DOMAIN) },
        ];
        const matches = presetList.some((preset) => preset.value === value);
        const selectValue = matches ? value : CUSTOM_SENTINEL;
        const isFamily = descriptor.kind === 'font-family';
        const selectTestId = `ap-site-editor-style-field-select-${descriptor.testId}`;

        return (
            <StyleControlRow key={descriptor.testId} {...rowProps}>
                <SelectControl
                    label={__(descriptor.label, TEXT_DOMAIN)}
                    value={selectValue}
                    options={options}
                    data-testid={selectTestId}
                    __nextHasNoMarginBottom={true}
                    __next40pxDefaultSize={true}
                    onChange={(next) => {
                        if (next === CUSTOM_SENTINEL) {
                            editor.setValue(path, value);
                            return;
                        }

                        editor.setValue(path, next);
                    }}
                />
                {selectValue === CUSTOM_SENTINEL ? (
                    isFamily ? (
                        <TextControl
                            label={__('Custom value', TEXT_DOMAIN)}
                            hideLabelFromVision={true}
                            value={value}
                            placeholder="system-ui, sans-serif"
                            data-testid={`ap-site-editor-style-field-custom-${descriptor.testId}`}
                            __nextHasNoMarginBottom={true}
                            __next40pxDefaultSize={true}
                            onChange={(next) => editor.setValue(path, next)}
                        />
                    ) : (
                        <UnitControl
                            label={__('Custom value', TEXT_DOMAIN)}
                            hideLabelFromVision={true}
                            value={value}
                            units={SIZE_UNITS}
                            data-testid={`ap-site-editor-style-field-custom-${descriptor.testId}`}
                            __nextHasNoMarginBottom={true}
                            onChange={(next) =>
                                editor.setValue(path, next ?? '')
                            }
                        />
                    )
                ) : null}
            </StyleControlRow>
        );
    }

    if (descriptor.kind === 'font-weight') {
        const options = [
            ...FONT_WEIGHT_PRESETS,
            { value: CUSTOM_SENTINEL, label: __('Custom…', TEXT_DOMAIN) },
        ];
        const matches = FONT_WEIGHT_PRESETS.some(
            (preset) => preset.value === value
        );
        const selectValue = matches ? value : CUSTOM_SENTINEL;

        return (
            <StyleControlRow key={descriptor.testId} {...rowProps}>
                <SelectControl
                    label={__(descriptor.label, TEXT_DOMAIN)}
                    value={selectValue}
                    options={options}
                    data-testid={`ap-site-editor-style-field-select-${descriptor.testId}`}
                    __nextHasNoMarginBottom={true}
                    __next40pxDefaultSize={true}
                    onChange={(next) => {
                        if (next === CUSTOM_SENTINEL) {
                            editor.setValue(path, value);
                            return;
                        }

                        editor.setValue(path, next);
                    }}
                />
                {selectValue === CUSTOM_SENTINEL ? (
                    <TextControl
                        label={__('Custom weight', TEXT_DOMAIN)}
                        hideLabelFromVision={true}
                        value={value}
                        placeholder="500"
                        data-testid={`ap-site-editor-style-field-custom-${descriptor.testId}`}
                        __nextHasNoMarginBottom={true}
                        __next40pxDefaultSize={true}
                        onChange={(next) => editor.setValue(path, next)}
                    />
                ) : null}
            </StyleControlRow>
        );
    }

    return (
        <StyleControlRow key={descriptor.testId} {...rowProps}>
            <UnitControl
                label={__(descriptor.label, TEXT_DOMAIN)}
                value={value}
                units={SIZE_UNITS}
                data-testid={`ap-site-editor-style-field-input-${descriptor.testId}`}
                __nextHasNoMarginBottom={true}
                onChange={(next) => editor.setValue(path, next ?? '')}
            />
        </StyleControlRow>
    );
}
