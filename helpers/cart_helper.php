<?php
class CartHelper {
    private $conn;

    public function __construct() {
        $db = new Database();
        $this->conn = $db->getConnection();
    }

    // Add item to cart (database)
    public function addToCart($user_id, $product_id, $quantity = 1, $price) {
        try {
            // Check if item already exists in cart
            $checkStmt = $this->conn->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
            $checkStmt->execute([$user_id, $product_id]);
            $existingItem = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($existingItem) {
                // Update quantity if item exists
                $updateStmt = $this->conn->prepare("UPDATE cart SET quantity = quantity + ?, updated_at = NOW() WHERE id = ?");
                $updateStmt->execute([$quantity, $existingItem['id']]);
            } else {
                // Insert new item
                $insertStmt = $this->conn->prepare("INSERT INTO cart (user_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
                $insertStmt->execute([$user_id, $product_id, $quantity, $price]);
            }

            return true;
        } catch (PDOException $e) {
            error_log("Cart Error: " . $e->getMessage());
            return false;
        }
    }

    public function updateCartItem($userId, $itemId, $quantity) {
        try {
            $stmt = $this->conn->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND id = ?");
            return $stmt->execute([$quantity, $userId, $itemId]);
        } catch (Exception $e) {
            error_log("Update cart item error: " . $e->getMessage());
            return false;
        }
    }

    public function removeFromCart($userId, $itemId) {
        try {
            $stmt = $this->conn->prepare("DELETE FROM cart WHERE user_id = ? AND id = ?");
            return $stmt->execute([$userId, $itemId]);
        } catch (Exception $e) {
            error_log("Remove from cart error: " . $e->getMessage());
            return false;
        }
    }

    // Get cart items for user with images from product_images table
    public function getCartItems($userId) {
        try {
            $stmt = $this->conn->prepare("
                SELECT
                    c.id,
                    c.product_id,
                    c.quantity,
                    c.price,
                    p.name,
                    p.sku,
                    p.image as main_image,
                    p.stock_quantity,
                    (
                        SELECT pi.image_path
                        FROM product_images pi
                        WHERE pi.product_id = p.id
                        ORDER BY pi.sort_order ASC, pi.id ASC
                        LIMIT 1
                    ) as gallery_image
                FROM cart c
                JOIN products p ON c.product_id = p.id
                WHERE c.user_id = ?
            ");
            $stmt->execute([$userId]);
            $cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Process images to ensure we have the correct image path
            foreach ($cartItems as &$item) {
                $item['image'] = $this->getProductImage($item);
            }

            return $cartItems;
        } catch (Exception $e) {
            error_log("Get cart items error: " . $e->getMessage());
            return [];
        }
    }

    // Helper function to get the correct product image
    private function getProductImage($item) {
        // Priority: 1. Gallery image, 2. Main product image, 3. Placeholder
        if (!empty($item['gallery_image'])) {
            return $item['gallery_image'];
        } elseif (!empty($item['main_image'])) {
            return $item['main_image'];
        } else {
            return 'assets/images/placeholder.jpg';
        }
    }

    // Alternative method to get all product images for a cart item
    public function getProductImages($productId) {
        try {
            $stmt = $this->conn->prepare("
                SELECT image_path, alt_text, sort_order
                FROM product_images
                WHERE product_id = ?
                ORDER BY sort_order ASC, id ASC
            ");
            $stmt->execute([$productId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Get product images error: " . $e->getMessage());
            return [];
        }
    }

    public function clearCart($userId) {
        try {
            $stmt = $this->conn->prepare("DELETE FROM cart WHERE user_id = ?");
            $stmt->execute([$userId]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Clear Cart Error: " . $e->getMessage());
            return false;
        }
    }

    // Get cart count
    public function getCartCount($user_id) {
        try {
            $stmt = $this->conn->prepare("SELECT SUM(quantity) as total FROM cart WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['total'] ?? 0;
        } catch (PDOException $e) {
            return 0;
        }
    }
}
?>