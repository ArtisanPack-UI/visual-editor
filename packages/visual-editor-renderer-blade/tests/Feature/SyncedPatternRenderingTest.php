<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Models\VisualEditorPattern;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;

uses( RefreshDatabase::class );

function bladePatternFixture( string $slug, array $blocks, bool $synced = true ): VisualEditorPattern
{
	return VisualEditorPattern::create( [
		'slug'    => $slug,
		'title'   => ucfirst( $slug ),
		'content' => [ 'raw' => '', 'blocks' => $blocks ],
		'synced'  => $synced,
		'status'  => 'publish',
	] );
}

function bladePatternRef( int $ref, string $clientId = 'pat-cid' ): array
{
	return [
		'clientId'    => $clientId,
		'name'        => 'core/block',
		'attributes'  => [ 'ref' => $ref ],
		'innerBlocks' => [],
	];
}

function bladePatternParagraph( string $text, string $clientId = 'p-cid' ): array
{
	return [
		'clientId'    => $clientId,
		'name'        => 'core/paragraph',
		'attributes'  => [ 'content' => $text ],
		'innerBlocks' => [],
	];
}

it( 'inlines a core/block reference inside <x-ve-blocks>', function () {
	$pattern = bladePatternFixture( 'hero', [ bladePatternParagraph( 'Hero copy' ) ] );

	$tree = [ bladePatternRef( $pattern->id ) ];

	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] );
	$normalized = $this->normalizeHtml( $rendered );

	expect( $normalized )->toContain( '<div class="wp-block-block"' );
	expect( $normalized )->toContain( 'data-ve-pattern-ref="' . $pattern->id . '"' );
	expect( $normalized )->toContain( '<p class="wp-block-paragraph">Hero copy</p>' );
} );

it( 'skips inlining when :resolve-patterns is false', function () {
	$pattern = bladePatternFixture( 'hero', [ bladePatternParagraph( 'Hero copy' ) ] );

	$tree = [ bladePatternRef( $pattern->id ) ];

	$rendered = Blade::render(
		'<x-ve-blocks :tree="$tree" :resolve-patterns="false" />',
		[ 'tree' => $tree ]
	);

	expect( $this->normalizeHtml( $rendered ) )->not()->toContain( 'Hero copy' );
} );

it( 'renders an empty wrapper when the pattern is missing in production', function () {
	$this->app['env'] = 'production';

	$tree = [ bladePatternRef( 9999 ) ];

	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] );
	$normalized = $this->normalizeHtml( $rendered );

	expect( $normalized )->toContain( 'wp-block-block' );
	expect( $normalized )->not()->toContain( 'failed to resolve' );
} );

it( 'surfaces a comment warning when the pattern is missing in development', function () {
	$this->app['env'] = 'local';

	$tree = [ bladePatternRef( 9999 ) ];

	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] );

	expect( $rendered )->toContain( 'failed to resolve (not-found)' );
} );

it( 'flags a missing-ref marker when the ref attribute is absent', function () {
	$this->app['env'] = 'local';

	$tree = [
		[
			'clientId'    => 'pat-no-ref',
			'name'        => 'core/block',
			'attributes'  => [],
			'innerBlocks' => [],
		],
	];

	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] );

	expect( $rendered )->toContain( 'failed to resolve (missing-ref)' );
} );

it( 'renders a cycle marker without infinite recursion', function () {
	$this->app['env'] = 'local';

	$patternA = bladePatternFixture( 'a', [] );
	$patternB = bladePatternFixture( 'b', [] );

	// Patch the pattern blocks now that we know the ids — pattern A
	// references B and vice versa, which would loop forever without the
	// cycle guard.
	$patternA->setContentEnvelope( [
		'raw'    => '',
		'blocks' => [ bladePatternRef( $patternB->id, 'cycle-b' ) ],
	] );
	$patternA->save();

	$patternB->setContentEnvelope( [
		'raw'    => '',
		'blocks' => [ bladePatternRef( $patternA->id, 'cycle-a' ) ],
	] );
	$patternB->save();

	$tree = [ bladePatternRef( $patternA->id ) ];

	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] );

	expect( $rendered )->toContain( 'data-ve-pattern-ref="' . $patternA->id . '"' );
	expect( $rendered )->toContain( 'data-ve-pattern-ref="' . $patternB->id . '"' );
	expect( $rendered )->toContain( 'failed to resolve (cycle)' );
} );

it( 'omits data-ve-pattern-ref when the ref attribute is whitespace-only', function () {
	// `:resolve-patterns="false"` lets the raw attributes flow straight to
	// the partial — exercise the partial's own normalization to confirm a
	// whitespace ref does not leak into the rendered data attribute. This
	// matches the React/Vue `refString` helper, which trims before
	// emptiness checks.
	$tree = [
		[
			'clientId'    => 'pat-whitespace',
			'name'        => 'core/block',
			'attributes'  => [ 'ref' => '   ' ],
			'innerBlocks' => [],
		],
	];

	$rendered = Blade::render(
		'<x-ve-blocks :tree="$tree" :resolve-patterns="false" />',
		[ 'tree' => $tree ]
	);

	$normalized = $this->normalizeHtml( $rendered );

	expect( $normalized )->toContain( '<div class="wp-block-block">' );
	expect( $normalized )->not()->toContain( 'data-ve-pattern-ref' );
} );

it( 'unsynced patterns travel as inlined block trees, never as core/block references', function () {
	// Sanity check on the synced/unsynced contract documented in
	// `docs/plans/11-v1-expansion.md` §2.2 / §8: the editor only emits
	// `core/block` references for synced patterns. Unsynced patterns are
	// inlined into the saved tree at insert time (the editor calls
	// Gutenberg's `parse()` on `pattern.content.raw` and pastes the
	// resulting block list into the target — see
	// `inserter-patterns-panel.tsx::patternBlocks`). The renderer never
	// resolves an unsynced pattern at render time, so the tree it sees
	// already carries the pattern's blocks directly.
	$pattern = bladePatternFixture(
		'hero-unsynced',
		[ bladePatternParagraph( 'Unsynced copy' ) ],
		false
	);

	expect( $pattern->synced )->toBeFalse();

	// Simulate the post-insert state: the saved tree has the pattern's
	// blocks inlined directly, with no `core/block` reference at all.
	$treeAsSaved = $pattern->getBlocks();

	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $treeAsSaved ] );

	$normalized = $this->normalizeHtml( $rendered );

	expect( $normalized )->toContain( 'Unsynced copy' );
	expect( $normalized )->not()->toContain( 'wp-block-block' );
	expect( $normalized )->not()->toContain( 'data-ve-pattern-ref' );
} );
