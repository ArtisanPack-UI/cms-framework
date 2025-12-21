<?php

declare( strict_types = 1 );

if ( ! function_exists( 'invokeMethod' ) ) {
    /**
     * Helper function to invoke protected/private methods for testing.
     *
     * @param  object  $object  The object instance
     * @param  string  $methodName  The method name to invoke
     * @param  array  $parameters  Parameters to pass to the method
     *
     * @return mixed The method's return value
     */
    function invokeMethod( $object, string $methodName, array $parameters = [] ): mixed
    {
        $reflection = new ReflectionClass( get_class( $object ) );
        $method     = $reflection->getMethod( $methodName );
        $method->setAccessible( true );

        return $method->invokeArgs( $object, $parameters );
    }
}
