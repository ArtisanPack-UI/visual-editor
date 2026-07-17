<?php

/**
 * Server-side evaluator that decides whether each block in a tree
 * should render for the current visitor.
 *
 * Called from every renderer (Blade at `renderBlock()`, React/Vue via
 * the `inlineVisibility` server-produced-JSON filter) so a block hidden
 * by a rule never emits markup. The evaluator does two things per
 * block:
 *
 *   1. Consults `supports.artisanpackVisibility` (default `true` — the
 *      whole feature is opt-out, not opt-in). Blocks that pass `false`
 *      in their `block.json` never evaluate, regardless of what the
 *      attribute bag says.
 *   2. Walks every registered {@see VisibilityRule} against the block's
 *      `artisanpackVisibility.{rule.key}` slice, combining the returned
 *      {@see VisibilityDecision}s. Any single `hidden()` wins outright.
 *
 * The `ap.visual-editor.visibility.evaluated` action fires once per
 * block after evaluation completes, receiving `[decision, blockName,
 * attributes, context]` so a debug listener can answer "why is this
 * block hidden?" without instrumenting each rule.
 *
 * The site-wide `artisanpack.visual-editor.visibility.enabled` config
 * flag is honored inside {@see evaluate()} — set it to `false` in the
 * host app to bypass every rule (useful during incident response, e.g.
 * to unhide everything after a mis-published rule accidentally hides
 * critical content).
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.4.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Visibility;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\Request;
use Throwable;

class VisibilityEvaluator
{
	/**
	 * Blocks whose block.json declared `supports.artisanpackVisibility: false`
	 * so we skip evaluation entirely. Populated lazily as blocks are seen.
	 *
	 * @var array<string, bool>
	 */
	protected array $supportsCache = [];

	public function __construct(
		protected RuleRegistry $rules,
		protected ConfigRepository $config,
		protected ?Request $request = null,
		protected ?Guard $auth = null,
	) {
	}

	/**
	 * Whether the whole visibility feature is enabled. Renderers can
	 * short-circuit before allocating a context when this is false.
	 *
	 * @since 1.4.0
	 */
	public function enabled(): bool
	{
		return true === (bool) $this->config->get( 'artisanpack.visual-editor.visibility.enabled', true );
	}

	/**
	 * Build the request-scoped context from the current Request / Guard.
	 * The result is safe to reuse for a whole block-tree walk.
	 *
	 * @since 1.4.0
	 */
	public function contextFromRequest(): VisibilityContext
	{
		$request = $this->request;
		$auth    = $this->auth;

		$queryString = [];
		$referrer    = '';
		$userAgent   = '';

		if ( null !== $request ) {
			foreach ( $request->query() as $key => $value ) {
				if ( is_string( $key ) && ( is_string( $value ) || is_numeric( $value ) ) ) {
					$queryString[ $key ] = (string) $value;
				}
			}
			$referrer  = (string) $request->headers->get( 'referer', '' );
			$userAgent = (string) $request->headers->get( 'user-agent', '' );
		}

		$isAuthenticated = false;
		$userId          = null;
		$userEmail       = null;
		$roles           = [];

		if ( null !== $auth && $auth->check() ) {
			$isAuthenticated = true;
			$user            = $auth->user();

			if ( is_object( $user ) ) {
				// Preserve UUID / other string keys (hosts on
				// `HasUuids`) — casting to int would map them to 0
				// and silently mis-match specific-user rules.
				if ( method_exists( $user, 'getAuthIdentifier' ) ) {
					$raw    = $user->getAuthIdentifier();
					$userId = is_int( $raw )
						? $raw
						: ( is_scalar( $raw ) && '' !== (string) $raw ? (string) $raw : null );
				} else {
					$userId = null;
				}

				if ( property_exists( $user, 'email' ) || isset( $user->email ) ) {
					$userEmail = is_string( $user->email ?? null ) ? $user->email : null;
				}

				$roles = $this->resolveRoles( $user );
			}
		}

		return new VisibilityContext(
			queryString:     $queryString,
			referrer:        $referrer,
			userAgent:       $userAgent,
			isAuthenticated: $isAuthenticated,
			userId:          $userId,
			userEmail:       $userEmail,
			roles:           $roles,
			now:             CarbonImmutable::now(),
			isPreview:       false,
		);
	}

	/**
	 * Layer a {@see PreviewContext} over the real request context. Only
	 * non-null fields on the preview take effect — everything else falls
	 * through to the real request. Marks the returned context as
	 * preview so rules that need to differentiate can branch.
	 *
	 * @since 1.4.0
	 */
	public function previewFrom( PreviewContext $preview ): VisibilityContext
	{
		$base = $this->contextFromRequest();

		$overrides = [];
		if ( null !== $preview->queryString )    { $overrides['queryString']     = $preview->queryString; }
		if ( null !== $preview->referrer )       { $overrides['referrer']        = $preview->referrer; }
		if ( null !== $preview->userAgent )      { $overrides['userAgent']       = $preview->userAgent; }
		if ( null !== $preview->isAuthenticated ){ $overrides['isAuthenticated'] = $preview->isAuthenticated; }
		if ( null !== $preview->userId )         { $overrides['userId']          = $preview->userId; }
		if ( null !== $preview->userEmail )      { $overrides['userEmail']       = $preview->userEmail; }
		if ( null !== $preview->roles )          { $overrides['roles']           = $preview->roles; }
		if ( null !== $preview->now )            { $overrides['now']             = $preview->now; }

		$overrides['isPreview'] = true;

		return $base->with( $overrides );
	}

	/**
	 * Evaluate a single block. Returns a {@see VisibilityDecision} the
	 * caller uses to decide whether to render markup for the block, drop
	 * it, or wrap it in a breakpoint-scoped `display:none` shell.
	 *
	 * @param  array<string, mixed>  $block  A single block node.
	 *
	 * @since 1.4.0
	 */
	public function evaluate( array $block, VisibilityContext $context ): VisibilityDecision
	{
		if ( ! $this->enabled() ) {
			return VisibilityDecision::visible();
		}

		$name       = isset( $block['name'] ) && is_string( $block['name'] ) ? $block['name'] : '';
		$attributes = isset( $block['attributes'] ) && is_array( $block['attributes'] ) ? $block['attributes'] : [];

		if ( '' === $name || ! $this->supportsVisibility( $name ) ) {
			return VisibilityDecision::visible();
		}

		$slice = $attributes['artisanpackVisibility'] ?? null;

		if ( ! is_array( $slice ) ) {
			return VisibilityDecision::visible();
		}

		$decision = VisibilityDecision::visible();

		foreach ( $this->rules->all() as $rule ) {
			$ruleAttrs = $slice[ $rule->key() ] ?? null;

			if ( ! is_array( $ruleAttrs ) || [] === $ruleAttrs ) {
				continue;
			}

			try {
				$next = $rule->evaluate( $ruleAttrs, $context );
			} catch ( Throwable $e ) {
				report( $e );
				continue;
			}

			$decision = $decision->combine( $next );

			// Short-circuit on hidden — later rules can't undo a hide.
			if ( $decision->isHidden() ) {
				break;
			}
		}

		$this->fireEvaluatedHook( $decision, $name, $attributes, $context );

		return $decision;
	}

	/**
	 * Populate the block-name → supports cache. Called by the renderer
	 * once per unique block name the tree contains. Blocks the registry
	 * doesn't know about default to opted-in (`true`) so the evaluator
	 * still checks their attribute bags.
	 *
	 * @param  array<string, bool>  $map  block name → opted in
	 *
	 * @since 1.4.0
	 */
	public function primeSupports( array $map ): void
	{
		foreach ( $map as $name => $optIn ) {
			if ( is_string( $name ) && '' !== $name ) {
				$this->supportsCache[ $name ] = (bool) $optIn;
			}
		}
	}

	public function rules(): RuleRegistry
	{
		return $this->rules;
	}

	protected function supportsVisibility( string $name ): bool
	{
		return $this->supportsCache[ $name ] ?? true;
	}

	protected function fireEvaluatedHook(
		VisibilityDecision $decision,
		string $name,
		array $attributes,
		VisibilityContext $context,
	): void {
		if ( ! function_exists( 'doAction' ) ) {
			return;
		}

		try {
			doAction( 'ap.visual-editor.visibility.evaluated', $decision, $name, $attributes, $context );
		} catch ( Throwable $e ) {
			report( $e );
		}
	}

	/**
	 * Resolve the visitor's roles.
	 *
	 * When cms-framework's `RoleManager` is bound in the container we
	 * consult it first (that's the canonical role registry for hosts
	 * running the cms-framework auth stack). Otherwise fall back to
	 * common Spatie/Laravel patterns: a `getRoleNames()` collection, a
	 * `roles` relation with `name`/`slug` fields, or a `roles` array
	 * attribute.
	 *
	 * **Identifier-shape contract**: the returned strings MUST match
	 * the identifiers the Inspector picker persists into
	 * `artisanpackVisibility.userRole.roles` — otherwise the rule's
	 * strict `in_array` never matches. Hosts wire the picker's role
	 * list via `setVisibilityRoles([{slug, label}])` in the editor
	 * bootstrap; the `slug` value MUST come from the same source this
	 * method returns:
	 *
	 *   - cms-framework hosts: `RoleManager` slugs on both sides.
	 *   - Spatie hosts: Spatie's role names (which ARE the identifier)
	 *     on both sides.
	 *   - Custom `->roles` relation hosts: whichever column the
	 *     relation exposes as `slug` (or `name` fallback), consistent
	 *     between picker source and this resolver.
	 *
	 * Silent mismatch is the biggest failure mode: installing
	 * cms-framework after content was authored with Spatie-persisted
	 * names will break every user-role rule. Document the identifier
	 * source when publishing a v1 site.
	 *
	 * @return array<int, string>
	 *
	 * @since 1.4.0
	 */
	protected function resolveRoles( object $user ): array
	{
		$manager = '\\ArtisanPackUI\\CMSFramework\\Modules\\Users\\Managers\\RoleManager';

		if ( class_exists( $manager ) ) {
			try {
				$instance = app( $manager );

				if ( method_exists( $instance, 'rolesFor' ) ) {
					$roles = $instance->rolesFor( $user );

					if ( is_iterable( $roles ) ) {
						$out = [];
						foreach ( $roles as $role ) {
							if ( is_string( $role ) && '' !== $role ) {
								$out[] = $role;
							} elseif ( is_object( $role ) && isset( $role->slug ) && is_string( $role->slug ) ) {
								$out[] = $role->slug;
							} elseif ( is_object( $role ) && isset( $role->name ) && is_string( $role->name ) ) {
								$out[] = $role->name;
							}
						}
						return $out;
					}
				}
			} catch ( Throwable $e ) {
				// Fall through to the generic fallbacks.
			}
		}

		if ( method_exists( $user, 'getRoleNames' ) ) {
			try {
				$names = $user->getRoleNames();
				if ( is_iterable( $names ) ) {
					$out = [];
					foreach ( $names as $name ) {
						if ( is_string( $name ) && '' !== $name ) {
							$out[] = $name;
						}
					}
					return $out;
				}
			} catch ( Throwable $e ) {
				// Ignore — try the next fallback.
			}
		}

		$roles = $user->roles ?? null;

		if ( is_iterable( $roles ) ) {
			$out = [];
			foreach ( $roles as $role ) {
				if ( is_string( $role ) && '' !== $role ) {
					$out[] = $role;
				} elseif ( is_object( $role ) ) {
					$slug = $role->slug ?? $role->name ?? null;
					if ( is_string( $slug ) && '' !== $slug ) {
						$out[] = $slug;
					}
				}
			}
			return $out;
		}

		return [];
	}
}
