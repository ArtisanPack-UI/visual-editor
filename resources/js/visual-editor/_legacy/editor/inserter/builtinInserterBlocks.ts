/**
 * No-op stub retained for backward compatibility.
 *
 * Block metadata and factories are now registered via `registerBlockType()`
 * in each block's `index.ts` file. The inserter derives its list from the
 * unified block registry. This function is called by `loadInserterBlocks()`
 * but no longer needs to do anything — `registerCoreBlocks()` handles
 * everything.
 */
export function registerBuiltinInserterBlocks(): void {
    // Intentionally empty — registration happens in registerCoreBlocks()
}
