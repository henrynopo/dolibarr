<?php
/**
 * Phan 静态分析配置
 *
 * 用于检测 PHP 8.2 弃用：
 *   - dynamic property 赋值（@phan-forbid-undeclared-magic-properties 触发）
 *   - utf8_encode/decode 调用
 *   - 隐式 nullable 参数（PHP 8.4 fatal）
 *   - ${var} 字符串插值
 *
 * 用法：
 *   # 安装 Phan
 *   composer global require phan/phan
 *
 *   # 跑分析
 *   phan --config .phan/config.php
 *   # 或
 *   bash scripts/php82-migration/run-phan.sh
 *
 * 输出：
 *   .phan/output/dynprop-report.txt  - dynamic property 报告
 *   .phan/output/all-issues.txt      - 所有 issue
 */

return [

    // ========== 分析目标 ==========
    // 14 个 custom 模块全部纳入
    'directory_list' => [
        'htdocs/custom/slycustom',
        'htdocs/custom/sghr',
        'htdocs/custom/grh',
        'htdocs/custom/embeddedbookkeeping',
        'htdocs/custom/odooconnector',
        'htdocs/custom/sfrs_reports',
        'htdocs/custom/switchthirdparty',
        'htdocs/custom/previewdocuments',
        'htdocs/custom/listincsv',
        'htdocs/custom/customlink',
        'htdocs/custom/langpicker',
        'htdocs/custom/multicompany',
        'htdocs/custom/abricot',
        'htdocs/custom/strongauth',   // 自研部分，vendor 走 exclude
    ],

    // 排除第三方 / vendor
    'exclude_analysis_file_list' => [
        'htdocs/custom/abricot/3rdparty/**',
        'htdocs/custom/strongauth/vendor/**',
        'htdocs/custom/multicompany/3rdparty/**',
        'htdocs/custom/langpicker/3rdparty/**',
        'htdocs/custom/customlink/3rdparty/**',
        // 已修过的 2 个 sghr 类（已加 AllowDynamicProperties，不参与检测）
        'htdocs/custom/sghr/class/employee.class.php',
        'htdocs/custom/sghr/class/payrollrecord.class.php',
    ],

    // ========== 静态分析开关 ==========
    'analyze_signature_compatibility' => true,
    'quick_mode' => true,                  // 快速模式，省内存
    'null_casts_as_any_type' => false,
    'use_suggested_type_for_null_casts' => true,
    'track_references' => false,           // 不跟踪引用，省内存

    // 关掉噪音大的检测（我们只关心 PHP 8.2 兼容）
    'unused_variable_detection' => false,
    'dead_code_detection' => false,
    'redundant_condition_detection' => false,
    'warn_about_undocumented_throw_statements' => false,
    'suppress_issue_types' => [
        'PhanUndeclaredMethod',
        'PhanUndeclaredClass',
        'PhanUndeclaredClassMethod',
        'PhanUndeclaredClassConstant',
        'PhanTypeMissingReturn',
        'PhanTypeExpectedObject',
        'PhanPartialTypeMismatchArgument',
        'PhanPartialTypeMismatchProperty',
    ],

    // ========== PHP 版本 ==========
    // 检测 8.2 弃用，目标是 8.2
    'minimum_php_version' => '8.2',
    'target_php_version' => '8.2',

    // ========== 输出 ==========
    'output' => 'text',                    // 文本输出
    'progressbar' => false,                // 关进度条
    'no_progress_bar' => true,

    // 强制把 dynamic property 当作 issue
    // 这要配合 @phan-forbid-undeclared-magic-properties 注释
    'forbid_undeclared_magic_properties' => true,
];
