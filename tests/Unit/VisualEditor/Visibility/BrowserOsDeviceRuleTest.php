<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Visibility\Rules\BrowserOsDeviceRule;
use ArtisanPackUI\VisualEditor\Visibility\UserAgentParser;
use ArtisanPackUI\VisualEditor\Visibility\VisibilityContext;

function uaCtx( string $ua ): VisibilityContext
{
	return new VisibilityContext( userAgent: $ua );
}

it( 'is visible when no families are configured', function () {
	$rule = new BrowserOsDeviceRule( new UserAgentParser() );
	expect( $rule->evaluate( [], uaCtx( 'anything' ) )->isVisible() )->toBeTrue();
} );

it( 'shows only for the listed browser', function () {
	$rule = new BrowserOsDeviceRule( new UserAgentParser() );
	$attrs = [ 'direction' => 'show', 'browsers' => [ 'chrome' ] ];
	$chrome = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36';
	$firefox = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0';

	expect( $rule->evaluate( $attrs, uaCtx( $chrome ) )->isVisible() )->toBeTrue();
	expect( $rule->evaluate( $attrs, uaCtx( $firefox ) )->isHidden() )->toBeTrue();
} );

it( 'combines browser + OS + device with AND', function () {
	$rule = new BrowserOsDeviceRule( new UserAgentParser() );
	$attrs = [ 'direction' => 'show', 'browsers' => [ 'safari' ], 'operatingSystems' => [ 'ios' ], 'deviceTypes' => [ 'mobile' ] ];
	$iphone = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';
	$macSafari = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15';

	expect( $rule->evaluate( $attrs, uaCtx( $iphone ) )->isVisible() )->toBeTrue();
	expect( $rule->evaluate( $attrs, uaCtx( $macSafari ) )->isHidden() )->toBeTrue();
} );

it( 'classifies bots as device=bot', function () {
	$parser = new UserAgentParser();
	expect( $parser->device( 'Googlebot/2.1 (+http://www.google.com/bot.html)' ) )->toBe( UserAgentParser::DEVICE_BOT );
} );
