/**
 * Tests for the `artisanpack/button` save component.
 */

import { describe, it, expect, vi } from 'vitest';
import { renderToStaticMarkup } from 'react-dom/server';

vi.mock('@wordpress/block-editor', () => ({
    useBlockProps: Object.assign(
        (props?: Record<string, unknown>) => ({ ...props }),
        { save: (props?: Record<string, unknown>) => ({ ...props }) }
    ),
    RichText: Object.assign(
        () => null,
        {
            Content: (
                props: Record<string, unknown> & {
                    tagName?: string;
                    value?: string;
                }
            ) => {
                const Tag = (props.tagName as 'a' | 'button') || 'a';
                const {
                    tagName: _tagName,
                    value,
                    ...rest
                } = props;
                return (
                    <Tag
                        {...(rest as Record<string, unknown>)}
                        dangerouslySetInnerHTML={{ __html: value ?? '' }}
                    />
                );
            },
        }
    ),
    __experimentalGetBorderClassesAndStyles: () => ({
        className: undefined,
        style: {},
    }),
    __experimentalGetColorClassesAndStyles: () => ({
        className: undefined,
        style: {},
    }),
    __experimentalGetSpacingClassesAndStyles: () => ({ style: {} }),
    __experimentalGetShadowClassesAndStyles: () => ({ style: {} }),
    __experimentalGetElementClassName: () => undefined,
    getTypographyClassesAndStyles: () => ({
        className: undefined,
        style: {},
    }),
}));

import ButtonSave from '../save';
import metadata from '../block.json';

describe('artisanpack/button block.json', () => {
    it('declares the artisanpack namespace and design category', () => {
        expect(metadata.name).toBe('artisanpack/button');
        expect(metadata.category).toBe('design');
    });

    it('lists artisanpack/buttons as the only parent', () => {
        expect(metadata.parent).toEqual(['artisanpack/buttons']);
    });

    it('uses the artisanpack textdomain', () => {
        expect(metadata.textdomain).toBe('artisanpack-visual-editor');
    });

    it('defaults the tagName to an <a>', () => {
        expect(metadata.attributes.tagName.default).toBe('a');
        expect(metadata.attributes.tagName.enum).toEqual(['a', 'button']);
    });

    it('declares both fill and outline styles', () => {
        const names = metadata.styles.map((s) => s.name);
        expect(names).toEqual(['fill', 'outline']);
    });
});

describe('ButtonSave', () => {
    it('renders an <a> by default wrapped in a div', () => {
        const html = renderToStaticMarkup(
            <ButtonSave attributes={{ text: 'Click', url: '/x' }} />
        );
        expect(html).toContain('<div');
        expect(html).toContain('<a');
        expect(html).toContain('Click');
    });

    it('renders a <button> tag when tagName is button', () => {
        const html = renderToStaticMarkup(
            <ButtonSave
                attributes={{ tagName: 'button', text: 'Submit' }}
            />
        );
        expect(html).toContain('<button');
    });

    it('adds the wp-block-button wrapper class', () => {
        const html = renderToStaticMarkup(
            <ButtonSave attributes={{ text: 'X' }} />
        );
        expect(html).toContain('wp-block-button');
    });

    it('emits the wp-block-button__link class on the link', () => {
        const html = renderToStaticMarkup(
            <ButtonSave attributes={{ text: 'X', url: '/y' }} />
        );
        expect(html).toContain('wp-block-button__link');
    });
});
