<?php

namespace dsda\auth;

class auth {

	public	$auth	= false;
	private	$db		= null;
	private	$url	= null;

	
	function __construct($config=false, $db=false) {
		if ($config==false) throw new \Exception("No config...");
		if ($db==false) throw new \Exception("No db...");
		$this->db = $db;
		$neededConfigurationKeys = array('url','themepath', 'cookiename', 'salt');
		foreach ($neededConfigurationKeys as $key => $value) {
			if ($config->get($value)!==false) {
				$this->$value = $config->get($value);
			} else {
				throw new \Exception("Error! No ".$value." defined in config...");
			}
		}
	}
	
	
	
	function check($isAjax=false){
		if ($isAjax==true) {
			if ($this->getCookie()) {
				return true;
			} else {
				return false;
			}
		}

		// if logout
		if(isset($_GET['action']) && $_GET['action'] == 'logout') {
			$this->logout();
		}
		
		// no session for users
		if(!$this->getCookie()){
			if(!isset($_POST['auth'])) {
				$this->authenticate();
			} else {
				$this->checkExistTable();
				$this->auth = $this->getAuthParam();
				$this->login();
			}
		} else {
			$this->auth = $this->checkCookie();
			if (!$this->auth) {
				$this->logout();
			}
			$query = "UPDATE `users` SET `last_activity` = ".time()." WHERE `id` = ".$this->auth->id.";";
			$this->db->query($query);
		}		
	}
	
	/**
	 * set cookie
	 */
	function login(){
		$cookievalue = md5( $this->auth->login . $this->auth->password . $this->cookiename . $this->salt );
		setcookie($this->cookiename,$cookievalue, time()+360*60*24, '/', false ,false, true); // one day
		$query = "UPDATE `users` SET `last_activity` = ".time().", `sid` = '".$cookievalue."' WHERE `id` = ".$this->auth->id.";";
		$this->db->query($query);
	}

	/**
	 * remove cookie function
	 */
	function logout(){
		if ($this->getCookie()) setcookie($this->cookiename,$_COOKIE[$this->cookiename], time()-360, '/', false ,false, true);
		echo "<script type=\"text/javascript\">parent.location='".$this->url."';</script>";
		exit;
	}

	/**
	 * get and filter cookie or false if false
	 */
	function getCookie(){
		if (!isset($_COOKIE[$this->cookiename])) return false;
		$cookie = filter_var($_COOKIE[$this->cookiename], FILTER_VALIDATE_REGEXP,array("options"=>array("regexp"=>"/^([a-f0-9]+)$/i")));
		if ($cookie!==false && $cookie!==NULL) {
			return $cookie;
		} else {
			return false;
		}
	}

	/**
	* get user connected information
	*
	* @access public
	*/
	function getAuthParam(){
		if(isset($_POST['login']))		$login 	= $_POST['login'];			else $login		= '';
		if(isset($_POST['password']))	$passwd	= md5($_POST['password']);	else $passwd	= '';
		
		$out = false;

		$query = "SELECT * FROM `users` WHERE `login` = '".$login."' AND `password` = '".$passwd."';";
		$infoUser = $this->db->query($query);

		if ($infoUser===NULL || !isset($infoUser[0])) {
			$this->logout();
		} else {
			$out = $infoUser[0];
		}
		return $out;
	}

	/**
	 * check cookie data
	 */
	function checkCookie(){
		$out = false;
		$sid = $this->getCookie();
		if ($sid) {
			$is_auth = $this->db->query("SELECT * FROM `users` WHERE `sid` = '".$sid."';");
			if ($is_auth!==NULL && isset($is_auth[0])) {
				$out = $is_auth[0];
			}
		}
		return $out;
	}

	/**
	* Send HTTP authentication FORM
	*
	* @access public
	*/
	function authenticate(){
		if (file_exists($this->themepath.'/'.'../favicon/favicon-96x96.png')) {
			$logo = '<div><img src="'.$this->themeurl.'/'.'../favicon/favicon-96x96.png'.'"></div>';
		} else {
			$logo = '';
		}
		if (file_exists($this->themepath.'/form.auth.php')) {
			include($this->themepath.'/form.auth.php');
		} else {
			echo '<form method="post">
			<input name="f_auth" value="true" type="hidden">
			<input name="login" type="text" value="" placeholder="login"><br>
			<input name="password" type="password" value="" placeholder="password"><br>
			<input type="submit"></form>';
		}
		exit();
	}

	/**
	* upgrade config database if not exist table 'users' and 'groupes'
	*
	* @access private
	*/
	private function checkExistTable(){
		$existAccounts = $this->db->tableExist('users');
		if($existAccounts===false) {
			// create table for attachment management
			$query[] = "CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, login VARCHAR (255) NOT NULL, password VARCHAR (255) NOT NULL, email VARCHAR (255), last_login DATETIME, last_activity DATETIME, sid VARCHAR (255), udid VARCHAR (255));";
			$query[] = "INSERT INTO users VALUES (NULL, 'admin', '76d80224611fc919a5d54f0ff9fba446', 'null@null.tld', NULL, NULL, NULL, NULL);"; // qwe
			foreach($query as $req) $this->db->query($req);
		}
		return;
	}

	
}
?>
