<?php
/**
 * File / 文件：app/Services/FacebookListingUnavailableException.php
 * EN: Signals that a Facebook Marketplace provider explicitly confirmed a listing is no longer available.
 * 中文：表示 Facebook Marketplace Provider 已明确确认帖子不可用、已删除或已下架。
 */
namespace App\Services;

final class FacebookListingUnavailableException extends \RuntimeException
{
}
