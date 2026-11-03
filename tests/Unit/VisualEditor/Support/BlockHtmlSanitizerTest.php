<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Support\BlockHtmlSanitizer;

function sanitizeBlockHtml( string $html ): string
{
	return app( BlockHtmlSanitizer::class )->sanitize( $html );
}

it( 'removes active markup', function ( string $html, string $expected ): void {
	expect( sanitizeBlockHtml( $html ) )->toBe( $expected );
} )->with( [
	'script'             => [ 'Hi<script>alert(1)</script> there', 'Hi there' ],
	'event handler'      => [ '<img src="/a.png" onerror="alert(1)">', '<img src="/a.png">' ],
	'upper-case handler' => [ '<a href="/a" OnClIcK="alert(1)">x</a>', '<a href="/a">x</a>' ],
	'script link'        => [ '<a href="javascript:alert(1)">x</a>', '<a>x</a>' ],
	'split script link'  => [ "<a href=\"java\tscript:alert(1)\">x</a>", '<a>x</a>' ],
	'entity script link' => [ '<a href="&#106;avascript:alert(1)">x</a>', '<a>x</a>' ],
	'data link'          => [ '<a href="data:text/html,<script>1</script>">x</a>', '<a>x</a>' ],
	'iframe'             => [ 'a<iframe srcdoc="<script>1</script>"></iframe>b', 'ab' ],
	'object'             => [ '<object data="x.swf">y</object>z', 'z' ],
	'style element'      => [ '<style>body{display:none}</style>x', 'x' ],
	'html comment'       => [ 'a<!-- <script>1</script> -->b', 'ab' ],
	'noscript'           => [ '<noscript><img src=x onerror=1></noscript>x', 'x' ],
	'framework attrs'    => [ '<div x-on:click="a()" @click="b()" wire:click="c" data-wp-on--click="d">x</div>', '<div>x</div>' ],
	'form tags unwrap'   => [ '<form action="/x"><p>text</p></form>', '<p>text</p>' ],
	'unknown tags'       => [ '<marquee>text</marquee>', 'text' ],
	'closing the body'   => [ '</div></body></html><script>1</script>x', '' ],
	'nested in allowed'  => [ '<p><b><script>1</script>x</b></p>', '<p><b>x</b></p>' ],
	'unsafe srcset'      => [ '<img src="/a.png" srcset="/a.png 1x, javascript:x 2x">', '<img src="/a.png">' ],
	'svg image'          => [ '<img src="data:image/svg+xml;base64,PHN2Zz4=">', '<img>' ],
] );

it( 'keeps what rich text and block markup use', function ( string $html ): void {
	expect( sanitizeBlockHtml( $html ) )->toBe( $html );
} )->with( [
	'formats'     => [ '<strong>a</strong> <em>b</em> <code>c</code> <kbd>d</kbd> <sub>e</sub> <sup>f</sup>' ],
	'link'        => [ '<a href="https://example.com/a?b=1&amp;c=2" target="_blank" rel="noopener">x</a>' ],
	'mailto'      => [ '<a href="mailto:me@example.com">x</a>' ],
	'relative'    => [ '<a href="/about#team">x</a>' ],
	'highlight'   => [ '<mark class="has-inline-color" style="background-color:rgba(0, 0, 0, 0);color:#ff0000">x</mark>' ],
	'inline img'  => [ '<img class="wp-image-1" style="width:150px" src="/a.png" alt="A">' ],
	'inline data' => [ '<img src="data:image/png;base64,iVBORw0KGgo=" alt="">' ],
	'dynamic'     => [ '<a href="/p/1" data-type="post" data-id="1">x</a>' ],
	'icon ref'    => [ '<span class="ap-inline-icon" data-icon-set="fab" data-icon-name="github" aria-hidden="true"></span>' ],
	'table'       => [ '<table><thead><tr><th scope="col">a</th></tr></thead><tbody><tr><td colspan="2">b</td></tr></tbody></table>' ],
	'inline math' => [ 'Euler <math data-latex="e^{i\pi}"><msup><mi>e</mi><mrow><mi>i</mi><mo>π</mo></mrow></msup></math> rocks' ],
	'var name'    => [ '<span style="--Foo-Bar:1px">x</span>' ],
	'non-ascii'   => [ '<p>Héllo — 日本語 🎉</p>' ],
	'escaped'     => [ '<code>&lt;script&gt;alert(1)&lt;/script&gt;</code>' ],
] );

it( 'keeps a custom inline icon, with the SVG sanitized', function (): void {
	$output = sanitizeBlockHtml( '<span class="ap-inline-icon"><svg viewBox="0 0 24 24" onload="alert(1)"><path d="M0 0h24"/><script>1</script></svg></span>' );

	expect( $output )->toContain( 'viewBox="0 0 24 24"' )
		->toContain( '<path' )
		->not->toContain( 'onload' )
		->not->toContain( '<script' );
} );

it( 'keeps the words after an empty icon', function (): void {
	$html = 'A <span><svg viewBox="0 0 24 24"></svg></span> middle <span><svg viewBox="0 0 1 1"></svg></span> end';

	expect( strip_tags( sanitizeBlockHtml( sanitizeBlockHtml( $html ) ) ) )->toBe( 'A  middle  end' );
} );

it( 'survives content over the limits of a default parse', function (): void {
	$image = '<img src="data:image/png;base64,' . str_repeat( 'A', 11 * 1024 * 1024 ) . '" alt="x">';
	$deep  = str_repeat( '<div>', 300 ) . 'kept' . str_repeat( '</div>', 300 );
	$long  = '<p>Keep me</p>' . str_repeat( ' ', 1500000 ) . '<svg viewBox="0 0 1 1"></svg>';

	expect( sanitizeBlockHtml( $image ) )->toBe( $image )
		->and( strip_tags( sanitizeBlockHtml( $deep ) ) )->toBe( 'kept' )
		->and( sanitizeBlockHtml( $long ) )->toContain( 'Keep me' );
} );

it( 'keeps script out of math', function ( string $html ): void {
	expect( sanitizeBlockHtml( $html ) )->not->toContain( 'alert' );
} )->with( [
	'href'           => [ '<math><mrow href="javascript:alert(1)">x</mrow></math>' ],
	'annotation-xml' => [ '<math><annotation-xml encoding="text/html"><script>alert(1)</script></annotation-xml></math>' ],
	'handler'        => [ '<math onmouseover="alert(1)"><mi>x</mi></math>' ],
] );

it( 'keeps a valid icon and its text after an unclosed svg', function (): void {
	$output = sanitizeBlockHtml( 'before <svg> middle <svg viewBox="0 0 1 1"><path d="M0 0"/></svg> after' );

	expect( $output )->toContain( 'before' )
		->toContain( 'middle' )
		->toContain( 'viewBox="0 0 1 1"' )
		->toContain( 'after' );
} );

it( 'keeps the text after a stray opening tag at the end', function (): void {
	expect( sanitizeBlockHtml( 'before <svg> after' ) )->toBe( 'before  after' );
} );

it( 'drops an unclosed svg', function (): void {
	expect( sanitizeBlockHtml( 'a<svg><script>1</script>' ) )->toBe( 'a' );
} );

it( 'filters inline styles by declaration', function (): void {
	expect( sanitizeBlockHtml( '<span style="color:red;position:fixed;background:url(https://evil.test/x);font-size:2em">x</span>' ) )
		->toBe( '<span style="color:red;font-size:2em">x</span>' );

	expect( sanitizeBlockHtml( '<span style="position:fixed">x</span>' ) )->toBe( '<span>x</span>' );
} );

it( 'leaves strings with no markup alone', function ( string $value ): void {
	expect( sanitizeBlockHtml( $value ) )->toBe( $value );
} )->with( [ 'a < b', 'x &amp; y', '1<2', 'plain', '' ] );

it( 'is idempotent', function ( string $html ): void {
	$once = sanitizeBlockHtml( $html );

	expect( sanitizeBlockHtml( $once ) )->toBe( $once );
} )->with( [
	'<p>a &lt;b&gt; &amp; <strong>c</strong><br>d</p>',
	'<a title="&quot;x&quot;" href="/a">y</a>',
	'<img src="/a.png" alt="&lt;img&gt;">',
	'A <span class="ap-inline-icon"><svg aria-hidden="true" viewBox="0 0 24 24"></svg></span> middle <span><svg viewBox="0 0 1 1"><path d="M0 0"/></svg></span> end',
	'<svg viewBox="0 0 1 1"/>',
] );

it( 'can not be broken out of through a parser difference', function ( string $html ): void {
	$output = sanitizeBlockHtml( $html );

	$document = new DOMDocument();
	libxml_use_internal_errors( true );
	$document->loadHTML( '<html><body>' . $output . '</body></html>' );
	libxml_clear_errors();

	$handlers = 0;

	foreach ( $document->getElementsByTagName( '*' ) as $element ) {
		foreach ( $element->attributes as $attribute ) {
			$handlers += str_starts_with( strtolower( $attribute->nodeName ), 'on' ) ? 1 : 0;
		}
	}

	expect( $document->getElementsByTagName( 'script' )->length )->toBe( 0 )
		->and( $handlers )->toBe( 0 )
		->and( $output )->not->toContain( '<!--' )
		->and( $output )->not->toContain( '<?' )
		->and( $output )->not->toContain( '<![' );
} )->with( [
	'empty comment'       => [ '<!--><img src=x onerror=1>-->' ],
	'bogus comment'       => [ '<!x><img src=x onerror=1>' ],
	'processing'          => [ '<?php echo 1; ?><img src=x onerror=1>' ],
	'mxss title'          => [ '<title><img src=x title="</title><img src=x onerror=1>"></title>' ],
	'mxss noscript'       => [ '<noscript><p title="</noscript><img src=x onerror=1>"></noscript>' ],
	'math mtext'          => [ '<math><mtext><table><mglyph><style><!--</style><img title="--&gt;&lt;img src=1 onerror=alert(1)&gt;">' ],
	'svg style'           => [ '<svg></p><style><a id="</style><img src=1 onerror=alert(1)>">' ],
	'attribute breakout'  => [ '<a title=\'"><img src=x onerror=1>\'>x</a>' ],
	'nested script'       => [ '<scr<script>ipt>alert(1)</scr</script>ipt>' ],
	'cdata'               => [ '<![CDATA[<img src=x onerror=1>]]>' ],
	'comment in svg'      => [ '<svg><!--><img src=x onerror=alert(1)>--></svg>' ],
	'nested svg comment'  => [ '<svg><g><!--><img src=x onerror=alert(1)>--></g></svg>' ],
	'pi in svg'           => [ '<svg><?x ><img src=x onerror=alert(1)>?></svg>' ],
	'cdata in svg'        => [ '<svg><text><![CDATA[><img src=x onerror=alert(1)>]]></text></svg>' ],
] );

it( 'can be widened by the host', function (): void {
	addFilter( 'ap.visualEditor.contentFilter.allowedHtml', function ( array $tags ): array {
		$tags['iframe'] = [ 'src' ];

		return $tags;
	} );

	expect( sanitizeBlockHtml( '<iframe src="https://example.com/embed" onload="x"></iframe>' ) )
		->toBe( '<iframe src="https://example.com/embed"></iframe>' );

	removeAllFilters( 'ap.visualEditor.contentFilter.allowedHtml' );
} );
