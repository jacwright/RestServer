<?php
namespace Jacwright\RestServer\apidocs;

class DocumentationGenerator {
	private $apiMap = null;
	private $apiBase = null;

	public function __construct($apiMap, $apiBase) {
		$this->apiMap = $apiMap;
		$this->apiBase = $apiBase;
	}

	public function render($path) {
		$components = explode('/', urldecode($path), 2);
		$method = null;
		$endpoint = null;
		$map = $this->apiMap;
		$interactive = false;
		if (array_key_exists($components[0], $map)) {
			$method = $components[0];
			$map = $map[$method];
			if (count($components) === 2 && array_key_exists($components[1], $map)) {
				$endpoint = $components[1];
				$map = array($endpoint => $map[$endpoint]);
				$interactive = true;
			}

			$map = array($method => $map);
		}

		$this->renderMap($map, $interactive);
	}

	private function renderMap($map, $interactive){
		$this->preamble("Api Docs");
		$this->header();

		foreach ($map as $apiMethod => $endpoints) {
			$this->method($apiMethod);
			foreach ($endpoints as $endpoint => $apiCall) {
				$this->endpoint($apiMethod, $endpoint);

				foreach ($apiCall['docs'] as $docname => $docstring) {
					if ($docname === "url") continue;

					if (is_array($docstring)) {
						foreach ($docstring as $message) {
							$this->endpointinfo($docname, $message);
						}
					} else {
						$this->endpointinfo($docname, $docstring);
					}
				}

				if ($interactive) {
					$this->endpointinteract($apiMethod, $this->apiBase, $endpoint, $apiCall['args']);
				}

				$this->endpointend();
			}

			$this->methodend();
		}

		$this->footer();
	}

	private function preamble($pagetitle) {
		require __DIR__ . '/templates/preamble.php';
	}

	private function header() {
		require __DIR__ . '/templates/header.php';
	}

	private function method($method) {
		require __DIR__ . '/templates/methodbegin.php';
	}

	private function methodend() {
		require __DIR__ . '/templates/methodend.php';
	}

	private function endpoint($method, $endpoint) {
		require __DIR__ . '/templates/endpointbegin.php';
	}

	private function endpointinfo($docname, $docstring) {
		require __DIR__ . '/templates/endpointinfo.php';
	}

	private function endpointinteract($method, $apibase, $endpoint, $apiArgs) {
		require __DIR__ . '/templates/endpointinteractbegin.php';
		foreach ($apiArgs as $arg => $position) {
			require __DIR__ . '/templates/endpointinteractarg.php';
		}
		require __DIR__ . '/templates/endpointinteractend.php';
	}

	private function endpointend() {
		require __DIR__ . '/templates/endpointend.php';
	}

	private function footer() {
		require __DIR__ . '/templates/footer.php';
	}
}
