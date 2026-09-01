<?php
/**
 * File / 文件：app/Services/GraphqlEngine.php
 * EN: Defines the GraphqlEngine service used by application business, security, or provider integration flows.
 * 中文：定义 GraphqlEngine 服务，用于应用业务、安全或 Provider 集成流程。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */
namespace App\Services;

use App\Core\ApiAuth;
use App\Core\ApiException;
use App\Models\User;

/**
 * EN: Application service that encapsulates graphql engine business, security, or integration behavior.
 * 中文：封装 graphql engine 业务、安全或外部集成行为的应用服务。
 */
class GraphqlEngine
{
    private array $tokens = [];
    private int $pos = 0;
    private array $variables = [];
    private int $fieldCount = 0;
    private int $maxDepth = 8;
    private int $maxFields = 50;
    private int $maxTokens = 2000;
    private int $maxOperations = 5;

    /**
     * EN: Parse, validate, and execute a GraphQL request against the supported authentication/RBAC schema.
     * 中文：解析、验证并执行针对当前认证/RBAC Schema 的 GraphQL 请求。
     *
     * @param string $query Query value used by this operation. / 本操作使用的“query”参数值。
     * @param array $variables Variables value used by this operation. / 本操作使用的“variables”参数值。
     * @param ?string $operationName Operation name value used by this operation. / 本操作使用的“operation name”参数值。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     *
     * @throws ApiException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
     */
    public function execute(string $query, array $variables = [], ?string $operationName = null): array
    {
        global $config;
        $this->maxDepth = (int)($config['api']['graphql_max_depth'] ?? 8);
        $this->maxFields = (int)($config['api']['graphql_max_fields'] ?? 50);
        $this->maxTokens = (int)($config['api']['graphql_max_tokens'] ?? 2000);
        $this->maxOperations = (int)($config['api']['graphql_max_operations'] ?? 5);
        $this->fieldCount = 0;
        $this->tokens = $this->tokenize($query);
        $this->pos = 0;
        $this->variables = $variables;
        $operations = $this->parseDocument();

        if (!$operations) {
            throw new ApiException(400, 'graphql_parse_error', 'The GraphQL document contains no operation.');
        }

        $operation = null;
        if ($operationName !== null && $operationName !== '') {
            foreach ($operations as $candidate) {
                if (($candidate['name'] ?? null) === $operationName) {
                    $operation = $candidate;
                    break;
                }
            }
            if ($operation === null) {
                throw new ApiException(400, 'graphql_operation_not_found', 'The requested GraphQL operationName was not found.');
            }
        } elseif (count($operations) === 1) {
            $operation = $operations[0];
        } else {
            throw new ApiException(400, 'graphql_operation_name_required', 'operationName is required when the document has multiple operations.');
        }

        $data = [];
        $errors = [];
        foreach ($operation['selection'] as $field) {
            $responseName = $field['alias'] ?: $field['name'];
            try {
                $value = $this->resolveRootField($operation['type'], $field);
                $data[$responseName] = $this->applySelection($value, $field['selection'], $field['name']);
            } catch (ApiException $e) {
                $data[$responseName] = null;
                $errors[] = [
                    'message' => $e->getMessage(),
                    'path' => [$responseName],
                    'extensions' => [
                        'code' => $e->apiCode(),
                        'httpStatus' => $e->status(),
                    ],
                ];
            } catch (\Throwable $e) {
                $data[$responseName] = null;
                $errors[] = [
                    'message' => 'The GraphQL operation failed.',
                    'path' => [$responseName],
                    'extensions' => ['code' => 'internal_error', 'httpStatus' => 500],
                ];
            }
        }

        $result = ['data' => $data];
        if ($errors) {
            $result['errors'] = $errors;
        }
        return $result;
    }

    /**
     * EN: Parse or extract the tokenize operation implemented by graphql engine.
     * 中文：解析或提取 graphql engine 实现的“tokenize”操作。
     *
     * @param string $query Query value used by this operation. / 本操作使用的“query”参数值。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     *
     * @throws \App\Core\ApiException When the GraphQL document contains unsupported syntax or invalid literals. / 当 GraphQL 文档包含不支持的语法或无效字面量时抛出。
     */
    private function tokenize(string $query): array
    {
        $tokens = [];
        $length = strlen($query);
        $i = 0;

        while ($i < $length) {
            $ch = $query[$i];
            if (ctype_space($ch) || $ch === ',') {
                $i++;
                continue;
            }
            if ($ch === '#') {
                while ($i < $length && $query[$i] !== "\n" && $query[$i] !== "\r") {
                    $i++;
                }
                continue;
            }
            if ($ch === '.' && substr($query, $i, 3) === '...') {
                throw new ApiException(400, 'graphql_unsupported_feature', 'GraphQL fragments are not supported by this deployment schema.');
            }
            if (strpos('{}()[]!:$=@', $ch) !== false) {
                $tokens[] = ['type' => 'punct', 'value' => $ch];
                $this->enforceTokenLimit($tokens);
                $i++;
                continue;
            }
            if ($ch === '"') {
                $start = $i;
                $i++;
                $escaped = false;
                while ($i < $length) {
                    $c = $query[$i];
                    if (!$escaped && $c === '"') {
                        $i++;
                        break;
                    }
                    if (!$escaped && $c === '\\') {
                        $escaped = true;
                    } else {
                        $escaped = false;
                    }
                    $i++;
                }
                $literal = substr($query, $start, $i - $start);
                $decoded = json_decode($literal, true);
                if (!is_string($decoded)) {
                    throw new ApiException(400, 'graphql_parse_error', 'Invalid GraphQL string literal.');
                }
                $tokens[] = ['type' => 'string', 'value' => $decoded];
                $this->enforceTokenLimit($tokens);
                continue;
            }
            if (preg_match('/[A-Za-z_]/', $ch)) {
                $start = $i++;
                while ($i < $length && preg_match('/[A-Za-z0-9_]/', $query[$i])) {
                    $i++;
                }
                $tokens[] = ['type' => 'name', 'value' => substr($query, $start, $i - $start)];
                $this->enforceTokenLimit($tokens);
                continue;
            }
            if ($ch === '-' || ctype_digit($ch)) {
                $start = $i++;
                while ($i < $length && preg_match('/[0-9eE+\-.]/', $query[$i])) {
                    $i++;
                }
                $raw = substr($query, $start, $i - $start);
                if (!preg_match('/^-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?(?:[eE][+-]?[0-9]+)?$/', $raw)) {
                    throw new ApiException(400, 'graphql_parse_error', 'Invalid GraphQL number literal.');
                }
                $tokens[] = ['type' => 'number', 'value' => $raw];
                $this->enforceTokenLimit($tokens);
                continue;
            }

            throw new ApiException(400, 'graphql_parse_error', 'Unsupported character in GraphQL document.');
        }

        return $tokens;
    }

    /**
     * EN: Parse or extract the parse document operation implemented by graphql engine.
     * 中文：解析或提取 graphql engine 实现的“parse document”操作。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     *
     * @throws ApiException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
     */
    private function parseDocument(): array
    {
        $operations = [];
        while (!$this->eof()) {
            if ($this->peekValue() === '{') {
                $operations[] = [
                    'type' => 'query',
                    'name' => null,
                    'selection' => $this->parseSelectionSet(1),
                ];
                $this->enforceOperationLimit($operations);
                continue;
            }

            $type = $this->expectName();
            if (!in_array($type, ['query', 'mutation'], true)) {
                throw new ApiException(400, 'graphql_parse_error', 'Only query and mutation operations are supported.');
            }

            $name = null;
            if ($this->peekType() === 'name') {
                $name = $this->expectName();
            }
            if ($this->peekValue() === '(') {
                $this->skipBalanced('(', ')');
            }
            if ($this->peekValue() === '@') {
                throw new ApiException(400, 'graphql_unsupported_feature', 'GraphQL directives are not supported by this deployment schema.');
            }
            $operations[] = [
                'type' => $type,
                'name' => $name,
                'selection' => $this->parseSelectionSet(1),
            ];
            $this->enforceOperationLimit($operations);
        }
        return $operations;
    }

    /**
     * EN: Parse or extract the parse selection set operation implemented by graphql engine.
     * 中文：解析或提取 graphql engine 实现的“parse selection set”操作。
     *
     * @param int $depth Depth value used by this operation. / 本操作使用的“depth”参数值。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     *
     * @throws ApiException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
     */
    private function parseSelectionSet(int $depth): array
    {
        if ($depth > $this->maxDepth) {
            throw new ApiException(400, 'graphql_complexity_limit', 'GraphQL selection depth exceeds the configured limit.');
        }
        $this->expectValue('{');
        $fields = [];
        while (!$this->eof() && $this->peekValue() !== '}') {
            $fields[] = $this->parseField($depth);
            $this->fieldCount++;
            if ($this->fieldCount > $this->maxFields) {
                throw new ApiException(400, 'graphql_complexity_limit', 'GraphQL field count exceeds the configured limit.');
            }
        }
        $this->expectValue('}');
        return $fields;
    }

    /**
     * EN: Parse or extract the parse field operation implemented by graphql engine.
     * 中文：解析或提取 graphql engine 实现的“parse field”操作。
     *
     * @param int $depth Depth value used by this operation. / 本操作使用的“depth”参数值。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     *
     * @throws ApiException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
     */
    private function parseField(int $depth): array
    {
        $first = $this->expectName();
        $alias = null;
        $name = $first;
        if ($this->peekValue() === ':') {
            $this->expectValue(':');
            $alias = $first;
            $name = $this->expectName();
        }

        $arguments = [];
        if ($this->peekValue() === '(') {
            $this->expectValue('(');
            while ($this->peekValue() !== ')') {
                $argName = $this->expectName();
                $this->expectValue(':');
                $arguments[$argName] = $this->parseValue();
            }
            $this->expectValue(')');
        }
        if ($this->peekValue() === '@') {
            throw new ApiException(400, 'graphql_unsupported_feature', 'GraphQL directives are not supported by this deployment schema.');
        }

        $selection = [];
        if ($this->peekValue() === '{') {
            $selection = $this->parseSelectionSet($depth + 1);
        }
        return [
            'name' => $name,
            'alias' => $alias,
            'arguments' => $arguments,
            'selection' => $selection,
        ];
    }

    /**
     * EN: Parse or extract the parse value operation implemented by graphql engine.
     * 中文：解析或提取 graphql engine 实现的“parse value”操作。
     *
     * @return mixed Result produced by this operation; the concrete type depends on the execution path. / 本操作生成的结果；具体类型取决于执行路径。
     *
     * @throws ApiException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
     */
    private function parseValue(): mixed
    {
        $token = $this->peek();
        if ($token === null) {
            throw new ApiException(400, 'graphql_parse_error', 'Unexpected end of GraphQL value.');
        }
        if ($token['value'] === '$') {
            $this->pos++;
            $name = $this->expectName();
            if (!array_key_exists($name, $this->variables)) {
                throw new ApiException(400, 'graphql_variable_missing', 'A required GraphQL variable is missing: ' . $name);
            }
            return $this->variables[$name];
        }
        if ($token['value'] === '{') {
            $this->expectValue('{');
            $object = [];
            while ($this->peekValue() !== '}') {
                $key = $this->expectName();
                $this->expectValue(':');
                $object[$key] = $this->parseValue();
            }
            $this->expectValue('}');
            return $object;
        }
        if ($token['value'] === '[') {
            $this->expectValue('[');
            $list = [];
            while ($this->peekValue() !== ']') {
                $list[] = $this->parseValue();
            }
            $this->expectValue(']');
            return $list;
        }
        $this->pos++;
        if ($token['type'] === 'string') {
            return $token['value'];
        }
        if ($token['type'] === 'number') {
            return str_contains($token['value'], '.') || stripos($token['value'], 'e') !== false
                ? (float)$token['value']
                : (int)$token['value'];
        }
        if ($token['type'] === 'name') {
            return match ($token['value']) {
                'true' => true,
                'false' => false,
                'null' => null,
                default => $token['value'],
            };
        }
        throw new ApiException(400, 'graphql_parse_error', 'Invalid GraphQL argument value.');
    }

    /**
     * EN: Resolve the resolve root field operation implemented by graphql engine.
     * 中文：解析或确定 graphql engine 实现的“resolve root field”操作。
     *
     * @param string $operationType Operation type value used by this operation. / 本操作使用的“operation type”参数值。
     * @param array $field Field value used by this operation. / 本操作使用的“field”参数值。
     *
     * @return mixed Result produced by this operation; the concrete type depends on the execution path. / 本操作生成的结果；具体类型取决于执行路径。
     *
     * @throws ApiException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
     */
    private function resolveRootField(string $operationType, array $field): mixed
    {
        global $config;
        $name = $field['name'];
        if ($name === '__typename') {
            return $operationType === 'mutation' ? 'Mutation' : 'Query';
        }

        if ($operationType === 'query') {
            return match ($name) {
                'apiVersion' => 'v1',
                'appVersion' => (string)$config['app']['version'],
                'me' => ApiAuth::publicUser(ApiAuth::requireLogin()),
                'adminUsers' => $this->resolveAdminUsers(),
                'salesProfile' => ApiAuth::publicUser(ApiAuth::requireRole('sales')),
                default => throw new ApiException(400, 'graphql_unknown_field', 'Unknown GraphQL Query field: ' . $name),
            };
        }

        if ($operationType === 'mutation') {
            return match ($name) {
                'authExchange' => $this->resolveAuthExchange($field['arguments']),
                'logout' => $this->resolveLogout(),
                default => throw new ApiException(400, 'graphql_unknown_field', 'Unknown GraphQL Mutation field: ' . $name),
            };
        }

        throw new ApiException(400, 'graphql_operation_invalid', 'Unsupported GraphQL operation type.');
    }

    /**
     * EN: Resolve the resolve auth exchange operation implemented by graphql engine.
     * 中文：解析或确定 graphql engine 实现的“resolve auth exchange”操作。
     *
     * @param array $arguments Arguments value used by this operation. / 本操作使用的“arguments”参数值。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     *
     * @throws ApiException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
     */
    private function resolveAuthExchange(array $arguments): array
    {
        $input = $arguments['input'] ?? null;
        if (!is_array($input)) {
            throw new ApiException(400, 'graphql_input_required', 'authExchange requires an input object.');
        }
        $payload = [
            'uid' => $input['uid'] ?? '',
            'sales_id' => $input['salesId'] ?? ($input['sales_id'] ?? ''),
            'name' => $input['name'] ?? '',
            'role' => $input['role'] ?? '',
            'ts' => $input['ts'] ?? 0,
            'nonce' => $input['nonce'] ?? '',
            'sig' => $input['sig'] ?? '',
        ];
        try {
            $user = (new ExternalAuthService())->accept($payload);
        } catch (\PDOException $e) {
            throw new ApiException(503, 'auth_service_unavailable', 'The authentication service is temporarily unavailable.');
        } catch (\RuntimeException $e) {
            throw new ApiException(401, 'signed_exchange_rejected', $e->getMessage());
        } catch (\Throwable $e) {
            throw new ApiException(503, 'auth_service_unavailable', 'The authentication service is temporarily unavailable.');
        }
        $issued = ApiAuth::issue($user, 'graphql_signed_exchange');
        return [
            'accessToken' => $issued['access_token'],
            'tokenType' => $issued['token_type'],
            'expiresIn' => $issued['expires_in'],
            'expiresAt' => $issued['expires_at'],
            'user' => ApiAuth::publicUser($user),
        ];
    }

    /**
     * EN: Resolve the resolve logout operation implemented by graphql engine.
     * 中文：解析或确定 graphql engine 实现的“resolve logout”操作。
     *
     * @return bool True when the requested condition is satisfied; otherwise false. / 请求条件满足时返回 true，否则返回 false。
     */
    private function resolveLogout(): bool
    {
        ApiAuth::requireLogin();
        ApiAuth::revokeCurrent();
        return true;
    }

    /**
     * EN: Resolve the resolve admin users operation implemented by graphql engine.
     * 中文：解析或确定 graphql engine 实现的“resolve admin users”操作。
     *
     * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     */
    private function resolveAdminUsers(): array
    {
        ApiAuth::requireRole('admin');
        return array_map(
            static fn(array $row): array => ApiAuth::publicUser($row),
            User::allForApi()
        );
    }

    /**
     * EN: Perform the apply selection operation implemented by graphql engine.
     * 中文：执行 graphql engine 实现的“apply selection”操作。
     *
     * @param mixed $value Value processed or stored by this operation. / 本操作处理或保存的值。
     * @param array $selection Selection value used by this operation. / 本操作使用的“selection”参数值。
     * @param string $fieldName Field name value used by this operation. / 本操作使用的“field name”参数值。
     *
     * @return mixed Result produced by this operation; the concrete type depends on the execution path. / 本操作生成的结果；具体类型取决于执行路径。
     *
     * @throws ApiException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
     */
    private function applySelection(mixed $value, array $selection, string $fieldName): mixed
    {
        if ($value === null || is_scalar($value)) {
            if ($selection) {
                throw new ApiException(400, 'graphql_selection_invalid', 'Scalar field cannot have a child selection: ' . $fieldName);
            }
            return $value;
        }

        if (array_is_list($value)) {
            if (!$selection) {
                throw new ApiException(400, 'graphql_selection_required', 'Object list field requires a child selection: ' . $fieldName);
            }
            return array_map(fn($item) => $this->applySelection($item, $selection, $fieldName), $value);
        }

        if (!is_array($value)) {
            throw new ApiException(500, 'graphql_internal_type', 'GraphQL resolver returned an unsupported value type.');
        }
        if (!$selection) {
            throw new ApiException(400, 'graphql_selection_required', 'Object field requires a child selection: ' . $fieldName);
        }

        $projected = [];
        foreach ($selection as $child) {
            $responseName = $child['alias'] ?: $child['name'];
            if ($child['name'] === '__typename') {
                $projected[$responseName] = $this->objectTypeName($value, $fieldName);
                continue;
            }
            if (!array_key_exists($child['name'], $value)) {
                throw new ApiException(400, 'graphql_unknown_field', 'Unknown field `' . $child['name'] . '` on `' . $fieldName . '`.');
            }
            $projected[$responseName] = $this->applySelection(
                $value[$child['name']],
                $child['selection'],
                $child['name']
            );
        }
        return $projected;
    }

    /**
     * EN: Perform the object type name operation implemented by graphql engine.
     * 中文：执行 graphql engine 实现的“object type name”操作。
     *
     * @param array $value Value processed or stored by this operation. / 本操作处理或保存的值。
     * @param string $fieldName Field name value used by this operation. / 本操作使用的“field name”参数值。
     *
     * @return string String result produced by this operation. / 本操作生成的字符串结果。
     */
    private function objectTypeName(array $value, string $fieldName): string
    {
        if (array_key_exists('accessToken', $value)) {
            return 'AuthPayload';
        }
        if (array_key_exists('role', $value) && array_key_exists('displayName', $value)) {
            return 'User';
        }
        return ucfirst($fieldName);
    }

    /**
     * EN: Perform the enforce token limit operation implemented by graphql engine.
     * 中文：执行 graphql engine 实现的“enforce token limit”操作。
     *
     * @param array $tokens Tokens value used by this operation. / 本操作使用的“tokens”参数值。
     *
     * @return void No value is returned. / 无返回值。
     *
     * @throws ApiException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
     */
    private function enforceTokenLimit(array $tokens): void
    {
        if (count($tokens) > $this->maxTokens) {
            throw new ApiException(400, 'graphql_complexity_limit', 'GraphQL token count exceeds the configured limit.');
        }
    }

    /**
     * EN: Perform the enforce operation limit operation implemented by graphql engine.
     * 中文：执行 graphql engine 实现的“enforce operation limit”操作。
     *
     * @param array $operations Operations value used by this operation. / 本操作使用的“operations”参数值。
     *
     * @return void No value is returned. / 无返回值。
     *
     * @throws ApiException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
     */
    private function enforceOperationLimit(array $operations): void
    {
        if (count($operations) > $this->maxOperations) {
            throw new ApiException(400, 'graphql_complexity_limit', 'GraphQL operation count exceeds the configured limit.');
        }
    }

    /**
     * EN: Perform the skip balanced operation implemented by graphql engine.
     * 中文：执行 graphql engine 实现的“skip balanced”操作。
     *
     * @param string $open Open value used by this operation. / 本操作使用的“open”参数值。
     * @param string $close Close value used by this operation. / 本操作使用的“close”参数值。
     *
     * @return void No value is returned. / 无返回值。
     *
     * @throws ApiException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
     */
    private function skipBalanced(string $open, string $close): void
    {
        $this->expectValue($open);
        $depth = 1;
        while (!$this->eof() && $depth > 0) {
            $value = $this->tokens[$this->pos++]['value'];
            if ($value === $open) {
                $depth++;
            } elseif ($value === $close) {
                $depth--;
            }
        }
        if ($depth !== 0) {
            throw new ApiException(400, 'graphql_parse_error', 'Unbalanced GraphQL variable definition.');
        }
    }

    /**
     * EN: Check or validate the expect name operation implemented by graphql engine.
     * 中文：检查或验证 graphql engine 实现的“expect name”操作。
     *
     * @return string String result produced by this operation. / 本操作生成的字符串结果。
     *
     * @throws ApiException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
     */
    private function expectName(): string
    {
        $token = $this->peek();
        if ($token === null || $token['type'] !== 'name') {
            throw new ApiException(400, 'graphql_parse_error', 'Expected a GraphQL name.');
        }
        $this->pos++;
        return (string)$token['value'];
    }

    /**
     * EN: Check or validate the expect value operation implemented by graphql engine.
     * 中文：检查或验证 graphql engine 实现的“expect value”操作。
     *
     * @param string $value Value processed or stored by this operation. / 本操作处理或保存的值。
     *
     * @return void No value is returned. / 无返回值。
     *
     * @throws ApiException When validation, persistence, or a delegated dependency cannot complete the operation. / 当验证、持久化或下游依赖无法完成操作时抛出。
     */
    private function expectValue(string $value): void
    {
        $token = $this->peek();
        if ($token === null || $token['value'] !== $value) {
            throw new ApiException(400, 'graphql_parse_error', 'Expected GraphQL token: ' . $value);
        }
        $this->pos++;
    }

    /**
     * EN: Perform the peek operation implemented by graphql engine.
     * 中文：执行 graphql engine 实现的“peek”操作。
     *
     * @return ?array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
     */
    private function peek(): ?array
    {
        return $this->tokens[$this->pos] ?? null;
    }

    /**
     * EN: Perform the peek value operation implemented by graphql engine.
     * 中文：执行 graphql engine 实现的“peek value”操作。
     *
     * @return ?string String result produced by this operation, or null when no value is available. / 本操作生成的字符串结果；无可用值时返回 null。
     */
    private function peekValue(): ?string
    {
        return isset($this->tokens[$this->pos]) ? (string)$this->tokens[$this->pos]['value'] : null;
    }

    /**
     * EN: Perform the peek type operation implemented by graphql engine.
     * 中文：执行 graphql engine 实现的“peek type”操作。
     *
     * @return ?string String result produced by this operation, or null when no value is available. / 本操作生成的字符串结果；无可用值时返回 null。
     */
    private function peekType(): ?string
    {
        return isset($this->tokens[$this->pos]) ? (string)$this->tokens[$this->pos]['type'] : null;
    }

    /**
     * EN: Perform the eof operation implemented by graphql engine.
     * 中文：执行 graphql engine 实现的“eof”操作。
     *
     * @return bool True when the requested condition is satisfied; otherwise false. / 请求条件满足时返回 true，否则返回 false。
     */
    private function eof(): bool
    {
        return $this->pos >= count($this->tokens);
    }
}
