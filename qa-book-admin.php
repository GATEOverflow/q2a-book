<?php
class qa_book_admin {


	public function init_queries($tablescreated)
	{
		$queries = [];

		if (!in_array(qa_db_add_table_prefix('book_options'), $tablescreated)) {
			$queries[] = 'CREATE TABLE IF NOT EXISTS ^book_options (' .
                        'title VARCHAR(40) NOT NULL PRIMARY KEY,' .
                        'content VARCHAR(16000) DEFAULT NULL' .
                        ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
		}

		return $queries;
	}

	function option_default($option) {

		switch($option) {
		case 'book_plugin_sort_q':
			return 0;
		case 'book_plugin_inc':
			return 0;
		case 'book_plugin_req_qv_no':
			return 2;
		case 'book_plugin_req_av_no':
			return 2;
		case 'book_plugin_enable_custom_filter_1':
			return 0;
		case 'book_plugin_enable_custom_filter_2':
			return 0;
		case 'book_plugin_enable_custom_filter_3':
			return 0;
		case 'book_plugin_enable_custom_filter_4':
			return 0;
		case 'book_plugin_enable_custom_filter_5':
			return 0;
		case 'book_plugin_custom_filter_1':
			return "where qs.tags like 'gate%'";
		case 'book_plugin_custom_filter_2':
			return "where qs.tags like 'gate%'";
		case 'book_plugin_custom_filter_3':
			return "where qs.tags like 'gate%'";
		case 'book_plugin_custom_filter_4':
			return "where qs.tags like 'gate%'";
		case 'book_plugin_custom_filter_5':
			return "where qs.tags like 'gate%'";
		case 'book_plugin_custom_filter_6':
			return "where qs.tags like 'gate%'";
		case 'book_plugin_custom_filter_7':
			return "where qs.tags like 'gate%'";
		case 'book_plugin_custom_filter_8':
			return "where qs.tags like 'gate%'";
		case 'book_plugin_custom_filter_9':
			return "where qs.tags like 'gate%'";
		case 'book_plugin_custom_filter_10':
			return "where qs.tags like 'gate%'";
		case 'book_plugin_custom_filter_1_desc':
			return "The questions included are restricted to these conditions";
		case 'book_plugin_custom_filter_2_desc':
			return "The questions included are restricted to these conditions";
		case 'book_plugin_custom_filter_3_desc':
			return "The questions included are restricted to these conditions";
		case 'book_plugin_custom_filter_4_desc':
			return "The questions included are restricted to these conditions";
		case 'book_plugin_custom_filter_5_desc':
			return "The questions included are restricted to these conditions";
		case 'book_plugin_custom_filter_6_desc':
			return "The questions included are restricted to these conditions";
		case 'book_plugin_custom_filter_7_desc':
			return "The questions included are restricted to these conditions";
		case 'book_plugin_custom_filter_8_desc':
			return "The questions included are restricted to these conditions";
		case 'book_plugin_custom_filter_9_desc':
			return "The questions included are restricted to these conditions";
		case 'book_plugin_custom_filter_10_desc':
			return "The questions included are restricted to these conditions";
		case 'book_plugin_refresh_last':
			return time();
		case 'book_plugin_refresh_hours':
			return 24;
		case 'book_plugin_loc':
			return dirname(__FILE__).'/book.html';
		case 'book_plugin_loc_pdf':
			return dirname(__FILE__).'/book.pdf';
		case 'book_plugin_request':
			return 'book';
		case 'book_plugin_request_pdf':
			return 'book.pdf';
		case 'book_plugin_show_a':
			return '1';
		case 'book_plugin_intro':
			return 'This book was created programatically by [site-title] on [date].';
		case 'book_plugin_ack':
			return 'Thanks to all the contributors of [site-title].';
		case 'book_plugin_css':
			return file_get_contents(dirname(__FILE__).'/book.css');
		case 'book_plugin_black_css':
			return file_get_contents(dirname(__FILE__).'/book.css');
		case 'book_plugin_template':
			return file_get_contents(dirname(__FILE__).'/template.html');
		case 'book_plugin_template_front':
			return file_get_contents(dirname(__FILE__).'/front.html');
		case 'book_plugin_template_toc':
			return file_get_contents(dirname(__FILE__).'/toc.html');
		case 'book_plugin_template_back':
			return file_get_contents(dirname(__FILE__).'/back.html');
		case 'book_plugin_template_category':
			return file_get_contents(dirname(__FILE__).'/category.html');
		case 'book_plugin_template_questions':
			return file_get_contents(dirname(__FILE__).'/questions.html');
		case 'book_plugin_template_question':
			return file_get_contents(dirname(__FILE__).'/question.html');
		case 'book_plugin_template_answer':
			return file_get_contents(dirname(__FILE__).'/answer.html');
		default:
			return null;				
		}

	}

	function allow_template($template)
	{
		return ($template!='admin');
	}	   

	function getcategoryoptions($selected)
	{
		$cats = array();
		$option='<option value="0">Select</option>';
		$navcats = qa_book_getallcats($cats, true);
		foreach($cats as $cat)
		{
			$option .= '<option '.(in_array($cat['categoryid'], $selected)? 'selected': '').' value="'.$cat['categoryid'].'">'.$cat['title'].'</option>';
		}
		return $option;
	}

	function admin_form(&$qa_content)
	{					   
		// Process form input

		$ok = null;

		if (qa_clicked('book_plugin_process') || qa_clicked('book_plugin_save')) {

			qa_opt('book_plugin_active',(bool)qa_post_text('book_plugin_active'));
			qa_book_set('book_plugin_shuffle',(bool)qa_post_text('book_plugin_shuffle'));

			qa_book_set('book_plugin_cats',(bool)qa_post_text('book_plugin_cats'));
			qa_book_set('book_plugin_catex', implode(",", $_POST['book_plugin_catex']));
			for($i=1; $i <= 10; $i++) {
				qa_book_set('book_plugin_enable_custom_filter'.$i,(bool)qa_post_text('book_plugin_enable_custom_filter'.$i), true);
				qa_book_set('book_plugin_custom_filter'.$i,qa_post_text('book_plugin_custom_filter'.$i), true);
				qa_book_set('book_plugin_custom_filter'.$i.'_desc',qa_post_text('book_plugin_custom_filter'.$i.'_desc'), true);
			}
			qa_book_set('book_plugin_sort_q',(int)qa_post_text('book_plugin_sort_q'));

			qa_book_set('book_plugin_show_a',(bool)qa_post_text('book_plugin_show_a'));
			qa_book_set('book_plugin_push_a',(bool)qa_post_text('book_plugin_push_a'));

			qa_book_set('book_plugin_req_sel',(bool)qa_post_text('book_plugin_req_sel'));
			qa_book_set('book_plugin_req_ans',(bool)qa_post_text('book_plugin_req_ans'));
			qa_book_set('book_plugin_req_abest',(bool)qa_post_text('book_plugin_req_abest'));
			qa_book_set('book_plugin_req_abest_max',(int)qa_post_text('book_plugin_req_abest_max'));
			qa_book_set('book_plugin_req_qv',(bool)qa_post_text('book_plugin_req_qv'));
			qa_book_set('book_plugin_req_av',(bool)qa_post_text('book_plugin_req_av'));

			qa_book_set('book_plugin_req_qv_no',(int)qa_post_text('book_plugin_req_qv_no'));
			qa_book_set('book_plugin_req_av_no',(int)qa_post_text('book_plugin_req_av_no'));

			qa_book_set('book_plugin_static',(bool)qa_post_text('book_plugin_static'));
			qa_book_set('book_plugin_pdf',(bool)qa_post_text('book_plugin_pdf'));
			qa_book_set('book_plugin_loc',qa_post_text('book_plugin_loc'));
			qa_book_set('book_plugin_loc_pdf',qa_post_text('book_plugin_loc_pdf'));

			qa_book_set('book_plugin_refresh',(bool)qa_post_text('book_plugin_refresh'));
			qa_book_set('book_plugin_refresh_time',(bool)qa_post_text('book_plugin_refresh_time'));
			qa_book_set('book_plugin_refresh_cron',(bool)qa_post_text('book_plugin_refresh_cron'));
			qa_book_set('book_plugin_refresh_hours',(int)qa_post_text('book_plugin_refresh_hours'));


			qa_book_set('book_plugin_request',qa_post_text('book_plugin_request'));
			qa_book_set('book_plugin_request_pdf',qa_post_text('book_plugin_request_pdf'));

			qa_book_set('book_plugin_prefix',(bool)qa_post_text('book_plugin_prefix'));
			qa_book_set('book_plugin_specialtags',qa_post_text('book_plugin_specialtags'));

			qa_book_set('book_plugin_css',qa_post_text('book_plugin_css'));
			qa_book_set('book_plugin_black_css',qa_post_text('book_plugin_black_css'));

			qa_book_set('book_plugin_ack',qa_post_text('book_plugin_ack'));
			qa_book_set('book_plugin_intro',qa_post_text('book_plugin_intro'));

			qa_book_set('book_plugin_template',qa_post_text('book_plugin_template'));
			qa_book_set('book_plugin_template_front',qa_post_text('book_plugin_template_front'));
			qa_book_set('book_plugin_template_back',qa_post_text('book_plugin_template_back'));
			qa_book_set('book_plugin_template_toc',qa_post_text('book_plugin_template_toc'));
			qa_book_set('book_plugin_template_category',qa_post_text('book_plugin_template_category'));
			qa_book_set('book_plugin_template_questions',qa_post_text('book_plugin_template_questions'));
			qa_book_set('book_plugin_template_question',qa_post_text('book_plugin_template_question'));
			qa_book_set('book_plugin_template_answer',qa_post_text('book_plugin_template_answer'));

			if(qa_clicked('book_plugin_process') && qa_opt('book_plugin_static'))
				$ok = qa_book_plugin_createBook();
			else
				$ok = qa_lang('admin/options_saved');
		}
		else if (qa_clicked('book_plugin_reset')) {
			foreach($_POST as $i => $v) {
				$def = $this->option_default($i);
				if($def !== null) qa_book_set($i,$def);
			}
			$ok = qa_lang('admin/options_reset');
		} 
		// Create the form for display

		$fields = array();

		$fields[] = array(
			'label' => 'Activate Plugin',
			'tags' => 'NAME="book_plugin_active"',
			'value' => qa_opt('book_plugin_active'),
			'type' => 'checkbox',
		);
		$fields[] = array(
			'label' => 'Shuffle Questions',
			'tags' => 'NAME="book_plugin_shuffle"',
			'value' => qa_book_get('book_plugin_shuffle'),
			'type' => 'checkbox',
		);
		$fields[] = array(
			'type' => 'blank',
		);
		for($i = 1; $i <= 10; $i++) {
			$fields[] = array(
				'label' => 'Enable Custom Filter '.$i,
				'tags' => 'NAME="book_plugin_enable_custom_filter'.$i.'" onchange="if(this.checked) $(\'#book_plugin_custom_filter'.$i.'_div\').show(); else $(\'#book_plugin_custom_filter'.$i.'_div\').hide();"',
				'value' => qa_book_get('book_plugin_enable_custom_filter'.$i, true),
				'type' => 'checkbox',
			);
			$fields[] = array(
				'value' => '<div style="display:'.(qa_book_get('book_plugin_enable_custom_filter'.$i,true)?'block':'none').'" id="book_plugin_custom_filter'.$i.'_div"><i>Custom query filter'.$i.' string</i><br/><input name="book_plugin_custom_filter'.$i.'" id="book_plugin_custom_filter'.$i.'" value="'.qa_book_get('book_plugin_custom_filter'.$i, true).'">
				<br/><i>Text to be added in Book</i><br/><textarea name="book_plugin_custom_filter'.$i.'_desc"  rows="10" id="book_plugin_custom_filter'.$i.'_desc">'.qa_book_get('book_plugin_custom_filter'.$i.'_desc', true).'</textarea>
				</div>',
			'type' => 'static',
			);
		}


		$fields[] = array(
			'label' => 'Sort By Categories',
			'tags' => 'onchange="if(this.checked) $(\'#book_plugin_cat_div\').show(); else $(\'#book_plugin_cat_div\').hide();" NAME="book_plugin_cats"',
			'value' => qa_book_get('book_plugin_cats'),
			'type' => 'checkbox',
		);

		$exccat = $this->getcategoryoptions(explode(",",qa_book_get('book_plugin_catex')));	
		$fields[] = array(
			'value' => '<span style="display:'.(qa_book_get('book_plugin_cats')?'block':'none').'" id="book_plugin_cat_div"><i>Categories to exclude (multi-select with CTRL/CMD):</i><br/><select multiple name="book_plugin_catex[]" id="book_plugin_catex>" '.$exccat.'"</select></span>',
			'type' => 'static',
			'note' => 'Selecting a parent category excludes all child categories',
		);

		$sort = array(
			'votes',
			'date',
		);

		$fields[] = array(
			'id' => 'book_plugin_sort_q',
			'label' => 'Sort questions by',
			'tags' => 'NAME="book_plugin_sort_q" ID="book_plugin_sort_q"',
			'type' => 'select',
			'options' => $sort,
			'value' => @$sort[qa_book_get('book_plugin_sort_q')],
		);
		$fields[] = array(
			'label' => 'Include Answers',
			'tags' => 'NAME="book_plugin_show_a"',
			'value' => qa_book_get('book_plugin_show_a'),
			'type' => 'checkbox',
		);
		$fields[] = array(
			'label' => 'Push Answers',
			'note' => 'Moves answers from below question to the end of the Topic/Category',
			'tags' => 'NAME="book_plugin_push_a"',
			'value' => qa_book_get('book_plugin_push_a'),
			'type' => 'checkbox',
		);

		$fields[] = array(
			'type' => 'blank',
		);

		$fields[] = array(
			'value' => '<b>Restrict inclusion to:</b>',
			'type' => 'static',
		);

		$fields[] = array(
			'label' => 'Selected answers',
			'tags' => 'NAME="book_plugin_req_sel"',
			'value' => qa_book_get('book_plugin_req_sel'),
			'type' => 'checkbox',
		);
		$fields[] = array(
			'label' => 'Answered Questions',
			'tags' => 'NAME="book_plugin_req_ans"',
			'value' => qa_book_get('book_plugin_req_ans'),
			'type' => 'checkbox',
		);

		$fields[] = array(
			'label' => 'Highest voted answers',
			'tags' => 'onclick="if(this.checked) $(\'#book_plugin_req_abest_max_div\').show(); else $(\'#book_plugin_req_abest_max_div\').hide();" NAME="book_plugin_req_abest"',
			'value' => qa_book_get('book_plugin_req_abest'),
			'type' => 'checkbox',
		);
		$fields[] = array(
			'value' => '<span id="book_plugin_req_abest_max_div" style="display:'.(qa_book_get('book_plugin_req_abest')?'block':'none').'">max number of answers to include: <input name="book_plugin_req_abest_max" size="3" value="'.(qa_book_get('book_plugin_req_abest_max')?qa_book_get('book_plugin_req_abest_max'):'').'"></span>',
			'type' => 'static',
		);

		$fields[] = array(
			'label' => 'Questions with minimum votes',
			'tags' => 'onclick="if(this.checked) $(\'#book_plugin_req_qv_div\').show(); else $(\'#book_plugin_req_qv_div\').hide();" NAME="book_plugin_req_qv"',
			'value' => qa_book_get('book_plugin_req_qv'),
			'type' => 'checkbox',
		);
		$fields[] = array(
			'value' => '<span id="book_plugin_req_qv_div" style="display:'.(qa_book_get('book_plugin_req_qv')?'block':'none').'">min. votes for inclusion: <input name="book_plugin_req_qv_no" size="3" value="'.qa_book_get('book_plugin_req_qv_no').'"></span>',
			'type' => 'static',
		);

		$fields[] = array(
			'label' => 'Answers with minimum votes',
			'tags' => 'onclick="if(this.checked) $(\'#book_plugin_req_av_div\').show(); else $(\'#book_plugin_req_av_div\').hide();" NAME="book_plugin_req_av"',
			'value' => qa_book_get('book_plugin_req_av'),
			'type' => 'checkbox',
		);
		$fields[] = array(
			'value' => '<span id="book_plugin_req_av_div" style="display:'.(qa_book_get('book_plugin_req_av')?'block':'none').'">min. votes for inclusion: <input name="book_plugin_req_av_no" size="3" value="'.qa_book_get('book_plugin_req_av_no').'"></span>',
			'type' => 'static',
		);

		$fields[] = array(
			'type' => 'blank',
		);

		$fields[] = array(
			'label' => 'Create Static Book',
			'note' => '<i>if this is unchecked, accessing the book page will recreate the book on every view</i>',
			'tags' => 'onclick="if(this.checked) $(\'#book_plugin_loc\').show(); else $(\'#book_plugin_loc\').hide();" NAME="book_plugin_static"',
			'value' => qa_book_get('book_plugin_static'),
			'type' => 'checkbox',
		);
		$fields[] = array(
			'value' => '<span id="book_plugin_loc" style="display:'.(qa_book_get('book_plugin_static')?'block':'none').'">Location (must be writable): <input name="book_plugin_loc" value="'.qa_book_get('book_plugin_loc').'"></span>',
			'type' => 'static',
		);
		$fields[] = array(
			'type' => 'blank',
		);

		$fields[] = array(
			'label' => 'Create Static PDF',
			'note' => '<i>requires wkhtmltopdf - see README.rst</i>',
			'tags' => 'onclick="if(this.checked) $(\'#book_plugin_loc_pdf\').show(); else $(\'#book_plugin_loc_pdf\').hide();" NAME="book_plugin_pdf"',
			'value' => qa_book_get('book_plugin_pdf'),
			'type' => 'checkbox',
		);
		$fields[] = array(
			'value' => '<span id="book_plugin_loc_pdf" style="display:'.(qa_book_get('book_plugin_pdf')?'block':'none').'">Location (must be writable): <input name="book_plugin_loc_pdf" value="'.qa_book_get('book_plugin_loc_pdf').'"></span>',
			'type' => 'static',
		);
		$fields[] = array(
			'type' => 'blank',
		);
		$fields[] = array(
			'label' => 'Recreate Static Book',
			'tags' => 'onclick="if(this.checked) $(\'#book_plugin_refresh_hours\').show(); else $(\'#book_plugin_refresh_hours\').hide();" NAME="book_plugin_refresh"',
			'value' => qa_book_get('book_plugin_refresh'),
			'type' => 'checkbox',
		);

		$cron_url = qa_opt('site_url').qa_book_get('book_plugin_request').'?cron=true';

		$fields[] = array(
			'value' => '<div id="book_plugin_refresh_hours" style="display:'.(qa_book_get('book_plugin_refresh')?'block':'none').'">minimum time to recreate:&nbsp;<input name="book_plugin_refresh_hours" value="'.qa_book_get('book_plugin_refresh_hours').'" size="3">&nbsp;hours<br/><i>if this is set to zero, the auto-recreate will not run, and the cron url may be called at any time.<br/><br/><input type="checkbox" name="book_plugin_refresh_time" '.(qa_book_get('book_plugin_refresh_time')?'checked':'').'> recreate on next access after above interval<br/><br/><input type="checkbox" name="book_plugin_refresh_cron" '.(qa_book_get('book_plugin_refresh_cron')?'checked':'').'>recreate via cron url below<br/><span style="font-style:italic;">url is currently <a href="'.$cron_url.'">'.$cron_url.'</a></span></div>',
			'type' => 'static',
		);
		$fields[] = array(
			'type' => 'blank',
		);

		$fields[] = array(
			'label' => 'Book Permalink',
			'note' => '<i>the url used to access the book, either via static file, or on the fly</i>',
			'tags' => 'NAME="book_plugin_request"',
			'value' => qa_book_get('book_plugin_request'),
		);
		$fields[] = array(
			'label' => 'Prefix Tag to Question Title',
			'tags' => 'onclick="if(this.checked) $(\'#book_plugin_specialtags_div\').show(); else $(\'#book_plugin_specialtags_div\').hide();" NAME="book_plugin_prefix"',
			'note' => 'Prefixes the most lightly used tag in tagstring to the Question title',	
			'value' => qa_book_get('book_plugin_prefix'),
			'type' => 'checkbox',
		);
		$fields[] = array(
			'tags' => 'NAME="book_plugin_specialtags"',
			'value' => '<div id="book_plugin_specialtags_div" style="display:'.(qa_book_get('book_plugin_prefix')?'block':'none').'"> 
			<p><i>Special Tags (comma separated)</i></p>
			<textarea rows="10" name="book_plugin_specialtags">'. qa_book_get('book_plugin_specialtags').'</textarea>
			<p><i>These tagnames won\'t be considered for Adding to Question Title Prefix</i></p>
			</div>',
		'type' => 'static',
		);

		$fields[] = array(
			'label' => 'Book PDF Permalink',
			'note' => '<i>the url used to access the PDF file; should correspond with static PDF location above</i>',
			'tags' => 'NAME="book_plugin_request_pdf"',
			'value' => qa_book_get('book_plugin_request_pdf'),
		);
		$fields[] = array(
			'type' => 'blank',
		);

		$fields[] = array(
			'label' => 'Book CSS',
			'note' => '<i>book.css</i>',
			'tags' => 'NAME="book_plugin_css"',
			'value' => qa_book_get('book_plugin_css'),
			'type' => 'textarea',
			'rows' => '10',
		);
		$fields[] = array(
			'label' => 'Book monochrome CSS',
			'note' => '<i>book.css</i>',
			'tags' => 'NAME="book_plugin_black_css"',
			'value' => qa_book_get('book_plugin_black_css'),
			'type' => 'textarea',
			'rows' => '10',
		);

		$fields[] = array(
			'type' => 'blank',
		);

		$fields[] = array(
			'label' => 'Book Template',
			'note' => '<i>template.html</i>',
			'tags' => 'NAME="book_plugin_template"',
			'value' => qa_book_get('book_plugin_template'),
			'type' => 'textarea',
			'rows' => '10',
		);
		$fields[] = array(
			'label' => 'Front Cover Template',
			'note' => '<i>front.html</i>',
			'tags' => 'NAME="book_plugin_template_front"',
			'value' => qa_book_get('book_plugin_template_front'),
			'type' => 'textarea',
			'rows' => '10',
		);
		$fields[] = array(
			'label' => 'Back Cover Template',
			'note' => '<i>back.html</i>',
			'tags' => 'NAME="book_plugin_template_back"',
			'value' => qa_book_get('book_plugin_template_back'),
			'type' => 'textarea',
			'rows' => '10',
		);
		$fields[] = array(
			'label' => 'Introduction',
			'tags' => 'NAME="book_plugin_intro"',
			'value' => qa_book_get('book_plugin_intro'),
			'type' => 'textarea',
			'rows' => '10',
		);
		$fields[] = array(
			'label' => 'Acknowledgement',
			'tags' => 'NAME="book_plugin_ack"',
			'value' => qa_book_get('book_plugin_ack'),
			'type' => 'textarea',
			'rows' => '20',
		);
		$fields[] = array(
			'label' => 'Table of Contents Template',
			'note' => '<i>toc.html</i>',
			'tags' => 'NAME="book_plugin_template_toc"',
			'value' => qa_book_get('book_plugin_template_toc'),
			'type' => 'textarea',
			'rows' => '10',
		);
		$fields[] = array(
			'label' => 'Category Template',
			'note' => '<i>category.html - used when sorting by categories</i>',
			'tags' => 'NAME="book_plugin_template_category"',
			'value' => qa_book_get('book_plugin_template_category'),
			'type' => 'textarea',
			'rows' => '10',
		);
		$fields[] = array(
			'label' => 'Questions Template',
			'note' => '<i>questions.html - used when not sorting by categories</i>',
			'tags' => 'NAME="book_plugin_template_questions"',
			'value' => qa_book_get('book_plugin_template_questions'),
			'type' => 'textarea',
			'rows' => '10',
		);
		$fields[] = array(
			'label' => 'Question Template',
			'note' => '<i>question.html</i>',
			'tags' => 'NAME="book_plugin_template_question"',
			'value' => qa_book_get('book_plugin_template_question'),
			'type' => 'textarea',
			'rows' => '10',
		);
		$fields[] = array(
			'label' => 'Answer Template',
			'note' => '<i>answer.html</i>',
			'tags' => 'NAME="book_plugin_template_answer"',
			'value' => qa_book_get('book_plugin_template_answer'),
			'type' => 'textarea',
			'rows' => '10',
		);

		return array(		   
			'ok' => ($ok && !isset($error)) ? $ok : null,

			'fields' => $fields,

			'buttons' => array(
				array(
					'label' => qa_lang_html('admin/save_options_button'),
					'tags' => 'NAME="book_plugin_save"',
				),
				array(
					'label' => 'Process',
					'tags' => 'NAME="book_plugin_process"',
				),
				array(
					'label' => qa_lang_html('admin/reset_options_button'),
					'tags' => 'NAME="book_plugin_reset"',
				),
			),
		);
	}
}

