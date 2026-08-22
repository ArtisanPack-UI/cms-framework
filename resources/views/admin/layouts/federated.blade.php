{{--
    Default route action for the federated ( React ) flavor of
    `PluginServiceProvider::registerAdminPage()`.

    A `component`-only admin page has no server-rendered body of its own — the
    real UI is a Module Federation module only the host's federation runtime can
    resolve. This layout renders inside the framework's admin chrome
    (`cms::admin.layouts.app`) and emits a single mount point,
    `<div data-cms-federated-module="…">`, for that runtime to hydrate.

    It is the shipped default behind the `ap.cmsFramework.admin.federatedPageAction`
    filter: a federation host that mounts components differently overrides the
    filter and never renders this file. Hosts that only want to restyle the
    shell can publish it with

        php artisan vendor:publish --tag=cms-views

    which writes to `resources/views/vendor/cms/`, where Laravel resolves it
    ahead of this copy.

    Variables: `component` ( the federation identifier, rendered into a data
    attribute ) and `title` ( optional, plain text ).

    @since 2.9.0
--}}
@extends( 'cms::admin.layouts.app' )

@section( 'title', $title ?? __( 'Admin' ) )

@section( 'content' )
	<div data-cms-federated-module="{{ $component }}"></div>
@endsection
