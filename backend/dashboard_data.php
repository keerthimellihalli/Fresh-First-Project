<?php

header("Content-Type: application/json; charset=UTF-8");

require_once "db.php";


/* =====================================================
   INITIAL DATA
===================================================== */

$data = [
    "total" => 0,
    "valid" => 0,
    "near_expiry" => 0,
    "expired" => 0,
    "health" => 0,
    "products" => [],
    "critical" => []
];


/* =====================================================
   GET PRODUCTS
===================================================== */

$sql = "
    SELECT
        product_id,
        product_name,
        category,
        quantity,
        purchase_date,
        expiry_date,
        storage_temperature,
        storage_condition
    FROM products
    ORDER BY expiry_date ASC
";

$result = $conn->query($sql);


if (!$result) {

    echo json_encode([
        "success" => false,
        "message" => "Could not load dashboard data.",
        "error" => $conn->error
    ]);

    $conn->close();

    exit;
}


/* =====================================================
   VARIABLES
===================================================== */

$today = new DateTime();

$total = 0;
$valid = 0;
$nearExpiry = 0;
$expired = 0;

$products = [];
$critical = [];


/* =====================================================
   PROCESS PRODUCTS
===================================================== */

while ($row = $result->fetch_assoc()) {

    $total++;


    /* =================================================
       CALCULATE DAYS LEFT
    ================================================= */

    $expiryDate =
        new DateTime(
            $row["expiry_date"]
        );

    $difference =
        $today->diff($expiryDate);

    $daysLeft =
        (int)$difference->format("%r%a");


    /* =================================================
       DEFAULT VALUES
    ================================================= */

    $status = "VALID";

    $statusClass = "valid";

    $statusIcon = "🟢";

    $priority = "Low";

    $action = "No action";

    $storageIssue = false;

    $quantityIssue = false;


    /* =================================================
       R1 - EXPIRY CHECK
    ================================================= */

    if ($daysLeft < 0) {

        $status = "EXPIRED";

        $statusClass = "expired";

        $statusIcon = "🔴";

        $expired++;

        $priority = "Critical";

        $action = "Remove / Dispose";


        $critical[] = [
            "name" =>
                $row["product_name"],

            "category" =>
                $row["category"],

            "days_left" =>
                $daysLeft,

            "status" =>
                "EXPIRED",

            "priority" =>
                "Critical",

            "action" =>
                "Remove / Dispose",

            "icon" =>
                "🔴"
        ];
    }


    /* =================================================
       R2 - NEAR EXPIRY
    ================================================= */

    elseif ($daysLeft <= 3) {

        $status =
            "NEAR EXPIRY";

        $statusClass =
            "near";

        $statusIcon =
            "🟠";

        $nearExpiry++;


        if ($daysLeft <= 2) {

            $priority =
                "High";

        } else {

            $priority =
                "Medium";
        }


        if (
            intval($row["quantity"]) >= 10
        ) {

            $action =
                "Redistribution";

        } else {

            $action =
                "Priority Sale";
        }


        $critical[] = [
            "name" =>
                $row["product_name"],

            "category" =>
                $row["category"],

            "days_left" =>
                $daysLeft,

            "status" =>
                "NEAR EXPIRY",

            "priority" =>
                $priority,

            "action" =>
                $action,

            "icon" =>
                "🟠"
        ];
    }


    /* =================================================
       R3 - VALID
    ================================================= */

    else {

        $status =
            "VALID";

        $statusClass =
            "valid";

        $statusIcon =
            "🟢";

        $valid++;

        $priority =
            "Low";

        $action =
            "No action";
    }


    /* =================================================
       R4 - QUANTITY CHECK
    ================================================= */

    $quantity =
        $row["quantity"];


    if (
        !is_numeric($quantity) ||
        intval($quantity) <= 0 ||
        intval($quantity) != $quantity
    ) {

        $quantityIssue =
            true;

        $priority =
            "Medium";

        $action =
            "Further Inspection";


        $critical[] = [
            "name" =>
                $row["product_name"],

            "category" =>
                $row["category"],

            "days_left" =>
                $daysLeft,

            "status" =>
                "INVALID QUANTITY",

            "priority" =>
                "Medium",

            "action" =>
                "Further Inspection",

            "icon" =>
                "⚠️"
        ];
    }


    /* =================================================
       R5 - STORAGE CHECK
    ================================================= */

    $temperature =
        $row["storage_temperature"];


    if (
        $temperature === null ||
        $temperature === ""
    ) {

        $storageIssue =
            true;

    } elseif (
        strtolower(
            trim($row["category"])
        ) === "dairy"
    ) {

        $temperature =
            (float)$temperature;


        if (
            $temperature < 2 ||
            $temperature > 8
        ) {

            $storageIssue =
                true;
        }
    }


    /* =================================================
       STORAGE ISSUE ACTION
    ================================================= */

    if ($storageIssue) {

        if ($daysLeft >= 0 && $daysLeft <= 3) {

            $priority =
                "High";

        } else {

            $priority =
                "Medium";
        }

        $action =
            "Further Inspection";


        $critical[] = [
            "name" =>
                $row["product_name"],

            "category" =>
                $row["category"],

            "days_left" =>
                $daysLeft,

            "status" =>
                "STORAGE ISSUE",

            "priority" =>
                $priority,

            "action" =>
                "Further Inspection",

            "icon" =>
                "🌡️"
        ];
    }


    /* =================================================
       PRODUCT DATA
    ================================================= */

    $products[] = [

        "id" =>
            $row["product_id"],

        "name" =>
            $row["product_name"],

        "category" =>
            $row["category"],

        "quantity" =>
            $row["quantity"],

        "purchase_date" =>
            $row["purchase_date"],

        "expiry_date" =>
            $row["expiry_date"],

        "days_left" =>
            $daysLeft,

        "status" =>
            $status,

        "status_class" =>
            $statusClass,

        "status_icon" =>
            $statusIcon,

        "priority" =>
            $priority,

        "action" =>
            $action,

        "storage_issue" =>
            $storageIssue,

        "quantity_issue" =>
            $quantityIssue
    ];
}


/* =====================================================
   STOCK HEALTH
===================================================== */

if ($total > 0) {

    $health =
        round(
            ($valid / $total) * 100
        );

} else {

    $health = 0;
}


/* =====================================================
   LIMIT CRITICAL ITEMS
===================================================== */

$critical =
    array_slice(
        $critical,
        0,
        6
    );


/* =====================================================
   FINAL DATA
===================================================== */

$data["total"] =
    $total;

$data["valid"] =
    $valid;

$data["near_expiry"] =
    $nearExpiry;

$data["expired"] =
    $expired;

$data["health"] =
    $health;

$data["products"] =
    $products;

$data["critical"] =
    $critical;


/* =====================================================
   SEND RESPONSE
===================================================== */

echo json_encode(
    [
        "success" => true,
        "data" => $data
    ],
    JSON_UNESCAPED_UNICODE
);


/* =====================================================
   CLOSE DATABASE
===================================================== */

$conn->close();

?>