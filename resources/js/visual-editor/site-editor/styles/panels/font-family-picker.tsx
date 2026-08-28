/**
 * Font-family picker (#636).
 *
 * A single font-family control shared by the site-editor Typography panel and
 * the per-block / per-element style panels. It merges the theme's registered
 * font-family presets with the fonts installed through the Font Library — the
 * latter sourced from the {@link useInstalledFontOptions} store slice — and
 * falls back to a free-text "Custom…" escape hatch for anything not in either
 * list.
 *
 * Every option's `value` is a `var(--wp--preset--font-family--{slug})` custom
 * property, so a selection flows through the existing block style pipeline and
 * resolves against the generated `fonts.css` on both the canvas and the public
 * site with no block schema change.
 *
 * The control mirrors the previous inline behavior in `styles-fields.tsx`:
 * selecting "Custom…" clears the stored value so the select stays on the
 * custom row and reveals the text input, and an out-of-list stored value
 * (e.g. a raw `system-ui, sans-serif`) selects "Custom…" automatically.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.7.0
 */

import { Button, SelectControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useMemo } from 'react';

import { TEXT_DOMAIN } from '../../../vendor/i18n';
import { openFontLibrary } from '../../../fonts/font-library-ui-store';
import {
    type FontFamilyOption,
    useInstalledFontOptions,
} from '../../../fonts/installed-fonts-store';

const CUSTOM_SENTINEL = '__custom__';

export interface FontFamilyPickerProps {
    /** The control label. */
    readonly label: string;
    /** The current `styles.…​.typography.fontFamily` value. */
    readonly value: string;
    /** The theme's registered font-family preset options. */
    readonly presetOptions: readonly FontFamilyOption[];
    /** Persist a new value, or an empty string to clear the field. */
    readonly onChange: (next: string) => void;
    /**
     * Base test id. The select renders as
     * `ap-site-editor-style-field-select-{testId}` and the custom input as
     * `ap-site-editor-style-field-custom-{testId}`, matching the ids the
     * panels used before the picker was extracted.
     */
    readonly testId: string;
    /** Placeholder shown in the custom free-text input. */
    readonly customPlaceholder?: string;
}

/**
 * Merge preset and installed options, de-duplicated by `value` with the theme
 * presets kept ahead of installed fonts.
 *
 * @since 1.7.0
 */
function mergeOptions(
    presetOptions: readonly FontFamilyOption[],
    installedOptions: readonly FontFamilyOption[]
): readonly FontFamilyOption[] {
    const seen = new Set(presetOptions.map((option) => option.value));
    const merged = [...presetOptions];

    for (const option of installedOptions) {
        if (seen.has(option.value)) {
            continue;
        }

        seen.add(option.value);
        merged.push(option);
    }

    return merged;
}

export function FontFamilyPicker(props: FontFamilyPickerProps): JSX.Element {
    const { label, value, presetOptions, onChange, testId, customPlaceholder } =
        props;

    const installedOptions = useInstalledFontOptions();

    const options = useMemo(
        () => mergeOptions(presetOptions, installedOptions),
        [presetOptions, installedOptions]
    );

    const selectOptions = useMemo(
        () => [
            ...options.map((option) => ({
                value: option.value,
                label: option.label,
            })),
            { value: CUSTOM_SENTINEL, label: __('Custom…', TEXT_DOMAIN) },
        ],
        [options]
    );

    const matches = options.some((option) => option.value === value);
    const selectValue = matches ? value : CUSTOM_SENTINEL;

    return (
        <>
            <SelectControl
                label={label}
                value={selectValue}
                options={selectOptions}
                data-testid={`ap-site-editor-style-field-select-${testId}`}
                __nextHasNoMarginBottom={true}
                __next40pxDefaultSize={true}
                onChange={(next) => {
                    if (next === CUSTOM_SENTINEL) {
                        // Clear the value so `matches` stays false and the
                        // custom input renders — re-writing the preset value
                        // would snap the select back to the preset row.
                        onChange('');
                        return;
                    }

                    onChange(next);
                }}
            />
            {selectValue === CUSTOM_SENTINEL ? (
                <TextControl
                    label={__('Custom font family', TEXT_DOMAIN)}
                    hideLabelFromVision={true}
                    value={value}
                    placeholder={customPlaceholder ?? 'system-ui, sans-serif'}
                    data-testid={`ap-site-editor-style-field-custom-${testId}`}
                    __nextHasNoMarginBottom={true}
                    __next40pxDefaultSize={true}
                    onChange={(next) => onChange(next)}
                />
            ) : null}
            {/*
              #739: entry point to the Font Library modal. The picker is the
              natural home — it's where a missing family is noticed — and the
              single modal instance mounted at the site-editor root opens off
              the shared `font-library-ui-store`, so every picker (global
              styles + per-block / per-element) shares one control without
              threading a callback through each panel. Browsing is ungated, so
              the button is shown even to read-only users; the modal itself
              disables its mutating controls.
            */}
            <Button
                variant="link"
                data-testid={`ap-site-editor-font-library-open-${testId}`}
                onClick={openFontLibrary}
            >
                {__('Manage fonts…', TEXT_DOMAIN)}
            </Button>
        </>
    );
}
