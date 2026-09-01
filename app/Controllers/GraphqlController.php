<?php
/**
 * File / 文件：app/Controllers/GraphqlController.php
 * EN: Defines the GraphqlController HTTP controller and request/response actions.
 * 中文：定义 GraphqlController HTTP Controller 及其请求/响应操作。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
namespace App\Controllers;

use App\Core\ApiException;
use App\Core\ApiRequest;
use App\Core\Controller;
use App\Core\Logger;
use App\Services\GraphqlEngine;

/**
 * EN: HTTP controller for graphql requests, responses, and server-side authorization.
 * 中文：负责 graphql 请求、响应及服务器端权限控制的 HTTP Controller。
 */
class GraphqlController extends Controller
{
    /**
     * EN: Handle the cors HTTP action for graphql controller and return the appropriate response.
     * 中文：处理 graphql controller 的“cors”HTTP 操作并返回相应响应。
     *
     * @return void No value is returned. / 无返回值。
     */
    public function cors(): void
    {
        try {
            ApiRequest::prepareCors(true);
            http_response_code(204);
            ApiRequest::securityHeaders();
            exit;
        } catch (ApiException $e) {
            $this->graphqlHttpError($e);
        }
    }

    /**
     * EN: Handle a GraphQL HTTP request, enforce request limits, and return a JSON GraphQL response.
     * 中文：处理 GraphQL HTTP 请求、执行请求限制，并返回 JSON 格式的 GraphQL 响应。
     *
     * @return void No value is returned. / 无返回值。
     *
     * @throws ApiException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
     */
    public function handle(): void
    {
        try {
            ApiRequest::prepareCors();
            $body = ApiRequest::jsonBody();
            $query = (string)($body['query'] ?? '');
            if (trim($query) === '') {
                throw new ApiException(400, 'graphql_query_required', 'The GraphQL `query` string is required.');
            }
            $variables = $body['variables'] ?? [];
            if ($variables === null) {
                $variables = [];
            }
            if (!is_array($variables)) {
                throw new ApiException(400, 'graphql_variables_invalid', 'GraphQL `variables` must be an object.');
            }
            $operationName = isset($body['operationName']) && $body['operationName'] !== null
                ? trim((string)$body['operationName'])
                : null;

            $result = (new GraphqlEngine())->execute($query, $variables, $operationName);
            http_response_code(200);
            header('Content-Type: application/json; charset=utf-8');
            ApiRequest::securityHeaders();
            echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            exit;
        } catch (ApiException $e) {
            $this->graphqlHttpError($e);
        } catch (\Throwable $e) {
            Logger::exception($e, 'graphql', ['event' => 'GraphQL HTTP execution failed'], 'error');
            $this->graphqlHttpError(new ApiException(500, 'internal_error', 'The GraphQL request failed.'));
        }
    }

    /**
     * EN: Perform the graphql http error operation.
     * 中文：执行“graphql http error”操作。
     *
     * @param ApiException $e Exception being handled or logged. / 正在处理或记录的异常对象。
     *
     * @return void No value is returned. / 无返回值。
     */
    private function graphqlHttpError(ApiException $e): void
    {
        Logger::log(
            $e->status() >= 500 ? 'error' : 'warning',
            'GraphQL request rejected.',
            ['status' => $e->status(), 'error_code' => $e->apiCode()],
            'graphql'
        );
        http_response_code($e->status());
        header('Content-Type: application/json; charset=utf-8');
        ApiRequest::securityHeaders();
        echo json_encode([
            'data' => null,
            'errors' => [[
                'message' => $e->getMessage(),
                'extensions' => [
                    'code' => $e->apiCode(),
                    'httpStatus' => $e->status(),
                    'requestId' => Logger::requestId(),
                ],
            ]],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}
