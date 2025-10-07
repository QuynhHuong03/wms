<?php
include_once(__DIR__ . '/connect.php');
class MModel {
    public function getAllModels() {
        $p = new clsKetNoi();
        $con = $p->moKetNoi();
        if ($con) {
            try {
                $col = $con->selectCollection('models');
                $cursor = $col->find([], ['sort' => ['model_id' => 1]]);
                $results = [];
                foreach ($cursor as $doc) {
                    $item = json_decode(json_encode($doc), true);
                    $results[] = $item;
                }
                $p->dongKetNoi($con);
                return $results;
            } catch (\Exception $e) {
                $p->dongKetNoi($con);
                die('Lỗi query MongoDB: ' . $e->getMessage());
            }
        }
        return false;
    }
}
