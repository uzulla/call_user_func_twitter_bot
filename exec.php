<?php
require "vendor/autoload.php";
require "config.php";

//twitter setup
$user_values = [
    'twitter_oauth_token'=>TWITTER_ACCESS_TOKEN,
    'twitter_oauth_token_secret'=>TWITTER_ACCESS_TOKEN_SECRET
];
\Uzulla\Util\Twitter::setConsumerKey(TWITTER_CONSUMER_KEY, TWITTER_CONSUMER_SECRET);

//drift file
if(!file_exists(LAST_DATE_FILE)){
    $lastdate = new DateTime('2000-01-01 00:00:00');
}else{
    $lastdate = new DateTime(file_get_contents(LAST_DATE_FILE));
}

//read rss
$newly_submitted_packages_rss = "https://packagist.org/feeds/packages.rss";

$ff = \FastFeed\Factory::create();
$ff->addFeed('new_submit', $newly_submitted_packages_rss);

/** @var \FastFeed\Item[] $items */
$items = $ff->fetch('new_submit');
$items = array_reverse($items); // RSSは新しいのが上にくる（っぽい）ので。

foreach($items as $item){
    if($lastdate>=$item->getDate()) continue;

    $str = "[New]{$item->getName()} {$item->getContent()}";
    if(mb_strlen($str)>TWEET_MAX_LENGTH_WITHOUT_URL){
        $str = mb_substr($str, 0, TWEET_MAX_LENGTH_WITHOUT_URL)."…";
    }
    $str = "{$str} {$item->getSource()}";
    \Uzulla\Util\Twitter::sendTweet($user_values, $str);
    sleep(1); // post wait.
    $lastdate = $item->getDate();
}

file_put_contents(LAST_DATE_FILE, $lastdate->format('c')); // update last
