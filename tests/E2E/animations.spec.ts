/**
 * E2E specification for the block-animations system (#489).
 *
 * The package does not yet ship Playwright as a runtime dependency
 * (it's not wired into the CI matrix in `release/1.1`). This file
 * documents the test plan in Playwright shape so the suite can be
 * activated as soon as the runner is added. Until then, the assertions
 * here serve as executable documentation of the runtime contract: an
 * engineer reading this knows exactly what behaviour the system must
 * exhibit end-to-end.
 *
 * To activate, install `@playwright/test`, add a `playwright.config.ts`
 * pointing at the dev app, and uncomment the imports at the top of
 * this file.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.1.0
 */

// import { expect, test } from '@playwright/test'
//
// test.describe( 'block animations', () => {
// 	test( 'entrance animation plays once on viewport entry', async ( { page } ) => {
// 		await page.goto( '/visual-editor/preview/animations-entrance' )
//
// 		const block = page.locator( '[data-ap-anim-entrance="fade-in-up"]' )
// 		await expect( block ).toHaveClass( /ap-anim-pre/ )
//
// 		await block.scrollIntoViewIfNeeded()
//
// 		await expect( block ).toHaveClass( /ap-anim-play/ )
// 	} )
//
// 	test( 'continuous animation persists across scroll', async ( { page } ) => {
// 		await page.goto( '/visual-editor/preview/animations-continuous' )
//
// 		const block = page.locator( '.continuous-target' )
// 		const animationName = await block.evaluate( ( el ) =>
// 			getComputedStyle( el ).animationName,
// 		)
// 		expect( animationName ).toBe( 'apPulse' )
// 	} )
//
// 	test( 'prefers-reduced-motion suppresses entrance + continuous', async ( {
// 		browser,
// 	} ) => {
// 		const context = await browser.newContext( { reducedMotion: 'reduce' } )
// 		const page = await context.newPage()
// 		await page.goto( '/visual-editor/preview/animations-entrance' )
//
// 		const block = page.locator( '[data-ap-anim-entrance="fade-in-up"]' )
// 		await expect( block ).toHaveClass( /ap-anim-play/ )
// 	} )
//
// 	test( 'a custom keyframe authored in Site Editor round-trips', async ( {
// 		page,
// 	} ) => {
// 		await page.goto( '/visual-editor/site-editor/styles/animations' )
// 		await page.getByRole( 'button', { name: '+ Add custom keyframe' } ).click()
// 		await page.getByLabel( 'Name' ).fill( 'confetti' )
// 		await page.getByRole( 'button', { name: 'Save' } ).click()
// 		await page.reload()
//
// 		await expect( page.getByLabel( 'Name' ) ).toHaveValue( 'confetti' )
// 	} )
//
// 	test( 'noscript fallback reveals blocks when JS is disabled', async ( {
// 		browser,
// 	} ) => {
// 		const context = await browser.newContext( { javaScriptEnabled: false } )
// 		const page = await context.newPage()
// 		await page.goto( '/visual-editor/preview/animations-entrance' )
//
// 		const block = page.locator( '[data-ap-anim-entrance="fade-in-up"]' )
// 		const opacity = await block.evaluate( ( el ) =>
// 			getComputedStyle( el ).opacity,
// 		)
// 		expect( opacity ).toBe( '1' )
// 	} )
// } )

export {}
