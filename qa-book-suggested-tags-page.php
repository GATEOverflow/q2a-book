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

		// --- Branch suggestion approve ---
		if (qa_clicked('approve_branch_tag')) {
			$ok = qa_check_form_security_code('book-suggested-tags', qa_post_text('code'));
			if (!$ok) {
				$messageHtml = $this->msg('Security check failed. Please retry.', 'error');
			} else {
				$branchCode   = preg_replace('/[^a-z]/', '', (string) qa_post_text('branch_code'));
				$suggestionId = (int) qa_post_text('suggestion_id');
				$postid       = (int) qa_post_text('postid');
				$tags         = trim((string) qa_post_text('suggested_tags'));
				$branches     = $this->getBranches();
				if (isset($branches[$branchCode])) {
					$result = $this->approveBranchSuggestion($suggestionId, $postid, $tags, $branches[$branchCode]['prefix']);
					$messageHtml = $this->msg($result['message'], $result['ok'] ? 'success' : 'error');
				}
			}
		}

		// --- Branch suggestion reject ---
		if (qa_clicked('reject_branch_tag')) {
			$ok = qa_check_form_security_code('book-suggested-tags', qa_post_text('code'));
			if ($ok) {
				$branchCode   = preg_replace('/[^a-z]/', '', (string) qa_post_text('branch_code'));
				$suggestionId = (int) qa_post_text('suggestion_id');
				$branches     = $this->getBranches();
				if (isset($branches[$branchCode])) {
					$prefix = $branches[$branchCode]['prefix'];
					qa_db_query_sub("UPDATE {$prefix}tag_suggestions SET status='rejected' WHERE id=#", $suggestionId);
					$messageHtml = $this->msg('Rejected suggestion #' . $suggestionId . '.', 'success');
				}
			}
		}

		// --- Generate EM tag suggestions for a branch (co-occurrence) ---
		if (qa_clicked('generate_em_suggestions')) {
			$ok = qa_check_form_security_code('book-suggested-tags', qa_post_text('code'));
			if ($ok) {
				$branchCode = preg_replace('/[^a-z]/', '', (string) qa_post_text('gen_branch'));
				$dryRun     = (bool) qa_post_text('gen_dryrun');
				$branches   = $this->getBranches();
				if (isset($branches[$branchCode])) {
					$result = $this->generateEmSuggestions($branchCode, $dryRun);
					$messageHtml = $this->msg(
						'EM suggestion run for ' . $branches[$branchCode]['name'] .
						($dryRun ? ' (dry run)' : '') . ': ' .
						$result['inserted'] . ' inserted, ' . $result['skipped'] . ' skipped.',
						'success'
					);
				}
			}
		}

		// --- Generate cross-site tag rename (merge) suggestions ---
		if (qa_clicked('generate_em_merges')) {
			$ok = qa_check_form_security_code('book-suggested-tags', qa_post_text('code'));
			if ($ok) {
				$branchCode = preg_replace('/[^a-z]/', '', (string) qa_post_text('merge_branch'));
				$branches   = $this->getBranches();
				if (isset($branches[$branchCode])) {
					$result = $this->generateTagMergeSuggestions($branchCode);
					$messageHtml = $this->msg(
						'Tag rename suggestions for ' . $branches[$branchCode]['name'] . ': ' .
						$result['inserted'] . ' inserted, ' . $result['skipped'] . ' skipped.',
						'success'
					);
				}
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

		// ---- Network EM Suggestions ----
		$selectedBranch = preg_replace('/[^a-z]/', '', (string) qa_get('branch'));
		$branches       = $this->getBranches();
		$baseUrlParams  = ['categoryid' => $selectedCategory, 'per_page' => $perPage, 'page' => $page];

		// Tab bar
		$networkHtml  = '<div style="margin-top:30px;border-top:2px solid #ddd;padding-top:18px;">';
		$networkHtml .= '<h3 style="margin:0 0 12px">EM Network Suggestions</h3>';
		$networkHtml .= '<div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:16px;align-items:center">';
		$isMain = ($selectedBranch === '');
		$networkHtml .= '<a href="' . qa_path_html('admin/book-suggested-tags', $baseUrlParams) . '" '
			. 'style="padding:5px 13px;border:1px solid #ccc;border-radius:4px;text-decoration:none;font-size:13px;'
			. ($isMain ? 'background:#0969da;color:#fff;border-color:#0969da' : 'color:inherit') . '">Main Site</a>';
		foreach ($branches as $bCode => $bInfo) {
			$isActive = ($bCode === $selectedBranch);
			$cnt = $this->getBranchPendingCount($bCode);
			$networkHtml .= '<a href="' . qa_path_html('admin/book-suggested-tags', ['branch' => $bCode] + $baseUrlParams) . '" '
				. 'style="padding:5px 13px;border:1px solid #ccc;border-radius:4px;text-decoration:none;font-size:13px;'
				. ($isActive ? 'background:#0969da;color:#fff;border-color:#0969da' : 'color:inherit') . '">'
				. qa_html($bInfo['name'])
				. '<span style="font-size:11px;margin-left:5px;opacity:0.75">(' . $cnt . ')</span></a>';
		}
		$networkHtml .= '</div>';

		// Branch suggestions table (only when a branch is selected)
		if ($selectedBranch && isset($branches[$selectedBranch])) {
			$networkHtml .= $this->renderBranchSuggestions(
				$selectedBranch, $branches[$selectedBranch], $securityCode, $selectedCategory, $page, $perPage
			);
		}

		// Generate / merge form (always shown)
		$networkHtml .= $this->renderGenerateForm($securityCode, $branches, $selectedBranch);
		$networkHtml .= '</div>';

		$qa_content['custom'] = '<div id="book-suggested-tags">' . $navHtml . $messageHtml . $formHtml . $summaryHtml . $tableHtml . $paginationHtml . $mergeHtml . $networkHtml . $this->darkModeCss() . '</div>';

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

	// =====================================================================
	// Branch / Network helpers
	// =====================================================================

	private function getBranches()
	{
		return [
			'ee' => ['prefix' => 'qaee_',    'url' => 'https://ee.gateoverflow.in/',     'name' => 'GO Electrical'],
			'ec' => ['prefix' => 'qaec_',    'url' => 'https://ec.gateoverflow.in/',     'name' => 'GO Electronics'],
			'me' => ['prefix' => 'qame_',    'url' => 'https://me.gateoverflow.in/',     'name' => 'GO Mechanical'],
			'ce' => ['prefix' => 'qacivil_', 'url' => 'https://civil.gateoverflow.in/', 'name' => 'GO Civil'],
			'ch' => ['prefix' => 'qach_',    'url' => 'https://ch.gateoverflow.in/',     'name' => 'GO Chemical'],
			'in' => ['prefix' => 'qain_',    'url' => 'https://in.gateoverflow.in/',     'name' => 'GO Instrumentation'],
			'bt' => ['prefix' => 'qabt_',    'url' => 'https://bt.gateoverflow.in/',     'name' => 'GO Biotech'],
		];
	}

	private function getEmCategoryTags()
	{
		return qa_db_read_all_assoc(qa_db_query_sub(
			'SELECT title, tags FROM qa_em_categories ORDER BY categoryid'
		));
	}

	private function getBranchPendingCount($branchCode)
	{
		$branches = $this->getBranches();
		if (!isset($branches[$branchCode])) return 0;
		$prefix = $branches[$branchCode]['prefix'];
		return (int) qa_db_read_one_value(
			qa_db_query_sub("SELECT COUNT(*) FROM {$prefix}tag_suggestions WHERE status IS NULL"),
			true
		);
	}

	/**
	 * Apply approved tags to a branch site's posts table.
	 */
	private function approveBranchSuggestion($suggestionId, $postid, $suggestedTags, $prefix)
	{
		$post = qa_db_read_one_assoc(qa_db_query_sub(
			"SELECT postid, tags FROM {$prefix}posts WHERE postid=# AND type='Q'", $postid
		), true);
		if (!$post) {
			return ['ok' => false, 'message' => 'Question #' . $postid . ' not found in branch.'];
		}

		$currentTags = array_filter(array_map('trim', explode(',', (string) $post['tags'])));
		$newTags = [];
		foreach (array_filter(array_map('trim', explode(',', $suggestedTags))) as $t) {
			if ($t !== '' && !ignoredtags($t) && !in_array($t, $newTags)) $newTags[] = $t;
		}
		$mergedTags   = array_values(array_unique(array_merge($currentTags, $newTags)));
		$newTagString = implode(',', $mergedTags);

		qa_db_query_sub("UPDATE {$prefix}posts SET tags=$ WHERE postid=#", $newTagString, $postid);

		foreach ($mergedTags as $oneTag) {
			if ($oneTag === '') continue;
			$wordId = qa_db_read_one_value(
				qa_db_query_sub("SELECT wordid FROM {$prefix}words WHERE word=$", $oneTag), true
			);
			if (!$wordId) {
				qa_db_query_sub("INSERT INTO {$prefix}words (word, tagcount) VALUES ($, 0)", $oneTag);
				$wordId = qa_db_last_insert_id();
			}
			if ($wordId) {
				qa_db_query_sub("INSERT IGNORE INTO {$prefix}posttags (postid, wordid) VALUES (#, #)", $postid, (int) $wordId);
			}
		}
		qa_db_query_sub(
			"UPDATE {$prefix}words w SET tagcount=(SELECT COUNT(*) FROM {$prefix}posttags pt WHERE pt.wordid=w.wordid) WHERE w.word IN ($)",
			$newTagString
		);
		qa_db_query_sub(
			"UPDATE {$prefix}tag_suggestions SET status='approved', suggested_tags=$ WHERE id=#",
			$newTagString, $suggestionId
		);

		$added = array_diff($mergedTags, $currentTags);
		$addedStr = !empty($added) ? ' Added: ' . implode(', ', $added) : ' (no new tags)';
		return ['ok' => true, 'message' => 'Approved #' . $suggestionId . ' for Q#' . $postid . '.' . $addedStr];
	}

	/**
	 * Build a tag co-occurrence map from main site EM questions.
	 * Returns cooc[tagA][tagB] = frequency.
	 */
	private function buildMainSiteTagCooccurrence($emTag)
	{
		$rows = qa_db_read_all_assoc(qa_db_query_sub(
			"SELECT tags FROM qa_posts WHERE type='Q' AND FIND_IN_SET($, tags) LIMIT 3000",
			$emTag
		));

		$skipPrefixes = ['gate', 'goclasses', 'isro', 'ugc', 'tifr', 'isi', 'drdo', 'barc', 'nielit', 'cmi', 'go2', 'cat', 'navathe'];
		$skipExact    = ['normal', 'easy', 'difficult', 'marks-to-all', 'numerical-answers', 'descriptive',
		                 'debated', '1-mark', '2-marks', 'one-mark', 'two-marks', 'multiple-selects',
		                 'engineering-mathematics', 'aptitude'];

		$cooc = [];
		foreach ($rows as $row) {
			$tags      = array_filter(array_map('trim', explode(',', (string) $row['tags'])));
			$subtopics = [];
			foreach ($tags as $t) {
				if ($t === $emTag || in_array($t, $skipExact)) continue;
				$skip = false;
				foreach ($skipPrefixes as $pf) {
					if (strpos($t, $pf) === 0) { $skip = true; break; }
				}
				if (!$skip && !ignoredtags($t)) $subtopics[] = $t;
			}
			for ($i = 0, $n = count($subtopics); $i < $n; $i++) {
				for ($j = 0; $j < $n; $j++) {
					if ($i === $j) continue;
					$cooc[$subtopics[$i]][$subtopics[$j]] = ($cooc[$subtopics[$i]][$subtopics[$j]] ?? 0) + 1;
				}
			}
		}
		return $cooc;
	}

	/**
	 * Generate EM tag suggestions for a branch site using co-occurrence against main site.
	 * Only enriches questions that already have at least one subtopic tag.
	 */
	private function generateEmSuggestions($branchCode, $dryRun = false)
	{
		$branches = $this->getBranches();
		if (!isset($branches[$branchCode])) return ['inserted' => 0, 'skipped' => 0];

		$prefix   = $branches[$branchCode]['prefix'];
		$emCats   = $this->getEmCategoryTags();
		$inserted = 0;
		$skipped  = 0;

		$skipExact = ['normal', 'easy', 'difficult', 'marks-to-all', 'numerical-answers',
		              'descriptive', 'debated', '1-mark', '2-marks', 'one-mark', 'two-marks', 'multiple-selects'];

		foreach ($emCats as $emCat) {
			$emTag = $emCat['tags'];
			$cooc  = $this->buildMainSiteTagCooccurrence($emTag);

			$branchQs = qa_db_read_all_assoc(qa_db_query_sub(
				"SELECT postid, tags FROM {$prefix}posts WHERE type='Q' AND FIND_IN_SET($, tags) AND tags NOT LIKE '%memorybased%'",
				$emTag
			));

			foreach ($branchQs as $bq) {
				$postid      = (int) $bq['postid'];
				$currentTags = array_filter(array_map('trim', explode(',', (string) $bq['tags'])));

				// Extract existing subtopic tags
				$existingSubtopics = [];
				foreach ($currentTags as $t) {
					if (ignoredtags($t) || preg_match('/^gate/i', $t) || $t === $emTag || in_array($t, $skipExact)) continue;
					$existingSubtopics[] = $t;
				}

				// Skip questions with no subtopic tags — co-occurrence needs at least one anchor
				if (empty($existingSubtopics)) { $skipped++; continue; }

				// Skip if already has a pending suggestion
				$pending = (int) qa_db_read_one_value(qa_db_query_sub(
					"SELECT COUNT(*) FROM {$prefix}tag_suggestions WHERE postid=# AND status IS NULL", $postid
				), true);
				if ($pending > 0) { $skipped++; continue; }

				// Collect candidate tags via co-occurrence
				$candidates = [];
				foreach ($existingSubtopics as $subtopic) {
					if (!isset($cooc[$subtopic])) continue;
					foreach ($cooc[$subtopic] as $coTag => $freq) {
						if ($freq < 3 || in_array($coTag, $currentTags) || ignoredtags($coTag)) continue;
						$candidates[$coTag] = ($candidates[$coTag] ?? 0) + $freq;
					}
				}
				if (empty($candidates)) { $skipped++; continue; }

				arsort($candidates);
				$topCandidates = array_slice(array_keys($candidates), 0, 5);

				if (!$dryRun) {
					qa_db_query_sub(
						"INSERT INTO {$prefix}tag_suggestions (postid, suggested_tags, source) VALUES (#, $, $)",
						$postid, implode(',', $topCandidates), 'em-crosssite:' . $emTag
					);
				}
				$inserted++;
			}
		}
		return ['inserted' => $inserted, 'skipped' => $skipped];
	}

	/**
	 * Generate tag rename suggestions for a branch by comparing its EM tag vocabulary
	 * against the main site's canonical tag vocabulary.
	 * Stores results in ^tag_merge_suggestions with branch column set.
	 */
	private function generateTagMergeSuggestions($branchCode)
	{
		$branches = $this->getBranches();
		if (!isset($branches[$branchCode])) return ['inserted' => 0, 'skipped' => 0];

		$prefix   = $branches[$branchCode]['prefix'];
		$branchName = $branches[$branchCode]['name'];
		$emCats   = $this->getEmCategoryTags();
		$emTags   = array_column($emCats, 'tags');

		$skipPfx   = ['gate', 'goclasses', 'isro', 'ugc', 'tifr', 'isi', 'drdo', 'barc', 'nielit', 'cmi', 'go2', 'cat'];
		$skipExact = ['normal', 'easy', 'difficult', 'marks-to-all', 'numerical-answers', 'descriptive',
		              'debated', '1-mark', '2-marks', 'one-mark', 'two-marks', 'multiple-selects',
		              'engineering-mathematics', 'aptitude'];

		$collectTags = function ($table) use ($emTags, $skipPfx, $skipExact) {
			$freq = [];
			foreach ($emTags as $emTag) {
				$rows = qa_db_read_all_assoc(qa_db_query_sub(
					"SELECT tags FROM {$table}posts WHERE type='Q' AND FIND_IN_SET($, tags) LIMIT 2000", $emTag
				));
				foreach ($rows as $row) {
					foreach (array_filter(array_map('trim', explode(',', (string) $row['tags']))) as $t) {
						if ($t === $emTag || in_array($t, $skipExact)) continue;
						$skip = false;
						foreach ($skipPfx as $pf) { if (strpos($t, $pf) === 0) { $skip = true; break; } }
						if (!$skip && !ignoredtags($t)) $freq[$t] = ($freq[$t] ?? 0) + 1;
					}
				}
			}
			return $freq;
		};

		$mainTags   = $collectTags('qa_');
		$branchTags = $collectTags($prefix);

		$inserted = 0;
		$skipped  = 0;

		foreach ($branchTags as $branchTag => $branchFreq) {
			if ($branchFreq < 2) { $skipped++; continue; } // too rare
			if (isset($mainTags[$branchTag])) { $skipped++; continue; } // same tag already on main

			// Find closest matching main site tag
			$bestMatch = null;
			$bestScore = 0.0;
			foreach ($mainTags as $mainTag => $mainFreq) {
				$score = $this->tagSimilarity($branchTag, $mainTag);
				if ($score > $bestScore && $score >= 0.7) {
					$bestScore = $score;
					$bestMatch = $mainTag;
				}
			}
			if (!$bestMatch) { $skipped++; continue; }

			// Skip if already suggested
			$exists = (int) qa_db_read_one_value(qa_db_query_sub(
				"SELECT COUNT(*) FROM ^tag_merge_suggestions WHERE tag_a=$ AND branch=$",
				$branchTag, $branchCode
			), true);
			if ($exists > 0) { $skipped++; continue; }

			qa_db_query_sub(
				"INSERT IGNORE INTO ^tag_merge_suggestions (tag_a, tag_b, category, branch, status) VALUES ($, $, $, $, 'pending')",
				$branchTag, $bestMatch, $branchName, $branchCode
			);
			$inserted++;
		}
		return ['inserted' => $inserted, 'skipped' => $skipped];
	}

	/** Simple tag similarity score (0–1). */
	private function tagSimilarity($a, $b)
	{
		if ($a === $b) return 1.0;
		// Normalise trailing s / es / ing
		$norm = function ($s) {
			$s = rtrim($s, 's');
			$s = rtrim($s, 'e');
			return $s;
		};
		if ($norm($a) === $norm($b)) return 0.95;
		if (strpos($a, $b) !== false || strpos($b, $a) !== false) return 0.85;
		$maxLen = max(strlen($a), strlen($b));
		if ($maxLen === 0) return 1.0;
		$dist = levenshtein($a, $b);
		if ($dist <= 2 && $maxLen >= 8) return 0.9 - ($dist * 0.05);
		return 0.0;
	}

	/**
	 * Render the table of pending suggestions for a branch site.
	 */
	private function renderBranchSuggestions($branchCode, $branchInfo, $securityCode, $selectedCategory, $page, $perPage)
	{
		$prefix  = $branchInfo['prefix'];
		$siteUrl = $branchInfo['url'];
		$offset  = ($page - 1) * $perPage;

		$total = (int) qa_db_read_one_value(qa_db_query_sub(
			"SELECT COUNT(*) FROM {$prefix}tag_suggestions ts
			 JOIN {$prefix}posts p ON ts.postid = p.postid WHERE ts.status IS NULL"
		), true);

		$rows = qa_db_read_all_assoc(qa_db_query_sub(
			"SELECT ts.id, ts.postid, ts.suggested_tags, ts.source, ts.created, p.title, p.tags
			 FROM {$prefix}tag_suggestions ts
			 JOIN {$prefix}posts p ON ts.postid = p.postid
			 WHERE ts.status IS NULL
			 ORDER BY (ts.source LIKE 'em-crosssite%') DESC, ts.created DESC
			 LIMIT # OFFSET #",
			$perPage, $offset
		));

		$totalPages = max(1, (int) ceil($total / $perPage));

		$html  = '<h4 style="margin:0 0 8px">' . qa_html($branchInfo['name']) . ' — Pending Suggestions (' . $total . ')</h4>';

		if (empty($rows)) {
			$html .= '<p style="color:#888"><i>No pending suggestions.</i></p>';
			return $html;
		}

		$html .= '<table style="width:100%;border-collapse:collapse;font-size:13px">';
		$html .= '<thead><tr style="background:#f5f5f5;border-bottom:2px solid #ddd">';
		$html .= '<th style="padding:6px 8px;text-align:left">Q#</th>';
		$html .= '<th style="padding:6px 8px;text-align:left">Title</th>';
		$html .= '<th style="padding:6px 8px;text-align:left">Current Tags</th>';
		$html .= '<th style="padding:6px 8px;text-align:left">Suggested (new)</th>';
		$html .= '<th style="padding:6px 8px;text-align:left">Source</th>';
		$html .= '<th style="padding:6px 8px;text-align:left">Action</th>';
		$html .= '</tr></thead><tbody>';

		$formAction = qa_html(qa_path('admin/book-suggested-tags',
			['branch' => $branchCode, 'page' => $page, 'per_page' => $perPage]));

		foreach ($rows as $row) {
			$currentTagList = array_filter(array_map('trim', explode(',', (string) $row['tags'])));
			$rawSuggested   = array_filter(array_map('trim', explode(',', (string) $row['suggested_tags'])));
			$newTags        = array_values(array_diff($rawSuggested, $currentTagList));
			if (empty($newTags)) continue;

			$qUrl      = $siteUrl . (int) $row['postid'];
			$sourceHtml = $row['source']
				? '<span style="font-size:11px;color:#888">' . qa_html($row['source']) . '</span>'
				: '—';

			$actionHtml = '<form method="post" action="' . $formAction . '" style="margin:0;display:flex;gap:4px">'
				. '<input type="hidden" name="code" value="' . qa_html($securityCode) . '">'
				. '<input type="hidden" name="suggestion_id" value="' . (int) $row['id'] . '">'
				. '<input type="hidden" name="postid" value="' . (int) $row['postid'] . '">'
				. '<input type="hidden" name="branch_code" value="' . qa_html($branchCode) . '">'
				. '<input type="hidden" name="suggested_tags" value="' . qa_html(implode(',', $newTags)) . '">'
				. '<button type="submit" name="approve_branch_tag" class="btn-approve">Approve</button>'
				. '<button type="submit" name="reject_branch_tag" class="btn-reject">Reject</button>'
				. '</form>';

			$html .= '<tr style="border-bottom:1px solid #eee">';
			$html .= '<td style="padding:5px 8px"><a href="' . qa_html($qUrl) . '" target="_blank" style="font-family:monospace">#' . (int) $row['postid'] . '</a></td>';
			$html .= '<td style="padding:5px 8px">' . qa_html(mb_substr((string) $row['title'], 0, 60)) . '</td>';
			$html .= '<td style="padding:5px 8px;font-size:11px">' . qa_html($row['tags']) . '</td>';
			$html .= '<td style="padding:5px 8px"><b style="color:#1b5e20">' . qa_html(implode(', ', $newTags)) . '</b></td>';
			$html .= '<td style="padding:5px 8px">' . $sourceHtml . '</td>';
			$html .= '<td style="padding:5px 8px;white-space:nowrap">' . $actionHtml . '</td>';
			$html .= '</tr>';
		}
		$html .= '</tbody></table>';

		// Pagination
		$baseParams = ['branch' => $branchCode, 'per_page' => $perPage];
		$html .= '<div style="margin-top:10px;display:flex;gap:8px;align-items:center">';
		if ($page > 1) {
			$html .= '<a href="' . qa_path_html('admin/book-suggested-tags', $baseParams + ['page' => $page - 1]) . '" style="padding:5px 12px;border:1px solid #ccc;text-decoration:none">← Prev</a>';
		}
		$html .= '<span style="color:#555">Page ' . $page . ' / ' . $totalPages . '</span>';
		if ($page < $totalPages) {
			$html .= '<a href="' . qa_path_html('admin/book-suggested-tags', $baseParams + ['page' => $page + 1]) . '" style="padding:5px 12px;border:1px solid #ccc;text-decoration:none">Next →</a>';
		}
		$html .= '</div>';

		return $html;
	}

	/**
	 * Render the EM suggestion generation / tag rename forms.
	 */
	private function renderGenerateForm($securityCode, $branches, $selectedBranch)
	{
		$emCats    = $this->getEmCategoryTags();
		$emTagList = implode(', ', array_column($emCats, 'tags'));

		$branchOpts = '';
		foreach ($branches as $bCode => $bInfo) {
			$sel = ($bCode === $selectedBranch) ? ' selected' : '';
			$branchOpts .= '<option value="' . qa_html($bCode) . '"' . $sel . '>' . qa_html($bInfo['name']) . '</option>';
		}

		$formAction = qa_html(qa_path('admin/book-suggested-tags',
			$selectedBranch ? ['branch' => $selectedBranch] : []));

		$html  = '<div style="margin-top:20px;background:#f8f8f8;border:1px solid #ddd;border-radius:6px;padding:16px">';
		$html .= '<h4 style="margin:0 0 6px">Generate EM Tag Suggestions (Co-occurrence)</h4>';
		$html .= '<p style="font-size:12px;color:#666;margin:0 0 10px">Enriches branch-site EM questions that already have a subtopic tag, by suggesting additional co-occurring tags from the main site (qa_). EM topics: <code style="font-size:11px">' . qa_html($emTagList) . '</code></p>';
		$html .= '<form method="post" action="' . $formAction . '" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">';
		$html .= '<input type="hidden" name="code" value="' . qa_html($securityCode) . '">';
		$html .= '<select name="gen_branch" style="padding:5px 8px">' . $branchOpts . '</select>';
		$html .= '<label style="font-size:13px"><input type="checkbox" name="gen_dryrun" value="1"> Dry run</label>';
		$html .= '<button type="submit" name="generate_em_suggestions" value="1" style="padding:6px 16px;background:#0969da;color:#fff;border:none;border-radius:4px;cursor:pointer">Generate Suggestions</button>';
		$html .= '</form>';

		$html .= '<hr style="margin:14px 0;border:none;border-top:1px solid #ddd">';
		$html .= '<h4 style="margin:0 0 6px">Generate Tag Rename Suggestions (Cross-site)</h4>';
		$html .= '<p style="font-size:12px;color:#666;margin:0 0 10px">Finds tags on a sister site that look like variant spellings of main-site canonical tags (e.g., <code>differential-equation</code> → <code>differential-equations</code>). Results appear in the Tag Merge Suggestions table above.</p>';
		$html .= '<form method="post" action="' . $formAction . '" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">';
		$html .= '<input type="hidden" name="code" value="' . qa_html($securityCode) . '">';
		$html .= '<select name="merge_branch" style="padding:5px 8px">' . $branchOpts . '</select>';
		$html .= '<button type="submit" name="generate_em_merges" value="1" style="padding:6px 16px;background:#8250df;color:#fff;border:none;border-radius:4px;cursor:pointer">Generate Rename Suggestions</button>';
		$html .= '</form>';
		$html .= '</div>';

		return $html;
	}
}