<?php
// Continue script after web page has closed.
ignore_user_abort(true);

// Include configuration file.
$config = include(__DIR__.'/config.php');

$subreddits = $config['subreddits'];
$postLogFilename = __DIR__."/{$config['postLogFilename']}";

// Get JSON of random subreddit.
$randomSubreddit = $subreddits[array_rand($subreddits)];
$jsonUrl = "https://www.reddit.com/r/$randomSubreddit.json";
$jsonString = file_get_contents($jsonUrl);
$json = json_decode($jsonString);

// Get Reddit posts and image format from JSON, if not stickied.
$posts = [];

// Loop through each Reddit post.
foreach ($json->data->children as $post) {
	// Get Reddit post format.
	$postFormat = pathinfo($post->data->url, PATHINFO_EXTENSION);

	// Check Reddit post.
	if (!$post->data->stickied &&
	($postFormat == 'jpg' ||
	$postFormat == 'jpeg' ||
	$postFormat == 'png')) {
		// If image format, and not stickied, append to posts array.
		array_push($posts, $post);
	}
}

// Open post log for reading and writing.
$postLogFileOld = fopen($postLogFilename, 'r');
$tempLogFilename = __DIR__."/tmp.txt";
$postLogFileNew = fopen($tempLogFilename, 'a+');

// Get current time.
$now = new DateTime();

$firstLoop = true;

// Pick a random Reddit post, which has not been posted to Instagram before.
do {
	$duplicate = false;

	// Start reading from start of log file.
	rewind($postLogFileOld);

	if (empty($posts)) {
		// All posts on subreddit have been posted to Instagram.
		exit();
	}

	// Choose a random Reddit post.
	$random = array_rand($posts);
	$randomPost = $posts[$random];

	// Check each line of post log for post IDs and dates.
	while(!feof($postLogFileOld)) {
		// Get post ID and date from current line.
		$line = fgets($postLogFileOld);
		if ($line) {
			$lineContent = explode(' ', trim($line, "\n"));
			$postId = $lineContent[0];

			if ($firstLoop) {
				$postDate = new DateTime('@'.$lineContent[1]);
		    $postAge = $now->getTimestamp() - $postDate->getTimestamp();

				// Check if posted less than a week ago.
		    if ($postAge < 604800) {
					// Copy log over to new file.
		      fwrite($postLogFileNew, $line);
		    }
			}

			// Compare randomly picked post's ID to post ID in post log.
			if ($postId == $randomPost->data->id) {
				// Dupliacte has been found.
				$duplicate = true;

				// Remove duplicate meme from array.
				unset($posts[$random]);
				$posts = array_values($posts);

				if (!$firstLoop) {
					break;
				}
			}
		}
	}
	$firstLoop = false;
} while ($duplicate);
// Non-duplicate post has been found.

fclose($postLogFileOld);

rename($tempLogFilename, $postLogFilename);

// Upload photo to Facebook.
$url = 'https://graph.facebook.com/v2.11/330498403748443/photos';

$photoUrl = $randomPost->data->url;
$photoCaption = $randomPost->data->title;
$accessToken = 'EAAC8hYflZC90BACIwKfRtveTbiNozufiacZCQcsajYULfRctWoD6jSDMK1pFFWNAXZBXgiQQzf2K6p2MjURUiA6yxAn7b2gGEZBG488YOWowJA7kGIdZABwEb2epDedfDrEYa9PHHTeJPUaiBMuDDiU5pzCu54cYWyVJqpNxL5wZDZD';

$fields = "url=$photoUrl&caption=$photoCaption&access_token=$accessToken";

$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);

curl_exec($ch);
curl_close($ch);

fwrite($postLogFileNew, "{$randomPost->data->id} {$now->getTimestamp()}\n");
fclose($postLogFileNew);
?>
