[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/zKillboard/crestsso/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/zKillboard/crestsso/?branch=master)

# Crest SSO

## READ FIRST

Before using this class you must register your application with CCP here:

https://developers.eveonline.com/applications/

There you will register the name and description of your app, provide the callback url, and the scopes you'd like to request. When that is completed you will be provided with the clientID and secretKey you will need for working with Crest SSO.

### Installation

I recommend using PHP's popular package manager:

    composer require zkillboard/crestsso

### Implementation

This code was created to make the usage of CREST SSO very simple. Once you have your clientID and secretKey you can instantiate CrestSSO like so:

    $sso = new CrestSSO($clientID, $secretKey, $callbackURL, $scopes);

$clientID, $secretKey, and $callbackURL are all strings. The $scopes parameter is an array and defaults to an empty array.

Once instantiated, you can then retrieve the URL needed for the user to login with CREST SSO:

    $loginURL = $sso->getLoginURL();
    
A typical web application will then redirect the user to this loginURL. This example will use PHP's header command, but I recommend using a framework such as Slim.

    header("Location: $loginURL");

Here the control is out of your hands since the user is verifying their identity with CCP and choosing which character they want to pass back to your application. Once they've completed these steps, the CCP auth server will redirect the user back to your callback URL. Here you will need to do a couple of steps to obtain the user's information.

    $sso = new CrestSSO($clientID, $secretKey, $callbackURL, $scopes)
    $code = filter_input(INPUT_GET, 'code');
    $userInfo = $sso->handleCallback($code);

The resulting $userInfo array will contain the following keys with their appropriate values:

    characterID
    characterName
    scopes
    tokenType
    refreshToken
    accessToken

Keep in mind accessTokens are only good for 20 minutes after creation. If your accessToken has expired, you can use the refreshToken to get a new accessToken:

    $sso->getAccessToken($refreshToken);

With an access token you can make various auth'ed calls to the CREST server:

    $result = $sso->doCall($url, $fields, $accessToken)

or, if you are using a write scope, there is a 4th parameter that when set to true will execute a POST call:

    $result = $sso->doCall($url, $fields, $accessToken, true);

These calls will also work as a utility for calling the XML API.

Each call returns the result as a string which will need to be json_decode'ed by your application. I have left this step out so that your application can json_decode to an object:

    $jsonObject = json_decode($result);

or to an array:

    $jsonArray = json_decode($result, true);
  
That's all there is to it! These simple calls will allow you to get started quickly with Eve Online's CREST SSO.

#### Errors

If the curl call is unsuccessful for any reason it will throw an exception. I recommend properly surrounding your code with try/catch blocks to handle any exceptions. The CREST API can and will go down and/or become unresponsive for various reasons (especially during downtime).

#### Issues

* I tried your example but I got a class not found error

You can either put a use statement at the beginning of your code:

    use zkillboard\crestsso\CrestSSO;

or fully qualify the class name when instantiating:

    $sso = new \zkillboard\crestsso\CrestSSO($clientID, $secretKey, $callbackURL, $scopes);
    
* $userInfo came back without a refreshToken

If you do not provide any scopes, or only request the publicData scope, then the call is basically good for authentication only and no refreshToken is needed, therefore the auth server doesn't give out a refreshToken.

#### TODO

* Provide a function that returns an array of what can be accessed on CREST (e.g. by walking the API)
