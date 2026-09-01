<?php
/**
 * File / 文件：app/Services/HtmlNoteSanitizer.php
 * EN: Defines the HtmlNoteSanitizer service used by application business, security, or provider integration flows.
 * 中文：定义 HtmlNoteSanitizer 服务，用于应用业务、安全或 Provider 集成流程。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * EN: Application service that encapsulates html note sanitizer business, security, or integration behavior.
 * 中文：封装 html note sanitizer 业务、安全或外部集成行为的应用服务。
 */
class HtmlNoteSanitizer
{
    private const ALLOWED_TAGS = ['p','br','strong','b','em','i','u','s','ul','ol','li','blockquote','a','h3','h4','img'];
    private const ALLOWED_ATTRS = [
        'a'=>['href','title','target','rel'],
        'img'=>['src','alt','title'],
    ];
    private const DROP_WITH_CONTENT = ['script','style','iframe','object','embed','form','input','button','textarea','select'];

    /**
     * EN: Perform the clean operation implemented by html note sanitizer.
     * 中文：执行 html note sanitizer 实现的“clean”操作。
     *
     * @param ?string $html HTML content processed by the operation. / 本操作处理的 HTML 内容。
     *
     * @return string String result produced by this operation. / 本操作生成的字符串结果。
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
     * EN: Normalize or format the sanitize children operation implemented by html note sanitizer.
     * 中文：规范化或格式化 html note sanitizer 实现的“sanitize children”操作。
     *
     * @param DOMNode $parent Parent value used by this operation. / 本操作使用的“parent”参数值。
     *
     * @return void No value is returned. / 无返回值。
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
