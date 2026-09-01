/**
 * Tests for the client block escape hatch (#766): the pre-boot queue, the
 * flush on `markExternalBlocksReady`, immediate registration afterwards, and
 * by-name dedupe.
 */

import { describe, it, expect, beforeEach, vi } from 'vitest';

const { registerBlockType, registerCustomBlocks } = vi.hoisted(() => ({
    registerBlockType: vi.fn(),
    registerCustomBlocks: vi.fn(
        (modules: ReadonlyArray<{ metadata: { name: string } }>) =>
            modules.map((module) => module.metadata.name)
    ),
}));

vi.mock('@wordpress/blocks', () => ({
    registerBlockType,
}));

vi.mock('../custom-blocks', () => ({
    registerCustomBlocks,
}));

import {
    __resetExternalBlockRegistration,
    markExternalBlocksReady,
    registerExternalBlocks,
    registerExternalBlockType,
} from '../external-block-registration';

beforeEach(() => {
    registerBlockType.mockClear();
    registerCustomBlocks.mockClear();
    __resetExternalBlockRegistration();
});

const settings = { title: 'X', edit: () => null } as never;

describe('pre-boot queueing', () => {
    it('queues registerBlockType calls until the editor is ready', () => {
        registerExternalBlockType('acme/one', settings);

        expect(registerBlockType).not.toHaveBeenCalled();

        markExternalBlocksReady();

        expect(registerBlockType).toHaveBeenCalledTimes(1);
        expect(registerBlockType).toHaveBeenCalledWith('acme/one', settings);
    });

    it('queues registerBlocks (module form) until ready', () => {
        registerExternalBlocks([{ metadata: { name: 'acme/mod' }, edit: () => null } as never]);

        expect(registerCustomBlocks).not.toHaveBeenCalled();

        markExternalBlocksReady();

        expect(registerCustomBlocks).toHaveBeenCalledTimes(1);
    });

    it('preserves registration order across the flush', () => {
        registerExternalBlockType('acme/a', settings);
        registerExternalBlockType('acme/b', settings);
        markExternalBlocksReady();

        expect(registerBlockType.mock.calls.map((call) => call[0])).toEqual(['acme/a', 'acme/b']);
    });
});

describe('post-boot registration', () => {
    it('registers immediately once ready', () => {
        markExternalBlocksReady();
        registerExternalBlockType('acme/late', settings);

        expect(registerBlockType).toHaveBeenCalledTimes(1);
        expect(registerBlockType).toHaveBeenCalledWith('acme/late', settings);
    });
});

describe('dedupe + guards', () => {
    it('registers a repeated name only once', () => {
        markExternalBlocksReady();
        registerExternalBlockType('acme/dupe', settings);
        registerExternalBlockType('acme/dupe', settings);

        expect(registerBlockType).toHaveBeenCalledTimes(1);
    });

    it('skips a call with no block name', () => {
        vi.spyOn(console, 'warn').mockImplementation(() => undefined);
        markExternalBlocksReady();
        registerExternalBlockType('', settings);

        expect(registerBlockType).not.toHaveBeenCalled();
    });

    it('swallows a registerBlockType throw so one bad block cannot break boot', () => {
        vi.spyOn(console, 'error').mockImplementation(() => undefined);
        registerBlockType.mockImplementationOnce(() => {
            throw new Error('bad block');
        });
        markExternalBlocksReady();

        expect(() => registerExternalBlockType('acme/throws', settings)).not.toThrow();
        // The name was not recorded, so a corrected retry can still register.
        registerBlockType.mockImplementationOnce(() => undefined);
        registerExternalBlockType('acme/throws', settings);
        expect(registerBlockType).toHaveBeenCalledTimes(2);
    });
});
