<?php
return [
	'site'  => [
		'name'     => 'ArtisanPack UI CMS Framework',
		'tagline'  => 'A flexible framework to build a CMS for your website.',
		'url'      => env( 'APP_URL', 'http://localhost' ),
		'timezone' => 'UTC',
		'locale'   => 'en',
	],
	'paths' => [
		'plugins' => base_path( 'plugins' ), // Path name changed from 'cms-plugins'
		'themes'  => base_path( 'themes' ),   // Path name changed from 'cms-themes'
	],
];