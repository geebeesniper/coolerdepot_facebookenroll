<?php
/**
 * File / 文件：app/Services/ProviderValidationException.php
 * EN: Defines the ProviderValidationException service used by application business, security, or provider integration flows.
 * 中文：定义 ProviderValidationException 服务，用于应用业务、安全或 Provider 集成流程。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
namespace App\Services;

/**
 * EN: Exception type used to report provider validation failures with structured application context.
 * 中文：用于携带结构化应用上下文并报告 provider validation 失败的异常类型。
 */
class ProviderValidationException extends \RuntimeException
{
    private string $field;

    /**
     * EN: Initialize ProviderValidationException with the dependencies and configuration required by later operations.
     * 中文：初始化 ProviderValidationException，保存后续操作所需的依赖与配置。
     *
     * @param string $field Field value used by this operation. / 本操作使用的“field”参数值。
     * @param string $message Human-readable message associated with the result or log entry. / 与结果或日志记录关联的可读消息。
     *
     * @return void No value is returned. / 无返回值。
     */
    public function __construct(string $field, string $message)
    {
        parent::__construct($message);
        $this->field = $field;
    }

    /**
     * EN: Perform the field operation for provider validation exception.
     * 中文：执行 provider validation exception 的“field”操作。
     *
     * @return string String result produced by this operation. / 本操作生成的字符串结果。
     */
    public function field(): string
    {
        return $this->field;
    }
}
