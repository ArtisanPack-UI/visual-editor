<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Livewire\Blocks\TableOfContentsBlockComponent;

test( 'toc component filters headings by level', function (): void {
	$component = new TableOfContentsBlockComponent();

	$component->headingLevels = [ 2, 3 ];
	$component->headings      = [
		[ 'level' => 1, 'text' => 'H1 Title', 'id' => 'h1-title' ],
		[ 'level' => 2, 'text' => 'H2 Section', 'id' => 'h2-section' ],
		[ 'level' => 3, 'text' => 'H3 Subsection', 'id' => 'h3-subsection' ],
		[ 'level' => 4, 'text' => 'H4 Detail', 'id' => 'h4-detail' ],
	];

	$method = new ReflectionMethod( $component, 'filterHeadings' );
	$method->setAccessible( true );
	$filtered = $method->invoke( $component );

	expect( $filtered )->toHaveCount( 2 )
		->and( $filtered[0]['text'] )->toBe( 'H2 Section' )
		->and( $filtered[1]['text'] )->toBe( 'H3 Subsection' );
} );

test( 'toc component builds flat list', function (): void {
	$component = new TableOfContentsBlockComponent();

	$headings = [
		[ 'level' => 2, 'text' => 'First', 'id' => 'first' ],
		[ 'level' => 2, 'text' => 'Second', 'id' => 'second' ],
	];

	$method = new ReflectionMethod( $component, 'buildFlat' );
	$method->setAccessible( true );
	$flat = $method->invoke( $component, $headings );

	expect( $flat )->toHaveCount( 2 )
		->and( $flat[0]['children'] )->toBeEmpty()
		->and( $flat[1]['children'] )->toBeEmpty();
} );

test( 'toc component builds hierarchical list', function (): void {
	$component = new TableOfContentsBlockComponent();

	$component->maxDepth = 3;

	$headings = [
		[ 'level' => 2, 'text' => 'Section 1', 'id' => 'section-1' ],
		[ 'level' => 3, 'text' => 'Sub 1.1', 'id' => 'sub-1-1' ],
		[ 'level' => 3, 'text' => 'Sub 1.2', 'id' => 'sub-1-2' ],
		[ 'level' => 2, 'text' => 'Section 2', 'id' => 'section-2' ],
	];

	$method = new ReflectionMethod( $component, 'buildHierarchical' );
	$method->setAccessible( true );
	$hierarchical = $method->invoke( $component, $headings );

	expect( $hierarchical )->toHaveCount( 2 )
		->and( $hierarchical[0]['text'] )->toBe( 'Section 1' )
		->and( $hierarchical[0]['children'] )->toHaveCount( 2 )
		->and( $hierarchical[0]['children'][0]['text'] )->toBe( 'Sub 1.1' )
		->and( $hierarchical[1]['text'] )->toBe( 'Section 2' )
		->and( $hierarchical[1]['children'] )->toBeEmpty();
} );

test( 'toc component generates sample headings when no headings provided', function (): void {
	$component = new TableOfContentsBlockComponent();

	$component->headingLevels = [ 2, 3 ];
	$component->headings      = [];

	$method = new ReflectionMethod( $component, 'filterHeadings' );
	$method->setAccessible( true );
	$samples = $method->invoke( $component );

	expect( $samples )->not->toBeEmpty();

	foreach ( $samples as $sample ) {
		expect( $sample )->toHaveKeys( [ 'level', 'text', 'id' ] )
			->and( in_array( $sample['level'], [ 2, 3 ], true ) )->toBeTrue();
	}
} );
