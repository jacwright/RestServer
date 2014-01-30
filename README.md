A PHP REST server for providing a very light-weight REST API. Very easy to set up and get going.
Independent from other libraries and frameworks.
Supports HTTP authentication.

The RestServer class assumes you are using URL rewriting and looks at the URL from the
request to map to the necessary actions. The map that gets a request from URL to class
method is all in the doc-comments of the classes. Here is an example of a class that
would handle some user actions:

```php
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
        $password = $_POST['password'];
        // validate input and log the user in
    }

    /**
     * Gets the user by id or current user
     *
     * @url GET /users/:id
     * @url GET /users/current
     */
    public function getUser($id = null)
    {
        if ($id) {
            $user = User::load($id); // possible user loading method
        } else {
            $user = $_SESSION['user'];
        }

        return $user; // serializes object into JSON
    }

    /**
     * Saves a user to the database
     *
     * @url POST /users
     * @url PUT /users/:id
     */
    public function saveUser($id = null, $data)
    {
        // ... validate $data properties such as $data->username, $data->firstName, etc.
        $data->id = $id;
        $user = User::saveUser($data); // saving the user to the database
        return $user; // returning the updated or newly created user object
    }
}
```
