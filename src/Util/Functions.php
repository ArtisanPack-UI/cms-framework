<?php

namespace ArtisanPackUI\CMSFramework\Util;

use ArtisanPackUI\CMSFramework\Util\Interfaces\Module;

class Functions
{
    protected array $functions = [];

    public function __construct( array $modules = [] )
    {
        foreach ( $modules as $module ) {
            $this->setFunctions( $module );
        }
    }

    protected function setFunctions( Module $module ): void
    {
        $tags = $module->functions();

        foreach ( $tags as $method_name => $callback ) {
            if ( is_callable( $callback ) ) {
                $callback                        = array( 'callback' => $callback );
                $this->functions[ $method_name ] = $callback;
            }
        }
    }

    public function __call( string $method, array $args )
    {
        return call_user_func_array( $this->functions[ $method ]['callback'], $args );
    }
}
