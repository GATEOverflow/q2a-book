<?php
/*
    Aptitude Migration Page
    Shows CS aptitude questions that are duplicated on branch sites.
    Allows migrating answers from CS to branch, then closing CS copy as duplicate.
    URL: aptitude-migrate
*/

if (!defined('QA_VERSION')) exit;

class qa_aptitude_migrate_page
{
    // Branch code => [db_prefix, site_url, display_name]
    private $branches = [
        'ee' => ['qaee_', 'https://ee.gateoverflow.in/', 'GO Electrical'],
        'ec' => ['qaec_', 'https://ec.gateoverflow.in/', 'GO Electronics'],
        'me' => ['qame_', 'https://me.gateoverflow.in/', 'GO Mechanical'],
        'ce' => ['qacivil_', 'https://civil.gateoverflow.in/', 'GO Civil'],
        'ch' => ['qach_', 'https://ch.gateoverflow.in/', 'GO Chemical'],
        'in' => ['qain_', 'https://in.gateoverflow.in/', 'GO Instrumentation'],
        'bt' => ['qabt_', 'https://bt.gateoverflow.in/', 'GO Biotech'],
    ];

    public function match_request($request)
    {
        return $request === 'aptitude-migrate';
    }

    public function process_request($request)
    {
        if (qa_get_logged_in_level() < QA_USER_LEVEL_ADMIN) {
            $qa_content = qa_content_prepare();
            $qa_content['error'] = 'Admin access required';
            return $qa_content;
        }

        require_once QA_INCLUDE_DIR . 'app/post-update.php';
        require_once QA_INCLUDE_DIR . 'app/posts.php';

        // Handle AJAX actions
        if (qa_post_text('ajax_action')) {
            header('Content-Type: application/json');
            $result = $this->handle_action();
            echo json_encode($result);
            exit;
        }

        return $this->render_page();
    }

    private function handle_action()
    {
        $action = qa_post_text('ajax_action');
        $csPostid = (int)qa_post_text('cs_postid');
        $branchPostid = (int)qa_post_text('branch_postid');
        $branch = qa_post_text('branch');

        if (!$csPostid || !$branchPostid || !isset($this->branches[$branch])) {
            return ['ok' => false, 'error' => 'Invalid parameters'];
        }

        $branchInfo = $this->branches[$branch];
        $prefix = $branchInfo[0];
        $siteUrl = $branchInfo[1];

        $userid = qa_get_logged_in_userid();
        $handle = qa_get_logged_in_handle();
        $cookieid = qa_cookie_get();

        if ($action === 'migrate_and_close') {
            return $this->do_migrate_and_close($csPostid, $branchPostid, $prefix, $siteUrl, $branch, $userid, $handle, $cookieid);
        } elseif ($action === 'migrate_close_merge') {
            return $this->do_migrate_close_merge($csPostid, $branchPostid, $prefix, $siteUrl, $branch, $userid, $handle, $cookieid);
        } elseif ($action === 'close_only') {
            return $this->do_close_only($csPostid, $branchPostid, $siteUrl, $userid, $handle, $cookieid);
        }

        return ['ok' => false, 'error' => 'Unknown action'];
    }

    /**
     * Migrate answers from CS question to branch question, then close CS as duplicate
     */
    private function do_migrate_and_close($csPostid, $branchPostid, $prefix, $siteUrl, $branch, $userid, $handle, $cookieid)
    {
        // Load CS question
        $csQuestion = qa_db_read_one_assoc(
            qa_db_query_sub('SELECT * FROM ^posts WHERE postid=#', $csPostid), true
        );
        if (!$csQuestion || $csQuestion['type'] !== 'Q') {
            return ['ok' => false, 'error' => 'CS question not found'];
        }
        if ($csQuestion['closedbyid'] !== null) {
            return ['ok' => false, 'error' => 'Already closed'];
        }

        // Get CS answers
        $csAnswers = qa_db_read_all_assoc(
            qa_db_query_sub('SELECT * FROM ^posts WHERE parentid=# AND type=$', $csPostid, 'A')
        );

        // Check which CS answers are already duplicated on the branch site
        $branchAnswers = qa_db_read_all_assoc(
            qa_db_query_sub('SELECT content FROM ' . $prefix . 'posts WHERE parentid=# AND type=$', $branchPostid, 'A')
        );
        $branchContentHashes = [];
        foreach ($branchAnswers as $ba) {
            $branchContentHashes[md5(trim($ba['content']))] = true;
        }

        $migrated = 0;
        foreach ($csAnswers as $answer) {
            // Skip if branch already has this answer (content match)
            $hash = md5(trim($answer['content']));
            if (isset($branchContentHashes[$hash])) continue;

            // Insert answer into branch site
            qa_db_query_sub(
                'INSERT INTO ' . $prefix . 'posts (type, parentid, userid, cookieid, createip, lastuserid, lastip, upvotes, downvotes, netvotes, views, hotness, flagcount, format, created, updated, updatetype, title, content, tags, notify) VALUES ($, #, #, #, #, #, #, #, #, #, #, #, #, $, #, #, $, $, $, $, $)',
                'A', $branchPostid, $answer['userid'], $answer['cookieid'], $answer['createip'],
                $answer['lastuserid'], $answer['lastip'], $answer['upvotes'], $answer['downvotes'],
                $answer['netvotes'], ($answer['views'] ?: 0), $answer['hotness'], $answer['flagcount'],
                ($answer['format'] ?: ''), $answer['created'], $answer['updated'], $answer['updatetype'],
                $answer['title'], $answer['content'], $answer['tags'], $answer['notify']
            );
            $newAnswerId = mysqli_insert_id(qa_db_connection());

            // Copy comments on this answer
            $comments = qa_db_read_all_assoc(
                qa_db_query_sub('SELECT * FROM ^posts WHERE parentid=# AND type=$', $answer['postid'], 'C')
            );
            foreach ($comments as $comment) {
                qa_db_query_sub(
                    'INSERT INTO ' . $prefix . 'posts (type, parentid, userid, cookieid, createip, lastuserid, lastip, upvotes, downvotes, netvotes, views, hotness, flagcount, format, created, updated, updatetype, title, content, tags, notify) VALUES ($, #, #, #, #, #, #, #, #, #, #, #, #, $, #, #, $, $, $, $, $)',
                    'C', $newAnswerId, $comment['userid'], $comment['cookieid'], $comment['createip'],
                    $comment['lastuserid'], $comment['lastip'], $comment['upvotes'], $comment['downvotes'],
                    $comment['netvotes'], ($comment['views'] ?: 0), $comment['hotness'], $comment['flagcount'],
                    ($comment['format'] ?: ''), $comment['created'], $comment['updated'], $comment['updatetype'],
                    $comment['title'], $comment['content'], $comment['tags'], $comment['notify']
                );
            }

            // Copy votes for this answer
            $votes = qa_db_read_all_assoc(
                qa_db_query_sub('SELECT * FROM ^uservotes WHERE postid=#', $answer['postid'])
            );
            foreach ($votes as $vote) {
                qa_db_query_sub(
                    'INSERT IGNORE INTO ' . $prefix . 'uservotes (postid, userid, vote, flag, votecreated, voteupdated) VALUES (#, #, #, #, $, $)',
                    $newAnswerId, $vote['userid'], $vote['vote'], $vote['flag'], $vote['votecreated'], $vote['voteupdated']
                );
            }

            // If this was the selected answer on CS, select it on branch too (if branch has none)
            if ($csQuestion['selchildid'] == $answer['postid']) {
                $branchQ = qa_db_read_one_assoc(
                    qa_db_query_sub('SELECT selchildid FROM ' . $prefix . 'posts WHERE postid=#', $branchPostid), true
                );
                if (!$branchQ['selchildid']) {
                    qa_db_query_sub('UPDATE ' . $prefix . 'posts SET selchildid=# WHERE postid=#', $newAnswerId, $branchPostid);
                }
            }

            // Copy ec_answers if they exist
            $ecAnswer = qa_db_read_one_assoc(
                qa_db_query_sub('SELECT * FROM ^ec_answers WHERE postid=#', $answer['postid']), true
            );
            if ($ecAnswer) {
                qa_db_query_sub(
                    'INSERT IGNORE INTO ' . $prefix . 'ec_answers (postid, answer_str, userid, created, edited, editedby) VALUES (#, $, #, $, $, #)',
                    $newAnswerId, $ecAnswer['answer_str'], $ecAnswer['userid'], $ecAnswer['created'], $ecAnswer['edited'], $ecAnswer['editedby']
                );
            }

            $migrated++;
        }

        // Update answer count on branch question
        if ($migrated > 0) {
            qa_db_query_sub(
                'UPDATE ' . $prefix . 'posts SET acount = (SELECT cnt FROM (SELECT COUNT(*) AS cnt FROM ' . $prefix . 'posts AS c WHERE c.parentid=# AND c.type=$) AS sub) WHERE postid=#',
                $branchPostid, 'A', $branchPostid
            );
        }

        // Re-index on branch site
        if ($migrated > 0) {
            global $migrate_change_db;
            $migrate_change_db = $prefix;

            require_once QA_INCLUDE_DIR . 'db/post-create.php';
            require_once QA_INCLUDE_DIR . 'db/post-update.php';
            require_once QA_INCLUDE_DIR . 'db/points.php';

            $branchPost = qa_db_read_one_assoc(
                qa_db_query_sub('SELECT * FROM ^posts WHERE postid=#', $branchPostid), true
            );
            if ($branchPost) {
                qa_db_posts_calc_category_path($branchPost['postid']);
                qa_db_category_path_qcount_update(qa_db_post_get_category_path($branchPost['postid']));
                qa_db_hotness_update($branchPost['postid']);
            }

            $migrate_change_db = null;
        }

        // Now close CS question as duplicate with a note linking to the branch site
        $branchUrl = $siteUrl . $branchPostid;
        $note = $branchUrl;

        // Get existing close post if any
        $closepost = null;
        if ($csQuestion['closedbyid']) {
            $closepost = qa_db_read_one_assoc(
                qa_db_query_sub('SELECT * FROM ^posts WHERE postid=#', $csQuestion['closedbyid']), true
            );
        }

        qa_question_close_other($csQuestion, $closepost, $note, $userid, $handle, $cookieid);

        return [
            'ok' => true,
            'migrated' => $migrated,
            'closed' => true,
            'message' => "Migrated $migrated answer(s) to $branchName, closed CS question as duplicate.",
        ];
    }

    /**
     * Merge CS question into branch question using q2a-post-merge plugin.
     * Copies children, merges tags (source preferred), deletes source with redirect.
     */
    private function do_migrate_close_merge($csPostid, $branchPostid, $prefix, $siteUrl, $branch, $userid, $handle, $cookieid)
    {
        $fromPrefix = QA_MYSQL_TABLE_PREFIX;
        $toPrefix = $prefix;

        $result = qa_copy_or_merge($csPostid, $branchPostid, $fromPrefix, $toPrefix, false, true, 'merge');
        if ($result !== true) {
            $error = is_array($result) ? implode(' ', array_filter($result)) : 'Merge failed';
            return ['ok' => false, 'error' => $error];
        }

        $deleted = delete_and_redirect_linking($csPostid, $branchPostid, $fromPrefix, $toPrefix);
        if ($deleted !== true) {
            return ['ok' => false, 'error' => 'Delete/redirect failed'];
        }

        $branchName = $this->branches[$branch][2];
        return [
            'ok' => true,
            'migrated' => 0,
            'closed' => true,
            'message' => "Merged CS #$csPostid into $branchName #$branchPostid (with redirect).",
        ];
    }

    /**
     * Close CS question as duplicate without migrating answers
     */
    private function do_close_only($csPostid, $branchPostid, $siteUrl, $userid, $handle, $cookieid)
    {
        $csQuestion = qa_db_read_one_assoc(
            qa_db_query_sub('SELECT * FROM ^posts WHERE postid=#', $csPostid), true
        );
        if (!$csQuestion || $csQuestion['type'] !== 'Q') {
            return ['ok' => false, 'error' => 'CS question not found'];
        }
        if ($csQuestion['closedbyid'] !== null) {
            return ['ok' => false, 'error' => 'Already closed'];
        }

        $branch = qa_post_text('branch');
        $branchUrl = $siteUrl . $branchPostid;
        $note = $branchUrl;

        $closepost = null;
        if ($csQuestion['closedbyid']) {
            $closepost = qa_db_read_one_assoc(
                qa_db_query_sub('SELECT * FROM ^posts WHERE postid=#', $csQuestion['closedbyid']), true
            );
        }

        qa_question_close_other($csQuestion, $closepost, $note, $userid, $handle, $cookieid);

        return ['ok' => true, 'closed' => true, 'message' => "Closed CS question as duplicate of $branchName #$branchPostid."];
    }

    private function get_duplicate_pairs($branchFilter = null, $page = 1, $perPage = 50)
    {
        $branchCondition = '';
        if ($branchFilter && isset($this->branches[$branchFilter])) {
            $branchCondition = " AND other.branch = '" . qa_db_escape_string($branchFilter) . "'";
        }

        $offset = ($page - 1) * $perPage;

        $rows = qa_db_read_all_assoc(qa_db_query_sub(
            "SELECT cs.postid AS cs_postid, cs.title, cs.acount AS cs_answers,
                    cs.selchildid AS cs_selchild, cs.closedbyid,
                    cs.netvotes AS cs_votes, cs.views AS cs_views,
                    other.postid AS branch_postid, other.branch,
                    other.acount AS branch_answers, other.selchildid AS branch_selchild,
                    other.netvotes AS branch_votes
             FROM qa_engineering_mathematics cs
             JOIN qa_engineering_mathematics other
               ON other.branch != 'cs' AND other.type = 'Q'
               AND cs.title LIKE 'GATE CSE %'
               AND (
                   (other.branch = 'ee' AND other.title LIKE 'GATE Electrical %') OR
                   (other.branch = 'ec' AND other.title LIKE 'GATE ECE %') OR
                   (other.branch = 'me' AND other.title LIKE 'GATE Mechanical %') OR
                   (other.branch = 'ce' AND other.title LIKE 'GATE Civil %') OR
                   (other.branch = 'ch' AND other.title LIKE 'GATE Chemical %') OR
                   (other.branch = 'in' AND other.title LIKE 'GATE IN %') OR
                   (other.branch = 'bt' AND other.title LIKE 'GATE BT %')
               )
               AND SUBSTRING_INDEX(SUBSTRING_INDEX(cs.title,' ',3),' ',-1)
                 = SUBSTRING_INDEX(SUBSTRING_INDEX(other.title,' ',3),' ',-1)
               AND IF(cs.title LIKE '%Set %', CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(cs.title,'Set ',-1),' ',1) AS UNSIGNED),0)
                 = IF(other.title LIKE '%Set %', CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(other.title,'Set ',-1),' ',1) AS UNSIGNED),0)
               AND CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(cs.title,': ',-1),'GA',-1) AS UNSIGNED)
                 = CAST(SUBSTRING_INDEX(other.title,': ',-1) AS UNSIGNED)
               AND (cs.title LIKE '%Question: GA%' OR cs.title LIKE '%GA Question%' OR cs.title LIKE '%| GA |%')
                 = (other.title LIKE '%GA Question%' OR other.title LIKE '%| GA |%')
             WHERE cs.branch = 'cs' AND cs.type = 'Q'
               AND (cs.tags LIKE '%aptitude%' OR cs.categoryid IN (4,5,6,7))
               $branchCondition
             ORDER BY cs.closedbyid IS NULL DESC, cs.acount DESC, cs.postid DESC
             LIMIT #, #",
            $offset, $perPage
        ));

        // Get total count
        $total = (int)qa_db_read_one_value(qa_db_query_sub(
            "SELECT COUNT(*)
             FROM qa_engineering_mathematics cs
             JOIN qa_engineering_mathematics other
               ON other.branch != 'cs' AND other.type = 'Q'
               AND cs.title LIKE 'GATE CSE %'
               AND (
                   (other.branch = 'ee' AND other.title LIKE 'GATE Electrical %') OR
                   (other.branch = 'ec' AND other.title LIKE 'GATE ECE %') OR
                   (other.branch = 'me' AND other.title LIKE 'GATE Mechanical %') OR
                   (other.branch = 'ce' AND other.title LIKE 'GATE Civil %') OR
                   (other.branch = 'ch' AND other.title LIKE 'GATE Chemical %') OR
                   (other.branch = 'in' AND other.title LIKE 'GATE IN %') OR
                   (other.branch = 'bt' AND other.title LIKE 'GATE BT %')
               )
               AND SUBSTRING_INDEX(SUBSTRING_INDEX(cs.title,' ',3),' ',-1)
                 = SUBSTRING_INDEX(SUBSTRING_INDEX(other.title,' ',3),' ',-1)
               AND IF(cs.title LIKE '%Set %', CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(cs.title,'Set ',-1),' ',1) AS UNSIGNED),0)
                 = IF(other.title LIKE '%Set %', CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(other.title,'Set ',-1),' ',1) AS UNSIGNED),0)
               AND CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(cs.title,': ',-1),'GA',-1) AS UNSIGNED)
                 = CAST(SUBSTRING_INDEX(other.title,': ',-1) AS UNSIGNED)
               AND (cs.title LIKE '%Question: GA%' OR cs.title LIKE '%GA Question%' OR cs.title LIKE '%| GA |%')
                 = (other.title LIKE '%GA Question%' OR other.title LIKE '%| GA |%')
             WHERE cs.branch = 'cs' AND cs.type = 'Q'
               AND (cs.tags LIKE '%aptitude%' OR cs.categoryid IN (4,5,6,7))
               $branchCondition"
        ), true);

        return ['rows' => $rows, 'total' => $total];
    }

    private function render_page()
    {
        $qa_content = qa_content_prepare();
        $qa_content['title'] = 'Aptitude Question Migration';

        $branchFilter = qa_get('branch');
        $page = max(1, (int)(qa_get('page') ?: 1));
        $perPage = 50;

        $data = $this->get_duplicate_pairs($branchFilter, $page, $perPage);
        $rows = $data['rows'];
        $total = $data['total'];
        $totalPages = max(1, ceil($total / $perPage));

        // Stats
        $stats = qa_db_read_all_assoc(qa_db_query_sub(
            "SELECT other.branch,
                    COUNT(DISTINCT cs.postid) AS total,
                    SUM(cs.closedbyid IS NOT NULL) AS closed
             FROM qa_engineering_mathematics cs
             JOIN qa_engineering_mathematics other
               ON other.branch != 'cs' AND other.type = 'Q'
               AND cs.title LIKE 'GATE CSE %'
               AND (
                   (other.branch = 'ee' AND other.title LIKE 'GATE Electrical %') OR
                   (other.branch = 'ec' AND other.title LIKE 'GATE ECE %') OR
                   (other.branch = 'me' AND other.title LIKE 'GATE Mechanical %') OR
                   (other.branch = 'ce' AND other.title LIKE 'GATE Civil %') OR
                   (other.branch = 'ch' AND other.title LIKE 'GATE Chemical %') OR
                   (other.branch = 'in' AND other.title LIKE 'GATE IN %') OR
                   (other.branch = 'bt' AND other.title LIKE 'GATE BT %')
               )
               AND SUBSTRING_INDEX(SUBSTRING_INDEX(cs.title,' ',3),' ',-1)
                 = SUBSTRING_INDEX(SUBSTRING_INDEX(other.title,' ',3),' ',-1)
               AND IF(cs.title LIKE '%Set %', CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(cs.title,'Set ',-1),' ',1) AS UNSIGNED),0)
                 = IF(other.title LIKE '%Set %', CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(other.title,'Set ',-1),' ',1) AS UNSIGNED),0)
               AND CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(cs.title,': ',-1),'GA',-1) AS UNSIGNED)
                 = CAST(SUBSTRING_INDEX(other.title,': ',-1) AS UNSIGNED)
               AND (cs.title LIKE '%Question: GA%' OR cs.title LIKE '%GA Question%' OR cs.title LIKE '%| GA |%')
                 = (other.title LIKE '%GA Question%' OR other.title LIKE '%| GA |%')
             WHERE cs.branch = 'cs' AND cs.type = 'Q'
               AND (cs.tags LIKE '%aptitude%' OR cs.categoryid IN (4,5,6,7))
             GROUP BY other.branch
             ORDER BY total DESC"
        ));

        $totalAll = 0;
        $closedAll = 0;
        foreach ($stats as $s) {
            $totalAll += $s['total'];
            $closedAll += $s['closed'];
        }

        ob_start();
        ?>
        <style>
            .am-wrap { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; max-width: 1200px; }
            .am-stats { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 16px; }
            .am-stat { padding: 6px 14px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none; border: 2px solid transparent; }
            .am-stat:hover { opacity: 0.85; }
            .am-stat.active { border-color: #333; }
            .am-stat-all { background: #e3f2fd; color: #1565c0; }
            .am-stat-ee { background: #fff3e0; color: #e65100; }
            .am-stat-ec { background: #e8f5e9; color: #2e7d32; }
            .am-stat-me { background: #fce4ec; color: #c62828; }
            .am-stat-ce { background: #f3e5f5; color: #6a1b9a; }
            .am-stat-ch { background: #e0f7fa; color: #00695c; }
            .am-stat-in { background: #fff8e1; color: #f57f17; }
            .am-stat-bt { background: #efebe9; color: #4e342e; }

            .am-progress { background: #f0f0f0; border-radius: 10px; height: 20px; margin-bottom: 16px; overflow: hidden; }
            .am-progress-bar { height: 100%; background: #4caf50; border-radius: 10px; transition: width 0.3s; text-align: center; color: #fff; font-size: 11px; line-height: 20px; font-weight: 600; }

            .am-table { width: 100%; border-collapse: collapse; font-size: 13px; background: #fff; border: 1px solid #ddd; }
            .am-table th { background: #f5f5f5; padding: 8px 10px; text-align: left; border-bottom: 2px solid #ddd; font-size: 11px; text-transform: uppercase; color: #666; }
            .am-table td { padding: 7px 10px; border-bottom: 1px solid #eee; vertical-align: middle; }
            .am-table tr:hover { background: #fafafa; }
            .am-table tr.am-closed { opacity: 0.5; }

            .am-title { max-width: 350px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
            .am-title a { color: #1a73e8; text-decoration: none; }
            .am-title a:hover { text-decoration: underline; }
            .am-branch { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
            .am-branch-ee { background: #fff3e0; color: #e65100; }
            .am-branch-ec { background: #e8f5e9; color: #2e7d32; }
            .am-branch-me { background: #fce4ec; color: #c62828; }
            .am-branch-ce { background: #f3e5f5; color: #6a1b9a; }
            .am-branch-ch { background: #e0f7fa; color: #00695c; }
            .am-branch-in { background: #fff8e1; color: #f57f17; }
            .am-branch-bt { background: #efebe9; color: #4e342e; }

            .am-num { text-align: center; font-weight: 600; }
            .am-num-warn { color: #e65100; }
            .am-num-ok { color: #2e7d32; }

            .am-btn { display: inline-block; padding: 4px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: 600; }
            .am-btn:disabled { opacity: 0.4; cursor: not-allowed; }
            .am-btn-migrate { background: #ff9800; color: #fff; }
            .am-btn-migrate:hover:not(:disabled) { background: #f57c00; }
            .am-btn-close { background: #f44336; color: #fff; }
            .am-btn-close:hover:not(:disabled) { background: #d32f2f; }
            .am-btn-done { background: #4caf50; color: #fff; cursor: default; }
            .am-btn-all { background: #1565c0; color: #fff; padding: 8px 20px; font-size: 14px; margin-bottom: 16px; }
            .am-btn-all:hover:not(:disabled) { background: #0d47a1; }

            .am-pager { margin-top: 12px; display: flex; gap: 4px; }
            .am-pager a, .am-pager span { padding: 4px 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 12px; text-decoration: none; color: #333; }
            .am-pager span.current { background: #1565c0; color: #fff; border-color: #1565c0; }

            .am-info { margin-top: 16px; padding: 12px; background: #f8f9fa; border-radius: 6px; font-size: 12px; color: #666; }
            .am-toast { position: fixed; bottom: 20px; right: 20px; padding: 10px 20px; border-radius: 6px; color: #fff; font-size: 13px; font-weight: 600; z-index: 9999; display: none; }
            .am-toast-ok { background: #4caf50; }
            .am-toast-err { background: #f44336; }

            /* Dark mode — Polaris theme */
            [data-theme="dark"] .am-wrap { color: #e0e0e0; }
            [data-theme="dark"] .am-table { background: #1e1e1e; border-color: #444; }
            [data-theme="dark"] .am-table th { background: #2d2d2d; color: #aaa; border-color: #444; }
            [data-theme="dark"] .am-table td { border-color: #333; color: #ddd; }
            [data-theme="dark"] .am-table tr:hover { background: #252525; }
            [data-theme="dark"] .am-progress { background: #333; }
            [data-theme="dark"] .am-title a { color: #6db3f2; }
            [data-theme="dark"] .am-pager a, [data-theme="dark"] .am-pager span { border-color: #444; color: #ccc; background: #2d2d2d; }
            [data-theme="dark"] .am-pager span.current { background: #1565c0; color: #fff; border-color: #1565c0; }
            [data-theme="dark"] .am-info { background: #2d2d2d; color: #aaa; }
            [data-theme="dark"] .am-stat.active { border-color: #eee; }
        </style>

        <div class="am-wrap">

        <!-- Progress -->
        <?php $pct = $totalAll > 0 ? round($closedAll / $totalAll * 100) : 0; ?>
        <div class="am-progress">
            <div class="am-progress-bar" style="width: <?= $pct ?>%"><?= $closedAll ?>/<?= $totalAll ?> (<?= $pct ?>%)</div>
        </div>

        <!-- Branch filter tabs -->
        <div class="am-stats">
            <a href="<?= qa_path('aptitude-migrate') ?>"
               class="am-stat am-stat-all <?= !$branchFilter ? 'active' : '' ?>">
                All: <?= $totalAll ?> (<?= $closedAll ?> done)
            </a>
            <?php foreach ($stats as $s): ?>
                <a href="<?= qa_path('aptitude-migrate', ['branch' => $s['branch']]) ?>"
                   class="am-stat am-stat-<?= $s['branch'] ?> <?= $branchFilter === $s['branch'] ? 'active' : '' ?>">
                    <?= strtoupper($s['branch']) ?>: <?= $s['total'] ?> (<?= (int)$s['closed'] ?> done)
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Bulk action -->
        <?php
            $openRows = array_filter($rows, function($r) { return $r['closedbyid'] === null; });
            $canMigrateAll = count($openRows) > 0;
        ?>
        <?php if ($canMigrateAll): ?>
        <button class="am-btn am-btn-all" onclick="amBulkAction('migrate_and_close')"
                title="Migrate answers & close all open CS duplicates on this page">
            Migrate & Close All on Page (<?= count($openRows) ?>)
        </button>
        <button class="am-btn am-btn-all" onclick="amBulkAction('migrate_close_merge')"
                style="background:#6a1b9a;" title="Merge answers/votes to branch, replace CS with redirect to branch">
            Merge & Redirect All (<?= count($openRows) ?>)
        </button>
        <button class="am-btn am-btn-all" onclick="amBulkAction('close_only')"
                style="background:#f44336;" title="Close all without migrating answers">
            Close Only All on Page (<?= count($openRows) ?>)
        </button>
        <?php endif; ?>

        <!-- Table -->
        <table class="am-table">
            <thead>
                <tr>
                    <th>CS Question</th>
                    <th>Branch</th>
                    <th title="Answers on CS / Branch">CS Ans</th>
                    <th>Br Ans</th>
                    <th>CS Votes</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="7" style="text-align:center;padding:20px;color:#999;">No duplicate aptitude questions found.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $row):
                $isClosed = $row['closedbyid'] !== null;
                $branchInfo = $this->branches[$row['branch']];
                $csUrl = qa_opt('site_url') . $row['cs_postid'];
                $branchUrl = $branchInfo[1] . $row['branch_postid'];
                $csHasMore = $row['cs_answers'] > $row['branch_answers'];
            ?>
                <tr class="<?= $isClosed ? 'am-closed' : '' ?>"
                    data-cs="<?= $row['cs_postid'] ?>"
                    data-branch-post="<?= $row['branch_postid'] ?>"
                    data-branch="<?= qa_html($row['branch']) ?>">
                    <td class="am-title">
                        <a href="<?= qa_html($csUrl) ?>" target="_blank"
                           title="<?= qa_html($row['title']) ?>"><?= qa_html($row['title']) ?></a>
                    </td>
                    <td>
                        <a href="<?= qa_html($branchUrl) ?>" target="_blank"
                           class="am-branch am-branch-<?= $row['branch'] ?>">
                            <?= strtoupper($row['branch']) ?> #<?= $row['branch_postid'] ?>
                        </a>
                    </td>
                    <td class="am-num <?= $csHasMore ? 'am-num-warn' : '' ?>"><?= (int)$row['cs_answers'] ?></td>
                    <td class="am-num <?= $row['branch_answers'] > 0 ? 'am-num-ok' : '' ?>"><?= (int)$row['branch_answers'] ?></td>
                    <td class="am-num"><?= (int)$row['cs_votes'] ?></td>
                    <td>
                        <?php if ($isClosed): ?>
                            <span class="am-btn am-btn-done">Closed</span>
                        <?php else: ?>
                            <span style="color:#e65100;font-weight:600;">Open</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!$isClosed): ?>
                            <button class="am-btn am-btn-migrate" onclick="amAction(this, 'migrate_and_close')"
                                    title="Migrate answers to branch site, then close CS copy">
                                Migrate & Close
                            </button>
                            <button class="am-btn am-btn-migrate" onclick="amAction(this, 'migrate_close_merge')"
                                    style="background:#6a1b9a;"
                                    title="Merge answers/votes to branch, delete CS and redirect visitors to branch">
                                Merge & Redirect
                            </button>
                            <button class="am-btn am-btn-close" onclick="amAction(this, 'close_only')"
                                    title="Close CS copy without migrating (branch already has answers)">
                                Close Only
                            </button>
                        <?php else: ?>
                            &mdash;
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="am-pager">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <?php
                    $params = ['page' => $p];
                    if ($branchFilter) $params['branch'] = $branchFilter;
                ?>
                <?php if ($p === $page): ?>
                    <span class="current"><?= $p ?></span>
                <?php else: ?>
                    <a href="<?= qa_path('aptitude-migrate', $params) ?>"><?= $p ?></a>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
        <?php endif; ?>

        <div class="am-info">
            <strong>How it works:</strong>
            <strong>Migrate & Close</strong> copies unique answers (+ comments, votes, answer keys) from the CS question
            to the branch site, then closes the CS question with a "Duplicate of [branch link]" note.<br>
            <strong>Close Only</strong> just closes the CS question without migrating answers (use when branch already has all answers).
        </div>

        </div><!-- .am-wrap -->

        <div id="am-toast" class="am-toast"></div>

        <script>
        function amToast(msg, ok) {
            var t = document.getElementById('am-toast');
            t.textContent = msg;
            t.className = 'am-toast ' + (ok ? 'am-toast-ok' : 'am-toast-err');
            t.style.display = 'block';
            setTimeout(function() { t.style.display = 'none'; }, 4000);
        }

        function amAction(btn, action) {
            var row = btn.closest('tr');
            var csPostid = row.getAttribute('data-cs');
            var branchPostid = row.getAttribute('data-branch-post');
            var branch = row.getAttribute('data-branch');

            if (!confirm('Are you sure? This will ' +
                (action === 'migrate_and_close' ? 'migrate answers and ' : action === 'migrate_close_merge' ? 'merge answers/votes to branch, delete CS and redirect visitors. ' : '') +
                'close CS question #' + csPostid + ' as duplicate.')) return;

            btn.disabled = true;
            btn.textContent = 'Working...';

            var fd = new FormData();
            fd.append('ajax_action', action);
            fd.append('cs_postid', csPostid);
            fd.append('branch_postid', branchPostid);
            fd.append('branch', branch);

            fetch(window.location.pathname, { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    if (d.ok) {
                        amToast(d.message, true);
                        row.classList.add('am-closed');
                        var actions = row.querySelector('td:last-child');
                        actions.innerHTML = '&mdash;';
                        var status = row.querySelector('td:nth-child(6)');
                        status.innerHTML = '<span class="am-btn am-btn-done">Closed</span>';
                    } else {
                        amToast('Error: ' + (d.error || 'Unknown'), false);
                        btn.disabled = false;
                        btn.textContent = action === 'migrate_and_close' ? 'Migrate & Close' : action === 'migrate_close_merge' ? 'Merge & Redirect' : 'Close Only';
                    }
                })
                .catch(function(e) {
                    amToast('Network error: ' + e.message, false);
                    btn.disabled = false;
                    btn.textContent = action === 'migrate_and_close' ? 'Migrate & Close' : action === 'migrate_close_merge' ? 'Merge & Redirect' : 'Close Only';
                });
        }

        function amBulkAction(action) {
            var rows = document.querySelectorAll('tr[data-cs]:not(.am-closed)');
            if (!rows.length) return;
            if (!confirm('Process ' + rows.length + ' questions with "' +
                (action === 'migrate_and_close' ? 'Migrate & Close' : 'Close Only') + '"?')) return;

            var queue = Array.from(rows);
            var idx = 0;

            function processNext() {
                if (idx >= queue.length) {
                    amToast('Bulk action complete: ' + queue.length + ' questions processed.', true);
                    return;
                }
                var row = queue[idx];
                var btn = row.querySelector('.am-btn-migrate') || row.querySelector('.am-btn-close');
                if (btn) {
                    btn.disabled = true;
                    btn.textContent = 'Working...';
                }

                var fd = new FormData();
                fd.append('ajax_action', action);
                fd.append('cs_postid', row.getAttribute('data-cs'));
                fd.append('branch_postid', row.getAttribute('data-branch-post'));
                fd.append('branch', row.getAttribute('data-branch'));

                fetch(window.location.pathname, { method: 'POST', body: fd })
                    .then(function(r) { return r.json(); })
                    .then(function(d) {
                        if (d.ok) {
                            row.classList.add('am-closed');
                            row.querySelector('td:last-child').innerHTML = '&mdash;';
                            row.querySelector('td:nth-child(6)').innerHTML = '<span class="am-btn am-btn-done">Closed</span>';
                        }
                        idx++;
                        setTimeout(processNext, 300); // small delay between requests
                    })
                    .catch(function() { idx++; setTimeout(processNext, 300); });
            }
            processNext();
        }
        </script>
        <?php

        $qa_content['custom'] = ob_get_clean();
        return $qa_content;
    }
}
