<?php
ini_set('error_reporting', -1);
ini_set('display_errors', 'On');
ini_set('log_errors', 'On');
ini_set('error_log', __DIR__.'/error.log');
// strict error bailout
function strict_error_handler($errno, $errstr, $errfile, $errline)
{
    error_log("STRICT: {$errno} {$errstr} {$errfile} {$errline} ");
    die ("STRICT: {$errno} {$errstr} {$errfile} {$errline} ".PHP_EOL);
}
set_error_handler("strict_error_handler");

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
    if($lastdate->format('U') >= $item->getDate()->format('U')){
        // skip! 
        continue;
    }

    $content = $item->getContent();
    $name = $item->getName();

    $packagist_json_api_url = "https://packagist.org/packages/{$name}.json";
    $api_json = file_get_contents($packagist_json_api_url);
    $api_data = json_decode($api_json,1);
    if(is_null($api_data)){
        // failed. skip!
        $lastdate = $item->getDate();
        continue;
    }

    $repo_url = $api_data['package']['repository'];

    $context = stream_context_create(array(
        'http' => array('ignore_errors' => true)
    ));
    file_get_contents($repo_url, false, $context);
    
    if (strpos($http_response_header[0], '200') === false) {
        // not exists repo, maybe SPAM?
        error_log("not 200 SKIP: " . $repo_url . " - " . $http_response_header[0]);
        $lastdate = $item->getDate();
        continue;
    }
    
    $content = preg_replace('|https?://[a-zA-Z0-9/:%#&~=_!\'\$\?\(\)\.\+\*]+|u', '<snip url>', $content);
    $str = "[New]{$item->getName()} {$content}";
    if(mb_strlen($str)>TWEET_MAX_LENGTH_WITHOUT_URL){
        $str = mb_substr($str, 0, TWEET_MAX_LENGTH_WITHOUT_URL)."…";
    }
    $str = "{$str} {$repo_url}";
    try{
        if(TWITTER_CONSUMER_KEY!=""){
            \Uzulla\Util\Twitter::sendTweet($user_values, $str);
        }
    }catch(\Exception $e){
        error_log($e->getMessage());
        echo "Twitter post fail: ".$e->getMessage().PHP_EOL;
    }
    echo $str."\n";
    sleep(1); // post wait.
    $lastdate = $item->getDate();
}

file_put_contents(LAST_DATE_FILE, $lastdate->format('c')); // update last
