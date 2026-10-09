<?php
/* Copyright (C) 2026 EmbeddedBookkeeping
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 *	\file       htdocs/custom/embeddedbookkeeping/class/EBKKnowledgeBase.class.php
 *	\ingroup    embeddedbookkeeping
 *	\brief      Local knowledge base fed to the AI assistant (Dolibarr accounting
 *	             usage + Singapore accounting standards + IRAS tax/GST rules).
 *
 *	Why a local knowledge base instead of letting the model answer from memory:
 *	a bookkeeping answer is only trustworthy if it cites the account that exists in
 *	THIS company and the rule that is actually in force in Singapore THIS tax year.
 *	The model has neither. So we ship the reference material as markdown files
 *	inside the module, pick the relevant ones per question, and inject them into
 *	the system prompt.
 *
 *	Design constraints (project CLAUDE.md):
 *	- §1 independent module: everything lives under custom/embeddedbookkeeping, no
 *	  core file is touched, disabling the module removes the whole feature.
 *	- §5 no query in a loop: every file is read once up front into memory, scoring
 *	  is pure PHP on that in-memory map.
 *	- §2 the knowledge base is read-only static content, no user input, so there is
 *	  nothing to escape for output — but it still never renders into HTML anywhere.
 *
 *	File format (see knowledge/*.md):
 *	    <!-- kb:title=... -->    short human label, also the citation the model shows
 *	    <!-- kb:keywords=a,b -->  scoring keywords, ZH and EN mixed on purpose
 *	    <!-- kb:sources=... -->   OPTIONAL, "Label <url> | Label <url>", the pages a
 *	                             human must open to double-check a figure
 *	    then plain markdown body.
 *
 *	Why kb:sources is separate from the body: this module deliberately has NO web
 *	access, so "以 IRAS 最新公告为准" is only actionable if the assistant can hand the
 *	user the exact page to open. These URLs used to live in a trailing 来源 section,
 *	which truncate() then cut away on every long file — the model never saw them.
 *	Parsed separately and re-appended after truncation so they always survive.
 *
 *	@package EmbeddedBookkeeping
 */
class EBKKnowledgeBase
{
	/**
	 * Default cap for the whole injected block, in characters. ~6000 chars is
	 * roughly 3k tokens of Chinese - enough for two full reference pages while
	 * leaving room for the chart of accounts and the document itself.
	 *
	 * @var int
	 */
	const DEFAULT_MAX_CHARS = 6000;

	/**
	 * Never inject more than this many files. Keeps one huge question from
	 * dumping the entire knowledge base into every prompt.
	 *
	 * @var int
	 */
	const MAX_FILES = 4;

	/**
	 * A single file longer than this is truncated at a section boundary.
	 *
	 * Was 2600, which was tuned for the original ~2.5KB knowledge files. After the
	 * 2026-10-09 fact-check the files grew to 4-7KB, and the old cap silently threw
	 * away 50-70% of the verified content (03-iras-gst kept only 31%). 4000 covers
	 * every file except the longest, which is then cut at a section boundary so
	 * whole topics survive even if not all of them do.
	 *
	 * @var int
	 */
	const MAX_PER_FILE = 4000;

	/**
	 * Never keep a file that would be cut below this many characters — a 200-char
	 * fragment of a reference document is worse than no reference, because the model
	 * may still treat it as the whole document and answer confidently from it.
	 *
	 * @var int
	 */
	const MIN_USEFUL_CHARS = 1200;

	/**
	 * Admin free-text notes stored in EMBEDDEDBOOKKEEPING_AI_KNOWLEDGE_EXTRA are
	 * always injected (they are the company's own rules and must not be scored
	 * away), capped at this many characters.
	 *
	 * @var int
	 */
	const MAX_ADMIN_NOTES = 4000;

	/**
	 * Per-request memo so a page with several chat turns does not re-read the
	 * same markdown files over and over.
	 *
	 * @var array<string,array>
	 */
	private static $fileCache = array();

	/**
	 * Build the knowledge block to append to the system prompt.
	 *
	 * @param  string       $question  Raw user question
	 * @param  string       $context   Page context (document / voucher / chart of accounts)
	 * @param  stdClass|null $conf     Global config, for the toggles and admin notes
	 * @return string                  Markdown block, or '' when nothing is relevant
	 */
	public static function buildBlock($question, $context = '', $conf = null)
	{
		// Master switch (setup page, provider tab). getDolGlobalInt() returns the
		// default when the constant does not exist yet, so the feature is on by
		// default after a plain file upload and off only when an admin unticks it.
		if (!getDolGlobalInt('EMBEDDEDBOOKKEEPING_AI_KNOWLEDGE_ENABLED', 1)) {
			return '';
		}

		$out = '';

		// Company-specific notes the admin typed on the setup page. These are
		// authoritative for this company (e.g. "we always book the courier fee to
		// 6831"), so they are not keyword-scored - the admin decides, not us.
		$notes = self::adminNotes($conf);
		if ($notes !== '') {
			$out .= "【公司内部会计约定 / Company accounting rules】\n".$notes."\n\n";
		}

		$files = self::select((string) $question, (string) $context, self::maxChars($conf));
		if (!empty($files)) {
			$out .= "【参考资料 / Reference material】\n";
			$out .= "（以下是本公司离线知识库，与问题相关的部分。回答时请引用来源标题，并说明这是离线资料；\n";
			$out .= "若资料与 IRAS/ACRA 最新公告冲突，以最新公告为准。）\n\n";
			foreach ($files as $f) {
				$out .= "--- 来源: ".$f['title']." ---\n".$f['body']."\n\n";
				// Appended here rather than folded into the body so it is never
				// truncated away and never eats the content budget. This module has no
				// web access, so this footer is the ONLY way the assistant can tell the
				// user which official page to open and check a figure against.
				if (!empty($f['sources'])) {
					$out .= "【人工复核入口 / Verify manually — 本模块无联网能力，请人工打开以下官方页面核对】\n"
						.$f['sources']."\n\n";
				}
			}
		}

		return $out;
	}

	/**
	 * Score every knowledge file against the question and the page context, then
	 * keep the best ones until the character budget runs out.
	 *
	 * A question that matches nothing returns an empty array on purpose: sending
	 * the whole library to the model on every unrelated question costs tokens and
	 * dilutes the answer.
	 *
	 * @param  string $question
	 * @param  string $context
	 * @param  int    $budget
	 * @return array<int,array{title:string,body:string,score:int}>
	 */
	public static function select($question, $context, $budget)
	{
		$all = self::loadAll();
		if (empty($all)) {
			return array();
		}

		$qLower = self::normalize($question);
		$cLower = self::normalize($context);

		$scored = array();
		foreach ($all as $f) {
			$score = 0;
			foreach ($f['keywords'] as $kw) {
				$k = self::normalize($kw);
				if ($k === '') {
					continue;
				}
				// A hit in the question is what the user actually asked about, so it
				// counts triple; a hit in the page context is a hint (the document on
				// screen mentions GST) and counts once.
				if (stripos($qLower, $k) !== false) {
					$score += 3;
				}
				if ($cLower !== '' && stripos($cLower, $k) !== false) {
					$score += 1;
				}
			}
			if ($score > 0) {
				$scored[] = array(
					'title' => $f['title'],
					'body' => $f['body'],
					// Must be carried through or buildBlock() has nothing to render the
					// "verify manually" footer from.
					'sources' => $f['sources'],
					'score' => $score,
				);
			}
		}
		if (empty($scored)) {
			return array();
		}

		// Stable sort by score desc; ties keep file order (glob is alphabetical,
		// and the files are numbered so the most general one comes first).
		$idx = 0;
		foreach ($scored as $k => $v) {
			$scored[$k]['ord'] = $idx++;
		}
		usort($scored, function ($a, $b) {
			// DESC by score. usort's comparator must return negative when $a sorts
			// first, so this is $b - $a — writing $a - $b here silently sorts the
			// LEAST relevant file to the front, which is then the one the budget
			// drops. Ties keep file order (most general file first).
			if ($a['score'] === $b['score']) {
				return $a['ord'] - $b['ord'];
			}
			return $b['score'] - $a['score'];
		});

		$picked = array();
		$used = 0;
		foreach ($scored as $item) {
			if (count($picked) >= self::MAX_FILES) {
				break;
			}
			// The budget is a real cap, not a hint. This used to read
			// "if ($used > 0 && ...)" which always waved the first file through, so a
			// 1500-char budget still shipped a 2600-char file (observed: 5415 chars
			// injected against a 1500 budget, 3.6x over).
			$room = $budget - $used;
			if ($room <= 0) {
				break;
			}
			if (strlen($item['body']) > $room) {
				// Truncate rather than drop. Dropping used to mean a two-topic question
				// ("供应商发票怎么记账？") only ever got the first topic's file: the
				// general Dolibarr manual filled the budget and the specific account-
				// mapping file was skipped entirely, so the model answered a mapping
				// question from a manual. A partial second file beats no second file,
				// provided what survives is still worth reading.
				if ($room < self::MIN_USEFUL_CHARS) {
					break;
				}
				$item['body'] = self::truncate($item['body'], $room);
				$picked[] = $item;
				$used += strlen($item['body']);
				continue;
			}
			$picked[] = $item;
			$used += strlen($item['body']);
		}

		return $picked;
	}

	/**
	 * Read and parse every knowledge file, once per request.
	 *
	 * @return array<int,array{title:string,keywords:array,body:string}>
	 */
	private static function loadAll()
	{
		if (!empty(self::$fileCache)) {
			return self::$fileCache;
		}

		$dir = DOL_DOCUMENT_ROOT.'/custom/embeddedbookkeeping/knowledge';
		$files = is_dir($dir) ? glob($dir.'/*.md') : array();
		if (empty($files)) {
			self::$fileCache = array();
			return array();
		}

		$out = array();
		foreach ($files as $path) {
			$raw = @file_get_contents($path);
			if ($raw === false || $raw === '') {
				continue;
			}
			$parsed = self::parse((string) $raw, basename($path, '.md'));
			if ($parsed !== null) {
				$out[] = $parsed;
			}
		}

		self::$fileCache = $out;
		return self::$fileCache;
	}

	/**
	 * Parse one knowledge file.
	 *
	 * @param  string $raw
	 * @param  string $fallbackTitle
	 * @return array|null
	 */
	private static function parse($raw, $fallbackTitle)
	{
		$title = $fallbackTitle;
		$keywords = array();
		$sources = '';
		$body = '';

		if (preg_match('/<!--\s*kb:title=(.*?)\s*-->/i', $raw, $m)) {
			$title = $m[1];
		}
		if (preg_match('/<!--\s*kb:keywords=(.*?)\s*-->/i', $raw, $m)) {
			$keywords = array_filter(array_map('trim', explode(',', $m[1])), 'strlen');
		}
		if (preg_match('/<!--\s*kb:sources=(.*?)\s*-->/i', $raw, $m)) {
			$sources = trim($m[1]);
		}

		// Body = everything except the three meta comments.
		$body = preg_replace('/<!--\s*kb:(title|keywords|sources)=.*?\s*-->/i', '', $raw);
		$body = self::stripSourceSection(trim((string) $body));
		if ($body === '' && $sources === '') {
			return null;
		}

		$body = self::truncate($body);

		return array(
			'title' => $title,
			'keywords' => $keywords,
			'body' => $body,
			// Sources are kept OUT of the budgeted body and appended at render time
			// by buildBlock(). Counting them inside the body starved the budget: the
			// footer is ~500 chars per file, so a two-file answer no longer fit and
			// the second (often more specific) file was dropped entirely.
			'sources' => $sources,
		);
	}

	/**
	 * Drop a trailing "## 来源" section from the body, since its content now lives
	 * in the kb:sources meta and would otherwise be duplicated.
	 *
	 * @param  string $body
	 * @return string
	 */
	private static function stripSourceSection($body)
	{
		$pos = strrpos($body, "\n## 来源");
		if ($pos !== false) {
			$body = substr($body, 0, $pos);
		}
		return trim($body);
	}

	/**
	 * Cap one file's body at a section boundary so we never end mid-sentence.
	 *
	 * @param  string $body
	 * @param  int    $limit  Defaults to MAX_PER_FILE
	 * @return string
	 */
	private static function truncate($body, $limit = self::MAX_PER_FILE)
	{
		if (strlen($body) <= $limit) {
			return $body;
		}
		$cut = substr($body, 0, $limit);
		$pos = strrpos($cut, "\n## ");
		if ($pos !== false && $pos > 200) {
			$cut = substr($cut, 0, $pos);
		}
		return rtrim($cut)."\n(…本节其余内容已省略)";
	}

	/**
	 * Admin-entered notes, from EMBEDDEDBOOKKEEPING_AI_KNOWLEDGE_EXTRA.
	 *
	 * @param  stdClass|null $conf
	 * @return string
	 */
	private static function adminNotes($conf)
	{
		$val = '';
		if (is_object($conf) && isset($conf->embeddedbookkeeping->knowledge_extra)) {
			$val = (string) $conf->embeddedbookkeeping->knowledge_extra;
		} elseif (is_object($conf) && isset($conf->global->EMBEDDEDBOOKKEEPING_AI_KNOWLEDGE_EXTRA)) {
			$val = (string) $conf->global->EMBEDDEDBOOKKEEPING_AI_KNOWLEDGE_EXTRA;
		}
		$val = trim($val);
		if ($val === '') {
			return '';
		}
		if (strlen($val) > self::MAX_ADMIN_NOTES) {
			$val = substr($val, 0, self::MAX_ADMIN_NOTES)."\n(…已截断)";
		}
		return $val;
	}

	/**
	 * Character budget from EMBEDDEDBOOKKEEPING_AI_KNOWLEDGE_MAXCHARS.
	 *
	 * Read through getDolGlobalInt() like the master switch rather than off
	 * $conf->global directly: two different lookups for two constants in the same
	 * class meant the budget ignored the $_SESSION "override constant" mechanism,
	 * so an admin testing a different budget in Setup got the old one at runtime.
	 *
	 * @param  stdClass|null $conf
	 * @return int
	 */
	private static function maxChars($conf)
	{
		$n = getDolGlobalInt('EMBEDDEDBOOKKEEPING_AI_KNOWLEDGE_MAXCHARS', self::DEFAULT_MAX_CHARS);
		if ($n < 500) {
			$n = self::DEFAULT_MAX_CHARS;
		}
		return $n;
	}

	/**
	 * Case-fold and simplify both ZH and EN before matching, so "GST" in the
	 * question hits the "gst" keyword in the file.
	 *
	 * @param  string $s
	 * @return string
	 */
	private static function normalize($s)
	{
		// Deliberately strtolower()/stripos() rather than the mb_* variants: mbstring
		// is not guaranteed on every host that runs this module, and byte-wise
		// matching is already correct here — stripos only case-folds ASCII bytes,
		// and no multi-byte UTF-8 sequence contains one, so Chinese keywords match
		// exactly while "GST" still matches "gst".
		return strtolower(trim((string) $s));
	}
}