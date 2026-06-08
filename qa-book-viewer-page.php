<?php

if (!defined('QA_VERSION')) {
	header('Location: ../../../');
	exit;
}

class qa_book_page
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
		return array(
			array(
				'title' => 'Book Viewer',
				'request' => 'book',
				'nav' => 'M',
			),
		);
	}

	public function match_request($request)
	{
		return $request === 'book' || strpos($request, 'book/') === 0;
	}

	public function process_request($request)
	{
		$qa_content = qa_content_prepare();
		$qa_content['title'] = 'GATE Overflow PYQ Book';

		// Determine which book file to show
		$parts = explode('/', $request);
		$bookSlug = isset($parts[1]) ? $parts[1] : '';

		// Base directory is the book file write directory
		$bookLoc = qa_book_get('book_plugin_loc');
		$siteSlug = strtolower(str_replace(' ', '_', qa_opt('site_title')));
		$baseDir = ($bookLoc ? $bookLoc : $this->directory . 'book') . '/' . $siteSlug . '/';

		// Allowed book relative paths (relative to baseDir)
		$allowedRaw = qa_book_get('book_viewer_allowed_books');
		$allowedList = $allowedRaw ? array_filter(array_map('trim', explode("\n", $allowedRaw))) : array();

		$books = array();
		$bookPaths = array();
		foreach ($allowedList as $relPath) {
			$fullPath = $baseDir . $relPath;
			if (file_exists($fullPath)) {
				$slug = basename($relPath, '.html');
				$title = $this->extractBookTitle($fullPath, $slug);
				$books[$slug] = $title;
				$bookPaths[$slug] = $fullPath;
			}
		}

		// Build the TOC index for the selected book
		$selectedBook = '';
		$tocJson = '[]';
		if ($bookSlug && isset($books[$bookSlug])) {
			$selectedBook = $bookSlug;
			$tocJson = $this->buildTocJson($bookPaths[$bookSlug]);
		} elseif (count($books) === 1) {
			$selectedBook = key($books);
			$tocJson = $this->buildTocJson($bookPaths[$selectedBook]);
		}

		// PDF download link (per book slug) and user context for request modal
		$pdfUrl      = ($selectedBook ? (qa_book_get('book_pdf_url__' . $selectedBook) ?: '') : '');
		$bookTitle   = ($selectedBook && isset($books[$selectedBook])) ? $books[$selectedBook] : $selectedBook;
		$userId      = qa_get_logged_in_userid();
		$userHandle  = qa_get_logged_in_handle() ?: '';
		$userEmail   = qa_get_logged_in_email()  ?: '';
		$csrfToken   = qa_get_form_security_code('book-request');

		// Build book selector dropdown
		$bookOptions = '';
		foreach ($books as $slug => $title) {
			$sel = ($slug === $selectedBook) ? ' selected' : '';
			$bookOptions .= '<option value="' . qa_html($slug) . '"' . $sel . '>' . qa_html($title) . '</option>';
		}
		$version = 15;

		$rootUrl = qa_path_html('book');
		$ajaxUrl = qa_path_html('book-ajax');
		$listsEnabled = function_exists('qa_lists_savelist') ? 'true' : 'false';
		$notesEnabled = function_exists('qa_note_to_html') ? 'true' : 'false';
		$cssUrl = qa_html($this->urltoroot . 'css/book-viewer.css?v=' . $version);
		$jsUrl = qa_html($this->urltoroot . 'js/book-viewer.js?v=' . $version);

		// Build PDF / hardcopy toolbar buttons (only when a book is selected)
		$pdfButtonHtml = '';
		if ($selectedBook) {
			if ($pdfUrl) {
				$pdfButtonHtml = '<a href="' . qa_html($pdfUrl) . '" class="bv-btn-pdf-dl" target="_blank" rel="noopener">&#8615; Download PDF</a>';
			} else {
				$pdfButtonHtml = '<button class="bv-btn-pdf-req" onclick="BookViewer.requestPdf()">Request PDF</button>';
			}
			$pdfButtonHtml .= ' <button class="bv-btn-hardcopy" onclick="BookViewer.requestHardcopy()">&#128218; Request Hardcopy</button>';
		}

		$pdfUrlJson     = json_encode($pdfUrl);
		$bookTitleJson  = json_encode($bookTitle);
		$userIdJson     = $userId ? (int)$userId : 'null';
		$userHandleJson = json_encode($userHandle);
		$userEmailJson  = json_encode($userEmail);
		$csrfJson       = json_encode($csrfToken);

		$qa_content['custom'] = <<<HTML
<link rel="stylesheet" href="{$cssUrl}">
<script type="text/x-mathjax-config">
	MathJax.Hub.Config({
		tex2jax: {
			inlineMath: [['\$','\$'], ['\\\\(','\\\\)']],
			displayMath: [['\$\$','\$\$'], ['\\\\[','\\\\]']],
			processEscapes: true
		},
		menuSettings: {CHTMLpreview: false},
		skipStartupTypeset: true
	});
</script>
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/mathjax/2.7.9/MathJax.js?config=TeX-AMS-MML_HTMLorMML"></script>
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/prettify/r298/prettify.min.js"></script>
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/prettify/r298/lang-c.min.js"></script>
<div id="book-viewer-app">
	<div class="bv-toolbar">
		<button id="bv-toggle-sidebar" onclick="BookViewer.toggleSidebar()" title="Toggle sidebar">&#9776;</button>
		<select id="bv-book-select" onchange="BookViewer.selectBook(this.value)">
			<option value="">-- Select a Book --</option>
			{$bookOptions}
		</select>
		<input type="text" id="bv-search" placeholder="Search sections..." oninput="BookViewer.filterToc(this.value)">
		<input type="text" id="bv-tag-filter" placeholder="Filter by tag... e.g. gatecse-2009" oninput="BookViewer.filterByTag(this.value)">
		<span id="bv-tag-count"></span>
		<button id="bv-expand-all" onclick="BookViewer.expandAll()">Expand All</button>
		<button id="bv-collapse-all" onclick="BookViewer.collapseAll()">Collapse All</button>
		{$pdfButtonHtml}
		<button id="bv-fullscreen" onclick="BookViewer.toggleFullscreen()" title="Fullscreen">&#x26F6;</button>
	</div>
	<div class="bv-container">
		<nav id="bv-sidebar" class="bv-sidebar">
			<div id="bv-toc-tree"></div>
		</nav>
		<main id="bv-content" class="bv-content">
			<div id="bv-content-area">
				<p class="bv-placeholder">Select a section from the sidebar to view its content.</p>
			</div>
		</main>
	</div>
</div>
<div id="bv-request-modal" style="display:none">
	<div class="bv-modal-overlay" onclick="BookViewer.closeModal()"></div>
	<div class="bv-modal-box">
		<h3 id="bv-modal-title">Request</h3>
		<p id="bv-modal-desc"></p>
		<div id="bv-modal-email-row">
			<label for="bv-modal-email">Your email (so we can notify you):</label>
			<input type="email" id="bv-modal-email" placeholder="your@email.com">
		</div>
		<div class="bv-modal-actions">
			<button onclick="BookViewer.submitRequest()" class="bv-modal-submit">Submit Request</button>
			<button onclick="BookViewer.closeModal()" class="bv-modal-cancel">Cancel</button>
		</div>
		<div id="bv-modal-result"></div>
	</div>
</div>
<script>
	var BookViewerConfig = {
		ajaxUrl: '{$ajaxUrl}',
		rootUrl: '{$rootUrl}',
		selectedBook: '{$selectedBook}',
		listsEnabled: {$listsEnabled},
		notesEnabled: {$notesEnabled},
		toc: {$tocJson},
		pdfUrl: {$pdfUrlJson},
		bookTitle: {$bookTitleJson},
		userId: {$userIdJson},
		userHandle: {$userHandleJson},
		userEmail: {$userEmailJson},
		csrfToken: {$csrfJson}
	};
</script>
<script src="{$jsUrl}"></script>
HTML;

		return $qa_content;
	}

	/**
	 * Extract the <title> from an HTML book file. Falls back to a humanized slug.
	 */
	private function extractBookTitle($filePath, $fallback)
	{
		$handle = fopen($filePath, 'r');
		if (!$handle) return $fallback;

		$chunk = fread($handle, 4096);
		fclose($handle);

		if (preg_match('/<title>([^<]+)<\/title>/i', $chunk, $m)) {
			return trim($m[1]);
		}

		return str_replace('_', ' ', ucfirst($fallback));
	}

	/**
	 * Parse the HTML file and build a TOC structure as JSON.
	 * Extracts categories, topics, and question titles with their line positions.
	 */
	private function buildTocJson($filePath)
	{
		if (!file_exists($filePath)) {
			return '[]';
		}

		$cacheFile = $filePath . '.toc.json';
		$fileModTime = filemtime($filePath);

		// Use cached TOC if available and fresh
		if (file_exists($cacheFile) && filemtime($cacheFile) >= $fileModTime) {
			return file_get_contents($cacheFile);
		}

		$toc = array();
		$handle = fopen($filePath, 'r');
		if (!$handle) {
			return '[]';
		}

		$lineNum = 0;
		$catIdx = -1;
		$topicIdx = -1;

		while (($line = fgets($handle)) !== false) {
			$lineNum++;

			// Match category
			if (strpos($line, 'class="cat-title"') !== false) {
				if (preg_match('/id="(cat\d+)"/', $line, $idMatch) &&
					preg_match('/<span class="number">(\d+)<\/span>\s*([^<]+)<\/a>/', $line, $nameMatch)) {
					$toc[] = array(
						'id' => $idMatch[1],
						'number' => $nameMatch[1],
						'title' => trim($nameMatch[2]),
						'line' => $lineNum,
						'type' => 'category',
						'children' => array(),
					);
					$catIdx = count($toc) - 1;
					$topicIdx = -1;
				}
			}

			// Match topic
			if (strpos($line, 'class="topic-block"') !== false) {
				if (preg_match('/id="([^"]+)"/', $line, $idMatch) &&
					preg_match('/<span class="number">([\d.]+)<\/span>\s*([^<]+)<\/a>/', $line, $nameMatch)) {

					$count = '';
					if (preg_match('/topic-title-count[^>]*>\s*\((\d+)\)/', $line, $cntMatch)) {
						$count = $cntMatch[1];
					}

					$topic = array(
						'id' => $idMatch[1],
						'number' => $nameMatch[1],
						'title' => trim($nameMatch[2]),
						'count' => $count,
						'line' => $lineNum,
						'type' => 'topic',
						'children' => array(),
					);
					if ($catIdx >= 0) {
						$toc[$catIdx]['children'][] = $topic;
						$topicIdx = count($toc[$catIdx]['children']) - 1;
					}
				}
			}

			// Match question
			if (strpos($line, 'class="question-title"') !== false) {
				if (preg_match('/id="(question\d+)"/', $line, $idMatch) &&
					preg_match('/<span class="number">([\d.]+)<\/span>([^<]+)<\/a>/', $line, $nameMatch)) {
					$question = array(
						'id' => $idMatch[1],
						'number' => $nameMatch[1],
						'title' => trim($nameMatch[2]),
						'line' => $lineNum,
						'type' => 'question',
					);
					if ($catIdx >= 0 && $topicIdx >= 0) {
						$toc[$catIdx]['children'][$topicIdx]['children'][] = $question;
					} elseif ($catIdx >= 0) {
						$toc[$catIdx]['children'][] = $question;
					}
				}
			}
		}

		fclose($handle);

		$json = json_encode($toc, JSON_UNESCAPED_UNICODE);

		// Cache the TOC
		@file_put_contents($cacheFile, $json);

		return $json;
	}
}
