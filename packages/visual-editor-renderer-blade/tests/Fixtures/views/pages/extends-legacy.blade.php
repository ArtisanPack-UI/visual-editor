@extends( 've-test::layouts.legacy' )

@section( 'content' )
<main>
<x-ve-blocks :tree="$tree" />
</main>
@endsection
