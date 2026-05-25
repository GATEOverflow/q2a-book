<?php

if (!defined('QA_VERSION')) {
	header('Location: ../../../');
	exit;
}

@ob_clean();

class qa_book_ajax
{
	private $directory;
	private $urltoroot;

	public function load_module($directory, $urltoroot)
	{
		$this->directory = $directory;
		$this->urltoroot = $urltoroot;
	}

	public function suggest_requests()
	{
		return array();
	}

	public function match_request($request)
	{
		return $request === 'book-ajax';
	}

	public function process_request($request)
	{
		header('Content-Type: application/json; charset=utf-8');

		// --- Handle PDF / Hardcopy request submission ---
		if (qa_get('action') === 'book_request') {
			$token    = isset($_POST['csrf']) ? $_POST['csrf'] : '';
			if (!qa_check_form_security_code('book-request', $token)) {
				echo json_encode(array('error' => 'Invalid security token. Please reload the page.'));
				exit;
			}
			$type     = isset($_POST['type']) ? $_POST['type'] : '';
			$bookSlug = isset($_POST['book']) ? $_POST['book'] : '';
			$email    = isset($_POST['email']) ? trim($_POST['email']) : '';

			if (!in_array($type, array('pdf', 'hardcopy'), true)) {
				echo json_encode(array('error' => 'Invalid request type.'));
				exit;
			}
			$bookSlug = preg_replace('/[^a-zA-Z0-9_\- ]/', '', $bookSlug);
			if ($bookSlug === '') {
				echo json_encode(array('error' => 'No book specified.'));
				exit;
			}
			if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
				echo json_encode(array('error' => 'Invalid email address.'));
				exit;
			}

			$userId    = qa_get_logged_in_userid();
			$handle    = qa_get_logged_in_handle();
			$userEmail = qa_get_logged_in_email();
			if ($email === '' && $userEmail) {
				$email = $userEmail;
			}

			// Deduplicate: same user/email, same book+type within the last hour
			if ($userId) {
				$recent = qa_db_read_one_value(qa_db_query_sub(
					'SELECT COUNT(*) FROM ^book_pdf_requests WHERE bookslug=$ AND type=$ AND userid=$ AND created > DATE_SUB(NOW(), INTERVAL 1 HOUR)',
					$bookSlug, $type, $userId
				), true);
			} elseif ($email !== '') {
				$recent = qa_db_read_one_value(qa_db_query_sub(
					'SELECT COUNT(*) FROM ^book_pdf_requests WHERE bookslug=$ AND type=$ AND email=$ AND created > DATE_SUB(NOW(), INTERVAL 1 HOUR)',
					$bookSlug, $type, $email
				), true);
			} else {
				$recent = 0;
			}

			if ($recent > 0) {
				echo json_encode(array('ok' => true, 'msg' => 'Your request is already recorded. We\'ll follow up soon!'));
				exit;
			}

			qa_db_query_sub(
				'INSERT INTO ^book_pdf_requests (bookslug, type, userid, handle, email) VALUES ($, $, $, $, $)',
				$bookSlug, $type,
				$userId  ?: null,
				$handle  ?: null,
				$email   ?: null
			);

			$label = ($type === 'hardcopy') ? 'Hardcopy request' : 'PDF request';
			echo json_encode(array('ok' => true, 'msg' => $label . ' recorded. Thank you!'));
			exit;
		}

		$book = qa_get('book');
		$sectionId = qa_get('section');
		$sectionType = qa_get('type');

		// Handle lists queries via GET
		if ($sectionType === 'lists') {
			$this->handleListsRequest();
			return;
		}

		// Handle list toggle via POST
		if (qa_post_text('type') === 'listtoggle') {
			$this->handleListToggle();
			return;
		}

		// Handle notes queries via GET (load)
		if ($sectionType === 'notes') {
			$this->handleNoteLoad();
			return;
		}

		// Handle note save via POST
		if (qa_post_text('type') === 'notesave') {
			$this->handleNoteSave();
			return;
		}

		// Handle note delete via POST
		if (qa_post_text('type') === 'notedelete') {
			$this->handleNoteDelete();
			return;
		}

		// Handle question status queries via GET (batch load)
		if ($sectionType === 'qstatus') {
			$this->handleQStatusLoad();
			return;
		}

		// Handle question status set via POST
		if (qa_post_text('type') === 'qstatusset') {
			$this->handleQStatusSet();
			return;
		}

		if (empty($book) || empty($sectionId) || empty($sectionType)) {
			echo json_encode(array('error' => 'Missing parameters'));
			exit;
		}

		// Sanitize inputs
		$book = preg_replace('/[^a-zA-Z0-9_\- ]/', '', $book);
		$sectionId = preg_replace('/[^a-zA-Z0-9_\- ]/', '', $sectionId);
		$sectionType = preg_replace('/[^a-z]/', '', $sectionType);

		// Base directory is the book file write directory
		$bookLoc = qa_book_get('book_plugin_loc');
		$siteSlug = strtolower(str_replace(' ', '_', qa_opt('site_title')));
		$baseDir = ($bookLoc ? $bookLoc : $this->directory . 'book') . '/' . $siteSlug . '/';

		// Resolve book slug to path via allowed list (relative to baseDir)
		$allowedRaw = qa_book_get('book_viewer_allowed_books');
		$allowedList = $allowedRaw ? array_filter(array_map('trim', explode("\n", $allowedRaw))) : array();

		$filePath = '';
		foreach ($allowedList as $relPath) {
			if (basename($relPath, '.html') === $book) {
				$filePath = $baseDir . $relPath;
				break;
			}
		}

		if (!$filePath) {
			echo json_encode(array('error' => 'Book not available'));
			exit;
		}

		if (!file_exists($filePath)) {
			echo json_encode(array('error' => 'Book not found'));
			exit;
		}

		$html = $this->extractSection($filePath, $sectionId, $sectionType);

		echo json_encode(array(
			'html' => $html,
			'sectionId' => $sectionId,
			'type' => $sectionType,
		));

		exit;
	}

	/**
	 * Extract a section from the HTML file by its ID and type.
	 */
	private function extractSection($filePath, $sectionId, $type)
	{
		$content = file_get_contents($filePath);
		if ($content === false) {
			return '<p>Error reading file.</p>';
		}

		// Build answer key lookup from the full content
		$answerKeys = $this->buildAnswerKeyMap($content);

		switch ($type) {
			case 'category':
				return $this->extractCategory($content, $sectionId, $answerKeys);
			case 'topic':
				return $this->extractTopic($content, $sectionId, $answerKeys);
			case 'question':
				return $this->extractQuestion($content, $sectionId, $answerKeys);
			default:
				return '<p>Unknown section type.</p>';
		}
	}

	/**
	 * Parse all answer key tables and build a map: questionId => answer text
	 */
	private function buildAnswerKeyMap($content)
	{
		$map = array();
		if (preg_match_all("/id='akt-(\d+)'[^>]*>.*?<\/td>\s*<td class='akt-key'>(.+?)<\/td>/s", $content, $matches, PREG_SET_ORDER)) {
			foreach ($matches as $m) {
				$qId = 'question' . $m[1];
				$answerHtml = $m[2];
				$answerText = strip_tags($answerHtml);
				$answerText = trim($answerText);
				if ($answerText !== '') {
					$href = '';
					if (preg_match("/href=['\"]([^'\"]+)['\"]/", $answerHtml, $hm)) {
						$href = $hm[1];
					}
					$map[$qId] = array('text' => $answerText, 'href' => $href);
				}
			}
		}
		return $map;
	}

	/**
	 * Extract a category header (title + mark distribution + category intro) without questions.
	 */
	private function extractCategory($content, $catId, $answerKeys)
	{
		$idAttr = 'id="' . $catId . '"';
		$pos = strpos($content, $idAttr);
		if ($pos === false) {
			return '<p>Section not found.</p>';
		}

		$catStart = strrpos(substr($content, 0, $pos), '<div class="category">');
		if ($catStart === false) {
			$catStart = strrpos(substr($content, 0, $pos), '<div class="cat-title">');
		}

		// Find the end of this category's header area (up to first topic-block or next category)
		$remaining = substr($content, $catStart);
		$firstTopic = strpos($remaining, '<div class="topic-block"', 10);
		$headerEnd = $firstTopic !== false ? $firstTopic : 8000;

		$chunk = substr($remaining, 0, $headerEnd);

		$titleEnd = strpos($chunk, '</div>', strpos($chunk, 'class="cat-title"'));
		if ($titleEnd !== false) {
			$titleEnd += 6;
		}

		$markStart = strpos($chunk, '<div class="cat-mark-distribution">');
		$result = '';
		if ($titleEnd !== false) {
			$result = substr($chunk, 0, $titleEnd);
		}

		if ($markStart !== false) {
			$markEnd = strpos($chunk, '</div>', $markStart);
			if ($markEnd !== false) {
				$result .= substr($chunk, $markStart, $markEnd - $markStart + 6);
			}
		}

		// Extract category-intro if present
		$introStart = strpos($chunk, '<div class="category-intro">');
		if ($introStart !== false) {
			// Find the matching closing </div> — the intro can be large
			$introChunk = substr($remaining, $introStart, $headerEnd - $introStart);
			// Find the closing </div> for category-intro by matching nesting
			$depth = 0;
			$introEnd = 0;
			$searchPos = 0;
			while ($searchPos < strlen($introChunk)) {
				$nextOpen = strpos($introChunk, '<div', $searchPos);
				$nextClose = strpos($introChunk, '</div>', $searchPos);
				if ($nextClose === false) break;
				if ($nextOpen !== false && $nextOpen < $nextClose) {
					$depth++;
					$searchPos = $nextOpen + 4;
				} else {
					$depth--;
					if ($depth <= 0) {
						$introEnd = $nextClose + 6;
						break;
					}
					$searchPos = $nextClose + 6;
				}
			}
			if ($introEnd > 0) {
				$result .= substr($introChunk, 0, $introEnd);
			}
		}

		return '<div class="category">' . $result . '</div>';
	}

	/**
	 * Extract all questions under a topic.
	 */
	private function extractTopic($content, $topicId, $answerKeys)
	{
		$idAttr = 'id="' . $topicId . '"';
		$pos = strpos($content, $idAttr);
		if ($pos === false) {
			return '<p>Section not found.</p>';
		}

		$blockStart = strrpos(substr($content, 0, $pos), '<div class="topic-block"');
		if ($blockStart === false) {
			return '<p>Section not found.</p>';
		}

		$remaining = substr($content, $blockStart);
		$nextTopic = strpos($remaining, '<div class="topic-block"', 10);
		$nextCat = strpos($remaining, '<div class="category">', 10);
		$answerKeysSection = strpos($remaining, '<h2 class="answer-keys">', 10);

		$endPos = strlen($remaining);
		if ($nextTopic !== false) $endPos = min($endPos, $nextTopic);
		if ($nextCat !== false) $endPos = min($endPos, $nextCat);
		if ($answerKeysSection !== false) $endPos = min($endPos, $answerKeysSection);

		$result = substr($remaining, 0, $endPos);
		$result = $this->injectAnswerKeys($result, $answerKeys);

		return '<div class="page"><div class="questions">' . $result . '</div></div>';
	}

	/**
	 * Extract a single question with its answers.
	 */
	private function extractQuestion($content, $questionId, $answerKeys)
	{
		$idAttr = 'id="' . $questionId . '"';
		$pos = strpos($content, $idAttr);
		if ($pos === false) {
			return '<p>Question not found.</p>';
		}

		$lookBack = min($pos, 1000);
		$searchBack = substr($content, $pos - $lookBack, $lookBack);
		$qStart = strrpos($searchBack, '<div class="question">');
		if ($qStart === false) {
			$qStart = strrpos($searchBack, '<div class="question-title">');
			if ($qStart === false) {
				return '<p>Question boundary not found.</p>';
			}
		}
		$absoluteStart = ($pos - $lookBack) + $qStart;

		$remaining = substr($content, $absoluteStart);

		$nextQuestion = strpos($remaining, '<div class="question">', 10);
		$nextTopic = strpos($remaining, '<div class="topic-block"', 10);
		$nextCat = strpos($remaining, '<div class="category">', 10);
		$nextAkt = strpos($remaining, '<h2 class="answer-keys">', 10);

		$endPos = strlen($remaining);
		if ($nextQuestion !== false) $endPos = min($endPos, $nextQuestion);
		if ($nextTopic !== false) $endPos = min($endPos, $nextTopic);
		if ($nextCat !== false) $endPos = min($endPos, $nextCat);
		if ($nextAkt !== false) $endPos = min($endPos, $nextAkt);

		$result = substr($remaining, 0, $endPos);
		$result = $this->injectAnswerKeys($result, $answerKeys);

		return '<div class="page"><div class="questions">' . $result . '</div></div>';
	}

	/**
	 * Resolve a full page URL to a DB table prefix by matching against known network site base URLs.
	 * Falls back to current site prefix.
	 */
	private function resolvePrefix($siteUrl)
	{
		if (!empty($siteUrl) && function_exists('qa_network_sites_list')) {
			$sites = qa_network_sites_list();
			//error_log("resolvePrefix: siteUrl='$siteUrl', checking against " . count($sites) . " network sites");

			// Sort by URL length descending so longer (more specific) base URLs match first
			usort($sites, function ($a, $b) {
				return strlen($b['url']) - strlen($a['url']);
			});

			foreach ($sites as $site) {
				$baseUrl = rtrim($site['url'], '/') . '/';
				// Check if the incoming URL starts with this site's base URL (with trailing slash for precise matching)
				if (strpos($siteUrl, $baseUrl) === 0) {
					//error_log("resolvePrefix: matched site '{$site['title']}' (base='$baseUrl', prefix='{$site['prefix']}')");
					return $site['prefix'];
				}
			}

			//error_log("resolvePrefix: no network site matched for siteUrl='$siteUrl'");
		} elseif (empty($siteUrl)) {
			//error_log("resolvePrefix: siteUrl is empty, using current site prefix");
		} else {
			//error_log("resolvePrefix: qa_network_sites_list() function not available");
		}

		//error_log("resolvePrefix: Falling back to QA_MYSQL_TABLE_PREFIX");
		return QA_MYSQL_TABLE_PREFIX;
	}

	/**
	 * Return list names and which lists a single question belongs to for the logged-in user.
	 * GET ?type=lists&postid=473&siteurl=https://bt.gateoverflow.in
	 */
	private function handleListsRequest()
	{
		if (!function_exists('qa_lists_savelist')) {
			echo json_encode(array('error' => 'Lists plugin not available'));
			exit;
		}

		if (!qa_is_logged_in()) {
			echo json_encode(array('loggedIn' => false));
			exit;
		}

		$userid = qa_get_logged_in_userid();
		$postid = (int) qa_get('postid');

		if (!$postid) {
			echo json_encode(array('error' => 'Missing postid'));
			exit;
		}

		$prefix = $this->resolvePrefix(qa_get('siteurl'));

		$listCount = (int) qa_opt('qa-lists-count');
		$listNames = array();
		for ($i = 0; $i <= $listCount; $i++) {
			$listNames[$i] = qa_opt('qa-lists-id-name' . $i);
		}

		$checkedLists = array();
		try {
			$table = $prefix . 'userquestionlists';
			$row = qa_db_read_one_assoc(qa_db_query_sub(
				"SELECT listids FROM $table WHERE userid=# AND questionid=#",
				$userid, $postid
			), true);
			if ($row && !empty($row['listids'])) {
				$checkedLists = array_map('intval', explode(',', $row['listids']));
			}
		} catch (Exception $e) {
			echo json_encode(array('error' => 'Lists table not found'));
			exit;
		}

		echo json_encode(array(
			'loggedIn' => true,
			'listCount' => $listCount,
			'listNames' => $listNames,
			'checkedLists' => $checkedLists,
		));
		exit;
	}

	/**
	 * Toggle a question in/out of a list for the logged-in user.
	 * POST type=listtoggle, postid=473, listid=1, checked=1|0, siteurl=https://bt.gateoverflow.in
	 */
	private function handleListToggle()
	{
		if (!function_exists('qa_lists_savelist')) {
			echo json_encode(array('error' => 'Lists plugin not available'));
			exit;
		}

		if (!qa_is_logged_in()) {
			echo json_encode(array('error' => 'Not logged in'));
			exit;
		}

		$userid = qa_get_logged_in_userid();
		$postid = (int) qa_post_text('postid');
		$listid = (int) qa_post_text('listid');
		$checked = qa_post_text('checked') === '1';

		if (!$postid) {
			echo json_encode(array('error' => 'Missing postid'));
			exit;
		}

		$prefix = $this->resolvePrefix(qa_post_text('siteurl'));

		// If current site, use qa_lists_savelist directly
		if ($prefix === QA_MYSQL_TABLE_PREFIX) {
			if ($checked) {
				qa_lists_savelist($userid, $postid, array($listid), array());
			} else {
				qa_lists_savelist($userid, $postid, array(), array($listid));
			}

			if ($listid === 0) {
				if ($checked) {
					qa_db_query_sub(
						"INSERT IGNORE INTO ^userfavorites (userid, entitytype, entityid, nouserevents) VALUES (#, 'Q', #, 0)",
						$userid, $postid
					);
				} else {
					qa_db_query_sub(
						"DELETE FROM ^userfavorites WHERE userid=# AND entitytype='Q' AND entityid=#",
						$userid, $postid
					);
				}
			}
		} else {
			// Cross-site: direct SQL with resolved prefix
			$this->toggleListDirect($prefix, $userid, $postid, $listid, $checked);
		}

		echo json_encode(array('success' => true));
		exit;
	}

	/**
	 * Direct SQL list toggle for cross-site questions.
	 */
	private function toggleListDirect($prefix, $userid, $postid, $listid, $checked)
	{
		$ulTable = $prefix . 'userlists';
		$uqlTable = $prefix . 'userquestionlists';

		// Read current questionids for this user+list
		$row = qa_db_read_one_assoc(qa_db_query_sub(
			"SELECT questionids FROM $ulTable WHERE userid=# AND listid=#",
			$userid, $listid
		), true);

		if ($checked) {
			if ($row === null) {
				qa_db_query_sub(
					"INSERT INTO $ulTable (userid, listid, questionids) VALUES (#, #, #)",
					$userid, $listid, (string) $postid
				);
			} else {
				$ids = $row['questionids'] ? explode(',', $row['questionids']) : array();
				if (!in_array((string) $postid, $ids)) {
					$ids[] = $postid;
					$csv = implode(',', $ids);
					qa_db_query_sub(
						"UPDATE $ulTable SET questionids=\$ WHERE userid=# AND listid=#",
						$csv, $userid, $listid
					);
				}
			}
		} else {
			if ($row !== null && $row['questionids']) {
				$ids = explode(',', $row['questionids']);
				$ids = array_filter($ids, function ($q) use ($postid) { return (int) $q !== $postid; });
				$csv = implode(',', $ids);
				qa_db_query_sub(
					"UPDATE $ulTable SET questionids=\$ WHERE userid=# AND listid=#",
					$csv, $userid, $listid
				);
			}
		}

		// Rebuild inverse mapping
		$rows = qa_db_read_all_assoc(qa_db_query_sub(
			"SELECT listid FROM $ulTable WHERE userid=# AND FIND_IN_SET(#, questionids)",
			$userid, $postid
		));
		$listids = array();
		foreach ($rows as $r) {
			$listids[] = $r['listid'];
		}

		if (!empty($listids)) {
			$csv = implode(',', $listids);
			qa_db_query_sub(
				"INSERT INTO $uqlTable (userid, questionid, listids) VALUES (#, #, \$) ON DUPLICATE KEY UPDATE listids=\$",
				$userid, $postid, $csv, $csv
			);
		} else {
			qa_db_query_sub(
				"DELETE FROM $uqlTable WHERE userid=# AND questionid=#",
				$userid, $postid
			);
		}

		// Sync userfavorites for list 0
		if ($listid === 0) {
			$favTable = $prefix . 'userfavorites';
			if ($checked) {
				qa_db_query_sub(
					"INSERT IGNORE INTO $favTable (userid, entitytype, entityid, nouserevents) VALUES (#, 'Q', #, 0)",
					$userid, $postid
				);
			} else {
				qa_db_query_sub(
					"DELETE FROM $favTable WHERE userid=# AND entitytype='Q' AND entityid=#",
					$userid, $postid
				);
			}
		}
	}

	// --- Notes Integration ---

	/**
	 * Load a note for a question.
	 * GET ?type=notes&postid=473&siteurl=http://localhost/q2a2/166/...
	 */
	private function handleNoteLoad()
	{
		if (!qa_is_logged_in()) {
			echo json_encode(array('loggedIn' => false));
			exit;
		}

		$userid = qa_get_logged_in_userid();
		$postid = (int) qa_get('postid');

		if (!$postid) {
			echo json_encode(array('error' => 'Missing postid'));
			exit;
		}

		$prefix = $this->resolvePrefix(qa_get('siteurl'));
		$table = $prefix . 'usernote';

		try {
			$note = qa_db_read_one_value(qa_db_query_sub(
				"SELECT note FROM $table WHERE postid=# AND userid=#",
				$postid, $userid
			), true);
		} catch (Exception $e) {
			echo json_encode(array('error' => 'Notes table not found'));
			exit;
		}

		echo json_encode(array(
			'loggedIn' => true,
			'note' => $note !== null ? $note : '',
		));
		exit;
	}

	/**
	 * Save a note for a question.
	 * POST type=notesave, postid=473, text=..., siteurl=...
	 */
	private function handleNoteSave()
	{
		if (!qa_is_logged_in()) {
			echo json_encode(array('error' => 'Not logged in'));
			exit;
		}

		$userid = qa_get_logged_in_userid();
		$postid = (int) qa_post_text('postid');
		$text = trim(qa_post_text('text'));

		if (!$postid) {
			echo json_encode(array('error' => 'Missing postid'));
			exit;
		}

		$prefix = $this->resolvePrefix(qa_post_text('siteurl'));
		$table = $prefix . 'usernote';

		if ($text === '') {
			// Empty text = delete
			qa_db_query_sub("DELETE FROM $table WHERE postid=# AND userid=#", $postid, $userid);
			echo json_encode(array('success' => true, 'deleted' => true));
			exit;
		}

		try {
			$exists = (int) qa_db_read_one_value(qa_db_query_sub(
				"SELECT COUNT(*) FROM $table WHERE postid=# AND userid=#",
				$postid, $userid
			));

			if ($exists) {
				qa_db_query_sub("UPDATE $table SET note=\$ WHERE postid=# AND userid=#", $text, $postid, $userid);
			} else {
				qa_db_query_sub("INSERT INTO $table (postid, userid, note) VALUES (#, #, \$)", $postid, $userid, $text);
			}
		} catch (Exception $e) {
			echo json_encode(array('error' => 'Notes table not found'));
			exit;
		}

		echo json_encode(array('success' => true));
		exit;
	}

	/**
	 * Delete a note for a question.
	 * POST type=notedelete, postid=473, siteurl=...
	 */
	private function handleNoteDelete()
	{
		if (!qa_is_logged_in()) {
			echo json_encode(array('error' => 'Not logged in'));
			exit;
		}

		$userid = qa_get_logged_in_userid();
		$postid = (int) qa_post_text('postid');

		if (!$postid) {
			echo json_encode(array('error' => 'Missing postid'));
			exit;
		}

		$prefix = $this->resolvePrefix(qa_post_text('siteurl'));
		$table = $prefix . 'usernote';

		try {
			qa_db_query_sub("DELETE FROM $table WHERE postid=# AND userid=#", $postid, $userid);
		} catch (Exception $e) {
			echo json_encode(array('error' => 'Notes table not found'));
			exit;
		}

		echo json_encode(array('success' => true));
		exit;
	}

	// --- Question Status Integration ---

	/**
	 * Batch-load question statuses for visible questions.
	 * GET ?type=qstatus&postids=101,102,103&siteurl=http://localhost/q2a2/...
	 */
	private function handleQStatusLoad()
	{
		if (!qa_is_logged_in()) {
			echo json_encode(array('loggedIn' => false));
			exit;
		}

		$userid = qa_get_logged_in_userid();
		$postidsRaw = qa_get('postids');

		if (empty($postidsRaw)) {
			echo json_encode(array('error' => 'Missing postids'));
			exit;
		}

		$prefix = $this->resolvePrefix(qa_get('siteurl'));
		$postids = array_map('intval', array_filter(explode(',', $postidsRaw)));

		if (empty($postids)) {
			echo json_encode(array('loggedIn' => true, 'statuses' => new \stdClass()));
			exit;
		}

		$table = qa_db_add_table_prefix('book_question_status');
		$placeholders = implode(',', array_fill(0, count($postids), '#'));
		$escapedPrefix = qa_db_argument_to_mysql($prefix, true);

		$params = array_merge(array($userid, $prefix), $postids);

		$rows = qa_db_read_all_assoc(qa_db_query_sub(
			"SELECT postid, status FROM $table WHERE userid=# AND site_prefix=\$ AND postid IN ($placeholders)",
			...$params
		));

		$statuses = array();
		foreach ($rows as $row) {
			$statuses[$row['postid']] = $row['status'];
		}

		echo json_encode(array(
			'loggedIn' => true,
			'statuses' => !empty($statuses) ? $statuses : new \stdClass(),
		));
		exit;
	}

	/**
	 * Set question status.
	 * POST type=qstatusset, postid=473, status=completed|skipped|wrong|clear, siteurl=...
	 */
	private function handleQStatusSet()
	{
		if (!qa_is_logged_in()) {
			echo json_encode(array('error' => 'Not logged in'));
			exit;
		}

		$userid = qa_get_logged_in_userid();
		$postid = (int) qa_post_text('postid');
		$status = qa_post_text('status');

		if (!$postid) {
			echo json_encode(array('error' => 'Missing postid'));
			exit;
		}

		$prefix = $this->resolvePrefix(qa_post_text('siteurl'));
		$table = qa_db_add_table_prefix('book_question_status');

		$validStatuses = array('skipped', 'completed', 'wrong');

		if ($status === 'clear' || !in_array($status, $validStatuses)) {
			// Clear status
			qa_db_query_sub(
				"DELETE FROM $table WHERE userid=# AND postid=# AND site_prefix=\$",
				$userid, $postid, $prefix
			);
			echo json_encode(array('success' => true, 'status' => ''));
			exit;
		}

		qa_db_query_sub(
			"INSERT INTO $table (userid, postid, site_prefix, status) VALUES (#, #, \$, \$) ON DUPLICATE KEY UPDATE status=\$",
			$userid, $postid, $prefix, $status, $status
		);

		echo json_encode(array('success' => true, 'status' => $status));
		exit;
	}

	/**
	 * Replace answer-link placeholders with actual answer key values.
	 */
	private function injectAnswerKeys($html, $answerKeys)
	{
		return preg_replace_callback(
			'/<a\s+class="answer-link"\s+href="#akt-(\d+)"[^>]*>[^<]*<\/a>/',
			function ($m) use ($answerKeys) {
				$qId = 'question' . $m[1];
				if (isset($answerKeys[$qId])) {
					$entry = $answerKeys[$qId];
					$answerText = $entry['text'];
					$href = $entry['href'];
					if ($answerText === 'N/A') {
						return '';
					}
					$answer = htmlspecialchars($answerText, ENT_QUOTES, 'UTF-8');
					$linkHtml = '';
					if ($href !== '') {
						$safeHref = htmlspecialchars($href, ENT_QUOTES, 'UTF-8');
						$linkHtml = ' <a class="bv-answer-link" href="' . $safeHref . '" target="_blank" title="View answer">&#128279;</a>';
					}
					return '<span class="bv-answer-key"><span class="bv-answer-label">Answer:</span> '
						. '<span class="bv-answer-value bv-answer-hidden" onclick="this.classList.toggle(\'bv-answer-hidden\')">' 
						. $answer . $linkHtml . '</span></span>';
				}
				return $m[0];
			},
			$html
		);
	}
}
