<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/xml_engine.php';

// Check manager privileges
Auth::requireManager();

$type = sanitize($_GET['type'] ?? 'inventory');

if ($type === 'inventory') {
    $xmlContent = GigsXMLEngine::exportInventoryXML();
    $filename = 'gigscafe_inventory_' . date('Y-m-d_His') . '.xml';
} else {
    die('Invalid export type requested.');
}

// Stream the XML payload as a download
header('Content-Description: File Transfer');
header('Content-Type: application/xml; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
echo $xmlContent;
exit;