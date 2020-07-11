<?php
use FastFeed\Factory;
use FastFeed\Item;
use Uzulla\Util\Twitter;

// strict error bailout
function strict_error_handler($errno, $errstr, $errfile, $errline)
{
    error_log("STRICT: {$errno} {$errstr} {$errfile} {$errline} ");
    die("STRICT: {$errno} {$errstr} {$errfile} {$errline} " . PHP_EOL);
}

set_error_handler("strict_error_handler");

require "vendor/autoload.php";
if(file_exists("config.php")) {
    require "config.php";
}else{
    require "config.sample.php";
}

// twitter setup
$user_values = [
    'twitter_oauth_token' => TWITTER_ACCESS_TOKEN,
    'twitter_oauth_token_secret' => TWITTER_ACCESS_TOKEN_SECRET
];
Twitter::setConsumerKey(TWITTER_CONSUMER_KEY, TWITTER_CONSUMER_SECRET);

// get last update date.
try {
    try {
        $tweets = Twitter::getTweetByScreenName($user_values, 'call_user_func');
    }catch(Exception $e){
        error_log("failed get last tweet from twitter");
        exit(1);
    }
    $last_date = new DateTime($tweets[0]->created_at);
}catch(Exception $e){
    $last_date = new DateTime('2000-01-01 00:00:00');
}

//read rss
$newly_submitted_packages_rss = "https://packagist.org/feeds/packages.rss";

$ff = Factory::create();
$ff->addFeed('new_submit', $newly_submitted_packages_rss);

/** @var Item[] $items */
$items = $ff->fetch('new_submit');
$items = array_reverse($items); // RSSは新しいのが上にくる（っぽい）ので。

foreach ($items as $item) {
    if ($last_date->format('U') >= $item->getDate()->format('U')) {
        // already processed, skip!
        continue;
    }

    $content = $item->getContent();
    $name = $item->getName();

    // check funny package name
    if (preg_match_all("/-/u", $name) > 5) {
        error_log("SKIP too many '-' {$name}");
        continue;
    }

    // get package meta data
    $packagist_json_api_url = "https://packagist.org/packages/{$name}.json";
    $api_json = file_get_contents($packagist_json_api_url);
    $api_data = json_decode($api_json, 1);
    if (is_null($api_data)) { // failed. skip!
        error_log("SKIP packagist json api access failed. {$name}");
        continue;
    }

    // try to get repo url
    $repo_url = $api_data['package']['repository'];
    if (!preg_match('|^https://|u', $repo_url)) {
        error_log("SKIP repo url is not https {$repo_url}"); // I found "git@gitlab.com〜" pattern. 
        continue;
    }
    $context = stream_context_create(array(
        'http' => [
            'ignore_errors' => true,
            'header' => 'User-Agent: PHP', // for github
        ],
    ));
    file_get_contents($repo_url, false, $context);

    // check repo exists
    if (
        strpos($http_response_header[0], '200') === false &&
        strpos($http_response_header[0], '302') === false  // for gitlab.
    ) {
        error_log("SKIP repo url not 200|302 " . $repo_url . " - " . $http_response_header[0]);
        $last_date = $item->getDate();
        continue;
    }

    // if github, more check user info (many spammer uses github)
    if (strpos($repo_url, 'github') !== false) {
        $match = preg_match("|https://github.com/([^/]+)/|u", $repo_url, $m);
        $gh_user_name = $m[1]; // FIXME why not use api???

        $context = stream_context_create(array(
            'http' => [
                'ignore_errors' => true,
                'header' => 'User-Agent: PHP', // for github
            ],
        ));
        $gh_user_data = json_decode(file_get_contents("https://api.github.com/users/{$gh_user_name}", false, $context), 1);
        if (is_null($gh_user_data)) {
            error_log("SKIP gh user data is null {$name} {$repo_url}");
            continue;
        }

        try {
            $gh_created_at = new DateTime($gh_user_data['created_at']);
        } catch (Exception $e) {
            error_log("fail parse datetime gh_user_data['created_at']");
            continue;
        }
        $yesterday_at = new DateTime('-2 days');
        if ($gh_created_at > $yesterday_at) {
            error_log("SKIP gh account too new {$name} {$repo_url}");
            continue;
        }
    }

    // snip url. that is spammers link in many cases. I don't want get DMCA mail.
    $content = preg_replace('|https?://[a-zA-Z0-9/:%#&~=_!\'$?().+*]+|u', '<snip url>', $content);
    $str = "{$item->getName()} {$content}";
    if (mb_strlen($str) > TWEET_MAX_LENGTH_WITHOUT_URL) {
        $str = mb_substr($str, 0, TWEET_MAX_LENGTH_WITHOUT_URL) . "…";
    }
    $str = "{$str} {$repo_url}";
    try {
        if (TWITTER_CONSUMER_KEY != "") {
            Twitter::sendTweet($user_values, $str);
//            var_dump([$user_values, $str]);
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        error_log("Twitter post fail: " . $e->getMessage());
    }
//    echo $str . "\n";
    sleep(1); // post wait.
}
