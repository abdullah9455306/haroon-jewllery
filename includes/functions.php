<?php
// Function to get active categories from database
function getActiveCategories($parent_id = null) {
    global $pdo;

    try {
        $sql = "SELECT id, name, slug FROM categories WHERE status = 'active'";

        if ($parent_id === null) {
            $sql .= " AND parent_id IS NULL";
        } else {
            $sql .= " AND parent_id = :parent_id";
        }

        $sql .= " ORDER BY sort_order ASC, name ASC";

        $stmt = $pdo->prepare($sql);

        if ($parent_id !== null) {
            $stmt->bindParam(':parent_id', $parent_id, PDO::PARAM_INT);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching categories: " . $e->getMessage());
        return [];
    }
}

// Function to get all main categories (for header dropdown)
function getMainCategories() {
    return getActiveCategories(null); // null gets parent categories
}

// Function to get popular categories (for footer)
function getPopularCategories($limit = 5) {
    global $pdo;

    try {
        $sql = "SELECT c.id, c.name, c.slug
                FROM categories c
                WHERE c.status = 'active' AND c.parent_id IS NULL
                ORDER BY c.sort_order ASC, c.name ASC
                LIMIT :limit";

        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching popular categories: " . $e->getMessage());
        return [];
    }
}
?>