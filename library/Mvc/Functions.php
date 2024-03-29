<?php

class Mvc_Functions {

	static function endsWith($haystack, $needle) {
		$length = strlen($needle);
		$start = $length * -1; //negative
		return (substr($haystack, $start) === $needle);
	}

	/**
	 * Check if a string starts with a specific string
	 *
	 * @param type $haystack
	 * @param type $needle
	 * @return type
	 */
	static function startsWith($haystack, $needle) {
		$length = strlen($needle);
		return (substr($haystack, 0, $length) === $needle);
	}

	static function get_current_site_url() {
		/* Get the current Directory */
		$cur_dir = explode(DIRECTORY_SEPARATOR, getcwd());
		/* Determine if this is an action or not */
		$cur_dir = array_reverse($cur_dir);
		$base = array_shift($cur_dir);

		$action = explode($base . '/', $_SERVER['REQUEST_URI']);
		$action_2 = array_shift($action);
		//var_dump('http://'.$_SERVER['HTTP_HOST'].$action.$base.'/');exit;


		/* Check if the site installation is dropped in the document root.
		  if so the base url should be equal to the domain address else the base url
		  should point to the folder in which the web installation reside

		 */
		$final_url = 'http://' . $_SERVER['HTTP_HOST'] . $action_2 . $base . '/';
		if ($_SERVER['DOCUMENT_ROOT'] == getcwd()) {
			$final_url = 'http://' . $_SERVER['HTTP_HOST'];
		}

//	if(getcwd() == CMS_DIR){
//		$final_url = str_replace('/'.basename(CMS_DIR), '', $final_url);
//	}
		if (defined('OVERWRITE_SITEURL')) {
			return OVERWRITE_SITEURL;
		}
		if (!self::endsWith($final_url, '/')) {
			$final_url = $final_url . '/';
		}
		return $final_url;
	}

	static function directoryToArray($directory, $recursive) {
		$array_items = array();
		if ($handle = opendir($directory)) {
			while (false !== ($file = readdir($handle))) {
				if ($file != "." && $file != "..") {
					if (is_dir($directory . "/" . $file)) {
						if ($recursive) {
							$array_items = array_merge($array_items, self::directoryToArray($directory . "/" . $file, $recursive));
						}
						$file = $directory . "/" . $file;
						$array_items[] = preg_replace("/\/\//si", "/", $file);
					} else {
						$file = $directory . "/" . $file;
						$array_items[] = preg_replace("/\/\//si", "/", $file);
					}
				}
			}
			closedir($handle);
		}
		return $array_items;
	}

	static function compress($code) {
		$code = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $code);
		$code = str_replace(array("\r\n", "\r", "\n", "\t", '  ', '    ', '    '), '', $code);
		$code = str_replace('{ ', '{', $code);
		$code = str_replace(' }', '}', $code);
		$code = str_replace('; ', ';', $code);

		return $code;
	}

	static function GET_to_string($arr = false) {
		if ($arr === false) {
			$arr = $_GET;
		}
		$string = [];
		foreach ($arr as $key => $value) {
			$key = urlencode($key);
			$value = urlencode($value);
			$string[] = "$key=$value";
		}
		return implode('&', $string);
	}

	static function curPageURL() {
		$pageURL = 'http';
		if ($_SERVER["HTTPS"] == "on") {
			$pageURL .= "s";
		}
		$pageURL .= "://";
		if ($_SERVER["SERVER_PORT"] != "80") {
			$pageURL .= $_SERVER["SERVER_NAME"] . ":" . $_SERVER["SERVER_PORT"] . $_SERVER["REQUEST_URI"];
		} else {
			$pageURL .= $_SERVER["SERVER_NAME"] . $_SERVER["REQUEST_URI"];
		}
		return $pageURL;
	}

	static function get_extension($file) {
		$str = $file;
		$i = strrpos($str, ".");
		if (!$i) {
			return "";
		}
		$l = strlen($str) - $i;
		$ext = substr($str, $i + 1, $l);
		return $ext;
	}

	/**
	 * Determince location of connection user
	 *
	 * @return type
	 */
	static function get_location() {
		if (!isset($_SESSION['framework_location'])) {
			$ip = $_SERVER['REMOTE_ADDR'];
			$details = file_get_contents("http://ipinfo.io/{$ip}/json");
			$_SESSION['framework_location'] = $details;
		} else {
			$details = $_SESSION['framework_location'];
		}

		return json_decode($details);
	}

}
