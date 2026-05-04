<?php

if (!defined('QA_VERSION')) {
	header('Location: ../../');
	exit;
}

class qa_book_suggested_tags_page
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
				'title' => 'Suggested Tags',
				'request' => 'admin/book-suggested-tags',
				'nav' => null,
			),
		);
	}

	public function match_request($request)
	{
		return $request === 'admin/book-suggested-tags';
	}

	public function process_request($request)
	{
		if (qa_get_logged_in_level() < QA_USER_LEVEL_ADMIN) {
			return include QA_INCLUDE_DIR . 'qa-page-not-found.php';
		}

		$messageHtml = '';

		// Handle approve action
		if (qa_clicked('approve_tag')) {
			$ok = qa_check_form_security_code('book-suggested-tags', qa_post_text('code'));
			if (!$ok) {
				$messageHtml = $this->msg('Security check failed. Please retry.', 'error');
			} else {
				$suggestionId = (int) qa_post_text('suggestion_id');
				$postid = (int) qa_post_text('postid');
				$tags = trim((string) qa_post_text('suggested_tags'));
				$result = $this->approveSuggestion($suggestionId, $postid, $tags);
				$messageHtml = $this->msg($result['message'], $result['ok'] ? 'success' : 'error');
			}
		}

		// Handle reject action
		if (qa_clicked('reject_tag')) {
			$ok = qa_check_form_security_code('book-suggested-tags', qa_post_text('code'));
			if (!$ok) {
				$messageHtml = $this->msg('Security check failed. Please retry.', 'error');
			} else {
				$suggestionId = (int) qa_post_text('suggestion_id');
				qa_db_query_sub("UPDATE ^tag_suggestions SET status='rejected' WHERE id=#", $suggestionId);
				$messageHtml = $this->msg('Suggestion #' . $suggestionId . ' rejected.', 'success');
			}
		}

		// Handle "add to extra filter tags" action
		if (qa_clicked('add_to_filter')) {
			$ok = qa_check_form_security_code('book-suggested-tags', qa_post_text('code'));
			if (!$ok) {
				$messageHtml = $this->msg('Security check failed. Please retry.', 'error');
			} else {
				$suggestionId = (int) qa_post_text('suggestion_id');
				$tagToFilter = trim((string) qa_post_text('filter_tag'));
				if ($tagToFilter !== '') {
					// Add to all volumes' extra filter tags
					for ($v = 1; $v <= 4; $v++) {
						$current = qa_book_get('extra_filter_tags_vol' . $v);
						$tags = array_filter(array_map('trim', explode(',', (string) $current)));
						if (!in_array($tagToFilter, $tags)) {
							$tags[] = $tagToFilter;
							qa_book_set('extra_filter_tags_vol' . $v, implode(',', $tags));
						}
					}
					// Reject this suggestion and all others suggesting this same tag
					qa_db_query_sub("UPDATE ^tag_suggestions SET status='rejected' WHERE suggested_tags=$ AND status IS NULL", $tagToFilter);
					$messageHtml = $this->msg('Tag "' . qa_html($tagToFilter) . '" added to extra filter tags (all volumes) and all suggestions for it rejected.', 'success');
				}
			}
		}

		// Handle "remove tag from owner" action
		if (qa_clicked('remove_from_owner')) {
			$ok = qa_check_form_security_code('book-suggested-tags', qa_post_text('code'));
			if (!$ok) {
				$messageHtml = $this->msg('Security check failed. Please retry.', 'error');
			} else {
				$suggestionId = (int) qa_post_text('suggestion_id');
				$ownerPostId = (int) qa_post_text('owner_postid');
				$tagToRemove = trim((string) qa_post_text('filter_tag'));
				if ($ownerPostId > 0 && $tagToRemove !== '') {
					$post = qa_db_read_one_assoc(qa_db_query_sub(
						"SELECT postid, tags FROM ^posts WHERE postid=# AND type='Q'", $ownerPostId
					), true);
					if ($post) {
						$existingTags = array_filter(array_map('trim', explode(',', (string) $post['tags'])));
						$existingTags = array_diff($existingTags, [$tagToRemove]);
						qa_db_query_sub("UPDATE ^posts SET tags=$ WHERE postid=#", implode(',', $existingTags), $ownerPostId);
						// Also update word table tag count
						qa_db_query_sub("UPDATE ^words SET tagcount=GREATEST(0, tagcount-1) WHERE word=$", $tagToRemove);
					}
					// Reject all suggestions for this tag
					qa_db_query_sub("UPDATE ^tag_suggestions SET status='rejected' WHERE suggested_tags=$ AND status IS NULL", $tagToRemove);
					$messageHtml = $this->msg('Tag "' . qa_html($tagToRemove) . '" removed from owner Q#' . $ownerPostId . ' and suggestions rejected.', 'success');
				}
			}
		}

		// Handle merge approve (mark as done)
		if (qa_clicked('approve_merge')) {
			$ok = qa_check_form_security_code('book-suggested-tags', qa_post_text('code'));
			if (!$ok) {
				$messageHtml = $this->msg('Security check failed. Please retry.', 'error');
			} else {
				$mergeId = (int) qa_post_text('merge_id');
				qa_db_query_sub("UPDATE ^tag_merge_suggestions SET status='approved' WHERE id=#", $mergeId);
				$messageHtml = $this->msg('Merge #' . $mergeId . ' marked as done.', 'success');
			}
		}

		// Handle merge reject
		if (qa_clicked('reject_merge')) {
			$ok = qa_check_form_security_code('book-suggested-tags', qa_post_text('code'));
			if (!$ok) {
				$messageHtml = $this->msg('Security check failed. Please retry.', 'error');
			} else {
				$mergeId = (int) qa_post_text('merge_id');
				qa_db_query_sub("UPDATE ^tag_merge_suggestions SET status='rejected' WHERE id=#", $mergeId);
				$messageHtml = $this->msg('Merge #' . $mergeId . ' rejected.', 'success');
			}
		}

		$qa_content = qa_content_prepare();
		$qa_content['title'] = 'Suggested Tags (from DB)';

		$selectedCategory = (int) qa_get('categoryid');
		$page = max(1, (int) qa_get('page'));
		$perPage = (int) qa_get('per_page');
		if ($perPage < 10) $perPage = 50;
		if ($perPage > 200) $perPage = 200;
		$offset = ($page - 1) * $perPage;

		// Get categories that have pending suggestions
		$categories = qa_db_read_all_assoc(qa_db_query_sub(
			"SELECT DISTINCT c.categoryid, c.title
			 FROM ^categories c
			 JOIN ^posts p ON p.categoryid = c.categoryid
			 JOIN ^tag_suggestions ts ON ts.postid = p.postid
			 WHERE ts.status IS NULL
			 ORDER BY c.title"
		));

		// Fetch pending suggestions with pagination
		$catFilter = '';
		if ($selectedCategory > 0) {
			$catFilter = " AND p.categoryid = " . (int) $selectedCategory;
		}

		// Get total count for pagination
		$totalPending = (int) qa_db_read_one_value(qa_db_query_sub(
			"SELECT COUNT(*) FROM ^tag_suggestions ts JOIN ^posts p ON ts.postid = p.postid WHERE ts.status IS NULL" . $catFilter
		), true);

		$rows = qa_db_read_all_assoc(qa_db_query_sub(
			"SELECT ts.id, ts.postid, ts.suggested_tags, ts.source, ts.created,
			        p.title, p.tags, p.content, p.categoryid, c.title AS category_title
			 FROM ^tag_suggestions ts
			 JOIN ^posts p ON ts.postid = p.postid
			 LEFT JOIN ^categories c ON p.categoryid = c.categoryid
			 WHERE ts.status IS NULL" . $catFilter . "
			 ORDER BY ts.created DESC
			 LIMIT # OFFSET #",
			$perPage, $offset
		));

		$totalPages = max(1, (int) ceil($totalPending / $perPage));

		// Build filter form
		$navHtml = '<div style="margin-bottom:15px; font-size:13px;">';
		$navHtml .= '<a href="' . qa_path_html('book') . '">→ Book</a>';
		$navHtml .= ' &nbsp;|&nbsp; <a href="' . qa_path_html('topic-exams') . '">→ Topic Exams</a>';
		$navHtml .= ' &nbsp;|&nbsp; <a href="' . qa_path_html('tag-review') . '">→ Tag Review (old)</a>';
		$navHtml .= '</div>';

		$formHtml = '<form method="get" action="' . qa_html(qa_path('admin/book-suggested-tags')) . '" style="margin-bottom:20px; display:flex; gap:12px; align-items:center; flex-wrap:wrap;">';
		$formHtml .= '<label>Category: <select name="categoryid" style="padding:4px 8px; max-width:320px;">';
		$formHtml .= '<option value="0">All Categories</option>';
		foreach ($categories as $cat) {
			$catId = (int) $cat['categoryid'];
			$selected = ($catId === $selectedCategory) ? ' selected' : '';
			$formHtml .= '<option value="' . $catId . '"' . $selected . '>' . qa_html($cat['title']) . '</option>';
		}
		$formHtml .= '</select></label>';
		$formHtml .= '<label>Per page: <input type="number" min="10" max="200" name="per_page" value="' . (int) $perPage . '" style="width:70px; padding:4px 8px;"></label>';
		$formHtml .= '<button type="submit" style="padding:6px 16px; cursor:pointer;">Filter</button>';
		$formHtml .= '</form>';

		$summaryHtml = '<p style="margin:0 0 12px; color:#666;">';
		$summaryHtml .= 'Page ' . $page . ' of ' . $totalPages . ' (' . $totalPending . ' pending suggestions). ';
		$summaryHtml .= 'Approve to apply tags to the post, or reject to dismiss.';
		$summaryHtml .= '</p>';

		$securityCode = qa_get_form_security_code('book-suggested-tags');

		$tableHtml = '<table style="width:100%; border-collapse:collapse; font-size:13px;">';
		$tableHtml .= '<thead><tr style="background:#f5f5f5; border-bottom:2px solid #ddd;">';
		$tableHtml .= '<th style="padding:8px; text-align:left;">Category</th>';
		$tableHtml .= '<th style="padding:8px; text-align:left;">Question</th>';
		$tableHtml .= '<th style="padding:8px; text-align:left;">Current Tags</th>';
		$tableHtml .= '<th style="padding:8px; text-align:left;">Suggested Tags</th>';
		$tableHtml .= '<th style="padding:8px; text-align:left;">Owner</th>';
		$tableHtml .= '<th style="padding:8px; text-align:left;">Date</th>';
		$tableHtml .= '<th style="padding:8px; text-align:left;">Action</th>';
		$tableHtml .= '</tr></thead><tbody>';

		if (empty($rows)) {
			$tableHtml .= '<tr><td colspan="7" style="padding:20px; text-align:center; color:#888;">No pending tag suggestions.</td></tr>';
		}

		// Pre-load owner question titles for singleton suggestions
		$ownerIds = [];
		foreach ($rows as $row) {
			if (!empty($row['source']) && preg_match('/owner:(\d+)/', $row['source'], $m)) {
				$ownerIds[(int)$m[1]] = true;
			}
		}
		$ownerTitles = [];
		if (!empty($ownerIds)) {
			$ownerRows = qa_db_read_all_assoc(qa_db_query_sub(
				"SELECT postid, CAST(title AS CHAR) AS title FROM ^posts WHERE postid IN (" . implode(',', array_keys($ownerIds)) . ")"
			));
			foreach ($ownerRows as $or) {
				$ownerTitles[(int)$or['postid']] = $or['title'];
			}
		}

		foreach ($rows as $row) {
			$questionUrl = qa_path_html(qa_q_request((int) $row['postid'], $row['title']));
			$currentTags = qa_html($row['tags']);

			// Deduplicate and filter out special/ignored tags from suggestions
			$currentTagList = array_map('trim', explode(',', (string) $row['tags']));
			$rawSuggested = array_map('trim', explode(',', (string) $row['suggested_tags']));
			$filteredSuggested = array();
			foreach ($rawSuggested as $st) {
				if ($st === '' || ignoredtags($st)) continue;
				if (!in_array($st, $filteredSuggested)) $filteredSuggested[] = $st;
			}

			// Skip row if no useful suggestions remain after filtering
			if (empty($filteredSuggested)) continue;

			// Only show suggestions that add new tags (skip if subset of existing)
			$newTags = array_values(array_diff($filteredSuggested, $currentTagList));
			if (empty($newTags)) continue;

			$suggestedTags = qa_html(implode(',', $newTags));
			$filteredSuggested = $newTags; // only pass new tags to approve action
			$newTagsHtml = '<div style="margin-top:3px; font-size:11px; color:#1b5e20;">New: <b>' . qa_html(implode(', ', $newTags)) . '</b></div>';

			$tableHtml .= '<tr style="border-bottom:1px solid #eee;">';
			$tableHtml .= '<td style="padding:8px; white-space:nowrap;">' . qa_html($row['category_title']) . '</td>';
			$tooltip = qa_html(mb_substr(strip_tags((string)$row['content']), 0, 300));
			$tableHtml .= '<td style="padding:8px;"><a href="' . $questionUrl . '" target="_blank" title="' . $tooltip . '">' . qa_html($row['title']) . '</a></td>';
			$tableHtml .= '<td style="padding:8px; font-size:12px;">' . $currentTags . '</td>';
			$tableHtml .= '<td style="padding:8px; font-size:12px;">' . $suggestedTags . $newTagsHtml . '</td>';

			// Owner column
			$ownerHtml = '-';
			$ownerPostId = 0;
			if (!empty($row['source']) && preg_match('/owner:(\d+)/', $row['source'], $m)) {
				$ownerPostId = (int) $m[1];
				$ownerTitle = $ownerTitles[$ownerPostId] ?? 'Q#' . $ownerPostId;
				$ownerUrl = qa_path_html(qa_q_request($ownerPostId, $ownerTitle));
				$ownerHtml = '<a href="' . $ownerUrl . '" target="_blank" style="font-size:11px;">' . qa_html(mb_substr($ownerTitle, 0, 40)) . '</a>';
			}
			$tableHtml .= '<td style="padding:8px; font-size:11px;">' . $ownerHtml . '</td>';

			$tableHtml .= '<td style="padding:8px; font-size:11px; white-space:nowrap;">' . qa_html(substr($row['created'], 0, 10)) . '</td>';

			$formAction = qa_html(qa_path('admin/book-suggested-tags', array('categoryid' => $selectedCategory, 'per_page' => $perPage, 'page' => $page)));
			$actionHtml = '<form method="post" action="' . $formAction . '" style="margin:0; display:flex; gap:4px; flex-wrap:wrap;">'
				. '<input type="hidden" name="code" value="' . qa_html($securityCode) . '">'
				. '<input type="hidden" name="suggestion_id" value="' . (int) $row['id'] . '">'
				. '<input type="hidden" name="postid" value="' . (int) $row['postid'] . '">'
				. '<input type="hidden" name="suggested_tags" value="' . qa_html(implode(',', $filteredSuggested)) . '">'
				. '<input type="hidden" name="filter_tag" value="' . qa_html($newTags[0]) . '">'
				. '<input type="hidden" name="owner_postid" value="' . $ownerPostId . '">'
				. '<button type="submit" name="approve_tag" class="btn-approve">Approve</button>'
				. '<button type="submit" name="reject_tag" class="btn-reject">Reject</button>'
				. '<button type="submit" name="add_to_filter" class="btn-filter" title="Add tag to extra_filter_tags for all volumes">+Filter</button>';
			if ($ownerPostId > 0) {
				$actionHtml .= '<button type="submit" name="remove_from_owner" class="btn-remove" title="Remove tag from owner question">−Owner</button>';
			}
			$actionHtml .= '</form>';
			$tableHtml .= '<td style="padding:8px; white-space:nowrap;">' . $actionHtml . '</td>';
			$tableHtml .= '</tr>';
		}

		$tableHtml .= '</tbody></table>';

		// Pagination links
		$paginationHtml = '<div style="margin-top:20px; display:flex; gap:8px; align-items:center; justify-content:center;">';
		$baseParams = array('categoryid' => $selectedCategory, 'per_page' => $perPage);
		if ($page > 1) {
			$paginationHtml .= '<a href="' . qa_path_html('admin/book-suggested-tags', $baseParams + array('page' => $page - 1)) . '" style="padding:6px 14px; border:1px solid #ccc; text-decoration:none;">← Prev</a>';
		}
		$paginationHtml .= '<span style="padding:6px 10px; color:#555;">Page ' . $page . ' / ' . $totalPages . '</span>';
		if ($page < $totalPages) {
			$paginationHtml .= '<a href="' . qa_path_html('admin/book-suggested-tags', $baseParams + array('page' => $page + 1)) . '" style="padding:6px 14px; border:1px solid #ccc; text-decoration:none;">Next →</a>';
		}
		$paginationHtml .= '</div>';

		// ---- Merge Suggestions Section ----
		$mergeHtml = '';
		$mergeRows = qa_db_read_all_assoc(qa_db_query_sub(
			"SELECT id, tag_a, tag_b, category, created FROM ^tag_merge_suggestions WHERE status='pending' ORDER BY created DESC LIMIT 100"
		));
		if (!empty($mergeRows)) {
			$mergeHtml .= '<h3 style="margin-top:30px; border-top:2px solid #ddd; padding-top:16px;">Tag Merge Suggestions</h3>';
			$mergeHtml .= '<p style="color:#666; font-size:12px;">Rename tag_a → tag_b. Copy the "Merge Pair" value into tagging-tools for bulk rename.</p>';
			$mergeHtml .= '<table style="width:100%; border-collapse:collapse; font-size:13px;">';
			$mergeHtml .= '<thead><tr style="background:#f5f5f5; border-bottom:2px solid #ddd;">';
			$mergeHtml .= '<th style="padding:8px; text-align:left;">Rename (tag_a)</th>';
			$mergeHtml .= '<th style="padding:8px; text-align:left;">To (tag_b)</th>';
			$mergeHtml .= '<th style="padding:8px; text-align:left;">Category</th>';
			$mergeHtml .= '<th style="padding:8px; text-align:left;">Merge Pair</th>';
			$mergeHtml .= '<th style="padding:8px; text-align:left;">Action</th>';
			$mergeHtml .= '</tr></thead><tbody>';

			foreach ($mergeRows as $mr) {
				$mergePair = qa_html($mr['tag_a'] . ',' . $mr['tag_b']);
				$mergeHtml .= '<tr style="border-bottom:1px solid #eee;">';
				$mergeHtml .= '<td style="padding:8px;">' . qa_html($mr['tag_a']) . '</td>';
				$mergeHtml .= '<td style="padding:8px;"><b>' . qa_html($mr['tag_b']) . '</b></td>';
				$mergeHtml .= '<td style="padding:8px; font-size:11px;">' . qa_html($mr['category']) . '</td>';
				$mergeHtml .= '<td style="padding:8px;"><code style="background:#f0f0f0; padding:2px 6px; border-radius:3px; user-select:all;">' . $mergePair . '</code></td>';
				$mergeHtml .= '<td style="padding:8px;">'
					. '<form method="post" action="' . qa_html(qa_path('admin/book-suggested-tags', array('categoryid' => $selectedCategory, 'per_page' => $perPage, 'page' => $page))) . '" style="margin:0; display:flex; gap:4px;">'
					. '<input type="hidden" name="code" value="' . qa_html($securityCode) . '">'
					. '<input type="hidden" name="merge_id" value="' . (int) $mr['id'] . '">'
					. '<button type="submit" name="approve_merge" class="btn-approve">Done</button>'
					. '<button type="submit" name="reject_merge" class="btn-reject">Reject</button>'
					. '</form></td>';
				$mergeHtml .= '</tr>';
			}
			$mergeHtml .= '</tbody></table>';
		}

		$qa_content['custom'] = '<div id="book-suggested-tags">' . $navHtml . $messageHtml . $formHtml . $summaryHtml . $tableHtml . $paginationHtml . $mergeHtml . $this->darkModeCss() . '</div>';

		return $qa_content;
	}

	private function approveSuggestion($suggestionId, $postid, $suggestedTags)
	{
		$post = qa_db_read_one_assoc(qa_db_query_sub(
			"SELECT postid, tags FROM ^posts WHERE postid=# AND type='Q'",
			$postid
		), true);

		if (!$post) {
			return array('ok' => false, 'message' => 'Question not found.');
		}

		// Merge suggested tags with existing (deduplicate, exclude special tags)
		$currentTags = array_filter(array_map('trim', explode(',', (string) $post['tags'])));
		$rawNew = array_filter(array_map('trim', explode(',', $suggestedTags)));
		$newTags = array();
		foreach ($rawNew as $t) {
			if ($t !== '' && !ignoredtags($t) && !in_array($t, $newTags)) $newTags[] = $t;
		}
		$mergedTags = array_values(array_unique(array_merge($currentTags, $newTags)));
		$newTagString = implode(',', $mergedTags);

		qa_db_query_sub("UPDATE ^posts SET tags=$ WHERE postid=#", $newTagString, $postid);

		// Update posttags and word counts
		foreach ($mergedTags as $oneTag) {
			if ($oneTag === '') continue;
			$wordId = qa_db_read_one_value(qa_db_query_sub(
				"SELECT wordid FROM ^words WHERE word=$", $oneTag
			), true);

			if (!$wordId) {
				qa_db_query_sub("INSERT INTO ^words (word, tagcount) VALUES ($, 0)", $oneTag);
				$wordId = qa_db_last_insert_id();
			}

			if ($wordId) {
				qa_db_query_sub("INSERT IGNORE INTO ^posttags (postid, wordid) VALUES (#, #)", $postid, (int) $wordId);
			}
		}

		// Update tag counts for all affected words
		qa_db_query_sub(
			"UPDATE ^words w SET tagcount=(SELECT COUNT(*) FROM ^posttags pt WHERE pt.wordid=w.wordid)
			 WHERE w.word IN ($)",
			$newTagString
		);

		// Mark suggestion as approved
		qa_db_query_sub("UPDATE ^tag_suggestions SET status='approved', suggested_tags=$ WHERE id=#", $newTagString, $suggestionId);

		$added = array_diff($mergedTags, $currentTags);
		$addedStr = !empty($added) ? ' Added: ' . implode(', ', $added) : ' (no new tags)';
		return array('ok' => true, 'message' => 'Approved suggestion #' . $suggestionId . ' for question #' . $postid . '.' . $addedStr);
	}

	private function msg($text, $type)
	{
		$bg = $type === 'success' ? '#e8f5e9' : '#ffebee';
		$color = $type === 'success' ? '#1b5e20' : '#b71c1c';
		$border = $type === 'success' ? '#a5d6a7' : '#ef9a9a';
		return '<div style="margin:0 0 12px; padding:10px 12px; background:' . $bg . '; color:' . $color . '; border:1px solid ' . $border . ';">' . qa_html($text) . '</div>';
	}

	private function darkModeCss()
	{
		return '<style>
#book-suggested-tags .btn-approve,
#book-suggested-tags .btn-reject,
#book-suggested-tags .btn-filter,
#book-suggested-tags .btn-remove { padding:4px 10px; cursor:pointer; border-radius:3px; font-size:12px; }
#book-suggested-tags .btn-approve { background:#e8f5e9; color:#2e7d32; border:1px solid #a5d6a7; }
#book-suggested-tags .btn-reject { background:#ffebee; color:#c62828; border:1px solid #ef9a9a; }
#book-suggested-tags .btn-filter { background:#fff3e0; color:#e65100; border:1px solid #ffcc80; }
#book-suggested-tags .btn-remove { background:#e3f2fd; color:#1565c0; border:1px solid #90caf9; }
[data-theme="dark"] #book-suggested-tags table { color:#ddd; }
[data-theme="dark"] #book-suggested-tags thead tr { background:#2a2a2a !important; border-bottom-color:#444 !important; }
[data-theme="dark"] #book-suggested-tags tbody tr { border-bottom-color:#333 !important; }
[data-theme="dark"] #book-suggested-tags a { color:#64b5f6; }
[data-theme="dark"] #book-suggested-tags input,
[data-theme="dark"] #book-suggested-tags select { background:#2a2a3a; color:#e0e0e0; border:1px solid #444; }
[data-theme="dark"] #book-suggested-tags .btn-approve { background:#1b5e20; color:#c8e6c9; border-color:#388e3c; }
[data-theme="dark"] #book-suggested-tags .btn-reject { background:#b71c1c; color:#ffcdd2; border-color:#d32f2f; }
[data-theme="dark"] #book-suggested-tags .btn-filter { background:#e65100; color:#fff3e0; border-color:#ff9800; }
[data-theme="dark"] #book-suggested-tags .btn-remove { background:#0d47a1; color:#bbdefb; border-color:#1976d2; }
</style>';
	}
}