<?php namespace TwswebInt\CamelotAuth\Auth;

use TwswebInt\CamelotAuth\Session\SessionInterface;
use TwswebInt\CamelotAuth\Cookie\CookieInterface;
use TwswebInt\CamelotAuth\Database\DatabaseInterface;

use TwswebInt\CamelotAuth\Auth\Oauth2Client\AbstractOauth2Provider;

class Oauth2ClientAuth extends AbstractAuth{

	/**
	* The Oauth 2 Provider 
	*
	* @var use TwswebInt\CamelotAuth\Auth\Oauth2Driver\ProviderInterface;
	*/
	protected $provider;

	public function __construct(SessionInterface $session,CookieInterface $cookie,DatabaseInterface $database,$providerName,array $settings,$httpPath)
	{
		parent::__construct($session,$cookie,$database,$providerName,$settings,$httpPath);

		// load the provider here 
		$this->provider = $this->loadProvider($providerName);
	}

	protected function loadProvider($providerName)
	{
		$providerFile = __DIR__.'/Oauth2Client/providers/'.ucfirst($providerName).'Oauth2Provider.php';
		if(!file_exists($providerFile))
		{
			throw new \Exception("Cannot Find the ".ucfirst($providerName)." Oauth2 provider");
		}

		include_once $providerFile;

		$providerClass = 'TwswebInt\CamelotAuth\Auth\Oauth2Client\providers\\'.ucfirst($providerName).'Oauth2Provider';
		if(!class_exists($providerClass,false))
		{
			throw new \Exception("Cannot Find the Provider class (".$providerName."Oauth2Provider)");
		}

		return $provider = new $providerClass(
				$this->session,
                $this->cookie,
                $this->database,
                $this->settings,
                $this->httpPath
                );
	}

	public function authenticate()
	{
		if(strpos($this->httpPath,"callback")=== false)
		{
			return $this->provider->authorize();
		}else{
			$token = $this->provider->callback();
			$userData = $this->provider->getUserInfo($token);

			$userData['provider'] = $this->provider->name;
			return $this->validateUser($userData);
		}
	}

	public function register()
	{
		return $this->authenticate();
	}

	protected function validateUser($userData)
	{
		//echo '<pre>';
		//var_dump($userData);
		// check to see if the oauth details match a db record
		$oauthUser = $this->database->createModel('oauth2User');
		$user = $oauthUser->where('provider','=',$userData['provider'])
				  ->where('user_id','=',$userData['user_id'])
				  ->where('username','=',$userData['username'])
				  ->with('account')->first();
	
		
		// if true create a new session 
		if(!is_null($user))
		{
			return $this->createSession($user->Account);
		}
		else
		{
			echo 'register new account';
		}
		var_dump(\DB::getQueryLog());
		// else lets register a account

	}
}