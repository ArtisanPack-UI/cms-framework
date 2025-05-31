<?php


namespace ArtisanPackUI\CMSFramework\Util\Interfaces;

interface Module
{
	public function getSlug(): string;

	public function functions(): array;

	public function init(): void;
}