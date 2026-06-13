/**
 * Visual regression contract for gradient borders (#490).
 *
 * Acceptance criterion AC-8 of #490 calls for Playwright visual
 * regression coverage at border-radius values 0 / 8 / 12 / 24 / 9999
 * across all three gradient types (linear, radial, conic). This file
 * is the test contract those snapshots have to satisfy — every entry
 * in `MATRIX` becomes one screenshot baseline once Playwright is wired
 * up.
 *
 * The Playwright runner itself is not yet installed in this package
 * (no `@playwright/test` dependency, no `playwright.config.ts`). Wiring
 * the runner — config file, baseline screenshots, CI job, fixture
 * blocks — is tracked separately. Until then this file documents the
 * matrix so the implementation here doesn't drift from the contract.
 *
 * When Playwright lands:
 *   - Rename to `border-gradients.spec.ts`, remove the skip guard.
 *   - Replace `serveBlockFixture` with the real harness call (likely
 *     `await mountBlock(page, 'fixtures/gradient-border.json', vars)`
 *     or similar).
 *   - Capture baselines via `--update-snapshots`.
 *
 * @since 1.1.0
 */

interface Fixture {
	name: string
	gradient: string
	radius: string | Record<string, string>
}

interface MatrixEntry extends Fixture {
	label: string
}

// All combinations the snapshot suite must cover. 3 gradient types × 5
// radius values = 15 baselines; per-corner is a 16th to verify the
// per-side path holds up under mask clipping.
const GRADIENT_TYPES: Array<Pick<Fixture, 'name' | 'gradient'>> = [
	{ name: 'linear',  gradient: 'linear-gradient(135deg, #ff0080, #7928ca)' },
	{ name: 'radial',  gradient: 'radial-gradient(circle, #ff0080, #7928ca)' },
	{ name: 'conic',   gradient: 'conic-gradient(from 0deg, #ff0080, #7928ca, #ff0080)' },
]

const RADII = [ '0', '8px', '12px', '24px', '9999px' ]

const MATRIX: MatrixEntry[] = []
for ( const gradient of GRADIENT_TYPES ) {
	for ( const radius of RADII ) {
		MATRIX.push( {
			label:    `${ gradient.name } @ radius ${ radius }`,
			name:     gradient.name,
			gradient: gradient.gradient,
			radius,
		} )
	}
}

// Per-corner asymmetry — covers the corner-by-corner emission path
// the resolver takes for the object-shape radius.
MATRIX.push( {
	label:    'linear @ per-corner radius',
	name:     'linear',
	gradient: 'linear-gradient(135deg, #ff0080, #7928ca)',
	radius:   {
		topLeft:     '4px',
		topRight:    '8px',
		bottomLeft:  '12px',
		bottomRight: '24px',
	},
} )

// Sentinel: Playwright not yet wired. The runner / config / fixture
// harness lands in a follow-up issue; until then the matrix above
// stays in sync with the resolver + emitter so the snapshot work can
// pick it up as the contract.
const PLAYWRIGHT_AVAILABLE = false

if ( PLAYWRIGHT_AVAILABLE ) {
	// @ts-expect-error — placeholder for the real runner import.
	const { test, expect } = require( '@playwright/test' )

	for ( const entry of MATRIX ) {
		test( `gradient border: ${ entry.label }`, async ( { page }: { page: unknown } ): Promise<void> => {
			void page
			void entry
			void expect
			// const url = await serveBlockFixture( entry )
			// await page.goto( url )
			// await expect( page.locator( '[data-test=block]' ) ).toHaveScreenshot( `${ entry.name }-${ entry.radius }.png` )
		} )
	}
}

export { MATRIX }
