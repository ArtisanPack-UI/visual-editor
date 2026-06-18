/**
 * Public entry point for the box-shadow feature (#607).
 *
 * Mirrors the deliberate split in `gradient-borders/index.ts`: the
 * HOC + filter registrars are NOT re-exported here. They import
 * `@wordpress/blocks`, which trips JSON-import-attribute requirements
 * when host bundlers (or Vitest) walk this barrel transitively.
 *
 * What lives in the barrel: pure data types and the resolver/emitter
 * helpers any host can call without booting Gutenberg.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.2.0
 */

export { resolveBoxShadow, referencedSlugs } from './resolver'
export { emitBoxShadowCss, DEFAULT_TRANSITION } from './emitter'
export type {
	BoxShadowAttributes,
	ShadowSubtree,
	ResolvedBoxShadow,
	ResolvedShadowLayer,
} from './types'
