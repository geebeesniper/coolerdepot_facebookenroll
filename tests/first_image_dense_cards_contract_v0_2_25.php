<?php
/**
 * File / 文件：tests/first_image_dense_cards_contract_v0_2_25.php
 * EN: Regression contract for first-image-only verification and dense responsive card grids.
 * 中文：首图验证与紧凑响应式卡片网格的回归契约测试。
 */
$root=dirname(__DIR__);
$failures=[];
$expect=static function(bool $condition,string $message)use(&$failures):void{
    if(!$condition){$failures[]=$message;}
};
$read=static function(string $relative)use($root):string{
    $text=@file_get_contents($root.'/'.$relative);
    if($text===false){throw new RuntimeException('Could not read '.$relative);}
    return $text;
};

$expect(trim($read('VERSION'))==='0.2.25','VERSION must be 0.2.25.');

$extractor=$read('app/Services/MarketplaceImageExtractor.php');
$expect(str_contains($extractor,'array_slice(array_keys($urls), 0, 1)'), 'Marketplace extractor must return only the first image.');

$duplicate=$read('app/Services/DuplicateIndex.php');
$expect(str_contains($duplicate,'array_slice(ImageFingerprint::urls($meta),0,1)'), 'Duplicate verification must inspect only the first image.');

$api=$read('app/Controllers/ApiController.php');
$expect(substr_count($api, "0, 1)")>=3, 'Inspection API responses must expose only one image.');

$js=$read('public/assets/app.js');
$expect(str_contains($js,'resultImages.slice(0,1)'), 'Sales verification preview must render only the first image.');

$css=$read('public/assets/app.css');
foreach([
    'grid-template-columns:repeat(6,minmax(0,1fr))',
    '@media(max-width:599px)',
    'grid-template-columns:repeat(2,minmax(0,1fr))',
    '@media(max-width:640px)',
] as $needle){
    $expect(str_contains($css,$needle),'Missing responsive card rule: '.$needle);
}

if($failures){
    fwrite(STDERR,"FAIL\n".implode("\n",$failures)."\n");
    exit(1);
}

echo "PASS v0.2.25 first-image/dense-card contract\n";
