<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';

class CafeInventory
{
    /**
     * Checks if all raw ingredients in the recipe table are available
     * before placing an order to avoid negative stock.
     */
    public static function canFulfillProduct(int $productId, int $qtyToSell): bool
    {
        $db = getDB();

        $stmt = $db->prepare('
            SELECT r.material_id, r.amount_needed, inv.material_name, inv.current_stock
            FROM recipe r
            JOIN inventory inv ON r.material_id = inv.material_id
            WHERE r.product_id = ?
        ');
        $stmt->execute([$productId]);
        $ingredients = $stmt->fetchAll();

        foreach ($ingredients as $ing) {
            $totalRequired = (float) $ing['amount_needed'] * $qtyToSell;
            if ((float) $ing['current_stock'] < $totalRequired) {
                return false; // Out of stock on an essential ingredient (e.g. cups, milk, pearls)
            }
        }
        return true;
    }

    /**
     * Returns products categorized for Storefront and POS.
     */
    public static function getMenuCatalog(): array
    {
        $db = getDB();
        $stmt = $db->query('SELECT Product_id, Product_type, Product_name, Retail_price FROM menu_product ORDER BY Product_type, Product_name');
        return $stmt->fetchAll();
    }
}