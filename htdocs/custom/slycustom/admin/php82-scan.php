<?php
/* Copyright (C) 2026 SLY Custom
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file       htdocs/custom/slycustom/admin/php82-scan.php
 * \ingroup    slycustom
 * \brief      PHP 8.2 兼容性扫描器（Web 访问版）
 *
 * 访问 URL：
 *   https://yourdomain.com/custom/slycustom/admin/php82-scan.php
 *
 * 输出：
 *   - utf8_encode/decode 命中（9.0 fatal）
 *   - ${var} 字符串插值命中（9.0 fatal）
 *   - 隐式 nullable 参数命中（8.4 fatal）
 *   - dynamic property 精确命中（剔除已声明属性，剔除继承父类）
 */

// Dolibarr 环境
$res = 0;
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

$langs->load("admin");
$langs->load("slycustom@slycustom");

if (!$user->admin) {
    accessforbidden();
}

$action = GETPOST('action', 'aZ09');
$target = GETPOST('target', 'alpha') ?: 'htdocs/custom';

// 标题
llxHeader('', 'PHP 8.2 兼容性扫描', '');

print load_fiche_titre('PHP 8.2 兼容性扫描', '', 'tools');

print '<div class="info">';
print '本工具扫 htdocs/custom/ 下的 PHP 文件，输出 PHP 8.2 / 8.4 / 9.0 兼容性问题。';
print '<br>当前 PHP 版本: <b>' . phpversion() . '</b>';
print '  目标: <b>' . htmlspecialchars($target) . '</b>';
print '</div>';

print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '目标目录: <input type="text" name="target" value="' . htmlspecialchars($target) . '" size="50">';
print ' <input type="submit" class="button" value="扫描">';
print '</form><br>';

// 解析路径（DOL_DOCUMENT_ROOT 基准）
$projectRoot = DOL_DOCUMENT_ROOT;
$fullTarget = $projectRoot . '/' . $target;

if (!is_dir($fullTarget)) {
    print '<div class="error">目录不存在: ' . htmlspecialchars($fullTarget) . '</div>';
    llxFooter();
    exit;
}

// 收集文件
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fullTarget));
$files = [];
foreach ($rii as $f) {
    if ($f->getExtension() !== 'php') continue;
    $path = $f->getPathname();
    if (preg_match('#/(vendor|3rdparty|node_modules)/#', $path)) continue;
    $files[] = $path;
}
sort($files);

$totalFiles = count($files);
print '<p>扫描 <b>' . $totalFiles . '</b> 个 PHP 文件</p>';

// ============== [P0] utf8_encode/decode ==============
print '<h2>[P0] utf8_encode / utf8_decode 命中（9.0 fatal）</h2>';
$utf8Hits = [];
foreach ($files as $f) {
    $hits = [];
    foreach (file($f) as $i => $line) {
        // 跳过"修复示例"代码（含 </code> 标签的 print 块）
        if (str_contains($line, '</code>')) continue;
        if (preg_match('/utf8_(en|de)code\(/', $line)) {
            $hits[] = ['line' => $i + 1, 'content' => trim($line)];
        }
    }
    if ($hits) $utf8Hits[$f] = $hits;
}

if (empty($utf8Hits)) {
    print '<div class="ok">✓ 无命中</div>';
} else {
    print '<table class="noborder" width="100%">';
    print '<tr class="liste_titre"><th>文件</th><th>行</th><th>代码</th></tr>';
    foreach ($utf8Hits as $f => $hits) {
        foreach ($hits as $h) {
            $rel = str_replace($projectRoot . '/', '', $f);
            print '<tr class="oddeven">';
            print '<td>' . htmlspecialchars($rel) . '</td>';
            print '<td>' . $h['line'] . '</td>';
            print '<td><code>' . htmlspecialchars($h['content']) . '</code></td>';
            print '</tr>';
        }
    }
    print '</table>';
    print '<p><b>修复：</b> <code>utf8_encode($x) → mb_convert_encoding($x, "UTF-8", "ISO-8859-1")</code></p>';
}

// ============== [P0] ${var} 插值 ==============
print '<h2>[P0] ${var} 字符串插值命中（9.0 fatal）</h2>';
$sintplHits = [];
foreach ($files as $f) {
    $hits = [];
    foreach (file($f) as $i => $line) {
        // 跳过"修复示例"代码
        if (str_contains($line, '</code>')) continue;
        if (preg_match('/"\$\{[a-zA-Z_]/', $line)) {
            $hits[] = ['line' => $i + 1, 'content' => trim($line)];
        }
    }
    if ($hits) $sintplHits[$f] = $hits;
}

if (empty($sintplHits)) {
    print '<div class="ok">✓ 无命中</div>';
} else {
    print '<table class="noborder" width="100%">';
    print '<tr class="liste_titre"><th>文件</th><th>行</th><th>代码</th></tr>';
    foreach ($sintplHits as $f => $hits) {
        foreach ($hits as $h) {
            $rel = str_replace($projectRoot . '/', '', $f);
            print '<tr class="oddeven">';
            print '<td>' . htmlspecialchars($rel) . '</td>';
            print '<td>' . $h['line'] . '</td>';
            print '<td><code>' . htmlspecialchars($h['content']) . '</code></td>';
            print '</tr>';
        }
    }
    print '</table>';
    print '<p><b>修复：</b> <code>"${name}" → "{$name}"</code></p>';
}

// ============== [P1] 隐式 nullable ==============
print '<h2>[P1] 隐式 nullable 参数命中（8.4 fatal，8.2 deprecation）</h2>';
$nullHits = [];
foreach ($files as $f) {
    $hits = [];
    foreach (file($f) as $i => $line) {
        // 跳过"修复示例"代码 + 注释
        if (str_contains($line, '</code>')) continue;
        $trimmed = ltrim($line);
        if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*') || str_starts_with($trimmed, '/*')) continue;
        if (preg_match('/function [a-zA-Z_][a-zA-Z0-9_]*\([^)]*\$[a-zA-Z_][a-zA-Z0-9_]* = null\)/', $line)) {
            $hits[] = ['line' => $i + 1, 'content' => trim($line)];
        }
    }
    if ($hits) $nullHits[$f] = $hits;
}

$nullTotal = array_sum(array_map('count', $nullHits));
print '<p>命中 <b>' . count($nullHits) . '</b> 个文件、<b>' . $nullTotal . '</b> 处函数签名</p>';

if (!empty($nullHits)) {
    print '<table class="noborder" width="100%">';
    print '<tr class="liste_titre"><th>文件</th><th>行</th><th>代码</th></tr>';
    foreach ($nullHits as $f => $hits) {
        foreach ($hits as $h) {
            $rel = str_replace($projectRoot . '/', '', $f);
            print '<tr class="oddeven">';
            print '<td>' . htmlspecialchars($rel) . '</td>';
            print '<td>' . $h['line'] . '</td>';
            print '<td><code>' . htmlspecialchars($h['content']) . '</code></td>';
            print '</tr>';
        }
    }
    print '</table>';
    print '<p><b>修复：</b> <code>function foo(string $x = null) → function foo(?string $x = null)</code></p>';
}

// ============== [P2] dynamic property ==============
print '<h2>[P2] dynamic property 命中（8.2 deprecation，9.0 fatal）</h2>';
print '<p><b>规则：</b>modXxx（继承 DolibarrModules）和继承 CommonObject / CommonObjectLine / DolibarrTriggers / ModeleNumRef* / DolibarrHookActions / CommonHookActions / TCPDF / FPDF / CommonForm / SearchPage 的类<b>不算</b>dynprop（父类已声明这些属性）。</p>';

$inheritedBaseClasses = [
    'CommonObject', 'CommonObjectLine',
    'DolibarrTriggers', 'DolibarrModules',
    'DolibarrHookActions', 'CommonHookActions',
    'TCPDF', 'FPDF',
    'CommonForm',
    'SearchPage',
    'Form',
    'Translate',
    'Conf',
];
// ModeleNumRefXxx 子类
$modeleNumRefPrefixes = [
    'ModeleNumRef', 'ModelePDF', 'ModeleDoc', 'ModeleCode',
    'ModeleMail', 'ModeleExport', 'ModeleImport',
];

$dynpropHits = [];
$dynpropInheritedHits = [];
foreach ($files as $f) {
    if (!str_ends_with($f, '.class.php')) continue;

    $basename = basename($f);
    if (str_starts_with($basename, 'mod') && str_contains($basename, '.class.php')) continue;

    $src = file_get_contents($f);
    if ($src === false) continue;
    if (str_contains($src, '#[AllowDynamicProperties]')) continue;

    $tokens = @token_get_all($src);
    if ($tokens === false) continue;

    // 找所有类（class ClassName extends ParentClass）
    $classes = []; // className => parentClassName
    for ($i = 0; $i < count($tokens); $i++) {
        if (is_array($tokens[$i]) && $tokens[$i][0] === T_CLASS) {
            for ($j = $i + 1; $j < count($tokens); $j++) {
                if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                    $className = $tokens[$j][1];
                    $parent = null;
                    // 找 extends
                    for ($k = $j + 1; $k < min($j + 30, count($tokens)); $k++) {
                        if (is_array($tokens[$k]) && $tokens[$k][0] === T_EXTENDS) {
                            for ($m = $k + 1; $m < min($k + 10, count($tokens)); $m++) {
                                if (is_array($tokens[$m]) && $tokens[$m][0] === T_STRING) {
                                    $parent = $tokens[$m][1];
                                    break 3;
                                }
                            }
                        }
                        if (is_array($tokens[$k]) && $tokens[$k][0] === T_IMPLEMENTS) break;
                        if ($tokens[$k] === '{' || (is_array($tokens[$k]) && $tokens[$k][0] === T_OPEN_CURLY_BRACKET)) break;
                    }
                    $classes[$className] = $parent;
                    break;
                }
            }
        }
    }

    // 判断一个类是否继承自"已知基类"
    function isInheritedFromKnown($className, $classes, $inheritedBaseClasses, $modeleNumRefPrefixes) {
        $visited = [];
        $cur = $className;
        while ($cur !== null && !isset($visited[$cur])) {
            $visited[$cur] = true;
            $parent = $classes[$cur] ?? null;
            if ($parent === null) return false;
            if (in_array($parent, $inheritedBaseClasses, true)) return true;
            foreach ($modeleNumRefPrefixes as $p) {
                if (str_starts_with($parent, $p)) return true;
            }
            $cur = $parent;
        }
        return false;
    }

    // 找类内声明的属性
    $declared = [];
    $currentClass = null;
    foreach ($tokens as $i => $t) {
        if (is_array($t) && $t[0] === T_CLASS) {
            for ($j = $i + 1; $j < count($tokens); $j++) {
                if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                    $currentClass = $tokens[$j][1];
                    $declared[$currentClass] = [];
                    break;
                }
            }
        }
        if ($currentClass && is_array($t) && in_array($t[0], [T_PUBLIC, T_PROTECTED, T_PRIVATE, T_VAR])) {
            $isFnParam = false;
            for ($k = max(0, $i - 50); $k < $i; $k++) {
                if (is_array($tokens[$k]) && $tokens[$k][0] === T_FUNCTION) {
                    $isFnParam = true;
                    break;
                }
            }
            if ($isFnParam) continue;
            for ($j = $i; $j < min($i + 10, count($tokens)); $j++) {
                if (is_array($tokens[$j]) && $tokens[$j][0] === T_VARIABLE) {
                    $declared[$currentClass][] = ltrim($tokens[$j][1], '$');
                    break;
                }
            }
        }
    }

    // 找 $this->xxx = 赋值
    $currentClass = null;
    foreach ($tokens as $i => $t) {
        if (is_array($t) && $t[0] === T_CLASS) {
            for ($j = $i + 1; $j < count($tokens); $j++) {
                if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                    $currentClass = $tokens[$j][1];
                    break;
                }
            }
        }
        if ($currentClass
            && is_array($t) && $t[0] === T_VARIABLE && $t[1] === '$this'
            && isset($tokens[$i + 1]) && is_array($tokens[$i + 1]) && $tokens[$i + 1][0] === T_OBJECT_OPERATOR
            && isset($tokens[$i + 2]) && is_array($tokens[$i + 2]) && $tokens[$i + 2][0] === T_STRING
        ) {
            $propName = $tokens[$i + 2][1];
            $line = $tokens[$i + 2][2];

            $foundAssign = false;
            for ($j = $i + 3; $j < min($i + 10, count($tokens)); $j++) {
                $tj = $tokens[$j];
                if (is_array($tj) && $tj[0] === T_OBJECT_OPERATOR) break;
                if ($tj === '=' || (is_array($tj) && $tj[0] === T_AND_EQUAL)) {
                    $foundAssign = true;
                    break;
                }
            }

            if ($foundAssign) {
                $declaredProps = $declared[$currentClass] ?? [];
                if (!in_array($propName, $declaredProps, true)) {
                    // 取代码上下文
                    $lines = file($f);
                    $contextStart = max(0, $line - 3);
                    $contextEnd = min(count($lines), $line + 1);
                    $context = '';
                    for ($l = $contextStart; $l < $contextEnd; $l++) {
                        $context .= trim($lines[$l]) . ' ↵ ';
                    }
                    $rel = str_replace($projectRoot . '/', '', $f);
                    $hit = [
                        'file' => $rel,
                        'line' => $line,
                        'class' => $currentClass,
                        'parent' => $classes[$currentClass] ?? '?',
                        'property' => $propName,
                        'context' => $context,
                    ];
                    // 区分：真命中 vs 继承类（已剔除）
                    if (isInheritedFromKnown($currentClass, $classes, $inheritedBaseClasses, $modeleNumRefPrefixes)) {
                        $dynpropInheritedHits[] = $hit;
                    } else {
                        $dynpropHits[] = $hit;
                    }
                }
            }
        }
    }
}

if (empty($dynpropHits)) {
    print '<div class="ok">✓ 无真命中（已剔除 modXxx + 继承 CommonObject/DolibarrTriggers/ModeleNumRef* 等的类）</div>';
} else {
    usort($dynpropHits, fn($a, $b) => strcmp($a['file'], $b['file']));
    print '<p><b>真命中：</b>' . count($dynpropHits) . ' 处（未继承已知基类）</p>';
    print '<table class="noborder" width="100%">';
    print '<tr class="liste_titre"><th>文件:行</th><th>类（父类）</th><th>属性</th><th>代码上下文</th></tr>';
    foreach ($dynpropHits as $h) {
        print '<tr class="oddeven">';
        print '<td>' . htmlspecialchars($h['file']) . ':' . $h['line'] . '</td>';
        print '<td>' . htmlspecialchars($h['class']) . ' <i>extends ' . htmlspecialchars($h['parent']) . '</i></td>';
        print '<td><code>->' . htmlspecialchars($h['property']) . '</code></td>';
        print '<td><code style="font-size:11px;">' . htmlspecialchars($h['context']) . '</code></td>';
        print '</tr>';
    }
    print '</table>';
    print '<p><b>修复策略：</b>在类内显式声明 <code>public $xxx;</code> 或用 <code>#[\AllowDynamicProperties]</code> 注解整个类</p>';
}

if (!empty($dynpropInheritedHits)) {
    print '<details><summary><b>继承类命中（' . count($dynpropInheritedHits) . ' 处，已剔除，不需修）：</b></summary>';
    print '<p>这些类的属性是父类 <code>CommonObject / DolibarrTriggers / ModeleNumRef* / DolibarrModules</code> 已声明的，PHP 8.2+ 不会报 deprecation。点击展开仅供了解。</p>';
    print '<table class="noborder" width="100%">';
    print '<tr class="liste_titre"><th>文件:行</th><th>类（父类）</th><th>属性</th></tr>';
    foreach (array_slice($dynpropInheritedHits, 0, 50) as $h) {
        print '<tr class="oddeven">';
        print '<td>' . htmlspecialchars($h['file']) . ':' . $h['line'] . '</td>';
        print '<td>' . htmlspecialchars($h['class']) . ' <i>extends ' . htmlspecialchars($h['parent']) . '</i></td>';
        print '<td><code>->' . htmlspecialchars($h['property']) . '</code></td>';
        print '</tr>';
    }
    if (count($dynpropInheritedHits) > 50) {
        print '<tr><td colspan="3">... 共 ' . count($dynpropInheritedHits) . ' 处，仅显示前 50</td></tr>';
    }
    print '</table></details>';
}

// ============== 汇总 ==============
print '<h2>汇总</h2>';
print '<table class="noborder" width="100%">';
print '<tr class="liste_titre"><th>项目</th><th>命中</th><th>严重度</th></tr>';
print '<tr class="oddeven"><td>utf8_encode/decode</td><td>' . count($utf8Hits) . '</td><td>9.0 fatal</td></tr>';
print '<tr class="oddeven"><td>${var} 插值</td><td>' . count($sintplHits) . '</td><td>9.0 fatal</td></tr>';
print '<tr class="oddeven"><td>隐式 nullable</td><td>' . $nullTotal . '</td><td>8.4 fatal</td></tr>';
print '<tr class="oddeven"><td>dynamic property（真命中）</td><td>' . count($dynpropHits) . '</td><td>9.0 fatal</td></tr>';
print '<tr class="oddeven"><td>dynamic property（继承类，已剔除）</td><td>' . count($dynpropInheritedHits) . '</td><td>非问题</td></tr>';
print '</table>';

print '<p style="margin-top: 20px;">';
print '详细报告参考：<code>scripts/php82-migration/fix-map.md</code>';
print '</p>';

llxFooter();
