<?php
include 'db.php';

// 1. Fetch products from database
$result = $conn->query("SELECT * FROM GoldProducts");

$apiKey = "goldapi-6a0b2e04339f61d1f06cbd2f9ef17f2b-io";
$symbol = "XAU";
$curr = "USD";
$date = "";

$myHeaders = array(
    'x-access-token: ' . $apiKey,
    'Content-Type: application/json'
);

$curl = curl_init();

$url = "https://www.goldapi.io/api/price/{$symbol}/{$curr}{$date}";

curl_setopt_array($curl, array(
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTPHEADER => $myHeaders
));

$response = curl_exec($curl);
$error = curl_error($curl);

curl_close($curl);

if ($error) {
    echo 'Error: ' . $error;
} else {
    echo $response;
}

$api = "https://api.frankfurter.dev/v2/rate/USD/MYR";

if ($response !== false) {
    $data = json_decode($response, true);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Product List</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 40px;
        }
        table {
            border-collapse: collapse;
            width: 60%;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        h2 {
            margin-bottom: 20px;
        }
        .status-success {
            color: green;
            font-weight: bold;
            margin-bottom: 15px;
        }
        .status-warning {
            color: #d97706;
            font-weight: bold;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>

    <h2>Product List</h2>

    <!-- Dynamic Status Banner -->
        <p>
            Gold Table
        </p>
    <table>
        <tr>
            <th>Name</th>
            <th>Price (MYR)</th>
            <th>Price (USD)</th>
        </tr>
        <?php
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    // Always computes USD using either live rate or fallback rate
                    $usd = $row['price'] * $rate;

                    echo "<tr>
                    <td>" . htmlspecialchars($row['name']) . "</td>
                    <td>" . number_format($row['price'], 2) . "</td>
                    <td>" . number_format($usd, 2) . "</td>
                    </tr>";
                }
            } else {
                echo "<tr><td colspan='3'>No products found</td></tr>";
            }
        ?>
    </table>

</body>
</html> 