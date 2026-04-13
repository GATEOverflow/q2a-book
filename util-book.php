<?php


 function qa_get_tag_count($tag){

                        $res = qa_db_query_sub("select tagcount from ^words where word like $ ", $tag);
                        return qa_db_read_one_value($res, true);

                }

function gettag($tagname)
{
	
   return strtolower(str_replace(" ", "-", $tagname));
}

 function mintag($question) {
                        $tags = qa_tagstring_to_tags($question['tags']);
			$min = 10000;
			$minneededcount=3;
			$mint='';
			$possibletag = false;
                        foreach ($tags as $tag)
                        {
                 
                                $tcount = qa_get_tag_count($tag);
                                if($tcount < $min && !ignoredtags($tag) && (!$possibletag  or ($tcount > $minneededcount))){
                                        $min=$tcount;
                                        $mint=ucwords(str_replace("-", " ", $tag));
                                }
			}
			$specialwords = array(
				" And", " To", " Of"
			);
			foreach($specialwords as $word) {
				$mint = str_replace($word, strtolower($word), $mint);
			}
			$specialwords = array(
			"Cisc", "Risc", "Co ", "Dram", "Dma", "Lr ", "Go ", "Ip ", "Lan ", "Mac ", "Csma Cd", "Csma ", "Crc ", "Tcp", "Udp", "Er ", "Sql", "Ieee ", "Lcm", "Hcf", "Rom", "Io ", "Os ","Avl ", "Np ", "Npc ", "Nph"
			);
			foreach($specialwords as $word) {
				$mint = str_replace($word, strtoupper($word), $mint);
			}
			$specialwords = array(
			"4nf","Pla"
			);
			foreach($specialwords as $word) {
				$offset = -strlen($word);
				//$mint = str_replace($word, strtoupper($word), $mint, $offset);
			}
                        return $mint;
                }



function skiptags($question) {
                        $tags = qa_tagstring_to_tags($question['tags']);
                        foreach ($tags as $tag)
                                //if($tag === 'descriptive' || $tag ==='proof' || $tag === 'out-of-syllabus-now')
                                if( $tag === 'out-of-syllabus-now')
                                        return true;
                        return false;
                }
                function ignoredtags($tag){
			$tags = qa_book_get('book_plugin_specialtags',true);
			if(trim($tags) && in_array($tag,explode(",", $tags)))
				return true;
			$extra_tags = qa_book_get('extra_filter_tags',true);
			if(trim($extra_tags) && in_array($tag,explode(",", $extra_tags)))
				return true;
                        if($tag === 'normal' || $tag === 'easy' || $tag === 'difficult' || $tag === 'numerical-answers' || $tag === 'descriptive' || $tag ==='debated' ||$tag ==='algorithms' ||$tag==='marks-to-all' ||$tag === 'co&architecture' ||$tag ==='made-easy' || $tag ==='databases' ||$tag === 'set-theory&algebra' || $tag ==='mathematical-logic' || $tag ==='ds' ||$tag==='theory-of-computation' ||$tag==='out-of-sylabus' ||$tag === 'compiler-design' ||$tag ==='linear-algebra'  ||$tag === 'engineering-mathematics' || $tag ==='graph-theory' || $tag==='calculus' ||$tag==='operating-system' ||$tag ==='computer-networks' ||$tag ==='digital-logic' ||$tag ==='isro' ||$tag ==='verbal-ability' ||$tag ==='numerical-ability' ||$tag==='programming' ||$tag==='non-gate' ||$tag ==='data-structure' ||$tag ==='aptitude' ||$tag ==='proof' ||$tag ==='fortran' ||$tag ==='8085' ||$tag ==='8086'||$tag==='out-of-syllabus-now' ||$tag ==='2015' ||$tag ==='test-series' ||$tag ==='php' || $tag==='1-mark' || $tag === '2-marks'){
                        return true;
                }
                        if(strpos($tag, "interview") === true)
                                return true;
                        if(strpos($tag, "test-series") === true)
                                return true;
                        if(!strncmp($tag, "cat", 3))
                                return true;
                        if(!strncmp($tag, "gate", 4))
                                return true;
                        if(!strncmp($tag, "tifr", 4))
                                return true;
                        if(!strncmp($tag, "ugc", 3))
                                return true;
                        if(!strncmp($tag, "isi", 3))
                                return true;
                        if(!strncmp($tag, "drdo", 4))
                                return true;
                        if(!strncmp($tag, "isro", 4))
                                return true;
                        if(!strncmp($tag, "cmi", 3))
                                return true;
                        if(!strncmp($tag, "barc", 4))
                                return true;
                        if(!strncmp($tag, "navathe", 7))
                                return true;
                        if(!strncmp($tag, "nielit", 6))
                                return true;
                        if(!strncmp($tag, "goclasses", 9))
                                return true;
                        if(!strncmp($tag, "go2", 3))
                                return true;
                        return false;
                }
                function mysortanswers($a, $b) {
                        if($a['selected'] === $a['apostid'])
                                return -1;
                        if($b['selected'] === $b['apostid'] )
                                return 1;

                        return $b['anetvotes'] - $a['anetvotes'];

                }
                function mysorttitle($c, $d){
                        $a=$c[0];
                        $b=$d[0];
			return !strcmp($a['title'], $b['title']);
		}
                function mysort($c, $d){
                        $a=$c[0];
                        $b=$d[0];

                        $mint=mintag($a);
                        $mintb=mintag($b);
                        if($mint === '' && $mintb === '')
				return !strcmp($a['title'], $b['title']);
                                //return 0;
                        if($mint === '')
                                return -1;
                        if($mintb === '')
                                return 1;
                        if (strcmp($mint, $mintb) == 0)
			{
				return strcmp($a['title'], $b['title']);
			}
                        return strcmp($mint, $mintb);
                }
?>
