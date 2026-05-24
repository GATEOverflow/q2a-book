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
