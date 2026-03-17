<?php
require '../auth.php';
require '../connection.php';
header('Content-Type: application/json');

if($_SESSION['role']!=='admin'){
    echo json_encode(['status'=>'error','message'=>'Unauthorized']);
    exit;
}

validate_csrf();

$user_id = $_POST['user_id'] ?? 0;
if(!$user_id){
    echo json_encode(['status'=>'error','message'=>'Missing user id']);
    exit;
}

// Check if already paid
$stmt = $pdo->prepare("SELECT COUNT(*) FROM sales WHERE user_id=?");
$stmt->execute([$user_id]);
$paid = $stmt->fetchColumn();

if($paid>0){
    // Remove payment (for toggle)
    $stmt = $pdo->prepare("DELETE FROM sales WHERE user_id=?");
    $stmt->execute([$user_id]);
    $paid = 0;
}else{
    // Add payment
    $stmt = $pdo->prepare("INSERT INTO sales(user_id,amount,sale_date) VALUES(?,?,NOW())");
    $stmt->execute([$user_id,0]);
    $paid = 1;
}

echo json_encode(['status'=>'success','is_paid'=>$paid]);
