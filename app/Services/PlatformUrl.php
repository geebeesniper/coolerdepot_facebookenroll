<?php
namespace App\Services;
class PlatformUrl {
    public static function platformFor(string $url):?string{
        $h=strtolower((string)parse_url($url,PHP_URL_HOST));$h=preg_replace('/^www\./','',$h);
        if($h==='facebook.com'||substr($h,-13)==='.facebook.com')return'facebook';
        if($h==='offerup.com'||substr($h,-12)==='.offerup.com'||$h==='offerup.co')return'offerup';
        if($h==='craigslist.org'||substr($h,-15)==='.craigslist.org')return'craigslist';
        return null;
    }
    public static function allowed(string $url,?string $expected=null):bool{
        if(!filter_var($url,FILTER_VALIDATE_URL))return false;
        if(!in_array(strtolower((string)parse_url($url,PHP_URL_SCHEME)),['http','https'],true))return false;
        $p=self::platformFor($url);return $p&&(!$expected||$p===$expected);
    }
    public static function externalId(string $p,string $url,string $html=''):?string{
        $mappings=[
            'facebook'=>['~facebook\.com/marketplace/item/(\d+)~i','~"listing_id":"?(\d+)"?~i'],
            'offerup'=>['~/item/detail/([a-z0-9-]+)~i','~"itemId":"([^"]+)"~i'],
            'craigslist'=>['~/(\d{8,})\.html~i','~posting id:\s*(\d+)~i']
        ];
        foreach($mappings[$p]??[] as $rx)if(preg_match($rx,$url."\n".$html,$m))return$m[1];
        return null;
    }
}
