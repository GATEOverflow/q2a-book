<?php

if (!defined('QA_VERSION')) {
	header('Location: ../../');
	exit;
}

class qa_book_requests_page
{
	public function load_module($directory, $urltoroot) {}

	public function suggest_requests()
	{
		return array(
			array(
				'title' => 'Book Requests',
				'request' => 'admin/book-requests',
				'nav' => null,
			),
		);
	}

	public function match_request($request)
	{
		return ($request === 'admin/book-requests') || strpos($request, 'admin/book-requests/') === 0;
	}

	public function process_request($request)
	{
		if (qa_get_logged_in_level() < QA_USER_LEVEL_ADMIN) {
			return include QA_INCLUDE_DIR . 'qa-page-not-found.php';
		}

		// Parse optional book slug from URL: admin/book-requests/{slug}
		$parts = explode('/', $request, 3);
		$filterSlug = isset($parts[2]) ? trim($parts[2]) : '';

		// Pagination
		$perPage = 50;
		$page    = max(1, (int)(qa_get('page') ?: 1));
		$offset  = ($page - 1) * $perPage;

		// ---- Summary (always shown) ----
		$summaryRows = qa_db_read_all_assoc(qa_db_query_sub(
			'SELECT bookslug, type, COUNT(*) AS cnt' .
			' FROM ^book_pdf_requests GROUP BY bookslug, type ORDER BY bookslug, type'
		));

		// Group summary by slug for the per-book table
		$byBook = array();
		$grandTotal = 0;
		foreach ($summaryRows as $r) {
			$byBook[$r['bookslug']][$r['type']] = (int)$r['cnt'];
			$grandTotal += (int)$r['cnt'];
		}

		// ---- Full list (filtered + paginated) ----
		if ($filterSlug !== '') {
			$totalFiltered = (int)qa_db_read_one_value(qa_db_query_sub(
				'SELECT COUNT(*) FROM ^book_pdf_requests WHERE bookslug=$',
				$filterSlug
			), true);
			$rows = qa_db_read_all_assoc(qa_db_query_sub(
				'SELECT bookslug, type, handle, email, DATE_FORMAT(created,\'%Y-%m-%d %H:%i\') AS created_fmt' .
				' FROM ^book_pdf_requests WHERE bookslug=$ ORDER BY created DESC LIMIT # OFFSET #',
				$filterSlug, $perPage, $offset
			));
		} else {
			$totalFiltered = $grandTotal;
			$rows = qa_db_read_all_assoc(qa_db_query_sub(
				'SELECT bookslug, type, handle, email, DATE_FORMAT(created,\'%Y-%m-%d %H:%i\') AS created_fmt' .
				' FROM ^book_pdf_requests ORDER BY created DESC LIMIT # OFFSET #',
				$perPage, $offset
			));
		}

		$totalPages = max(1, (int)ceil($totalFiltered / $perPage));
		$baseUrl    = qa_path_html($filterSlug !== '' ? 'admin/book-requests/' . urlencode($filterSlug) : 'admin/book-requests');

		// ---- Build HTML ----
		$css = '
<style>
.bvr-wrap { max-width:1000px; margin:20px auto; font-family:inherit }
.bvr-back  { font-size:13px; margin-bottom:16px; display:inline-block }
.bvr-title { font-size:1.4em; font-weight:bold; margin-bottom:16px }
.bvr-summary-table { border-collapse:collapse; width:100%; margin-bottom:24px; font-size:14px }
.bvr-summary-table th { padding:7px 12px; text-align:left; background:rgba(128,128,128,0.12); border-bottom:2px solid rgba(128,128,128,0.2) }
.bvr-summary-table td { padding:6px 12px; border-bottom:1px solid rgba(128,128,128,0.15) }
.bvr-summary-table tr:hover td { background:rgba(128,128,128,0.06) }
.bvr-detail-table { border-collapse:collapse; width:100%; font-size:13px }
.bvr-detail-table th { padding:6px 10px; text-align:left; background:rgba(128,128,128,0.12); border-bottom:2px solid rgba(128,128,128,0.2) }
.bvr-detail-table td { padding:5px 10px; border-bottom:1px solid rgba(128,128,128,0.12) }
.bvr-tag-pdf { color:#0969da; font-weight:500 }
.bvr-tag-hc  { color:#8250df; font-weight:500 }
.bvr-slug-link { font-family:monospace; font-size:13px; text-decoration:none; color:inherit; border-bottom:1px solid rgba(128,128,128,0.4) }
.bvr-slug-link:hover { border-color:currentColor }
.bvr-pager { margin-top:14px; font-size:13px }
.bvr-pager a { margin:0 4px; text-decoration:none; padding:3px 8px; border:1px solid rgba(128,128,128,0.35); border-radius:3px }
.bvr-pager a.current { font-weight:bold; background:rgba(128,128,128,0.15) }
.bvr-filter-note { font-size:13px; margin-bottom:12px; color:inherit; opacity:0.7 }
</style>';

		$html = $css . '<div class="bvr-wrap">';
		$html .= '<a class="bvr-back" href="' . qa_path_html('admin/plugins') . '">&larr; Back to Admin</a>';

		// Summary section
		$html .= '<div class="bvr-title">Book PDF &amp; Hardcopy Requests</div>';
		if (empty($byBook)) {
			$html .= '<p><em>No requests recorded yet.</em></p>';
		} else {
			$html .= '<p style="font-size:14px;margin-bottom:8px"><strong>Summary by book</strong> &mdash; ' . $grandTotal . ' total request' . ($grandTotal !== 1 ? 's' : '') . '</p>';
			$html .= '<table class="bvr-summary-table">';
			$html .= '<tr><th>Book</th><th>PDF Requests</th><th>Hardcopy Requests</th><th>Total</th></tr>';
			foreach ($byBook as $slug => $counts) {
				$pdf = isset($counts['pdf']) ? $counts['pdf'] : 0;
				$hc  = isset($counts['hardcopy']) ? $counts['hardcopy'] : 0;
				$tot = $pdf + $hc;
				$isActive = ($filterSlug === $slug);
				$slugLink = '<a class="bvr-slug-link" href="' . qa_path_html('admin/book-requests/' . urlencode($slug)) . '">' . qa_html($slug) . '</a>';
				$style = $isActive ? ' style="background:rgba(128,128,128,0.1);font-weight:500"' : '';
				$html .= '<tr' . $style . '>'
					. '<td>' . $slugLink . '</td>'
					. '<td>' . ($pdf ? '<span class="bvr-tag-pdf">' . $pdf . '</span>' : '—') . '</td>'
					. '<td>' . ($hc  ? '<span class="bvr-tag-hc">'  . $hc  . '</span>' : '—') . '</td>'
					. '<td><strong>' . $tot . '</strong></td>'
					. '</tr>';
			}
			$html .= '</table>';

			// Detail list
			$heading = $filterSlug !== ''
				? 'Requests for <code>' . qa_html($filterSlug) . '</code>'
				: 'All Requests';
			$html .= '<div style="font-size:1.05em;font-weight:bold;margin-bottom:8px">' . $heading . '</div>';

			if ($filterSlug !== '') {
				$html .= '<p class="bvr-filter-note"><a href="' . qa_path_html('admin/book-requests') . '">&times; Clear filter</a></p>';
			}

			if (empty($rows)) {
				$html .= '<p><em>No requests found.</em></p>';
			} else {
				$html .= '<table class="bvr-detail-table">';
				$html .= '<tr><th>Book</th><th>Type</th><th>User</th><th>Email</th><th>Date</th></tr>';
				foreach ($rows as $r) {
					$typeLabel = $r['type'] === 'hardcopy'
						? '<span class="bvr-tag-hc">Hardcopy</span>'
						: '<span class="bvr-tag-pdf">PDF</span>';
					$html .= '<tr>'
						. '<td><span style="font-family:monospace;font-size:12px">' . qa_html($r['bookslug']) . '</span></td>'
						. '<td>' . $typeLabel . '</td>'
						. '<td>' . qa_html($r['handle'] ?: '—') . '</td>'
						. '<td>' . qa_html($r['email']  ?: '—') . '</td>'
						. '<td style="white-space:nowrap">' . qa_html($r['created_fmt']) . '</td>'
						. '</tr>';
				}
				$html .= '</table>';

				// Pagination
				if ($totalPages > 1) {
					$html .= '<div class="bvr-pager">Page: ';
					for ($p = 1; $p <= $totalPages; $p++) {
						$cls = ($p === $page) ? ' class="current"' : '';
						$html .= '<a href="' . $baseUrl . '?page=' . $p . '"' . $cls . '>' . $p . '</a>';
					}
					$html .= '</div>';
				}
				$html .= '<p class="bvr-filter-note">Showing ' . count($rows) . ' of ' . $totalFiltered . ' requests.</p>';
			}
		}

		$html .= '</div>';

		$qa_content = qa_content_prepare();
		$qa_content['title']  = 'Book Requests';
		$qa_content['custom'] = $html;
		return $qa_content;
	}
}
