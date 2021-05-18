<?php
namespace ybrenLib\apolloClient\core\cache;

class FileStorgeDriver implements CacheDriver {

    private $fileName;

    private static $cacheStorge = [];

    public function __construct($appId){
        $this->fileName = ROOT_PATH . "configservice".DIRECTORY_SEPARATOR.$appId;
        if(!is_dir($this->fileName)){
            mkdir($this->fileName , 0777 , true);
        }
    }

    public function get($key){
        $fileName = $this->fileName . DIRECTORY_SEPARATOR . $key;
        if(isset(self::$cacheStorge[$fileName]) && !empty(self::$cacheStorge[$fileName])){
            return self::$cacheStorge[$fileName];
        }
        if(!file_exists($fileName)){
            return null;
        }else{
            return unserialize(file_get_contents($fileName));
        }
    }

    public function set($key , $data){
        $fileName = $this->fileName . DIRECTORY_SEPARATOR . $key;
        $seriData = serialize($data);
        file_put_contents($fileName , $seriData);
        self::$cacheStorge[$fileName] = $data;
    }

    public function lock($key){
        $fileName = $this->getLockFileName($key);
        if(is_file($fileName)){
            return false;
        }
        file_put_contents($fileName , time());
        return true;
    }

    public function unlock($key){
        try{
            @unlink($this->getLockFileName($key));
        }catch (\Throwable $e){
        }
    }

    private function getLockFileName($key){
        $lockPath = $this->fileName . DIRECTORY_SEPARATOR . "lock";
        if(!is_dir($lockPath)){
            @mkdir($lockPath , 0777 , true);
        }
        return $lockPath . DIRECTORY_SEPARATOR . $key;
    }
}