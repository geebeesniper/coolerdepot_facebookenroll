<?php
namespace App\Core;
class Util {
    public static function normalizeText(?string $v):string{
        $v=html_entity_decode(strip_tags((string)$v),ENT_QUOTES|ENT_HTML5,'UTF-8');
        $v=mb_strtolower($v,'UTF-8');
        $v=preg_replace('/[^\p{L}\p{N}]+/u',' ',$v);
        return trim(preg_replace('/\s+/u',' ',$v));
    }
    public static function hashText(?string $v):string{return hash('sha256',self::normalizeText($v));}
    public static function urlHash(?string $v):string{return hash('sha256',strtolower(trim((string)$v)));}
    public static function e($v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
}
