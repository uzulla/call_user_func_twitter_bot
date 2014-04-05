<?php
namespace Uzulla\Util;

class Twitter
{
    private static $_instance = null;
    protected $_oauth_token = null;
    protected $_oauth_token_secret = null;

    public static function getInstance()
    {
        if (self::$_instance == null) {
            self::$_instance = new self;
        }
        return self::$_instance;
    }

    public static function getCb()
    {
        return \Codebird\Codebird::getInstance();
    }

    public static function setConsumerKey($key, $secret)
    {
        \Codebird\Codebird::setConsumerKey($key, $secret);
    }

    public function setToken($token, $secret)
    {
        $this->_oauth_token        = $token;
        $this->_oauth_token_secret = $secret;
    }

    static public function getMe($user_value)
    {
        $cb = self::getCb();
        $cb->setToken($user_value['twitter_oauth_token'], $user_value['twitter_oauth_token_secret']);

        try{
            $res = $cb->account_verifyCredentials();
            if($res->httpstatus != 200){
                throw new \Exception("Twitter api response {$res->httpstatus} -> {$res->errors[0]->message}");
            }
            if(!$res){
                throw new \Exception('null response');
            }
            if(!property_exists($res, 'id_str')){
                throw new \Exception('not found id_str');
            }
        }catch(\Exception $e){
            throw $e;
        }

        return $res;
    }

    static public function getByScreenName($user_value, $screen_name)
    {
        $cb = self::getCb();
        $cb->setToken($user_value['twitter_oauth_token'], $user_value['twitter_oauth_token_secret']);

        try{
            $res = $cb->users_show(['screen_name'=>$screen_name]);
            if($res->httpstatus != 200){
                throw new \Exception("Twitter api response {$res->httpstatus} -> {$res->errors[0]->message}");
            }
        }catch(\Exception $e){
            throw $e;
        }

        return $res;
    }

    static public function getByTwitterId($user_value, $twitter_id)
    {
        $cb = self::getCb();
        $cb->setToken($user_value['twitter_oauth_token'], $user_value['twitter_oauth_token_secret']);

        try{
            $res = $cb->users_show(['user_id'=>$twitter_id]);
            if($res->httpstatus != 200){
                throw new \Exception("Twitter api response {$res->httpstatus} -> {$res->errors[0]->message}");
            }
        }catch(\Exception $e){
            throw $e;
        }

        return $res;
    }


    static public function getTweetByScreenName($user_value, $screen_name, $include_rt=false, $limit=10, $since_id=null)
    {
        $cb = self::getCb();
        $cb->setToken($user_value['twitter_oauth_token'], $user_value['twitter_oauth_token_secret']);

        try{
            $api = 'statuses/userTimeline';
            $params['screen_name'] = $screen_name; // App owner - the app has read access
            $params['include_rts'] = $include_rt;
            $params['count'] = $limit;
            if($since_id != null){
                $params['since_id'] = $since_id;
            }
            $res = (array) $cb->$api($params);
            if($res['httpstatus'] != 200){
                error_log(print_r($cb,1));
                error_log(print_r($res,1));
                throw new \Exception("Twitter api response {$res->httpstatus} -> {$res->errors[0]->message}");
            }
            unset($res['httpstatus']);

        }catch(\Exception $e){
            throw $e;
        }
        return $res;
    }

    static public function followByScreenName($user_value, $screen_name)
    {
        $cb = self::getCb();
        $cb->setToken($user_value['twitter_oauth_token'], $user_value['twitter_oauth_token_secret']);

        try{
            $res = $cb->friendships_create(['screen_name'=>$screen_name]);
            if($res->httpstatus != 200){
                throw new \Exception("Twitter api response {$res->httpstatus} -> {$res->errors[0]->message}");
            }
        }catch(\Exception $e){
            throw $e;
        }

        return $res;
    }

    static public function followCheckByScreenName($user_value, $screen_name)
    {
        $cb = self::getCb();
        $cb->setToken($user_value['twitter_oauth_token'], $user_value['twitter_oauth_token_secret']);

        try{
            $res = $cb->friendships_lookup(['screen_name'=>$screen_name]);
            if($res->httpstatus != 200){
                throw new \Exception("Twitter api response {$res->httpstatus} -> {$res->errors[0]->message}");
            }
        }catch(\Exception $e){
            throw $e;
        }

        $res_array = (array)$res;// $res->0 に相当するのってどうやって指定するの…。
        $connections_list = $res_array['0']->connections;

        foreach($connections_list as $connections){
            if($connections=='none'){
                return false;
            }else if($connections=='following'){
                return true;
            }else if($connections=='followed_by'){
                continue;
            }
        }
        throw new \Exception('Twitter API response invalid.');
    }

    static public function getOtherSizeProfileImageUrl($url, $type='normal')
    {
        if(!preg_match('/\A(normal|bigger|mini|original)\z/', $type)){
            throw new \Exception('Type must (normal|bigger|mini|original)');
        }
        $new_url = preg_replace('/_(normal|bigger|mini|original)\./', "_{$type}.", $url);
        return $new_url;
    }

    static public function sendTweet($user_value, $text){
        $cb = self::getCb();
        $cb->setToken($user_value['twitter_oauth_token'], $user_value['twitter_oauth_token_secret']);

        try{
            $res = $cb->statuses_update([
                'status' => $text
            ]);
            if($res->httpstatus != 200){
                throw new \Exception("Twitter api response {$res->httpstatus} -> {$res->errors[0]->message}");
            }
        }catch(\Exception $e){
            throw $e;
        }
        return $res;
    }

    static public function getAuthUrl($callback_url){
        try{
            $cb = \Codebird\Codebird::getInstance();
            $reply = $cb->oauth_requestToken(array(
                'oauth_callback' => $callback_url
            ));
            $cb->setToken($reply->oauth_token, $reply->oauth_token_secret);

            $_SESSION['oauth_token'] = $reply->oauth_token;
            $_SESSION['oauth_token_secret'] = $reply->oauth_token_secret;
            $_SESSION['oauth_verify'] = true;

            $auth_url = $cb->oauth_authenticate();
        }catch(\Exception $e){
            throw $e;
        }
        return $auth_url;
    }

    static public function oauthCallbackGetTokenList(){
        if( !isset($_GET['oauth_verifier']) || !isset($_SESSION['oauth_verify']) ){
            throw new \Exception('twitter auth fail');
        }
        //unset($_SESSION['oauth_verify']);

        try{
            $cb = \Codebird\Codebird::getInstance();
            $cb->setToken($_SESSION['oauth_token'], $_SESSION['oauth_token_secret']);
            $reply = $cb->oauth_accessToken(['oauth_verifier' => $_GET['oauth_verifier']]);
            var_dump($reply);

            return [
                'twitter_oauth_token' => $reply->oauth_token,
                'twitter_oauth_token_secret' => $reply->oauth_token_secret,
                ];
        }catch(\Exception $e){
            throw $e;
        }
    }

}
