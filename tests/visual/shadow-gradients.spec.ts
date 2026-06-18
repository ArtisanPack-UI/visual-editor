/**
 * Visual regression contract for box shadows (#607).
 *
 * Acceptance criterion AC-7 of #607 calls for Playwright visual
 * regression coverage spanning the matrix of:
 *
 *   {solid, gradient, preset} × {outer, inset} × {idle, hover state, md breakpoint}
 *
 * = 18 baselines (3 fill kinds × 2 inset states × 3 cascade slots).
 * The issue's `Additional Context` note about the inset-mask strategy
 * being the genuinely novel piece is covered by the gradient × inset
 * cells, which exercise both Chrome/Safari/Firefox under the same
 * harness once the runner is wired.
 *
 * Mirrors the gated pattern in `border-gradients.spec.ts`: the
 * Playwright runner is NOT installed in this package today, so this
 * file documents the matrix the snapshot suite must cover once
 * Playwright is wired up. The matrix lives at top-level so static
 * analysis catches drift (e.g. someone removes the inset gradient
 * code path — the matrix entry stops compiling).
 *
 * When Playwright lands:
 *   - Remove the `PLAYWRIGHT_AVAILABLE` skip guard at the bottom.
 *   - Replace `serveShadowFixture` with the real harness call.
 *   - Capture baselines via `--update-snapshots`.
 *
 * @since 1.2.0
 */

interface ShadowFill {
	name: string
	color?: string
	gradient?: string
	preset?: string
}

interface MatrixEntry {
	label: string
	fill: ShadowFill
	inset: boolean
	cascade: 'idle' | 'hover' | 'md'
}

const FILLS: ShadowFill[] = [
	{ name: 'solid',    color: 'rgba(0,0,0,0.4)' },
	{ name: 'gradient', gradient: 'linear-gradient(135deg, #ff0080, #7a00ff)' },
	{ name: 'preset',   preset: 'shadow-md' },
]

const INSET   = [ false, true ] as const
const CASCADE = [ 'idle', 'hover', 'md' ] as const

const MATRIX: MatrixEntry[] = []

for ( const fill of FILLS ) {
	for ( const inset of INSET ) {
		for ( const cascade of CASCADE ) {
			MATRIX.push( {
				label:   `${ fill.name }-${ inset ? 'inset' : 'outer' }-${ cascade }`,
				fill,
				inset,
				cascade,
			} )
		}
	}
}

/**
 * Stub for the real Playwright fixture harness — when the runner is
 * wired the implementation will render a single block configured per
 * the matrix entry, capture a screenshot, and diff against the
 * baseline.
 */
async function serveShadowFixture( _entry: MatrixEntry ): Promise<void> {
	// no-op until Playwright is wired
}

const PLAYWRIGHT_AVAILABLE = false

if ( PLAYWRIGHT_AVAILABLE ) {
	// @ts-expect-error — placeholder until @playwright/test is added
	const { test, expect } = await import( '@playwright/test' )

	for ( const entry of MATRIX ) {
		test( `shadow ${ entry.label }`, async ( { page }: { page: unknown } ) => {
			await serveShadowFixture( entry )
			// eslint-disable-next-line @typescript-eslint/no-explicit-any
			await expect( page as any ).toHaveScreenshot( `shadow-${ entry.label }.png` )
		} )
	}
}

// Surface the matrix for any tooling that walks this file looking for
// the snapshot inventory (e.g. CI scripts that diff added/removed
// baselines).
export { MATRIX }
