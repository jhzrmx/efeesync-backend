<?php
function json_request_body() {
    $data = json_decode(file_get_contents("php://input"), true);
    return is_array($data) ? $data : [];
}

function require_params($data, $params) {
    require_once "_snake_to_capital.php";
    foreach ($params as $param) {
        if (empty($data[$param])) {
            echo json_encode([
                "status" => "error",
                "message" => snake_to_capital($param) . " is required"
            ]);
            exit();
        }
    }
}
