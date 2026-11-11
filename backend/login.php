<?php

include "db.php";

header("Content-Type: application/json");


/* CHECK REQUEST */

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    echo json_encode([
        "success" => false,
        "message" => "Invalid request"
    ]);

    exit;
}


/* GET LOGIN DATA */

$username =
    trim($_POST["username"] ?? "");

$password =
    $_POST["password"] ?? "";


if (
    empty($username) ||
    empty($password)
) {

    echo json_encode([
        "success" => false,
        "message" =>
            "Username and password are required."
    ]);

    exit;
}


/* CHECK USER */

$sql =
    "SELECT user_id, username, role
     FROM users
     WHERE username = ?
     AND password = ?";


$stmt =
    $conn->prepare($sql);


if (!$stmt) {

    echo json_encode([
        "success" => false,
        "message" =>
            "Database error."
    ]);

    $conn->close();

    exit;
}


$stmt->bind_param(
    "ss",
    $username,
    $password
);

$stmt->execute();

$result =
    $stmt->get_result();


/* LOGIN SUCCESS */

if ($result->num_rows === 1) {

    $user =
        $result->fetch_assoc();


    echo json_encode([
        "success" => true,
        "message" =>
            "Login successful",
        "user" => $user
    ]);


} else {

    echo json_encode([
        "success" => false,
        "message" =>
            "Invalid username or password"
    ]);

}


$stmt->close();

$conn->close();

?>