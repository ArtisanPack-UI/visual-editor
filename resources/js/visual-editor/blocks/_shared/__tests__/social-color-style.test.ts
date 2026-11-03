/**
 * Social chip color style (#1079): only safe CSS values reach the chip's
 * inline style, matching the Blade partials.
 */

import { describe, expect, it } from 'vitest';

import { buildSocialColorStyle } from '../social-color-style';

describe('buildSocialColorStyle', () => {
    it('writes every safe color to its custom property', () => {
        expect(
            buildSocialColorStyle({
                iconColor: '#112233',
                iconBackgroundColor: 'rgba(0, 0, 0, 0.5)',
                iconHoverColor: 'var(--wp--preset--color--accent)',
                iconHoverBackgroundColor: 'white',
            })
        ).toEqual({
            '--ap-social-color': '#112233',
            '--ap-social-bg': 'rgba(0, 0, 0, 0.5)',
            '--ap-social-hover-color': 'var(--wp--preset--color--accent)',
            '--ap-social-hover-bg': 'white',
        });
    });

    it('returns an empty style when no colors are set', () => {
        expect(buildSocialColorStyle({})).toEqual({});
        expect(buildSocialColorStyle({ iconColor: '', iconHoverColor: '   ' })).toEqual({});
    });

    it.each([
        ['a declaration break', 'red;position:fixed;inset:0'],
        ['a rule break', 'red}body{display:none'],
        ['a protocol-relative url()', 'url(//evil.test/x.png)'],
        ['a tag break', '</style><script>alert(1)</script>'],
        ['a quote', '"><img src=x onerror=alert(1)>'],
        ['a comment', 'red/* x */'],
        ['an edge vertical tab', '\u000bred'],
        ['an edge NUL', '\u0000red'],
    ])('drops a value carrying %s', (_label, value) => {
        expect(
            buildSocialColorStyle({
                iconColor: value,
                iconBackgroundColor: value,
                iconHoverColor: value,
                iconHoverBackgroundColor: value,
            })
        ).toEqual({});
    });

    it('keeps the safe colors when another one is dropped', () => {
        expect(
            buildSocialColorStyle({
                iconColor: 'red;position:fixed',
                iconHoverColor: '#abcdef',
            })
        ).toEqual({ '--ap-social-hover-color': '#abcdef' });
    });
});
