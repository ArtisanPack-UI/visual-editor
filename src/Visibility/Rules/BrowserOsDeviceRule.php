<?php

/**
 * Show / hide a block based on the visitor's user-agent classification.
 *
 * Attribute shape:
 *
 *     artisanpackVisibility.browserOsDevice = {
 *         "direction": "show",
 *         "browsers": ["chrome", "firefox"],  // slugs from UserAgentParser::BROWSER_*
 *         "operatingSystems": ["ios"],
 *         "deviceTypes": ["mobile", "tablet"]
 *     }
 *
 * Each family (browsers / operatingSystems / deviceTypes) evaluates
 * independently and combines with AND — a block that lists "chrome" and
 * "ios" is visible only in Chrome-on-iOS traffic. Leave a family off (or
 * empty) to skip it. `direction: hide` inverts the result.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.4.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Visibility\Rules;

use ArtisanPackUI\VisualEditor\Visibility\UserAgentParser;
use ArtisanPackUI\VisualEditor\Visibility\VisibilityContext;
use ArtisanPackUI\VisualEditor\Visibility\VisibilityDecision;
use ArtisanPackUI\VisualEditor\Visibility\VisibilityRule;

class BrowserOsDeviceRule implements VisibilityRule
{
	public function __construct( protected UserAgentParser $parser )
	{
	}

	public function key(): string
	{
		return 'browserOsDevice';
	}

	public function evaluate( array $ruleAttributes, VisibilityContext $context ): VisibilityDecision
	{
		$browsers = $this->stringList( $ruleAttributes['browsers']         ?? [] );
		$os       = $this->stringList( $ruleAttributes['operatingSystems'] ?? [] );
		$devices  = $this->stringList( $ruleAttributes['deviceTypes']      ?? [] );

		if ( [] === $browsers && [] === $os && [] === $devices ) {
			return VisibilityDecision::visible();
		}

		$direction = ( 'hide' === ( $ruleAttributes['direction'] ?? 'show' ) ) ? 'hide' : 'show';

		$browserSlug = $this->parser->browser( $context->userAgent );
		$osSlug      = $this->parser->os( $context->userAgent );
		$deviceSlug  = $this->parser->device( $context->userAgent );

		$matches = ( [] === $browsers || in_array( $browserSlug, $browsers, true ) )
			&& ( [] === $os       || in_array( $osSlug,      $os,       true ) )
			&& ( [] === $devices  || in_array( $deviceSlug,  $devices,  true ) );

		$visible = 'show' === $direction ? $matches : ! $matches;

		return $visible
			? VisibilityDecision::visible()
			: VisibilityDecision::hidden( [ $this->key() ] );
	}

	/**
	 * @return array<int, string>
	 */
	protected function stringList( mixed $value ): array
	{
		if ( ! is_array( $value ) ) {
			return [];
		}

		$out = [];
		foreach ( $value as $item ) {
			if ( is_string( $item ) && '' !== $item ) {
				$out[] = strtolower( $item );
			}
		}
		return array_values( array_unique( $out ) );
	}
}
