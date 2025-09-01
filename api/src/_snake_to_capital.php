<?php 

function snake_to_capital($string_to_be_cap) {
	$string_to_be_cap = str_replace("_", " ",  $string_to_be_cap);
	return ucwords($string_to_be_cap);
}