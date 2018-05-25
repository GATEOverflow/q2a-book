<?php



if (!defined('QA_VERSION')) { // don't allow this page to be requested directly from browser
	header('Location: ../../');
	exit;   
}               

qa_register_plugin_module('module', 'qa-book-admin.php', 'qa_book_admin', 'Book Export');

qa_register_plugin_overrides('qa-book-overrides.php');

qa_register_plugin_module('widget', 'qa-book-widget.php', 'qa_book_widget', 'Book Widget');

qa_register_plugin_phrases('qa-book-lang-*.php', 'book');

require 'util-book.php';
require_once QA_INCLUDE_DIR.'/app/format.php';

function qa_book_catselect(){
	if(qa_opt('book_plugin_catex'))
	{
		$ex = qa_opt('book_plugin_catex');
		$excat = "(select categoryid from ^categories where parentid in ($ex) union 
			select categoryid from ^categories where parentid in (select categoryid from ^categories where parentid in ($ex)) union
			select categoryid from ^categories where categoryid in ($ex))";
		return $excat;
	}
	else return '()';

}
function qa_book_getallcats(&$cats, $all=false){
	$cats = 	qa_db_read_all_assoc(
			qa_db_query_sub(
				'SELECT c.categoryid, concat(ifnull(concat(p.title,": "), ""), c.title) as title, c.tags as tags, p.categoryid as parentid FROM ^categories c left join ^categories p on (c.parentid = p.categoryid) '.// categoryid not in (select parentid from ^categories where parentid is not null) '.
				//			'SELECT c.categoryid,  c.title as title, p.categoryid as parentid FROM ^categories c left join ^categories p on (c.parentid = p.categoryid) '.// categoryid not in (select parentid from ^categories where parentid is not null) '.
				(!$all?
				 (qa_opt('book_plugin_catex')?' where c.categoryid NOT IN '.qa_book_catselect():'')
				 :'')
				.' order by title'
				)
			);
	$navcats = array();
	foreach($cats as $cat)
		$navcats[$cat['categoryid']] = $cat;
	return $navcats;

}


function qa_book_plugin_createBook($return=false) {

	$book = qa_opt('book_plugin_template');

	// static replacements

	$extras='';
	if(qa_opt('qa-mathjax-enable'))
	{
		$extras.=  '<script  type="text/x-mathjax-config">'. qa_opt('qa-mathjax-config').'</script>';
		$extras .= '<script  async type="text/javascript" src="'.qa_opt('qa-mathjax-url').'"></script>';
	}
	if(qa_opt("qa-pretiffy-enable"))
	{
		$extras.='<script  async type="text/javascript" src="'.qa_opt('qa-pretiffy-url').'"></script>';
	}


	$book = str_replace('[css]',qa_opt('book_plugin_css'),$book);
	$book = str_replace('[script]',$extras,$book);
	$book = str_replace('[front]',qa_opt('book_plugin_template_front'),$book);
	$book = str_replace('[back]',qa_opt('book_plugin_template_back'),$book);			

	$shuffle = qa_opt('book_plugin_shuffle');
	$iscats = qa_opt('book_plugin_cats');

	// categories

	$cats = array(false);
	if($iscats) {
		$navcats =  qa_book_getallcats($cats);
	}

	// intro

	$intro = '<p>'.qa_opt('book_plugin_intro').'</p>';
	$ack = '<p>'.qa_opt('book_plugin_ack').'</p>';

	$intro = str_replace('[sort_questions]',qa_lang('book/'.(qa_opt('book_plugin_sort_q') == 0?'sort_upvotes':'sort_date')),$intro);
	$intro = str_replace('[sort_categories]',$iscats?qa_lang('book/sort_categories'):'',$intro);
	$intro = str_replace('[restrict_questions]',qa_opt('book_plugin_req_qv')?qa_lang_sub('book/restrict_q_x_votes',qa_opt('book_plugin_req_qv_no')):qa_lang('book/all_questions'),$intro);

	$rq = array();

	if(qa_opt('book_plugin_req_sel'))
		$rq[] = qa_lang('book/restrict_selected');
	if(qa_opt('book_plugin_req_abest'))
		$rq[] = qa_lang('book/restrict_best_a');
	if(qa_opt('book_plugin_req_av'))
		$rq[] = qa_lang_sub('book/restrict_a_x_votes',qa_opt('book_plugin_req_av_no'));



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

	$ccount=0;
	$catanchor = '';
			$topic_array = array();// $qcontent; 
	foreach($cats as $cat) {
		$qcount=0;
		$incsql = '';
		$anssql = ' and ans.type = \'A\' ';
		$sortsql = '';

		$toc = '';
		$qhtml = '';

		if(qa_opt('book_plugin_sort_q') == 0)
			$sortsql='ORDER BY qs.netvotes DESC, qs.created ASC';
		else
			$sortsql='ORDER BY qs.created ASC';

		if(qa_opt('book_plugin_req_sel'))
			$incsql .= ' AND qs.selchildid=ans.postid';

		if(qa_opt('book_plugin_req_abest'))
			//$sortsql.=',  ans.netvotes DESC'; // get all, limit later with break
			$sortsql.=', (ans.postid = qs.selchildid) desc, ans.netvotes DESC'; // get all, limit later with break

		if(qa_opt('book_plugin_req_qv'))
			$incsql .= ' AND qs.netvotes >= '.(int)qa_opt('book_plugin_req_qv_no');

		if(qa_opt('book_plugin_req_av'))
			$anssql .= ' AND (ans.netvotes >= '.(int)qa_opt('book_plugin_req_av_no').' OR  qs.selchildid=ans.postid)';

		$skipanswers = !qa_opt('book_plugin_show_a');
		$reqanswers = qa_opt('book_plugin_req_ans');
		$introsuffix = '';
		if(qa_opt('book_plugin_enable_custom_filter1')){
			$incsql .= " and (".qa_opt('book_plugin_custom_filter1').")";
			$introsuffix.='<p>'. qa_opt('book_plugin_custom_filter1_desc').'</p>';
		}
		if(qa_opt('book_plugin_enable_custom_filter2')){
			$incsql.=" and (".qa_opt('book_plugin_custom_filter2').")";
			$introsuffix.='<p>'. qa_opt('book_plugin_custom_filter2_desc').'</p>';
		}
		if(qa_opt('book_plugin_enable_custom_filter3')){
			$incsql .= " and (".qa_opt('book_plugin_custom_filter3').")";
			$introsuffix.='<p>'. qa_opt('book_plugin_custom_filter3_desc').'</p>';
		}
		if(qa_opt('book_plugin_enable_custom_filter4')){
			$incsql .= " and (".qa_opt('book_plugin_custom_filter4').")";
			$introsuffix.='<p>'. qa_opt('book_plugin_custom_filter4_desc').'</p>';
		}
		if(qa_opt('book_plugin_enable_custom_filter5')){
			$incsql .= " and (".qa_opt('book_plugin_custom_filter5').")";
			$introsuffix.='<p>'. qa_opt('book_plugin_custom_filter5_desc').'</p>';
		}
		if($skipanswers){
			$anssql .= ' AND  ans.postid < 0 '; 
		}
		$sortsql.=',  ans.netvotes DESC'; // get all, limit later with break
		$wrongsql = ' and ans.postid not in (select postid from ^postmetas where title like "wrong" and content = 1) ';
		$selectspec="SELECT qs.postid AS postid, BINARY qs.title AS title, BINARY qs.content AS content, qs.format AS format, qs.netvotes AS netvotes, qs.tags as tags, qs.selchildid as selected, ans.postid as apostid, BINARY ans.content AS acontent, ans.format AS aformat, ans.userid AS auserid, ans.netvotes AS anetvotes FROM ^posts  qs ".($reqanswers?"":"left outer join")." ^posts  ans on qs.postid=ans.parentid and qs.type='Q' and ans.type='A' where true   ".($iscats?" AND qs.categoryid=".$cat['categoryid']:"") ." ". $incsql." ".$anssql." ".$wrongsql." ".$sortsql;

		$qs = qa_db_read_all_assoc(
				qa_db_query_sub(
					$selectspec
					)
				);	

		if(empty($qs)) // no questions in this category
			continue;
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
		$topicblockprefix = '<div class="topic-block" id="'.$cat['categoryid'].'_topic_[tlink]"><h2 class="top-title"><a class="topic-link" href="'.qa_opt('site_url').'tag/[topicurl]">[topic]</a></h2><a class="top-link" href=#[top-link]>top</a></div>';
		$qtopiccount = 1;
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
					if(qa_opt('book_plugin_push_a') && ($answers != '')){
						$answerblock = str_replace("[topic]",  gettag($oldmint), $answerblockprefix);
						$answerblock = str_replace("[topicname]",  $oldmint, $answerblock);
						$qhtml .=qa_book_answerblockprefix($oldmint,$answerblockprefix). $answers;
						$answers='';
					}
					$tcount++;
					$topicanchor= $cat['categoryid'].'_topic_'.gettag($mint);
					$number="<div class=\"number\">".$ccount.".".$tcount."</div>";	
					//$topic = str_replace("[topic]", $number.' '.$mint.'('.$qtopiccount.')',  $topicblockprefix);	
					$qhtml = str_replace("[zzzqcount]", $qcount, $qhtml); 
					$topic = str_replace("[topic]", $number.' '.$mint.'([zzzqcount])',  $topicblockprefix);	
					$topic = str_replace("[topicurl]", gettag($mint),  $topic);	
					$topic = str_replace("[tlink]", gettag($mint),  $topic);	
					$topic = str_replace("[top-link]", $catanchor,  $topic);	
					$qhtml .= $topic;
					//$toc.=str_replace('[qlink]','<a href="#question'.$qs[0]['postid'].'">'.$mint.'</a>',qa_opt('book_plugin_template_toc'));
					//$toc.=str_replace('[qlink]','<div class="toc-col1"><a href="#topic'.gettag($mint).'">'.$mint.'</a></div><div class="toc-col2"> ('.$qtopiccount.')</div>',qa_opt('book_plugin_template_toc'));
					$toc = str_replace("[zzzqcount]", $qcount, $toc);
					$toc.=str_replace('[qlink]','<div class="toc-col1"><a href="#'.$cat['categoryid'].'_topic_'.gettag($mint).'">'.$mint.'</a></div><div class="toc-col2"> ([zzzqcount])</div>',qa_opt('book_plugin_template_toc'));
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
			foreach($qs as $idx => $q) {
				if(qa_opt('book_plugin_req_abest') && qa_opt('book_plugin_req_abest_max') && $idx >= qa_opt('book_plugin_req_abest_max'))
					break;
				if($nv !== false  && qa_opt('book_plugin_req_abest') && $q['anetvotes'] < 10) // if a best answer add one more with at least 10 votes
					break;
				//if($nv !== false && qa_opt('book_plugin_req_abest') && $nv != $q['anetvotes']) // best answers only
				//	break;
				/*arjun*/
				if($idx && ($q['anetvotes'] < qa_opt('book_plugin_req_av_no')))	
					break;
				$acount++;
				$acontent = '';
				if(!empty($q['acontent'])) {
					$viewer=qa_load_viewer($q['acontent'], $q['aformat']);
					$acontent = $viewer->get_html($q['acontent'], $q['aformat'], array());
					$noanswer = false;
				}
				$a = str_replace('[answer]',$acontent,qa_opt('book_plugin_template_answer'));
				if($q['selected'] == $q['apostid'] && $q['apostid'] !== NULL){
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
				if($q['auserid'] !== NULL)	
					$a = str_replace('[answerer]',qa_get_user_name($q['auserid']),$a);
				else {
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
				$qcount++;
				$viewer=qa_load_viewer($q['content'], $q['format']);
				$qcontent = $viewer->get_html($q['content'], $q['format'], array());
			}
			$tagshtml='';
			$tags = qa_tagstring_to_tags($q['tags']);
			$mint=mintag($q);
			if(count($topic_array[$mint]) < 15)
			$topic_array[$mint][] = $qcontent; 
		//	print_r($topic_array);
		//	echo $content;
			foreach ($tags as $tag)
			{

				$tagshtml.="<li class=\"qa-q-view-tag-item\"> <a href=\"".qa_opt("site_url")."tag/".$tag."\"    class=\"qa-tag-link\">".$tag." </a></li>";
			}
			if($mint !== '')
				$mint.=": ";
			$number="<div class=\"number\">".$ccount.".".$tcount.".".$qcount."</div>";	
			$titleright="<div class=\"title-right\"><a href=\"".qa_opt("site_url").$q['postid']."\">".qa_opt("site_url").$q['postid']."</a></div>";
			$oneq = str_replace('[question-title]',$number.$mint.$q['title'].$titleright,qa_opt('book_plugin_template_question'));
			$oneq = str_replace('[qanchor]','question'.$q['postid'],$oneq);
			$oneq = str_replace('[qurl]',qa_html(qa_q_request($q['postid'],$q['title'])),$oneq);
			$oneq = str_replace('[question]',$qcontent,$oneq);
			$oneq = str_replace('[top-link]',($topicanchor? $topicanchor:$catanchor),$oneq);
			$oneq = str_replace('[tags]', $tagshtml, $oneq);
			$oneq = str_replace('[hide]', '', $oneq);
			// output with answers  
			if($skipanswers)
				$qhtml .= str_replace('[answers]','',$oneq);
			else if(qa_opt('book_plugin_push_a') && ($as != '')) {
				$qhtml .= str_replace('[answers]','<a class="answer-link" href="#a-question'.$q['postid'].'">Answer<a/>',$oneq);
				$onea = str_replace('[question-title]',$number.$mint.$q['title'].$titleright,qa_opt('book_plugin_template_question'));
				$onea = str_replace('[qanchor]','a-question'.$q['postid'],$onea);
				$onea = str_replace('[qurl]','#question'.$q['postid'],$onea);
				$onea = str_replace('[site-url]','',$onea);
				$onea = str_replace('[question]','',$onea);
				$onea = str_replace('[top-link]','question'.$q['postid'],$onea);
				$onea = str_replace('[tags]', '', $onea);
				$onea = str_replace('[hide]', 'hide', $onea);
				$answers .= str_replace('[answers]',$as,$onea);

			}
			else
				$qhtml .= str_replace('[answers]',$as,$oneq);
		}
		if(qa_opt('book_plugin_push_a')){
			$qhtml .= qa_book_answerblockprefix($oldmint,$answerblockprefix). $answers;
		}
		if(!$shuffle)
			$qhtml = str_replace("[zzzqcount]", $qcount, $qhtml); 

		if($iscats) {
			if(!$shuffle)
			{
				$toc = str_replace("[zzzqcount]", $qcount, $toc);
			}
			$tocout .= '<li><a href="#cat'.$cat['categoryid'].'" onclick="toggle(\'cat'.$cat['categoryid'].'Details\')" class="toc-cat">'.$cat['title'].'</a> <div class="cat-count">('.$cqcount.')'.'</div> <span id="cat'.$cat['categoryid'].'Details"> <ol class="toc-ul">'.$toc.'</ol></li>';

			// todo fix category link
			$catnumber="<div class=\"number\">$ccount</div>";
			//	echo print_r($navcats)."<br>".$cat['categoryid']."<br>";
			$catout = str_replace('[cat-url]',qa_path_html('questions/'.qa_category_path_request($navcats, $cat['categoryid'])),qa_opt('book_plugin_template_category'));
			$catout = str_replace('[cat-anchor]','cat'.$cat['categoryid'],$catout);
			$catout = str_replace('[cat-title]',$catnumber.' '.$cat['title'],$catout);
			$catout = str_replace('[cat-count]',$cqcount,$catout);
			$catout = str_replace('[questions]',$qhtml,$catout);
			$qout .= $catout;
		}
		else {
			if(!$shuffle)
				$tocout .= '<ol class="toc-ul">'.$toc.'</ol>';
			$catout = str_replace('[questions]',$qhtml,qa_opt('book_plugin_template_questions'));
			$qout .= $catout;
		}
	}	
	if($iscats)
		$tocout = '<ol class="toc-ul">'.$tocout.'</ol>';
	$book = str_replace('[intro]',$intro.$introsuffix,$book);
	$book = str_replace('[ack]',$ack,$book);

	// add toc and questions

	$book = str_replace('[toc]',$tocout,$book);
	$book = str_replace('[categories]',$qout,$book);

	// misc subs

	$book = str_replace('[site-title]',qa_opt('site_title'),$book);
	$book = str_replace('[site-url]',qa_opt('site_url'),$book);
	$book = str_replace('[date]',date('M j, Y'),$book);
//file_put_contents("/tmp/out.txt",json_encode($topic_array),FILE_APPEND);
file_put_contents("/tmp/out.txt",json_encode($topic_array));
print_r($topic_array);
	error_log('Q2A Book Created on '.date('M j, Y \a\t H\:i\:s'). ' for '.$selectspec);

	if($return){
		//qa_opt('book_plugin_refresh_last',time());
		return $book;
	}

	if(file_put_contents(qa_opt('book_plugin_loc'),$book)) {
		qa_opt('book_plugin_refresh_last',time());

	}

	if(qa_opt('book_plugin_pdf'))
		qa_book_plugin_create_pdf();


	return 'Book Created';

	//return 'Error creating '.qa_opt('book_plugin_loc').'; check the error log.';
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
		$pdf->output(WKPDF::$PDF_SAVEFILE,qa_opt('book_plugin_loc_pdf')); 

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


