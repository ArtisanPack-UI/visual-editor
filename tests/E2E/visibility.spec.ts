/**
 * E2E specification for Block Visibility (#491 · #492 · #493).
 *
 * The package does not yet ship Playwright as a runtime dependency
 * (matches the same status quo as `animations.spec.ts` and
 * `positioning.spec.ts`). This file documents the test plan in
 * Playwright shape so the suite can be activated as soon as the
 * runner is added. Until then, the assertions here serve as
 * executable documentation of the runtime contract.
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
// test.describe( 'block visibility — contextual rules', () => {
//     test( 'Hide toggle removes markup entirely', async ( { page } ) => {
//         await page.goto( '/visual-editor/preview/visibility-hide' )
//         await expect( page.locator( '[data-ap-vis-target="hidden"]' ) ).toHaveCount( 0 )
//         await expect( page.locator( '[data-ap-vis-target="visible"]' ) ).toBeVisible()
//     } )
//
//     test( 'Screen size rule emits CSS at the right breakpoints', async ( { page } ) => {
//         await page.setViewportSize( { width: 1200, height: 800 } )
//         await page.goto( '/visual-editor/preview/visibility-screen-size' )
//         const hiddenAboveLg = page.locator( '[data-ap-vis-target="hide-above-lg"]' )
//         await expect( hiddenAboveLg ).not.toBeVisible()
//
//         await page.setViewportSize( { width: 375, height: 800 } )
//         await expect( hiddenAboveLg ).toBeVisible()
//     } )
//
//     test( 'Query string rule shows a block only when the flag is set', async ( { page } ) => {
//         await page.goto( '/visual-editor/preview/visibility-query-string' )
//         await expect( page.locator( '[data-ap-vis-target="promo"]' ) ).toHaveCount( 0 )
//
//         await page.goto( '/visual-editor/preview/visibility-query-string?utm_source=newsletter' )
//         await expect( page.locator( '[data-ap-vis-target="promo"]' ) ).toBeVisible()
//     } )
//
//     test( 'Referrer rule respects *.example.com wildcards', async ( { page, context } ) => {
//         await context.setExtraHTTPHeaders( { Referer: 'https://sub.example.com/foo' } )
//         await page.goto( '/visual-editor/preview/visibility-referrer' )
//         await expect( page.locator( '[data-ap-vis-target="from-example"]' ) ).toBeVisible()
//     } )
//
//     test( 'Browser/OS/Device rule combines families with AND', async ( { browser } ) => {
//         const context = await browser.newContext( { userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1' } )
//         const page = await context.newPage()
//         await page.goto( '/visual-editor/preview/visibility-browser' )
//         await expect( page.locator( '[data-ap-vis-target="ios-safari-mobile"]' ) ).toBeVisible()
//     } )
// } )
//
// test.describe( 'block visibility — user & auth', () => {
//     test( 'Logged-out block is hidden for authenticated visitors', async ( { page } ) => {
//         await page.goto( '/login' )
//         await page.fill( '[name="email"]', 'ada@example.com' )
//         await page.fill( '[name="password"]', 'secret' )
//         await page.click( 'button[type="submit"]' )
//         await page.goto( '/visual-editor/preview/visibility-auth' )
//         await expect( page.locator( '[data-ap-vis-target="signup-cta"]' ) ).toHaveCount( 0 )
//     } )
//
//     test( 'Role-restricted block is hidden without the role', async ( { page } ) => {
//         await page.goto( '/visual-editor/preview/visibility-role-admin' )
//         await expect( page.locator( '[data-ap-vis-target="admin-only"]' ) ).toHaveCount( 0 )
//     } )
//
//     test( 'Specific user rule matches by email', async ( { page } ) => {
//         await page.goto( '/login' )
//         await page.fill( '[name="email"]', 'ada@example.com' )
//         await page.fill( '[name="password"]', 'secret' )
//         await page.click( 'button[type="submit"]' )
//         await page.goto( '/visual-editor/preview/visibility-specific-user' )
//         await expect( page.locator( '[data-ap-vis-target="ada-only"]' ) ).toBeVisible()
//     } )
// } )
//
// test.describe( 'block visibility — scheduling', () => {
//     test( 'Date/time window respects a per-rule timezone override', async ( { page } ) => {
//         await page.goto( '/visual-editor/preview/visibility-window?_veTime=2026-11-25T10:00:00-06:00' )
//         await expect( page.locator( '[data-ap-vis-target="black-friday"]' ) ).toBeVisible()
//     } )
//
//     test( 'Recurring weekly window is active at the right wall-clock time', async ( { page } ) => {
//         await page.goto( '/visual-editor/preview/visibility-recurring?_veTime=2026-07-15T12:30:00Z' )
//         await expect( page.locator( '[data-ap-vis-target="weds-noon"]' ) ).toBeVisible()
//     } )
//
//     test( 'DST fall-back — a "10:00 America/Chicago" window matches at 10:30 CST post-transition', async ( { page } ) => {
//         await page.goto( '/visual-editor/preview/visibility-dst?_veTime=2026-11-01T16:30:00Z' )
//         await expect( page.locator( '[data-ap-vis-target="chicago-morning"]' ) ).toBeVisible()
//     } )
//
//     test( 'DST spring-forward — the same window matches at 10:30 CDT post-transition', async ( { page } ) => {
//         await page.goto( '/visual-editor/preview/visibility-dst?_veTime=2026-03-08T15:30:00Z' )
//         await expect( page.locator( '[data-ap-vis-target="chicago-morning"]' ) ).toBeVisible()
//     } )
// } )
