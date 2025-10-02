<?php
require __DIR__ . '/../vendor/autoload.php';

use MongoDB\Client;

class clsKetNoi {
    private $uri = 'mongodb://127.0.0.1:27017';      // URI MongoDB trên laptop
    private $dbName = 'WMS'; //'wms';                        // mặc định DB (đổi nếu cần)
    private $client = null;

    // Bạn có thể truyền URI / dbName khi tạo object nếu muốn
    public function __construct(string $uri = null, string $dbName = null) {
        if ($uri !== null) $this->uri = $uri;
        if ($dbName !== null) $this->dbName = $dbName;
    }

    // Giữ nguyên tên hàm: moKetNoi()
    // Trả về đối tượng MongoDB\Database (tương tự "kết nối" MySQL)
    public function moKetNoi() {
        if ($this->client === null) {
            $this->client = new Client($this->uri);
        }
        return $this->client->selectDatabase($this->dbName);
    }

    // Giữ nguyên tên hàm: dongKetNoi($con)
    // MongoDB driver không có close(); để giải phóng, ta null client trong class.
    // Lưu ý: nếu muốn unset biến $con ở scope caller thì cần truyền theo tham chiếu (&$con) —
    // mình giữ chữ ký hàm như cũ để bạn không phải đổi nơi gọi.
    public function dongKetNoi($con) {
        // giải phóng client ở bên trong object
        $this->client = null;
        // đặt local $con = null (không ảnh hưởng biến ở caller vì không pass theo tham chiếu)
        $con = null;
        return true;
    }
}