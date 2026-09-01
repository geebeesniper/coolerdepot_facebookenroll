<?php
/**
 * File / 文件：scripts/audit_jsdoc_contract.php
 * EN: CLI source audit that verifies bilingual JSDoc, parameter tags, and return tags for project-owned named JavaScript functions.
 * 中文：命令行源码审计脚本，用于验证项目自有命名 JavaScript 函数的中英文 JSDoc、参数标签及返回标签。
 * Maintenance / 维护：Keep this audit aligned with the documented source-comment contract and fail closed on missing JSDoc.
 * 维护要求：本审计应与源码注释规范保持一致，缺少 JSDoc 时必须失败。
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$files = glob($root . '/public/assets/*.js') ?: [];
sort($files);
$failures = [];
$functionCount = 0;
$parameterCount = 0;

foreach ($files as $path) {
    $content = (string)file_get_contents($path);
    $relative = ltrim(str_replace('\\', '/', substr($path, strlen($root))), '/');

    preg_match_all(
        '/(?m)^([ \t]*)function\s+([A-Za-z_$][\w$]*)\s*\(([^)]*)\)/',
        $content,
        $matches,
        PREG_OFFSET_CAPTURE
    );

    foreach ($matches[0] ?? [] as $index => $whole) {
        $functionCount++;
        $name = (string)$matches[2][$index][0];
        $offset = (int)$whole[1];
        $prefix = substr($content, 0, $offset);
        $trimmed = rtrim($prefix);
        $docStart = str_ends_with($trimmed, '*/') ? strrpos($trimmed, '/**') : false;
        $doc = $docStart === false ? '' : substr($trimmed, $docStart);

        if ($doc === '' || !str_contains($doc, 'EN:') || !str_contains($doc, '中文：') || !str_contains($doc, '@returns ')) {
            $failures[] = $relative . '::' . $name . ': missing detailed bilingual JSDoc';
            continue;
        }

        $rawParams = trim((string)$matches[3][$index][0]);
        if ($rawParams === '') {
            continue;
        }

        foreach (explode(',', $rawParams) as $rawParam) {
            if (!preg_match('/(?:\.\.\.)?([A-Za-z_$][\w$]*)/', trim($rawParam), $paramMatch)) {
                continue;
            }
            $parameterCount++;
            $param = (string)$paramMatch[1];
            if (!preg_match('/@param\s+\{[^}]+\}\s+' . preg_quote($param, '/') . '\b/', $doc)) {
                $failures[] = $relative . '::' . $name . ': missing @param ' . $param;
            }
        }
    }
}

foreach ($failures as $failure) {
    fwrite(STDERR, "FAIL {$failure}\n");
}

if ($failures) {
    fwrite(
        STDERR,
        sprintf(
            "Detailed JSDoc audit: FAIL (%d named functions, %d parameters, %d failures)\n",
            $functionCount,
            $parameterCount,
            count($failures)
        )
    );
    exit(1);
}

printf(
    "Detailed JSDoc audit: PASS (%d named functions, %d parameters)\n",
    $functionCount,
    $parameterCount
);
