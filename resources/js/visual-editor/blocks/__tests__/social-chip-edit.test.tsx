/**
 * Tests for the Author Social Icons and Social Share canvas chips (#1027, #1079).
 */

import type { ComponentType } from 'react';
import { describe, it, expect, vi } from 'vitest';
import { render } from '@testing-library/react';

vi.mock('@wordpress/i18n', () => ({
    __: (text: string) => text,
    _x: (text: string) => text,
}));

vi.mock('@wordpress/block-editor', () => ({
    InspectorControls: () => null,
    PanelColorSettings: () => null,
    useBlockProps: (props: Record<string, unknown>) => props,
}));

vi.mock('@wordpress/components', () => ({
    CheckboxControl: () => null,
    PanelBody: () => null,
    RangeControl: () => null,
    SelectControl: () => null,
}));

import AuthorSocialIconsEdit from '../author-social-icons/edit';
import SocialShareContentEdit from '../social-share-content/edit';
import { authorSocialPlatforms, shareSocialPlatforms } from '../social-link/services';

const attributes = (socialIcons: ReadonlyArray<string>): Record<string, unknown> => ({
    socialIcons,
    iconStyle: 'show-label-icon',
    iconsDirection: 'horizontal',
    iconsStretch: 'auto-width',
    iconsBorderRadius: 0,
    iconColor: '',
    iconHoverColor: '',
    iconBackgroundColor: '',
    iconHoverBackgroundColor: '',
});

describe.each([
    ['author-social-icons', AuthorSocialIconsEdit, 'ap-author-social-icons', authorSocialPlatforms],
    ['social-share-content', SocialShareContentEdit, 'ap-social-share-content', shareSocialPlatforms],
])('%s canvas chips', (_name, Edit, prefix, platforms) => {
    const Component = Edit as unknown as ComponentType<Record<string, unknown>>;

    it.each(platforms().map(({ slug, label, viewBox, path }) => [slug, label, viewBox, path]))(
        'draws %s with its own glyph and viewBox',
        (slug, label, viewBox, path) => {
            const { container } = render(
                <Component attributes={attributes([slug])} setAttributes={vi.fn()} />
            );

            const chip = container.querySelector(`.${prefix}__chip.${slug}`);

            expect(chip?.querySelector('svg')?.getAttribute('viewBox')).toBe(viewBox);
            expect(chip?.querySelector('path')?.getAttribute('d')).toBe(path);
            expect(chip?.textContent).toBe(label);
        }
    );

    it('uses non-square viewBoxes rather than a fixed 24×24 box', () => {
        const { container } = render(
            <Component attributes={attributes(['twitter', 'email'])} setAttributes={vi.fn()} />
        );

        const viewBoxes = Array.from(container.querySelectorAll('svg')).map((svg) =>
            svg.getAttribute('viewBox')
        );

        expect(viewBoxes).toEqual(['0 0 448 512', '0 0 512 512']);
    });

    it('sets safe colors on the chip as custom properties (#1079)', () => {
        const slug = platforms()[0].slug;
        const { container } = render(
            <Component
                attributes={{
                    ...attributes([slug]),
                    iconColor: '#112233',
                    iconHoverBackgroundColor: 'white',
                }}
                setAttributes={vi.fn()}
            />
        );

        const chip = container.querySelector<HTMLElement>(`.${prefix}__chip.${slug}`);

        expect(chip?.style.getPropertyValue('--ap-social-color')).toBe('#112233');
        expect(chip?.style.getPropertyValue('--ap-social-hover-bg')).toBe('white');
    });

    it('drops hostile colors from the chip (#1079)', () => {
        const slug = platforms()[0].slug;
        const { container } = render(
            <Component
                attributes={{
                    ...attributes([slug]),
                    iconColor: 'red;position:fixed;inset:0',
                    iconBackgroundColor: 'url(//evil.test/x.png)',
                    iconHoverColor: 'red}body{display:none',
                    iconHoverBackgroundColor: '</style><script>alert(1)</script>',
                }}
                setAttributes={vi.fn()}
            />
        );

        const chip = container.querySelector<HTMLElement>(`.${prefix}__chip.${slug}`);

        expect(chip).not.toBeNull();
        expect(chip?.getAttribute('style') ?? '').not.toContain('--ap-social-');
    });
});
