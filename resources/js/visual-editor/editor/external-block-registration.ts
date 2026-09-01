/**
 * Client escape hatch for third-party block registration (#766).
 *
 * The server-driven path ({@see registerServerRenderedBlocks}) covers dynamic
 * blocks that only need attribute controls + a server preview. A block that
 * needs a bespoke editor experience — a custom canvas, rich inspector logic —
 * needs a real client `edit` component, which this seam accepts at runtime:
 * a host page calls `window.ApVisualEditor.registerBlockType(name, settings)`
 * or `registerBlocks(modules)` (wired in `main.tsx`), building `edit`/`save`
 * against the singletons exposed on `window.ApVisualEditor.libs`.
 *
 * Registration is order-independent. Calls made **before** the editor boots
 * are queued and flushed by {@see markExternalBlocksReady} (invoked once the
 * build-time blocks have registered); calls made **after** run immediately.
 * Re-registering a name is a no-op, mirroring {@see registerCustomBlocks}.
 */

import { getBlockType, registerBlockType, type BlockConfiguration } from '@wordpress/blocks';

import { registerCustomBlocks, type CustomBlockModule } from './custom-blocks';

let ready = false;
const queue: Array<() => void> = [];
const registered = new Set<string>();

function runOrQueue(task: () => void): void {
    if (ready) {
        task();
        return;
    }

    queue.push(task);
}

function registerOne(name: string, settings: BlockConfiguration): void {
    if (typeof name !== 'string' || name === '') {
        console.warn('visual-editor: registerBlockType called without a block name.');
        return;
    }

    // Consult `@wordpress/blocks` itself, not just the local set, so a name
    // already registered through another path — the server-block pass or a
    // built-in block — is a clean skip rather than a caught throw (#766).
    if (registered.has(name) || undefined !== getBlockType(name)) {
        registered.add(name);
        return;
    }

    try {
        registerBlockType(name, settings);
        registered.add(name);
    } catch (error) {
        console.error(
            `visual-editor: failed to register external block "${name}".`,
            error
        );
    }
}

/**
 * Register a single block from a host, in the low-level
 * `@wordpress/blocks.registerBlockType` shape (the host supplies `edit`, and
 * `save` for static blocks).
 */
export function registerExternalBlockType(
    name: string,
    settings: BlockConfiguration
): void {
    runOrQueue(() => registerOne(name, settings));
}

/**
 * Register a batch of host blocks in this package's
 * {@link CustomBlockModule} shape (a parsed `block.json` in `metadata` plus
 * `edit`/optional `save`). Deduping is handled by {@see registerCustomBlocks}.
 */
export function registerExternalBlocks(
    modules: ReadonlyArray<CustomBlockModule>
): void {
    runOrQueue(() => {
        registerCustomBlocks(modules);
    });
}

/**
 * Flush the pre-boot queue and switch to immediate registration for any later
 * calls. Idempotent — safe to call from both editor boot paths and across HMR.
 */
export function markExternalBlocksReady(): void {
    ready = true;

    while (queue.length > 0) {
        const task = queue.shift();

        if (task) {
            task();
        }
    }
}

/**
 * Test-only: reset queue + ready + dedupe state.
 *
 * @internal
 */
export function __resetExternalBlockRegistration(): void {
    ready = false;
    queue.length = 0;
    registered.clear();
}
