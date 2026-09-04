<?php
/** V0.2.125 static integration contract: provider success/date/unavailable responsibilities stay separated. */
$root=dirname(__DIR__);
$chain=(string)file_get_contents($root.'/app/Services/FacebookMarketplaceProviderChain.php');
$inspect=(string)file_get_contents($root.'/app/Services/PostInspector.php');
$js=(string)file_get_contents($root.'/public/assets/app.js');
$providers='';
foreach([
    'RegistryBrightDataMarketplaceProvider.php',
    'RegistryApifyMarketplaceProvider.php',
    'RegistryScrapeCreatorsMarketplaceProvider.php',
    'GenericJsonMarketplaceProvider.php',
] as $file){$providers.=(string)file_get_contents($root.'/app/Services/'.$file);}

$checks=[
    'chain uses providerUsable'=>str_contains($chain,'FacebookListingMetadata::providerUsable'),
    'chain preserves explicit unavailable'=>str_contains($chain,'FacebookListingUnavailableException'),
    'inspector has LISTING_UNAVAILABLE'=>str_contains($inspect,"'LISTING_UNAVAILABLE'"),
    'inspector requires normalized published_date'=>str_contains($inspect,"if (\$publishedDate === '')"),
    'inspector feeds normalized date downstream'=>str_contains($inspect,"\$publishedAt !== '' ? \$publishedAt : \$publishedDate"),
    'provider adapters use shared normalizer'=>substr_count($providers,'FacebookListingMetadata::normalizeItem')>=4,
    'provider adapters detect unavailable'=>substr_count($providers,'FacebookListingMetadata::unavailableReason')>=4,
    'queue has unavailable taxonomy'=>str_contains($js,"code==='LISTING_UNAVAILABLE'")&&str_contains($js,'queueErrorUnavailable'),
];
$failed=array_keys(array_filter($checks,fn($ok)=>!$ok));
if($failed){fwrite(STDERR,"FAILED\n - ".implode("\n - ",$failed)."\n");exit(1);}
echo "OK Facebook verification classification v0.2.125\n";
