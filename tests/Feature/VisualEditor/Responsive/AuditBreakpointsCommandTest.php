<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Models\VisualEditorPost;

beforeEach( function () {
	// Only the visual-editor's default 'sm', 'md', 'lg', 'xl', '2xl' breakpoints
	// are registered, plus the implicit `base`. Anything else is an orphan.
	config()->set( 'artisanpack.visual-editor.breakpoints', [] );
} );

it( 'reports orphaned breakpoint overrides on stored block trees', function () {
	VisualEditorPost::create( [
		'title'  => 'Has orphans',
		'blocks' => [
			[
				'blockName' => 'artisanpack/columns',
				'attrs'     => [
					'spacing' => [ 'base' => 4, 'md' => 6, 'legacy' => 9 ],
				],
				'innerBlocks' => [],
			],
		],
	] );

	$this->artisan( 'visual-editor:audit-breakpoints' )
		->assertExitCode( 0 )
		->expectsOutputToContain( 'legacy' );
} );

it( 'reports zero orphans when every override key is in the registry', function () {
	VisualEditorPost::create( [
		'title'  => 'Clean',
		'blocks' => [
			[
				'blockName' => 'artisanpack/columns',
				'attrs'     => [
					'spacing' => [ 'base' => 4, 'md' => 6 ],
				],
				'innerBlocks' => [],
			],
		],
	] );

	$this->artisan( 'visual-editor:audit-breakpoints' )
		->assertExitCode( 0 )
		->expectsOutputToContain( 'No orphaned breakpoint overrides found' );
} );

it( 'outputs JSON when --json flag is passed', function () {
	VisualEditorPost::create( [
		'title'  => 'JSON output',
		'blocks' => [
			[
				'blockName' => 'artisanpack/columns',
				'attrs'     => [
					'spacing' => [ 'base' => 4, 'legacy' => 9 ],
				],
				'innerBlocks' => [],
			],
		],
	] );

	$this->artisan( 'visual-editor:audit-breakpoints --json' )
		->assertExitCode( 0 )
		->expectsOutputToContain( '"scanned"' );
} );
