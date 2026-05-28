/**
 * Locks the deprecation chain for `artisanpack/audio`.
 */

import { describe, it, expect, vi } from 'vitest';
import { renderToStaticMarkup } from 'react-dom/server';

vi.mock('@wordpress/block-editor', () => ({
    RichText: Object.assign(() => null, {
        isEmpty: (value?: string) => !value || value === '',
        Content: ({
            value,
            tagName,
        }: {
            value?: string;
            tagName?: string;
        }) => {
            const Tag = (tagName ?? 'figcaption') as keyof JSX.IntrinsicElements;
            return (
                <Tag
                    dangerouslySetInnerHTML={{ __html: value ?? '' }}
                />
            );
        },
    }),
}));

import deprecated from '../deprecated';

describe('audio deprecation chain', () => {
    it('ships a single deprecation entry matching upstream', () => {
        expect(Array.isArray(deprecated)).toBe(true);
        expect(deprecated).toHaveLength(1);
    });

    it('preserves the legacy html-source caption schema in v1', () => {
        const v1 = deprecated[0];
        expect(v1.attributes.caption.source).toBe('html');
        expect(v1.attributes.caption.selector).toBe('figcaption');
    });

    it('renders v1 markup with the legacy figcaption structure', () => {
        const v1 = deprecated[0];
        const html = renderToStaticMarkup(
            v1.save({
                attributes: {
                    src: 'https://example.com/song.mp3',
                    caption: 'legacy',
                },
            })
        );
        expect(html).toContain('<figure');
        expect(html).toContain('<audio');
        expect(html).toContain('<figcaption');
        expect(html).toContain('legacy');
    });
});
