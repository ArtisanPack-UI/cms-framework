<?php

namespace ArtisanPackUI\CMSFramework\Util\Interfaces;

use ArtisanPackUI\CMSFramework\Util\Interfaces\Module;

interface PublicModule extends Module
{
    public function publicInit(): void;
}
