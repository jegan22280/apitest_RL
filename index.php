<?php
// '{baseurl}/DocumentRetrieval?DocumentTypes=WeightCertificate&ProNumber=056391174&AllInOneDocument=true&MediaType=pdf'
// need to use PEAR HTTP_Request2 or cURL
// base url is as follows:
// Production Environment: https://api.rlcarriers.com
// Test/Sandbox Environment: https://api.rlcarriers.com/test
// api key: gtOzM2Ijk5O0U3ZjktOWIyYy00NDAwLWI4ODZTBlAxMTMGUGC
// MediaType is required so i may end up running the call 2x

// ex code:
// 1. Set the URL (Replace {baseurl} with the actual production URL)

$docsURL = "https://api.rlcarriers.com/test/DocumentRetrieval/GetDocumentTypes";
// to list docs I have access to
$typesURL = "https://api.rlcarriers.com/test/DocumentRetrieval/";
// to get docs

// 2. Initialize cURL
$ch = curl_init($typesURL);

// 3. Set headers and options
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'apiKey: gtOzM2Ijk5O0U3ZjktOWIyYy00NDAwLWI4ODZTBlAxMTMGUGC' // Replace with your actual key
]);

// 4. Execute and get the result
$response = curl_exec($ch);

// 5. Check for errors or output the data
if (curl_errno($ch)) {
    echo 'Error: ' . curl_error($ch);
} else {
    echo $response;
}

echo $typesURL;
echo $docsURL;
?>
