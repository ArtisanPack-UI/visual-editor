<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature/VisualEditor');

pest()->extend(Tests\Feature\Ai\AiTestCase::class)
    ->in('Feature/Ai');

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Unit/VisualEditor');

pest()->extend(ArtisanPackUI\VisualEditorRendererBlade\Tests\TestCase::class)
    ->in(__DIR__ . '/../packages/visual-editor-renderer-blade/tests/Unit');

pest()->extend(ArtisanPackUI\VisualEditorRendererBlade\Tests\TestCase::class)
    ->in(__DIR__ . '/../packages/visual-editor-renderer-blade/tests/Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Whether cms-framework's `BlockMarkupParser` is installed.
 *
 * The adapters' raw→blocks server-side parse (#674) depends on it, and it
 * arrived in cms-framework 2.5 — which requires PHP 8.3+, so on PHP 8.2 CI
 * the composer resolver picks an older cms-framework and the parser is
 * absent. Production code guards with `class_exists`; tests asserting the
 * parsed output skip on this.
 *
 * Declared here rather than in an adapter test file because both
 * `TemplateAdapterTest` and `TemplatePartAdapterTest` use it, and a
 * cross-file declaration broke running the latter on its own.
 */
function templatePartParserAvailable(): bool
{
    return class_exists( 'ArtisanPackUI\\CMSFramework\\Modules\\SiteEditor\\Support\\BlockMarkupParser' );
}

function something()
{
    // ..
}
