@extends( 've-test::layouts.bare' )

@section( 'content' )
<main>
<x-ve-blocks :tree="$tree" />
</main>
@endsection
