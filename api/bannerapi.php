<?php
header("Content-Type: application/json");

require_once "../config/bannerconfig.php";
require_once "../BannerController.php";

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

$controller = new BannerController();
$input = json_decode(file_get_contents("php://input"), true);

switch ($method) {

    case 'POST':
        if ($action === 'createBanner') {
            $result = $controller->createBanner($input);
            echo json_encode($result);
            exit;
        }
        break;

    case 'GET':
        if ($action === 'getAllBanners') {
            $data = $controller->getAllBanners();
            echo json_encode([
                "success" => true,
                "data" => $data
            ]);
            exit;
        }
        break;

    case 'PUT':
        if ($action === 'updateBanner' && isset($_GET['id'])) {
            $id = intval($_GET['id']);
            $result = $controller->updateBanner($id, $input);
            echo json_encode($result);
            exit;
        }
        break;

    case 'DELETE':
        if ($action === 'deleteBanner' && isset($_GET['id'])) {
            $id = intval($_GET['id']);
            $result = $controller->deleteBanner($id);
            echo json_encode(["success" => $result]);
            exit;
        }

        if ($action === 'deleteAll') {
            $result = $controller->deleteAllBanners();
            echo json_encode(["success" => $result]);
            exit;
        }
        break;
}

echo json_encode([
    "success" => false,
    "message" => "Invalid API request"
]);
exit;
