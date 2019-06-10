<?php
$now = new DateTime();

$asd = fopen(__DIR__.'/test.txt', 'r');
$asd2 = fopen(__DIR__.'/test2.txt', 'a+');

while(!feof($asd)) {
  $line = fgets($asd);
  if ($line) {
    $lineContent = explode(' ', trim($line, "\n"));
    $postId = $lineContent[0];
    $postDate = new DateTime('@'.$lineContent[1]);
    $diff = $now->getTimestamp() - $postDate->getTimestamp();
    echo $now->getTimestamp()."\n";

    if ($postId != 'a') {
      fwrite($asd2, $line);
    }
  }
}
fclose($asd);
rename(__DIR__.'/test2.txt', __DIR__.'/test.txt');
fwrite($asd2, 'TEST');

fclose($asd2);
?>
