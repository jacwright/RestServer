<?php
namespace Jacwright\RestServer\Auth;

class HTTPAuthServer implements \Jacwright\RestServer\AuthServer {
	protected $realm;

	public function __construct($realm = 'Rest Server') {
		$this->realm = $realm;
	}

	public function isAuthenticated($classObj) {
    $auth_headers = $this->getAuthHeaders();

    // Try to use bearer token as default
    $auth_method = 'Bearer';
    $credentials = $this->getBearer($auth_headers);

    // TODO: add digest method

    // In case bearer token is not present try with Basic autentication
    if (empty($credentials)) {
      $auth_method = 'Basic';
      $credentials = $this->getBasic($auth_headers);
    }

    if (method_exists($classObj, 'authenticate')) {
      return $classObj->authenticate($credentials, $auth_method);
    }

		return true; // original behavior
	}

	public function unauthenticated($path) {
		header("WWW-Authenticate: Basic realm=\"$this->realm\"");
		throw new \Jacwright\RestServer\RestException(401, "Invalid credentials, access is denied to $path.");
	}

	public function isAuthorized($classObj, $method) {
		if (method_exists($classObj, 'authorize')) {
			return $classObj->authorize($method);
		}

		return true;
	}

	public function unauthorized($path) {
		throw new \Jacwright\RestServer\RestException(403, "You are not authorized to access $path.");
	}

	/**
	 * Get username and password from header
	 */
	protected function getBasic($headers) {
    // mod_php
    if (isset($_SERVER['PHP_AUTH_USER'])) {
        return array($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);
    } else { // most other servers
      if (!empty($headers)) {
        list ($username, $password) = explode(':',base64_decode(substr($headers, 6)));
        return array('username' => $username, 'password' => $password);
      }
    }
    return array('username' => null, 'password' => null);
  }

	/**
	 * Get access token from header
	 */
	protected function getBearer($headers) {
		if (!empty($headers)) {
			if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
				return $matches[1];
			}
		}
		return null;
	}

	/**
	 * Get username and password from header via Digest method
	 */
	protected function getDigest() {
    if (false) { // TODO // currently not in function
      return array('username' => null, 'password' => null);
    }
    return null;
  }

	/**
	 * Get authorization header
	 */
	protected function getAuthHeaders() {
		$headers = null;
		if (isset($_SERVER['Authorization'])) {
			$headers = trim($_SERVER["Authorization"]);
		} else if (isset($_SERVER['HTTP_AUTHORIZATION'])) { //Nginx or fast CGI
			$headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
		} else if (function_exists('apache_request_headers')) {
			$requestHeaders = apache_request_headers();
			$requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
			if (isset($requestHeaders['Authorization'])) {
				$headers = trim($requestHeaders['Authorization']);
			}
		}
		return $headers;
  }

}
