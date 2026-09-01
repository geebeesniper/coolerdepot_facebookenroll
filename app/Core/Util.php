<?php
/**
 * File / 文件：app/Core/Util.php
 * EN: Core runtime/infrastructure component used across the application.
 * 中文：该文件是应用全局复用的核心运行时或基础设施组件。
 * Maintenance / 维护：Keep security, logging, and responsive behavior explicit when modifying this file.
 * 维护要求：修改本文件时应明确保留安全、日志与响应式行为。
 */
namespace App\Core;
class Util {
    /**
     * EN: Builds, formats, or transforms data for `normalizeText` (normalize Text).
     * 中文：为 `normalizeText`（normalize Text）构建、格式化或转换数据。
     */
    public static function normalizeText(?string $v):string{
        $v=html_entity_decode(strip_tags((string)$v),ENT_QUOTES|ENT_HTML5,'UTF-8');
        $v=mb_strtolower($v,'UTF-8');
        $v=preg_replace('/[^\p{L}\p{N}]+/u',' ',$v);
        return trim(preg_replace('/\s+/u',' ',$v));
    }
    /**
     * EN: Checks or validates the condition represented by `hashText` (hash Text).
     * 中文：检查或校验 `hashText`（hash Text）所表示的条件。
     */
    public static function hashText(?string $v):string{return hash('sha256',self::normalizeText($v));}
    /**
     * EN: Implements the application operation `urlHash` (url Hash).
     * 中文：实现应用操作 `urlHash`（url Hash）。
     */
    public static function urlHash(?string $v):string{return hash('sha256',strtolower(trim((string)$v)));}
    /**
     * EN: Implements the application operation `e` (e).
     * 中文：实现应用操作 `e`（e）。
     */
    public static function e($v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
}
