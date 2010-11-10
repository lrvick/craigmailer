<?php // CraigMailer v0.9
$log_dir        = '/home/rvick/projects/craigmailer/';  // directory where logs should be stored
$host           = "ssl://smtp.gmail.com";               // smtp server
$port           = "465";                                // smtp port
$username       = "lance@reaganvick.com";               // smtp username
$password       = "lRv710";                             // smtp password
$craigs_urls    = array(                                // list of urls (formatted as an array)
		'http://orlando.craigslist.org/cpg', 
		'http://orlando.craigslist.org/sad',
		'http://orlando.craigslist.org/web',
		'http://orlando.craigslist.org/sof',
		'http://orlando.craigslist.org/eng'
		);
require_once "Mail.php";
if (!file_exists($log_dir."last_url.txt")) {
	touch($log_dir."last_url.txt");
	$lastfile = array();
} else {
	$lastfile = file($log_dir."last_url.txt", FILE_IGNORE_NEW_LINES);
}

if (!file_exists($log_dir."craiglock")){
	touch($log_dir."craiglock");
	foreach ($craigs_urls as $watch_url) {
		$rss = file_get_contents($watch_url.'/index.rss');
		preg_match_all('#'.$watch_url.'/\d+\.html#', $rss, $urls_loop);
		$urls = array_unique($urls_loop[0]);
		$last_url .= $urls[0]."\n";
		file_put_contents($log_dir."craigmailer.log", date('d-m-Y H:i:s')." | last urls: $debug_last_url ; starting sends\n", FILE_APPEND);
		foreach ($urls as $url) {
			//Old style
			//if (!in_array($url, $lastfile)) {

			//New hotness
			//Does craigslist delete posts? This is more robust, either way.
			$url_path = parse_url($url, PHP_URL_PATH);
			$url_pathinfo = pathinfo($url_path);
			if (!$url_pathinfo['filename'] <= basename($lastfile[array_search($url_pathinfo['dirname'], $lastfile)], ".html")) {
				$page = file_get_contents($url);
				preg_match_all('#[-+.\w]+((\@)|(.{,4}at.{,4}))[-+.\w]+\.[[:alpha:]]{2,3}#', $page, $emails);
				preg_match('#mailto\:(.*?)\?#', $page, $mailto);
				preg_match('#<h2>(.*)</h2>#', $page, $header);
				if (end($emails[0])) {
					$mailto = html_entity_decode(end($emails[0]));
				} elseif (end($mailto)) {
					$mailto = html_entity_decode(end($mailto));
				} else {
					$mailto = FALSE;
					file_put_contents($log_dir."craigmailer.log", date('d-m-Y H:i:s')." | $url | not sent - no email address was listed.\n", FILE_APPEND);
				}
				if ($mailto) {
					preg_match('#<div id=\"userbody\">(.*)</div>#msU', $page, $body);
					$body = $body[1]."<a href=\"".$url."\">$url</a>";
					$from = "\"".$mailto."\" <".$mailto.">";
					$to = $username;
					$subject = "craigslist:".$header[1]; 
					$headers = array ('From' => $from, 'To' => $to, 'Subject' => $subject, 'Reply-To' => $mailto, 'Content-type' => 'text/html');
					$smtp = Mail::factory('smtp', array ('host' => $host, 'port' => $port, 'auth' => true, 'username' => $username, 'password' => $password));
					$mail = $smtp->send($to, $headers, $body);
					file_put_contents($log_dir."unsent_mails", "\n--------------------\n".date('d-m-Y H:i:s').$headers."\n-----\n$body\n--------------------\n", FILE_APPEND);
					/**if (PEAR::isError($mail)) {
						file_put_contents($log_dir."craigmailer.log", date('d-m-Y H:i:s')." | $url | $subject | error : ".$mail->getMessage()."\n", FILE_APPEND);
						continue;
					} else {
						file_put_contents($log_dir."craigmailer.log", date('d-m-Y H:i:s')." | $url | $subject | sent \n", FILE_APPEND);
						continue;
					}**/
				}
			} ELSE {
				file_put_contents($log_dir."craigmailer.log", date('d-m-Y H:i:s')." | $url in last urls - skipping to next feed. \n", FILE_APPEND);
				break 2;
			}
		}
		file_put_contents($log_dir."craigmailer.log", date('d-m-Y H:i:s')." | Continuing to next feed from $urls[0]\n", FILE_APPEND);
	}
	file_put_contents($log_dir."craigmailer.log", date('d-m-Y H:i:s')." | DONE. Setting last urls for feeds\n", FILE_APPEND);
	file_put_contents($log_dir."last_url.txt", $last_url);
	unlink($log_dir."craiglock");
} ELSE {
	file_put_contents($log_dir."craigmailer.log", date('d-m-Y H:i:s')." | Directory locked: Check for frozen script.\n", FILE_APPEND);
	die("Lockfile exists");
}


?>
