<?php
namespace ybrenLib\apolloClient\core\cache;

interface CacheDriver{

    function get($key);

    function set($key , $data);

    function lock($key);

    function unlock($key);
}