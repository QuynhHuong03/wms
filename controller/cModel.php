<?php
include_once(__DIR__ . '/../model/mModel.php');
class CModel {
    public function getAllModels() {
        $p = new MModel();
        return $p->getAllModels();
    }
}
