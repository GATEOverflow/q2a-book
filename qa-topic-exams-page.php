<?php

if (!defined('QA_VERSION')) {
	header('Location: ../../');
	exit;
}

class qa_topic_exams_page
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
		return [
			[
				'title' => 'GATE CSE Topic-wise Tests',
				'request' => 'topic-exams',
				'nav' => null,
			],
		];
	}

	public function match_request($request)
	{
		return $request === 'topic-exams';
	}

	public function process_request($request)
	{
		$qa_content = qa_content_prepare(true);
		$qa_content['title'] = 'GATE CSE PYQs — Topic-wise Practice Tests';

		$siteUrl = rtrim(qa_opt('site_url'), '/');

		// Fetch all topic exams grouped by category
		$exams = qa_db_read_all_assoc(qa_db_query_sub(
			"SELECT e.postid, e.title, e.total_qs, e.time_alotted, e.total_marks,
			        e.taken, c.title AS cat_title, c.categoryid,
			        pc.title AS parent_cat_title, c.parentid
			 FROM ^exams e
			 LEFT JOIN ^categories c ON e.categoryid = c.categoryid
			 LEFT JOIN ^categories pc ON c.parentid = pc.categoryid
			 WHERE e.title LIKE 'GATE CSE PYQs |%' AND e.type = 'E'
			 ORDER BY COALESCE(pc.title, c.title), c.title, e.title"
		));

		if (empty($exams)) {
			$qa_content['custom'] = '<p>No topic exams found.</p>';
			return $qa_content;
		}

		// Parse topic from title: "GATE CSE PYQs | Subject | Topic | Test N"
		$subjects = [];
		$totalExams = 0;
		$totalQuestions = 0;
		foreach ($exams as $e) {
			$parts = array_map('trim', explode('|', $e['title']));
			if (count($parts) < 4) continue;

			$subjectName = $parts[1];
			$topicName = $parts[2];
			$testLabel = $parts[3];

			if (!isset($subjects[$subjectName])) {
				$subjects[$subjectName] = ['topics' => [], 'totalQs' => 0, 'totalExams' => 0];
			}
			if (!isset($subjects[$subjectName]['topics'][$topicName])) {
				$subjects[$subjectName]['topics'][$topicName] = ['tests' => [], 'totalQs' => 0];
			}

			$subjects[$subjectName]['topics'][$topicName]['tests'][] = [
				'postid' => $e['postid'],
				'label' => $testLabel,
				'total_qs' => (int)$e['total_qs'],
				'total_marks' => (int)$e['total_marks'],
				'time' => (int)$e['time_alotted'],
				'taken' => (int)$e['taken'],
			];

			$subjects[$subjectName]['topics'][$topicName]['totalQs'] += (int)$e['total_qs'];
			$subjects[$subjectName]['totalQs'] += (int)$e['total_qs'];
			$subjects[$subjectName]['totalExams']++;
			$totalExams++;
			$totalQuestions += (int)$e['total_qs'];
		}

		// Sort subjects alphabetically, topics alphabetically
		ksort($subjects);
		foreach ($subjects as &$subj) {
			ksort($subj['topics']);
		}
		unset($subj);

		// Build HTML
		$html = '';

		// Summary stats
		$numSubjects = count($subjects);
		$numTopics = 0;
		foreach ($subjects as $s) $numTopics += count($s['topics']);

		$html .= '<div class="te-summary">';
		$html .= '<span class="te-stat"><strong>' . $numSubjects . '</strong> Subjects</span>';
		$html .= '<span class="te-stat"><strong>' . $numTopics . '</strong> Topics</span>';
		$html .= '<span class="te-stat"><strong>' . $totalExams . '</strong> Tests</span>';
		$html .= '<span class="te-stat"><strong>' . number_format($totalQuestions) . '</strong> Questions</span>';
		$html .= '</div>';

		// Subject quick-nav
		$html .= '<div class="te-nav">';
		foreach ($subjects as $sName => $sData) {
			$anchor = 'subj-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($sName));
			$html .= '<a class="te-nav-link" href="#' . $anchor . '">' . htmlspecialchars($sName)
				. ' <span class="te-nav-count">(' . $sData['totalExams'] . ')</span></a> ';
		}
		$html .= '</div>';

		// Subject sections
		foreach ($subjects as $sName => $sData) {
			$anchor = 'subj-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($sName));
			$html .= '<div class="te-subject" id="' . $anchor . '">';
			$html .= '<h2 class="te-subject-title">' . htmlspecialchars($sName)
				. ' <span class="te-subject-meta">' . count($sData['topics']) . ' topics · '
				. number_format($sData['totalQs']) . ' questions</span></h2>';

			$html .= '<div class="te-topics">';
			foreach ($sData['topics'] as $tName => $tData) {
				$html .= '<div class="te-topic">';
				$html .= '<div class="te-topic-header">';
				$html .= '<span class="te-topic-name">' . htmlspecialchars($tName) . '</span>';
				$html .= '<span class="te-topic-meta">' . $tData['totalQs'] . ' Qs</span>';
				$html .= '</div>';
				$html .= '<div class="te-tests">';

				foreach ($tData['tests'] as $t) {
					$examUrl = $siteUrl . '/exam/' . $t['postid'];
					$html .= '<a class="te-test-link" href="' . $examUrl . '">';
					$html .= '<span class="te-test-name">' . htmlspecialchars($t['label']) . '</span>';
					$html .= '<span class="te-test-info">' . $t['total_qs'] . 'Q · '
						. $t['total_marks'] . 'M · ' . $t['time'] . 'min</span>';
					if ($t['taken'] > 0) {
						$html .= '<span class="te-test-taken">' . $t['taken'] . ' taken</span>';
					}
					$html .= '</a>';
				}

				$html .= '</div></div>'; // te-tests, te-topic
			}

			$html .= '</div></div>'; // te-topics, te-subject
		}

		// CSS
		$css = '
<style>
.te-summary{display:flex;gap:20px;flex-wrap:wrap;padding:16px 20px;background:#f0f4ff;border-radius:12px;margin-bottom:20px}
.te-stat{font-size:15px;color:#475569}
.te-stat strong{font-size:22px;color:#1e40af;display:block}
.te-nav{display:flex;flex-wrap:wrap;gap:8px;padding:12px 0;margin-bottom:20px;border-bottom:1px solid #e2e8f0}
.te-nav-link{display:inline-block;padding:6px 14px;background:#f1f5f9;border-radius:8px;color:#334155;text-decoration:none;font-size:14px;font-weight:500;transition:all .15s}
.te-nav-link:hover{background:#2563eb;color:#fff}
.te-nav-count{color:#94a3b8;font-weight:400}
.te-nav-link:hover .te-nav-count{color:#bfdbfe}
.te-subject{margin-bottom:32px}
.te-subject-title{font-size:22px;font-weight:700;color:#1e293b;padding-bottom:8px;border-bottom:3px solid #2563eb;margin-bottom:16px}
.te-subject-meta{font-size:14px;font-weight:400;color:#64748b;margin-left:8px}
.te-topics{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:14px}
.te-topic{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:14px 16px;transition:box-shadow .15s}
.te-topic:hover{box-shadow:0 2px 12px rgba(0,0,0,.06)}
.te-topic-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px}
.te-topic-name{font-size:16px;font-weight:600;color:#1e293b}
.te-topic-meta{font-size:13px;color:#64748b;background:#f1f5f9;padding:2px 8px;border-radius:6px}
.te-tests{display:flex;flex-wrap:wrap;gap:6px}
.te-test-link{display:inline-flex;align-items:center;gap:6px;padding:5px 12px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;text-decoration:none;font-size:13px;color:#334155;transition:all .15s}
.te-test-link:hover{background:#2563eb;color:#fff;border-color:#2563eb}
.te-test-link:hover .te-test-info,.te-test-link:hover .te-test-taken{color:#bfdbfe}
.te-test-name{font-weight:600}
.te-test-info{color:#64748b;font-size:12px}
.te-test-taken{color:#16a34a;font-size:11px;font-weight:500}
@media(max-width:640px){.te-topics{grid-template-columns:1fr}.te-summary{gap:12px}.te-stat strong{font-size:18px}}
</style>';

		$qa_content['custom'] = $css . $html;

		return $qa_content;
	}
}
