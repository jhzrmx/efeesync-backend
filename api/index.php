<?php
date_default_timezone_set('Asia/Manila');

// IMPORT NECESSARY LIBRARIES
require "libs/Router.php";
require "libs/EnvLoader.php";

// HEADER CONFUSE
header("X-Powered-By: Why are you here?");

// LOAD ENVIRONMENT VARIABLES
EnvLoader::loadFromFile("../.env");

if (!$_ENV["EFEESYNC_IS_PRODUCTION"]) {
	// DEVELOPMENT MODE
	header("Access-Control-Allow-Origin: " . $_ENV["EFEESYNC_DEV_FRONTEND_URL"]);
	header("Access-Control-Allow-Credentials: true");
	header("Access-Control-Allow-Methods: GET,POST,PUT,PATCH,DELETE,OPTIONS");
	header("Access-Control-Allow-Headers: *");
}

// HANDLE PREFLIGHT REQUESTS
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(204);
    exit();
}

// APPEND /api
Route::enableBasePath();

// ROUTES: BASIC AUTH
Route::post("/check-roles", "src/check-roles.php");
Route::post("/login", "src/login.php");
Route::get("/verify-login", "src/verify-login.php");
Route::post("/logout", "src/logout.php");

//ROUTES: ROLES
Route::get("/roles", "src/get-roles.php");

// ROUTES: USER
Route::get("/users/current", "src/get-current-user.php");
Route::put("/users/current/password", "src/edit-current-user-password.php");
Route::post("/users/picture/:id", "src/edit-user-picture.php");
Route::delete("/users/picture/:id", "src/delete-user-picture.php");

// ROUTES: DASHBOARD DATA
Route::get("/admin/dashboard", "src/get-admin-dashboard.php");

// ROUTES: DEPARTMENTS
Route::get("/departments", "src/get-departments.php");
Route::get("/departments/:id", "src/get-departments.php");
Route::get("/departments/code/:code", "src/get-departments.php");
Route::post("/departments", "src/add-department.php");
Route::put("/departments/:id", "src/edit-department.php");
Route::delete("/departments", "src/delete-department.php");		// Multi delete
Route::delete("/departments/:id", "src/delete-department.php");	// Single delete

// ROUTES: DEPARTMENT > PROGRAMS
Route::get("/departments/:department_id/programs", "src/get-programs.php");
Route::get("/departments/code/:department_code/programs", "src/get-programs.php");

// ROUTES: PROGRAMS
Route::get("/programs", "src/get-programs.php");
Route::get("/programs/:id", "src/get-programs.php");
Route::get("/programs/code/:code", "src/get-programs.php");
Route::post("/programs", "src/add-program.php");
Route::put("/programs/:id", "src/edit-program.php");
Route::delete("/programs", "src/delete-program.php");		// Multi delete
Route::delete("/programs/:id", "src/delete-program.php");	// Single delete

// ROUTES: ORGANIZATIONS
Route::get("/organizations", "src/get-organizations.php");
Route::get("/organizations/:id", "src/get-organizations.php");
Route::get("/organizations/code/:code", "src/get-organizations.php");
Route::post("/organizations", "src/add-organization.php");
Route::put("/organizations/:id", "src/edit-organization.php");
Route::delete("/organizations/:id", "src/delete-organization.php");

Route::post("/organizations/logo/:id", "src/edit-organization-logo.php");
Route::delete("/organizations/logo/:id", "src/delete-organization-logo.php");

// ROUTES: ORGANIZATION > EVENTS
// USING ORG ID
Route::get("/organizations/:organization_id/events", "src/get-events.php");
Route::get("/organizations/:organization_id/events/:id", "src/get-events.php");
Route::post("/organizations/:organization_id/events", "src/add-event.php");
Route::put("/organizations/:organization_id/events/:id", "src/edit-event.php");
Route::put("/organizations/:organization_id/events/:id/attendance", "src/edit-event-attendance.php");
Route::put("/organizations/:organization_id/events/:id/contribution", "src/edit-event-contribution.php");
Route::delete("/organizations/:organization_id/events", "src/delete-event.php");		// Multi delete
Route::delete("/organizations/:organization_id/events/:id", "src/delete-event.php");	// Single delete
// USING ORG CODE
Route::get("/organizations/code/:organization_code/events", "src/get-events.php");
Route::get("/organizations/code/:organization_code/events/:id", "src/get-events.php");
Route::post("/organizations/code/:organization_code/events", "src/add-event.php");
Route::put("/organizations/code/:organization_code/events/:id", "src/edit-event.php");
Route::put("/organizations/code/:organization_code/events/:id/attendance", "src/edit-event-attendance.php");
Route::put("/organizations/code/:organization_code/events/:id/contribution", "src/edit-event-contribution.php");
Route::delete("/organizations/code/:organization_code/events", "src/delete-event.php");		// Multi delete
Route::delete("/organizations/code/:organization_code/events/:id", "src/delete-event.php");	// Single delete

// ATTENDANCE MADE
Route::get("/events/:id/attendance/made/:event_attend_date_id", "src/get-event-attendance-made.php"); // Optional query parameter: page, per_page, search
Route::get("/events/:id/attendance/made/:date", "src/get-event-attendance-made.php");
// ADD ATTENDANCE
Route::post("/events/:id/attendance/:date/:time/:student_id", "src/add-attendance.php");
Route::post("/events/:id/attendance/:date/:time/number/:student_number_id", "src/add-attendance.php");

// CONTRIBUTIONS
Route::get("/events/:id/contributions", "src/get-event-contributions.php"); // Optional query parameter: page, per_page, search

// ROUTES: STUDENTS
Route::get("/students", "src/get-students.php"); // Optional query parameter: page, per_page, search
Route::get("/students/:id", "src/get-students.php");
Route::get("/students/number/:student_number", "src/get-students.php");
Route::post("/students", "src/add-student.php");
Route::put("/students/:id", "src/edit-student.php");
Route::delete("/students", "src/delete-student.php");		// Multi delete
Route::delete("/students/:id", "src/delete-student.php");	// Single delete

// ROUTES: DEPARTMENT > STUDENTS
Route::get("/departments/:department_id/students", "src/get-students.php");
Route::get("/departments/:department_id/students/search/:search", "src/get-students.php");
Route::get("/departments/code/:department_code/students", "src/get-students.php");
Route::get("/departments/code/:department_code/students/search/:search", "src/get-students.php");

// ROUTE: 404
Route::add404("src/not-found.php");
