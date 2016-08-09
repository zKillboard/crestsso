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

    public function getLoginURL($session)
    {
        $factory = new \RandomLib\Factory;
        $generator = $factory->getGenerator(new \SecurityLib\Strength(\SecurityLib\Strength::MEDIUM));
        $state = $generator->generateString(32, "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ");

        if (is_array($session)) $session["oauth2State"] = $state;
        else if ($session instanceof \Aura\Session\Segment) $session->set("oauth2State", $state);
        else throw new \Exception("Unknown session type");

        $fields = [
            "response_type" => "code", 
            "client_id" => $this->ccpClientID,
            "redirect_uri" => $this->callbackURL, 
            "scope" => implode(' ', $this->scopes),
            "redirect" => $this->referer,
            "state" => $state
        ];
        $params = $this->buildParams($fields);

        $url = $this->loginURL . "?" . $params;
        return $url;
    }

    public function handleCallback($code, $state, $session)
    {
        if (is_array($session)) $oauth2State = $session["oauth2State"];
        elseif ($session instanceof \Aura\Session\Segment) $oauth2State = $session->get("oauth2State");
        else throw new \Exception("Unknown session type");

        if ($oauth2State != $state) {
            throw new \Exception("Invalid state returned - possible hijacking attempt");
        }

        $fields = ['grant_type' => 'authorization_code', 'code' => $code];

        $tokenString = $this->doCall($this->tokenURL, $fields, null, 'POST');
        $tokenJson = json_decode($tokenString, true);

        $accessToken = $tokenJson['access_token'];
        $refreshToken = $tokenJson['refresh_token'];
        
        $verifyString = $this->doCall($this->verifyURL, [], $accessToken, 'GET');
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
        $accessString = $this->doCall($this->tokenURL, $fields, null, 'POST');
        $accessJson = json_decode($accessString, true);
        return $accessJson['access_token'];
    }

    public function doCall($url, $fields, $accessToken, $callType = 'GET')
    {
        $callType = strtoupper($callType);
        $header = $accessToken !== null ? 'Authorization: Bearer ' . $accessToken : 'Authorization: Basic ' . base64_encode($this->ccpClientID . ':' . $this->ccpClientSecret);
        $headers = [$header];

        $fieldsString = $this->buildParams($fields);
        $url = $callType != 'GET' ? $url : $url . "?" . $fieldsString;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->callbackURL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        switch ($callType) {
            case 'DELETE':
            case 'PUT':
            case 'POST_JSON':
                $headers[] = "Content-Type: application/json";
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(empty($fields) ? (object) NULL : $fields, JSON_UNESCAPED_SLASHES));
                $callType = $callType == 'POST_JSON' ? 'POST' : $callType;
                break;
            case 'POST':
                curl_setopt($ch, CURLOPT_POSTFIELDS, $fieldsString);
                break;
        }
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $callType);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

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
            $string .= "$field=" . rawurlencode($value);
        }
        return $string;
    }
}
