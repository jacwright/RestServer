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

/**
 * Constants used in RestServer Class.
 */
class RestFormat
{

	const PLAIN = 'text/plain';
	const HTML = 'text/html';
	const AMF = 'applicaton/x-amf';
	const JSON = 'application/json';
	const XML = 'application/xml';
	static public $formats = array(
		'plain' => RestFormat::PLAIN,
		'txt' => RestFormat::PLAIN,
		'html' => RestFormat::HTML,
		'amf' => RestFormat::AMF,
		'json' => RestFormat::JSON,
		'xml'  => RestFormat::XML,
	);
}

/**
 * Description of RestServer
 *
 * @author jacob
 */
class RestServer
{
	public $url;
	public $method;
	public $params;
	public $format;
	public $cacheDir = __DIR__;
	public $realm;
	public $mode;
	public $root;
	private $data;
	
	protected $map = array();
	protected $errorClasses = array();
	protected $cached;

	/**
	 * The constructor.
	 * 
	 * @param string $mode The mode, either debug or production
	 */
	public function  __construct($mode = 'debug', $realm = 'Rest Server')
	{
		$this->mode = $mode;
		$this->realm = $realm;
		$dir = dirname(str_replace($_SERVER['DOCUMENT_ROOT'], '', $_SERVER['SCRIPT_FILENAME']));
		if ($dir == '.') $this->root = '/';
		elseif (substr($dir, -1) != '/') $this->root = $dir . '/';
		else $this->root = $dir;
	}
	
	public function  __destruct()
	{
		if ($this->mode == 'production' && !$this->cached) {
			if (function_exists('apc_store')) {
				apc_store('urlMap', $this->map);
			} else {
				file_put_contents($this->cacheDir . '/urlMap.cache', serialize($this->map));
			}
		}
	}
	
	public function refreshCache()
	{
		$this->map = array();
		$this->cached = false;
	}
	
	public function unauthorized($ask = false)
	{
		if ($ask) {
			header("WWW-Authenticate: Basic realm=\"$this->realm\"");
		}
		throw new RestException(401, "You are not authorized to access this resource.");
	}
	
	
	public function handle()
	{
		$this->url = $this->getPath();
		$this->method = $this->getMethod();
		$this->format = $this->getFormat();
		
		if ($this->method == 'PUT' || $this->method == 'POST') {
			$this->data = $this->getData();
		}
		
		list($obj, $method, $params, $this->params, $noAuth) = $this->findUrl();
		
		if ($obj) {
			if (is_string($obj)) {
				if (class_exists($obj)) {
					$obj = new $obj();
				} else {
					throw new Exception("Class $obj does not exist");
				}
			}
			
			$obj->server = $this;
			
			try {
				if (method_exists($obj, 'init')) {
					$obj->init();
				}
				
				if (!$noAuth && method_exists($obj, 'authorize')) {
					if (!$obj->authorize()) {
						$this->sendData($this->unauthorized(true));
						exit;
					}
				}
				/*in case of sending json using post method the class can get this object by getData()
				but can not use the gathered info in sendData() */
				if($this->data){
					$params['data'] = $this->data;
				}
				$result = call_user_func_array(array($obj, $method), $params);
				
				if ($result !== null) {
					$this->sendData($result);
				}
			} catch (RestException $e) {
				$this->handleError($e->getCode(), $e->getMessage());
			}			
		
		} else {
			$this->handleError(404);
		}
	}

	public function addClass($class, $basePath = '')
	{
		$this->loadCache();
		
		if (!$this->cached) {
			if (is_string($class) && !class_exists($class)){
				throw new Exception('Invalid method or class');
			} elseif (!is_string($class) && !is_object($class)) {
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
	
	public function addErrorClass($class)
	{
		$this->errorClasses[] = $class;
	}
	
	public function handleError($statusCode, $errorMessage = null)
	{
		$method = "handle$statusCode";
		foreach ($this->errorClasses as $class) {
			if (is_object($class)) {
				$reflection = new ReflectionObject($class);
			} elseif (class_exists($class)) {
				$reflection = new ReflectionClass($class);
			}
			
			if ($reflection->hasMethod($method))
			{
				$obj = is_string($class) ? new $class() : $class;
				$obj->$method();
				return;
			}
		}
		
		$message = $this->codes[$statusCode] . ($errorMessage && $this->mode == 'debug' ? ': ' . $errorMessage : '');
		
		$this->setStatus($statusCode);
		$this->sendData(array('error' => array('code' => $statusCode, 'message' => $message)));
	}
	
	protected function loadCache()
	{
		if ($this->cached !== null) {
			return;
		}
		
		$this->cached = false;
		
		if ($this->mode == 'production') {
			if (function_exists('apc_fetch')) {
				$map = apc_fetch('urlMap');
			} elseif (file_exists($this->cacheDir . '/urlMap.cache')) {
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
	
	protected function findUrl()
	{
		$urls = $this->map[$this->method];
		if (!$urls) return null;
		
		foreach ($urls as $url => $call) {
			$args = $call[2];
			
			if (!strstr($url, '$')) {
				if ($url == $this->url) {
					if (isset($args['data'])) {
						$params = array_fill(0, $args['data'] + 1, null);
						$params[$args['data']] = $this->data;
						$call[2] = $params;
					}
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
						if (!key_exists($i, $params)) {
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

	protected function generateMap($class, $basePath)
	{
		if (is_object($class)) {
			$reflection = new ReflectionObject($class);
		} elseif (class_exists($class)) {
			$reflection = new ReflectionClass($class);
		}
		
		$methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
		
		foreach ($methods as $method) {
			$doc = $method->getDocComment();
			$noAuth = strpos($doc, '@noAuth') !== false;
			if (preg_match_all('/@url[ \t]+(GET|POST|PUT|DELETE|HEAD|OPTIONS)[ \t]+\/?(\S*)/s', $doc, $matches, PREG_SET_ORDER)) {
				
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

	public function getPath()
	{
		$path = preg_replace('/\?.*$/', '', $_SERVER['REQUEST_URI']);
		// remove root from path
		if ($this->root) $path = preg_replace('/^' . preg_quote($this->root, '/') . '/', '', $path);
		// remove trailing format definition, like /controller/action.json -> /controller/action
		$path = preg_replace('/\.(\w+)$/i', '', $path);
		return $path;
	}
	
	public function getMethod()
	{
		$method = $_SERVER['REQUEST_METHOD'];
		$override = isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']) ? $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] : (isset($_GET['method']) ? $_GET['method'] : '');
		if ($method == 'POST' && strtoupper($override) == 'PUT') {
			$method = 'PUT';
		} elseif ($method == 'POST' && strtoupper($override) == 'DELETE') {
			$method = 'DELETE';
		}
		return $method;
	}
	
	public function getFormat()
	{
		$format = RestFormat::PLAIN;
		$accept_mod = preg_replace('/\s+/i', '', $_SERVER['HTTP_ACCEPT']); // ensures that exploding the HTTP_ACCEPT string does not get confused by whitespaces
		$accept = explode(',', $accept_mod);
		$override = '';

		if (isset($_REQUEST['format']) || isset($_SERVER['HTTP_FORMAT'])) {
			// give GET/POST precedence over HTTP request headers
			$override = isset($_SERVER['HTTP_FORMAT']) ? $_SERVER['HTTP_FORMAT'] : '';
			$override = isset($_REQUEST['format']) ? $_REQUEST['format'] : $override;
			$override = trim($override);
		}
		
		// Check for trailing dot-format syntax like /controller/action.format -> action.json
		if(preg_match('/\.(\w+)$/i', $_SERVER['REQUEST_URI'], $matches)) {
			$override = $matches[1];
		}

		// Give GET parameters precedence before all other options to alter the format
		$override = isset($_GET['format']) ? $_GET['format'] : $override;
		if (isset(RestFormat::$formats[$override])) {
			$format = RestFormat::$formats[$override];
		} elseif (in_array(RestFormat::AMF, $accept)) {
			$format = RestFormat::AMF;
		} elseif (in_array(RestFormat::JSON, $accept)) {
			$format = RestFormat::JSON;
		}
		return $format;
	}
	
	public function getData()
	{
		$data = file_get_contents('php://input');
		
		if ($this->format == RestFormat::AMF) {
			require_once 'Zend/Amf/Parse/InputStream.php';
			require_once 'Zend/Amf/Parse/Amf3/Deserializer.php';
			$stream = new Zend_Amf_Parse_InputStream($data);
			$deserializer = new Zend_Amf_Parse_Amf3_Deserializer($stream);
			$data = $deserializer->readTypeMarker();
		} else {
			$data = json_decode($data);
		}
		
		return $data;
	}
	

	public function sendData($data)
	{
		header("Cache-Control: no-cache, must-revalidate");
		header("Expires: 0");
		header('Content-Type: ' . $this->format);

		if ($this->format == RestFormat::AMF) {
			require_once 'Zend/Amf/Parse/OutputStream.php';
			require_once 'Zend/Amf/Parse/Amf3/Serializer.php';
			$stream = new Zend_Amf_Parse_OutputStream();
			$serializer = new Zend_Amf_Parse_Amf3_Serializer($stream);
			$serializer->writeTypeMarker($data);
			$data = $stream->getStream();}

		elseif ($this->format == RestFormat::XML) {

		if (is_object($data) && method_exists($data, '__keepOut')) {
				$data = clone $data;
				foreach ($data->__keepOut() as $prop) {
					unset($data->$prop);
				}
			}
			$data = $this->xml_encode($data);
			if ($data && $this->mode == 'debug') {
				$data = $this->json_format($data);
			}
		
		} else {
			if (is_object($data) && method_exists($data, '__keepOut')) {
				$data = clone $data;
				foreach ($data->__keepOut() as $prop) {
					unset($data->$prop);
				}
			}
			$data = json_encode($data);
			if ($data && $this->mode == 'debug') {
				$data = $this->json_format($data);
			}
		}

		echo $data;
	}

	public function setStatus($code)
	{
		$code .= ' ' . $this->codes[strval($code)];
		header("{$_SERVER['SERVER_PROTOCOL']} $code");
	}
	
	private function xml_encode($mixed, $domElement=null, $DOMDocument=null) {
    if (is_null($DOMDocument)) {
        $DOMDocument =new DOMDocument;
        $DOMDocument->formatOutput = true;
        $this->xml_encode($mixed, $DOMDocument, $DOMDocument);
        echo $DOMDocument->saveXML();
    }
    else {
        if (is_array($mixed)) {
            foreach ($mixed as $index => $mixedElement) {
                if (is_int($index)) {
                    if ($index === 0) {
                        $node = $domElement;
                    }
                    else {
                        $node = $DOMDocument->createElement($domElement->tagName);
                        $domElement->parentNode->appendChild($node);
                    }
                }
                else {
                    $plural = $DOMDocument->createElement($index);
                    $domElement->appendChild($plural);
                    $node = $plural;
                    if (!(rtrim($index, 's') === $index)) {
                        $singular = $DOMDocument->createElement(rtrim($index, 's'));
                        $plural->appendChild($singular);
                        $node = $singular;
                    }
                }
 
                $this->xml_encode($mixedElement, $node, $DOMDocument);
            }
        }
        else {
            $domElement->appendChild($DOMDocument->createTextNode($mixed));
        }
    }
}
	// Pretty print some JSON
	private function json_format($json)
	{
		$tab = "  ";
		$new_json = "";
		$indent_level = 0;
		$in_string = false;
		
		$len = strlen($json);
		
		for($c = 0; $c < $len; $c++) {
			$char = $json[$c];
			switch($char) {
				case '{':
				case '[':
					if(!$in_string) {
						$new_json .= $char . "\n" . str_repeat($tab, $indent_level+1);
						$indent_level++;
					} else {
						$new_json .= $char;
					}
					break;
				case '}':
				case ']':
					if(!$in_string) {
						$indent_level--;
						$new_json .= "\n" . str_repeat($tab, $indent_level) . $char;
					} else {
						$new_json .= $char;
					}
					break;
				case ',':
					if(!$in_string) {
						$new_json .= ",\n" . str_repeat($tab, $indent_level);
					} else {
						$new_json .= $char;
					}
					break;
				case ':':
					if(!$in_string) {
						$new_json .= ": ";
					} else {
						$new_json .= $char;
					}
					break;
				case '"':
					if($c > 0 && $json[$c-1] != '\\') {
						$in_string = !$in_string;
					}
				default:
					$new_json .= $char;
					break;					
			}
		}
		
		return $new_json;
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

class RestException extends Exception
{
	
	public function __construct($code, $message = null)
	{
		parent::__construct($message, $code);
	}
	
}
