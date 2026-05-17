/**
 * Unit tests for `parseNavigationContent` — the shim's local
 * block-comment parser scoped to `core/navigation-link` /
 * `core/navigation-submenu` (Keystone #48). Mirror of the PHP
 * `MenuItemBlockBridge::rawToBlocks` tests; if you change one,
 * change the other.
 */

import { describe, expect, it } from 'vitest';

import { parseNavigationContent } from '../parse-navigation-content';

describe('parseNavigationContent', () => {
    it('returns [] for an empty / whitespace-only string', () => {
        expect(parseNavigationContent('')).toEqual([]);
        expect(parseNavigationContent('   \n\t')).toEqual([]);
    });

    it('parses a self-closing nav-link with JSON attributes', () => {
        expect(
            parseNavigationContent(
                '<!-- wp:navigation-link {"label":"Home","url":"/"} /-->'
            )
        ).toEqual([
            {
                name: 'core/navigation-link',
                attributes: { label: 'Home', url: '/' },
                innerBlocks: [],
            },
        ]);
    });

    it('parses a self-closing nav-link with no attributes', () => {
        expect(parseNavigationContent('<!-- wp:navigation-link /-->')).toEqual([
            {
                name: 'core/navigation-link',
                attributes: {},
                innerBlocks: [],
            },
        ]);
    });

    it('parses a submenu with nested children', () => {
        const raw =
            '<!-- wp:navigation-submenu {"label":"About"} -->\n' +
            '<!-- wp:navigation-link {"label":"Team"} /-->\n' +
            '<!-- /wp:navigation-submenu -->';

        expect(parseNavigationContent(raw)).toEqual([
            {
                name: 'core/navigation-submenu',
                attributes: { label: 'About' },
                innerBlocks: [
                    {
                        name: 'core/navigation-link',
                        attributes: { label: 'Team' },
                        innerBlocks: [],
                    },
                ],
            },
        ]);
    });

    it('parses multiple siblings on separate lines', () => {
        const raw =
            '<!-- wp:navigation-link {"label":"A"} /-->\n' +
            '<!-- wp:navigation-link {"label":"B"} /-->';

        const parsed = parseNavigationContent(raw);

        expect(parsed).toHaveLength(2);
        expect(parsed[0]?.attributes).toEqual({ label: 'A' });
        expect(parsed[1]?.attributes).toEqual({ label: 'B' });
    });

    it('drops unsupported block types at any nesting level', () => {
        const raw =
            '<!-- wp:paragraph {"content":"nope"} /-->\n' +
            '<!-- wp:navigation-link {"label":"Real"} /-->';

        const parsed = parseNavigationContent(raw);

        expect(parsed).toHaveLength(1);
        expect(parsed[0]?.attributes).toEqual({ label: 'Real' });
    });

    it('survives a missing close tag without infinite-looping', () => {
        // Pathological input from a malformed server payload should
        // produce whatever it can rather than crash the editor.
        const raw =
            '<!-- wp:navigation-submenu {"label":"Orphan"} -->\n' +
            '<!-- wp:navigation-link {"label":"Inside"} /-->';

        const parsed = parseNavigationContent(raw);

        expect(parsed).toHaveLength(1);
        expect(parsed[0]?.name).toBe('core/navigation-submenu');
        expect(parsed[0]?.innerBlocks[0]?.attributes).toEqual({ label: 'Inside' });
    });

    it('treats malformed JSON in a block comment as empty attributes', () => {
        const parsed = parseNavigationContent(
            '<!-- wp:navigation-link {label} /-->'
        );

        expect(parsed).toEqual([
            {
                name: 'core/navigation-link',
                attributes: {},
                innerBlocks: [],
            },
        ]);
    });

    it('preserves unicode and forward-slash characters from the JSON attrs', () => {
        const parsed = parseNavigationContent(
            '<!-- wp:navigation-link {"label":"Café","url":"https://example.com/path"} /-->'
        );

        expect(parsed[0]?.attributes).toEqual({
            label: 'Café',
            url: 'https://example.com/path',
        });
    });
});
