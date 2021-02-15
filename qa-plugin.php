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

qa_register_plugin_phrases('qa-book-lang-*.php', 'book');

require 'util-book.php';
require_once QA_INCLUDE_DIR.'/app/format.php';

function code_gen($content, $type = 0)
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
			$link = '<img alt="" src = "'.qa_opt('site_url').'qa-plugin/q2a-book/images/'.$key.'">';
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
			$link = '<img alt="" src = "'.qa_opt('site_url').'qa-plugin/q2a-book/images/'.$key.'">';
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
				$link .= '<img alt="" src = "'.qa_opt('site_url').'qa-plugin/q2a-book/images/'.$key.'">';
			}
			//foreach($matches1 as $match)
			{
				//	$key=hash('sha1',$match[0]);
				//	QRcode::png($match[0], '/var/www/html/qa/qa-plugin/q2a-book/images/'.$key, 'L', 3, 3);
				//	$link .= '<img src = "'.qa_opt('site_url').'qa-plugin/q2a-book/images/'.$key.'">';
			}
			return $content."<div class=\"ref\">References</div>".$link;
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
function qa_book_getallcats(&$cats, $all=false)	{
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
	$globalquestioncount = 0;
	$globalanswercount = 0;
	$book = qa_opt('book_plugin_template');
	include_once QA_INCLUDE_DIR."../qa-plugin/q2a-book/phpqrcode/qrlib.php";

	// static replacements

	$extras='';
	if(qa_opt('qa-mathjax-enable'))
	{
		$extras.=  '<script  type="text/x-mathjax-config">'. qa_opt('qa-mathjax-config').'</script>';
		$extras .= '<script  async type="text/javascript" src="'.qa_opt('qa-mathjax-url').'"></script>';
	}
	if(qa_opt("qa-prettify-enable"))
	{
		$extras.='<script  async type="text/javascript" src="'.qa_opt('qa-pretiffy-url').'"></script>';
	}


	$bw = qa_get('bw');
	if(qa_get('md'))
	{
		$markdistribution = true;

	}
	else $markdistribution = false;
	if($bw)

		$book = str_replace('[css]',qa_opt('book_plugin_black_css'),$book);
	else	
		$book = str_replace('[css]',qa_opt('book_plugin_css'),$book);
	$book = str_replace('[script]',$extras,$book);
	$book = str_replace('[front]',qa_opt('book_plugin_template_front'),$book);
	$book = str_replace('[back]',qa_opt('book_plugin_template_back'),$book);			

	$shuffle = qa_opt('book_plugin_shuffle');
	$iscats = qa_opt('book_plugin_cats');
	$volume = qa_get('volume');
	$hideanswers = qa_get('hideanswers');
	$catinc = "";
	$cats = array(false);
	if($iscats) {
		if($volume)
			$navcats =  qa_book_getallcats($cats, true);
		else $navcats =  qa_book_getallcats($cats);
	}
	if($volume)
	{
		//$iscats = false;
		switch($volume)
		{
		case 1:
			$catinc = " and qs.categoryid in (13,26,27,28,29,30,31,32,33,35)";break;
			//$catinc = " and qs.categoryid in (30)";break;
		case 2:
			$catinc = " and qs.categoryid in (2,12,14,18,36,37)";break;
		case 3:
			$catinc = " and qs.categoryid in (15,16,17,19,22)";break;
		}
	}
	//$catinc = "";
	// categories


	// intro

	$intro = qa_opt('book_plugin_intro');
	$ack = qa_opt('book_plugin_ack');

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
	$quserarray = array();
	$auserarray = array();
	$euserarray = array();
	$ccount=0;
	$catanchor = '';
	$topic_array = array();// $qcontent; 
	foreach($cats as $cat) {
		$qcount=0;
		$incsql = '';
		$anssql = '';// and ans.type = \'A\' ';
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
			$anssql .= ' AND  (ans.postid < 0 OR  '; 
		}else $anssql .=' AND ( ';
		$sortsql.=',  ans.netvotes DESC'; // get all, limit later with break
		$allowemptyq = " ans.postid is null or ";
		//$allowemptyq = "";
		$wrongsql = ' ('.$allowemptyq.' ans.postid not in (select postid from ^postmetas where title like "wrong" and content = 1))) ';
		$selectspec="SELECT qs.postid AS postid, BINARY qs.title AS title, BINARY qs.content AS content, qs.format AS format, qs.netvotes AS netvotes, qs.tags as tags, qs.selchildid as selected, qs.userid as quserid, qs.lastuserid as qediter, ans.lastuserid as aeditor, ans.postid as apostid, BINARY ans.content AS acontent, ans.format AS aformat, ans.userid AS auserid, ans.netvotes AS anetvotes, pm.content as useful FROM ^posts  qs ".($reqanswers?"":"left outer join")." ^posts  ans on qs.postid=ans.parentid and qs.type='Q'  and qs.tags not like '%usermod%' and ans.type='A' left outer join ^postmetas pm on ans.postid=pm.postid and pm.title like 'useful' where true   ".($iscats?" AND qs.closedbyid is null and qs.type = 'Q' and  qs.categoryid=".$cat['categoryid']:"") ." ". $incsql." ".$catinc." ".$anssql." ".$wrongsql." ".$sortsql;
		//echo str_replace("^", "qa_", $selectspec);
		//exit;
		$qs = qa_db_read_all_assoc(
			qa_db_query_sub(
				$selectspec
			)
		);	

		if(empty($qs)) // no questions in this category
			continue;
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
					if(!$skipanswers && qa_opt('book_plugin_push_a') && ($answers != '')){
						//if(qa_opt('book_plugin_push_a') && ($answers != '')){
						//$answerblock = str_replace("[topic]",  gettag($oldmint), $answerblockprefix);
						//$answerblock = str_replace("[topicname]",  $oldmint, $answerblock);
						if(!$hideanswers)
						{
							$qhtml .=qa_book_answerblockprefix($oldmint,$answerblockprefix). $answers;
						}
						$answers='';
					}
					$tcount++;
					$topicanchor= $cat['categoryid'].'_topic_'.gettag($mint);
					$number="<span class=\"number\">".$ccount.".".$tcount."</span>";	
					//$topic = str_replace("[topic]", $number.' '.$mint.'('.$qtopiccount.')',  $topicblockprefix);	
					$qhtml = str_replace("[zzzqcount]", $qcount, $qhtml); 
					$topic = str_replace("[topic]", $number.' '.$mint.' ([zzzqcount])',  $topicblockprefix);	
					//$topic = str_replace("[topicurl]", gettag($mint),  $topic);	
					$topic = str_replace("[topicurl]", urlencode($mint),  $topic);
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
			$baid = 0;
			//echo $q['title']."<br>";
			foreach($qs as $idx => $q) {
				if($acount == 0 )
					$baid = $q['apostid'];
				if(qa_opt('book_plugin_req_abest') && qa_opt('book_plugin_req_abest_max') && $idx >= qa_opt('book_plugin_req_abest_max'))
					break;
				//	if($nv !== false  && qa_opt('book_plugin_req_abest') && $q['anetvotes'] < 20) // if a best answer add one more with at least 20 votes
				//if($nv !== false  && qa_opt('book_plugin_req_abest') && $q['useful'] == NULL) // if a best answer add one more with at least 20 votes
				if($nv !== false  && ($q['apostid']> 0) && qa_opt('book_plugin_req_abest') && $q['useful'] == NULL) // if a best answer add one more with at least 20 votes
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
				if($idx && ($q['apostid']>0) && ($q['anetvotes'] < qa_opt('book_plugin_req_av_no')))	
					break;
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
					echo "<a href='".qa_opt('site_url').$q['postid']."'>".qa_opt('site_url').$q['postid']."</a><br>";
					continue;
				}
				$acontent =code_gen($acontent, 1);
				$a = str_replace('[answer]',$acontent,qa_opt('book_plugin_template_answer'));
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
					$quserarray[$quserid]++;
				}
				if($q['qeditor'] !== NULL)	
				{
					$qeditor = qa_get_user_name($q['qeditor']);
					$qeditor = $q['qeditor'];
					$euserarray[$qeditor]++;
				}
				if($q['aeditor'] !== NULL)	
				{
					$aeditor = qa_get_user_name($q['aeditor']);
					$aeditor = $q['aeditor'];
					$euserarray[$aeditor]++;
				}
				if($q['auserid'] !== NULL)	
				{
					$auname = qa_get_user_name($q['auserid']);
					$auserid = $q['auserid'];
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
				$titleurl = "<a href=\"".qa_opt("site_url").$q['postid']."\">".qa_opt("site_url").$q['postid']."</a>";	
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
				$titleright="<div class=\"title-right\"><a href=\"".qa_opt("site_url").$q['postid']."\">".qa_opt("site_url").$q['postid']."</a></div>";
				//echo $titleright;//." ".$q['tags'];			
				//echo "<p>".$arrays[5]." <br>".$arrays[9]."<br> ".$arrays[13]."<br> ".$arrays[17]."</p>";
				$len1 = strlen(strip_latex($arrays[5]));
				$len2 = strlen(strip_latex($arrays[9]));
				$len3 = strlen(strip_latex($arrays[13]));
				$len4 = strlen(strip_latex($arrays[17]));
				//$len2 = strlen(strip_tags($arrays[9]));
				//$len3 = strlen(strip_tags($arrays[13]));
				//$len4 = strlen(strip_tags($arrays[17]));
				$len = $len1+$len2+$len3+$len4;
				$mlen = 20;
				$mlen2 = 50;
				if(!strpos($qcontent, "inline-options"))
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
				$titleright="<div class=\"title-right\"><a href=\"".qa_opt("site_url").$q['postid']."\">".qa_opt("site_url").$q['postid']."</a></div>";
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

				$tagshtml.="<li class=\"qa-q-view-tag-item\"> <a href=\"".qa_opt('site_url')."tag/".urlencode($tag)."\"    class=\"qa-tag-link\">".htmlentities($tag)." </a></li>";
			}
			if($mint !== '')
				$mint.=": ";
			$nnumber=$ccount.".".$tcount.".".$qcount;	
			$number="<span class=\"number\">".$nnumber."</span>";	
			$titleurl = "<a href=\"".qa_opt("site_url").$q['postid']."\">".qa_opt("site_url").$q['postid']."</a>";	
			$titleqr = code_gen($titleurl, 3);
			$titleright="<div class=\"title-right\">$titleurl</div>";
			$oneq = str_replace('[question-title]',$number.$mint.$q['title'],qa_opt('book_plugin_template_question'));
			$oneq = str_replace('[title-right]',$titleurl,$oneq);
			$oneq = str_replace('[question-qr]',$titleqr,$oneq);
			$oneq = str_replace('[qanchor]','question'.$q['postid'],$oneq);
			$oneq = str_replace('[qurl]',qa_html(qa_q_request($q['postid'],$q['title'])),$oneq);
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
			// output with answers  
			if($skipanswers)
				$qhtml .= str_replace('[answers]','',$oneq);
			else if(qa_opt('book_plugin_push_a') ) {
				$answerkeysql = "select answer_str from ^ec_answers where postid = #";
				$result = qa_db_query_sub($answerkeysql, $q['postid']);
				$row = qa_db_read_one_value($result, true);
				if(!empty($row))
				{
					$answerkey = strtoupper($row);
				}
				else//if(!$answerkey)
				{
					$answerkey = '<a href="'.qa_opt('site_url').$q['postid'].'" target="_blank">q</a>';
				}
				//echo $answerkey;
				$answerkeys[$nnumber]['postid'] = $q['postid'];
				$answerkeys[$nnumber]['apostid'] = $q['apostid'];
				$answerkeys[$nnumber]['key']	= $answerkey;
				if($as != '')
				{
				if(!$hideanswers)
				{
					$qhtml .= str_replace('[answers]','<a class="answer-link" href="#a-question'.$q['postid'].'">Answer</a>',$oneq);
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
				$titleurl = "<a href=\"".qa_opt("site_url").$q['postid']."#".$aid."\">".qa_opt("site_url").$q['postid']."</a>";	
				$titlear = code_gen($titleurl, 3);
				$onea = str_replace('[question-title]',$number.$mint.$q['title'],qa_opt('book_plugin_template_question'));
				$onea = str_replace('[title-right]',$titleurl,$onea);
				$onea = str_replace('[question-qr]',$titlear,$onea);
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
			else
				$qhtml .= str_replace('[answers]',$as,$oneq);
		}
		if(!$skipanswers && qa_opt('book_plugin_push_a')){
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
			$catout = str_replace('[cat-url]',qa_path_html('questions/'.qa_category_path_request($navcats, $cat['categoryid'])),qa_opt('book_plugin_template_category'));
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
			$catout = str_replace('[questions]',$qhtml,qa_opt('book_plugin_template_questions'));
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
	$book = str_replace('[site-url]',qa_opt('site_url'),$book);
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

	if(file_put_contents(qa_opt('book_plugin_loc'),$book)) 
	{
		qa_opt('book_plugin_refresh_last',time());

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
	error_log('Q2A Book Created on '.date('M j, Y \a\t H\:i\:s'). ' for '.$selectspec);
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

	return 'Book Created';
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
function buildanswerkeytable($answerkeys)
{
	$html = '<h2> Answer Keys</h2><table style="width:100%"> <tr>';
	$ccount = 0;
	$cmax = 5;
	foreach($answerkeys as $post => $postmeta)
	{
		$postid = $postmeta['postid'];
		$apostid = $postmeta['apostid'];
		$key = $postmeta['key'];
		//$html .="<td class='akt-td'><table style='width:100%'><tr><td class='akt-id'><a href='#question".$postid."'>$post</a></td><td class='akt-key'><a href='".qa_opt('site_url').$postid."#".$apostid."'>$key</a></td></tr></table></td>";
		$html .="<td class='akt-id'><a href='#question".$postid."'>$post</a></td><td class='akt-key'><a href='".qa_opt('site_url').$postid."#".$apostid."'>$key</a></td>";
		$ccount++;
		if($ccount == $cmax)
		{
			$html .='</tr><tr>';
			$ccount =0;
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


