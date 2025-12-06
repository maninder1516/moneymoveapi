<?php

// Simple test to generate JWT token and test transfer endpoint

require __DIR__ . '/vendor/autoload.php';

$baseUrl = 'http://moneymoveapi.com';

// Test 1: Generate JWT token
echo "=== Generating JWT Token ===\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/api/v1/auth/login');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'username' => 'john@example.com',
    'password' => 'password123'
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_VERBOSE, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
if ($error) {
    echo "cURL Error: $error\n";
}
echo "Response: $response\n\n";

if ($httpCode === 200) {
    $data = json_decode($response, true);
    if (isset($data['token'])) {
        $token = $data['token'];
        echo "✅ JWT Token generated successfully\n\n";
        
        // Test 2: Try the transfer endpoint
        echo "=== Testing Transfer Endpoint ===\n";
        
        $transferData = [
            'to_account_number' => 'ACC00010007498344',
            'amount' => '5.00',
            'note' => 'Monthly savings transfer'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $baseUrl . '/api/v1/accounts/4/transfer');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($transferData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $token
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_VERBOSE, false);
        
        $transferResponse = curl_exec($ch);
        $transferHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $transferError = curl_error($ch);
        curl_close($ch);
        
        echo "Transfer HTTP Code: $transferHttpCode\n";
        if ($transferError) {
            echo "Transfer cURL Error: $transferError\n";
        }
        echo "Transfer Response: $transferResponse\n";
        
        // Pretty print the JSON response
        $transferData = json_decode($transferResponse, true);
        if ($transferData) {
            echo "Formatted Response:\n";
            echo json_encode($transferData, JSON_PRETTY_PRINT) . "\n";
        }
    } else {
        echo "❌ No token in response\n";
    }
} else {
    echo "❌ Failed to get JWT token\n";
}