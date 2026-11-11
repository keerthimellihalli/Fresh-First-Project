<?php

include "db.php";

header("Content-Type: application/json; charset=UTF-8");


/* =========================================================
   GET HISTORY
========================================================= */

$sql = "
    SELECT
        h.history_id,
        p.product_name,
        h.action_type,
        h.description,
        h.created_at

    FROM history h

    INNER JOIN products p
        ON h.product_id = p.product_id

    ORDER BY h.history_id DESC
";


/* =========================================================
   EXECUTE QUERY
========================================================= */

$result = $conn->query($sql);


if (!$result) {

    echo json_encode([
        "success" => false,
        "message" => "Could not load history.",
        "error" => $conn->error
    ]);

    $conn->close();

    exit;
}


/* =========================================================
   STORE HISTORY
========================================================= */

$history = [];


while ($row = $result->fetch_assoc()) {

    $history[] = $row;
}


/* =========================================================
   SEND JSON RESPONSE
========================================================= */

echo json_encode(
    $history,
    JSON_UNESCAPED_UNICODE
);


/* =========================================================
   CLOSE DATABASE
========================================================= */

$conn->close();

?>