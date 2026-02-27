-- 修复发货单扩展字段计算表达式错误
-- 错误: Bad string syntax to evaluate (mode 2, found call of a function or method...)
-- 原因: dol_eval 不允许括号做算术分组，原公式 ($a-$b)/86400 需改写为 $a/86400-$b/86400
-- 表前缀请按 MAIN_DB_PREFIX 修改，默认 llx_

UPDATE llx_extrafields
SET fieldcomputed = '$object->array_options[''options_atd''] ? $object->array_options[''options_atd'']/86400 - $object->array_options[''options_etd'']/86400 : '''''
WHERE elementtype = 'expedition'
  AND fieldcomputed LIKE '%options_atd%'
  AND fieldcomputed LIKE '%options_etd%'
  AND fieldcomputed LIKE '%86400%';
