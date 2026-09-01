<?php
/**
 * File / 文件：scripts/audit_phpdoc_contract.php
 * EN: CLI maintenance/deployment script for audit phpdoc contract.
 * 中文：用于 audit phpdoc contract 的命令行维护/部署脚本。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */

declare(strict_types=1);

$root = dirname(__DIR__);

/**
 * EN: Perform the php files for doc audit helper used by this validation script.
 * 中文：执行 当前验证脚本使用的“php files for doc audit”辅助操作。
 *
 * @param string $root Root directory used to resolve project-relative paths. / 用于解析项目相对路径的根目录。
 *
 * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
 */
function phpFilesForDocAudit(string $root): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile()) {
            continue;
        }
        $path = $file->getPathname();
        if (strtolower($file->getExtension()) !== 'php') {
            continue;
        }
        if (str_contains(str_replace('\\', '/', $path), '/vendor/')) {
            continue;
        }
        $files[] = $path;
    }
    sort($files);
    return $files;
}

/**
 * EN: Perform the direct docblock before helper used by this validation script.
 * 中文：执行 当前验证脚本使用的“direct docblock before”辅助操作。
 *
 * @param string $content Content to inspect, transform, or store. / 需要检查、转换或保存的内容。
 * @param int $offset Offset used when reading or paginating data. / 读取或分页数据时使用的偏移量。
 *
 * @return ?string String result produced by this operation, or null when no value is available. / 本操作生成的字符串结果；无可用值时返回 null。
 */
function directDocblockBefore(string $content, int $offset): ?string
{
    $prefix = substr($content, 0, $offset);
    $trimmed = rtrim($prefix);
    if (!str_ends_with($trimmed, '*/')) {
        return null;
    }
    $start = strrpos($trimmed, '/**');
    if ($start === false) {
        return null;
    }
    return substr($trimmed, $start);
}

/**
 * EN: Perform the function parameters for doc audit helper used by this validation script.
 * 中文：执行 当前验证脚本使用的“function parameters for doc audit”辅助操作。
 *
 * @param string $signature HMAC signature used to verify the request. / 用于验证请求的 HMAC 签名。
 *
 * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
 */
function functionParametersForDocAudit(string $signature): array
{
    preg_match_all('/\$([A-Za-z_]\w*)/', $signature, $matches);
    return array_values(array_unique($matches[1] ?? []));
}

/**
 * EN: Execute the inspect php doc file helper used by this validation script.
 * 中文：执行 当前验证脚本使用的“inspect php doc file”辅助操作。
 *
 * @param string $root Root directory used to resolve project-relative paths. / 用于解析项目相对路径的根目录。
 * @param string $path Filesystem, route, or data path used by the operation. / 本操作使用的文件、路由或数据路径。
 *
 * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
 */
function inspectPhpDocFile(string $root, string $path): array
{
    $relative = ltrim(str_replace('\\', '/', substr($path, strlen(rtrim($root, '/\\')))), '/');
    $content = (string)file_get_contents($path);
    $failures = [];
    $functions = 0;
    $classes = 0;

    if (!str_contains(substr($content, 0, 1200), 'File / 文件：' . $relative)) {
        $failures[] = $relative . ': missing bilingual file header';
    }

    preg_match_all(
        '/(?m)^\s*(?:(?:final|abstract)\s+)?(?:class|interface|trait)\s+([A-Za-z_]\w*)/',
        $content,
        $classMatches,
        PREG_OFFSET_CAPTURE
    );
    foreach ($classMatches[0] ?? [] as $index => $wholeMatch) {
        $classes++;
        $className = (string)$classMatches[1][$index][0];
        $doc = directDocblockBefore($content, (int)$wholeMatch[1]);
        if ($doc === null || !str_contains($doc, 'EN:') || !str_contains($doc, '中文：')) {
            $failures[] = $relative . '::' . $className . ': missing bilingual class PHPDoc';
        }
    }

    preg_match_all(
        '/(?ms)^\s*(?:(?:public|protected|private|static|final|abstract)\s+)*function\s+&?\s*([A-Za-z_]\w*)\s*\((.*?)\)\s*(?::\s*([^\{;\n]+))?\s*[\{;]/',
        $content,
        $functionMatches,
        PREG_OFFSET_CAPTURE
    );
    foreach ($functionMatches[1] ?? [] as $index => $functionMatch) {
        $functions++;
        $name = (string)$functionMatch[0];
        $offset = (int)$functionMatches[0][$index][1];
        $doc = directDocblockBefore($content, $offset);
        if ($doc === null) {
            $failures[] = $relative . '::' . $name . ': missing PHPDoc';
            continue;
        }
        if (!str_contains($doc, 'EN:') || !str_contains($doc, '中文：')) {
            $failures[] = $relative . '::' . $name . ': PHPDoc is not bilingual';
        }
        foreach (functionParametersForDocAudit((string)$functionMatches[2][$index][0]) as $param) {
            if (!preg_match('/@param\s+[^\r\n]+\$' . preg_quote($param, '/') . '\b/', $doc)) {
                $failures[] = $relative . '::' . $name . ': missing @param $' . $param;
            }
        }
        if (!str_contains($doc, '@return ')) {
            $failures[] = $relative . '::' . $name . ': missing @return';
        }
    }

    return ['relative'=>$relative,'classes'=>$classes,'functions'=>$functions,'failures'=>$failures];
}

/**
 * EN: Execute the run php doc contract audit helper used by this validation script.
 * 中文：执行 当前验证脚本使用的“run php doc contract audit”辅助操作。
 *
 * @param string $root Root directory used to resolve project-relative paths. / 用于解析项目相对路径的根目录。
 *
 * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
 */
function runPhpDocContractAudit(string $root): array
{
    $files = 0;
    $classes = 0;
    $functions = 0;
    $failures = [];
    foreach (phpFilesForDocAudit($root) as $path) {
        $result = inspectPhpDocFile($root, $path);
        $files++;
        $classes += $result['classes'];
        $functions += $result['functions'];
        array_push($failures, ...$result['failures']);
    }
    return compact('files','classes','functions','failures');
}

$result = runPhpDocContractAudit($root);
foreach ($result['failures'] as $failure) {
    fwrite(STDERR, "FAIL {$failure}\n");
}
if ($result['failures']) {
    fwrite(STDERR, sprintf(
        "Detailed PHPDoc audit: FAIL (%d PHP files, %d classes, %d named functions, %d failures)\n",
        $result['files'], $result['classes'], $result['functions'], count($result['failures'])
    ));
    exit(1);
}
printf(
    "Detailed PHPDoc audit: PASS (%d PHP files, %d classes, %d named functions)\n",
    $result['files'], $result['classes'], $result['functions']
);
