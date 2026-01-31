<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Models\Content;
use ArtisanPackUI\VisualEditor\Policies\ContentPolicy;
use Tests\Models\User;

beforeEach( function (): void {
	$this->policy  = new ContentPolicy();
	$this->author  = User::factory()->create();
	$this->other   = User::factory()->create();
	$this->content = Content::create( [
		'title'     => 'Test Content',
		'slug'      => 'test-content',
		'blocks'    => [ [ 'type' => 'heading' ] ],
		'status'    => 'draft',
		'author_id' => $this->author->id,
	] );
} );

test( 'update allows the content author', function (): void {
	expect( $this->policy->update( $this->author, $this->content ) )->toBeTrue();
} );

test( 'update denies a different user', function (): void {
	expect( $this->policy->update( $this->other, $this->content ) )->toBeFalse();
} );

test( 'publish allows the content author', function (): void {
	expect( $this->policy->publish( $this->author, $this->content ) )->toBeTrue();
} );

test( 'publish denies a different user', function (): void {
	expect( $this->policy->publish( $this->other, $this->content ) )->toBeFalse();
} );

test( 'unpublish allows the content author', function (): void {
	expect( $this->policy->unpublish( $this->author, $this->content ) )->toBeTrue();
} );

test( 'unpublish denies a different user', function (): void {
	expect( $this->policy->unpublish( $this->other, $this->content ) )->toBeFalse();
} );

test( 'schedule allows the content author', function (): void {
	expect( $this->policy->schedule( $this->author, $this->content ) )->toBeTrue();
} );

test( 'schedule denies a different user', function (): void {
	expect( $this->policy->schedule( $this->other, $this->content ) )->toBeFalse();
} );
