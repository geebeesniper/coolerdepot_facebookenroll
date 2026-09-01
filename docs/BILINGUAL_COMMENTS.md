# Bilingual Source Comment Contract / 源码双语注释契约

## English

Every project-owned source/configuration file covered by `scripts/audit_bilingual_comments.php` must contain a file header with both `EN:` and `中文：` descriptions. Every named PHP function/method and named JavaScript function must have an immediately preceding bilingual purpose comment. Comments should explain ownership, intent, validation/error behavior, or non-obvious constraints; they should not merely restate syntax.

Run before packaging:

```bash
php scripts/audit_bilingual_comments.php
```

`package_release.sh` and `validate_release.sh` run this audit automatically, including against the exact contents of the generated ZIP.

## 中文

所有由 `scripts/audit_bilingual_comments.php` 管理的项目自有源码/配置文件，都必须包含同时带有 `EN:` 与 `中文：` 的文件头说明。每个 PHP 命名函数/方法以及 JavaScript 命名函数，都必须在函数前有中英文功能说明。注释应说明功能归属、用途、校验/错误行为或不明显的限制，而不是简单重复代码语法。

打包前执行：

```bash
php scripts/audit_bilingual_comments.php
```

`package_release.sh` 与 `validate_release.sh` 会自动执行该审计，并且会针对最终 ZIP 内的实际文件再次检查。
