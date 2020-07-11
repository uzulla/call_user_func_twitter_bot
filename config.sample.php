<?php
ini_set('error_reporting', -1);
ini_set('display_errors', 'Off');
ini_set('log_errors', 'On');
ini_set('error_log', 'php://stderr');

define('TWITTER_SHORT_URL_LENGTH', 26);
define('TWITTER_TWEET_MAX_LENGTH', 140);
define('TWEET_MAX_LENGTH_WITHOUT_URL', TWITTER_TWEET_MAX_LENGTH-TWITTER_SHORT_URL_LENGTH);
define('TWITTER_CONSUMER_KEY', getenv('TWITTER_CONSUMER_KEY'));
define('TWITTER_CONSUMER_SECRET', getenv('TWITTER_CONSUMER_SECRET'));
define('TWITTER_ACCESS_TOKEN', getenv('TWITTER_ACCESS_TOKEN'));
define('TWITTER_ACCESS_TOKEN_SECRET', getenv('TWITTER_ACCESS_TOKEN_SECRET'));
