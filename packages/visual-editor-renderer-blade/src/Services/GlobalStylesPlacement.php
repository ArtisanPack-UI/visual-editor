<?php

/**
 * Decides where the request's single global-styles `<style>` lands (#879).
 *
 * Global styles belong in `<head>`, before the theme's own stylesheet, so
 * the front end uses the editor canvas's (and WordPress's) cascade order.
 * Blade, though, renders a layout's `@section` content and a component
 * layout's slot *before* the layout markup that holds `<head>`, so the
 * first `<x-ve-blocks>` / `<x-ve-template>` usually runs before
 * `<x-ve-blocks-styles>`. This class settles the order:
 *
 *  - `<x-ve-blocks-styles>` rendering first (a single-file view whose head
 *    comes before the blocks) claims the emission and prints it inline.
 *  - A body renderer that runs first *inside a deferred capture* (an
 *    `@section` or a component slot, whose layout renders later) pushes the
 *    `<style>` onto the `ve-global-styles` stack. `<x-ve-blocks-styles>`
 *    prints that stack, so the styles still land in the head.
 *  - A body renderer that runs outside any capture, or after the head has
 *    already rendered, prints inline as before: nothing rendered later could
 *    place the styles any earlier in the document.
 *
 * A page that renders blocks inside a layout capture but never renders
 * `<x-ve-blocks-styles>` (or `@stack('ve-global-styles')`) would leave the
 * queued styles unprinted. For HTML responses the
 * {@see \ArtisanPackUI\VisualEditorRendererBlade\Http\Middleware\InjectQueuedGlobalStyles}
 * middleware on the `web` group catches that and injects them before
 * `</head>`. Outside an HTTP response (mail, `Blade::render()` into a
 * string) set `artisanpack.visual-editor.global_styles_in_head` to `false`
 * to always print the styles inline in the body (the 1.14 placement).
 *
 * Bound as a scoped service, alongside the scoped emission tracker, so a
 * long-lived worker starts every request fresh.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.15.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditorRendererBlade\Services;

use ArtisanPackUI\VisualEditor\Services\GlobalStylesEmissionTracker;
use Closure;
use Illuminate\View\Factory;
use Throwable;

class GlobalStylesPlacement
{
	/**
	 * Blade stack a body renderer pushes the global styles onto when the
	 * head hasn't rendered yet.
	 *
	 * @since 1.15.0
	 *
	 * @var string
	 */
	public const STACK = 've-global-styles';

	/**
	 * Config key that turns head placement on (default) or off.
	 *
	 * @since 1.15.0
	 *
	 * @var string
	 */
	public const CONFIG_KEY = 'artisanpack.visual-editor.global_styles_in_head';

	/**
	 * Whether an `<x-ve-blocks-styles>` has rendered in this request.
	 *
	 * @since 1.15.0
	 */
	protected bool $headRendered = false;

	/**
	 * Whether an `<x-ve-blocks-styles>` has already printed the stack, so
	 * a second instance doesn't print the queued styles again.
	 *
	 * @since 1.15.0
	 */
	protected bool $stackPrinted = false;

	/**
	 * The `<style data-ve-global-styles>` tag a body renderer queued on the
	 * stack, or null when nothing is queued or it has been delivered.
	 *
	 * @since 1.15.0
	 */
	protected ?string $queuedTag = null;

	/**
	 * Whether global styles go in `<head>` (the default) rather than
	 * inline in the body.
	 *
	 * @since 1.15.0
	 */
	public function inHead(): bool
	{
		return false !== filter_var(
			config( self::CONFIG_KEY, true ),
			FILTER_VALIDATE_BOOLEAN,
			FILTER_NULL_ON_FAILURE,
		);
	}

	/**
	 * Called by `<x-ve-blocks-styles>`: records that the head has rendered
	 * and, when this instance prints global styles and nobody has emitted
	 * them yet, claims the emission and returns the CSS to print inline.
	 *
	 * @since 1.15.0
	 *
	 * @param  GlobalStylesEmissionTracker   $tracker       The request's emission tracker.
	 * @param  GlobalStylesEmissionResolver  $resolver      Resolves the compiled CSS.
	 * @param  bool                          $globalStyles  The component's `:global-styles` flag.
	 *
	 * @return string The CSS to print, or '' when another renderer owns it.
	 */
	public function claimForHead(
		GlobalStylesEmissionTracker $tracker,
		GlobalStylesEmissionResolver $resolver,
		bool $globalStyles,
	): string {
		$this->headRendered = true;

		if ( ! $globalStyles || ! $this->inHead() || $tracker->hasEmitted() ) {
			return '';
		}

		$tracker->markEmitted();

		return $resolver->emit();
	}

	/**
	 * Called by `<x-ve-blocks-styles>`: whether this instance should print
	 * the `ve-global-styles` stack. True once per request.
	 *
	 * Every instance qualifies, including one with `:global-styles="false"`:
	 * by the time a head that renders after the body runs, the body has
	 * already queued its styles there, and printing them is the only way
	 * not to lose them.
	 *
	 * @since 1.15.0
	 */
	public function takeStack(): bool
	{
		if ( $this->stackPrinted ) {
			return false;
		}

		$this->stackPrinted = true;

		return true;
	}

	/**
	 * The queued `<style data-ve-global-styles>` tag no
	 * `<x-ve-blocks-styles>` has printed yet, or null. The response
	 * middleware delivers it when the layout never printed the stack.
	 *
	 * @since 1.15.0
	 */
	public function pendingTag(): ?string
	{
		return $this->stackPrinted ? null : $this->queuedTag;
	}

	/**
	 * Record that the queued tag has been delivered, so nothing prints it
	 * again.
	 *
	 * @since 1.15.0
	 */
	public function markDelivered(): void
	{
		$this->queuedTag = null;
	}

	/**
	 * Called by `<x-ve-blocks>` / `<x-ve-template>`: claims the emission
	 * when nobody has, then either returns the CSS to print inline or
	 * queues it on the `ve-global-styles` stack for the head.
	 *
	 * @since 1.15.0
	 *
	 * @param  GlobalStylesEmissionTracker   $tracker   The request's emission tracker.
	 * @param  GlobalStylesEmissionResolver  $resolver  Resolves the compiled CSS.
	 *
	 * @return string|null The CSS to print inline, or null when there is
	 *                     nothing to print here.
	 */
	public function claimForBody(
		GlobalStylesEmissionTracker $tracker,
		GlobalStylesEmissionResolver $resolver,
	): ?string {
		if ( $tracker->hasEmitted() ) {
			return null;
		}

		$tracker->markEmitted();

		$css = $resolver->emit();

		if ( '' === $css ) {
			return null;
		}

		if ( $this->inHead() && ! $this->headRendered && $this->insideDeferredCapture() ) {
			$this->queuedTag = '<style data-ve-global-styles>' . $css . '</style>';

			app( 'view' )->startPush( self::STACK, $this->queuedTag );

			return null;
		}

		return $css;
	}

	/**
	 * Clear the per-request state, for tests that render several pages in
	 * one process.
	 *
	 * @since 1.15.0
	 */
	public function reset(): void
	{
		$this->headRendered = false;
		$this->stackPrinted = false;
		$this->queuedTag    = null;
	}

	/**
	 * Whether Blade is capturing output for later: an open `@section`, or
	 * a component whose slot is rendering before its own view. Only then
	 * can a layout rendered afterwards still put markup above this point.
	 *
	 * Reads the view factory's section and component stacks, which Laravel
	 * exposes no accessor for. Any failure reads as "not capturing", which
	 * falls back to printing inline.
	 *
	 * @since 1.15.0
	 */
	protected function insideDeferredCapture(): bool
	{
		$factory = app( 'view' );

		if ( ! $factory instanceof Factory ) {
			return false;
		}

		$reader = function (): bool {
			return [] !== ( $this->sectionStack ?? [] ) || [] !== ( $this->componentStack ?? [] );
		};

		try {
			return (bool) Closure::bind( $reader, $factory, $factory::class )();
		} catch ( Throwable ) {
			return false;
		}
	}
}
