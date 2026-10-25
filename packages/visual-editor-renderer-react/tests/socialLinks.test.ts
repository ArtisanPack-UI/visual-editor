/**
 * Tests for the Social Icons context stamp (#1023).
 */

import { describe, expect, it } from 'vitest';

import { stampSocialLinksContext } from '../src/support/socialLinks';
import { makeBlock } from './helpers';

describe('stampSocialLinksContext', () => {
    it('hands a nested Social Icons block its settings', () => {
        const tree = [
            makeBlock('core/group', {}, [
                makeBlock('artisanpack/social-links', { openInNewTab: true, iconColorValue: '#fff' }, [
                    makeBlock('artisanpack/social-link', { service: 'x', url: 'https://x.com' }),
                    makeBlock('core/paragraph', { content: 'not an icon' }),
                ]),
            ]),
        ];

        const [group] = stampSocialLinksContext(tree);
        const [links] = group.innerBlocks;
        const [icon, paragraph] = links.innerBlocks;

        expect(icon.attributes._veSocialLinks).toEqual({ openInNewTab: true, showLabels: false });
        expect(paragraph.attributes._veSocialLinks).toBeUndefined();
    });

    it('overwrites a forged context and leaves stray icons alone', () => {
        const forged = { openInNewTab: true, showLabels: true };
        const [links, stray] = stampSocialLinksContext([
            makeBlock('artisanpack/social-links', {}, [
                makeBlock('artisanpack/social-link', { service: 'x', _veSocialLinks: forged }),
            ]),
            makeBlock('artisanpack/social-link', { service: 'x', _veSocialLinks: forged }),
        ]);

        expect(links.innerBlocks[0].attributes._veSocialLinks).toEqual({ openInNewTab: false, showLabels: false });
        expect(stray.attributes._veSocialLinks).toBe(forged);
    });
});
