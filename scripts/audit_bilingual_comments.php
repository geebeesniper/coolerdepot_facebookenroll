<?php
/**
 * File / 文件：scripts/audit_bilingual_comments.php
 * EN: CLI maintenance/deployment script for audit bilingual comments.
 * 中文：用于 audit bilingual comments 的命令行维护/部署脚本。
 * Maintenance / 维护：Keep behavior, security checks, error logging, and public contracts unchanged unless the related feature is intentionally modified.
 * 维护要求：除非明确修改相关功能，否则应保持行为、安全检查、错误日志及公开接口契约不变。
 */

declare(strict_types=1);

$root = dirname(__DIR__);

/**
 * EN: Perform the comment audit files helper used by this validation script.
 * 中文：执行 当前验证脚本使用的“comment audit files”辅助操作。
 *
 * @param string $root Root directory used to resolve project-relative paths. / 用于解析项目相对路径的根目录。
 *
 * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
 */
function commentAuditFiles(string $root): array
{
    $files = [];
    $roots = ['app', 'config', 'database', 'public', 'scripts', 'tests', 'integration', 'deploy', 'storage'];
    $extensions = ['php', 'js', 'css', 'sh', 'sql', 'conf'];

    foreach ($roots as $relativeRoot) {
        $dir = $root . '/' . $relativeRoot;

        if (!is_dir($dir)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile()) {
                continue;
            }

            $name = $file->getFilename();
            $extension = strtolower($file->getExtension());

            if (!in_array($extension, $extensions, true)
                && $name !== '.htaccess'
                && !str_ends_with($name, '.conf.example')) {
                continue;
            }

            $files[] = $file->getPathname();
        }
    }

    foreach ([
        $root . '/index.php',
        $root . '/http-status.php',
        $root . '/.htaccess',
        $root . '/.env.example',
        $root . '/.gitignore',
    ] as $entry) {
        if (is_file($entry)) {
            $files[] = $entry;
        }
    }

    $files = array_values(array_unique($files));
    sort($files);

    return $files;
}

/**
 * EN: Perform the relative audit path helper used by this validation script.
 * 中文：执行 当前验证脚本使用的“relative audit path”辅助操作。
 *
 * @param string $root Root directory used to resolve project-relative paths. / 用于解析项目相对路径的根目录。
 * @param string $path Filesystem, route, or data path used by the operation. / 本操作使用的文件、路由或数据路径。
 *
 * @return string String result produced by this operation. / 本操作生成的字符串结果。
 */
function relativeAuditPath(string $root, string $path): string
{
    $prefix = rtrim(str_replace('\\', '/', $root), '/') . '/';
    $normalized = str_replace('\\', '/', $path);

    return str_starts_with($normalized, $prefix)
        ? substr($normalized, strlen($prefix))
        : $normalized;
}

/**
 * EN: Check or validate the has bilingual file header helper used by this validation script.
 * 中文：检查或验证 当前验证脚本使用的“has bilingual file header”辅助操作。
 *
 * @param string $relativePath Relative path value used by this operation. / 本操作使用的“relative path”参数值。
 * @param string $content Content to inspect, transform, or store. / 需要检查、转换或保存的内容。
 *
 * @return bool True when the requested condition is satisfied; otherwise false. / 请求条件满足时返回 true，否则返回 false。
 */
function hasBilingualFileHeader(string $relativePath, string $content): bool
{
    return str_contains($content, 'File / 文件：' . $relativePath)
        && str_contains($content, 'EN:')
        && str_contains($content, '中文：');
}

/**
 * EN: Perform the named functions for audit helper used by this validation script.
 * 中文：执行 当前验证脚本使用的“named functions for audit”辅助操作。
 *
 * @param string $relativePath Relative path value used by this operation. / 本操作使用的“relative path”参数值。
 * @param string $content Content to inspect, transform, or store. / 需要检查、转换或保存的内容。
 *
 * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
 */
function namedFunctionsForAudit(string $relativePath, string $content): array
{
    $matches = [];

    if (str_ends_with($relativePath, '.php')) {
        preg_match_all(
            '/^\s*(?:(?:public|protected|private|static|final|abstract)\s+)*function\s+([A-Za-z_]\w*)\s*\(/m',
            $content,
            $raw,
            PREG_OFFSET_CAPTURE
        );
    } elseif (str_ends_with($relativePath, '.js')) {
        preg_match_all(
            '/^\s*function\s+([A-Za-z_$][\w$]*)\s*\(/m',
            $content,
            $raw,
            PREG_OFFSET_CAPTURE
        );
    } else {
        return [];
    }

    foreach ($raw[1] ?? [] as $item) {
        $matches[] = [
            'name' => (string)$item[0],
            'offset' => (int)$item[1],
        ];
    }

    return $matches;
}

/**
 * EN: Check or validate the has bilingual function comment helper used by this validation script.
 * 中文：检查或验证 当前验证脚本使用的“has bilingual function comment”辅助操作。
 *
 * @param string $content Content to inspect, transform, or store. / 需要检查、转换或保存的内容。
 * @param string $name Display or logical name associated with the operation. / 与本操作关联的显示名称或逻辑名称。
 * @param int $offset Offset used when reading or paginating data. / 读取或分页数据时使用的偏移量。
 *
 * @return bool True when the requested condition is satisfied; otherwise false. / 请求条件满足时返回 true，否则返回 false。
 */
function hasBilingualFunctionComment(string $content, string $name, int $offset): bool
{
    $linePrefix = substr($content, 0, $offset);
    $lineStart = strrpos($linePrefix, "\n");
    $declarationOffset = $lineStart === false ? 0 : $lineStart + 1;
    $prefix = substr($content, 0, $declarationOffset);
    $trimmed = rtrim($prefix);

    if (!str_ends_with($trimmed, '*/')) {
        return false;
    }

    $docStart = strrpos($trimmed, '/**');
    if ($docStart === false) {
        return false;
    }

    $doc = substr($trimmed, $docStart);

    return str_contains($doc, 'EN:')
        && str_contains($doc, '中文：')
        && (str_contains($doc, '@return ') || str_contains($doc, '@returns '));
}

/**
 * EN: Execute the run bilingual comment audit helper used by this validation script.
 * 中文：执行 当前验证脚本使用的“run bilingual comment audit”辅助操作。
 *
 * @param string $root Root directory used to resolve project-relative paths. / 用于解析项目相对路径的根目录。
 *
 * @return array Structured result data produced by this operation. / 本操作生成的结构化结果数据。
 */
function runBilingualCommentAudit(string $root): array
{
    $failures = [];
    $fileCount = 0;
    $functionCount = 0;

    foreach (commentAuditFiles($root) as $path) {
        $relative = relativeAuditPath($root, $path);
        $content = (string)file_get_contents($path);
        $fileCount++;

        if (!hasBilingualFileHeader($relative, $content)) {
            $failures[] = $relative . ': missing bilingual file header';
        }

        foreach (namedFunctionsForAudit($relative, $content) as $function) {
            $functionCount++;

            if (!hasBilingualFunctionComment($content, $function['name'], $function['offset'])) {
                $failures[] = $relative . '::' . $function['name'] . ': missing bilingual function comment';
            }
        }
    }

    return [
        'files' => $fileCount,
        'functions' => $functionCount,
        'failures' => $failures,
    ];
}

$result = runBilingualCommentAudit($root);

foreach ($result['failures'] as $failure) {
    fwrite(STDERR, "FAIL {$failure}\n");
}

if ($result['failures']) {
    fwrite(
        STDERR,
        sprintf(
            "Bilingual comment audit: FAIL (%d files, %d named functions, %d failures)\n",
            $result['files'],
            $result['functions'],
            count($result['failures'])
        )
    );
    exit(1);
}

printf(
    "Bilingual comment audit: PASS (%d files, %d named functions)\n",
    $result['files'],
    $result['functions']
);
