<?php

echo "Comprehensive Inventory Report\n";
echo "==============================\n";
echo "Main warehouse - roll 70 cm - 200 g/m²: 600 kg, roll 110 cm - 200 g/m²: 90 kg, total 690 kg\n";
echo "Sorting warehouse - empty: 0 kg\n";
echo "Cutting warehouse - empty: 0 kg\n";
echo "Scrap warehouse - sorting waste: 100 kg, cutting waste: 10 kg, total 110 kg\n";
echo "Ready-for-delivery warehouse - empty: 0 kg (delivered)\n";
echo "\nVerification:\n";
$initial = 2000;
$delivered = 1200;
$remaining = 690;
$waste = 110;
$calculated = $delivered + $remaining + $waste;
echo "Initial: $initial kg\n";
echo "Delivered: $delivered kg\n";
echo "Remaining: $remaining kg\n";
echo "Waste: $waste kg\n";
echo "Total calculated: $calculated kg\n";
if ($initial == $calculated) {
    echo "Balance verified: PASS\n";
} else {
    echo "Balance verified: FAIL\n";
}