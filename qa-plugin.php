<?php
if (!defined('QA_VERSION')) { // don't allow this page to be requested directly from browser
    header('Location: ../../');
    exit;   
}               
//error_reporting(-1);
//ini_set('display_errors', 'On');
//set_error_handler("var_dump");
qa_register_plugin_module('module', 'qa-book-admin.php', 'qa_book_admin', 'Book Export');

qa_register_plugin_overrides('qa-book-overrides.php');

qa_register_plugin_module('widget', 'qa-book-widget.php', 'qa_book_widget', 'Book Widget');

qa_register_plugin_module('page', 'qa-topic-exams-page.php', 'qa_topic_exams_page', 'Topic Exams Index');

qa_register_plugin_phrases('qa-book-lang-*.php', 'book');

require 'util-book.php';
require_once QA_INCLUDE_DIR.'/app/format.php';

function qa_network_get($branch) {
    if(!$branch) {
        return qa_opt('site_url');
    }
    $url = "https://gateoverflow.in/";
    if($branch === "ce") {
        $url = str_replace("https://gate", "https://civil.gate", $url);
    }
    else if($branch != "cs") {
        $url = str_replace("https://gate", "https://".$branch.".gate", $url);
    }
    //echo $url;
    return $url;
}
function get_branch_table($branch, $table) {
    if((!$branch) || ($branch === "cs")) {
        //	echo "Default $branch<br>";
        return "^$table";
    }
    if($branch == "ce") {
        return "qacivil_$table";
    }
    else {
        return "qa{$branch}_$table";
    }
}
function code_gen($content, $type = 0, $branch = null)
{
    //int c = 0;
    include_once QA_INCLUDE_DIR."../qa-plugin/q2a-book/phpqrcode/qrlib.php";
    $matches = array(false);
    $matches1 = array(false);
    //$m =
    if(($type <= 0) || ($type == 3) ||($type == 4))
    {
        preg_match("'<a href=\"(.*?)\".*?</a>'si", $content, $matches);
        if($matches)
        {
            if($type === -1) return $matches[1];
            if($type == 4){
                //echo $matches[0]; 
                return str_replace($matches[0],"",$content);
                return "<div class=\"ref\">Web Page".$link."</div>".str_replace($matches[0],"",$content);

            }
            //	print_r($matches);
            //exit;
            $key=hash('sha1',$matches[1]);
            QRcode::png($matches[1], '/var/www/html/qa/qa-plugin/q2a-book/images/'.$key, 'L', 3, 3);
            $link = '<a href="'.$matches[1].'"><img alt="" src="'.qa_network_get($branch).'qa-plugin/q2a-book/images/'.$key.'"></a>';
            if($type == 3) return $link;
            return $content.$link;
        }
        return $content;
    }
    if($type == 1)
    {

        preg_match("'<iframe.*src=\"(.*)\".*</iframe>'si", $content, $matches);
        if($matches)
        {
            //echo "<br>Video: ";
            //				print_r($matches[1]);
            //exit;
            $key=hash('sha1',$matches[1]);
            QRcode::png($matches[1], '/var/www/html/qa/qa-plugin/q2a-book/images/'.$key, 'L', 3, 3);
            $link = '<a href="'.$matches[1].'"><img alt="" src="'.qa_network_get($branch).'qa-plugin/q2a-book/images/'.$key.'"></a>';
            $content = preg_replace("'<iframe.*src=\".*\".*</iframe>'si", "<b>Video: </b>".$link, $content);
            $content = $content."Video:".$link;
        }
        $matches = array(false);
        preg_match_all("'<a .*href=\"(.*)\".*</a>'Usi", $content, $matches, PREG_SET_ORDER);
        //preg_match_all("'<a .* href=\"(.*?)\".*</a>'si", $content, $matches);
        $httpreg = "_(^|[\s.:;?\-\]<\(])(https?://[-\w;/?:@&=+$\|\_.!~*\|'()\[\]%#,☺]+[\w/#](\(\))?)(?=$|[\s',\|\(\).:;?\-\[\]>\)])_i";
        $httpreg = "@^(https?)://[^\s/$.?#].[^\s]*$@iS";
        $httpreg = "/(((http|ftp|https):\/{2})+(([0-9a-z_-]+\.)+(aero|asia|biz|cat|com|coop|edu|gov|info|int|jobs|mil|mobi|museum|name|net|org|pro|tel|travel|ac|ad|ae|af|ag|ai|al|am|an|ao|aq|ar|as|at|au|aw|ax|az|ba|bb|bd|be|bf|bg|bh|bi|bj|bm|bn|bo|br|bs|bt|bv|bw|by|bz|ca|cc|cd|cf|cg|ch|ci|ck|cl|cm|cn|co|cr|cu|cv|cx|cy|cz|cz|de|dj|dk|dm|do|dz|ec|ee|eg|er|es|et|eu|fi|fj|fk|fm|fo|fr|ga|gb|gd|ge|gf|gg|gh|gi|gl|gm|gn|gp|gq|gr|gs|gt|gu|gw|gy|hk|hm|hn|hr|ht|hu|id|ie|il|im|in|io|iq|ir|is|it|je|jm|jo|jp|ke|kg|kh|ki|km|kn|kp|kr|kw|ky|kz|la|lb|lc|li|lk|lr|ls|lt|lu|lv|ly|ma|mc|md|me|mg|mh|mk|ml|mn|mn|mo|mp|mr|ms|mt|mu|mv|mw|mx|my|mz|na|nc|ne|nf|ng|ni|nl|no|np|nr|nu|nz|nom|pa|pe|pf|pg|ph|pk|pl|pm|pn|pr|ps|pt|pw|py|qa|re|ra|rs|ru|rw|sa|sb|sc|sd|se|sg|sh|si|sj|sj|sk|sl|sm|sn|so|sr|st|su|sv|sy|sz|tc|td|tf|tg|th|tj|tk|tl|tm|tn|to|tp|tr|tt|tv|tw|tz|ua|ug|uk|us|uy|uz|va|vc|ve|vg|vi|vn|vu|wf|ws|ye|yt|yu|za|zm|zw|arpa)(:[0-9]+)?((\/([~0-9a-zA-Z\#\+\%@\.\/_-]+))?(\?[0-9a-zA-Z\+\%@\/&\[\];=_-]+)?)?))\b/imuS";
        //		preg_match_all($httpreg, $content, $matches1, PREG_SET_ORDER);
        if($matches and count($matches)>0)
        {
            //exit;
            $link = '';$i=0;
            foreach($matches as $match)
            {
                //	print_r(($match));
                //			echo "<br>QR$i". htmlentities($match)."<br>";$i++;
                $key=hash('sha1',$match[1]);
                QRcode::png($match[1], '/var/www/html/qa/qa-plugin/q2a-book/images/'.$key, 'L', 3, 3);
                $link .= '<a href="'.$match[1].'"> <img alt="" src="'.qa_network_get($branch).'qa-plugin/q2a-book/images/'.$key.'"></a>';
            }
            //foreach($matches1 as $match)
            {
                //	$key=hash('sha1',$match[0]);
                //	QRcode::png($match[0], '/var/www/html/qa/qa-plugin/q2a-book/images/'.$key, 'L', 3, 3);
                //	$link .= '<img src = "'.qa_network_get($branch).'qa-plugin/q2a-book/images/'.$key.'">';
            }
            return $content."<div class=\"ref\"><p>References</p>".$link."</div>";
        }
        return $content;

    }
}
function strip_latex($string)
{
    //	if(strpos($string, '\\'))
    {
        //		$string = substr($string, 0, strpos($string, '\\'));
    }
    $tags = array("left", 'right','matrix','\\frac', "\\dfrac", "{", "}", "\\large", "\\begin", "array", "end", "-", "!", " and ", "\\text", "$");
    foreach($tags as $tag)
    {
        $string = str_replace($tag, "", $string);
    }
    return trim(strip_tags($string));
}


function qa_book_catselect(){
    if(qa_book_get('book_plugin_catex'))
    {
        $ex = qa_book_get('book_plugin_catex');
        $excat = "(select categoryid from ^categories where parentid in ($ex) union 
            select categoryid from ^categories where parentid in (select categoryid from ^categories where parentid in ($ex)) union
            select categoryid from ^categories where categoryid in ($ex))";
        return $excat;
    }
    else return '()';

}
function qa_book_getallcats(&$cats, $all=false, $em=false)	{
    if($em) {
        $table = "qa_em_categories";
    }
    else {
        $table = "^categories";
    }
    $cats = 	qa_db_read_all_assoc(
            qa_db_query_sub(
                'SELECT c.categoryid, concat(ifnull(concat(p.title,": "), ""), c.title) as title, c.tags as tags, p.categoryid as parentid FROM '.$table.' c left join ^categories p on (c.parentid = p.categoryid) '.// categoryid not in (select parentid from ^categories where parentid is not null) '.
                                                                                                                                                                                                                      //			'SELECT c.categoryid,  c.title as title, p.categoryid as parentid FROM ^categories c left join ^categories p on (c.parentid = p.categoryid) '.// categoryid not in (select parentid from ^categories where parentid is not null) '.
                (!$all?
                 (qa_book_get('book_plugin_catex')?' where c.categoryid NOT IN '.qa_book_catselect():'')
                 :'')
                .' order by title'
                )
            );
    $navcats = array();
    foreach($cats as $cat)
        $navcats[$cat['categoryid']] = $cat;
    return $navcats;

}

function qa_book_get($key,$prefix = null,$book="book_") {
    static $bookcache = array();
    if($prefix) $pre = "^"; else $pre = "qa_";
    if($book === "book_") {
        if(isset($bookcache[$key])) {
            return $bookcache[$key];
        }
    }
    $query = "select content from ".$pre.$book."options where title = $";
    $result = qa_db_query_sub($query, $key);
    $value = qa_db_read_one_value($result, true);
    if($book === "book_") {
        $bookcache[$key] = $value;
    }
    return $value;
}

function qa_book_set($key, $value, $prefix = null) {
    if($prefix) $pre = "^"; else $pre = "qa_";
    if(isset($bookcache[$key])) unset($bookcache[$key]);
    $table = $pre."book_options";
    $query = "insert into $table (title, content) values ($,$) on duplicate key update content = $";
    //	error_log($query." ".$value." ".$key);
    $result = qa_db_query_sub($query, $key, $value, $value);
}


/**
 * Create topic-wise exams from the book's category/topic structure.
 *
 * URL params (all optional):
 *   make_topic_exam=1          — trigger creation
 *   volume=1|2|3               — limit to book volume categories
 *   catid=2,12,15              — limit to specific category IDs (comma-separated)
 *   max=20                     — max questions per test (default 15)
 *   dryrun=1                   — preview only, don't create
 *   delete=1                   — delete all existing GATE CSE PYQs topic exams
 *   merge-single=1             — merge small topics into "Mixed Topics" tests
 *   min-topic=N                — topics with fewer than N questions get merged (default 2)
 *
 * Volume mapping:
 *   1 = Engineering Mathematics + General Aptitude (13,26,27,28,29,30,31,32,33,35,112,113)
 *   2 = CS Technical subjects (2,12,14,18,36,37,15,16,17,19,22)
 *   3 = CO, OS, DB, Networks, Digital Logic (15,16,17,19,22)
 */
function qa_book_make_topic_exams($filterCatIds = null, $maxPerTest = 15, $dryrun = false, $minTopicQs = 0) {
	$excludeTags = ['descriptive', 'proof', 'true-false', 'match-the-following'];
	$userid = 1;
	$created = 0;
	$log = [];
	$mergeBuckets = []; // subject => ['oneMark' => [], 'twoMark' => [], 'categoryid' => N]

	// GATE CSE subject category IDs (parents)
	$gateSubjectIds = [2, 12, 13, 14, 15, 16, 17, 18, 19, 22, 25];

	// Get all categories and their parent info
	$allCats = qa_db_read_all_assoc(qa_db_query_sub(
		"SELECT c.categoryid, c.title, c.parentid, p.title AS parent_title
		 FROM ^categories c LEFT JOIN ^categories p ON c.parentid = p.categoryid
		 ORDER BY c.title"
	));

	// Filter to GATE CSE subjects and their children
	$cats = [];
	foreach ($allCats as $c) {
		$cid = (int)$c['categoryid'];
		$pid = $c['parentid'] ? (int)$c['parentid'] : null;
		if (in_array($cid, $gateSubjectIds) || ($pid && in_array($pid, $gateSubjectIds))) {
			// If filterCatIds provided, only include matching categories
			if ($filterCatIds !== null && !in_array($cid, $filterCatIds)) continue;
			$c['subject'] = ($pid && in_array($pid, $gateSubjectIds)) ? $c['parent_title'] : $c['title'];
			$cats[] = $c;
		}
	}

	foreach ($cats as $cat) {
		$subject = $cat['subject'];
		$categoryid = $cat['categoryid'];

		// Get all questions in this category
		$qs = qa_db_read_all_assoc(qa_db_query_sub(
			"SELECT qs.postid, BINARY qs.title AS title, qs.tags, qs.netvotes
			 FROM ^posts qs
			 WHERE qs.type='Q' AND qs.closedbyid IS NULL
			   AND qs.categoryid=#
			   AND qs.tags NOT LIKE '%usermod%'
			   AND qs.tags NOT LIKE '%usergate%'
			   AND qs.tags NOT LIKE '%memorybased%'
			   AND qs.tags LIKE '%gate%'
			 ORDER BY qs.netvotes DESC, qs.created ASC",
			$categoryid
		));

		if (empty($qs)) continue;

		// Group by topic (mintag)
		$topicGroups = [];
		foreach ($qs as $q) {
			$topic = mintag($q);
			if (empty($topic)) $topic = '_unassigned';
			$topicGroups[$topic][] = $q;
		}

		foreach ($topicGroups as $topicName => $questions) {
			if ($topicName === '_unassigned') continue;

			// Filter out excluded types
			$filtered = [];
			foreach ($questions as $q) {
				$tags = explode(',', $q['tags']);
				$skip = false;
				foreach ($tags as $t) {
					if (in_array(trim($t), $excludeTags)) {
						$skip = true;
						break;
					}
				}
				if (!$skip) $filtered[] = $q;
			}

			if (empty($filtered)) continue;

			// If merge enabled and this topic is too small, collect into merge bucket
			if ($minTopicQs > 0 && count($filtered) < $minTopicQs) {
				if (!isset($mergeBuckets[$subject])) {
					$mergeBuckets[$subject] = ['oneMark' => [], 'twoMark' => [], 'categoryid' => $categoryid];
				}
				foreach ($filtered as $q) {
					$tags = explode(',', $q['tags']);
					$is2 = false;
					foreach ($tags as $t) {
						$t = trim($t);
						if (in_array($t, ['2-marks', '2marks', '2mark', 'two-marks', 'two-mark'])) { $is2 = true; break; }
					}
					if ($is2) $mergeBuckets[$subject]['twoMark'][] = $q['postid'];
					else $mergeBuckets[$subject]['oneMark'][] = $q['postid'];
				}
				continue;
			}

			// Split into 1-mark and 2-mark based on tags
			$oneMark = [];
			$twoMark = [];
			foreach ($filtered as $q) {
				$tags = explode(',', $q['tags']);
				$is2 = false;
				foreach ($tags as $t) {
					$t = trim($t);
					if (in_array($t, ['2-marks', '2marks', '2mark', 'two-marks', 'two-mark'])) {
						$is2 = true;
						break;
					}
				}
				if ($is2) {
					$twoMark[] = $q['postid'];
				} else {
					$oneMark[] = $q['postid'];
				}
			}

			// Chunk into tests of $maxPerTest
			$allIds = array_merge($oneMark, $twoMark);
			$totalQuestions = count($allIds);
			$chunks = array_chunk($allIds, $maxPerTest);
			$testNo = 0;

			foreach ($chunks as $chunk) {
				$testNo++;
				// Split this chunk back into 1-mark and 2-mark
				$chunk1 = array_intersect($chunk, $oneMark);
				$chunk2 = array_intersect($chunk, $twoMark);
				$num1 = count($chunk1);
				$num2 = count($chunk2);
				$totalMarks = $num1 + 2 * $num2;
				$timeAllotted = $num1 + 2 * $num2; // minutes
				$totalQs = count($chunk);

				$title = "GATE CSE PYQs | $subject | $topicName | Test $testNo";

				// Check if exam with this title already exists
				$existing = qa_db_read_one_value(qa_db_query_sub(
					"SELECT postid FROM ^exams WHERE title=$", $title
				), true);
				if ($existing) {
					$log[] = ['action' => 'skip', 'title' => $title, 'reason' => 'exists'];
					continue;
				}

				if (!$dryrun) {
					qa_db_query_sub(
						"INSERT INTO ^exams (title, created, time_alotted, total_qs, total_marks,
						 post_ids_apti_1_mark, post_ids_apti_2_mark,
						 post_ids_technical_1_mark, post_ids_technical_2_mark,
						 penalty_aptitude, penalty_technical, num_options, shuffle,
						 categoryid, userid, type)
						 VALUES ($, NOW(), #, #, #, '', '', $, $, 0, 0, 4, 1, #, #, 'E')",
						$title,
						$timeAllotted,
						$totalQs,
						$totalMarks,
						implode(',', array_values($chunk1)),
						implode(',', array_values($chunk2)),
						$categoryid,
						$userid
					);
				}
				$log[] = ['action' => $dryrun ? 'would_create' : 'created', 'title' => $title,
				          'qs' => $totalQs, 'marks' => $totalMarks, 'time' => $timeAllotted];
				$created++;
			}
		}
	}

	// Create "Mixed Topics" tests from merged small-topic buckets
	foreach ($mergeBuckets as $mSubject => $bucket) {
		$mOneMark = $bucket['oneMark'];
		$mTwoMark = $bucket['twoMark'];
		$mCatId = $bucket['categoryid'];
		$mAllIds = array_merge($mOneMark, $mTwoMark);
		if (empty($mAllIds)) continue;

		$mChunks = array_chunk($mAllIds, $maxPerTest);
		$mTestNo = 0;
		foreach ($mChunks as $mChunk) {
			$mTestNo++;
			$mChunk1 = array_values(array_intersect($mChunk, $mOneMark));
			$mChunk2 = array_values(array_intersect($mChunk, $mTwoMark));
			$mNum1 = count($mChunk1);
			$mNum2 = count($mChunk2);
			$mTotalMarks = $mNum1 + 2 * $mNum2;
			$mTime = $mNum1 + 2 * $mNum2;
			$mTotalQs = count($mChunk);

			$mTitle = "GATE CSE PYQs | $mSubject | Mixed Topics | Test $mTestNo";

			$existing = qa_db_read_one_value(qa_db_query_sub(
				"SELECT postid FROM ^exams WHERE title=$", $mTitle
			), true);
			if ($existing) {
				$log[] = ['action' => 'skip', 'title' => $mTitle, 'reason' => 'exists'];
				continue;
			}

			if (!$dryrun) {
				qa_db_query_sub(
					"INSERT INTO ^exams (title, created, time_alotted, total_qs, total_marks,
					 post_ids_apti_1_mark, post_ids_apti_2_mark,
					 post_ids_technical_1_mark, post_ids_technical_2_mark,
					 penalty_aptitude, penalty_technical, num_options, shuffle,
					 categoryid, userid, type)
					 VALUES ($, NOW(), #, #, #, '', '', $, $, 0, 0, 4, 1, #, #, 'E')",
					$mTitle, $mTime, $mTotalQs, $mTotalMarks,
					implode(',', $mChunk1), implode(',', $mChunk2),
					$mCatId, $userid
				);
			}
			$log[] = ['action' => $dryrun ? 'would_create' : 'created', 'title' => $mTitle,
			          'qs' => $mTotalQs, 'marks' => $mTotalMarks, 'time' => $mTime];
			$created++;
		}
	}

	return ['created' => $created, 'log' => $log];
}

function qa_book_plugin_createBook($return=false) {
    set_time_limit(600); // book generation can be slow

    // Handle make_topic_exam GET request
    if (qa_get('make_topic_exam')) {
        $isCron = (qa_get('cron') === 'true');
        if (!$isCron && (!qa_is_logged_in() || qa_get_logged_in_level() < QA_USER_LEVEL_ADMIN)) {
            echo '<h2>Admin access required.</h2>';
            return 'Access denied.';
        }

        $volumeMap = [
            1 => [13, 26, 27, 28, 29, 30, 31, 32, 33, 35, 112, 113],
            2 => [2, 12, 14, 18, 36, 37, 15, 16, 17, 19, 22],
            3 => [15, 16, 17, 19, 22],
        ];

        // Handle delete
        if (qa_get('delete')) {
            $deleted = qa_db_read_one_value(qa_db_query_sub(
                "SELECT COUNT(*) FROM ^exams WHERE title LIKE 'GATE CSE PYQs |%'"
            ), true);
            qa_db_query_sub("DELETE FROM ^exams WHERE title LIKE 'GATE CSE PYQs |%'");
            echo "<h2>Deleted $deleted topic exams.</h2>";
            echo '<p><a href="' . qa_path('book', ['make_topic_exam' => 1]) . '">Back to Topic Exam Creator</a></p>';
            return "Deleted $deleted topic exams.";
        }

        // Parse filters
        $filterCatIds = null;
        $volume = qa_get('volume');
        $catidParam = qa_get('catid');
        $maxPerTest = (int)(qa_get('max') ?: 15);
        if ($maxPerTest < 1) $maxPerTest = 15;
        $dryrun = (bool)qa_get('dryrun');
        $minTopicQs = qa_get('merge-single') ? max(2, (int)(qa_get('min-topic') ?: 2)) : 0;

        if ($volume && isset($volumeMap[(int)$volume])) {
            $filterCatIds = $volumeMap[(int)$volume];
        } elseif ($catidParam) {
            $filterCatIds = array_map('intval', explode(',', $catidParam));
        }

        $result = qa_book_make_topic_exams($filterCatIds, $maxPerTest, $dryrun, $minTopicQs);

        // Render output
        $action = $dryrun ? 'Would create' : 'Created';
        $html = "<h2>$action {$result['created']} topic exams</h2>";

        if ($dryrun) {
            $html .= '<p><em>Dry run — no exams were created.</em> ';
            $params = ['make_topic_exam' => 1];
            if ($volume) $params['volume'] = $volume;
            if ($catidParam) $params['catid'] = $catidParam;
            if ($maxPerTest != 15) $params['max'] = $maxPerTest;
            if ($minTopicQs > 0) { $params['merge-single'] = 1; $params['min-topic'] = $minTopicQs; }
            $html .= '<a href="' . qa_path('book', $params) . '"><strong>Create for real &rarr;</strong></a></p>';
        }

        // Summary table by subject
        $subjects = [];
        foreach ($result['log'] as $entry) {
            $parts = array_map('trim', explode('|', $entry['title']));
            $subj = $parts[1] ?? '?';
            if (!isset($subjects[$subj])) $subjects[$subj] = ['created' => 0, 'skipped' => 0, 'qs' => 0];
            if ($entry['action'] === 'skip') {
                $subjects[$subj]['skipped']++;
            } else {
                $subjects[$subj]['created']++;
                $subjects[$subj]['qs'] += $entry['qs'] ?? 0;
            }
        }
        ksort($subjects);

        if (!empty($subjects)) {
            $html .= '<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;margin:16px 0">';
            $html .= '<tr><th>Subject</th><th>New</th><th>Skipped</th><th>Questions</th></tr>';
            foreach ($subjects as $sName => $s) {
                $html .= '<tr><td>' . htmlspecialchars($sName) . '</td><td>' . $s['created']
                    . '</td><td>' . $s['skipped'] . '</td><td>' . $s['qs'] . '</td></tr>';
            }
            $html .= '</table>';
        }

        // Usage guide
        $baseUrl = qa_path('book', ['make_topic_exam' => 1]);
        $html .= '<h3>URL Controls</h3><table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;margin:8px 0;font-size:14px">';
        $html .= '<tr><th>Action</th><th>URL</th></tr>';
        $html .= '<tr><td>Dry run (all)</td><td><a href="' . $baseUrl . '&dryrun=1">' . $baseUrl . '&amp;dryrun=1</a></td></tr>';
        $html .= '<tr><td>Create all</td><td><a href="' . $baseUrl . '">' . $baseUrl . '</a></td></tr>';
        $html .= '<tr><td>Volume 1 (Math + Aptitude)</td><td><a href="' . $baseUrl . '&volume=1&dryrun=1">&amp;volume=1</a></td></tr>';
        $html .= '<tr><td>Volume 2 (CS Technical)</td><td><a href="' . $baseUrl . '&volume=2&dryrun=1">&amp;volume=2</a></td></tr>';
        $html .= '<tr><td>Volume 3 (CO,OS,DB,Net,DL)</td><td><a href="' . $baseUrl . '&volume=3&dryrun=1">&amp;volume=3</a></td></tr>';
        $html .= '<tr><td>Specific categories</td><td>' . $baseUrl . '&amp;catid=2,12,15&amp;dryrun=1</td></tr>';
        $html .= '<tr><td>Custom max per test</td><td>' . $baseUrl . '&amp;max=20</td></tr>';
        $html .= '<tr><td>Merge small topics</td><td><a href="' . $baseUrl . '&merge-single=1&dryrun=1">&amp;merge-single=1</a></td></tr>';
        $html .= '<tr><td>Merge topics &lt; N questions</td><td>' . $baseUrl . '&amp;merge-single=1&amp;min-topic=3</td></tr>';
        $html .= '<tr><td>Delete all topic exams</td><td><a href="' . $baseUrl . '&delete=1" onclick="return confirm(\'Delete all GATE CSE PYQs topic exams?\')">&amp;delete=1</a></td></tr>';
        $html .= '<tr><td>View topic exams index</td><td><a href="' . qa_path('topic-exams') . '">/topic-exams</a></td></tr>';
        $html .= '</table>';

        echo $html;
        return "$action {$result['created']} topic exams.";
    }

    $globalquestioncount = 0;
    $globalanswercount = 0;
    $book = qa_book_get('book_plugin_template');
    include_once QA_INCLUDE_DIR."../qa-plugin/q2a-book/phpqrcode/qrlib.php";

    // static replacements

    $extras='';
    if(qa_book_get('qa-mathjax-enable', null, null ) == 1)
    {
        //$mathjax_header =  qa_book_get('qa-mathjax-config', null, null);
        $mathjax_header =  '<script  type="text/x-mathjax-config">  
            MathJax.Hub.Config({
tex2jax: {
inlineMath: [ [\'$\',\'$\'], ["\\\\(","\\\\)"] ],
config: ["MMLorHTML.js"],
jax: ["input/TeX"],
processEscapes: true
},
});

</script><script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/mathjax/2.7.9/MathJax.js?config=TeX-AMS-MML_HTMLorMML"></script>';
$extras.=   $mathjax_header;
}
if(qa_book_get("qa-prettify-enable", null, null) == 1)
{
    $extras.='<script  async type="text/javascript" src="'.qa_book_get('qa-pretiffy-url', null, null).'"></script>';
}


$bw = qa_get('bw');
$hidden = qa_get('hidden');
if(qa_get('md'))
{
    $markdistribution = true;

}
else $markdistribution = false;
if($bw)

    $book = str_replace('[css]',qa_book_get('book_plugin_black_css'),$book);
    else	
    $book = str_replace('[css]',qa_book_get('book_plugin_css'),$book);
    $book = str_replace('[script]',$extras,$book);
    $book = str_replace('[front]',qa_book_get('book_plugin_template_front'),$book);
    $book = str_replace('[back]',qa_book_get('book_plugin_template_back'),$book);			

    $shuffle = qa_book_get('book_plugin_shuffle');
    $iscats = qa_book_get('book_plugin_cats');
    $volume = qa_get('volume');
    $hideanswers = qa_get('hideanswers');
    $only_unanswered = qa_get('only_unanswered');
    $only_answered = qa_get('only_answered');
    $showanswerkeylink = true;
    $catinc = "";
    $cats = array(false);
    $qtags=qa_get("tag");
    $gate_em = qa_get('gate_em');
    $gate_da = qa_get('gate_da');
    if($iscats) {
        if($volume)
            $navcats =  qa_book_getallcats($cats, $qtags, $gate_em);
        else $navcats =  qa_book_getallcats($cats, $qtags, $gate_em);
    }
if($volume)
{
    //$iscats = false;
    switch($volume)
    {
        case 1:
            $catinc = " and qs.categoryid in (13,26,27,28,29,30,31,32,33,35,112,113)";break;
            //$catinc = " and qs.categoryid in (118)";break;
            //$catinc = " and qs.categoryid in (30)";break;
            qa_book_set('extra_filter_tags', '');
        case 2:
            $catinc = " and qs.categoryid in (2,12,14,18,36,37,118)";break;
            //$catinc = " and qs.categoryid in (2,12,14,18,36,37,15,16,17,19,22,118)";
            qa_book_set('extra_filter_tags', 'direct-mapping,little-endian-big-endian,demand-paging,page-fault,ll-parser,round-robin-scheduling,translation-lookaside-buffer,abstract-syntax-tree,dual-function,conjunctive-normal-form,timestamp-ordering,abstract-data-type,ambiguous-grammar,least-recently-used,viable-prefix,heap-sort,inversion,maximum-minimum,conflict-misses,hamming-code,icmp,pure-aloha,paging,safe-query,data-independence,serial-communication,stall,selection-sort,uniform-hashing,control-unit,dram,bubble-sort,breadth-first-search,directed-acyclic-graph,two-phase-locking-protocol,binary-codes');
            break;
            //$catinc = " and qs.categoryid in (2,14)";break;
        case 3:
            qa_book_set('extra_filter_tags', 'direct-mapping,little-endian-big-endian,demand-paging,page-fault,ll-parser,round-robin-scheduling,translation-lookaside-buffer,abstract-syntax-tree,dual-function,conjunctive-normal-form,timestamp-ordering,abstract-data-type,ambiguous-grammar,least-recently-used,viable-prefix,heap-sort,inversion,maximum-minimum,conflict-misses,hamming-code,icmp,pure-aloha,paging,safe-query,data-independence,serial-communication,stall,selection-sort,uniform-hashing,control-unit,dram,bubble-sort,breadth-first-search,directed-acyclic-graph,two-phase-locking-protocol,binary-codes');
            $catinc = " and qs.categoryid in (15,16,17,19,22)";break;
        case 4:
            qa_book_set('extra_filter_tags', 'direct-mapping,little-endian-big-endian,demand-paging,page-fault,ll-parser,round-robin-scheduling,translation-lookaside-buffer,abstract-syntax-tree,dual-function,conjunctive-normal-form,timestamp-ordering,abstract-data-type,ambiguous-grammar,least-recently-used,viable-prefix,heap-sort,inversion,maximum-minimum,conflict-misses,hamming-code,icmp,pure-aloha,paging,safe-query,data-independence,serial-communication,stall,selection-sort,uniform-hashing,control-unit,dram,bubble-sort,breadth-first-search,directed-acyclic-graph,two-phase-locking-protocol,binary-codes');
            $catinc = " and qs.categoryid in (15,16,17,19,22)";break;
    }
}
if($gate_da) {
    $catinc = " and qs.categoryid in (2,12,13,17,28,29,33,35,36,37,99,108,109,116,117,119)";
}
//$catinc = "";
// categories


// intro

$intro = qa_book_get('book_plugin_intro');
$ack = qa_book_get('book_plugin_ack');

$intro = str_replace('[sort_questions]',qa_lang('book/'.(qa_book_get('book_plugin_sort_q') == 0?'sort_upvotes':'sort_date')),$intro);
$intro = str_replace('[sort_categories]',$iscats?qa_lang('book/sort_categories'):'',$intro);
$intro = str_replace('[restrict_questions]',qa_book_get('book_plugin_req_qv')?qa_lang_sub('book/restrict_q_x_votes',qa_book_get('book_plugin_req_qv_no')):qa_lang('book/all_questions'),$intro);

$rq = array();

if(qa_book_get('book_plugin_req_sel'))
$rq[] = qa_lang('book/restrict_selected');
if(qa_book_get('book_plugin_req_abest'))
$rq[] = qa_lang('book/restrict_best_a');
if(qa_book_get('book_plugin_req_av_no'))
$rq[] = qa_lang_sub('book/restrict_a_x_votes',qa_book_get('book_plugin_req_av_no'));



if(empty($rq))
    $intro = str_replace('[restrict_answers]','',$intro);
    else {
        $rqs = qa_lang('book/restrict_answers_clause_'.count($rq));
        foreach($rq as $i => $v) 
            $rqs = str_replace('('.($i+1).')',$v,$rqs);
        $intro = str_replace('[restrict_answers]',$rqs,$intro);
    }



$tocout = '';
$qout = '';
$quserarray = array();
$auserarray = array();
$euserarray = array();
$ccount=0;
$catanchor = '';
$topic_array = array();// $qcontent; 
$book_plugin_req_abest = qa_book_get('book_plugin_req_abest');
$book_plugin_req_abest_max = qa_book_get('book_plugin_req_abest_max');
$book_plugin_req_av_no = qa_book_get('book_plugin_req_av_no');
$book_plugin_sort_q = qa_book_get('book_plugin_sort_q');
$book_plugin_req_sel = qa_book_get('book_plugin_req_sel');
$book_plugin_req_abest = qa_book_get('book_plugin_req_abest');
$book_plugin_req_qv = qa_book_get('book_plugin_req_qv');
$book_plugin_show_a = qa_book_get('book_plugin_show_a');
$book_plugin_req_ans = qa_book_get('book_plugin_req_ans');
$filter = array();
$filter_desc = array();

for($i=1; $i <= 10; $i++) {
    $filter[$i] = qa_book_get('book_plugin_custom_filter'.$i);
    $filter_desc[$i] = qa_book_get('book_plugin_custom_filter'.$i."_desc");
}

foreach($cats as $cat) {
    $qcount=0;
    $incsql = '';
    $anssql = '';// and ans.type = \'A\' ';
    $sortsql = '';

    $toc = '';
    $qhtml = '';

    if($book_plugin_sort_q == 0)
        $sortsql='ORDER BY qs.netvotes DESC, qs.created ASC';
    else
        $sortsql='ORDER BY qs.created ASC';

    if($book_plugin_req_sel)
        $incsql .= ' AND qs.selchildid=ans.postid';

    if($book_plugin_req_abest)
        //$sortsql.=',  ans.netvotes DESC'; // get all, limit later with break
        $sortsql.=', (ans.postid = qs.selchildid) desc, ans.netvotes DESC'; // get all, limit later with break

    if($book_plugin_req_qv)
        $incsql .= ' AND qs.netvotes >= '.(int)$book_plugin_req_qv_no;

    if($book_plugin_req_av_no)
        $anssql .= ' AND (ans.netvotes >= '.(int)$book_plugin_req_av_no.' OR  qs.selchildid=ans.postid)';
    if($only_unanswered)
        $incsql .= ' AND qs.acount = 0';
    elseif($only_answered)
        $incsql .= ' AND qs.acount > 0';

    $skipanswers = !(bool)$book_plugin_show_a;
    $reqanswers = $book_plugin_req_ans;
    $introsuffix = '';
    $booknamesuffix = "book";
    for($i=1; $i <= 10; $i++) {
        //if(qa_opt('book_plugin_enable_custom_filter'.$i) || (qa_get('filter'.$i))){
        //if(qa_book_get('filter'.$i)){
        if(qa_get('filter'.$i)){
            $booknamesuffix .= "_filter$i";
            $incsql .= " and (".$filter[$i].")";
            $introsuffix.='<p>'. $filter_desc[$i].'</p>';
        }
    }
    $privatefilterstring=" qs.tags not like '%memorybased%' and qs.tags not like '%goclasses%' ";
    $privatefilterstring_em=" qs.tags not like '%memorybased% and qs.tags not like '%goclasses%' or qs.title like '%Weekly Quiz%' ";
    if($qtags) {
        $booknamesuffix .="_$qtags";
        $incsql .= " and (qs.tags like '%$qtags%') ";
        $incsql .= " and (qs.postid in (select postid from ^posttags where wordid = (select wordid from ^words WHERE word = '$qtags' or word = '".qa_strtolower($qtags)."')))";
        $privatefilterstring_em = "true";
        $privatefilterstring = "true";


    }
    if($volume) $booknamesuffix .="_volume$volume";

    if(!$hideanswers) {
        $booknamesuffix .= "_with_answers";
    }

    if($skipanswers){
        $anssql .= " AND  (ans.postid < 0 OR  "; 
    }
    else  {
        $anssql .=" AND ( ";
    }

    $sortsql.=',  ans.netvotes DESC'; // get all, limit later with break
    $allowemptyq = " ans.postid is null or ";
    //$allowemptyq = "";
    $qtype="Q";
    $atype="A";
    if($hidden== 1)
    {
        $qtype="Q_HIDDEN";
        //$atype="A_HIDDEN";
    }
    if(!$gate_em) {
        if($gate_da) {
            $incsql .= " and ( (qs.title like 'GATE%' and qs.tags like '%gate%' and qs.title not like 'GATE Overflow%' and qs.title not like 'GATE Suitability%')  or (qs.title like 'UGC%' and qs.tags like '%ugc%') or (qs.title like 'GO Classes%' and qs.tags like '%goclasses%') )";
            $booknamesuffix .= "_gate_da";
        }
        $wrongsql = ' ('.$allowemptyq.' ans.postid not in (select postid from ^postmetas where title like "wrong" and content = 1))) ';

        $selectspec="SELECT null as branch, qs.postid AS postid, BINARY qs.title AS title, BINARY qs.content AS content, qs.format AS format, qs.netvotes AS netvotes, qs.tags as tags, qs.selchildid as selected, qs.userid as quserid, qs.lastuserid as qeditor, ans.lastuserid as aeditor, ans.postid as apostid, BINARY ans.content AS acontent, ans.format AS aformat, ans.userid AS auserid, ans.netvotes AS anetvotes, pm.content as useful FROM ^posts  qs ".($reqanswers?"":"left outer join")." ^posts  ans on qs.postid=ans.parentid and ans.type='$atype'  left outer join ^postmetas pm on ans.postid=pm.postid and pm.title like 'useful' where 
            qs.type='".$qtype."'  and qs.tags not like '%usermod%' and qs.tags not like '%usergate%' and $privatefilterstring  
            ".($iscats?" AND qs.closedbyid is null  and  qs.categoryid=".$cat['categoryid']:"") ." ". $incsql." ".$catinc." ".$anssql." ".$wrongsql." ".$sortsql;
    }
    else{
        $booknamesuffix .= "_gate_em";
        $wrongsql = " ($allowemptyq true  )) ";
        $selectspec="SELECT qs.branch as branch,qs.postid AS postid, BINARY qs.title AS title, BINARY qs.content AS content, qs.format AS format, qs.netvotes AS netvotes, qs.tags as tags, qs.selchildid as selected, qs.userid as quserid, qs.lastuserid as qeditor, ans.lastuserid as aeditor, ans.postid as apostid, BINARY ans.content AS acontent, ans.format AS aformat, ans.userid AS auserid, ans.netvotes AS anetvotes, ans.useful as useful FROM qa_engineering_mathematics  qs ".($reqanswers?"":"left outer join")." qa_engineering_mathematics  ans on qs.postid=ans.parentid and ans.type='$atype'  where  
            qs.type='".$qtype."'  and qs.tags not like '%usermod%' and qs.tags not like '%usergate%' and ($privatefilterstring_em)  and qs.tags like '%gate%' and qs.title not like 'GATE Overflow%' and qs.title like 'GATE %'
            ".($iscats?" AND qs.closedbyid is null  and  qs.categoryid=".$cat['categoryid']:"") ." ". $incsql." ".$catinc." ".$anssql." ".$wrongsql." ".$sortsql;
    }

    //	echo str_replace("^", "qa_", $selectspec);
    //	echo "<br>";
    //		continue;
    //	exit;
    $booknamesuffix .= ".html";
    $qs = qa_db_read_all_assoc(
            qa_db_query_sub(
                $selectspec
                )
            );	
    if(empty($qs)) // no questions in this category
        continue;
    //print_r($qs);
    //	continue;
    //exit;
    $answerkeys = array();
    $cqcount = 0;
    $ccount++;
    $q2 = array();
    foreach($qs as $q) { // group by questions
        $q2['q'.$q['postid']][] = $q;
    }

    //usort($q2,array($this,  "mysort"));
    if(!$shuffle)
    {
        usort($q2, "mysorttitle");
        usort($q2, "mysort");
    }
    else
    {
        //shuffle($q2);
    }
    $catanchor = 'cat'.$cat['categoryid'];
    $topicanchor=null;
    $oldmint='';	
    $answers='';//for pushing answers
    $tcount = 0;
    $answerblockprefix = '<h2 class="answers-block">Answers: <a class="topic-link" href="#'.$cat['categoryid'].'_topic_[topic]">[topicname]</a></h2>';
    $topicblockprefix = '<div class="topic-block" id="'.$cat['categoryid'].'_topic_[tlink]"><h2 class="top-title"><a class="topic-link" href="'.qa_network_get($branch).'tag/[topicurl]"> [topic] </a> <span class="topic-title-count"> ([topic-count])</span> </h2> <a class="top-link" href=#[top-link]>top</a> </div>';
    $qtopiccount = 1;
    $branch = null;
    foreach($q2 as $qs) {
        usort($qs, "mysortanswers");
        // toc entry
        $cqcount++;
        $mint = mintag($qs[0]);
        if($mint !== $oldmint){
            if($oldmint == '' &&!$shuffle)
            {
                //	$toc.=str_replace('[qlink]','<div class="toc-col1"><a href="#cat'.$cat['categoryid'].'">'."No topic assigned".'</a></div><div class="toc-col2"> ([zzzqcount])</div>',qa_opt('book_plugin_template_toc'));
            }

            if($mint !== '' && !$shuffle)
            {
                if(!$skipanswers && qa_book_get('book_plugin_push_a') && ($answers != '')){
                    //if(qa_opt('book_plugin_push_a') && ($answers != '')){
                    //$answerblock = str_replace("[topic]",  gettag($oldmint), $answerblockprefix);
                    //$answerblock = str_replace("[topicname]",  $oldmint, $answerblock);
                    if(!$hideanswers)
                    {
                        $qhtml .=qa_book_answerblockprefix($oldmint,$answerblockprefix). $answers;
                    }
                    /*	if($showanswerkeylink){
                        $qhtml .=qa_book_answerblockprefix($oldmint,$answerblockprefix). $answers;
                        }*/
                    $answers='';
                }
                $tcount++;
                $topicanchor= $cat['categoryid'].'_topic_'.gettag($mint);
                $number="<span class=\"number\">".$ccount.".".$tcount."</span>";	
                //$topic = str_replace("[topic]", $number.' '.$mint.'('.$qtopiccount.')',  $topicblockprefix);	
                $qhtml = str_replace("[zzzqcount]", $qcount, $qhtml); 
                $topic = str_replace("[topic]", $number.' '.$mint,  $topicblockprefix);	
                $topic = str_replace("[topic-count]", "[zzzqcount]",  $topic);	
                //$topic = str_replace("[topicurl]", gettag($mint),  $topic);	
                $topic = str_replace("[topicurl]", urlencode($mint),  $topic);
                $topic = str_replace("[tlink]", gettag($mint),  $topic);	
                $topic = str_replace("[top-link]", $catanchor,  $topic);	
                $qhtml .= $topic;
                //$toc.=str_replace('[qlink]','<a href="#question'.$qs[0]['postid'].'">'.$mint.'</a>',qa_opt('book_plugin_template_toc'));
                //$toc.=str_replace('[qlink]','<div class="toc-col1"><a href="#topic'.gettag($mint).'">'.$mint.'</a></div><div class="toc-col2"> ('.$qtopiccount.')</div>',qa_opt('book_plugin_template_toc'));
                $toc = str_replace("[zzzqcount]", $qcount, $toc);
                $toc.=str_replace('[qlink]','<div class="toc-col1"><a href="#'.$cat['categoryid'].'_topic_'.gettag($mint).'">'.$mint.'</a></div><div class="toc-col2"> ([zzzqcount])</div>',qa_book_get('book_plugin_template_toc'));
                $qcount = 0;
                }
                $oldmint = $mint;
            }

            else
            {
            }

            // answer html

            $as = '';
            $nv = false;
            $acount= 0;
            $baid = 0;
            foreach($qs as $idx => $q) {
                //echo $q['title']."<br>";
                if($acount == 0 )
                    $baid = $q['apostid'];
                if($book_plugin_req_abest && $book_plugin_req_abest_max && $idx >= $book_plugin_req_abest_max){
                    break;
                }
                //	if($nv !== false  && qa_opt('book_plugin_req_abest') && $q['anetvotes'] < 20) // if a best answer add one more with at least 20 votes
                //if($nv !== false  && qa_opt('book_plugin_req_abest') && $q['useful'] == NULL) // if a best answer add one more with at least 20 votes
                if($nv !== false  && ($q['apostid']> 0) && $book_plugin_req_abest && $q['useful'] == NULL) // if a best answer add one more with at least 20 votes
                    continue;//	break;
                             //if($nv !== false && qa_opt('book_plugin_req_abest') && $nv != $q['anetvotes']) // best answers only
                             //	break;
                /*arjun*/
                //if(($nv !== false) && ($q['useful'] == NULL))
                if(($nv !== false) && ($q['useful'] != NULL))
                {
                    //echo $q['useful']."<a href='https://gateoverflow.in/".$q['postid']."'>https://gateoverflow.in/".$q['postid']."</a><br>".$q['acontent']."<br>";
                    echo $q['useful'];//"<a href='https://gateoverflow.in/".$q['postid']."'>https://gateoverflow.in/".$q['postid']."</a><br>".$q['acontent']."<br>";

                }
                if($idx && ($q['apostid']>0) && ($q['anetvotes'] < $book_plugin_req_av_no))
                {
                    echo $q['postid'];
                    break;
                }
                $branch = $q['branch'];
                $acount++;
                $globalanswercount ++;
                $acontent = '';
                //if(!empty($q['acontent'])) {
                if($q['apostid'] > 0) {
                    $viewer=qa_load_viewer($q['acontent'], $q['aformat']);
                    $acontent = $viewer->get_html($q['acontent'], $q['aformat'], array());
                    $noanswer = false;
                }
                else{
                    echo "Empty answer<br>";
                    echo "<a href='".qa_network_get($branch).$q['postid']."'>".qa_network_get($branch).$q['postid']."</a><br>";
                    continue;
                }
                $acontent =code_gen($acontent, 1);
                $a = str_replace('[answer]',$acontent,qa_book_get('book_plugin_template_answer'));
                if($q['selected'] == $q['apostid'] && $q['apostid'] !== NULL){
                    //$baid = $q['apostid'];
                    $a = str_replace('[best]', 'bestanswer', $a);
                    $a = str_replace('[bestanswer]', 'bestanswercontent', $a);
                    $a = str_replace('[beforebest]', '<div class="tick"> <button disabled class="qa-a-select-button"><span class="fa fa-check"></span></button></div><div class="best-text">Selected Answer</div>', $a);
                    $a = str_replace('[afterbest]', '', $a);
                }
                else{
                    $a = str_replace('[best]', '', $a);
                    $a = str_replace('[bestanswer]', '', $a);
                    $a = str_replace('[beforebest]', '', $a);
                    $a = str_replace('[afterbest]', '', $a);
                }
                if($q['quserid'] !== NULL)	
                {
                    $quname = qa_get_user_name($q['quserid']);
                    $quserid = $q['quserid'];
                    if(!isset($quserarray[$quserid])) {
                        $quserarray[$quserid] = 0;
                    }
                    $quserarray[$quserid]++;
                }
                if($q['qeditor'] !== NULL)	
                {
                    $qeditor = qa_get_user_name($q['qeditor']);
                    $qeditor = $q['qeditor'];
                    if(!isset($euserarray[$qeditor])) {
                        $euserarray[$qeditor] = 0;
                    }
                    $euserarray[$qeditor]++;
                }
                if($q['aeditor'] !== NULL)	
                {
                    $aeditor = qa_get_user_name($q['aeditor']);
                    $aeditor = $q['aeditor'];
                    if(!isset($euserarray[$aeditor])) {
                        $euserarray[$aeditor] = 0;
                    }
                    $euserarray[$aeditor]++;
                }
                if($q['auserid'] !== NULL)	
                {
                    $auname = qa_get_user_name($q['auserid']);
                    $auserid = $q['auserid'];
                    if(!isset($auserarray[$auserid])) {
                        $auserarray[$auserid]['count'] = 0;
                    }
                    $auserarray[$auserid]['count']++;
                    $auserarray[$auserid]['userid'] = $auserid;
                    $auserarray[$auserid]['likes']+=$q['anetvotes'];
                    $a = str_replace('[answerer]',$auname,$a);
                }else {
                    $a = str_replace('[answerer]','',$a);
                }
                $nv = $q['anetvotes'];
                if($q['anetvotes'] !== NULL)	
                    $a = str_replace('[votes]',$nv,$a);
                else {
                    $a = str_replace('[votes]','',$a);
                }
                $res = qa_db_query_sub("select points from ^userpoints where userid=#", $q['auserid']);
                $points = qa_db_read_one_value($res, true);
                if($q['auserid'] !== NULL)	
                    $a = str_replace('[upoints]',qa_format_number($points, 0, true),$a);
                else {
                    $a = str_replace('[upoints]','',$a);
                }
                $as .= $a;

            }
            // question html
            $qcontent = '';
            if(!empty($q['content']) || !empty($q['title'])) {
                $globalquestioncount++;
                $qcount++;
                $viewer=qa_load_viewer($q['content'], $q['format']);
                $qcontent = $viewer->get_html($q['content'], $q['format'], array());
            }
            $tagshtml='';
            $tags = qa_tagstring_to_tags($q['tags']);
            $mint=mintag($q);
            if(!isset($topic_array[$mint]) || count($topic_array[$mint]) < 15)
                $topic_array[$mint][] = $qcontent; 
            $count  = 0; $c1 = 0; $c2 = 0;
            $text = $qcontent;//preg_replace('/[[:^print:]]/', '', $text);
            $pattern1 ='/(.*)(\(\s*A\s*\))(.+)\s+(\(\s*B\s*\))(.+)\s+(\(\s*C\s*\))(.+)\s+(\(\s*D\s*\))(.+)/is';
            //$pattern1 ='/(.+)(\(\s*A\s*\))(.+)(\s+)(\(B\))(.+)/i';
            $pattern2 ='/(.*)(\(a\))(.+)\s+(\(b\))(.+)\s+(\(c\))(.+)\s+(\(d\))(.+)\s+(\(e\))(.+)/i';
            $pattern3 ='/(.*)(\(a\))(.*)(\(b\))(.*)(\(c\))(.*)(\(d\))(.*)/i';
            $replacement = '$1<ol style="list-style-type:upper-alpha"><li> $3 </li><li> $5 </li> <li>$7 </li>  <li>$9  </li></ol>';
            $replacement2 = '$1<ol style="list-style-type:upper-alpha"><li> $3 </li><li> $5 </li> <li>$7 </li>  <li>$9  </li><li>$11</li></ol>';
            $text = preg_replace($pattern2, $replacement2, $text, -1, $c1);
            $text = preg_replace($pattern1, $replacement, $text, -1, $count);
            //$text = preg_replace($pattern3, $replacement, $text, -1, $c2);
            //if(!strcmp($qcontent, $text))
            if(($count || $c1))
            {
                if(qa_get('replace'))
                {
                    $query = "update ^posts set content = $, format='html' where postid = #";
                    //		qa_db_query_sub($query, $text, $q['postid']);
                    echo $q['postid'];
                    //			exit;
                }
                $titleurl = "<a href=\"".qa_network_get($branch).$q['postid']."\">".qa_network_get($branch).$q['postid']."</a>";	
                $titleright="<div class=\"title-right\">$titleurl</div>";
                //	echo $titleright;			
                //	print_r($qcontent);echo '<br><br>';
                //	print_r($text);
            }
            $text = $qcontent;//preg_replace('/[[:^print:]]/', '', $text);
                              //$match = '/(.*)(<ol style="list-style-type:upper-alpha">)(.*)(<li>)(.*)(</li>)(.*)(<li>)(.*)(</li>)(.*)(<li>)(.*)(</li>)(.*)(<li>)(.*)(</li>)(.*)(</ol>)(.*)/i';
            $match = '/(.*)(<ol)(.*)(<li>)(.*)(<\/li>)(.*)(<li>)(.*)(<\/li>)(.*)(<li>)(.*)(<\/li>)(.*)(<li>)(.*)(<\/li>)(.*)(<\/ol>)(.*)/is';
            //	$match = '/(.*)(<ol)(.*)/i';
            $arrays = array();
            $inline_options = false;
            $m =	preg_match($match, $text, $arrays);
            if($m == 1)
            {
                //	var_dump($arrays);
                $titleright="<div class=\"title-right\"><a href=\"".qa_network_get($branch).$q['postid']."\">".qa_network_get($branch).$q['postid']."</a></div>";
                //echo $titleright;//." ".$q['tags'];			
                //echo "<p>".$arrays[5]." <br>".$arrays[9]."<br> ".$arrays[13]."<br> ".$arrays[17]."</p>";
                $len1 = strlen(strip_latex($arrays[5]));
                $len2 = strlen(strip_latex($arrays[9]));
                $len3 = strlen(strip_latex($arrays[13]));
                $len4 = strlen(strip_latex($arrays[17]));
                if(strpos($arrays[5], "img ")  || strpos($arrays[9], "img ") || strpos($arrays[13], "img ") || strpos($arrays[17], "img "))  {
                    $image_in_options = true;
                }
                else{
                    $image_in_options = false;
                }
                //$len2 = strlen(strip_tags($arrays[9]));
                //$len3 = strlen(strip_tags($arrays[13]));
                //$len4 = strlen(strip_tags($arrays[17]));
                $len = $len1+$len2+$len3+$len4;
                $mlen = 20;
                $mlen2 = 50;
                if(!strpos($qcontent, "inline-options") and !$image_in_options)
                {
                    if(($len1 < $mlen) 
                            &&
                            ($len2 < $mlen)
                            &&
                            ($len3 < $mlen)
                            &&
                            ($len4 < $mlen)
                            &&($len < $mlen2)
                      )
                    {
                        //echo "Small";
                        $qcontent = str_replace('style="list-style-type:upper-alpha', ' class="shrink-inline-options" style="list-style-type:upper-alpha', $qcontent);
                        $qcontent = str_replace('style="list-style-type: upper-alpha', ' class="shrink-inline-options" style="list-style-type:upper-alpha', $qcontent);
                        $qcontent = str_replace('style="list-style-type:lower-alpha', ' class="shrink-inline-options" style="list-style-type:lower-alpha', $qcontent);
                        $qcontent = str_replace('style="list-style-type: lower-alpha', ' class="shrink-inline-options" style="list-style-type:lower-alpha', $qcontent);

                        //	$qcontent = str_replace('style="list-style-type: lower-alpha', ' class="shrink-inline-options" style="list-style-type:lower-alpha', $qcontent);
                        //echo $q['content'];
                        //	$inline_options = true;

                    }
                    else if(($len1 < 3*$mlen) 
                            &&
                            ($len2 < 3* $mlen)
                            &&
                            ($len3 < 3*$mlen)
                            &&
                            ($len4 < 3*$mlen)
                            &&($len < 3*$mlen2)
                            && !$image_in_options
                           )
                    {
                        $qcontent = str_replace('style="list-style-type:upper-alpha', ' class="shrink-inline-options2" style="list-style-type:upper-alpha', $qcontent);
                        $qcontent = str_replace('style="list-style-type: upper-alpha', ' class="shrink-inline-options2" style="list-style-type:upper-alpha', $qcontent);
                        $qcontent = str_replace('style="list-style-type:lower-alpha', ' class="shrink-inline-options2" style="list-style-type:lower-alpha', $qcontent);
                        $qcontent = str_replace('style="list-style-type: lower-alpha', ' class="shrink-inline-options2" style="list-style-type:lower-alpha', $qcontent);

                    }
                    else{
                        //				$qcontent = str_replace('style="list-style-type:upper-alpha', ' class="noshrink-inline-options" style="list-style-type:upper-alpha', $qcontent);
                        //				$qcontent = str_replace('style="list-style-type: upper-alpha', ' class="noshrink-inline-options" style="list-style-type:upper-alpha', $qcontent);
                        //				$qcontent = str_replace('style="list-style-type:lower-alpha', ' class="noshrink-inline-options" style="list-style-type:lower-alpha', $qcontent);
                        //				$qcontent = str_replace('style="list-style-type: lower-alpha', ' class="noshrink-inline-options" style="list-style-type:lower-alpha', $qcontent);

                    }
                }

                //echo"<br>";
                //exit;
            }
            else if(!strpos($q['tags'], "numerical-answer") && !strpos($q['tags'], "descriptive")
                    && !strpos($q['tags'], "combined-question")
                    && !strpos($q['tags'], "match-the-following")
                   )
            {
                //print_r($text);
                $titleright="<div class=\"title-right\"><a href=\"".qa_network_get($branch).$q['postid']."\">".qa_network_get($branch).$q['postid']."</a></div>";
                //echo $titleright." ".$q['tags'];			
                //echo"<br>";
                if(qa_get('replace'))
                {
                    $query = "update ^posts set tags = $ where postid = #";
                    qa_db_query_sub($query, $q['tags'].",descriptive", $q['postid']);
                    echo $q['postid'];
                    //	exit;
                }
                //exit;
            }
            else{

            }
            //$q['content'] = str_replace('style="list-style-type:upper-alpha', ' class="shrink-inline-options" style="list-style-type:upper-alpha', $q['content']);
            //	print_r($topic_array);
            //	echo $content;
            foreach ($tags as $tag)
            {

                $tagshtml.="<li class=\"qa-q-view-tag-item\"> <a href=\"".qa_network_get($branch)."tag/".urlencode($tag)."\"    class=\"qa-tag-link\">".htmlentities($tag)." </a></li>";
            }
            if($mint !== '')
                $mint.=": ";
            $nnumber=$ccount.".".$tcount.".".$qcount;	
            $number="<span class=\"number\">".$nnumber."</span>";	
            $titleurl = "<a href=\"".qa_network_get($branch).$q['postid']."\">".qa_network_get($branch).$q['postid']."</a>";	
            $titleqr = code_gen($titleurl, 3);
            $titleright="<div class=\"title-right\">$titleurl</div>";
            $oneq = str_replace('[question-title]',$number.$mint.$q['title'],qa_book_get('book_plugin_template_question'));
            $oneq = str_replace('[title-right]',$titleurl,$oneq);
            $oneq = str_replace('[question-qr]',$titleqr,$oneq);
            $oneq = str_replace('[qanchor]','question'.$q['postid'],$oneq);
            $oneq = str_replace('[qurl]', qa_html(qa_q_request($q['postid'],$q['title'])),$oneq);
            $oneq = str_replace('[site-url]', qa_network_get($branch),$oneq);
            $oneq = str_replace('[question]',$qcontent,$oneq);
            if($inline_options)
            {
                $oneq = str_replace('[options-inline]',"inline-options",$oneq);
            }
            else
            {
                $oneq = str_replace('[options-inline]',"",$oneq);
            }
            $oneq = str_replace('[top-link]',($topicanchor? $topicanchor:$catanchor),$oneq);
            $oneq = str_replace('[tags]', $tagshtml, $oneq);
            $oneq = str_replace('[hide]', '', $oneq);
            if(($qtags && (strpos($qtags, "goclasses") !== false)) || qa_get("watermark")) {
                $oneq = str_replace('[question-watermark]', 'question-content1', $oneq);
            }
            else {
                $oneq = str_replace('[question-watermark]', '', $oneq);
            }
            // output with answers  
            if($skipanswers)
                $qhtml .= str_replace('[answers]','',$oneq);
            else if(qa_book_get('book_plugin_push_a') ) {
                $answer_table = get_branch_table($branch, "ec_answers");
                //echo "Answer table $answer_table, branch = $branch <br>";
                $answerkeysql = "select answer_str from $answer_table where postid = #";
                $result = qa_db_query_sub($answerkeysql, $q['postid']);
                $row = qa_db_read_one_value($result, true);
                if($row != null)
                {
                    if(in_array("true-false", $tags))
                        $answerkey = strtoupper($row) == 1? "True" : "False";
                    else $answerkey = strtoupper($row);
                }
                else if(in_array("descriptive", $tags) || in_array("fill-in-the-blanks", $tags) || in_array("match-the-following", $tags) || in_array("proof", $tags))
                {
                    $answerkey = '<a href="'.qa_network_get($branch).$q['postid'].'" target="_blank">N/A</a>';
                }
                else//if(!$answerkey)
                {
                    $answerkey = '<a href="'.qa_network_get($branch).$q['postid'].'" target="_blank">TBA</a>';
                }
                //echo $answerkey;
                //if($acount <= 1)//arjun
                {
                    $answerkeys[$nnumber]['postid'] = $q['postid'];
                    $answerkeys[$nnumber]['apostid'] = $q['apostid'];
                    $answerkeys[$nnumber]['key']	= $answerkey;
                }
                if($as != '')
                {
                    //	$qhtml .= str_replace('[answers]','<a class="answer-link" href="#akt-'.$q['postid'].'">Answer key</a>',$oneq);
                    if(!$hideanswers)
                    {
                        $qhtml .= str_replace('[answers]','<a class="answer-link" href="#a-question'.$q['postid'].'">Answer</a>',$oneq);
                    }
                    else if($showanswerkeylink)
                    {
                        $qhtml .= str_replace('[answers]','<a class="answer-link" href="#akt-'.$q['postid'].'">Answer key</a>',$oneq);
                    }
                    else
                    {
                        $qhtml .= str_replace('[answers]','',$oneq);
                    }
                    //if($q['aselchildid'] == null) $aid = $q['apostid'];
                    //if($q['selected'] == $q['apostid'] && $q['apostid'] !== NULL){
                    //	$aid = $q['apostid'];
                    //}
                    //else if($q['selected'] !== NULL){
                    //	$aid = $q['selected'];
                    //}
                    $aid = $baid;
                    $titleurl = "<a href=\"".qa_network_get($branch).$q['postid']."#".$aid."\">".qa_network_get($branch).$q['postid']."</a>";	
                    $titlear = code_gen($titleurl, 3);
                    $onea = str_replace('[question-title]',$number.$mint.$q['title'],qa_opt('book_plugin_template_question'));
                    $onea = str_replace('[title-right]',$titleurl,$onea);
                    $onea = str_replace('[question-qr]',$titlear,$onea);
                    $onea = str_replace('[qanchor]','a-question'.$q['postid'],$onea);
                    $onea = str_replace('[qurl]','#question'.$q['postid'],$onea);
                    $onea = str_replace('[site-url]','',$onea);
                    $onea = str_replace('[question]','',$onea);
                    //$onea = str_replace('[top-link]','question'.$q['postid'],$onea);
                    $onea = str_replace('[top-link]','',$onea);
                    $onea = str_replace('[tags]', '', $onea);
                    $onea = str_replace('[hide]', 'hide', $onea);
                    $answers .= str_replace('[answers]',$as,$onea);
                }
                else
                    $qhtml .= str_replace('[answers]',$as,$oneq);

            }
            else
                $qhtml .= str_replace('[answers]',$as,$oneq);
            }
            if(!$skipanswers && qa_book_get('book_plugin_push_a')){
                if(!$hideanswers)
                {
                    $qhtml .= qa_book_answerblockprefix($oldmint,$answerblockprefix). $answers;
                }
            }
            if(!$shuffle)
                $qhtml = str_replace("[zzzqcount]", $qcount, $qhtml); 

            if($iscats) {
                if(!$shuffle)
                {
                    $toc = str_replace("[zzzqcount]", $qcount, $toc);
                }
                $tocout .= '<li><a href="#cat'.$cat['categoryid'].'" onclick="toggle(\'cat'.$cat['categoryid'].'Details\')" class="toc-cat">'.$cat['title'].'</a> <div class="cat-count">('.$cqcount.')'.'</div> <span id="cat'.$cat['categoryid'].'Details"> <ol class="toc-ul">'.$toc.'</ol></li>';
                $answerkeytable = buildanswerkeytable($answerkeys);
                print_r($answerkeytable);
                // todo fix category link
                $catnumber="<span class=\"number\">$ccount</span>";
                //	echo print_r($navcats)."<br>".$cat['categoryid']."<br>";
                $catout = str_replace('[cat-url]',qa_path_html('questions/'.qa_category_path_request($navcats, $cat['categoryid'])),qa_book_get('book_plugin_template_category'));
                //$catout = str_replace('[cat-syllabus]','cat'.$catsyllabus,$catout);
                $catdescription = qa_db_categorymeta_get($cat['categoryid'], 'description');

                $titleurl = "";//code_gen($catdescription, -1);
                $titleqr = code_gen($catdescription, 3);
                $catdescription =code_gen($catdescription, 4);
                $titleright="<div class=\"title-right\">$titleurl</div>";
                $catout = str_replace('[title-right]',$titleurl,$catout);
                $catout = str_replace('[category-qr]',$titleqr,$catout);
                if($markdistribution)
                    $catout = str_replace('[cat-mark-distribution]',$catdescription,$catout);
                else
                    $catout = str_replace('[cat-mark-distribution]',"",$catout);
                $catout = str_replace('[cat-anchor]','cat'.$cat['categoryid'],$catout);
                $catout = str_replace('[cat-title]',$catnumber.' '.$cat['title'],$catout);
                $catout = str_replace('[cat-count]',$cqcount,$catout);
                $catout = str_replace('[questions]',$qhtml.$answerkeytable,$catout);
                $qout .= $catout;
            }
            else {
                if(!$shuffle)
                    $tocout .= '<ol class="toc-ul">'.$toc.'</ol>';
                $catout = str_replace('[questions]',$qhtml,qa_book_get('book_plugin_template_questions'));
                $qout .= $catout;
            }
        }	
        if($iscats)
            $tocout = '<ol class="toc-ul">'.$tocout.'</ol>';


        //write code into file, Error corection lecer is lowest, L (one form: L,M,Q,H)
        //each code square will be 4x4 pixels (4x zoom)
        //code will have 2 code squares white boundary around 


        // add toc and questions


        $book = str_replace('[intro]',$intro.$introsuffix,$book);
        //$book = str_replace('[ack]',$ack.$link,$book);
        $book = str_replace('[ack]',$ack,$book);
        $book = str_replace('[toc]','',$book);
        $book = str_replace('[toc]',$tocout,$book);
        $book = str_replace('[categories]',$qout,$book);

        $likes = array();
        foreach($auserarray as $key => $value)
        {
            $likes[$key] = $value['likes']; 
        }
        array_multisort($likes, SORT_DESC, $auserarray);	
        arsort($quserarray);
        arsort($euserarray);
        // misc subs
        echo "Total Questions: ".$globalquestioncount."<br>";
        echo "Total Answers: ".$globalanswercount."<br>";

        $book = str_replace('[site-title]',qa_opt('site_title'),$book);
        $book = str_replace('[site-url]',qa_network_get($branch),$book);
        $book = str_replace('[date]',date('M j, Y'),$book);
        //file_put_contents("/tmp/out.txt",json_encode($topic_array),FILE_APPEND);
        //file_put_contents("/tmp/out.txt",json_encode($topic_array));
        //print_r($topic_array);
        //$book = code_gen($book);
        $book = str_replace('[answerer]', contriba($auserarray),$book);
        $book = str_replace('[questioner]',contrib($quserarray,0),$book);
        $book = str_replace('[editor]', contrib($euserarray,1),$book);
        if($return){
            //qa_opt('book_plugin_refresh_last',time());
            return $book;
        }
        $file_folder_location = qa_opt('book_plugin_loc')."/".strtolower(str_replace(" ","_",qa_opt('site_title')));
        if(!is_dir($file_folder_location)){
            mkdir($file_folder_location, 0777, true);
        }
        $file_location = $file_folder_location."/".$booknamesuffix;

        if(file_put_contents($file_location,$book)) 
        {
            qa_opt('book_plugin_refresh_last',time());

        }
        if(qa_get("create_pdf")) {
            $toemail = qa_get_logged_in_email() ? qa_get_logged_in_email(): qa_get('useremail');
            $tohandle = qa_get_logged_in_handle()? qa_get_logged_in_handle(): qa_get('userhandle');
            $pdfname = qa_book_get($booknamesuffix,true);

            if($pdfname && !qa_get('rebuild')) {
                $command = '/usr/bin/php '.dirname(__FILE__).'/sendemail.php '.qa_network_get($branch).'share/'.$pdfname.'.pdf "'.$toemail.'" "'.$tohandle.'" &';
                error_log($command);
                exec($command);

            }
            else {
                error_log("PDF starting");
                $pdfname = bin2hex(random_bytes(10));

                $command = 'nohup '.dirname(__FILE__).'/wkhtmltopdf --javascript-delay 12800 -T 20mm -B 20mm --header-spacing 6   --title "GATE Overflow Book" --no-stop-slow-scripts   --load-error-handling ignore  --enable-local-file-access  toc    '.dirname(__FILE__).'/../../'.$file_location.'  --zoom 0.6 --enable-toc-back-links   '.dirname(__FILE__).'/../../share/'.$pdfname.'.pdf  >/dev/null 2>&1 </dev/null ; /usr/bin/php '.dirname(__FILE__).'/sendemail.php "'.qa_network_get($branch).'share/'.$pdfname.'.pdf" "'.$toemail.'" "'.$tohandle.'" >/dev/null 2>&1 &';
                error_log($command);
                qa_book_set($booknamesuffix, $pdfname, true);
                exec($command);
            }

        }
        if(qa_opt('book_plugin_pdf'))
            qa_book_plugin_create_pdf();
        $mailhtml = qa_get("mailme");
        if($mailhtml)
        {

            $file = qa_opt('book_plugin_loc');
            $filename = "book.html";
            $mailto = 'arjunsuresh1987@gmail.com';
            $subject = 'New Book';
            $message = 'New book attached';

            $content = file_get_contents($file);
            $content = chunk_split(base64_encode($content));
            // a random hash will be necessary to send mixed content
            $separator = md5(time());

            // carriage return type (RFC)
            $eol = "\r\n";

            // main header (multipart mandatory)
            $headers = "From: GO <no-reply@gateoverflow.in>" . $eol;
            $headers .= "MIME-Version: 1.0" . $eol;
            $headers .= "Content-Type: multipart/mixed; boundary=\"" . $separator . "\"" . $eol;
            $headers .= "Content-Transfer-Encoding: 7bit" . $eol;
            $headers .= "This is a MIME encoded message." . $eol;

            // message
            $body = "--" . $separator . $eol;
            $body .= "Content-Type: text/plain; charset=\"iso-8859-1\"" . $eol;
            $body .= "Content-Transfer-Encoding: 8bit" . $eol;
            $body .= $message . $eol;

            // attachment
            $body .= "--" . $separator . $eol;
            $body .= "Content-Type: application/octet-stream; name=\"" . $filename . "\"" . $eol;
            $body .= "Content-Transfer-Encoding: base64" . $eol;
            $body .= "Content-Disposition: attachment" . $eol;
            $body .= $content . $eol;
            $body .= "--" . $separator . "--";
            $userid = qa_get_logged_in_userid();
            $email = qa_get_user_email($userid);

            if (mail($mailto, $subject, $body, $headers)) {
                echo "mail send to $mailto... OK<br>"; // or use booleans here
            } else {
                echo "mail send ... ERROR!";
                print_r( error_get_last() );
            }			
            if (mail($email, $subject, $body, $headers)) {
                echo "mail send to $email ... OK<br>"; // or use booleans here
            } else {
                echo "mail send ... ERROR!";
                print_r( error_get_last() );
            }			

        }
        error_log('Q2A Book Created on '.date('M j, Y \a\t H\:i\:s'). ' at '.$file_location.' for '.$selectspec);
        //echo"<br><br>";	 
        // print_r($likes);
        /*
           print_r($quserarray);
           echo "<hr><br>";
           print_r($auserarray);
           echo "<hr><br>";
           print_r($euserarray);*/
        require_once QA_INCLUDE_DIR.'qa-app-emails.php';
        $subject = "GO hardcopy publishing";
        $signature = "Arjun";
        $i = 0;

        return 0;//'Book Created';
        foreach($auserarray as $key=>$value)
        {
            $userid = $value['userid'];
            if($i == 0)
                $maxval = $value['likes'];
            if($value['likes'] < 0.2*$maxval)
                break;
            $uname = qa_get_user_name($userid);
            $email = qa_get_user_email($userid);
            $body = "This is to inform you that in the upcoming version of GO hardcopy Volume 1 (Mathematics and Aptitude) we are adding the name of the contributors as given in their GO profile in a separate Contributors page. Currently your name is given as $uname. If you would like to change this please do this by changing your Full Name at <a href='https://gateoverflow.in/account'>https://gateoverflow.in/account</a>. If by any reason you would like to have your name removed, please reply back to this email.";
            $content="Hi, <p>".$body."</p>Thanks and Regards,<br>".$signature;
            /*              qa_send_email(array(
                            'fromemail' => 'gatecse@gateoverflow.in',
                            'fromname' => 'GATE Overflow',
            //    'replytoemail' => 'noreply@gateoverflow.in',
            // 'replytoname' => 'noreply@gateoverflow.in',
            'toemail' => $email,
            //'toemail' => 'arjunsuresh1987@gmail.com',
            'toname' => $uname,
            'subject' => $subject,
            'body' => $content,
            'html' => true,
            ));*/
            $i = $i+1;
            $likes = $value['likes'];
            $count = $value['count'];
            echo "<br>".$i."&nbsp;$userid&nbsp;$likes&nbsp;$count;&nbsp;".$email;
        }	


        //return 'Error creating '.qa_opt('book_plugin_loc').'; check the error log.';
    }
    function buildanswerkeytable($answerkeys, $branch=null)
    {
        $html = '<h2 class="answer-keys"> Answer Keys</h2><table class="akt-table" style="width:100%"> <tr>';
        $ccount = 0;
        $cmax = 5;
        foreach($answerkeys as $post => $postmeta)
        {
            $postid = $postmeta['postid'];
            $apostid = $postmeta['apostid'];
            $key = $postmeta['key'];
            //$html .="<td class='akt-td'><table style='width:100%'><tr><td class='akt-id'><a href='#question".$postid."'>$post</a></td><td class='akt-key'><a href='".qa_network_get($branch).$postid."#".$apostid."'>$key</a></td></tr></table></td>";
            $html .="<td class='akt-id' id='akt-$postid'><a href='#question".$postid."'>$post</a></td><td class='akt-key'><a href='".qa_network_get($branch).$postid."#".$apostid."'>$key</a></td>";
            $ccount++;
            if($ccount == $cmax)
            {
                $html .='</tr><tr>';
                $ccount =0;
            }
            else
            {
                $html .="<td class='akt-gap'></td>";
            }


        }
        $html .= "</tr></table>";
        $html = str_replace("<tr></tr>", "", $html);
        return $html;
    }
    function contrib($array, $type = 0)
    {
        $string = "";$i = 0;
        if($type == 0) {$word = "questions"; $headright="<i class=\"fa fa-question\"></i>Added";  }
        if($type == 1) {$word = "edits"; $headright="<i class=\"fa fa-edit\"></i>Done";  }
        $string = "";
        $string .='<div class="row head"><div class="col1">User</div><div class="col2">'.$headright.'</div></div>';
        foreach($array as $key=>$value)
        {
            $userid = $key;
            $count = $value;
            if($i == 0)
                $maxval = $value;
            if($value < 0.01*$maxval)
                break;
            $i++;
            $uname = qa_get_user_name($userid);
            $string.= "<div class=\"row\"><div class=\"col1\"><span class =\"name\">$uname </span></div><div class=\"col2\"><span class=\"count\"> $count</span><span> </span></div></div>";
        }
        return $string;
    }
    function contriba($array)
    {
        $string = "";
        $i = 0;
        $string .='<div class="row head"><div class="col1">User</div><div class="col2"><i class="fa fa-thumbs-o-up"></i>, Answers</div></div>';
        foreach($array as $key=>$value)
        {
            $userid = $value['userid'];
            $count = $value['count'];
            $likes = $value['likes'];
            if($i == 0)
                $maxval = $value['likes'];
            if($value['likes'] < 0.02*$maxval)
                break;
            $i++;
            $uname = qa_get_user_name($userid);
            $string.= "<div class=\"row\"><div class=\"col1\"><span class =\"name\">$uname </span></div><div class=\"col2\"><span class=\"count likes\">$likes, </span><span class=\"count\"> $count</span></div></div>";
        }
        return $string;
    }
    function qa_book_answerblockprefix($oldmint, $answerblockprefix)
    {
        $answerblock = str_replace("[topic]",  gettag($oldmint), $answerblockprefix);
        return str_replace("[topicname]",  $oldmint, $answerblock);

    }
    function qa_book_plugin_create_pdf($return=false) {

        include 'wkhtmltopdf.php';

        //echo $html;

        $pdf = new WKPDF();

        $pdf->render_q2a();

        if($return)
            $pdf->output(WKPDF::$PDF_DOWNLOAD,'book.pdf'); 
        else
            $pdf->output(WKPDF::$PDF_SAVEFILE,qa_book_get('book_plugin_loc_pdf')); 

        error_log('Q2A PDF Book Created on '.date('M j, Y \a\t H\:i\:s'));
    }

    function qa_get_user_name($uid) {

        $handles = qa_userids_to_handles(array($uid));
        $handle = $handles[$uid];

        if(QA_FINAL_EXTERNAL_USERS) {
            $user_info = get_userdata($uid);
            if ($user_info->display_name)
                $name = $user_info->display_name;
        }
        else {
            $name = qa_db_read_one_value(
                    qa_db_query_sub(
                        'SELECT content AS name FROM ^userprofile '.
                        'WHERE userid=# AND title=$',
                        $uid, 'name'
                        ),
                    true
                    );
        }
        if(!@$name)
            $name = $handle;

        return strlen($handle) ? ('<A HREF="'.qa_path_absolute('user/'.$handle).
                '" CLASS="qa-user-link">'.qa_html($name).'</A>') : 'Anonymous';
    }


    /*                              
                                    Omit PHP closing tag to help avoid accidental output
     */                              


