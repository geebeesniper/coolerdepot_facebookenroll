<?php
/**
 * File / 文件：app/Services/HtmlNoteSanitizer.php
 * EN: Application service for reusable business or integration logic.
 * 中文：该文件负责可复用的业务逻辑或外部集成服务。
 * Maintenance / 维护：Keep security, logging, and responsive behavior explicit when modifying this file.
 * 维护要求：修改本文件时应明确保留安全、日志与响应式行为。
 */
namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;

class HtmlNoteSanitizer
{
    private const ALLOWED_TAGS = ['p','br','strong','b','em','i','u','s','ul','ol','li','blockquote','a','h3','h4','img'];
    private const ALLOWED_ATTRS = [
        'a'=>['href','title','target','rel'],
        'img'=>['src','alt','title'],
    ];
    private const DROP_WITH_CONTENT = ['script','style','iframe','object','embed','form','input','button','textarea','select'];

    /**
     * EN: Removes or cleans data/state for `clean` (clean).
     * 中文：删除或清理 `clean`（clean）相关的数据或状态。
     */
    public static function clean(?string $html): string
    {
        $html=trim((string)$html);
        if($html==='') return '';
        libxml_use_internal_errors(true);
        $doc=new DOMDocument('1.0','UTF-8');
        $wrapped='<!doctype html><html><body><div id="cdsp-note-root">'.$html.'</div></body></html>';
        $doc->loadHTML(mb_convert_encoding($wrapped,'HTML-ENTITIES','UTF-8'),LIBXML_HTML_NOIMPLIED|LIBXML_HTML_NODEFDTD);
        $root=$doc->getElementById('cdsp-note-root');
        if(!$root){libxml_clear_errors();return '';}
        self::sanitizeChildren($root);
        $out='';
        foreach($root->childNodes as $child){$out.=$doc->saveHTML($child);}
        libxml_clear_errors();
        return trim($out);
    }

    /**
     * EN: Implements the application operation `sanitizeChildren` (sanitize Children).
     * 中文：实现应用操作 `sanitizeChildren`（sanitize Children）。
     */
    private static function sanitizeChildren(DOMNode $parent): void
    {
        $children=[];foreach($parent->childNodes as $child){$children[]=$child;}
        foreach($children as $child){
            if($child->nodeType===XML_COMMENT_NODE){$parent->removeChild($child);continue;}
            if($child->nodeType!==XML_ELEMENT_NODE) continue;
            /** @var DOMElement $child */
            $tag=strtolower($child->tagName);
            if(in_array($tag,self::DROP_WITH_CONTENT,true)){$parent->removeChild($child);continue;}
            if(!in_array($tag,self::ALLOWED_TAGS,true)){
                self::sanitizeChildren($child);
                while($child->firstChild){$parent->insertBefore($child->firstChild,$child);}
                $parent->removeChild($child);continue;
            }
            $allowed=self::ALLOWED_ATTRS[$tag]??[];$remove=[];
            foreach($child->attributes as $attr){
                $name=strtolower($attr->name);
                if(!in_array($name,$allowed,true)){$remove[]=$name;continue;}
                if($tag==='a'&&$name==='href'){
                    $href=trim($attr->value);
                    if(!preg_match('~^(https?://|mailto:|/|#)~i',$href))$remove[]=$name;
                }
                if($tag==='img'&&$name==='src'){
                    $src=trim($attr->value);
                    if(!preg_match('~^(https://|/)~i',$src))$remove[]=$name;
                }
            }
            foreach($remove as $name){$child->removeAttribute($name);}
            if($tag==='a'){
                $child->setAttribute('rel','noopener noreferrer');
                if($child->hasAttribute('target'))$child->setAttribute('target','_blank');
            }
            if($tag==='img'){
                if(!$child->hasAttribute('src')){$parent->removeChild($child);continue;}
                if(!$child->hasAttribute('alt'))$child->setAttribute('alt','');
            }
            self::sanitizeChildren($child);
        }
    }
}
