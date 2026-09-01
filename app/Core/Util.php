<?php
/**
 * File / 文件：app/Core/Util.php
 * EN: Defines the shared Util core infrastructure component.
 * 中文：定义全应用共享的 Util 核心基础设施组件。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
namespace App\Core;
/**
 * EN: Core infrastructure component that provides util behavior shared across the application.
 * 中文：提供全应用共享 util 能力的核心基础设施组件。
 */
class Util {
    /**
     * EN: Normalize or format the normalize text core operation provided by util.
     * 中文：规范化或格式化 util 提供的“normalize text”核心操作。
     *
     * @param ?string $v V value used by this operation. / 本操作使用的“v”参数值。
     *
     * @return string String result produced by this operation. / 本操作生成的字符串结果。
     */
    public static function normalizeText(?string $v):string{
        $v=html_entity_decode(strip_tags((string)$v),ENT_QUOTES|ENT_HTML5,'UTF-8');
        $v=mb_strtolower($v,'UTF-8');
        $v=preg_replace('/[^\p{L}\p{N}]+/u',' ',$v);
        return trim(preg_replace('/\s+/u',' ',$v));
    }
    /**
     * EN: Check or validate the hash text core operation provided by util.
     * 中文：检查或验证 util 提供的“hash text”核心操作。
     *
     * @param ?string $v V value used by this operation. / 本操作使用的“v”参数值。
     *
     * @return string String result produced by this operation. / 本操作生成的字符串结果。
     */
    public static function hashText(?string $v):string{return hash('sha256',self::normalizeText($v));}
    /**
     * EN: Perform the url hash core operation provided by util.
     * 中文：执行 util 提供的“url hash”核心操作。
     *
     * @param ?string $v V value used by this operation. / 本操作使用的“v”参数值。
     *
     * @return string String result produced by this operation. / 本操作生成的字符串结果。
     */
    public static function urlHash(?string $v):string{return hash('sha256',strtolower(trim((string)$v)));}
    /**
     * EN: Perform the e core operation provided by util.
     * 中文：执行 util 提供的“e”核心操作。
     *
     * @param mixed $v V value used by this operation. / 本操作使用的“v”参数值。
     *
     * @return string String result produced by this operation. / 本操作生成的字符串结果。
     */
    public static function e($v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
}
