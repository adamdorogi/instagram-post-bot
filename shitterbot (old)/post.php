<?php
ignore_user_abort(true);
require __DIR__.'/../vendor/autoload.php';
$config = include(__DIR__.'/config.php');

\InstagramAPI\Instagram::$allowDangerousWebUsageAtMyOwnRisk = true;

$ig = new \InstagramAPI\Instagram();

// Subreddits to get JSON from.
$subreddits = $config['subreddits'];

// Get JSON of random subreddit.
$jsonUrl = 'https://www.reddit.com/r/'.$subreddits[array_rand($subreddits)].
'.json';
$jsonString = file_get_contents($jsonUrl);
$json = json_decode($jsonString);

// Get memes from JSON if not stickied, and image format.
$memes=[];

foreach ($json->data->children as $child) {
	$memeFormat = pathinfo($child->data->url, PATHINFO_EXTENSION);
	if (!$child->data->stickied &&
		($memeFormat == 'jpg'
			|| $memeFormat == 'jpeg'
			|| $memeFormat == 'png')) {
		array_push($memes, $child);
	}
}

// Check whether meme has been posted before.
do {
	$duplicate = false;
    
	// All memes on subreddit have been posted.
	if (empty($memes)) {
// 		echo "All memes have been shown.\n";
		exit();
	}
    
	// Choose a random meme.
	$random = array_rand($memes);
	$randomMeme = $memes[$random];
    
	// Check log for saved IDs.
	$postedMemes = fopen(__DIR__.'/'.$config['postLog'], 'a+');
	while(!feof($postedMemes)) {
		// Found duplicate.
		if ($randomMeme->data->id."\n" == fgets($postedMemes)) {
			$duplicate = true;
            
            // Remove duplicate meme from array.
			unset($memes[$random]);
			$memes = array_values($memes);
            
			fclose($postedMemes);
			break;
		}
	}
} while ($duplicate);
// Non-duplicate meme has been found.

// echo "Posting: ".$randomMeme->data->title." (".$randomMeme->data->id."\n";
// echo "URL:     ".$randomMeme->data->url."\n";

// Set meme path.
$photoFilename = $config['photoFilename'].
	'.'.pathinfo($randomMeme->data->url, PATHINFO_EXTENSION);

// Save meme image from URL.
copy($randomMeme->data->url, $photoFilename);

// Login to Instagram.
try {
	$ig->login($config['username'], $config['password']);
} catch (\Exception $e) {
	echo 'Something went wrong: '.$e->getMessage()."\n";
	exit();
}

// Log ID.
fwrite($postedMemes, $randomMeme->data->id."\n");
fclose($postedMemes);

// Upload image to Instagram.
try {
	$photo = new \InstagramAPI\Media\Photo\InstagramPhoto($photoFilename, ["operation"=>2]);
	$ig->timeline->uploadPhoto($photo->getFile(), ['caption' => $randomMeme->data->title]);
} catch (\Exception $e) {
	echo 'Something went wrong: '.$e->getMessage()."\n";
	exit();
}
// Image has been posted.

// Delete saved meme.
unlink($photoFilename);

// Random number of users to follow.
$schedule = $config['schedule'];
$maxFollowsPerDay = $config['maxFollowsPerDay'];
$executionsPerDay = 24 * 60 / $schedule;

$maxFollowsPerSchedule = floor($maxFollowsPerDay / $executionsPerDay);
$minFollowsPerSchedule = ceil($maxFollowsPerSchedule / 2);

$numberToFollow = rand($minFollowsPerSchedule, $maxFollowsPerSchedule);

// echo "Following ".$numberToFollow." users...";

// Follow related users.
for ($i = 1; $i <= $numberToFollow; $i++) {
	$relatedUsername = $config['relatedUsernames'][array_rand($config['relatedUsernames'])];
	$relatedUserId = $ig->people->getUserIdForName($relatedUsername);
    
	// Generate a random rank token.
	$rankToken = \InstagramAPI\Signatures::generateUUID();
    
	$relatedUserFollowers = $ig->people->getFollowers($relatedUserId, $rankToken)->getUsers();

	if ($relatedUserFollowers == null) {
// 		echo "ERROR: Private account or no followers\n";
		$i--;
		continue;
	}

// 	echo "Related account chosen: ".$relatedUsername."\n";

	foreach ($relatedUserFollowers as $relatedUserFollower) {
		$relatedUserFollowerId = $relatedUserFollower->getPk();
		$relatedUserFollowerFriendship = $ig->people->getFriendship($relatedUserFollowerId);
		// If not following user...
		if (!$relatedUserFollowerFriendship->isFollowing() && !$relatedUserFollowerFriendship->isOutgoingRequest()) {
			// Follow user.
			$ig->people->follow($relatedUserFollowerId);
			break;
		}
		// If following user, keep looking.
	}

	sleep(rand($config['minFollowDelay'], $config['maxFollowDelay']));
}

?>