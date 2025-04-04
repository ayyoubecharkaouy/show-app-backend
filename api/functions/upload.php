<?php
function handleImageUpload() {
    if (empty($_FILES['image'])) {
        return null;
    }

    // $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
    // $fileType = $_FILES['image']['type'];
    
    // if (!in_array($fileType, $allowedTypes)) {
    //     return null;
    // }
    
    $uploadDir = __DIR__ . '/../uploads/';
    $filename = time() . '_' . basename($_FILES['image']['name']);
    $targetPath = $uploadDir . $filename;
    
    if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
        return '/uploads/' . $filename;
    }
    
    return null;
}
?>