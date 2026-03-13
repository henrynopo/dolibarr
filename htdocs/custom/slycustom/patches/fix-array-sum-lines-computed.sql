-- 修复扩展字段计算表达式：当 $object->lines 为 null 时 array_sum/array_column 报错
-- dol_eval 不允许 ??，且要求 ? 前后有空格，故用 (is_array($object->lines) ? $object->lines : [])
-- 表前缀请按 MAIN_DB_PREFIX 修改，默认 llx_

-- 公式 1：array_sum(array_column($object->lines,'qty'))
UPDATE llx_extrafields
SET fieldcomputed = 'array_sum(array_column((is_array($object->lines) ? $object->lines : []), ''qty''))'
WHERE fieldcomputed LIKE '%array_sum(array_column($object->lines%'
  AND fieldcomputed LIKE '%qty%'
  AND fieldcomputed NOT LIKE '%array_options%'
  AND fieldcomputed NOT LIKE '%is_array%';

-- 公式 2：array_sum(array_column(array_column($object->lines,'array_options'), 'options_quantitycarton'))
UPDATE llx_extrafields
SET fieldcomputed = 'array_sum(array_column(array_column((is_array($object->lines) ? $object->lines : []), ''array_options''), ''options_quantitycarton''))'
WHERE fieldcomputed LIKE '%array_column($object->lines%'
  AND fieldcomputed LIKE '%array_options%'
  AND fieldcomputed LIKE '%options_quantitycarton%'
  AND fieldcomputed NOT LIKE '%is_array%';

-- 公式 3：array_sum(array_column(array_column($object->lines,'array_options'), 'options_grossweight'))
UPDATE llx_extrafields
SET fieldcomputed = 'array_sum(array_column(array_column((is_array($object->lines) ? $object->lines : []), ''array_options''), ''options_grossweight''))'
WHERE fieldcomputed LIKE '%array_column($object->lines%'
  AND fieldcomputed LIKE '%array_options%'
  AND fieldcomputed LIKE '%options_grossweight%'
  AND fieldcomputed NOT LIKE '%is_array%';
