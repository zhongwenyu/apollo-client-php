<?php
namespace ybrenLib\apolloClient\core\utils;

class AuthorizationUtil{

    /**
     * @param $path
     * @param $timestampString
     * @param $secret
     * @return string
     */
    public static function buildHttpSignature($path, $timestampString, $secret){
        return md5($path . $timestampString . $secret);
    }
}