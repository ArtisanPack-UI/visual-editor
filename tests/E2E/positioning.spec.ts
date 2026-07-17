/**
 * E2E specification for CSS position support (#647).
 *
 * The package does not yet ship Playwright as a runtime dependency
 * (it's not wired into the CI matrix in `release/1.x`). This file
 * documents the test plan in Playwright shape so the suite can be
 * activated as soon as the runner is added — matches the existing
 * animations.spec.ts precedent.
 *
 * To activate, install `@playwright/test`, add a `playwright.config.ts`
 * pointing at the dev app, and uncomment the imports at the top of
 * this file.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.4.0
 */

// import { expect, test } from '@playwright/test'
//
// test.describe( 'CSS position support (#640)', () => {
// 	test( 'artisanpack/group with position: sticky sticks on canvas scroll', async ( { page } ) => {
// 		// Renders a group block inside a tall column, with the group
// 		// configured as position: sticky + top: 0. Scrolling the
// 		// canvas should pin the group at the top of its scroll
// 		// container until the container scrolls past.
// 		await page.goto( '/visual-editor/preview/position-sticky-group' )
//
// 		const group   = page.locator( '.wp-block-group.ve-pos-fixture1' )
// 		const scroll  = page.locator( '[data-scroll-container]' )
//
// 		// Baseline: group starts at its natural offset in the column.
// 		const initialTop = await group.evaluate( ( el ) => el.getBoundingClientRect().top )
//
// 		await scroll.evaluate( ( el ) => { el.scrollTop = 400 } )
//
// 		const scrolledTop = await group.evaluate( ( el ) => el.getBoundingClientRect().top )
//
// 		// Sticky: bounding-rect top stays at (or near) the container's
// 		// top edge instead of scrolling with the column content.
// 		expect( scrolledTop ).toBeLessThanOrEqual( initialTop )
// 		expect( scrolledTop ).toBeGreaterThanOrEqual( -1 )
// 	} )
//
// 	test( 'artisanpack/cover with position: absolute + positioned parent respects offsets', async ( { page } ) => {
// 		// Renders a cover block inside an explicitly positioned
// 		// (relative) group. The cover has position: absolute + top:
// 		// 20px + left: 40px. It should sit at those offsets relative
// 		// to the group, NOT the viewport.
// 		await page.goto( '/visual-editor/preview/position-absolute-cover' )
//
// 		const parent = page.locator( '.wp-block-group.ve-pos-parent1' )
// 		const cover  = page.locator( '.wp-block-cover.ve-pos-cover1' )
//
// 		const parentBox = await parent.boundingBox()
// 		const coverBox  = await cover.boundingBox()
//
// 		expect( parentBox ).not.toBeNull()
// 		expect( coverBox ).not.toBeNull()
//
// 		// Cover's top edge is 20px below the parent's top edge, its
// 		// left edge is 40px right of the parent's left edge.
// 		expect( Math.round( coverBox!.y - parentBox!.y ) ).toBe( 20 )
// 		expect( Math.round( coverBox!.x - parentBox!.x ) ).toBe( 40 )
// 	} )
//
// 	test( 'artisanpack/cover with position: absolute + STATIC parent surfaces the inspector warning', async ( { page } ) => {
// 		// Same absolute cover, but the parent group is not positioned.
// 		// The Position panel should render a warning notice; the
// 		// block still applies its position (relative to the nearest
// 		// positioned ancestor, often the page).
// 		await page.goto( '/visual-editor/edit/position-absolute-no-parent' )
//
// 		await page.click( '.wp-block-cover.ve-pos-cover2' )
//
// 		const warning = page.locator( '.ap-position__ancestor-warning' )
// 		await expect( warning ).toBeVisible()
// 		await expect( warning ).toContainText( /nearest positioned ancestor/i )
// 	} )
//
// 	test( 'per-breakpoint values render distinct CSS at each viewport width', async ( { page, browser } ) => {
// 		// A `artisanpack/group` with base position: relative + top:
// 		// 10px, md override to zIndex: 3, lg override to value:
// 		// sticky + top: 0. Each viewport should surface the merged
// 		// layer at that breakpoint.
// 		await page.goto( '/visual-editor/preview/position-per-breakpoint' )
//
// 		const group = page.locator( '.wp-block-group.ve-pos-per-bp' )
//
// 		// Mobile-first: base rule applies below md.
// 		await page.setViewportSize( { width: 640, height: 800 } )
// 		let position = await group.evaluate( ( el ) => getComputedStyle( el ).position )
// 		let top      = await group.evaluate( ( el ) => getComputedStyle( el ).top )
// 		expect( position ).toBe( 'relative' )
// 		expect( top ).toBe( '10px' )
//
// 		// md: inherits value=relative + top from base, adds zIndex.
// 		await page.setViewportSize( { width: 900, height: 800 } )
// 		const zIndex = await group.evaluate( ( el ) => getComputedStyle( el ).zIndex )
// 		expect( zIndex ).toBe( '3' )
//
// 		// lg: overrides value + top.
// 		await page.setViewportSize( { width: 1200, height: 800 } )
// 		position = await group.evaluate( ( el ) => getComputedStyle( el ).position )
// 		top      = await group.evaluate( ( el ) => getComputedStyle( el ).top )
// 		expect( position ).toBe( 'sticky' )
// 		expect( top ).toBe( '0px' )
// 	} )
//
// 	test( 'legacy Gutenberg sticky (bare string) still renders on frontend', async ( { page } ) => {
// 		// A block whose saved attributes ship style.position="sticky"
// 		// (Gutenberg native), NOT the structured object. The resolver
// 		// widens the string; the emitter emits the same sticky rule.
// 		await page.goto( '/visual-editor/preview/position-legacy-sticky' )
//
// 		const group = page.locator( '.wp-block-group.ve-pos-legacy' )
// 		const position = await group.evaluate( ( el ) => getComputedStyle( el ).position )
// 		expect( position ).toBe( 'sticky' )
// 	} )
// } )
