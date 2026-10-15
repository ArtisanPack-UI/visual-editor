@extends( 've-test::layouts.bare-stack' )

@section( 'content' )
<main>
<x-ve-blocks :tree="$tree" />
</main>
@endsection
