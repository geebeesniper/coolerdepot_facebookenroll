<?php
/**
 * File / 文件：app/Controllers/ExternalApiController.php
 * EN: Defines the ExternalApiController HTTP controller and request/response actions.
 * 中文：定义 ExternalApiController HTTP Controller 及其请求/响应操作。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
namespace App\Controllers;

use App\Core\ApiAuth;
use App\Core\ApiException;
use App\Core\ApiRequest;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Logger;
use App\Models\User;
use App\Services\ExternalAuthService;

/**
 * EN: HTTP controller for external api requests, responses, and server-side authorization.
 * 中文：负责 external api 请求、响应及服务器端权限控制的 HTTP Controller。
 */
class ExternalApiController extends Controller
{
    /**
     * EN: Handle the cors HTTP action for external api controller and return the appropriate response.
     * 中文：处理 external api controller 的“cors”HTTP 操作并返回相应响应。
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
            $this->apiError($e);
        }
    }

    /**
     * EN: Return the external API version and a non-sensitive database readiness result.
     * 中文：返回外部 API 版本以及不包含敏感信息的数据库就绪状态。
     *
     * @return void No value is returned. / 无返回值。
     */
    public function health(): void
    {
        try {
            ApiRequest::prepareCors();
            global $config;
            Database::connection()->query('SELECT 1')->fetchColumn();
            $this->json([
                'ok' => true,
                'api_version' => 'v1',
                'app_version' => (string)$config['app']['version'],
                'database' => 'ready',
            ]);
        } catch (ApiException $e) {
            $this->apiError($e);
        } catch (\Throwable $e) {
            Logger::exception($e, 'api', ['event' => 'REST health check failed'], 'error');
            $this->json([
                'ok' => false,
                'error' => 'service_unavailable',
                'message' => 'The API health check failed.',
            ], 503);
        }
    }

    /**
     * EN: Exchange a signed external identity payload for a short-lived REST/GraphQL Bearer token.
     * 中文：将外部系统签名的身份载荷交换为短期 REST/GraphQL Bearer Token。
     *
     * @return void No value is returned. / 无返回值。
     */
    public function exchange(): void
    {
        try {
            ApiRequest::prepareCors();
            $payload = ApiRequest::jsonBody();
            $user = (new ExternalAuthService())->accept($payload);
            $issued = ApiAuth::issue($user, 'rest_signed_exchange');
            $this->json([
                'ok' => true,
                'access_token' => $issued['access_token'],
                'token_type' => $issued['token_type'],
                'expires_in' => $issued['expires_in'],
                'expires_at' => $issued['expires_at'],
                'user' => ApiAuth::publicUser($user),
            ]);
        } catch (ApiException $e) {
            $this->apiError($e);
        } catch (\PDOException $e) {
            Logger::exception($e, 'auth', ['event' => 'REST signed exchange database failure'], 'error');
            $this->json([
                'ok' => false,
                'error' => 'auth_service_unavailable',
                'message' => 'The authentication service is temporarily unavailable.',
            ], 503);
        } catch (\RuntimeException $e) {
            Logger::exception($e, 'auth', ['event' => 'REST signed exchange rejected'], 'warning');
            $this->json([
                'ok' => false,
                'error' => 'signed_exchange_rejected',
                'message' => $e->getMessage(),
            ], 401);
        } catch (\Throwable $e) {
            Logger::exception($e, 'auth', ['event' => 'REST signed exchange failed'], 'error');
            $this->json([
                'ok' => false,
                'error' => 'auth_service_unavailable',
                'message' => 'The authentication service is temporarily unavailable.',
            ], 503);
        }
    }

    /**
     * EN: Return the identity associated with the current API Bearer token.
     * 中文：返回当前 API Bearer Token 对应的用户身份。
     *
     * @return void No value is returned. / 无返回值。
     */
    public function me(): void
    {
        try {
            ApiRequest::prepareCors();
            $user = ApiAuth::requireLogin();
            $this->json(['ok' => true, 'user' => ApiAuth::publicUser($user)]);
        } catch (ApiException $e) {
            $this->apiError($e);
        }
    }

    /**
     * EN: Revoke the current API Bearer token.
     * 中文：撤销当前 API Bearer Token。
     *
     * @return void No value is returned. / 无返回值。
     */
    public function logout(): void
    {
        try {
            ApiRequest::prepareCors();
            ApiAuth::requireLogin();
            ApiAuth::revokeCurrent();
            $this->json(['ok' => true]);
        } catch (ApiException $e) {
            $this->apiError($e);
        }
    }

    /**
     * EN: Handle the admin users HTTP action for external api controller and return the appropriate response.
     * 中文：处理 external api controller 的“admin users”HTTP 操作并返回相应响应。
     *
     * @return void No value is returned. / 无返回值。
     */
    public function adminUsers(): void
    {
        try {
            ApiRequest::prepareCors();
            ApiAuth::requireRole('admin');
            $users = array_map(
                static fn(array $row): array => ApiAuth::publicUser($row),
                User::allForApi()
            );
            $this->json(['ok' => true, 'users' => $users]);
        } catch (ApiException $e) {
            $this->apiError($e);
        }
    }

    /**
     * EN: Handle the sales profile HTTP action for external api controller and return the appropriate response.
     * 中文：处理 external api controller 的“sales profile”HTTP 操作并返回相应响应。
     *
     * @return void No value is returned. / 无返回值。
     */
    public function salesProfile(): void
    {
        try {
            ApiRequest::prepareCors();
            $user = ApiAuth::requireRole('sales');
            $this->json(['ok' => true, 'user' => ApiAuth::publicUser($user)]);
        } catch (ApiException $e) {
            $this->apiError($e);
        }
    }

    /**
     * EN: Perform the api error operation.
     * 中文：执行“api error”操作。
     *
     * @param ApiException $e Exception being handled or logged. / 正在处理或记录的异常对象。
     *
     * @return void No value is returned. / 无返回值。
     */
    private function apiError(ApiException $e): void
    {
        $this->json([
            'ok' => false,
            'error' => $e->apiCode(),
            'message' => $e->getMessage(),
        ], $e->status());
    }
}
