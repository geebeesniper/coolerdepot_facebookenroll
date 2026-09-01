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

## Detailed PHPDoc contract / 详细 PHPDoc 规范 — v0.2.07+

All project-owned PHP files must use detailed bilingual PHPDoc. / 所有项目自有 PHP 文件必须使用详细中英文 PHPDoc。

For every named function/method: / 每个命名函数或方法：

- Explain the actual responsibility in English and Chinese; do not merely repeat the function name. / 用英文和中文说明实际职责，不能只重复函数名。
- Document every declared parameter with `@param`, including its declared type, variable name, and bilingual purpose. / 每个参数均使用 `@param` 标注类型、变量名及中英文用途。
- Document the return contract with `@return`, including `void`. / 使用 `@return` 标注返回契约，包括 `void`。
- Add `@throws` when the implementation explicitly throws or intentionally propagates an exception. / 实现中明确抛出或有意继续抛出异常时添加 `@throws`。
- Security-sensitive parameters such as passwords, signatures, nonces, tokens, and secrets must state their security purpose and must never be logged in plaintext. / 密码、签名、Nonce、Token、Secret 等敏感参数应说明安全用途，且不得明文写入日志。

Every class must also have a bilingual responsibility PHPDoc, and every PHP file must keep a bilingual file header. / 每个 Class 也必须具有中英文职责说明，每个 PHP 文件必须保留中英文文件头。

Run before packaging: / 打包前执行：

```bash
php scripts/audit_phpdoc_contract.php
php scripts/audit_bilingual_comments.php
```
