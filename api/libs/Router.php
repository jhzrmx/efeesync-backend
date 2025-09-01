<?php

function get($name) {
	return isset($_GET[$name]) ? $_GET[$name] : null;
}

class Route {
	private static $base_path = "";
	private static $route_matched = false;

	public static function enableBasePath() {
		$script_name = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
		self::$base_path = $script_name === '/' ? '' : $script_name;
	}

	public static function get($route, $path_to_include) {
		if ($_SERVER['REQUEST_METHOD'] == 'GET') {
			self::reroute($route, $path_to_include);
		}
	}

	public static function post($route, $path_to_include) {
		if ($_SERVER['REQUEST_METHOD'] == 'POST') {
			self::reroute($route, $path_to_include);
		}
	}

	public static function put($route, $path_to_include) {
		if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
			self::reroute($route, $path_to_include);
		}
	}

	public static function patch($route, $path_to_include) {
		if ($_SERVER['REQUEST_METHOD'] == 'PATCH') {
			self::reroute($route, $path_to_include);
		}
	}

	public static function delete($route, $path_to_include) {
		if ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
			self::reroute($route, $path_to_include);
		}
	}

	public static function any($route, $path_to_include) {
		self::reroute($route, $path_to_include);
	}

	private static function getRootDir() {
		return dirname($_SERVER['SCRIPT_FILENAME']);
	}

	private static function reroute($route, $path_to_include) {
		$callback = $path_to_include;
		if (!is_callable($callback)) {
			if (!strpos($path_to_include, '.php') && !strpos($path_to_include, '.html')) {
				$path_to_include .= '.php';
			}
		}
		if (empty($route)) $route = '/';
		if ($route[0] != '/') $route = '/' . $route;
		$full_route = self::$base_path . $route;
		$request_url = filter_var($_SERVER['REQUEST_URI'], FILTER_SANITIZE_URL);
		$request_url = rtrim($request_url, '/');
		$request_url = strtok($request_url, '?');
		if ($full_route === self::$base_path . '/' && ($request_url === self::$base_path || $request_url === self::$base_path . '/')) {
			self::$route_matched = true;
			if (is_callable($callback)) {
				call_user_func_array($callback, []);
			} else {
				include_once self::getRootDir() . "/$path_to_include";
			}
			exit();
		}
		$route_parts = explode('/', $full_route);
		$request_url_parts = explode('/', $request_url);
		array_shift($route_parts);
		array_shift($request_url_parts);
		if (count($route_parts) != count($request_url_parts)) {
			return;
		}
		$parameters = [];
		for ($i = 0; $i < count($route_parts); $i++) {
			if (preg_match("/^[:]/", $route_parts[$i])) {
				$param_name = ltrim($route_parts[$i], ':');
				$parameters[] = $request_url_parts[$i];
				$$param_name = $request_url_parts[$i];
			} else if ($route_parts[$i] != $request_url_parts[$i]) {
				return;
			}
		}
		self::$route_matched = true;
		if (is_callable($callback)) {
			call_user_func_array($callback, $parameters);
		} else {
			include_once self::getRootDir() . "/$path_to_include";
		}
		exit();
	}

	public static function add404($path_to_include = "") {
		if (!self::$route_matched) {
			http_response_code(404);
			if (empty($path_to_include)) {
				exit();
			}
			if (!strpos($path_to_include, '.php') && !strpos($path_to_include, '.html')) {
				$path_to_include .= '.php';
			}
			include_once self::getRootDir() . "/$path_to_include";
			exit();
		}
	}
}