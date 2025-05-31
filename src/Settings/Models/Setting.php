<?php

namespace ArtisanPackUI\CMSFramework\Settings\Models;

use ArtisanPackUI\CMSFramework\Settings\Factories\SettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
	/** @use HasFactory<SettingFactory> */
	use HasFactory;

	/**
	 * The columns to protect from mass assignment.
	 *
	 * @since 1.0.0
	 * @var string[]
	 */
	protected $guarded = [
		'id',
	];
}