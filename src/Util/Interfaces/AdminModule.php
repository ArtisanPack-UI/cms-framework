<?php

namespace ArtisanPackUI\CMSFramework\Util\Interfaces;

interface AdminModule extends Module
{
	public function adminInit(): void;
}