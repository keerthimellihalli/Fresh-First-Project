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


/* GET PRODUCT ID */

$product_id =
    $_POST["product_id"] ?? "";


if (empty($product_id)) {

    echo json_encode([
        "success" => false,
        "message" => "Product ID is required"
    ]);

    exit;
}


/* GET IMAGE PATH BEFORE DELETE */

$sql =
    "SELECT image_path
     FROM products
     WHERE product_id = ?";

$stmt =
    $conn->prepare($sql);

$stmt->bind_param(
    "i",
    $product_id
);

$stmt->execute();

$result =
    $stmt->get_result();


if ($result->num_rows == 0) {

    echo json_encode([
        "success" => false,
        "message" => "Product not found"
    ]);

    $stmt->close();
    $conn->close();

    exit;
}


$product =
    $result->fetch_assoc();

$image_path =
    $product["image_path"];

$stmt->close();


/* DELETE PRODUCT */

$sql =
    "DELETE FROM products
     WHERE product_id = ?";

$stmt =
    $conn->prepare($sql);

$stmt->bind_param(
    "i",
    $product_id
);


if ($stmt->execute()) {


    /* DELETE PRODUCT IMAGE */

    if (!empty($image_path)) {

        $image_file =
            __DIR__ .
            "/../" .
            $image_path;


        if (file_exists($image_file)) {

            unlink($image_file);

        }

    }


    echo json_encode([
        "success" => true,
        "message" =>
            "Product deleted successfully"
    ]);


} else {

    echo json_encode([
        "success" => false,
        "message" =>
            "Failed to delete product"
    ]);

}


$stmt->close();

$conn->close();

?>