<?php
/**
 * File / 文件：app/Core/ApiException.php
 * EN: Defines the shared ApiException core infrastructure component.
 * 中文：定义全应用共享的 ApiException 核心基础设施组件。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
namespace App\Core;

/**
 * EN: Exception type used to report api failures with structured application context.
 * 中文：用于携带结构化应用上下文并报告 api 失败的异常类型。
 */
class ApiException extends \RuntimeException
{
    private int $status;
    private string $apiCode;

    /**
     * EN: Initialize ApiException with the dependencies and configuration required by later operations.
     * 中文：初始化 ApiException，保存后续操作所需的依赖与配置。
     *
     * @param int $status Status value applied or evaluated by the operation. / 本操作设置或判断的状态值。
     * @param string $apiCode Api code value used by this operation. / 本操作使用的“api code”参数值。
     * @param string $message Human-readable message associated with the result or log entry. / 与结果或日志记录关联的可读消息。
     *
     * @return void No value is returned. / 无返回值。
     */
    public function __construct(int $status, string $apiCode, string $message)
    {
        parent::__construct($message);
        $this->status = $status;
        $this->apiCode = $apiCode;
    }

    /**
     * EN: Perform the status core operation provided by api exception.
     * 中文：执行 api exception 提供的“status”核心操作。
     *
     * @return int Numeric result produced by this operation. / 本操作生成的数字结果。
     */
    public function status(): int
    {
        return $this->status;
    }

    /**
     * EN: Perform the api code core operation provided by api exception.
     * 中文：执行 api exception 提供的“api code”核心操作。
     *
     * @return string String result produced by this operation. / 本操作生成的字符串结果。
     */
    public function apiCode(): string
    {
        return $this->apiCode;
    }
}
