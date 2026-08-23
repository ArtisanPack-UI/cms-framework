@extends('cms::admin.layouts.app')

@section('title', __('Hello World'))

@section('content')
    <div class="hello-world">
        <h1>{{ __('Hello World') }}</h1>

        <p>
            {{ __('This admin page is registered by the hello-world plugin. It uses the framework\'s Blade admin-page path.') }}
        </p>

        <p>
            {{ __('The plugin also declares a federated_module entry for hosts that support runtime-loaded React admin pages (see plugin.json). Blade and federated flavors coexist — the host chooses which to render based on its own capabilities.') }}
        </p>
    </div>
@endsection
