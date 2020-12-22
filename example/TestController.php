<?php

use \Jacwright\RestServer\RestException;

class TestController
{
    /**
     * Returns a JSON string object to the browser when hitting the root of the domain
     *
     * @url GET /
     */
    public function test()
    {
        return "Hello World";
    }

    /**
     * Logs in a user with the given username and password POSTed. Though true
     * REST doesn't believe in sessions, it is often desirable for an AJAX server.
     *
     * @url POST /login
     */
    public function login()
    {
        $username = $_POST['username'];
        $password = $_POST['password']; //@todo remove since it is not needed anywhere
        return array("success" => "Logged in " . $username);
    }

    /**
     * Gets the user by id or current user
     *
     * @url GET /users/$id
     * @url GET /users/current
     */
    public function getUser($id = null)
    {
        // if ($id) {
        //     $user = User::load($id); // possible user loading method
        // } else {
        //     $user = $_SESSION['user'];
        // }

        return array("id" => $id, "name" => null); // serializes object into JSON
    }

    /**
     * Saves a user to the database
     *
     * @url POST /users
     * @url PUT /users/$id
     */
    public function saveUser($id = null, $data)
    {
        // ... validate $data properties such as $data->username, $data->firstName, etc.
        // $data->id = $id;
        // $user = User::saveUser($data); // saving the user to the database
        $user = array("id" => $id, "name" => null);
        return $user; // returning the updated or newly created user object
    }

    /**
     * Gets user list
     *
     * @url GET /users
     */
    public function listUsers($query)
    {
        $users = array('Andra Combes', 'Valerie Shirkey', 'Manda Douse', 'Nobuko Fisch', 'Roger Hevey');
        if (isset($query['search'])) {
          $users = preg_grep("/{$query[search]}/i", $users);
        }
        return $users; // serializes object into JSON
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

    /**
     * Get Charts
     * 
     * @url GET /charts
     * @url GET /charts/$id
     * @url GET /charts/$id/$date
     * @url GET /charts/$id/$date/$interval/
     * @url GET /charts/$id/$date/$interval/$interval_months
     */
    public function getCharts($id=null, $date=null, $interval = 30, $interval_months = 12)
    {
        echo "$id, $date, $interval, $interval_months";
    }

    /**
     * Throws an error
     * 
     * @url GET /error
     */
    public function throwError() {
        throw new RestException(401, "Empty password not allowed");
    }
}
