<?php

require_once "db.php";

/* =========================================================
   GET BILL ID
========================================================= */

$billId = intval($_GET["id"] ?? 0);

if ($billId <= 0) {
    die("Invalid Bill ID");
}


/* =========================================================
   GET BILL DATA
========================================================= */

$stmt = $conn->prepare("
    SELECT
        b.bill_id,
        b.repair_id,
        b.customer_id,
        b.amount,
        b.payment_method,
        b.payment_status,
        b.parts_cost,
        b.labor_cost,
        b.discount,
        b.total,
        b.bill_date,

        c.name AS customer_name,
        c.phone,
        c.email,
        c.address,

        r.device_type,
        r.brand,
        r.model,
        r.issue

    FROM bills b

    LEFT JOIN customers c
        ON b.customer_id = c.customer_id

    LEFT JOIN repairs r
        ON b.repair_id = r.repair_id

    WHERE b.bill_id = ?

    LIMIT 1
");

$stmt->bind_param("i", $billId);
$stmt->execute();

$result = $stmt->get_result();

$bill = $result->fetch_assoc();

$stmt->close();


if (!$bill) {
    die("Bill not found");
}


/* =========================================================
   DATA
========================================================= */

$customerName =
    $bill["customer_name"] ?? "Customer";

$phone =
    $bill["phone"] ?? "";

$email =
    $bill["email"] ?? "";

$address =
    $bill["address"] ?? "";

$device =
    $bill["device_type"] ?? "";

$brand =
    $bill["brand"] ?? "";

$model =
    $bill["model"] ?? "";

$issue =
    $bill["issue"] ?? "";

$paymentMethod =
    $bill["payment_method"] ?? "Cash";

$paymentStatus =
    $bill["payment_status"] ?? "Pending";

$parts =
    (float)($bill["parts_cost"] ?? 0);

$labor =
    (float)($bill["labor_cost"] ?? 0);

$discount =
    (float)($bill["discount"] ?? 0);

$total =
    (float)(
        $bill["total"]
        ?? $bill["amount"]
        ?? 0
    );


$billDate =
    !empty($bill["bill_date"])
    ? date(
        "d M Y",
        strtotime($bill["bill_date"])
    )
    : date("d M Y");


/* =========================================================
   PDF HELPER
========================================================= */

function pdfText($text)
{
    $text = (string)$text;

    $text = str_replace(
        ["\\", "(", ")", "\r", "\n"],
        ["\\\\", "\\(", "\\)", "", " "],
        $text
    );

    /*
     * Keep basic ASCII for maximum compatibility.
     */
    $text = preg_replace(
        '/[^\x20-\x7E]/',
        '',
        $text
    );

    return $text;
}


/* =========================================================
   BUILD PDF CONTENT
========================================================= */

$content = "";

$content .= "BT\n";
$content .= "/F1 24 Tf\n";
$content .= "0.12 0.35 0.75 rg\n";
$content .= "50 790 Td\n";
$content .= "(REPXA) Tj\n";

$content .= "/F1 10 Tf\n";
$content .= "0 0 0 rg\n";
$content .= "0 -18 Td\n";
$content .= "(Repair Management System) Tj\n";

$content .= "/F1 18 Tf\n";
$content .= "400 18 Td\n";
$content .= "(INVOICE) Tj\n";

$content .= "/F1 10 Tf\n";
$content .= "0 -18 Td\n";
$content .= "(Bill #"
    . pdfText($billId)
    . ") Tj\n";

$content .= "0 -15 Td\n";
$content .= "(Date: "
    . pdfText($billDate)
    . ") Tj\n";


/* LINE */

$content .= "BT\n";
$content .= "0.12 0.35 0.75 RG\n";
$content .= "2 w\n";
$content .= "50 735 m\n";
$content .= "545 735 l\n";
$content .= "S\n";


/* CUSTOMER */

$content .= "BT\n";
$content .= "/F1 10 Tf\n";
$content .= "0 0 0 rg\n";
$content .= "50 705 Td\n";
$content .= "(BILL TO) Tj\n";

$content .= "/F1 12 Tf\n";
$content .= "0 -20 Td\n";
$content .= "("
    . pdfText($customerName)
    . ") Tj\n";

$content .= "/F1 10 Tf\n";
$content .= "0 -16 Td\n";
$content .= "("
    . pdfText($phone)
    . ") Tj\n";


if ($email !== "") {

    $content .= "0 -15 Td\n";
    $content .= "("
        . pdfText($email)
        . ") Tj\n";
}


if ($address !== "") {

    $content .= "0 -15 Td\n";
    $content .= "("
        . pdfText($address)
        . ") Tj\n";
}


/* PAYMENT */

$content .= "BT\n";
$content .= "/F1 10 Tf\n";
$content .= "0 0 0 rg\n";
$content .= "360 705 Td\n";
$content .= "(PAYMENT) Tj\n";

$content .= "/F1 11 Tf\n";
$content .= "0 -20 Td\n";
$content .= "(Mode: "
    . pdfText($paymentMethod)
    . ") Tj\n";

$content .= "0 -18 Td\n";
$content .= "(Status: "
    . pdfText($paymentStatus)
    . ") Tj\n";

$content .= "0 -18 Td\n";
$content .= "(Repair #"
    . pdfText($bill["repair_id"])
    . ") Tj\n";


/* DEVICE */

$content .= "BT\n";
$content .= "/F1 10 Tf\n";
$content .= "50 590 Td\n";
$content .= "(DEVICE DETAILS) Tj\n";

$content .= "/F1 11 Tf\n";
$content .= "0 -20 Td\n";

$deviceLine =
    trim(
        $device
        . " | "
        . $brand
        . " | "
        . $model
    );

$content .= "("
    . pdfText($deviceLine)
    . ") Tj\n";


if ($issue !== "") {

    $content .= "/F1 10 Tf\n";
    $content .= "0 -20 Td\n";
    $content .= "(Issue: "
        . pdfText($issue)
        . ") Tj\n";
}


/* TABLE HEADER */

$content .= "BT\n";
$content .= "/F1 10 Tf\n";
$content .= "50 520 Td\n";
$content .= "(DESCRIPTION) Tj\n";

$content .= "300 0 Td\n";
$content .= "(AMOUNT) Tj\n";


/* TABLE LINE */

$content .= "BT\n";
$content .= "0.75 0.75 0.75 RG\n";
$content .= "1 w\n";
$content .= "50 505 m\n";
$content .= "545 505 l\n";
$content .= "S\n";


/* PARTS */

$content .= "BT\n";
$content .= "/F1 11 Tf\n";
$content .= "0 0 0 rg\n";
$content .= "50 480 Td\n";
$content .= "(Parts Cost) Tj\n";

$content .= "300 0 Td\n";
$content .= "(Rs. "
    . number_format($parts, 2)
    . ") Tj\n";


/* LABOR */

$content .= "BT\n";
$content .= "/F1 11 Tf\n";
$content .= "50 450 Td\n";
$content .= "(Labor Cost) Tj\n";

$content .= "300 0 Td\n";
$content .= "(Rs. "
    . number_format($labor, 2)
    . ") Tj\n";


/* DISCOUNT */

$content .= "BT\n";
$content .= "/F1 11 Tf\n";
$content .= "50 420 Td\n";
$content .= "(Discount) Tj\n";

$content .= "300 0 Td\n";
$content .= "(Rs. -"
    . number_format($discount, 2)
    . ") Tj\n";


/* TOTAL LINE */

$content .= "BT\n";
$content .= "0.12 0.35 0.75 RG\n";
$content .= "2 w\n";
$content .= "50 390 m\n";
$content .= "545 390 l\n";
$content .= "S\n";


/* TOTAL */

$content .= "BT\n";
$content .= "/F1 15 Tf\n";
$content .= "0 0 0 rg\n";
$content .= "50 355 Td\n";
$content .= "(TOTAL) Tj\n";

$content .= "300 0 Td\n";
$content .= "0.12 0.35 0.75 rg\n";
$content .= "(Rs. "
    . number_format($total, 2)
    . ") Tj\n";


/* FOOTER */

$content .= "BT\n";
$content .= "/F1 10 Tf\n";
$content .= "0 0 0 rg\n";
$content .= "50 100 Td\n";
$content .= "(Thank you for choosing REPXA.) Tj\n";

$content .= "0 -16 Td\n";
$content .= "(Please keep this invoice for your records.) Tj\n";

$content .= "ET\n";


/* =========================================================
   CREATE PDF OBJECTS
========================================================= */

$objects = [];


/* 1 CATALOG */

$objects[] =
    "<< /Type /Catalog /Pages 2 0 R >>";


/* 2 PAGES */

$objects[] =
    "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";


/* 3 PAGE */

$objects[] =
    "<<
        /Type /Page
        /Parent 2 0 R
        /MediaBox [0 0 595 842]
        /Resources <<
            /Font <<
                /F1 5 0 R
            >>
        >>
        /Contents 4 0 R
    >>";


/* 4 CONTENT */

$objects[] =
    "<< /Length "
    . strlen($content)
    . " >>
stream
"
    . $content
    . "
endstream";


/* 5 FONT */

$objects[] =
    "<<
        /Type /Font
        /Subtype /Type1
        /BaseFont /Helvetica
    >>";


/* =========================================================
   BUILD FINAL PDF
========================================================= */

$pdf =
    "%PDF-1.4\n";


$offsets = [];

for (
    $i = 0;
    $i < count($objects);
    $i++
) {

    $objectNumber =
        $i + 1;

    $offsets[
        $objectNumber
    ] = strlen($pdf);


    $pdf .=
        $objectNumber
        . " 0 obj\n"
        . $objects[$i]
        . "\nendobj\n";
}


/* XREF */

$xrefPosition =
    strlen($pdf);


$pdf .=
    "xref\n";

$pdf .=
    "0 "
    . (count($objects) + 1)
    . "\n";


$pdf .=
    "0000000000 65535 f \n";


for (
    $i = 1;
    $i <= count($objects);
    $i++
) {

    $pdf .=
        sprintf(
            "%010d 00000 n \n",
            $offsets[$i]
        );
}


/* TRAILER */

$pdf .=
    "trailer\n";

$pdf .=
    "<<
        /Size "
        . (count($objects) + 1)
        . "
        /Root 1 0 R
    >>\n";


$pdf .=
    "startxref\n"
    . $xrefPosition
    . "\n"
    . "%%EOF";


/* =========================================================
   DOWNLOAD PDF
========================================================= */

$filename =
    "REPXA_Bill_"
    . $billId
    . ".pdf";


header(
    "Content-Type: application/pdf"
);

header(
    "Content-Disposition: attachment; filename=\""
    . $filename
    . "\""
);

header(
    "Content-Length: "
    . strlen($pdf)
);

header(
    "Cache-Control: private, max-age=0, must-revalidate"
);

header(
    "Pragma: public"
);


echo $pdf;

exit;

?>