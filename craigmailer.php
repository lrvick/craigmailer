<?php // CraigMailer
$to_email    = 'lance@lrvick.net';                   // the email all postings should be sent to
$sqlite_file = 'craigmailer.sqlite';                 // sqlite file location
$city        = 'orlando';                            // city to watch (as per cl subdomain)
$topics      = array('cpg','sad','web','sof','eng'); // topics (three char topics as used in cl urls)

$handle = sqlite_open($sqlite_file, 0666, $sqlite_error) or die("Could not open database");
$query = sqlite_query($handle,"SELECT name FROM sqlite_master WHERE type='table'");
if (!sqlite_fetch_array($query)){
	sqlite_query($handle,"CREATE TABLE posts (id INTEGER(15) PRIMARY KEY NOT NULL,timestamp DATETIME NOT NULL,topic CHAR(4) NOT NULL, subject CHAR(255) NOT NULL, email CHAR(255) NOT NULL, body TEXT, mail_sent INTEGER(1) DEFAULT 0 NOT NULL);")
		or die("Error in query: ".sqlite_error_string(sqlite_last_error($handle)));
}
foreach ($topics as $topic) {
	$watch_url = "http://".$city.".craigslist.org/".$topic;
	$rss = file_get_contents($watch_url."/index.rss");
	preg_match_all('#'.$watch_url.'/\d+\.html#', $rss, $urls_loop);
	$urls = array_unique($urls_loop[0]);
	foreach ($urls as $url) {
		$url_path = parse_url($url, PHP_URL_PATH);
		$url_pathinfo = pathinfo($url_path);
		$id =  $url_pathinfo['filename'];
    $query = sqlite_query($handle,"SELECT * FROM posts WHERE id='$id'");
    if (!sqlite_fetch_array($query)){
		  $page = file_get_contents($url);
      preg_match('#<div id=\"userbody\">(.*)</div>#msU', $page, $body);
		  preg_match('#Date: (.*) EDT#msU', $page, $timestamp);
		  $timestamp = explode(',',str_replace(' ','',$timestamp[1]));
		  $timestamp = $timestamp[0]." ".date( 'H:i:s', strtotime($timestamp[1]));
		  preg_match_all('#[-+.\w]+((\@)|(.{,4}at.{,4}))[-+.\w]+\.[[:alpha:]]{2,3}#', $page, $emails);
		  preg_match('#mailto\:(.*?)\?#', $page, $mailto);
		  preg_match('#<h2>(.*)</h2>#', $page, $header);
		  if (end($emails[0])) {
			  $email = html_entity_decode(end($emails[0]));
		  } elseif (end($mailto)) {
			  $email = html_entity_decode(end($mailto));
		  }
		  $body = sqlite_escape_string($body[1]."<a href=\"".$url."\">$url</a>");
		  $subject = sqlite_escape_string($header[1]);
		  if ($id && $timestamp && $topic && $subject && $email && $body) {
		    $query = "INSERT INTO posts (id,timestamp,topic,subject,email,body) VALUES ('$id','$timestamp','$topic','$subject','$email','$body');";
		    sqlite_query($handle,$query) or die("Error in query: ".sqlite_error_string(sqlite_last_error($handle)));
		    echo "\n Added - ".$id." | ".$timestamp." | ".$topic." | ".$subject." | ".$email."\n";
		  }
		}
	}
}
$query = sqlite_query($handle,"SELECT * FROM posts WHERE mail_sent='0';") or die("Error in query: ".sqlite_error_string(sqlite_last_error($handle)));
while ($post = sqlite_fetch_array($query)){
	$id = $post[0];
	$timestamp = $post[1];
  $topic = $post[2];
	$subject = "cl/".$topic." ".$post[3];
	$email = $post[4];
	$body = $timestamp."\n ".$post[5];
  $headers  = 'From: '.$email."\r\n";
  $headers .= 'Subject: '.$subject."\r\n";
	$headers .= 'Reply-To: '.$email."\r\n";
	//echo "\n".$body."\n";
	if (mail($to_email, $subject, $body, $headers)){
	  echo "\n mail sent for post #".$id."\n";
	  sqlite_query($handle,"UPDATE posts SET mail_sent = '1' WHERE id='$id'") or die("Error in query: ".sqlite_error_string(sqlite_last_error($handle)));
	}
}
?>
