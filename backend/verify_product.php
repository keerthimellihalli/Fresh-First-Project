<?php

include "db.php";

header("Content-Type: application/json; charset=UTF-8");


/* =========================================================
   CHECK REQUEST
========================================================= */

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    echo json_encode([
        "success" => false,
        "message" => "Invalid request"
    ]);

    exit;
}


/* =========================================================
   GET PRODUCT ID
========================================================= */

$product_id =
    $_POST["product_id"] ?? "";


if (empty($product_id)) {

    echo json_encode([
        "success" => false,
        "message" => "Product ID is required"
    ]);

    exit;
}


/* =========================================================
   GET PRODUCT
========================================================= */

$sql =
    "SELECT *
     FROM products
     WHERE product_id = ?";

$stmt =
    $conn->prepare($sql);


if (!$stmt) {

    echo json_encode([
        "success" => false,
        "message" => "Database error"
    ]);

    $conn->close();

    exit;
}


$stmt->bind_param(
    "i",
    $product_id
);

$stmt->execute();

$result =
    $stmt->get_result();


if ($result->num_rows === 0) {

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

$stmt->close();


/* =========================================================
   DATE CALCULATION
========================================================= */

$today =
    new DateTime();

$expiry =
    new DateTime(
        $product["expiry_date"]
    );

$purchase =
    new DateTime(
        $product["purchase_date"]
    );


$days_left =
    (int)$today
        ->diff($expiry)
        ->format("%r%a");


/* =========================================================
   RULE SETTINGS
========================================================= */

/*
   Near expiry threshold:
   0 to 3 days = Near Expiry

   Dairy storage:
   2°C to 8°C = Valid
*/

$near_expiry_threshold = 3;

$dairy_min_temperature = 2;

$dairy_max_temperature = 8;


/* =========================================================
   R1 - EXPIRY CHECK
========================================================= */

if ($days_left < 0) {

    $expiry_status =
        "EXPIRED";

} elseif (
    $days_left <=
    $near_expiry_threshold
) {

    $expiry_status =
        "NEAR EXPIRY";

} else {

    $expiry_status =
        "VALID";
}


/* =========================================================
   R4 - QUANTITY CHECK
========================================================= */

$quantity =
    $product["quantity"];


if (
    !is_numeric($quantity) ||
    intval($quantity) <= 0 ||
    intval($quantity) != $quantity
) {

    $quantity_status =
        "INVALID QUANTITY";

} else {

    $quantity_status =
        "VALID QUANTITY";
}


/* =========================================================
   R5 - STORAGE CHECK
========================================================= */

$temperature =
    $product["storage_temperature"];


if (
    $temperature === null ||
    $temperature === ""
) {

    $storage_status =
        "STORAGE ISSUE";

} elseif (
    $product["category"] === "Dairy"
) {

    if (
        $temperature >=
        $dairy_min_temperature
        &&
        $temperature <=
        $dairy_max_temperature
    ) {

        $storage_status =
            "VALID STORAGE";

    } else {

        $storage_status =
            "STORAGE ISSUE";
    }

} else {

    $storage_status =
        "VALID STORAGE";
}


/* =========================================================
   R6 - RECORD CHECK
========================================================= */

if (
    empty($product["product_name"]) ||
    empty($product["category"]) ||
    empty($product["purchase_date"]) ||
    empty($product["expiry_date"])
) {

    $record_status =
        "INCOMPLETE RECORD";

} else {

    $record_status =
        "COMPLETE RECORD";
}


/* =========================================================
   R7 - DATE CHECK
========================================================= */

if ($expiry <= $purchase) {

    $date_status =
        "INVALID DATES";

} else {

    $date_status =
        "VALID DATES";
}


/* =========================================================
   OVERALL STATUS
========================================================= */

/*
   Priority order:

   1. Expired
   2. Invalid dates
   3. Invalid quantity
   4. Incomplete record
   5. Near expiry + storage issue
   6. Near expiry
   7. Storage issue
   8. Valid
*/

if (
    $expiry_status ===
    "EXPIRED"
) {

    $overall_status =
        "EXPIRED";

} elseif (
    $date_status ===
    "INVALID DATES"
) {

    $overall_status =
        "INSPECTION NEEDED";

} elseif (
    $quantity_status ===
    "INVALID QUANTITY"
) {

    $overall_status =
        "INSPECTION NEEDED";

} elseif (
    $record_status ===
    "INCOMPLETE RECORD"
) {

    $overall_status =
        "INSPECTION NEEDED";

} elseif (
    $expiry_status ===
    "NEAR EXPIRY"
    &&
    $storage_status ===
    "STORAGE ISSUE"
) {

    $overall_status =
        "HIGH PRIORITY";

} elseif (
    $expiry_status ===
    "NEAR EXPIRY"
) {

    $overall_status =
        "NEAR EXPIRY";

} elseif (
    $storage_status ===
    "STORAGE ISSUE"
) {

    $overall_status =
        "STORAGE ISSUE";

} else {

    $overall_status =
        "VALID";
}


/* =========================================================
   PRIORITY AND ACTION
========================================================= */

$priority =
    "Low";

$action =
    "No action";


/* EXPIRED */

if (
    $overall_status ===
    "EXPIRED"
) {

    $priority =
        "Critical";

    $action =
        "Remove/Dispose";
}


/* NEAR EXPIRY + STORAGE ISSUE */

elseif (
    $overall_status ===
    "HIGH PRIORITY"
) {

    $priority =
        "High";

    $action =
        "Further Inspection";
}


/* NEAR EXPIRY */

elseif (
    $overall_status ===
    "NEAR EXPIRY"
) {

    $priority =
        "High";


    /*
       Large quantity:
       Redistribution

       Small quantity:
       Priority Sale
    */

    if (
        intval($quantity) >= 10
    ) {

        $action =
            "Redistribution";

    } else {

        $action =
            "Priority Sale";
    }
}


/* STORAGE ISSUE */

elseif (
    $overall_status ===
    "STORAGE ISSUE"
) {

    $priority =
        "Medium";

    $action =
        "Further Inspection";
}


/* INSPECTION NEEDED */

elseif (
    $overall_status ===
    "INSPECTION NEEDED"
) {

    $priority =
        "Medium";

    $action =
        "Further Inspection";
}


/* =========================================================
   SAVE VERIFICATION
========================================================= */

$verification_date =
    date("Y-m-d");


$sql =
    "INSERT INTO verifications
    (
        product_id,
        verification_date,
        expiry_status,
        quantity_status,
        storage_status,
        record_status,
        overall_status
    )
    VALUES (?, ?, ?, ?, ?, ?, ?)";


$stmt =
    $conn->prepare($sql);


if (!$stmt) {

    echo json_encode([
        "success" => false,
        "message" =>
            "Could not prepare verification."
    ]);

    $conn->close();

    exit;
}


$stmt->bind_param(
    "issssss",
    $product_id,
    $verification_date,
    $expiry_status,
    $quantity_status,
    $storage_status,
    $record_status,
    $overall_status
);


if (!$stmt->execute()) {

    echo json_encode([
        "success" => false,
        "message" =>
            "Could not save verification."
    ]);

    $stmt->close();
    $conn->close();

    exit;
}


$stmt->close();


/* =========================================================
   SAVE ACTION
========================================================= */

$sql =
    "INSERT INTO actions
    (
        product_id,
        priority,
        suggested_action,
        action_status,
        action_date
    )
    VALUES (?, ?, ?, 'Pending', ?)";


$stmt =
    $conn->prepare($sql);


if (!$stmt) {

    echo json_encode([
        "success" => false,
        "message" =>
            "Could not prepare action."
    ]);

    $conn->close();

    exit;
}


$stmt->bind_param(
    "isss",
    $product_id,
    $priority,
    $action,
    $verification_date
);


if (!$stmt->execute()) {

    echo json_encode([
        "success" => false,
        "message" =>
            "Could not save action."
    ]);

    $stmt->close();
    $conn->close();

    exit;
}


$stmt->close();


/* =========================================================
   SAVE HISTORY
========================================================= */

$description =
    "Product verified. Status: " .
    $overall_status .
    ", Priority: " .
    $priority .
    ", Action: " .
    $action;


$sql =
    "INSERT INTO history
    (
        product_id,
        action_type,
        description
    )
    VALUES (?, 'Verification', ?)";


$stmt =
    $conn->prepare($sql);


if (!$stmt) {

    echo json_encode([
        "success" => false,
        "message" =>
            "Could not prepare history."
    ]);

    $conn->close();

    exit;
}


$stmt->bind_param(
    "is",
    $product_id,
    $description
);


if (!$stmt->execute()) {

    echo json_encode([
        "success" => false,
        "message" =>
            "Could not save history."
    ]);

    $stmt->close();
    $conn->close();

    exit;
}


$stmt->close();


/* =========================================================
   SEND FINAL RESULT
========================================================= */

echo json_encode([

    "success" =>
        true,

    "product_id" =>
        $product_id,

    "days_left" =>
        $days_left,

    "expiry_status" =>
        $expiry_status,

    "quantity_status" =>
        $quantity_status,

    "storage_status" =>
        $storage_status,

    "record_status" =>
        $record_status,

    "date_status" =>
        $date_status,

    "overall_status" =>
        $overall_status,

    "priority" =>
        $priority,

    "action" =>
        $action

]);


$conn->close();

?>