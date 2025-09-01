<?php
require_once "_connect_to_database.php";
require_once "./libs/JWTHandler.php";

/**
 * Get the current JWT payload.
 * Returns null if token is invalid or missing.
 */
function current_jwt_payload() {
	$login_token = isset($_COOKIE["basta"]) ? trim($_COOKIE["basta"]) : "";
	$jwt = new JWTHandler($_ENV["EFEESYNC_JWT_SECRET"]);
	$validation = $jwt->validateToken($login_token);

	return $validation["is_valid"] ? $validation["payload"] : null;
}

/**
 * Get the current user's role name (e.g. "admin", "student", etc.)
 * Returns null if not logged in or role is missing.
 */
function current_role() {
	$payload = current_jwt_payload();
	return $payload && isset($payload["role"]) ? strtolower($payload["role"]) : null;
}

/**
 * Check if the current role is in the provided role list.
 * Accepts a string or an array of role names.
 * Returns true if match found, otherwise false.
 */
function is_current_role_in($allowed_roles) {
	$role = current_role();
	if (!$role) return false;

	$allowed = is_string($allowed_roles) ? [$allowed_roles] : $allowed_roles;
	$allowed = array_map('strtolower', $allowed);

	return in_array($role, $allowed);
}