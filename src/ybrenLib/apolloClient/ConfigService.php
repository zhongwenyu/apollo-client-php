<?php
namespace ybrenLib\apolloClient;

use ybrenLib\apolloClient\core\ApolloClient;

class ConfigService{

    /**
     * @var ApolloClient
     */
    private static $apolloClient = null;

    /**
     * @return ApolloClient
     */
    private static function init(){
        if(is_null(self::$apolloClient)){
            self::$apolloClient = new ApolloClient();
        }
        return self::$apolloClient;
    }

    /**
     * @param $key
     * @param $class
     * @return mixed|string|null
     */
    public static function getAppConfig($key , $class = null){
        $result = self::init()->getAppConfig($key , $class);
        return $result;
    }

    /**
     * @param $namespace
     * @param null $key
     * @param null $class
     * @return mixed|$class
     */
    public static function getConfigProperty($namespace , $key = null , $class = null){
        return self::init()->getConfigProperty($namespace , $key , $class);
    }

    /**
     * @param $namespace
     * @return mixed|null
     */
    public static function getConfigFile($namespace){
        return self::init()->getConfigFile($namespace);
    }

    /**
     * @param $namespace
     */
    public static function refreshConfig($namespace){
        self::init()->refreshConfig($namespace);
    }

    /**
     * @param $namespace
     * @return mixed|null
     */
    public static function getConfigDirectly($namespace){
        return self::init()->getConfigDirectly($namespace);
    }
}