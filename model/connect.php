<?php
    class clsKetNoi{
        public function moKetNoi(){
            return mysqli_connect("localhost","root","", "quanlykhohang");
        }
        public function dongKetNoi($con){
        $con->close();
        }
    }
    
?>