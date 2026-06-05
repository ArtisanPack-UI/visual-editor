@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\UrlSanitizer;

	// Default form target is the cms-framework comments REST endpoint
	// (`POST /api/v1/comments`). That route returns 201 + JSON though,
	// which is great for SPAs but not for a plain HTML form submit —
	// the browser navigates to the JSON. Host apps that want a
	// classic form-post UX should override this via the
	// `comments.form.action` filter (artisanpack-ui/hooks) and point
	// it at a controller that creates the comment and redirects back.
	$defaultAction = isset( $attributes['_resolvedFormAction'] ) && is_string( $attributes['_resolvedFormAction'] )
		? $attributes['_resolvedFormAction']
		: '/api/v1/comments';
	if ( function_exists( 'applyFilters' ) ) {
		$defaultAction = ( string ) applyFilters( 'comments.form.action', $defaultAction );
	}
	$formAction = UrlSanitizer::safe( $defaultAction );
@endphp
{{--
	post-comments-form

	Renders a minimal, semantically-correct comment form. The default
	`action` posts to cms-framework's `/api/v1/comments` endpoint; the
	`comments.form.action` filter lets host apps redirect submissions
	to their own controller so they can validate, persist, and redirect
	back to the post with a flash message — the standard browser-form
	UX the API endpoint can't provide on its own.
--}}
<div{!! BlockSupports::wrapperAttrs( $attributes, [ 'wp-block-post-comments-form', 'comment-respond' ] ) !!}>
	<h3 class="comment-reply-title">{{ __( 'Leave a Comment' ) }}</h3>
	<form action="{{ $formAction }}" method="post" class="comment-form">
		@if ( function_exists( 'csrf_field' ) )
			{!! csrf_field() !!}
		@endif
		@isset( $attributes['_resolvedPostId'] )
			<input type="hidden" name="post_id" value="{{ (int) $attributes['_resolvedPostId'] }}" />
		@endisset
		<p class="comment-form-author">
			<label for="comment-author">{{ __( 'Name' ) }} <span aria-hidden="true">*</span></label>
			<input id="comment-author" name="author_name" type="text" autocomplete="name" required />
		</p>
		<p class="comment-form-email">
			<label for="comment-email">{{ __( 'Email' ) }} <span aria-hidden="true">*</span></label>
			<input id="comment-email" name="author_email" type="email" autocomplete="email" required />
		</p>
		<p class="comment-form-url">
			<label for="comment-url">{{ __( 'Website' ) }}</label>
			<input id="comment-url" name="author_url" type="url" autocomplete="url" />
		</p>
		<p class="comment-form-comment">
			<label for="comment-content">{{ __( 'Comment' ) }} <span aria-hidden="true">*</span></label>
			<textarea id="comment-content" name="content" rows="6" required></textarea>
		</p>
		<p class="form-submit">
			{{-- Deliberately no `name="submit"` on the input — that
			     attribute shadows the form's `.submit` property in
			     the DOM and breaks any JS that hooks the form
			     (analytics, validation libraries, etc.). The
			     value isn't read server-side either. --}}
			<input type="submit" class="submit" value="{{ __( 'Post Comment' ) }}" />
		</p>
	</form>
</div>
