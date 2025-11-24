<?php
include("../../../../controller/cRoles.php");
$cRoles = new CRoles();

$q = isset($_GET['q']) ? trim($_GET['q']) : '';

if ($q !== '') {
    $roles = $cRoles->searchRolesByName($q);

    if (empty($roles)) {
        echo "<div class='no-result'>Không tìm thấy vai trò</div>";
    } else {
        echo "
            <table>
                <thead>
                    <tr>
                        <th>Mã vai trò</th>
                        <th>Tên vai trò</th>
                        <th>Mô tả</th>
                    </tr>
                </thead>
                <tbody>
        ";

        foreach ($roles as $role) {
            $id = $role['role_id'];
            $name = $role['role_name'];
            $desc = $role['description'];

            echo "
                <tr onclick=\"window.location.href='index.php?page=roles/updateRoles&id={$id}'\">
                    <td>{$id}</td>
                    <td>{$name}</td>
                    <td>{$desc}</td>
                </tr>
            ";
        }

        echo "</tbody></table>";
    }
}
?>