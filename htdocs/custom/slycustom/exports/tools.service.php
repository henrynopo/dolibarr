<?php
/* Copyright (C) 2025 SLY Custom
 *
 * @package SLY Custom
 */

/**
 * Service layer wrappers.
 *
 * Actual implementation helpers (fetchDatasetRows/filterDatasetRows/outputDatasetCsv/formatting)
 * are still declared in tools.php; this file centralizes the orchestration calls.
 */
function slyExportsServiceFetchRows($db, $dataset, &$sqlError = '')
{
	return fetchDatasetRows($db, $dataset, $sqlError);
}

function slyExportsServiceFilterRows($rows, $filters)
{
	return filterDatasetRows($rows, $filters);
}

function slyExportsServiceOutputCsv($filename, $columns, $rows, $datasetKey)
{
	return outputDatasetCsv($filename, $columns, $rows, $datasetKey);
}

