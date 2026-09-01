<?php
/**
 * File / 文件：app/Services/ProviderValidationException.php
 * EN: Application service for reusable business or integration logic.
 * 中文：该文件负责可复用的业务逻辑或外部集成服务。
 * Maintenance / 维护：Keep security, logging, and responsive behavior explicit when modifying this file.
 * 维护要求：修改本文件时应明确保留安全、日志与响应式行为。
 */
namespace App\Services;

class ProviderValidationException extends \RuntimeException
{
    private string $field;

    /**
     * EN: `__construct` initializes this object and its required dependencies/state.
     * 中文：`__construct` 用于初始化当前对象及其所需依赖与状态。
     */
    public function __construct(string $field, string $message)
    {
        parent::__construct($message);
        $this->field = $field;
    }

    /**
     * EN: Implements the application operation `field` (field).
     * 中文：实现应用操作 `field`（field）。
     */
    public function field(): string
    {
        return $this->field;
    }
}
