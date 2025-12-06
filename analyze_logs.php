<?php

// Log Analysis Helper - Shows clean, structured logs

require __DIR__ . '/vendor/autoload.php';

echo "=== MoneyMove API - Clean Logging Summary ===\n\n";

$logFile = __DIR__ . '/var/log/dev.log';

if (!file_exists($logFile)) {
    echo "❌ Log file not found at: $logFile\n";
    exit(1);
}

echo "📊 **Recent Transfer Activity:**\n";
echo str_repeat("=", 50) . "\n";

// Read last 50 lines and filter for transfer-related logs
$lines = file($logFile);
$transferLogs = [];
$recentLines = array_slice($lines, -50);

foreach ($recentLines as $line) {
    if (strpos($line, 'Transfer') !== false && strpos($line, 'app.') !== false) {
        // Parse the log line to extract structured data
        if (preg_match('/\[([^\]]+)\] app\.(\w+): ([^{]+)({.*})?/', $line, $matches)) {
            $timestamp = $matches[1];
            $level = $matches[2];
            $message = trim($matches[3]);
            $data = isset($matches[4]) ? json_decode($matches[4], true) : [];
            
            $transferLogs[] = [
                'timestamp' => $timestamp,
                'level' => strtoupper($level),
                'message' => $message,
                'data' => $data
            ];
        }
    }
}

// Display organized transfer logs
$currentTransferId = null;

foreach ($transferLogs as $log) {
    $transferId = $log['data']['transfer_id'] ?? 'unknown';
    
    if ($currentTransferId !== $transferId) {
        if ($currentTransferId !== null) {
            echo "\n";
        }
        echo "🔄 **Transfer ID: {$transferId}**\n";
        $currentTransferId = $transferId;
    }
    
    $time = date('H:i:s', strtotime($log['timestamp']));
    $level = str_pad($log['level'], 5, ' ', STR_PAD_RIGHT);
    
    echo "   [{$time}] {$level} | {$log['message']}\n";
    
    // Show key data points
    if (!empty($log['data'])) {
        $keyData = [];
        if (isset($log['data']['amount'])) $keyData[] = "Amount: {$log['data']['amount']}";
        if (isset($log['data']['from_currency'])) $keyData[] = "From: {$log['data']['from_currency']}";
        if (isset($log['data']['to_currency'])) $keyData[] = "To: {$log['data']['to_currency']}";
        if (isset($log['data']['exchange_rate'])) $keyData[] = "Rate: " . number_format($log['data']['exchange_rate'], 6);
        if (isset($log['data']['amount_credited'])) $keyData[] = "Credited: {$log['data']['amount_credited']}";
        
        if (!empty($keyData)) {
            echo "     └─ " . implode(' | ', $keyData) . "\n";
        }
    }
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "📈 **Log Summary:**\n";
echo "   • Total Transfer Events: " . count($transferLogs) . "\n";
echo "   • Unique Transfers: " . count(array_unique(array_column(array_column($transferLogs, 'data'), 'transfer_id'))) . "\n";
echo "   • Log Levels: " . implode(', ', array_unique(array_column($transferLogs, 'level'))) . "\n";

// Check log levels distribution
$levelCounts = array_count_values(array_column($transferLogs, 'level'));
foreach ($levelCounts as $level => $count) {
    echo "   • {$level}: {$count} events\n";
}

echo "\n✅ **Clean Logging Benefits:**\n";
echo "   • Unique transaction IDs for tracing\n";
echo "   • Structured data for monitoring\n";
echo "   • Different log levels for filtering\n";
echo "   • Minimal noise, maximum insight\n";
echo "   • Production-ready format\n\n";

echo "🔍 **To monitor transfers in real-time:**\n";
echo "   tail -f var/log/dev.log | grep Transfer\n\n";