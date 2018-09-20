<?php

namespace dsda\auth;

class auth {

	var $auth = false;

	function __construct($is_ajax=false){

		$this->config = new \dsda\config\config();
		$this->db = new \dsda\dbconnector\dbconnector();

		if ($is_ajax==true) {
			if ($this->getCookie()) {
				return true;
			} else {
				header('HTTP/1.1 501 Fuck off!');
				exit();
			}
		}

		// if logout
		if(isset($_GET['action']) && $_GET['action'] == 'logout') {
			$this->logout();
		}
		
		// no session for users
		if(!$this->getCookie()){
			if(!isset($_POST['f_auth'])) {
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
			$db->query($query);
		}
	}


	/**
	 * set cookie
	 */
	function login(){
		$cookievalue = md5( $this->auth->login . $this->auth->password . $this->config->get('cookiename') . $this->config->get('salt') );
		setcookie($this->config->get('cookiename'),$cookievalue, time()+360*60*24, '/', false ,false, true); // one day
		$query = "UPDATE `users` SET `last_activity` = ".time().", `uid` = '".$cookievalue."' WHERE `id` = ".$this->auth->id.";";
		$this->db->query($query);
	}

	/**
	 * remove cookie function
	 */
	function logout(){
		if ($this->getCookie()) setcookie($this->config->get('cookiename'),$_COOKIE[$this->config->get('cookiename')], time()-360, '/', false ,false, true);
		echo "<script type=\"text/javascript\">parent.location='admin.php';</script>";
		exit;
	}

	/**
	 * get and filter cookie or false if false
	 */
	function getCookie(){
		if (!isset($_COOKIE[$this->config->get('cookiename')])) return false;
		$cookie = filter_var($_COOKIE[$this->config->get('cookiename')], FILTER_VALIDATE_REGEXP,array("options"=>array("regexp"=>"/^([a-f0-9]+)$/i")));
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
		$uid = $this->getCookie();
		if ($uid) {
			$is_auth = $this->db->query("SELECT * FROM `users` WHERE `uid` = '".$uid."';");
			if ($is_auth!==NULL && isset($is_auth[0])) {
				$out = $is_auth[0];
			}
		}
		return $out;
	}

	/**
	* Send HTTP authentification FORM
	*
	* @access public
	*/
	function authenticate(){
		if (file_exists($this->config->get('themepath').'/'.'assets/favicon/favicon-96x96.png')) {
			$logo = '<div><img src="'.$this->config->get('themeurl').'/'.'assets/favicon/favicon-96x96.png'.'"></div>';
		} else {
			$logo = '';
		}
		if (file_exists($this->config->get('path').'/gear/admin/auth.form.php')) {
			include($this->config->get('path').'/gear/admin/auth.form.php');
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
		$existAccounts = $this->db->query("SELECT * FROM `users`;");
		if($existAccounts===NULL || !isset($existAccounts[0])) {
			// create table for attachment management
			$query[] = "CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, login VARCHAR (255) UNIQUE, password VARCHAR (255));";
			$query[] = "INSERT INTO users VALUES (NULL, 'admin', '76d80224611fc919a5d54f0ff9fba446';"; // qwe
			foreach($query as $req) $this->db->query($req);
		}
		return;
	}

	
}
?>
