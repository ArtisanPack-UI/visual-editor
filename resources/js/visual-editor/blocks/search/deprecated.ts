/**
 * Search — deprecations.
 *
 * `@wordpress/block-library/src/search` (v9.43.0) ships no `deprecated.js`
 * — the block has never had a saved-markup change to migrate (it is
 * rendered outside `save`). The empty array is exported for symmetry with
 * the other forks and so the deprecation-chain test has a stable target.
 */

const deprecated: unknown[] = [];

export default deprecated;
