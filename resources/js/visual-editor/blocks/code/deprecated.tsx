/**
 * Code — deprecation chain.
 *
 * Upstream `@wordpress/block-library/src/code/` ships no `deprecated.js`
 * (v9.43.0). The block's save shape has been stable since pre-rich-text;
 * an empty chain preserves parity. The file exists so the auto-discovery
 * glob always finds the same module surface across forks.
 */

const deprecated: Array<Record<string, unknown>> = [];

export default deprecated;
