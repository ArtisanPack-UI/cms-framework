<?php

namespace ArtisanPackUI\CMSFramework\Http\Controllers;

use ArtisanPackUI\CMSFramework\Http\Requests\AuditLogRequest;
use ArtisanPackUI\CMSFramework\Http\Resources\AuditLogResource;
use ArtisanPackUI\CMSFramework\Models\AuditLog;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class AuditLogController
{
	use AuthorizesRequests;

	public function index()
	{
		$this->authorize( 'viewAny', AuditLog::class );

		return AuditLogResource::collection( AuditLog::all() );
	}

	public function store( AuditLogRequest $request )
	{
		$this->authorize( 'create', AuditLog::class );

		return new AuditLogResource( AuditLog::create( $request->validated() ) );
	}

	public function show( AuditLog $auditLog )
	{
		$this->authorize( 'view', $auditLog );

		return new AuditLogResource( $auditLog );
	}

	public function update( AuditLogRequest $request, AuditLog $auditLog )
	{
		$this->authorize( 'update', $auditLog );

		$auditLog->update( $request->validated() );

		return new AuditLogResource( $auditLog );
	}

	public function destroy( AuditLog $auditLog )
	{
		$this->authorize( 'delete', $auditLog );

		$auditLog->delete();

		return response()->json();
	}
}
