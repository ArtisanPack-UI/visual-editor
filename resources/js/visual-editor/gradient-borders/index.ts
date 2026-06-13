/**
 * Public entry point for the gradient border feature (#490).
 *
 * Mirrors the deliberate split the responsive feature uses: the
 * HOC + filter registrars (`with-gradient-border-control`,
 * `extend-supports`) are NOT re-exported here. They import
 * `@wordpress/blocks`, which trips JSON-import-attribute requirements
 * when host bundlers (or Vitest) walk this barrel transitively. The
 * editor bootstrap imports them directly from
 * `./with-gradient-border-control` and `./extend-supports` (or via
 * `./register` for the one-shot helper).
 *
 * What lives in the barrel: pure data types and the resolver/emitter
 * helpers that any host can call without booting Gutenberg.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.1.0
 */

export { resolveGradientBorder, referencedSlugs } from './resolver'
export { emitGradientBorderCss, DEFAULT_TRANSITION } from './emitter'
export type {
	GradientBorderAttributes,
	BorderSubtree,
	ResolvedGradientBorder,
} from './types'
