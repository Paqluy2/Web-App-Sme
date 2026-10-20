<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';

class GigsXMLEngine
{
    /**
     * Generates a structured XML receipt for a transaction.
     */
    public static function generateReceiptXML(string $transactionCode): string
    {
        $db = getDB();

        $stmt = $db->prepare('
            SELECT s.*, p.Product_name, p.Retail_price, e.First_name, e.Last_name
            FROM salestb s
            JOIN menu_product p ON s.product_id = p.Product_id
            JOIN employees e ON s.employee_id = e.employee_id
            WHERE s.transaction_code = ?
        ');
        $stmt->execute([$transactionCode]);
        $rows = $stmt->fetchAll();

        if (empty($rows)) {
            throw new RuntimeException("No transaction found for code: {$transactionCode}");
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElement('receipt');
        $root->setAttribute('transaction_code', $transactionCode);
        $root->setAttribute('timestamp', $rows[0]['sold_date']);
        $dom->appendChild($root);

        $cashier = $dom->createElement('staff');
        $cashier->appendChild($dom->createElement('id', (string) $rows[0]['employee_id']));
        $cashier->appendChild($dom->createElement('name', $rows[0]['First_name'] . ' ' . $rows[0]['Last_name']));
        $root->appendChild($cashier);

        $itemsNode = $dom->createElement('items');
        $grandTotal = 0.0;

        foreach ($rows as $item) {
            $itemNode = $dom->createElement('item');
            $itemNode->appendChild($dom->createElement('product_id', (string) $item['product_id']));
            $itemNode->appendChild($dom->createElement('name', $item['Product_name']));
            $itemNode->appendChild($dom->createElement('unit_price', (string) $item['Retail_price']));
            $itemNode->appendChild($dom->createElement('quantity', (string) $item['quantity_sold']));
            $itemNode->appendChild($dom->createElement('subtotal', (string) $item['total_amount']));
            $itemsNode->appendChild($itemNode);

            $grandTotal += (float) $item['total_amount'];
        }
        $root->appendChild($itemsNode);

        $totals = $dom->createElement('financials');
        $totals->appendChild($dom->createElement('payment_method', $rows[0]['payment_method']));
        $totals->appendChild($dom->createElement('order_type', $rows[0]['order_type']));
        $totals->appendChild($dom->createElement('grand_total', number_format($grandTotal, 2, '.', '')));
        $root->appendChild($totals);

        return $dom->saveXML();
    }

    /**
     * Exports raw inventory levels as XML.
     */
    public static function exportInventoryXML(): string
    {
        $db = getDB();
        $stmt = $db->query('SELECT * FROM inventory ORDER BY category, material_name');
        $items = $stmt->fetchAll();

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElement('inventory_report');
        $root->setAttribute('exported_at', date('Y-m-d H:i:s'));
        $dom->appendChild($root);

        foreach ($items as $item) {
            $node = $dom->createElement('material');
            $node->setAttribute('id', (string) $item['material_id']);
            $node->appendChild($dom->createElement('name', $item['material_name']));
            $node->appendChild($dom->createElement('category', $item['category'] ?? 'General'));
            $node->appendChild($dom->createElement('stock', (string) $item['current_stock']));
            $node->appendChild($dom->createElement('unit', $item['unit']));

            // Stock warning flag
            $status = ((int) $item['current_stock'] <= 500) ? 'LOW_STOCK' : 'HEALTHY';
            $node->appendChild($dom->createElement('status', $status));
            $root->appendChild($node);
        }

        return $dom->saveXML();
    }
}