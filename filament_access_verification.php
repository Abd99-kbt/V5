<?php

/**
 * Filament Admin Access Verification Script
 * This script confirms the correct URL and server status for Filament admin panel access
 */

echo "=======================================\n";
echo "   FILAMENT ADMIN ACCESS VERIFICATION\n";
echo "=======================================\n\n";

// Server status check
echo "🔍 Server Status Check:\n";
echo "------------------------\n";

// Check if port 8080 is accessible
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8080');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);

$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode == 200) {
    echo "✅ Laravel server is running on port 8080\n";
} else {
    echo "❌ Laravel server is NOT responding on port 8080\n";
    echo "   HTTP Code: $httpCode\n";
}

echo "\n📋 Access Information:\n";
echo "----------------------\n";
echo "Correct URL: http://localhost:8080/admin/login\n";
echo "Username: مدير_شامل\n";
echo "Password: password123\n";

echo "\n🚫 Wrong URLs (DO NOT USE):\n";
echo "----------------------------\n";
echo "❌ http://127.0.0.1:8000/login\n";
echo "❌ http://localhost:8000/login\n";
echo "❌ http://localhost:8080/login (missing /admin path)\n";

echo "\n✅ Correct Access Steps:\n";
echo "------------------------\n";
echo "1. Open your web browser\n";
echo "2. Go to: http://localhost:8080/admin/login\n";
echo "3. Enter username: مدير_شامل\n";
echo "4. Enter password: password123\n";
echo "5. Click Login\n";

echo "\n🔧 Troubleshooting:\n";
echo "-------------------\n";
echo "If the page doesn't load:\n";
echo "- Check if server is running on port 8080\n";
echo "- Visit http://localhost:8080 to test basic connectivity\n";
echo "- Ensure no firewall is blocking port 8080\n";

echo "\nIf login fails:\n";
echo "- Verify username is exactly: مدير_شامل\n";
echo "- Verify password is exactly: password123\n";
echo "- Check browser console for JavaScript errors\n";

echo "\n=======================================\n";
echo "        END OF VERIFICATION\n";
echo "=======================================\n";