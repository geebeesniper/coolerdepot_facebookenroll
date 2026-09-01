<?php
/**
 * File / 文件：scripts/audit_bilingual_comments.php
 * EN: Source-maintenance audit that verifies bilingual file headers and named-function comments.
 * 中文：源码维护审计脚本，用于验证双语文件头以及命名函数/方法的双语注释。
 * Maintenance / 维护：Run before packaging whenever project-owned PHP/JS/CSS/SQL/Shell/config sources change.
 * 维护要求：项目自有 PHP/JS/CSS/SQL/Shell/配置源码变更后，打包前应执行本脚本。
 */

declare(strict_types=1);

$root = dirname(__DIR__);

/**
 * EN: `commentAuditFiles` returns the project-owned source/configuration files covered by the comment contract.
 * 中文：`commentAuditFiles` 返回纳入双语注释契约检查的项目自有源码与配置文件。
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
 * EN: `relativeAuditPath` converts an absolute source path into the stable project-relative path used by headers/reports.
 * 中文：`relativeAuditPath` 将源码绝对路径转换为文件头与审计报告使用的稳定项目相对路径。
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
 * EN: `hasBilingualFileHeader` verifies that the file identifies itself and contains both English and Chinese documentation labels.
 * 中文：`hasBilingualFileHeader` 验证文件是否自我标识，并同时包含英文与中文说明标签。
 */
function hasBilingualFileHeader(string $relativePath, string $content): bool
{
    return str_contains($content, 'File / 文件：' . $relativePath)
        && str_contains($content, 'EN:')
        && str_contains($content, '中文：');
}

/**
 * EN: `namedFunctionsForAudit` extracts named PHP methods/functions and named JavaScript functions with their source offsets.
 * 中文：`namedFunctionsForAudit` 提取 PHP 命名方法/函数与 JavaScript 命名函数及其源码位置。
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
 * EN: `hasBilingualFunctionComment` checks the nearest preceding documentation window for English and Chinese purpose text.
 * 中文：`hasBilingualFunctionComment` 检查函数前最近的文档窗口是否同时具备英文与中文功能说明。
 */
function hasBilingualFunctionComment(string $content, string $name, int $offset): bool
{
    $start = max(0, $offset - 700);
    $window = substr($content, $start, $offset - $start);

    if (!str_contains($window, 'EN:') || !str_contains($window, '中文：')) {
        return false;
    }

    // Constructors are documented by purpose, while ordinary functions also
    // mention their stable function name to make maintenance searches precise.
    if ($name === '__construct') {
        return str_contains($window, '__construct');
    }

    return str_contains($window, '`' . $name . '`');
}

/**
 * EN: `runBilingualCommentAudit` executes the complete file/function documentation contract and returns structured failures.
 * 中文：`runBilingualCommentAudit` 执行完整的文件/函数双语文档契约检查，并返回结构化失败项。
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
