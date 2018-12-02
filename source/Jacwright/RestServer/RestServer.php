<?php
////////////////////////////////////////////////////////////////////////////////
//
// Copyright (c) 2009 Jacob Wright
//
// Permission is hereby granted, free of charge, to any person obtaining a copy
// of this software and associated documentation files (the "Software"), to deal
// in the Software without restriction, including without limitation the rights
// to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
// copies of the Software, and to permit persons to whom the Software is
// furnished to do so, subject to the following conditions:
//
// The above copyright notice and this permission notice shall be included in
// all copies or substantial portions of the Software.
//
// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
// IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
// FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
// AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
// LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
// OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
// THE SOFTWARE.
//
////////////////////////////////////////////////////////////////////////////////

namespace Jacwright\RestServer;

require_once(__DIR__ . '/RestFormat.php');
require_once(__DIR__ . '/RestException.php');
require_once(__DIR__ . '/AuthServer.php');
require_once(__DIR__ . '/Auth/HTTPAuthServer.php');

use Exception;
use ReflectionClass;
use ReflectionObject;
use ReflectionMethod;
use DOMDocument;

/**
 * Description of RestServer
 *
 * @author jacob
 */
class RestServer {
	//@todo add type hint
	public $url;
	public $method;
	public $params;
	public $format;
	public $cacheDir = __DIR__;
	public $mode;
	public $root;
	public $rootPath;
	public $jsonAssoc = false;
	public $authHandler = null;

	public $useCors = false;
	public $allowedOrigin = '*';

	protected $data = null;   // special parameter for post data
	protected $query = null;  // special parameter for query string
	protected $map = array();
	protected $errorClasses = array();
	protected $cached;

	/**
	 * The constructor.
	 *
	 * @param string $mode The mode, either debug or production
	 */
	public function  __construct($mode = 'debug') {
		$this->mode = $mode;

		// Set the root
		$dir = str_replace('\\', '/', dirname(str_replace($_SERVER['DOCUMENT_ROOT'], '', $_SERVER['SCRIPT_FILENAME'])));

		if ($dir == '.') {
			$dir = '/';
		} else {
			// add a slash at the beginning, and remove the one at the end
			if (substr($dir, -1) == '/') $dir = substr($dir, 0, -1);
			if (substr($dir, 0, 1) != '/') $dir = '/' . $dir;
		}

		$this->root = $dir;

		// For backwards compatability, register HTTPAuthServer
		$this->setAuthHandler(new \Jacwright\RestServer\Auth\HTTPAuthServer);
	}

	public function  __destruct() {
		if ($this->mode == 'production' && !$this->cached) {
			if (function_exists('apc_store')) {
				apc_store('urlMap', $this->map);
			} else {
				file_put_contents($this->cacheDir . '/urlMap.cache', serialize($this->map));
			}
		}
	}

	public function setAuthHandler($authHandler) {
		if ($authHandler instanceof AuthServer) {
			$this->authHandler = $authHandler;
		}
	}

	public function refreshCache() {
		$this->map = array();
		$this->cached = false;
	}

	public function options() {
		throw new RestException(200, "authorized");
	}

	public function handle() {
		$this->url = $this->getPath();
		$this->method = $this->getMethod();
		$this->format = $this->getFormat();

		if (($this->useCors) && ($this->method == 'OPTIONS')) {
			$this->corsHeaders();
			exit;
		}

		if ($this->method == 'PUT' || $this->method == 'POST' || $this->method == 'PATCH') {
			$this->data = $this->getData();
		}

		//preflight requests response
		if ($this->method == 'OPTIONS' && getallheaders()->Access-Control-Request-Headers) {
			$this->sendData($this->options());
		}

		list($obj, $method, $params, $this->params, $noAuth) = $this->findUrl();

		if ($obj) {
			if (is_string($obj) && !($newObj = $this->instantiateClass($obj))) {
				throw new Exception("Class $obj does not exist");
			}

			$obj = $newObj;
			$obj->server = $this;

			try {
				$this->initClass($obj);

				if (!$noAuth && !$this->isAuthorized($obj)) {
					$data = $this->unauthorized($obj);
					$this->sendData($data);
				} else {
					$result = call_user_func_array(array($obj, $method), $params);

					$this->sendData($result);
				}
			} catch (RestException $e) {
				$this->handleError($e->getCode(), $e->getMessage());
			}
		} else {
			$this->handleError(404);
		}
	}

	public function setRootPath($path) {
		$this->rootPath = '/' . trim($path, '/');
	}

	public function setJsonAssoc($value) {
		$this->jsonAssoc = ($value === true);
	}

	public function addClass($class, $basePath = '') {
		$this->loadCache();

		if (!$this->cached) {
			if (is_string($class) && !class_exists($class)) {
				throw new Exception('Invalid method or class');
			} else if (!is_string($class) && !is_object($class)) {
				throw new Exception('Invalid method or class; must be a classname or object');
			}

			if (substr($basePath, 0, 1) == '/') {
				$basePath = substr($basePath, 1);
			}

			if ($basePath && substr($basePath, -1) != '/') {
				$basePath .= '/';
			}

			$this->generateMap($class, $basePath);
		}
	}

	public function addErrorClass($class) {
		$this->errorClasses[] = $class;
	}

	public function handleError($statusCode, $errorMessage = null) {
		$method = "handle$statusCode";

		foreach ($this->errorClasses as $class) {
			if (is_object($class)) {
				$reflection = new ReflectionObject($class);
			} else if (class_exists($class)) {
				$reflection = new ReflectionClass($class);
			}

			if (isset($reflection)) {
				if ($reflection->hasMethod($method)) {
					$obj = is_string($class) ? new $class() : $class;
					$obj->$method();
					return;
				}
			}
		}

		if (!$errorMessage) {
			$errorMessage = $this->codes[$statusCode];
		}

		$this->setStatus($statusCode);
		$this->sendData(array('error' => array('code' => $statusCode, 'message' => $errorMessage)));
	}

	protected function instantiateClass($obj) {
		if (class_exists($obj)) {
			return new $obj();
		}

		return false;
	}

	protected function initClass($obj) {
		if (method_exists($obj, 'init')) {
			$obj->init();
		}
	}

	protected function unauthorized($obj) {
		if ($this->authHandler !== null) {
			return $this->authHandler->unauthorized($obj);
		}

		throw new RestException(401, "You are not authorized to access this resource.");
	}

	protected function isAuthorized($obj) {
		if ($this->authHandler !== null) {
			return $this->authHandler->isAuthorized($obj);
		}

		return true;
	}

	protected function loadCache() {
		if ($this->cached !== null) {
			return;
		}

		$this->cached = false;

		if ($this->mode == 'production') {
			if (function_exists('apc_fetch')) {
				$map = apc_fetch('urlMap');
			} else if (file_exists($this->cacheDir . '/urlMap.cache')) {
				$map = unserialize(file_get_contents($this->cacheDir . '/urlMap.cache'));
			}

			if (isset($map) && is_array($map)) {
				$this->map = $map;
				$this->cached = true;
			}
		} else {
			if (function_exists('apc_delete')) {
				apc_delete('urlMap');
			} else {
				@unlink($this->cacheDir . '/urlMap.cache');
			}
		}
	}

	protected function findUrl() {
		$urls = $this->map[$this->method];
		if (!$urls) return null;

		foreach ($urls as $url => $call) {
			$args = $call[2];

			if (!strstr($url, '$')) {
				if ($url == $this->url) {
					$params = array();
					if (isset($args['data'])) {
						$params += array_fill(0, $args['data'] + 1, null);
						$params[$args['data']] = $this->data;
					}
					if (isset($args['query'])) {
						$params += array_fill(0, $args['query'] + 1, null);
						$params[$args['query']] = $this->query;
					}
					$call[2] = $params;
					return $call;
				}
			} else {
				$regex = preg_replace('/\\\\\$([\w\d]+)\.\.\./', '(?P<$1>.+)', str_replace('\.\.\.', '...', preg_quote($url)));
				$regex = preg_replace('/\\\\\$([\w\d]+)/', '(?P<$1>[^\/]+)', $regex);

				if (preg_match(":^$regex$:", urldecode($this->url), $matches)) {
					$params = array();
					$paramMap = array();

					if (isset($args['data'])) {
						$params[$args['data']] = $this->data;
					}
					if (isset($args['query'])) {
						$params[$args['query']] = $this->query;
					}

					foreach ($matches as $arg => $match) {
						if (is_numeric($arg)) continue;

						$paramMap[$arg] = $match;

						if (isset($args[$arg])) {
							$params[$args[$arg]] = $match;
						}
					}

					ksort($params);

					// make sure we have all the params we need
					end($params);
					$max = key($params);
					for ($i = 0; $i < $max; $i++) {
						if (!array_key_exists($i, $params)) {
							$params[$i] = null;
						}
					}

					ksort($params);

					$call[2] = $params;
					$call[3] = $paramMap;

					return $call;
				}
			}
		}
	}

	protected function generateMap($class, $basePath) {
		if (is_object($class)) {
			$reflection = new ReflectionObject($class);
		} else if (class_exists($class)) {
			$reflection = new ReflectionClass($class);
		}

		$methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);    //@todo $reflection might not be instantiated

		foreach ($methods as $method) {
			$doc = $method->getDocComment();
			$noAuth = strpos($doc, '@noAuth') !== false;
			if (preg_match_all('/@url[ \t]+(GET|POST|PUT|PATCH|DELETE|HEAD|OPTIONS)[ \t]+\/?(\S*)/s', $doc, $matches, PREG_SET_ORDER)) {
				$params = $method->getParameters();

				foreach ($matches as $match) {
					$httpMethod = $match[1];
					$url = $basePath . $match[2];
					if ($url && $url[strlen($url) - 1] == '/') {
						$url = substr($url, 0, -1);
					}

					$call = array($class, $method->getName());
					$args = array();

					foreach ($params as $param) {
						$args[$param->getName()] = $param->getPosition();
					}

					$call[] = $args;
					$call[] = null;
					$call[] = $noAuth;

					$this->map[$httpMethod][$url] = $call;
				}
			}
		}
	}

	public function getPath() {
		//@todo should only work with GET method
		$this->query = $_GET;

		$path = preg_replace('/\?.*$/', '', $_SERVER['REQUEST_URI']);

		// remove root from path
		if ($this->root) $path = preg_replace('/^' . preg_quote($this->root, '/') . '/', '', $path);

		// remove trailing format definition, like /controller/action.json -> /controller/action
		// Only remove formats that are valid for RestServer
		$dot = strrpos($path, '.');
		if ($dot !== false) {
			$path_format = substr($path, $dot + 1);

			foreach (RestFormat::$formats as $format => $mimetype) {
				if ($path_format == $format) {
					$path = substr($path, 0, $dot);
					break;
				}
			}
		}

		// remove root path from path, like /root/path/api -> /api
		if ($this->rootPath) $path = str_replace($this->rootPath, '', $path);

		return ltrim($path, '/');
	}

	public function getMethod() {
		$method = $_SERVER['REQUEST_METHOD'];
		$override = isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']) ? $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] : (isset($_GET['method']) ? $_GET['method'] : '');

		if ($method == 'POST' && strtoupper($override) == 'PUT') {
			$method = 'PUT';
		} else if ($method == 'POST' && strtoupper($override) == 'DELETE') {
			$method = 'DELETE';
		} else if ($method == 'POST' && strtoupper($override) == 'PATCH') {
			$method = 'PATCH';
		}

		return $method;
	}

	public function getFormat() {
		$format = RestFormat::PLAIN;
		$accept_mod = null;

		if (isset($_SERVER["HTTP_ACCEPT"])) {
			$accept_mod = preg_replace('/\s+/i', '', $_SERVER['HTTP_ACCEPT']); // ensures that exploding the HTTP_ACCEPT string does not get confused by whitespaces
		}

		$accept = explode(',', $accept_mod);
		$override = '';

		if (isset($_REQUEST['format']) || isset($_SERVER['HTTP_FORMAT'])) {
			// give GET/POST precedence over HTTP request headers
			$override = isset($_SERVER['HTTP_FORMAT']) ? $_SERVER['HTTP_FORMAT'] : '';
			$override = isset($_REQUEST['format']) ? $_REQUEST['format'] : $override;
			$override = trim($override);
		}

		// Check for trailing dot-format syntax like /controller/action.format -> action.json
		if (preg_match('/\.(\w+)$/i', strtok($_SERVER["REQUEST_URI"],'?'), $matches)) {
			$override = $matches[1];
		}

		// Give GET parameters precedence before all other options to alter the format
		$override = isset($_GET['format']) ? $_GET['format'] : $override;
		if (isset(RestFormat::$formats[$override])) {
			$format = RestFormat::$formats[$override];
		} else if (in_array(RestFormat::JSON, $accept)) {
			$format = RestFormat::JSON;
		}

		return $format;
	}

	public function getData() {
		$data = file_get_contents('php://input');
		$data = json_decode($data, $this->jsonAssoc);

		return $data;
	}


	public function sendData($data) {
		header("Cache-Control: no-cache, must-revalidate");
		header("Expires: 0");
		header('Content-Type: ' . $this->format);

		if ($this->useCors) {
			$this->corsHeaders();
		}

		if ($data === null) {
			$this->setStatus(204);
		} else if ($this->format == RestFormat::XML) {
			if (is_object($data) && method_exists($data, '__keepOut')) {
				$data = clone $data;
				foreach ($data->__keepOut() as $prop) {
					unset($data->$prop);
				}
			}

			$this->xml_encode($data);
		} else {
			if (is_object($data) && method_exists($data, '__keepOut')) {
				$data = clone $data;
				foreach ($data->__keepOut() as $prop) {
					unset($data->$prop);
				}
			}

			$options = 0;
			if ($this->mode == 'debug' && defined('JSON_PRETTY_PRINT')) {
				$options = JSON_PRETTY_PRINT;
			}

			if (defined('JSON_UNESCAPED_UNICODE')) {
				$options = $options | JSON_UNESCAPED_UNICODE;
			}

			echo json_encode($data, $options);
		}
	}

	public function setStatus($code) {
		if (function_exists('http_response_code')) {
			http_response_code($code);
		} else {
			$protocol = $_SERVER['SERVER_PROTOCOL'] ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0';
			$code .= ' ' . $this->codes[strval($code)];
			header("$protocol $code");
		}
	}

	private function xml_encode($mixed, $domElement = null, $DOMDocument = null) {  //@todo add type hint for $domElement and $DOMDocument
		if (is_null($DOMDocument)) {
			$DOMDocument = new DOMDocument;
			$DOMDocument->formatOutput = true;
			$this->xml_encode($mixed, $DOMDocument, $DOMDocument);
			echo $DOMDocument->saveXML();
		} else if (is_null($mixed) || $mixed === false || (is_array($mixed) && empty($mixed))) {
			$domElement->appendChild($DOMDocument->createTextNode(null));
		} else if (is_array($mixed)) {
			foreach ($mixed as $index => $mixedElement) {
				if (is_int($index)) {
					if ($index === 0) {
						$node = $domElement;
					} else {
						$node = $DOMDocument->createElement($domElement->tagName);
						$domElement->parentNode->appendChild($node);
					}
				} else {
					$index = str_replace(' ', '_', $index);
					$plural = $DOMDocument->createElement($index);
					$domElement->appendChild($plural);
					$node = $plural;

					if (!(rtrim($index, 's') === $index) && !empty($mixedElement)) {
						$singular = $DOMDocument->createElement(rtrim($index, 's'));
						$plural->appendChild($singular);
						$node = $singular;
					}
				}

				$this->xml_encode($mixedElement, $node, $DOMDocument);
			}
		} else {
			$domElement->appendChild($DOMDocument->createTextNode($mixed));
		}
	}

	private function corsHeaders() {
		// to support multiple origins we have to treat origins as an array
		$allowedOrigin = (array)$this->allowedOrigin;
		// if no origin header is present then requested origin can be anything (i.e *)
		$currentOrigin = !empty($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*';
		if (in_array($currentOrigin, $allowedOrigin)) {
			$allowedOrigin = array($currentOrigin); // array ; if there is a match then only one is enough
		}
		foreach($allowedOrigin as $allowed_origin) { // to support multiple origins
			header("Access-Control-Allow-Origin: $allowed_origin");
		}
		header('Access-Control-Allow-Methods: GET,POST,PUT,DELETE,OPTIONS');
		header('Access-Control-Allow-Credential: true');
		header('Access-Control-Allow-Headers: X-Requested-With, content-type, access-control-allow-origin, access-control-allow-methods, access-control-allow-headers, Authorization');
	}

	private $codes = array(
		'100' => 'Continue',
		'200' => 'OK',
		'201' => 'Created',
		'202' => 'Accepted',
		'203' => 'Non-Authoritative Information',
		'204' => 'No Content',
		'205' => 'Reset Content',
		'206' => 'Partial Content',
		'300' => 'Multiple Choices',
		'301' => 'Moved Permanently',
		'302' => 'Found',
		'303' => 'See Other',
		'304' => 'Not Modified',
		'305' => 'Use Proxy',
		'307' => 'Temporary Redirect',
		'400' => 'Bad Request',
		'401' => 'Unauthorized',
		'402' => 'Payment Required',
		'403' => 'Forbidden',
		'404' => 'Not Found',
		'405' => 'Method Not Allowed',
		'406' => 'Not Acceptable',
		'409' => 'Conflict',
		'410' => 'Gone',
		'411' => 'Length Required',
		'412' => 'Precondition Failed',
		'413' => 'Request Entity Too Large',
		'414' => 'Request-URI Too Long',
		'415' => 'Unsupported Media Type',
		'416' => 'Requested Range Not Satisfiable',
		'417' => 'Expectation Failed',
		'500' => 'Internal Server Error',
		'501' => 'Not Implemented',
		'503' => 'Service Unavailable'
	);
}
