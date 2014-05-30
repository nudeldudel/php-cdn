<?php

// php-cdn
// dynamic file caching pseudo cdn
/////////////////////////////////////////////////////////////////////////
// cdn root path   : http://cdn.com/
// cdn example url : http://cdn.com/path/to/resource.css?d=12345
// maps the uri    : /path/to/resource.css?d=12345
// to the origin   : http://yoursite.com/path/to/resource.css?d=12345
// caches file to  : ./cache/[base64-encoded-uri].css
// returns local cached copy or issues 304 not modified
/////////////////////////////////////////////////////////////////////////
// error_reporting(E_ERROR | E_PARSE);


// the source that we intend to mirror
$f_origin = 'http://cdn.com';

// encode as filename-safe base64
$f_name = strtr(base64_encode($_SERVER['REQUEST_URI']), '+/=', '-_,');

// parse the file extension
$f_ext = strrchr(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '.');


function send_file($f_path)
{
	
	$header = file_get_contents($f_path . ".header");
	$header_list = explode("\r\n", $header);
	foreach ($header_list as &$elem) {
		if(strpos(strtolower($elem), "transfer-encoding:")===0)
		{
			continue;
		}
		header($elem);
	}
	
	if(strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false 
	   && file_exists($f_path . ".gz") )
	{
		header("Content-Encoding: gzip");
		readfile($f_path . ".gz");
	}
	else
	{
		// stream the file
		readfile($f_path);
	}
}

// construct usable file path
$f_path = "./cache/{$f_name}{$f_ext}";

// check the local cache
if (file_exists($f_path)) {
	// get last modified time
	$f_modified = filemtime($f_path);
	
	// validate the client cache
	if (isset(    $_SERVER['HTTP_IF_MODIFIED_SINCE']) && 
	   (strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) == $f_modified)
	) {
		// client has a valid cache, issue *304*
		header($_SERVER['SERVER_PROTOCOL'] . ' 304 Not Modified');
	} else {
		send_file($f_path);
	}
} else {
	// http *HEAD* request 
	// verify that the image exists
	$ch = curl_init();
	curl_setopt_array($ch, 	array(
		CURLOPT_URL            => $f_origin . $_SERVER['REQUEST_URI'],
		CURLOPT_TIMEOUT        => 15,
		CURLOPT_CONNECTTIMEOUT => 15,
		CURLOPT_FAILONERROR    => 1,
		CURLOPT_RETURNTRANSFER => 1,
		CURLOPT_BINARYTRANSFER => 1,
		CURLOPT_HEADER         => 0,
		CURLOPT_NOBODY         => 1,
		// CURLOPT_FOLLOWLOCATION => 1, 
	));
	
	// we have located the remote file
	if (curl_exec($ch) !== false) {
		$fp = fopen($f_path, 'a+b');
		$file_ok = false;
		if(flock($fp, LOCK_EX | LOCK_NB)) {
			// empty *possible* contents
			ftruncate($fp, 0);
			rewind($fp);

			// http *GET* request
			// and write directly to the file
			$ch2 = curl_init();
			curl_setopt_array($ch2, 	array(
				CURLOPT_URL            => $f_origin . $_SERVER['REQUEST_URI'],
				CURLOPT_TIMEOUT        => 15,
				CURLOPT_CONNECTTIMEOUT => 15,
				CURLOPT_FAILONERROR    => 1,
				CURLOPT_RETURNTRANSFER => 1,
				CURLOPT_BINARYTRANSFER => 1,
				CURLOPT_HEADER         => 1,
				CURLOPT_RETURNTRANSFER => 1,
				CURLOPT_FOLLOWLOCATION => 1, 
			));
				
			$response = curl_exec($ch2);
			
			// did the transfer complete?
			if ( $response === false) {
				// something went wrong, null 
				// the file just in case >.>
				ftruncate($fp, 0); 
			}
			else
			{
				list($header, $body) = explode("\r\n\r\n", $response, 2);
				fwrite($fp, $body);
				$fp_header = fopen($f_path . ".header", 'w');
				fwrite($fp_header, $header);
				fclose($fp_header);
				$file_ok = true;
				
				if($f_ext == ".css" || $f_ext == ".js")
				{
					$fp_gzipped = fopen($f_path . ".gz", 'w');
					fwrite($fp_gzipped, gzencode($body));
					fclose($fp_gzipped);
				}
			}
				
			// 1) flush output to the file
			// 2) release the file lock
			// 3) release the curl socket
			fflush($fp);
			flock($fp, LOCK_UN);
			curl_close($ch2);
		}
				
		// close the file
		fclose($fp);
		
		if($file_ok)
		{
			send_file($f_path);
		}
		else
		{
			// issue *302* for *this* request
			header('Location: ' . $f_origin . $_SERVER['REQUEST_URI']);
		}
		
	} else {
		// the file doesn't exist, issue *404*
		header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
		header('Cache-Control: private');
	}
	
	// finished
	curl_close($ch);
}

?>