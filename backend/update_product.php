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

$product_id =
    $_POST["product_id"] ?? "";

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


/* VALIDATION */

if (
    empty($product_id) ||
    empty($product_name) ||
    empty($category) ||
    empty($quantity) ||
    empty($purchase_date) ||
    empty($expiry_date)
) {

    echo json_encode([
        "success" => false,
        "message" => "Please fill all required fields."
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
        "message" => "Quantity must be at least 1."
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


/* GET CURRENT IMAGE */

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
        "message" => "Product not found."
    ]);

    $stmt->close();
    $conn->close();

    exit;
}


$product =
    $result->fetch_assoc();

$current_image =
    $product["image_path"];

$stmt->close();


/* KEEP OLD IMAGE */

$image_path =
    $current_image;


/* CHECK NEW IMAGE */

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

        $conn->close();

        exit;
    }


    /* CHECK REAL IMAGE */

    if (
        getimagesize(
            $temporary_file
        ) === false
    ) {

        echo json_encode([
            "success" => false,
            "message" =>
                "Invalid image file."
        ]);

        $conn->close();

        exit;
    }


    /* UPLOAD FOLDER */

    $upload_folder =
        __DIR__ . "/../uploads/";


    if (
        !is_dir($upload_folder)
    ) {

        mkdir(
            $upload_folder,
            0777,
            true
        );
    }


    /* NEW FILE NAME */

    $new_file_name =
        "product_" .
        uniqid() .
        "." .
        $extension;


    $destination =
        $upload_folder .
        $new_file_name;


    /* SAVE NEW IMAGE */

    if (
        !move_uploaded_file(
            $temporary_file,
            $destination
        )
    ) {

        echo json_encode([
            "success" => false,
            "message" =>
                "Could not save new image."
        ]);

        $conn->close();

        exit;
    }


    /* NEW IMAGE PATH */

    $image_path =
        "uploads/" .
        $new_file_name;


    /* DELETE OLD IMAGE */

    if (
        !empty($current_image)
    ) {

        $old_image =
            __DIR__ .
            "/../" .
            $current_image;


        if (
            file_exists($old_image)
        ) {

            unlink($old_image);
        }
    }
}


/* UPDATE DATABASE */

$sql =
    "UPDATE products
     SET product_name = ?,
         category = ?,
         quantity = ?,
         purchase_date = ?,
         expiry_date = ?,
         storage_temperature = ?,
         storage_condition = ?,
         image_path = ?
     WHERE product_id = ?";


$stmt =
    $conn->prepare($sql);


if (!$stmt) {

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
   i = integer
*/

$stmt->bind_param(
    "ssissdssi",
    $product_name,
    $category,
    $quantity,
    $purchase_date,
    $expiry_date,
    $storage_temperature,
    $storage_condition,
    $image_path,
    $product_id
);


/* EXECUTE UPDATE */

if ($stmt->execute()) {

    echo json_encode([
        "success" => true,
        "message" =>
            "Product updated successfully!",
        "image_path" =>
            $image_path
    ]);

} else {

    echo json_encode([
        "success" => false,
        "message" =>
            "Update failed: " .
            $stmt->error
    ]);
}


$stmt->close();

$conn->close();

?>