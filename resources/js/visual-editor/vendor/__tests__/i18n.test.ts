import { beforeEach, describe, expect, it } from 'vitest';
import { getLocaleData } from '@wordpress/i18n';

import { bootI18n, TEXT_DOMAIN, __resetI18nForTests } from '../i18n';

describe('i18n bootstrap', () => {
    beforeEach(() => {
        __resetI18nForTests();
    });

    it('registers the artisanpack-visual-editor text domain', () => {
        bootI18n();

        const data = getLocaleData(TEXT_DOMAIN);
        expect(data).toBeDefined();
        const header = data?.[''] as
            | { domain?: string; lang?: string }
            | undefined;
        expect(header).toMatchObject({
            domain: TEXT_DOMAIN,
            lang: 'en',
        });
    });

    it('is idempotent across repeat calls', () => {
        bootI18n();
        bootI18n();

        const data = getLocaleData(TEXT_DOMAIN);
        const header = data?.[''] as { domain?: string } | undefined;
        expect(header?.domain).toBe(TEXT_DOMAIN);
    });
});
