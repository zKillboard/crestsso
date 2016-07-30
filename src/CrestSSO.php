<?php

namespace zkillboard\crestsso;

class CrestSSO
{
    protected $ccpClientID;
    protected $ccpClientSecret;
    protected $callbackURL;
    protected $scopes;
    protected $referer;

    protected $loginURL = "https://login.eveonline.com/oauth/authorize";
    protected $tokenURL = "https://login.eveonline.com/oauth/token";
    protected $verifyURL = "https://login.eveonline.com/oauth/verify";

    public function __construct($ccpClientID, $ccpClientSecret, $callbackURL, $scopes = [], $referer = '/')
    {
        $this->ccpClientID = $ccpClientID;
        $this->ccpClientSecret = $ccpClientSecret;
        $this->callbackURL = $callbackURL;
        $this->scopes = $scopes;
        $this->referer = $referer;
    }

    public function getLoginURL()
    {
        $fields = [
            "response_type" => "code", 
            "client_id" => $this->ccpClientID,
            "redirect_uri" => $this->callbackURL, 
            "scope" => implode('+', $this->scopes),
            "redirect" => $this->referer
        ];
        $params = $this->buildParams($fields);

        $url = $this->loginURL . "?" . $params;
        return $url;
    }

    public function handleCallback($code)
    {
        $fields = ['grant_type' => 'authorization_code', 'code' => $code];

        $tokenString = $this->doCall($this->tokenURL, $fields, null, true);
        $tokenJson = json_decode($tokenString, true);

        $accessToken = $tokenJson['access_token'];
        $refreshToken = $tokenJson['refresh_token'];
        
        $verifyString = $this->doCall($this->verifyURL, [], $accessToken, false);
        $verifyJson = json_decode($verifyString, true);

        $retValue = [
            'characterID' => $verifyJson['CharacterID'],
            'characterName' => $verifyJson['CharacterName'],
            'scopes' => $verifyJson['Scopes'],
            'tokenType' => $verifyJson['TokenType'],
            'refreshToken' => $refreshToken,
            'accessToken' => $accessToken
        ];

        return $retValue;
    }

    public function getAccessToken($refreshToken)
    {
        $fields = ['grant_type' => 'refresh_token', 'refresh_token' => $refreshToken];
        $accessString = $this->doCall($this->tokenURL, $fields, null, true);
        $accessJson = json_decode($accessString, true);
        return $accessJson['access_token'];
    }

    public function doCall($url, $fields, $accessToken, $isPost = false)
    {
        $header = $accessToken !== null ? 'Authorization: Bearer ' . $accessToken : 'Authorization: Basic ' . base64_encode($this->ccpClientID . ':' . $this->ccpClientSecret);

        $fieldsString = $this->buildParams($fields);
        $url = $isPost ? $url : $url . "?" . $fieldsString;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->callbackURL);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [$header]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        if ($isPost) {
            curl_setopt($ch, CURLOPT_POST, count($fields));
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fieldsString);
        }

        $result = curl_exec($ch);

        if (curl_errno($ch) !== 0) {
            throw new \Exception(curl_error($ch));
        }

        return $result;
    }

    protected function buildParams($fields)
    {
        $string = "";
        foreach ($fields as $field=>$value) {
            $string .= $string == "" ? "" : "&";
            $string .= "$field=$value";
        }
        return $string;
    }
}