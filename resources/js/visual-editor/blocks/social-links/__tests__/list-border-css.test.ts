/**
 * #1121 — the Social Icons `<ul>` carries the icon border settings as
 * `--ap-social-links-border-*` properties. The block library's
 * `html :where([style*="border-color"])` / `[style*="border-width"]` rules
 * match those names and give the list a solid border of its own. Both the
 * canvas stylesheet and the Blade front-end copy must reset it, on every
 * list that carries one of those properties.
 */

import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

import { describe, expect, it } from 'vitest';

import { socialLinksClassName, socialLinksStyle } from '../utils';

function readCss(relative: string): string {
    return readFileSync(fileURLToPath(new URL(relative, import.meta.url)), 'utf8');
}

const stylesheets = {
    canvas: readCss('../social-links.css'),
    frontend: readCss('../../../../../../packages/visual-editor-renderer-blade/resources/assets/frontend/social-icons.css'),
};

/** Selector of the rule that resets the list's own border style. */
function resetSelector(css: string): string | null {
    const match = /([^{}\n]+)\{\s*border-style:\s*none;\s*\}/.exec(css);

    return match ? match[1].trim() : null;
}

function list(className: string, style: Record<string, string>): HTMLUListElement {
    const el = document.createElement('ul');
    el.className = `wp-block-artisanpack-social-links ${className}`;
    el.setAttribute(
        'style',
        Object.entries(style)
            .map(([property, value]) => `${property}: ${value}`)
            .join('; '),
    );
    document.body.appendChild(el);

    return el;
}

describe.each(Object.entries(stylesheets))('%s stylesheet', (_name, css) => {
    const selector = resetSelector(css);

    it('resets the list border style', () => {
        expect(selector).not.toBeNull();
    });

    it.each([
        ['an icon border', { iconBorderColorValue: '#efefef', iconBorderWidth: '1px', iconBorderStyle: 'solid' }],
        ['only an icon border width', { iconBorderWidth: '2px' }],
        ['only a hover border colour', { iconHoverBorderColorValue: '#111111' }],
    ])('covers every list whose inline style the block-library rule matches: %s', (_label, attributes) => {
        const el = list(socialLinksClassName(attributes), socialLinksStyle(attributes));

        // The block-library rule fires on this list…
        expect(el.matches('[style*="border-color"], [style*="border-width"]')).toBe(true);
        // …and so does the reset.
        expect(el.matches(selector as string)).toBe(true);
    });

    it('leaves lists without border properties alone', () => {
        const attributes = { iconColorValue: '#ffffff', iconBorderRadius: '9999px' };
        const el = list(socialLinksClassName(attributes), socialLinksStyle(attributes));

        expect(el.matches(selector as string)).toBe(false);
    });
});

describe('the list border reset', () => {
    it('is identical in the canvas and front-end stylesheets', () => {
        expect(resetSelector(stylesheets.frontend)).toBe(resetSelector(stylesheets.canvas));
    });
});
