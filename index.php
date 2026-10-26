<?php
require_once 'config.php';
require_once 'db.php';

$api_status = true;
$api_message = "Latest API data retrieved successfully.";
$gold_spot_usd = 0;
$usd_myr_rate = 0;
$api_timestamp = date("Y-m-d H:i:s");

// API 1: MetalpriceAPI - Gold Spot Price API (USD per troy ounce)
$metals_url = "https://api.metalpriceapi.com/v1/latest?api_key=" . METALS_API_KEY . "&base=USD&currencies=XAU";

$ch1 = curl_init();
curl_setopt_array($ch1, array(
    CURLOPT_URL => $metals_url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_HTTPHEADER => array('Accept: application/json')
));
$metals_response = curl_exec($ch1);
$metals_error = curl_error($ch1);
curl_close($ch1);

if (!$metals_error && $metals_response) {
    $metals_data = json_decode($metals_response, true);
    
    if (isset($metals_data['rates']['USDXAU'])) {
        $gold_spot_usd = (float)$metals_data['rates']['USDXAU'];
    } elseif (isset($metals_data['rates']['XAU']) && $metals_data['rates']['XAU'] > 0) {
        $gold_spot_usd = 1 / (float)$metals_data['rates']['XAU'];
    } else {
        $api_status = false;
    }
} else {
    $api_status = false;
}

// API 2: Frankfurter - Reference Exchange Rate API (USD to MYR)
$frankfurter_url = "https://api.frankfurter.dev/v2/rate/USD/MYR";

$ch2 = curl_init();
curl_setopt_array($ch2, array(
    CURLOPT_URL => $frankfurter_url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_HTTPHEADER => array('Accept: application/json')
));
$frankfurter_response = curl_exec($ch2);
$frankfurter_error = curl_error($ch2);
curl_close($ch2);

if (!$frankfurter_error && $frankfurter_response) {
    $frankfurter_data = json_decode($frankfurter_response, true);
    if (isset($frankfurter_data['rate'])) {
        $usd_myr_rate = (float)$frankfurter_data['rate'];
    } elseif (isset($frankfurter_data['rates']['MYR'])) {
        $usd_myr_rate = (float)$frankfurter_data['rates']['MYR'];
    } else {
        $api_status = false;
    }
} else {
    $api_status = false;
}

// Fallback logic for basic error handling
if (!$api_status || $gold_spot_usd == 0 || $usd_myr_rate == 0) {
    $gold_spot_usd = FALLBACK_GOLD_SPOT_USD;
    $usd_myr_rate = FALLBACK_USD_MYR_RATE;
    $api_message = "Warning: API request failed or quota exceeded. Displaying fallback values.";
}

// Pure-gold price (MYR/gram) = (Gold spot price * USD-to-MYR rate) / 31.1035
$pure_gold_price_myr_g = ($gold_spot_usd * $usd_myr_rate) / 31.1035;

// SQL JOIN Query mapped to exact database schema
$sql = "SELECT 
            o.OrderID AS order_id,
            c.Name AS customer_name,
            p.GoldName AS product_name,
            p.WeightInGM AS weight_grams,
            p.GoldPurity AS purity,
            o.Quantity AS quantity,
            '2026-10-24' AS order_date
        FROM orders o
        JOIN customers c ON o.CustID = c.CustID
        JOIN goldproducts p ON o.GoldID = p.GoldID
        ORDER BY o.OrderID ASC";

$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Orders & Gold Intrinsic Value</title>
    
    <style>
        /* 1. Page Background & Font */
        body {
            font-family: Arial, sans-serif;
            margin: 40px;
            background-color: #f4f161; /* Change page background color here */
            color: #1e293b;            /* Main text color */
        }

        h2 {
            margin-bottom: 15px;
            color: #0f172a;
        }

        /* 2. Status Banner Box */
        .status-banner {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-weight: bold;
        }
        .status-success {
            background-color: #d1fae5; /* Success banner background (Light Green) */
            color: #065f46;            /* Text color */
            border: 1px solid #a7f3d0;
        }
        .status-warning {
            background-color: #fef3c7; /* Warning banner background (Light Amber) */
            color: #92400e;            /* Text color */
            border: 1px solid #fde68a;
        }

        /* 3. API Summary Metadata Box */
        .meta-info {
            background-color: #ffffff;    /* Metadata box background (White) */
            border-left: 5px solid #d97706;/* Left accent border color (Gold/Amber) */
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 25px;
            line-height: 1.6;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        /* 4. Main Data Table */
        table {
            border-collapse: collapse;
            width: 100%;
            background-color: #ffffff; /* Table inner background */
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border-radius: 6px;
            overflow: hidden;
        }

        th, td {
            border: 1px solid #cbd5e1; /* Cell border color */
            padding: 12px 15px;
            text-align: left;
        }

        /* 5. Table Header Row */
        th {
            background-color: #1e293b; /* Header row background (Dark Navy/Slate) */
            color: #ffffff;            /* Header text color (White) */
            font-weight: bold;
        }

        /* 6. Alternating Rows & Hover Effects */
        tr:nth-child(even) {
            background-color: #f8fafc; /* Even row background color */
        }

        tr:hover {
            background-color: #e2e8f0; /* Row hover highlight color */
        }
    </style>
</head>
<body>

    <h2>Product List & Customer Orders</h2>

    <!-- Dynamic Status Banner -->
    <div class="status-banner <?php echo $api_status ? 'status-success' : 'status-warning'; ?>">
        API Status: <?php echo htmlspecialchars($api_message); ?>
    </div>

    <!-- API Information Metadata -->
    <div class="meta-info">
        <p><strong>Latest spot price (MetalpriceAPI):</strong> $<?php echo number_format($gold_spot_usd, 2); ?> USD / troy ounce</p>
        <p><strong>Latest available reference exchange rate (Frankfurter):</strong> 1 USD = MYR <?php echo number_format($usd_myr_rate, 4); ?></p>
        <p><strong>Calculated Pure-Gold Rate:</strong> MYR <?php echo number_format($pure_gold_price_myr_g, 2); ?> / gram</p>
        <p><strong>API Timestamp:</strong> <?php echo $api_timestamp; ?></p>
    </div>

    <!-- Output Table -->
    <table>
        <thead>
            <tr>
                <th>Order ID</th>
                <th>Customer Name</th>
                <th>Gold Product</th>
                <th>Purity</th>
                <th>Weight / Quantity</th>
                <th>Order Date</th>
                <th>Gold Rate (MYR/g)</th>
                <th>Estimated Intrinsic Value (MYR)</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    // Purity factor (e.g., 916 / 1000 = 0.916)
                    $purity_factor = $row['purity'] / 1000;

                    // Total weight (weight * quantity)
                    $total_weight = $row['weight_grams'] * $row['quantity'];

                    // Estimated intrinsic gold value
                    $intrinsic_value = $pure_gold_price_myr_g * $total_weight * $purity_factor;

                    echo "<tr>
                        <td>" . htmlspecialchars($row['order_id']) . "</td>
                        <td>" . htmlspecialchars($row['customer_name']) . "</td>
                        <td>" . htmlspecialchars($row['product_name']) . "</td>
                        <td>" . htmlspecialchars($row['purity']) . "</td>
                        <td>" . number_format($row['weight_grams'], 2) . "g (Qty: " . htmlspecialchars($row['quantity']) . ")</td>
                        <td>" . htmlspecialchars($row['order_date']) . "</td>
                        <td>MYR " . number_format($pure_gold_price_myr_g, 2) . "</td>
                        <td>MYR " . number_format($intrinsic_value, 2) . "</td>
                    </tr>";
                }
            } else {
                echo "<tr><td colspan='8'>No records found</td></tr>";
            }
            ?>
        </tbody>
    </table>

</body>
</html>