<?php

include "db.php";

header("Content-Type: application/json; charset=UTF-8");


/* GET PRODUCTS */

$sql =
    "SELECT
        product_id,
        product_name,
        category,
        quantity,
        purchase_date,
        expiry_date,
        storage_temperature,
        storage_condition,
        image_path,
        created_at
     FROM products
     ORDER BY product_id DESC";


$result =
    $conn->query($sql);


$products = [];


if ($result) {

    while (
        $row = $result->fetch_assoc()
    ) {

        $products[] = $row;

    }

}


/* RETURN PRODUCTS */

echo json_encode(
    $products,
    JSON_UNESCAPED_UNICODE
);


$conn->close();

?>