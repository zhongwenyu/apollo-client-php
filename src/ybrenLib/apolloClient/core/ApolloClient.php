<?php
namespace ybrenLib\apolloClient\core;

use GuzzleHttp\Client;
use ybrenLib\apolloClient\core\cache\CacheDriver;
use ybrenLib\apolloClient\core\cache\FileStorgeDriver;
use ybrenLib\apolloClient\core\exception\ConfigException;
use ybrenLib\apolloClient\core\utils\AuthorizationUtil;
use ybrenLib\logger\utils\TimeUtil;
use ybrenLib\registerCenter\RegisterCenterFactory;

class ApolloClient{

    private $configServer;
    private $appId;
    private $clusterName;
    private $cacheTimeout;  // 缓存时间
    private $httpRequestTimeout = 1;
    private $configUrl = "/configfiles/json/%s/%s/%s";
    private $authorizationFormat = "Apollo %s:%s";
    private $config = [];

    /**
     * @var Client
     */
    private $httpClient;

    /**
     * @var CacheDriver
     */
    private $cacheDriver;

    public function __construct(){
        $this->config = $this->getConfig();
        $this->appId = $this->getAppId();
        $this->clusterName = $this->getClusterName();
        $this->configServer = $this->getConfigServerAddress();
        $this->cacheDriver = new FileStorgeDriver($this->appId);
        $this->httpClient = new Client();
        $this->cacheTimeout = isset($this->config['cacheTimeout']) ? $this->config['cacheTimeout'] : 60;
    }

    /**
     * @param $key
     * @return mixed|null
     */
    public function getAppConfig($key , $class = null){
        return $this->getConfigProperty("application" , $key , $class);
    }

    public function getConfigProperty($namespace , $key = null , $class = null){
        $config = $this->getConfigFile($namespace);
        if(is_null($key)){
            return $config;
        }else if(is_null($class)){
            return $config[$key] ?? null;
        }
        $configClass = new $class();
        $ref = new \ReflectionClass($configClass);
        $propertiesList = $ref->getProperties();
        if(!empty($propertiesList)){
            foreach ($propertiesList as $property){
                $propertyName = $property->getName();
                $configKeyName = $key . "." . $propertyName;
                if(isset($config[$configKeyName])){
                    $setPropertyMethodName = "set" . ucfirst($propertyName);
                    if(method_exists($configClass , $setPropertyMethodName)){
                        $configClass->$setPropertyMethodName($config[$configKeyName]);
                    }
                }
            }
        }
        return $configClass;
    }

    /**
     * @param $namespace
     */
    public function refreshConfig($namespace){
        $time = time();
        $responseContents = $this->getConfigDirectly($namespace);
        $this->cacheDriver->set($namespace , [
            'expire' => $time + $this->cacheTimeout,
            'data' => $responseContents
        ]);
    }

    /**
     * @param $namespace
     * @return mixed
     */
    public function getConfigFile($namespace){
        $time = time();
        $data = $this->cacheDriver->get($namespace);
        $cacheExist = empty($data) ? false : true;
        if($cacheExist && $data['expire'] > $time){
            return $data['data'];
        }

        // 加锁
        if(!$this->cacheDriver->lock($namespace) && $cacheExist){
            return $data['data'];
        }

        try{
            $responseContents = $this->getConfigDirectly($namespace);
            $this->cacheDriver->set($namespace , [
                'expire' => $time + $this->cacheTimeout,
                'data' => $responseContents
            ]);
            return $responseContents;
        }catch (\Exception $e){
            if($cacheExist){
                return $data['data'];
            }else{
                throw $e;
            }
        }finally{
            $this->cacheDriver->unlock($namespace);
        }
    }

    /**
     * @param $namespace
     * @return mixed
     */
    public function getConfigDirectly($namespace){
        $url = sprintf("http://%s" . $this->configUrl , $this->configServer , $this->appId , $this->clusterName ,
        $namespace);

        $headers = [
            'Accept' => 'application/json',
        ];

        if(isset($this->config['apollo.accesskey.secret']) && !empty($this->config['apollo.accesskey.secret'])){
            $timestamp = TimeUtil::getTimestamp();
            $headers['Authorization'] = sprintf($this->authorizationFormat , $this->appId ,
                AuthorizationUtil::buildHttpSignature($this->configUrl , $timestamp , $this->config['apollo.accesskey.secret']));
            $headers['Timestamp'] = $timestamp;
            $headers['Signtype'] = "http";
        }

        $response = $this->httpClient->get($url , [
            'timeout' => $this->httpRequestTimeout,
            'headers' => $headers
        ]);
        $responseContents = json_decode($response->getBody()->getContents() , true);
        return $responseContents;
    }

    /**
     * @return string
     */
    private function getClusterName(){
        return "default";
    }

    /**
     * @return mixed|string
     * @throws ConfigException
     */
    private function getAppId(){
        if(isset($this->config['appId'])){
            return $this->config['appId'];
        }else if(defined("APP_NAME")){
            return APP_NAME;
        }else if(defined("APP_ID")){
            return APP_ID;
        }
        throw new ConfigException("appId is not config");
    }

    private function getConfig(){
        if(defined("ROOT_PATH") && file_exists(ROOT_PATH . "apollo.json")){
            return json_decode(file_get_contents(ROOT_PATH . "apollo.json") , true);
        }
        return [];
    }

    /**
     * 获取apollo服务地址
     * @return string
     */
    private function getConfigServerAddress(){
        if(class_exists("Yaconf")){
            $yaconf = new \Yaconf();
            $apolloServerAddress = $yaconf::get("database.apollo_config_service_url" , null);
            if(!empty($apolloServerAddress)){
                return $apolloServerAddress;
            }
        }

        $instance = RegisterCenterFactory::build()->getInstance("APOLLO-CONFIGSERVICE");
        return $instance->getIp() . ":" . $instance->getPort();
    }
}