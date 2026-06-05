import { render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { ComponentType } from 'react';

const filters: Array<{
    hook: string;
    namespace: string;
    callback: (component: unknown) => unknown;
}> = [];

vi.mock('@wordpress/hooks', () => ({
    addFilter: (
        hook: string,
        namespace: string,
        callback: (component: unknown) => unknown
    ) => {
        filters.push({ hook, namespace, callback });
    },
}));

let mockSettings: Record<string, unknown> = {};

vi.mock('@wordpress/data', () => ({
    useSelect: (
        mapSelect: (
            select: (name: string) => Record<string, unknown> | undefined
        ) => unknown
    ) =>
        mapSelect((name: string) => {
            if (name === 'core/block-editor') {
                return {
                    getSettings: () => mockSettings,
                };
            }

            return undefined;
        }),
}));

const colorSupportRegistry = new Map<string, boolean>();

vi.mock('@wordpress/blocks', () => ({
    hasBlockSupport: (name: string, feature: string) =>
        feature === 'color' ? colorSupportRegistry.get(name) === true : false,
}));

vi.mock('@wordpress/block-editor', () => ({
    InspectorControls: ({
        children,
        group,
    }: {
        children: React.ReactNode;
        group?: string;
    }) => (
        <div data-testid="inspector-controls" data-group={group}>
            {children}
        </div>
    ),
}));

vi.mock('@wordpress/components', () => ({
    Notice: ({
        children,
        status,
    }: {
        children: React.ReactNode;
        status?: string;
    }) => (
        <div data-testid="notice" data-status={status} role="alert">
            {children}
        </div>
    ),
}));

vi.mock('@wordpress/i18n', () => ({
    __: (text: string) => text,
    sprintf: (template: string, value: string) => template.replace('%s', value),
}));

const REGISTERED_KEY = Symbol.for(
    'artisanpack-ui.visual-editor.contrast-warning.registered'
);

beforeEach(() => {
    filters.length = 0;
    mockSettings = {};
    colorSupportRegistry.clear();
    delete (globalThis as Record<symbol, unknown>)[REGISTERED_KEY];
    vi.resetModules();
});

afterEach(() => {
    delete (globalThis as Record<symbol, unknown>)[REGISTERED_KEY];
    vi.resetModules();
});

describe('evaluateBlockContrast', () => {
    it('returns null when no text or background color is set', async () => {
        const { evaluateBlockContrast } = await import('../contrast-warning');

        expect(
            evaluateBlockContrast({ attributes: {}, palette: [] })
        ).toBeNull();
        expect(
            evaluateBlockContrast({
                attributes: { style: { color: { text: '#000000' } } },
                palette: [],
            })
        ).toBeNull();
        expect(
            evaluateBlockContrast({
                attributes: { style: { color: { background: '#ffffff' } } },
                palette: [],
            })
        ).toBeNull();
    });

    it('passes high-contrast attribute pairs', async () => {
        const { evaluateBlockContrast } = await import('../contrast-warning');

        const result = evaluateBlockContrast({
            attributes: {
                style: {
                    color: { text: '#000000', background: '#ffffff' },
                },
            },
            palette: [],
        });

        expect(result).not.toBeNull();
        expect(result?.passes).toBe(true);
        expect(result?.ratio).toBeGreaterThanOrEqual(4.5);
    });

    it('flags low-contrast attribute pairs', async () => {
        const { evaluateBlockContrast } = await import('../contrast-warning');

        const result = evaluateBlockContrast({
            attributes: {
                style: {
                    color: { text: '#cccccc', background: '#ffffff' },
                },
            },
            palette: [],
        });

        expect(result?.passes).toBe(false);
        expect(result?.ratio).toBeLessThan(4.5);
    });

    it('resolves textColor/backgroundColor slugs against the palette', async () => {
        const { evaluateBlockContrast } = await import('../contrast-warning');

        const palette = [
            { slug: 'base-content', color: '#1f2937' },
            { slug: 'primary', color: '#2563eb' },
        ];

        const result = evaluateBlockContrast({
            attributes: {
                textColor: 'base-content',
                backgroundColor: 'primary',
            },
            palette,
        });

        expect(result?.foreground).toBe('#1f2937');
        expect(result?.background).toBe('#2563eb');
    });

    it('resolves var:preset|color| references in style.color.*', async () => {
        const { evaluateBlockContrast } = await import('../contrast-warning');

        const palette = [{ slug: 'success', color: '#16a34a' }];

        const result = evaluateBlockContrast({
            attributes: {
                style: {
                    color: {
                        text: 'var:preset|color|success',
                        background: '#ffffff',
                    },
                },
            },
            palette,
        });

        expect(result?.foreground).toBe('#16a34a');
        expect(result?.background).toBe('#ffffff');
    });

    it('returns null for unresolvable preset slugs', async () => {
        const { evaluateBlockContrast } = await import('../contrast-warning');

        const result = evaluateBlockContrast({
            attributes: {
                textColor: 'mystery-color',
                backgroundColor: 'primary',
            },
            palette: [{ slug: 'primary', color: '#2563eb' }],
        });

        expect(result).toBeNull();
    });

    it('prefers slug attributes over style.color when both are set', async () => {
        const { evaluateBlockContrast } = await import('../contrast-warning');

        const palette = [{ slug: 'base-content', color: '#1f2937' }];

        const result = evaluateBlockContrast({
            attributes: {
                textColor: 'base-content',
                style: {
                    color: { text: '#ff00ff', background: '#ffffff' },
                },
            },
            palette,
        });

        // Slug wins → foreground resolves to the palette entry, not the
        // raw hex in style.color.text.
        expect(result?.foreground).toBe('#1f2937');
    });
});

describe('<ContrastWarning />', () => {
    it('renders a warning notice when contrast fails', async () => {
        mockSettings = {
            colors: [
                { slug: 'base-content', color: '#cccccc' },
                { slug: 'page', color: '#ffffff' },
            ],
        };

        const { ContrastWarning } = await import('../contrast-warning');

        render(
            <ContrastWarning
                attributes={{
                    textColor: 'base-content',
                    backgroundColor: 'page',
                }}
            />
        );

        const notice = screen.getByTestId('notice');
        expect(notice).toHaveAttribute('data-status', 'warning');
        expect(notice.textContent).toMatch(/contrast ratio/i);
        expect(screen.getByTestId('inspector-controls')).toHaveAttribute(
            'data-group',
            'color'
        );
    });

    it('truncates the displayed ratio rather than rounding it up', async () => {
        // #777777 on #ffffff produces a ratio of ~4.4781. The
        // regression we're guarding against is `toFixed(2)` rounding
        // failing ratios upward — here it would render "4.48", which
        // is fine for this exact pair but telegraphs the rounding
        // direction. Truncation locks the displayed value strictly
        // below the actual ratio so no failing ratio can ever read
        // ≥ 4.50:1 in the warning.
        mockSettings = {
            colors: [
                { slug: 'fg', color: '#777777' },
                { slug: 'bg', color: '#ffffff' },
            ],
        };

        const { ContrastWarning } = await import('../contrast-warning');

        render(
            <ContrastWarning
                attributes={{ textColor: 'fg', backgroundColor: 'bg' }}
            />
        );

        const notice = screen.getByTestId('notice');
        // Truncated value for ratio 4.4781 is 4.47, not 4.48.
        expect(notice.textContent).toContain('4.47:1');
        expect(notice.textContent).not.toContain('4.50:1');
    });

    it('renders nothing when contrast passes', async () => {
        mockSettings = {
            colors: [
                { slug: 'base-content', color: '#000000' },
                { slug: 'page', color: '#ffffff' },
            ],
        };

        const { ContrastWarning } = await import('../contrast-warning');

        const { container } = render(
            <ContrastWarning
                attributes={{
                    textColor: 'base-content',
                    backgroundColor: 'page',
                }}
            />
        );

        expect(container.firstChild).toBeNull();
    });

    it('renders nothing when text or background is missing', async () => {
        const { ContrastWarning } = await import('../contrast-warning');

        const { container: noBg } = render(
            <ContrastWarning
                attributes={{
                    style: { color: { text: '#000000' } },
                }}
            />
        );
        expect(noBg.firstChild).toBeNull();

        const { container: noFg } = render(
            <ContrastWarning
                attributes={{
                    style: { color: { background: '#ffffff' } },
                }}
            />
        );
        expect(noFg.firstChild).toBeNull();
    });

    it('reads palette from __experimentalFeatures.color.palette.custom', async () => {
        mockSettings = {
            __experimentalFeatures: {
                color: {
                    palette: {
                        custom: [{ slug: 'muted', color: '#cccccc' }],
                    },
                },
            },
        };

        const { ContrastWarning } = await import('../contrast-warning');

        render(
            <ContrastWarning
                attributes={{
                    textColor: 'muted',
                    style: { color: { background: '#ffffff' } },
                }}
            />
        );

        // #cccccc on #ffffff fails AA — warning should render.
        expect(screen.getByTestId('notice')).toBeInTheDocument();
    });
});

describe('registerContrastWarning', () => {
    it('registers the editor.BlockEdit filter exactly once', async () => {
        const mod = await import('../contrast-warning');

        mod.registerContrastWarning();
        mod.registerContrastWarning();
        mod.registerContrastWarning();

        expect(filters).toHaveLength(1);
        expect(filters[0]?.hook).toBe('editor.BlockEdit');
        expect(filters[0]?.namespace).toBe(
            'artisanpack-ui/visual-editor/contrast-warning'
        );
    });

    it('passes through blocks without color support unchanged', async () => {
        const { withContrastWarning } = await import('../contrast-warning');

        colorSupportRegistry.set('core/spacer', false);

        const InnerEdit: ComponentType<{ name: string }> = ({ name }) => (
            <div data-testid="inner">{String(name)}</div>
        );

        const Wrapped = withContrastWarning(
            InnerEdit as unknown as Parameters<typeof withContrastWarning>[0]
        );

        render(<Wrapped name="core/spacer" attributes={{}} />);

        expect(screen.getByTestId('inner')).toHaveTextContent('core/spacer');
        expect(screen.queryByTestId('notice')).toBeNull();
        expect(screen.queryByTestId('inspector-controls')).toBeNull();
    });

    it('renders the contrast warning alongside blocks that support color', async () => {
        mockSettings = {
            colors: [
                { slug: 'base-content', color: '#cccccc' },
                { slug: 'page', color: '#ffffff' },
            ],
        };

        const { withContrastWarning } = await import('../contrast-warning');

        colorSupportRegistry.set('core/paragraph', true);

        const InnerEdit: ComponentType<{ name: string }> = ({ name }) => (
            <div data-testid="inner">{String(name)}</div>
        );

        const Wrapped = withContrastWarning(
            InnerEdit as unknown as Parameters<typeof withContrastWarning>[0]
        );

        render(
            <Wrapped
                name="core/paragraph"
                attributes={{
                    textColor: 'base-content',
                    backgroundColor: 'page',
                }}
            />
        );

        expect(screen.getByTestId('inner')).toHaveTextContent('core/paragraph');
        expect(screen.getByTestId('notice')).toBeInTheDocument();
    });
});
