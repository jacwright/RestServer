<?php
namespace Jacwright\RestServer;

interface AuthServer {
	/**
	 * Indicates whether the requesting client is a recognized and authenticated party.
	 *
	 * @param object $classObj An instance of the controller for the path.
	 *
	 * @return bool True if authenticated, false if not.
	 */
	public function isAuthenticated($classObj);

	/**
	 * Handles the case where the client is not recognized party.
	 * This method must either return data or throw a RestException.
	 *
	 * @param string $path The requested path.
	 *
	 * @return mixed The response to send to the client
	 *
	 * @throws RestException
	 */
	public function unauthenticated($path);

	/**
	 * Indicates whether the client is authorized to access the resource.
	 *
	 * @param object $classObj An instance of the controller for the path.
	 * @param string $method   The requested method.
	 *
	 * @return bool True if authorized, false if not.
	 */
	public function isAuthorized($classObj, $method);

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
	public function unauthorized($path);
}
