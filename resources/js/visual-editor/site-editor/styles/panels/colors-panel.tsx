/**
 * Colors panel.
 *
 * The palette editor uses Gutenberg's `TextControl` for slug/name fields
 * and its native `ColorPicker` for the value; site-level color defaults
 * (background, text, link) use `ColorPalette` so the user picks from
 * the theme's registered colors the same way they do inside any block's
 * color settings.
 */

import {
    Button,
    ColorIndicator,
    ColorPalette,
    ColorPicker,
    Dropdown,
    PanelRow,
    TextControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useMemo } from 'react';

import { TEXT_DOMAIN } from '../../../vendor/i18n';
import type { ValidationErrors } from '../../api-client';
import type { UseGlobalStylesEditorResult } from '../use-global-styles-editor';
import {
    StyleControlRow,
    StylePanelSection,
} from './panel-controls';
import { useStylePresets } from './use-preset-data';

export interface ColorsPanelProps {
    editor: UseGlobalStylesEditorResult;
    validationErrors: ValidationErrors | null;
}

interface PaletteEntry {
    slug: string;
    name: string;
    color: string;
}

const PALETTE_PATH: readonly string[] = ['settings', 'color', 'palette'];
const PRESET_VAR_PATTERN = /^var\(--wp--preset--color--([a-z0-9-]+)\)$/i;

function stringOrEmpty(value: unknown): string {
    return typeof value === 'string' ? value : '';
}

function readPalette(editor: UseGlobalStylesEditorResult): PaletteEntry[] {
    const raw = editor.readValue(PALETTE_PATH);

    if (!Array.isArray(raw)) {
        return [];
    }

    return raw
        .map((entry): PaletteEntry | null => {
            if (entry === null || typeof entry !== 'object') {
                return null;
            }

            const row = entry as Record<string, unknown>;

            return {
                slug: typeof row.slug === 'string' ? row.slug : '',
                name: typeof row.name === 'string' ? row.name : '',
                color: typeof row.color === 'string' ? row.color : '#000000',
            };
        })
        .filter((entry): entry is PaletteEntry => entry !== null);
}

function slugify(value: string, existing: readonly string[]): string {
    const base =
        value
            .toLowerCase()
            .normalize('NFKD')
            .replace(/[^a-z0-9-]+/g, '-')
            .replace(/-+/g, '-')
            .replace(/^-|-$/g, '') || 'color';

    if (!existing.includes(base)) {
        return base;
    }

    let counter = 2;

    while (existing.includes(`${base}-${counter}`)) {
        counter += 1;
    }

    return `${base}-${counter}`;
}

function detectDuplicateSlug(
    palette: readonly PaletteEntry[]
): string | null {
    const seen: string[] = [];

    for (const entry of palette) {
        if (entry.slug === '') {
            continue;
        }

        if (seen.includes(entry.slug)) {
            return entry.slug;
        }

        seen.push(entry.slug);
    }

    return null;
}

function resolveValueForPalette(
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

const STYLE_FIELDS: ReadonlyArray<{
    label: string;
    path: readonly string[];
    testId: string;
}> = [
    {
        label: 'Background color',
        path: ['styles', 'color', 'background'],
        testId: 'color-background',
    },
    {
        label: 'Text color',
        path: ['styles', 'color', 'text'],
        testId: 'color-text',
    },
    {
        label: 'Default link color',
        path: ['styles', 'elements', 'link', 'color', 'text'],
        testId: 'link-color',
    },
];

export function ColorsPanel(props: ColorsPanelProps): JSX.Element {
    const { editor, validationErrors } = props;

    const palette = useMemo(() => readPalette(editor), [editor]);
    const duplicateSlug = useMemo(
        () => detectDuplicateSlug(palette),
        [palette]
    );
    const presets = useStylePresets(editor);

    const paletteCustomized = editor.isPathCustomized(PALETTE_PATH);

    const customizedStyleCount = useMemo(
        () =>
            STYLE_FIELDS.filter((field) =>
                editor.isPathCustomized(field.path)
            ).length,
        [editor]
    );
    const customizedCount =
        customizedStyleCount + (paletteCustomized ? 1 : 0);

    const persistPalette = (next: PaletteEntry[]): void => {
        // `setValue` takes `unknown` — declare the array shape explicitly
        // instead of double-casting through `unknown`.
        editor.setValue(PALETTE_PATH, next as readonly PaletteEntry[]);
    };

    const addSwatch = (): void => {
        const slug = slugify(
            'new-color',
            palette.map((entry) => entry.slug)
        );

        persistPalette([
            ...palette,
            { slug, name: __('New Color', TEXT_DOMAIN), color: '#000000' },
        ]);
    };

    const removeSwatch = (index: number): void => {
        if (index < 0 || index >= palette.length) {
            return;
        }

        persistPalette(palette.filter((_, idx) => idx !== index));
    };

    const moveSwatch = (index: number, delta: number): void => {
        const target = index + delta;

        if (target < 0 || target >= palette.length) {
            return;
        }

        const copy = palette.slice();
        const [removed] = copy.splice(index, 1);

        if (removed !== undefined) {
            copy.splice(target, 0, removed);
        }

        persistPalette(copy);
    };

    const updateSwatch = (
        index: number,
        key: keyof PaletteEntry,
        value: string
    ): void => {
        const copy = palette.slice();
        const existing = copy[index];

        if (existing === undefined) {
            return;
        }

        copy[index] = { ...existing, [key]: value };
        persistPalette(copy);
    };

    const paletteError =
        validationErrors?.['settings.color.palette']?.[0] ??
        (duplicateSlug !== null
            ? __('Palette slugs must be unique.', TEXT_DOMAIN)
            : null);

    const paletteOptions = useMemo(
        () =>
            presets.palette.map((entry) => ({
                name: entry.name,
                color: entry.color,
                slug: entry.slug,
            })),
        [presets.palette]
    );

    return (
        <StylePanelSection
            testId="ap-site-editor-style-panel-colors"
            title={__('Colors', TEXT_DOMAIN)}
            customizedCount={customizedCount}
            onResetSection={() => {
                editor.resetPath(PALETTE_PATH);
                STYLE_FIELDS.forEach((field) => editor.resetPath(field.path));
            }}
            description={__(
                'Palette entries are exposed as CSS variables (var(--wp--preset--color--{slug})).',
                TEXT_DOMAIN
            )}
        >
            <PanelRow>
                <div
                    className="ap-site-editor__style-palette"
                    data-testid="ap-site-editor-style-panel-palette"
                >
                    <div className="ap-site-editor__style-palette-header">
                        <h4 className="ap-site-editor__style-panel-presets-title">
                            {__('Palette', TEXT_DOMAIN)}
                        </h4>
                        <Button
                            variant="secondary"
                            size="small"
                            data-testid="ap-site-editor-style-palette-add"
                            onClick={addSwatch}
                        >
                            {__('Add color', TEXT_DOMAIN)}
                        </Button>
                    </div>
                    {paletteError !== null ? (
                        <p
                            role="alert"
                            className="ap-site-editor__style-control-row-error"
                            data-testid="ap-site-editor-style-palette-error"
                        >
                            {paletteError}
                        </p>
                    ) : null}
                    {palette.length === 0 ? (
                        <p className="ap-site-editor__style-panel-description">
                            {__(
                                'No palette entries — add one to expose it as a CSS variable.',
                                TEXT_DOMAIN
                            )}
                        </p>
                    ) : null}
                    <ul
                        className="ap-site-editor__style-palette-list"
                        aria-label={__('Color palette', TEXT_DOMAIN)}
                    >
                        {palette.map((entry, index) => (
                            <li
                                key={`${entry.slug}-${index}`}
                                className="ap-site-editor__style-palette-row"
                                data-testid={`ap-site-editor-style-palette-row-${index}`}
                            >
                                <Dropdown
                                    popoverProps={{ placement: 'bottom-start' }}
                                    renderToggle={({ isOpen, onToggle }) => (
                                        <Button
                                            variant="tertiary"
                                            className="ap-site-editor__style-palette-color-trigger"
                                            aria-expanded={isOpen}
                                            aria-label={__(
                                                'Color value',
                                                TEXT_DOMAIN
                                            )}
                                            data-testid={`ap-site-editor-style-palette-color-${index}`}
                                            onClick={onToggle}
                                        >
                                            <ColorIndicator
                                                colorValue={entry.color}
                                            />
                                        </Button>
                                    )}
                                    renderContent={() => (
                                        <ColorPicker
                                            color={entry.color}
                                            enableAlpha={false}
                                            onChange={(next) =>
                                                updateSwatch(
                                                    index,
                                                    'color',
                                                    next
                                                )
                                            }
                                        />
                                    )}
                                />
                                <TextControl
                                    label={__('Color name', TEXT_DOMAIN)}
                                    hideLabelFromVision={true}
                                    value={entry.name}
                                    placeholder={__('Name', TEXT_DOMAIN)}
                                    data-testid={`ap-site-editor-style-palette-name-${index}`}
                                    __nextHasNoMarginBottom={true}
                                    __next40pxDefaultSize={true}
                                    onChange={(next) =>
                                        updateSwatch(index, 'name', next)
                                    }
                                />
                                <TextControl
                                    label={__('Color slug', TEXT_DOMAIN)}
                                    hideLabelFromVision={true}
                                    value={entry.slug}
                                    placeholder={__('slug', TEXT_DOMAIN)}
                                    data-testid={`ap-site-editor-style-palette-slug-${index}`}
                                    __nextHasNoMarginBottom={true}
                                    __next40pxDefaultSize={true}
                                    onChange={(next) =>
                                        updateSwatch(index, 'slug', next)
                                    }
                                />
                                <div className="ap-site-editor__style-palette-row-actions">
                                    <Button
                                        variant="tertiary"
                                        size="small"
                                        aria-label={__('Move up', TEXT_DOMAIN)}
                                        data-testid={`ap-site-editor-style-palette-up-${index}`}
                                        disabled={index === 0}
                                        onClick={() => moveSwatch(index, -1)}
                                    >
                                        {__('↑', TEXT_DOMAIN)}
                                    </Button>
                                    <Button
                                        variant="tertiary"
                                        size="small"
                                        aria-label={__('Move down', TEXT_DOMAIN)}
                                        data-testid={`ap-site-editor-style-palette-down-${index}`}
                                        disabled={
                                            index === palette.length - 1
                                        }
                                        onClick={() => moveSwatch(index, 1)}
                                    >
                                        {__('↓', TEXT_DOMAIN)}
                                    </Button>
                                    <Button
                                        variant="tertiary"
                                        size="small"
                                        isDestructive={true}
                                        data-testid={`ap-site-editor-style-palette-remove-${index}`}
                                        onClick={() => removeSwatch(index)}
                                    >
                                        {__('Remove', TEXT_DOMAIN)}
                                    </Button>
                                </div>
                            </li>
                        ))}
                    </ul>
                </div>
            </PanelRow>

            {STYLE_FIELDS.map((field) => {
                const value = stringOrEmpty(editor.readValue(field.path));
                const baseValue = stringOrEmpty(
                    editor.readBaseValue(field.path)
                );
                const error =
                    validationErrors?.[field.path.join('.')]?.[0] ?? null;
                const resolved = resolveValueForPalette(value, paletteOptions);

                return (
                    <StyleControlRow
                        key={field.testId}
                        testId={field.testId}
                        isCustomized={editor.isPathCustomized(field.path)}
                        onReset={() => editor.resetPath(field.path)}
                        baseValue={baseValue === '' ? undefined : baseValue}
                        error={error}
                    >
                        <div
                            className="ap-site-editor__style-color-field"
                            data-testid={`ap-site-editor-style-swatches-${field.testId}`}
                        >
                            <span className="ap-site-editor__style-color-field-label">
                                {__(field.label, TEXT_DOMAIN)}
                            </span>
                            <ColorPalette
                                colors={paletteOptions}
                                value={resolved}
                                disableCustomColors={false}
                                clearable={true}
                                enableAlpha={false}
                                onChange={(next?: string) => {
                                    if (next === undefined || next === '') {
                                        editor.resetPath(field.path);
                                        return;
                                    }

                                    editor.setValue(
                                        field.path,
                                        paletteRefFromColor(next, paletteOptions)
                                    );
                                }}
                            />
                        </div>
                    </StyleControlRow>
                );
            })}
        </StylePanelSection>
    );
}
