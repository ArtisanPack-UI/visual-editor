/**
 * Typography panel.
 *
 * Exposes the schema-v3 typography surface: `styles.typography.fontFamily`,
 * `.fontSize`, `.lineHeight`, and `.letterSpacing`. Controls are the
 * same Gutenberg primitives (`SelectControl`, `TextControl`,
 * `__experimentalUnitControl`) the block inspector uses, wrapped in the
 * Styles-panel customization chrome.
 */

import {
    SelectControl,
    TextControl,
    // `UnitControl` is still marked experimental upstream — its export name,
    // props, and emitted value shape may shift between Gutenberg releases.
    // Keep the alias centralized so the inevitable rename is a single diff.
    __experimentalUnitControl as UnitControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useMemo } from 'react';

import { TEXT_DOMAIN } from '../../../vendor/i18n';
import type { ValidationErrors } from '../../api-client';
import type { UseGlobalStylesEditorResult } from '../use-global-styles-editor';
import { StyleControlRow, StylePanelSection } from './panel-controls';
import { useStylePresets } from './use-preset-data';

export interface TypographyPanelProps {
    editor: UseGlobalStylesEditorResult;
    validationErrors: ValidationErrors | null;
}

const FONT_FAMILY_PATH = ['styles', 'typography', 'fontFamily'] as const;
const FONT_SIZE_PATH = ['styles', 'typography', 'fontSize'] as const;
const LINE_HEIGHT_PATH = ['styles', 'typography', 'lineHeight'] as const;
const LETTER_SPACING_PATH = ['styles', 'typography', 'letterSpacing'] as const;

const WATCHED_PATHS = [
    FONT_FAMILY_PATH,
    FONT_SIZE_PATH,
    LINE_HEIGHT_PATH,
    LETTER_SPACING_PATH,
];

const CUSTOM_SENTINEL = '__custom__';

function stringOrEmpty(value: unknown): string {
    return typeof value === 'string' ? value : '';
}

function errorFor(
    errors: ValidationErrors | null,
    path: readonly string[]
): string | null {
    return errors?.[path.join('.')]?.[0] ?? null;
}

export function TypographyPanel(props: TypographyPanelProps): JSX.Element {
    const { editor, validationErrors } = props;
    const presets = useStylePresets(editor);

    const customizedCount = useMemo(
        () =>
            WATCHED_PATHS.filter((path) =>
                editor.isPathCustomized(path)
            ).length,
        [editor]
    );

    const fontFamilyValue = stringOrEmpty(editor.readValue(FONT_FAMILY_PATH));
    const fontFamilyOptions = useMemo(
        () => [
            ...presets.fontFamilies.map((preset) => ({
                value: preset.value,
                label: preset.label,
            })),
            { value: CUSTOM_SENTINEL, label: __('Custom…', TEXT_DOMAIN) },
        ],
        [presets.fontFamilies]
    );
    const fontFamilyMatches = presets.fontFamilies.some(
        (preset) => preset.value === fontFamilyValue
    );
    const fontFamilySelectValue = fontFamilyMatches
        ? fontFamilyValue
        : CUSTOM_SENTINEL;

    const fontSizeValue = stringOrEmpty(editor.readValue(FONT_SIZE_PATH));
    const fontSizeOptions = useMemo(
        () => [
            ...presets.fontSizes.map((preset) => ({
                value: preset.value,
                label: preset.label,
            })),
            { value: CUSTOM_SENTINEL, label: __('Custom…', TEXT_DOMAIN) },
        ],
        [presets.fontSizes]
    );
    const fontSizeMatches = presets.fontSizes.some(
        (preset) => preset.value === fontSizeValue
    );
    const fontSizeSelectValue = fontSizeMatches
        ? fontSizeValue
        : CUSTOM_SENTINEL;

    return (
        <StylePanelSection
            testId="ap-site-editor-style-panel-typography"
            title={__('Typography', TEXT_DOMAIN)}
            customizedCount={customizedCount}
            onResetSection={() =>
                WATCHED_PATHS.forEach((path) => editor.resetPath(path))
            }
            description={__(
                'Typography defaults apply site-wide. Override per element under "Elements" and per block under "Blocks".',
                TEXT_DOMAIN
            )}
        >
            <StyleControlRow
                testId="font-family"
                isCustomized={editor.isPathCustomized(FONT_FAMILY_PATH)}
                onReset={() => editor.resetPath(FONT_FAMILY_PATH)}
                baseValue={stringOrEmpty(
                    editor.readBaseValue(FONT_FAMILY_PATH)
                )}
                error={errorFor(validationErrors, FONT_FAMILY_PATH)}
            >
                <SelectControl
                    label={__('Font family', TEXT_DOMAIN)}
                    value={fontFamilySelectValue}
                    options={fontFamilyOptions}
                    data-testid="ap-site-editor-style-field-select-font-family"
                    __nextHasNoMarginBottom={true}
                    __next40pxDefaultSize={true}
                    onChange={(next) => {
                        if (next === CUSTOM_SENTINEL) {
                            // Clear the value so `fontFamilyMatches` flips
                            // to false and the Custom input renders —
                            // otherwise re-writing the current preset value
                            // would snap the select straight back.
                            editor.setValue(FONT_FAMILY_PATH, '');
                            return;
                        }

                        editor.setValue(FONT_FAMILY_PATH, next);
                    }}
                />
                {fontFamilySelectValue === CUSTOM_SENTINEL ? (
                    <TextControl
                        label={__('Custom font family', TEXT_DOMAIN)}
                        hideLabelFromVision={true}
                        value={fontFamilyValue}
                        placeholder="system-ui, sans-serif"
                        data-testid="ap-site-editor-style-field-custom-font-family"
                        __nextHasNoMarginBottom={true}
                        __next40pxDefaultSize={true}
                        onChange={(next) =>
                            editor.setValue(FONT_FAMILY_PATH, next)
                        }
                    />
                ) : null}
            </StyleControlRow>

            <StyleControlRow
                testId="font-size"
                isCustomized={editor.isPathCustomized(FONT_SIZE_PATH)}
                onReset={() => editor.resetPath(FONT_SIZE_PATH)}
                baseValue={stringOrEmpty(
                    editor.readBaseValue(FONT_SIZE_PATH)
                )}
                error={errorFor(validationErrors, FONT_SIZE_PATH)}
            >
                <SelectControl
                    label={__('Font size', TEXT_DOMAIN)}
                    value={fontSizeSelectValue}
                    options={fontSizeOptions}
                    data-testid="ap-site-editor-style-field-select-font-size"
                    __nextHasNoMarginBottom={true}
                    __next40pxDefaultSize={true}
                    onChange={(next) => {
                        if (next === CUSTOM_SENTINEL) {
                            editor.setValue(FONT_SIZE_PATH, '');
                            return;
                        }

                        editor.setValue(FONT_SIZE_PATH, next);
                    }}
                />
                {fontSizeSelectValue === CUSTOM_SENTINEL ? (
                    <UnitControl
                        label={__('Custom font size', TEXT_DOMAIN)}
                        hideLabelFromVision={true}
                        value={fontSizeValue}
                        units={[
                            { value: 'px', label: 'px', default: 16 },
                            { value: 'rem', label: 'rem', default: 1 },
                            { value: 'em', label: 'em', default: 1 },
                            { value: '%', label: '%', default: 100 },
                        ]}
                        data-testid="ap-site-editor-style-field-custom-font-size"
                        __nextHasNoMarginBottom={true}
                        onChange={(next) =>
                            editor.setValue(FONT_SIZE_PATH, next ?? '')
                        }
                    />
                ) : null}
            </StyleControlRow>

            <StyleControlRow
                testId="line-height"
                isCustomized={editor.isPathCustomized(LINE_HEIGHT_PATH)}
                onReset={() => editor.resetPath(LINE_HEIGHT_PATH)}
                baseValue={stringOrEmpty(
                    editor.readBaseValue(LINE_HEIGHT_PATH)
                )}
                error={errorFor(validationErrors, LINE_HEIGHT_PATH)}
            >
                <TextControl
                    label={__('Line height', TEXT_DOMAIN)}
                    type="number"
                    value={stringOrEmpty(
                        editor.readValue(LINE_HEIGHT_PATH)
                    )}
                    step="0.1"
                    min="0"
                    data-testid="ap-site-editor-style-field-input-line-height"
                    __nextHasNoMarginBottom={true}
                    __next40pxDefaultSize={true}
                    onChange={(next) =>
                        editor.setValue(LINE_HEIGHT_PATH, next)
                    }
                />
            </StyleControlRow>

            <StyleControlRow
                testId="letter-spacing"
                isCustomized={editor.isPathCustomized(LETTER_SPACING_PATH)}
                onReset={() => editor.resetPath(LETTER_SPACING_PATH)}
                baseValue={stringOrEmpty(
                    editor.readBaseValue(LETTER_SPACING_PATH)
                )}
                error={errorFor(validationErrors, LETTER_SPACING_PATH)}
            >
                <UnitControl
                    label={__('Letter spacing', TEXT_DOMAIN)}
                    value={stringOrEmpty(
                        editor.readValue(LETTER_SPACING_PATH)
                    )}
                    units={[
                        { value: 'px', label: 'px', default: 0 },
                        { value: 'em', label: 'em', default: 0 },
                        { value: 'rem', label: 'rem', default: 0 },
                    ]}
                    data-testid="ap-site-editor-style-field-input-letter-spacing"
                    __nextHasNoMarginBottom={true}
                    onChange={(next) =>
                        editor.setValue(LETTER_SPACING_PATH, next ?? '')
                    }
                />
            </StyleControlRow>
        </StylePanelSection>
    );
}
