<?php

namespace ArtisanPackUI\CMSFramework\Util\Interfaces;

use ArtisanPackUI\CMSFramework\Util\Interfaces\Module;

interface AuthModule extends Module
{
    public function authInit(): void;
}
