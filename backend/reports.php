<?php

include "db.php";

header("Content-Type: application/json; charset=UTF-8");


/* =========================================================
   GET LATEST REPORT FOR EACH PRODUCT
========================================================= */

$sql = "
    SELECT
        p.product_id,
        p.product_name,
        p.category,
        p.quantity,
        p.expiry_date,

        v.overall_status,

        a.priority,
        a.suggested_action

    FROM products p

    LEFT JOIN verifications v
        ON v.verification_id = (
            SELECT MAX(v2.verification_id)
            FROM verifications v2
            WHERE v2.product_id = p.product_id
        )

    LEFT JOIN actions a
        ON a.action_id = (
            SELECT MAX(a2.action_id)
            FROM actions a2
            WHERE a2.product_id = p.product_id
        )

    ORDER BY p.product_id DESC
";


/* =========================================================
   EXECUTE QUERY
========================================================= */

$result = $conn->query($sql);


if (!$result) {

    echo json_encode([
        "success" => false,
        "message" => "Could not load reports.",
        "error" => $conn->error
    ]);

    $conn->close();

    exit;
}


/* =========================================================
   STORE REPORTS
========================================================= */

$reports = [];


while ($row = $result->fetch_assoc()) {

    /*
       If product has not been verified yet,
       show default values.
    */

    if (
        empty($row["overall_status"])
    ) {
        $row["overall_status"] =
            "NOT VERIFIED";
    }


    if (
        empty($row["priority"])
    ) {
        $row["priority"] =
            "Low";
    }


    if (
        empty($row["suggested_action"])
    ) {
        $row["suggested_action"] =
            "No action";
    }


    $reports[] = $row;
}


/* =========================================================
   SEND JSON RESPONSE
========================================================= */

echo json_encode(
    $reports,
    JSON_UNESCAPED_UNICODE
);


/* =========================================================
   CLOSE DATABASE
========================================================= */

$conn->close();

?>