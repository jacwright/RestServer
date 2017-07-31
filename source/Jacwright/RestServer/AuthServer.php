<?php
namespace Jacwright\RestServer;

interface AuthServer {
	/**
	 * Indicates whether the client is authorized to access the resource.
	 *
	 * @param string $path     The requested path.
	 * @param object $classObj An instance of the controller for the path.
	 *
	 * @return bool True if authorized, false if not.
	 */
	public function isAuthorized($classObj);

	/**
	 * Handles the case where the client is not authorized.
	 * This method must either return data or throw a RestException.
	 *
	 * @param string $path The requested path.
	 *
	 * @return mixed The response to send to the client
	 *
	 * @throws RestException
	 */
	public function unauthorized($classObj);
}
