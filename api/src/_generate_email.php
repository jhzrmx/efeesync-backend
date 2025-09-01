<?php

function sanitize_name($string) {
	$string = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $string); // ñ → n
	return strtolower(preg_replace('/[^a-z]/i', '', $string));
}

function generate_email($first_name, $last_name, $domain_name = "@cbsua.edu.ph") {
	return sanitize_name($first_name) . "." . sanitize_name($last_name) . $domain_name;
}
