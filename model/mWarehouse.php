<?php
include_once("connect.php");

class MWarehouse {
    public function getWarehouseTypes() {
        $p = new clsKetNoi();
        $con = $p->moKetNoi();
        $types = [];
        if ($con) {
            $sql = "SELECT * FROM warehouse_types";
            $result = $con->query($sql);
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $types[] = $row;
                }
            }
            $p->dongKetNoi($con);
        }
        return $types;
    }
    public function getWarehousesByType($type_id) {
        $p = new clsKetNoi();
        $con = $p->moKetNoi();
        if ($con) {
            $sql = "SELECT * FROM warehouse WHERE warehouse_type = ?";
            $stmt = $con->prepare($sql);
            $stmt->bind_param("i", $type_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $p->dongKetNoi($con);
            return $result;
        }
        return false;
    }
    public function searchWarehousesByName($name) {
        $p = new clsKetNoi();
        $con = $p->moKetNoi();
        if ($con) {
            $sql = "SELECT w.*, t.name AS type_name 
                    FROM warehouse w 
                    JOIN warehouse_types t ON w.warehouse_type = t.id 
                    WHERE w.warehouse_name LIKE ?";
            $stmt = $con->prepare($sql);
            $like = "%$name%";
            $stmt->bind_param("s", $like);
            $stmt->execute();
            $result = $stmt->get_result();
            $p->dongKetNoi($con);
            return $result;
        }
        return false;
    }
    public function addBranchWarehouse($warehouse_id, $warehouse_name, $address, $status) {
        $p = new clsKetNoi();
        $con = $p->moKetNoi();
        // 2 là warehouse_type cho kho chi nhánh
        $sql = "INSERT INTO warehouse (warehouse_id, warehouse_name, address, status, warehouse_type) VALUES (?, ?, ?, ?, 2)";
        $stmt = $con->prepare($sql);
        $stmt->bind_param("sssi", $warehouse_id, $warehouse_name, $address, $status);
        $result = $stmt->execute();
        $p->dongKetNoi($con);
        return $result;
    }
}
?>