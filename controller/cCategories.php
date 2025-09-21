<?php
require_once(__DIR__ . '/../model/mCategories.php');

class CCategories {
    private $mCategories;

    public function __construct() {
        $this->mCategories = new MCategories();
    }

    // Lấy tất cả categories
    public function getAllCategories() {
        try {
            $categories = $this->mCategories->SelectAllCategories();
            if (!$categories) return [];
            return $categories;
        } catch (Exception $e) {
            error_log("getAllCategories error: " . $e->getMessage());
            return [];
        }
    }

    // Lấy category theo ID
    public function getCategoryById($id) {
        try {
            return $this->mCategories->SelectCategoryById($id);
        } catch (Exception $e) {
            error_log("getCategoryById error: " . $e->getMessage());
            return null;
        }
    }

    // Thêm category
    public function addCategory($name, $code, $description, $status) {
        try {
            return $this->mCategories->addCategory($name, $code, $description, $status);
        } catch (Exception $e) {
            error_log("addCategory error: " . $e->getMessage());
            return false;
        }
    }

    // Cập nhật category
    public function updateCategory($id, $data) {
        try {
            return $this->mCategories->updateCategory($id, $data);
        } catch (Exception $e) {
            error_log("updateCategory error: " . $e->getMessage());
            return false;
        }
    }

    // Xóa category
    public function deleteCategory($id) {
        try {
            return $this->mCategories->deleteCategory($id);
        } catch (Exception $e) {
            error_log("deleteCategory error: " . $e->getMessage());
            return false;
        }
    }
}
?>
