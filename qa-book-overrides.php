<?php
		
	function qa_get_request_content() {
		$requestlower=strtolower(qa_request());
		if($requestlower && $requestlower === qa_opt('book_plugin_request')) {
			if(qa_opt('book_plugin_static'))
				include(qa_opt('book_plugin_loc'));
			else
				echo qa_book_plugin_createBook(true);
			return false;
		}
		else if($requestlower && $requestlower === qa_opt('book_plugin_request_pdf')) {
			if(qa_opt('book_plugin_static')) {
				$pdf = file_get_contents(qa_opt('book_plugin_loc_pdf'));
				header('Content-Description: File Transfer');
				header('Cache-Control: public, must-revalidate, max-age=0'); // HTTP/1.1
				header('Pragma: public');
				header('Expires: Sat, 26 Jul 1997 05:00:00 GMT'); // Date in the past
				header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
				// force download dialog
				header('Content-Type: application/force-download');
				header('Content-Type: application/octet-stream', false);
				header('Content-Type: application/download', false);
				header('Content-Type: application/pdf', false);
				// use the Content-Disposition header to supply a recommended filename
				header('Content-Disposition: attachment; filename="'.basename(qa_opt('book_plugin_loc_pdf')).'";');
				header('Content-Transfer-Encoding: binary');
				header('Content-Length: '.strlen($pdf));
				echo $pdf;
			}
			else
				qa_book_plugin_create_pdf(true);
			return false;
		}
		return qa_get_request_content_base();
	}
						
/*							  
		Omit PHP closing tag to help avoid accidental output
*/							  
						  

