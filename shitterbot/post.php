<?php
// Continue script after web page has closed.
ignore_user_abort(true);

// Require private Instagram API.
require __DIR__.'/../vendor/autoload.php';

// Include configuration file.
$config = include(__DIR__.'/config.php');

$username = $config['username'];
$password = $config['password'];
$subreddits = $config['subreddits'];
$postLogFilename = __DIR__."/{$config['postLogFilename']}";
$imageFilename = $config['imageFilename'];
$relatedUsernames = $config['relatedUsernames'];
$action = $config['action'];
$timing = $config['timing'];
$maxActionsPerDay = $config['maxActionsPerDay'];
$minActionDelay = $config['minActionDelay'];
$maxActionDelay = $config['maxActionDelay'];

// Bypass web warning.
\InstagramAPI\Instagram::$allowDangerousWebUsageAtMyOwnRisk = true;

$ig = new \InstagramAPI\Instagram();

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

// Append image extension to image filename.
$imageFilename .= '.'.pathinfo($randomPost->data->url, PATHINFO_EXTENSION);

// Save image from URL.
copy($randomPost->data->url, $imageFilename);

try {
	// Login to Instagram.
	$ig->login($username, $password);
} catch (\Exception $e) {
	exit();
}

try {
	// Upload image to Instagram.
	$image = new \InstagramAPI\Media\Photo\InstagramPhoto($imageFilename,
	['operation'=>2]);
	$ig->timeline->uploadPhoto($image->getFile(), ['caption' => $randomPost->data->title]);

	// Log post ID and time.
	fwrite($postLogFileNew, "{$randomPost->data->id} {$now->getTimestamp()}\n");
} catch (\Exception $e) {

}
// Image has been posted and logged.

fclose($postLogFileNew);

// Delete saved image.
unlink($imageFilename);

// Random number of actions to execute.
$executionsPerDay = 24 * 60 / $timing;

$maxActionsToExecute = floor($maxActionsPerDay / $executionsPerDay);
$minActionsToExecute = ceil($maxActionsToExecute / 2);

$actionsToExecute = rand($minActionsToExecute, $maxActionsToExecute);

if ($action == 'follow') {
	// Follow related users.
	for ($i = 1; $i <= $actionsToExecute; $i++) {
		// Get user ID of random related user.
		$relatedUsername = $relatedUsernames[array_rand($relatedUsernames)];
		$relatedUserId = $ig->people->getUserIdForName($relatedUsername);

		// Generate a random rank token.
		$rankToken = \InstagramAPI\Signatures::generateUUID();

		// Get followers of related user.
		$relatedUserFollowers = $ig->people->getFollowers($relatedUserId, $rankToken)->getUsers();

		if ($relatedUserFollowers == null) {
			// Related user has no followers, or is private.
			$i--;
			continue;
		}

		// Find follower to follow.
		foreach ($relatedUserFollowers as $relatedUserFollower) {

			// Get follower's user ID and friendship.
			$relatedUserFollowerId = $relatedUserFollower->getPk();
			$relatedUserFollowerFriendship = $ig->people->getFriendship($relatedUserFollowerId);

			// Check if already following, or requested to follow.
			if (!$relatedUserFollowerFriendship->isFollowing() && !$relatedUserFollowerFriendship->isOutgoingRequest()) {
				// Follow user.
				$ig->people->follow($relatedUserFollowerId);
				break;
			}
			// If already following user, or requested to follow, keep looking.
		}

		// Wait between actions.
		sleep(rand($minActionDelay, $maxActionDelay));
	}
} else if ($action == 'unfollow') {
	// Unfollow users.
	// TODO: 
}
?>
