<?php
// Configuration
require_once "includes/header.php";
// Settting time limit to 0 for infinite time.
set_time_limit(0);
$apiKey = 'gtOzM2Ijk5O0U3ZjktOWIyYy00NDAwLWI4ODZTBlAxMTMGUGC';
$baseUrl = "https://api.rlc.com";
$searchItems = ["WeightCertificate", "NmfcCertificate", "BillOfLading", "DeliveryReceipt", "Invoice"];

// Display the upload form if no file has been submitted yet
if (!isset($_POST['upload'])) {
    echo '

    <div class="container">
        <div class="row mt-3">
            <div class= "col-8">
                <h2 class="mt-5">Upload PRO Numbers CSV</h2>
            </div>
        </div>
        <div class="row mt-2">
            <div class= "col-8">
                <form method="POST" enctype="multipart/form-data">
                    <input type="file" name="csv_file" id="csv_file" accept=".csv" required><br>
                    <button type="submit" name="upload" class="btn btn-success mt-2">Process</button>
                </form>
            </div>
        </div>
    </div>


    ';
    require_once "includes/footer.php";
    exit; // Stop execution here until a file is uploaded
}

// -------------------------------------------------------------------
// Process the uploaded file
// -------------------------------------------------------------------

// Prepare output flushing for the progress bar
require_once "includes/functions.php";
ob_implicit_flush(true);
if (ob_get_level() > 0) {
    ob_end_flush();
}

// Output the progress bar container
echo '
<style>
    .progress-container { width: 100%; background: #eee; border-radius: 5px; margin: 20px 0; }
    .progress-bar { width: 0%; height: 25px; background: #28a745; text-align: center; color: white; border-radius: 5px; transition: width 0.3s; }
</style>



    <div class="container"mt-5>
        <div class="row mt-3">
            <div class= "col-8">
                <div class="progress-container"><div id="bar" class="progress-bar mt-5">0%</div></div>
            </div>
        </div>
    </div>
    
    '

;


if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == UPLOAD_ERR_OK) {
    $proArray = [];
    $tmpName = $_FILES['csv_file']['tmp_name'];
    
    // Open and read the CSV
    if (($handle = fopen($tmpName, "r")) !== FALSE) {
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            // Assuming the PRO number is in the very first column (index 0)
            $pro = trim($data[0]);
            
            // Skip empty rows or obvious headers (optional: adjust as needed)
            if (!empty($pro) && strtolower($pro) !== 'pronumber' && strtolower($pro) !== 'pro') {
                $proArray[] = $pro;
            }
        }
        fclose($handle);
    }

    $total = count($proArray);
    $current = 0;

    if ($total === 0) {
        echo "No valid PRO numbers found in the CSV.";
        exit;
    }
    
    // adding progress text to screen
    echo '
    <div class="container"mt-2>
        <div class="row">
            <div class= "col-8">
                Checking '.$total.' shipments from CSV:
                <div id="status_text" style="font-weight: bold; color: #333; margin-bottom: 20px;">Initializing downloads...</div>
            </div>
        </div>
    </div>
    ';
    

    // Loop through the uploaded PROs
    foreach ($proArray as $pro) {
        $current++;
        $percent = round(($current / $total) * 100);
        
        // Update the loading bar via JavaScript
        echo "<script>
            document.getElementById('bar').style.width = '$percent%';
            document.getElementById('bar').innerHTML = '$percent%';
        </script>";
        
        // Force the browser to render the updated script tags immediately
        flush();

        // 1. Call GetDocumentTypes for this PRO
        $typesURL = "$baseUrl/DocumentRetrieval/GetDocumentTypes?ProNumber=$pro";
        
        $chType = curl_init($typesURL);
        curl_setopt($chType, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($chType, CURLOPT_HTTPHEADER, ["apiKey: $apiKey"]);
        
        $typeResponse = curl_exec($chType);
        $typeData = json_decode($typeResponse, true);
        curl_close($chType);

        $availableDocs = $typeData['DocumentTypes'] ?? [];
        $matches = array_intersect($searchItems, $availableDocs);

        // 2. Output and Download findings
        if (!empty($matches)) {
            // Build the query string manually to ensure repeated keys
            $docTypesUrlPart = "";
            foreach ($matches as $type) {
                $docTypesUrlPart .= "&DocumentTypes=" . urlencode($type);
            }

            // Construct the URL
            $combinedUrl = "$baseUrl/DocumentRetrieval?ProNumber=$pro" 
                 . $docTypesUrlPart 
                 . "&AllInOneDocument=true" 
                 . "&MediaType=pdf";

            $chDoc = curl_init($combinedUrl);
            curl_setopt($chDoc, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($chDoc, CURLOPT_HTTPHEADER, ["apiKey: $apiKey"]);
            $docResponse = curl_exec($chDoc);
            $docData = json_decode($docResponse, true);
            curl_close($chDoc);

            // 3. Save the result
            if (!empty($docData['Documents'][0]['Data'])) {
                $savePath = __DIR__ . "/downloads";
                if (!is_dir($savePath)) mkdir($savePath, 0777, true);

                $fileData = base64_decode($docData['Documents'][0]['Data']);
                $fileName = "{$pro}.pdf";
                
                file_put_contents("$savePath/$fileName", $fileData);
                echo "<script>
                    document.getElementById('status_text').innerHTML = 'Saved Multi-page PDF: $fileName';
                    </script>";
                // echo "Saved Multi-page PDF: $fileName<br>";
                flush(); // Flush text output to browser
            }
        }
    }
    
    echo "<br><b>Processing Complete!</b>";
} else {
    echo "Error uploading file. Please try again.";
}



?>