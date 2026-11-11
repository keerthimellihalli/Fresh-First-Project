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


/* GET FORM DATA */

$product_name =
    trim($_POST["product_name"] ?? "");

$category =
    trim($_POST["category"] ?? "");

$quantity =
    $_POST["quantity"] ?? "";

$purchase_date =
    $_POST["purchase_date"] ?? "";

$expiry_date =
    $_POST["expiry_date"] ?? "";

$storage_temperature =
    $_POST["storage_temperature"] ?? null;

$storage_condition =
    trim($_POST["storage_condition"] ?? "");

$image_path = null;


/* REQUIRED FIELD VALIDATION */

if (
    empty($product_name) ||
    empty($category) ||
    $quantity === "" ||
    empty($purchase_date) ||
    empty($expiry_date)
) {

    echo json_encode([
        "success" => false,
        "message" =>
            "Please fill all required fields."
    ]);

    exit;
}


/* QUANTITY VALIDATION */

if (
    !is_numeric($quantity) ||
    intval($quantity) < 1
) {

    echo json_encode([
        "success" => false,
        "message" =>
            "Quantity must be at least 1."
    ]);

    exit;
}

$quantity = intval($quantity);


/* DATE VALIDATION */

if ($expiry_date <= $purchase_date) {

    echo json_encode([
        "success" => false,
        "message" =>
            "Expiry date must be after purchase date."
    ]);

    exit;
}


/* IMAGE UPLOAD */

if (
    isset($_FILES["product_image"]) &&
    $_FILES["product_image"]["error"]
    === UPLOAD_ERR_OK
) {

    $temporary_file =
        $_FILES["product_image"]["tmp_name"];

    $original_name =
        $_FILES["product_image"]["name"];

    $extension =
        strtolower(
            pathinfo(
                $original_name,
                PATHINFO_EXTENSION
            )
        );


    /* ALLOWED IMAGE TYPES */

    $allowed =
        [
            "jpg",
            "jpeg",
            "png",
            "webp",
            "gif"
        ];


    if (
        !in_array(
            $extension,
            $allowed,
            true
        )
    ) {

        echo json_encode([
            "success" => false,
            "message" =>
                "Invalid image type."
        ]);

        exit;
    }


    /* CHECK IMAGE */

    if (
        getimagesize(
            $temporary_file
        ) === false
    ) {

        echo json_encode([
            "success" => false,
            "message" =>
                "Selected file is not a valid image."
        ]);

        exit;
    }


    /* UPLOAD FOLDER */

    $upload_folder =
        __DIR__ . "/../uploads/";


    if (
        !is_dir($upload_folder)
    ) {

        if (
            !mkdir(
                $upload_folder,
                0777,
                true
            )
        ) {

            echo json_encode([
                "success" => false,
                "message" =>
                    "Could not create upload folder."
            ]);

            exit;
        }
    }


    /* CREATE UNIQUE FILE NAME */

    $new_file_name =
        "product_" .
        uniqid() .
        "." .
        $extension;


    $destination =
        $upload_folder .
        $new_file_name;


    /* SAVE IMAGE */

    if (
        !move_uploaded_file(
            $temporary_file,
            $destination
        )
    ) {

        echo json_encode([
            "success" => false,
            "message" =>
                "Could not save image."
        ]);

        exit;
    }


    $image_path =
        "uploads/" .
        $new_file_name;
}


/* INSERT PRODUCT */

$sql =
    "INSERT INTO products
    (
        product_name,
        category,
        quantity,
        purchase_date,
        expiry_date,
        storage_temperature,
        storage_condition,
        image_path
    )
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";


$stmt =
    $conn->prepare($sql);


if (!$stmt) {

    /* DELETE UPLOADED IMAGE IF DB PREPARATION FAILS */

    if (!empty($image_path)) {

        $uploaded_file =
            __DIR__ .
            "/../" .
            $image_path;

        if (
            file_exists($uploaded_file)
        ) {

            unlink($uploaded_file);
        }
    }


    echo json_encode([
        "success" => false,
        "message" =>
            "Database error: " .
            $conn->error
    ]);

    $conn->close();

    exit;
}


/*
   s = string
   s = string
   i = integer
   s = string
   s = string
   d = decimal
   s = string
   s = string
*/

$stmt->bind_param(
    "ssissdss",
    $product_name,
    $category,
    $quantity,
    $purchase_date,
    $expiry_date,
    $storage_temperature,
    $storage_condition,
    $image_path
);


/* EXECUTE */

if ($stmt->execute()) {

    echo json_encode([
        "success" => true,
        "message" =>
            "Product added successfully!",
        "image_path" =>
            $image_path
    ]);

} else {

    /* DELETE IMAGE IF DATABASE INSERT FAILS */

    if (!empty($image_path)) {

        $uploaded_file =
            __DIR__ .
            "/../" .
            $image_path;

        if (
            file_exists($uploaded_file)
        ) {

            unlink($uploaded_file);
        }
    }


    echo json_encode([
        "success" => false,
        "message" =>
            "Database insert error: " .
            $stmt->error
    ]);
}


$stmt->close();

$conn->close();

?>