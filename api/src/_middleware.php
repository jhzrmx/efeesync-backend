<?php
require_once "_current_role.php";

function require_role(array|string $roles) {
    if (!is_current_role_in($roles)) {
        http_response_code(403);
        echo json_encode([
            "status" => "error",
            "message" => "Forbidden"
        ]);
        exit();
    }
    return true;
}

function require_login() {
    if (current_role() === null) {
        http_response_code(401);
        echo json_encode([
            "status" => "error",
            "message" => "Unauthorized"
        ]);
        exit();
    }
    return true;
}