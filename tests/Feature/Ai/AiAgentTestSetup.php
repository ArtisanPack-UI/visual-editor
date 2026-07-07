<?php

/**
 * Shared bootstrap for visual-editor AI agent tests.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @since      1.3.0
 */

declare( strict_types=1 );

namespace Tests\Feature\Ai;

use ArtisanPackUI\Ai\Contracts\AgentPrompter;
use ArtisanPackUI\Ai\Contracts\CredentialResolver;
use ArtisanPackUI\Ai\Credentials\ChainedCredentialResolver;
use ArtisanPackUI\Ai\Credentials\Credentials;
use Tests\Support\FakeAgentPrompter;

/**
 * Registers a fake prompter, stub credentials, and enables the five
 * feature toggles the visual editor cares about.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @since      1.3.0
 */
final class AiAgentTestSetup
{
	/**
	 * @since 1.3.0
	 *
	 * @param  \Illuminate\Foundation\Application  $app  Application instance.
	 *
	 * @return FakeAgentPrompter The bound fake prompter.
	 */
	public static function bootstrap( $app ): FakeAgentPrompter
	{
		/** @var ChainedCredentialResolver $resolver */
		$resolver = $app->make( CredentialResolver::class );
		$resolver->setOverride(
			new Credentials( provider: 'anthropic', apiKey: 'sk-test', defaultModel: 'claude-haiku-4-5' ),
		);
		$resolver->useStore( fn () => null );

		$prompter = new FakeAgentPrompter();
		$app->instance( AgentPrompter::class, $prompter );

		foreach (
			[
				'visual_editor.suggest_next_block',
				'visual_editor.suggest_layout',
				'visual_editor.heading_hierarchy',
				'ai.alt_text',
				'ai.content_rewrite',
			] as $key
		) {
			$app['config']->set( "artisanpack.ai.features.{$key}.enabled", true );
		}

		return $prompter;
	}
}
