<?php

namespace Ast\Online;

/**
 * Singleton
 */
trait Singleton {

    /**
     * @var
     */
    protected static $instance;

    /**
     * Get instance
     *
     * @return Singleton
     */
    public static function instance(): self
    {
        return static::$instance ?? (static::$instance = static::initInstance());
    }

    /**
     * Initialization of class instance
     *
     * @return static
     */
    private static function initInstance()
    {
        return new static();
    }

    /**
     * Reset instance
     *
     * @return void
     */
    public static function resetInstance(): void
    {
        static::$instance = null;
    }

    /**
     * Disabled by access level
     */
    private function __construct() {}

    /**
     * Disabled by access level
     */
    private function __clone() {}

}
