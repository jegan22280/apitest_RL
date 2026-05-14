<?php
require_once "includes/functions.php";
// 10. Configuration
$apiKey = 'gtOzM2Ijk5O0U3ZjktOWIyYy00NDAwLWI4ODZTBlAxMTMGUGC';
$baseUrl = "https://api.rlc.com";
$searchItems = ["WeightCertificate", "NmfcCertificate", "BillOfLading","DeliveryReceipt", "Invoice"];
$proArray = [];

// 20. Fetch Delivered Shipments (Activity History)
$historyUrl = "$baseUrl/ActivityHistory/ShipmentHistory";

// Note: StartDate can only be up to 1 month ago
$historyPayload = json_encode([
    "StartDate" => date('m/d/Y', strtotime('-1 month')), 
    "EndDate" => date('m/d/Y'),
    "ShipmentStatus" => "DELIVERED"
]);

$ch = curl_init($historyUrl);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($ch, CURLOPT_POSTFIELDS, $historyPayload);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "apiKey: $apiKey",
    "Content-Type: application/json"
]);

$historyResponse = curl_exec($ch);
$historyData = json_decode($historyResponse, true);
curl_close($ch);


// 30. Process results and check documents
if (!empty($historyData['ShipmentHistoryResults'])) {
    echo "## Checking " . count($historyData['ShipmentHistoryResults']) . " delivered shipments:\n\n";
    echo "<br>";
    foreach ($historyData['ShipmentHistoryResults'] as $shipment) {
        // Correct path based on documentation for Activity History response
        $pro = $shipment['ShipmentInformation']['ProNumber'] ?? null;
        
        if (!$pro) continue;

        // 40. Call GetDocumentTypes for this PRO
        $typesURL = "$baseUrl/DocumentRetrieval/GetDocumentTypes?ProNumber=$pro";
        
        $chType = curl_init($typesURL);
        curl_setopt($chType, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($chType, CURLOPT_HTTPHEADER, ["apiKey: $apiKey"]);
        
        $typeResponse = curl_exec($chType);
        $typeData = json_decode($typeResponse, true);
        curl_close($chType);

        $availableDocs = $typeData['DocumentTypes'] ?? [];
        $matches = array_intersect($searchItems, $availableDocs);

        // 50. Output findings
        echo "PRO #$pro: ";
        if (!empty($matches)) {
            echo "Found (" . implode(", ", $matches) . ")\n";
            array_push($proArray, $pro);
        } else {
            echo "No matching documents found.\n";
        }
        echo "<br>";
    }
} else {
    echo "No delivered shipments found in the specified date range.";
    // Debugging: echo $historyResponse; 
}
?>