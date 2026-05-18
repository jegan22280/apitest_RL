<?php
require_once "includes/functions.php";

if (ob_get_level() == 0) ob_start();
ob_implicit_flush(true);

echo "
<style>
    .progress-container { width: 100%; background: #eee; border-radius: 5px; margin: 20px 0; border: 1px solid #ccc; }
    .progress-bar { width: 0%; height: 25px; background: #28a745; text-align: center; color: white; border-radius: 5px; transition: width 0.3s; line-height: 25px; font-family: sans-serif;}
    #status { font-family: sans-serif; font-size: 14px; color: #555; margin-bottom: 5px; }
</style>
<div id='status'>Initializing...</div>
<div class='progress-container'><div id='bar' class='progress-bar'>0%</div></div>
";


// Configuration
$apiKey = 'gtOzM2Ijk5O0U3ZjktOWIyYy00NDAwLWI4ODZTBlAxMTMGUGC';
$baseUrl = "https://api.rlc.com";
$searchItems = ["WeightCertificate", "NmfcCertificate", "BillOfLading","DeliveryReceipt", "Invoice"];
$headers = ["apiKey: $apiKey", "Content-Type: application/json"];

// 1. Fetch History
$historyUrl = "$baseUrl/ActivityHistory/ShipmentHistory";
$historyPayload = json_encode([
    "StartDate" => date('m/d/Y', strtotime('-1 week')), 
    "EndDate" => date('m/d/Y'),
    "ShipmentStatus" => "DELIVERED"
]);

$ch = curl_init($historyUrl);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($ch, CURLOPT_POSTFIELDS, $historyPayload);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
$historyResponse = curl_exec($ch);
$historyData = json_decode($historyResponse, true);
curl_close($ch);

if (!empty($historyData['ShipmentHistoryResults'])) {
    $results = $historyData['ShipmentHistoryResults'];
    $total = count($results);
    $current = 0;

    foreach ($results as $shipment) {
        $pro = $shipment['ShipmentInformation']['ProNumber'] ?? null;
        if (!$pro) {
            $current++; // Count skipped items to keep percentage accurate
            continue;
        }

        // Update status text so you know what's happening
        echo "<script>document.getElementById('status').innerHTML = 'Processing PRO #$pro...';</script>";
        force_flush();

        // 2. Get Document Types
        $typesURL = "$baseUrl/DocumentRetrieval/GetDocumentTypes?ProNumber=$pro";
        $chType = curl_init($typesURL);
        curl_setopt($chType, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($chType, CURLOPT_HTTPHEADER, $headers);
        $typeResponse = curl_exec($chType);
        $typeData = json_decode($typeResponse, true);
        curl_close($chType);

        $availableDocs = $typeData['DocumentTypes'] ?? [];
        $matches = array_intersect($searchItems, $availableDocs);

        if (!empty($matches)) {
            $docTypesUrlPart = "";
            foreach ($matches as $type) {
                $docTypesUrlPart .= "&DocumentTypes=" . urlencode($type);
            }

            // 3. Download (Using the combined params found in the R+L Documentation)
            $combinedUrl = "$baseUrl/DocumentRetrieval?ProNumber=$pro" . $docTypesUrlPart . "&AllInOneDocument=true&AllOneDocument=true&MediaType=pdf";

            $chDoc = curl_init($combinedUrl);
            curl_setopt($chDoc, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($chDoc, CURLOPT_HTTPHEADER, $headers);
            $docResponse = curl_exec($chDoc);
            $docData = json_decode($docResponse, true);
            curl_close($chDoc);

            if (!empty($docData['Documents'][0]['Data'])) {
                $savePath = __DIR__ . "/downloads";
                if (!is_dir($savePath)) mkdir($savePath, 0777, true);
                
                $fileData = base64_decode($docData['Documents'][0]['Data']);
                file_put_contents("$savePath/{$pro}.pdf", $fileData);
            }
        }

        // Update progress bar AFTER the work is done
        $current++;
        $percent = round(($current / $total) * 100);
        
        echo "<script>
            document.getElementById('bar').style.width = '$percent%';
            document.getElementById('bar').innerHTML = '$percent%';
            if($percent == 100) document.getElementById('status').innerHTML = 'All Downloads Complete!';
        </script>";
        
        force_flush();
    }
} else {
    echo "No shipments found.";
}
?>