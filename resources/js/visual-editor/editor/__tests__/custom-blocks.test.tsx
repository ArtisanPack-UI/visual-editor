/**
 * Tests for the custom block discovery/registration pipeline and the
 * bundled `artisanpack/callout` reference block.
 *
 * `@wordpress/blocks` is fully mocked so Node's strict JSON import
 * semantics don't trip over the package's internal
 * `i18n-block.json` import — we only need the mock to prove the
 * orchestration layer calls the right WP APIs.
 */

import { describe, it, expect, beforeEach, vi } from 'vitest';
import { render } from '@testing-library/react';

const categoriesState: Array<{ slug: string; title: string }> = [];
const registeredTypes = new Map<string, unknown>();

vi.mock('@wordpress/blocks', () => ({
    getCategories: () => [...categoriesState],
    setCategories: (next: Array<{ slug: string; title: string }>) => {
        categoriesState.length = 0;
        categoriesState.push(...next);
    },
    registerBlockType: (name: string, settings: unknown) => {
        if (registeredTypes.has(name)) {
            throw new Error(`Block ${name} already registered`);
        }
        registeredTypes.set(name, settings);
        return settings;
    },
    getBlockType: (name: string) => registeredTypes.get(name),
    unregisterBlockType: (name: string) => {
        registeredTypes.delete(name);
    },
    getBlockTypes: () =>
        Array.from(registeredTypes.entries()).map(([name, settings]) => ({
            name,
            ...(settings as Record<string, unknown>),
        })),
}));

vi.mock('@wordpress/block-editor', () => ({
    InspectorControls: ({ children }: { children: React.ReactNode }) => (
        <div data-testid="inspector">{children}</div>
    ),
    RichText: Object.assign(
        function RichText(props: { value?: string; className?: string }) {
            return (
                <div
                    data-testid="richtext"
                    className={props.className}
                    dangerouslySetInnerHTML={{ __html: props.value ?? '' }}
                />
            );
        },
        {
            Content: function RichTextContent(props: {
                value?: string;
                className?: string;
            }) {
                return (
                    <div
                        data-testid="richtext-content"
                        className={props.className}
                        dangerouslySetInnerHTML={{ __html: props.value ?? '' }}
                    />
                );
            },
        }
    ),
    useBlockProps: Object.assign(
        (props?: Record<string, unknown>) => ({ ...props }),
        { save: (props?: Record<string, unknown>) => ({ ...props }) }
    ),
    // I2 media-cluster forks (cover) use these block-editor HOCs at module load.
    // Provide minimal stubs so the discovery test can import the block index.
    withColors:
        (..._args: unknown[]) =>
        (Component: unknown) =>
            Component,
    useSettings: () => [undefined],
    useBlockEditingMode: () => 'default',
    BlockControls: ({ children }: { children: React.ReactNode }) => (
        <div data-testid="block-controls">{children}</div>
    ),
    BlockIcon: () => null,
    MediaPlaceholder: () => null,
    MediaReplaceFlow: () => null,
    MediaUpload: () => null,
    MediaUploadCheck: ({ children }: { children: React.ReactNode }) => (
        <>{children}</>
    ),
    BlockVerticalAlignmentToolbar: () => null,
    InnerBlocks: Object.assign(() => null, {
        Content: () => null,
        ButtonBlockAppender: () => null,
        DefaultBlockAppender: () => null,
    }),
    ColorPalette: () => null,
    ContrastChecker: () => null,
    PanelColorSettings: ({ children }: { children?: React.ReactNode }) => (
        <>{children}</>
    ),
    URLInput: () => null,
    URLPopover: () => null,
    AlignmentControl: () => null,
    BlockAlignmentControl: () => null,
    __experimentalImageURLInputUI: () => null,
    __experimentalImageSizeControl: () => null,
    __experimentalGetElementClassName: (name: string) =>
        `wp-element-${name}`,
    __experimentalGetBorderClassesAndStyles: () => ({ className: '', style: {} }),
    __experimentalGetShadowClassesAndStyles: () => ({ className: '', style: {} }),
    __experimentalUseBorderProps: () => ({ className: '', style: {} }),
    __experimentalUseColorProps: () => ({ className: '', style: {} }),
    __experimentalUseGradient: () => ({
        gradientClass: undefined,
        gradientValue: undefined,
        setGradient: () => {},
    }),
    __experimentalGetGradientClass: () => '',
    __experimentalGetGradientObjectByGradientValue: () => undefined,
    __experimentalPanelColorGradientSettings: () => null,
    __experimentalColorGradientSettingsDropdown: () => null,
    __experimentalUseMultipleOriginColorsAndGradients: () => ({
        colors: [],
        gradients: [],
    }),
    __experimentalLinkControl: () => null,
    BlockControlsBlock: ({ children }: { children: React.ReactNode }) => (
        <>{children}</>
    ),
    HeightControl: () => null,
    store: { name: 'core/block-editor' },
    // I3 layout-cluster forks (spacer, columns) reach for the
    // `privateApis` lock-and-unlock surface. Stub the unlocked shape
    // they consume so importing the block doesn't blow up.
    privateApis: { useSpacingSizes: () => [] },
    __experimentalUseSpacingSizes: () => [],
    __experimentalBlockVariationPicker: () => null,
}));

vi.mock('@wordpress/components', () => ({
    PanelBody: ({
        title,
        children,
    }: {
        title: string;
        children: React.ReactNode;
    }) => (
        <div data-testid="panel-body" data-title={title}>
            {children}
        </div>
    ),
    SelectControl: ({
        label,
        value,
        options,
        onChange,
    }: {
        label: string;
        value: string;
        options: ReadonlyArray<{ label: string; value: string }>;
        onChange: (value: string) => void;
    }) => (
        <label>
            {label}
            <select
                value={value}
                onChange={(event) => onChange(event.target.value)}
                data-testid={`select-${label}`}
            >
                {options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
        </label>
    ),
}));

vi.mock('@wordpress/i18n', () => ({
    __: (text: string) => text,
    _x: (text: string) => text,
    _n: (single: string, plural: string, n: number) => (n === 1 ? single : plural),
    _nx: (single: string, plural: string, n: number) => (n === 1 ? single : plural),
    sprintf: (format: string, ...args: unknown[]) => {
        let i = 0;
        return format.replace(/%[sdif]/g, () => String(args[i++] ?? ''));
    },
    isRTL: () => false,
}));

import {
    ARTISANPACK_CATEGORY_SLUG,
    SEARCH_CATEGORY_SLUG,
    __resetCustomBlockRegistrationCache,
    ensureArtisanpackCategory,
    ensureSearchCategory,
    registerCustomBlocks,
} from '../custom-blocks';

import calloutEdit from '../../blocks/callout/edit';
import calloutSave from '../../blocks/callout/save';
import calloutIcon from '../../blocks/callout/inserter-icon';
import calloutMetadata from '../../blocks/callout/block.json';

beforeEach(() => {
    categoriesState.length = 0;
    categoriesState.push(
        { slug: 'text', title: 'Text' },
        { slug: 'media', title: 'Media' }
    );
    registeredTypes.clear();
    __resetCustomBlockRegistrationCache();
});

describe('ensureArtisanpackCategory', () => {
    it('adds the artisanpack category when missing', () => {
        ensureArtisanpackCategory('ArtisanPack');

        expect(categoriesState.map((cat) => cat.slug)).toContain(
            ARTISANPACK_CATEGORY_SLUG
        );
    });

    it('does not duplicate the category on repeat calls', () => {
        ensureArtisanpackCategory();
        ensureArtisanpackCategory();

        const matches = categoriesState.filter(
            (cat) => cat.slug === ARTISANPACK_CATEGORY_SLUG
        );
        expect(matches).toHaveLength(1);
    });

    it('preserves pre-existing categories', () => {
        ensureArtisanpackCategory();

        const slugs = categoriesState.map((cat) => cat.slug);
        expect(slugs).toContain('text');
        expect(slugs).toContain('media');
    });
});

describe('ensureSearchCategory', () => {
    it('adds the search category when missing', () => {
        ensureSearchCategory('Search');

        expect(categoriesState.map((cat) => cat.slug)).toContain(
            SEARCH_CATEGORY_SLUG
        );
    });

    it('does not duplicate the category on repeat calls', () => {
        ensureSearchCategory();
        ensureSearchCategory();

        const matches = categoriesState.filter(
            (cat) => cat.slug === SEARCH_CATEGORY_SLUG
        );
        expect(matches).toHaveLength(1);
    });

    it('preserves pre-existing categories', () => {
        ensureSearchCategory();

        const slugs = categoriesState.map((cat) => cat.slug);
        expect(slugs).toContain('text');
        expect(slugs).toContain('media');
    });
});

describe('registerCustomBlocks', () => {
    it('registers a module with metadata, edit, and save', () => {
        const names = registerCustomBlocks([
            {
                metadata: calloutMetadata,
                edit: calloutEdit,
                save: calloutSave,
            },
        ]);

        expect(names).toContain('artisanpack/callout');
        expect(registeredTypes.has('artisanpack/callout')).toBe(true);
    });

    it('skips modules missing a metadata name', () => {
        const names = registerCustomBlocks([
            {
                metadata: { title: 'Nameless' } as unknown as {
                    name: string;
                },
                edit: calloutEdit,
            },
        ]);

        expect(names).toHaveLength(0);
    });

    it('is idempotent on repeat calls for the same block', () => {
        registerCustomBlocks([
            { metadata: calloutMetadata, edit: calloutEdit, save: calloutSave },
        ]);
        const second = registerCustomBlocks([
            { metadata: calloutMetadata, edit: calloutEdit, save: calloutSave },
        ]);

        expect(second).toEqual(['artisanpack/callout']);
        expect(registeredTypes.size).toBe(1);
    });

    it('passes edit and save through to registerBlockType settings', () => {
        registerCustomBlocks([
            { metadata: calloutMetadata, edit: calloutEdit, save: calloutSave },
        ]);

        const settings = registeredTypes.get('artisanpack/callout') as {
            edit?: unknown;
            save?: unknown;
            category?: string;
        };

        expect(settings.edit).toBe(calloutEdit);
        expect(settings.save).toBe(calloutSave);
        expect(settings.category).toBe('design');
    });

    it('forwards an icon override to registerBlockType settings', () => {
        registerCustomBlocks([
            {
                metadata: calloutMetadata,
                edit: calloutEdit,
                save: calloutSave,
                icon: calloutIcon,
            },
        ]);

        const settings = registeredTypes.get('artisanpack/callout') as {
            icon?: unknown;
        };

        expect(settings.icon).toBe(calloutIcon);
    });
});

describe('artisanpack/callout block.json', () => {
    it('declares severity + icon attributes with enum defaults', () => {
        expect(calloutMetadata.name).toBe('artisanpack/callout');
        expect(calloutMetadata.category).toBe('design');
        expect(calloutMetadata.attributes.severity.default).toBe('info');
        expect(calloutMetadata.attributes.icon.default).toBe('info');
        expect(calloutMetadata.attributes.severity.enum).toContain('warning');
        expect(calloutMetadata.attributes.icon.enum).toContain('lightbulb');
    });
});

describe('artisanpack/callout save', () => {
    it('renders with the severity class and icon SVG', () => {
        const { container } = render(
            calloutSave({
                attributes: {
                    severity: 'success',
                    icon: 'check',
                    content: '<strong>Saved!</strong>',
                },
            } as Parameters<typeof calloutSave>[0])
        );

        const root = container.querySelector('.ap-callout');
        expect(root).not.toBeNull();
        expect(root?.classList.contains('ap-callout--success')).toBe(true);
        expect(root?.getAttribute('data-severity')).toBe('success');
        expect(container.querySelector('svg')).not.toBeNull();
        expect(container.querySelector('.ap-callout__body')?.innerHTML).toBe(
            '<strong>Saved!</strong>'
        );
    });
});

describe('artisanpack/callout edit', () => {
    it('renders inspector controls for severity and icon and fires setAttributes on change', () => {
        const setAttributes = vi.fn();
        const { getByTestId } = render(
            calloutEdit({
                attributes: {
                    severity: 'info',
                    icon: 'info',
                    content: 'Body text',
                },
                setAttributes,
            } as Parameters<typeof calloutEdit>[0])
        );

        const severitySelect = getByTestId('select-Severity') as HTMLSelectElement;
        severitySelect.value = 'warning';
        severitySelect.dispatchEvent(new Event('change', { bubbles: true }));
        expect(setAttributes).toHaveBeenCalledWith({ severity: 'warning' });

        const iconSelect = getByTestId('select-Icon') as HTMLSelectElement;
        iconSelect.value = 'lightbulb';
        iconSelect.dispatchEvent(new Event('change', { bubbles: true }));
        expect(setAttributes).toHaveBeenCalledWith({ icon: 'lightbulb' });
    });
});
