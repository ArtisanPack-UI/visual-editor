/**
 * `registerForkedCoreBlocks()` — idempotent registration of the upstream
 * `core/navigation` family plus the `core/navigation` animations opt-in
 * (#808 follow-up).
 *
 * Uses the real `@wordpress/blocks` registry. Each upstream `init` is
 * replaced with the same unguarded `registerBlockType` call
 * `@wordpress/block-library`'s `initBlock` makes (the real entrypoints
 * pull the whole navigation edit surface, which cannot load under jsdom),
 * so a missing guard surfaces as the upstream "already registered" error.
 *
 * @since 1.12.0
 */

import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest';

vi.mock('@wordpress/blocks', async () =>
    (await import('./wp-blocks-cjs')).requireCjs('@wordpress/blocks'),
);
vi.mock('@wordpress/hooks', async () =>
    (await import('./wp-blocks-cjs')).requireCjs('@wordpress/hooks'),
);
vi.mock('@wordpress/block-library/build-module/navigation/index.mjs', async () =>
    (await import('./wp-blocks-cjs')).fakeUpstreamInit('core/navigation'),
);
vi.mock('@wordpress/block-library/build-module/navigation-link/index.mjs', async () =>
    (await import('./wp-blocks-cjs')).fakeUpstreamInit('core/navigation-link'),
);
vi.mock('@wordpress/block-library/build-module/navigation-submenu/index.mjs', async () =>
    (await import('./wp-blocks-cjs')).fakeUpstreamInit('core/navigation-submenu'),
);
vi.mock('@wordpress/block-library/build-module/page-list/index.mjs', async () =>
    (await import('./wp-blocks-cjs')).fakeUpstreamInit('core/page-list'),
);
vi.mock('@wordpress/block-library/build-module/page-list-item/index.mjs', async () =>
    (await import('./wp-blocks-cjs')).fakeUpstreamInit('core/page-list-item'),
);
vi.mock('@wordpress/block-library/build-module/home-link/index.mjs', async () =>
    (await import('./wp-blocks-cjs')).fakeUpstreamInit('core/home-link'),
);
vi.mock('@wordpress/block-library/build-module/loginout/index.mjs', async () =>
    (await import('./wp-blocks-cjs')).fakeUpstreamInit('core/loginout'),
);
import { getBlockType } from '@wordpress/blocks';

import { registerAnimationsAttribute } from '../../../animations/register-attribute';
import {
    addForkedCoreAnimationsSupport,
    registerForkedCoreBlocks,
} from '../../../editor/register-forked-cores';

const FAMILY = [
    'core/navigation',
    'core/navigation-link',
    'core/navigation-submenu',
    'core/page-list',
    'core/page-list-item',
    'core/home-link',
    'core/loginout',
];

describe('registerForkedCoreBlocks', () => {
    beforeAll(() => {
        registerAnimationsAttribute();
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('registers the whole core/navigation family', () => {
        registerForkedCoreBlocks();

        for (const name of FAMILY) {
            expect(getBlockType(name), name).toBeDefined();
        }
    });

    it('is idempotent — a second boot neither throws nor logs duplicate registration', () => {
        registerForkedCoreBlocks();

        const errorSpy = vi.spyOn(console, 'error').mockImplementation(() => {});
        const warnSpy = vi.spyOn(console, 'warn').mockImplementation(() => {});

        expect(() => registerForkedCoreBlocks()).not.toThrow();
        expect(() => registerForkedCoreBlocks()).not.toThrow();

        const logged = [...errorSpy.mock.calls, ...warnSpy.mock.calls]
            .map((args) => String(args[0]))
            .filter((message) => message.includes('already registered'));

        expect(logged).toEqual([]);
    });

    it('opts core/navigation into artisanpack animations', () => {
        registerForkedCoreBlocks();

        const navigation = getBlockType('core/navigation');

        expect(navigation?.supports?.artisanpackAnimations).toBe(true);
        expect(navigation?.attributes).toHaveProperty('artisanpackAnimations');
    });

    it('leaves other blocks and explicit opt-outs untouched', () => {
        const other = { supports: { html: false } };
        expect(addForkedCoreAnimationsSupport(other, 'core/navigation-link')).toBe(other);

        const optedOut = { supports: { artisanpackAnimations: false } };
        expect(addForkedCoreAnimationsSupport(optedOut, 'core/navigation')).toBe(optedOut);
    });
});
