<?php
/* 
*********************************************************************
****************************** NOTICE  ******************************
*********************************************************************

Before testing this example, you must full fill following requirements

1. Generate private and public keys pair in same directory as (testkey and testkey.pub)
2. install php-jwt ie. run `composer require firebase/php-jwt`

*/

use \Firebase\JWT\JWT;
use \Jacwright\RestServer\RestException;

class TestController
{
    /**
     * Mocking up user table
     */
    $listUser = array(
        'admin@domain.tld' => array('email' => 'admin@domain.tld', 'password' => 'adminPass', 'role' => 'admin'),
        'user@domain.tld' => array('email' => 'user@domain.tld', 'password' => 'userPass', 'role' => 'user')
    );

    /**
     * Security
     */
    $private_key = __DIR__ . DIRECTORY_SEPARATOR . 'testkey';
    $public_key = __DIR__ . DIRECTORY_SEPARATOR . 'testkey.pub';
    $hash_type = 'RS256';

    /**
     * Logged in user
     */
    $loggedUser = null;

    /**
     * Check client credentials and return true if found valid, false otherwise
     */
    public function authenticate($credentials, $auth_type)
    {
        switch ($auth_type) {
            case 'Bearer':
                $public_key = file_get_contents($this->public_key);
                $token = JWT::decode($credentials, $public_key, array($this->hash_type));
                if ($token && !empty($token->username) && $this->listUser[$token->username]) {
                  $this->loggedUser = $this->listUser[$token->username];
                  return true;
                }
                break;

            case 'Basic':
            default:
                $email = $credentials['username'];
                if (isset($this->listUser[$email]) && $this->listUser[$email]['password'] == $credentials['password']) {
                  $this->loggedUser = $this->listUser[$email];
                  return true;
                }
                break;
        }

        return false;
    }

    /**
     * Check if current user is allowed to access a certain method
     */
    public function authorize($method)
    {
        if ('admin' == $this->loggedUser['role']) {
          return true; // admin can access everthing

        } else if ('user' == $this->loggedUser['role']) {
          // user can access selected methods only
          if (in_array($method, array('download'))) {
            return true;
          }
        }

        return false;
    }

    /**
     * To get JWT token client can post his username and password to this method
     *
     * @url POST /login
     * @noAuth
     */
    public function login($data = array())
    {
        $username = isset($data['username']) ? $data['username'] : null;
        $password = isset($data['password']) ? $data['password'] : null;

        // only if we have valid user
        if (isset($this->listUser[$username]) && $this->listUser[$username] == $password) {
            $token = array(
                "iss" => 'My Website',
                "iat" => time(),
                "nbf" => time(),
                "exp" => time() + (60 * 60 * 24 * 30 * 12 * 1), // valid for one year
                "username" => $email
            );

            // return jwt token
            $private_key = file_get_contents($this->private_key);
            return JWT::encode($token, $private_key, $this->hash_type);
        }

        throw new RestException(401, "Invalid username or password");
    }

    /**
     * Upload a file
     *
     * @url PUT /files/$filename
     */
    public function upload($filename, $data, $mime)
    {
        $storage_dir  = sys_get_temp_dir();
        $allowedTypes = array('pdf' => 'application/pdf', 'html' => 'plain/html', 'wav' => 'audio/wav');
        if (in_array($mime, $allowedTypes)) {
          if (!empty($data)) {
            $file_path = $storage_dir . DIRECTORY_SEPARATOR . $filename;
            file_put_contents($file_path, $data);
            return $filename;
          } else {
            throw new RestException(411, "Empty file");
          }
        } else {
          throw new RestException(415, "Unsupported File Type");
        }
    }

    /**
     * Download a file
     *
     * @url GET /files/$filename
     */
    public function download($filename)
    {
        $storage_dir = sys_get_temp_dir();
        $file_path = $storage_dir . DIRECTORY_SEPARATOR . $filename;
        if (file_exists($file_path)) {
          return SplFileInfo($file_path);
        } else {
          throw new RestException(404, "File not found");
        }
    }

}
