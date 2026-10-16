@extends( 've-test::layouts.app' )

@section( 'content' )
<main>
<x-ve-blocks :tree="$tree" />
</main>
@endsection
