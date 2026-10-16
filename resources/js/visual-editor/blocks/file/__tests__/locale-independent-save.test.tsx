/**
 * The File block's saved markup must not depend on the editor's locale
 * (M-12): a translated aria-label in `save()` would make File blocks
 * saved in one language invalid in another.
 *
 * @since 1.15.0
 */

import { afterEach, describe, expect, it, vi } from 'vitest';
import { renderToStaticMarkup } from 'react-dom/server';
import { resetLocaleData, setLocaleData } from '@wordpress/i18n';
import type { ReactElement } from 'react';

vi.mock('@wordpress/block-editor', () => ({
    useBlockProps: Object.assign(
        (props?: Record<string, unknown>) => ({ ...props }),
        { save: (props?: Record<string, unknown>) => ({ ...props }) }
    ),
    RichText: Object.assign(() => null, {
        isEmpty: (value?: string) => !value || value === '',
        Content: ({ value, tagName }: { value?: string; tagName?: string }) => {
            const Tag = (tagName ?? 'span') as keyof JSX.IntrinsicElements;
            return <Tag dangerouslySetInnerHTML={{ __html: value ?? '' }} />;
        },
    }),
    __experimentalGetElementClassName: (name: string) => `wp-element-${name}`,
}));

import { TEXT_DOMAIN } from '../../../vendor/i18n';
import deprecated from '../deprecated';
import save from '../save';

type SaveFn = (props: { attributes: Record<string, unknown> }) => ReactElement | null;

const SAVES: Array<[string, SaveFn]> = [
    ['save', save as unknown as SaveFn],
    ...deprecated.map((entry, i): [string, SaveFn] => [`deprecated[${i}]`, entry.save as unknown as SaveFn]),
];

const ATTRIBUTE_SETS = {
    named: {
        href: 'https://example.com/report.pdf',
        fileName: 'Report',
        fileId: 'wp-block-file--media-1',
        textLinkHref: 'https://example.com/report.pdf',
        showDownloadButton: true,
        downloadButtonText: 'Download',
        displayPreview: true,
        previewHeight: 600,
    },
    unnamed: {
        href: 'https://example.com/report.pdf',
        fileName: '',
        displayPreview: true,
        previewHeight: 600,
    },
};

const TRANSLATIONS = {
    '': { domain: TEXT_DOMAIN, lang: 'xx' },
    'PDF embed': ['XX PDF embed'],
    'Embed of %s.': ['XX Embed of %s.'],
};

function renderAll(): string[] {
    return SAVES.flatMap(([, saveFn]) =>
        Object.values(ATTRIBUTE_SETS).map((attributes) => {
            const element = saveFn({ attributes });

            return element ? renderToStaticMarkup(element) : '';
        })
    );
}

afterEach(() => {
    resetLocaleData(undefined, TEXT_DOMAIN);
    resetLocaleData();
});

describe('file save is locale-independent', () => {
    it('serializes the same markup with translations loaded', () => {
        const english = renderAll();

        expect(english.join('')).toContain('aria-label="Embed of Report."');
        expect(english.join('')).toContain('aria-label="PDF embed"');

        setLocaleData(TRANSLATIONS, TEXT_DOMAIN);
        setLocaleData({ ...TRANSLATIONS, '': { lang: 'xx' } });

        expect(renderAll()).toEqual(english);
    });
});
