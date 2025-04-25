<?php
// Configuration
$uploadDirectory = 'uploads/'; // Directory to store uploads
$audioDirectory = 'audio/'; // Directory to store audio output
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif']; // Allowed file types
$maxFileSize = 10 * 1024 * 1024; // 10MB max file size

// Create directories if they don't exist
if (!file_exists($uploadDirectory)) {
    mkdir($uploadDirectory, 0755, true);
}
if (!file_exists($audioDirectory)) {
    mkdir($audioDirectory, 0755, true);
}

// Initialize response array
$response = [
    'success' => false,
    'message' => '',
    'imagePath' => '',
    'audioUrl' => ''
];

// Check if form was submitted with file
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
    $file = $_FILES['image'];
    
    // Validate file was uploaded successfully
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $response['message'] = 'Upload failed with error code: ' . $file['error'];
    }
    // Validate file size
    elseif ($file['size'] > $maxFileSize) {
        $response['message'] = 'File is too large. Maximum size is ' . ($maxFileSize / 1024 / 1024) . 'MB.';
    } 
    // Validate file type
    elseif (!in_array($file['type'], $allowedTypes)) {
        $response['message'] = 'Invalid file type. Allowed types: JPG, PNG, GIF.';
    } 
    else {
        // Generate unique filename to prevent overwriting
        $filename = uniqid() . '_' . basename($file['name']);
        $uploadPath = $uploadDirectory . $filename;
        
        // Move uploaded file to the upload directory
        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
            // Generate audio output filename
            $audioFilename = pathinfo($filename, PATHINFO_FILENAME) . '.mp3';
            $audioPath = $audioDirectory . $audioFilename;
            
            // Execute the enscribe command
            $command = escapeshellcmd('enscribe ' . escapeshellarg($uploadPath) . ' ' . escapeshellarg($audioPath));
            $output = [];
            $returnValue = 0;
            
            exec($command, $output, $returnValue);
            
            // Check if command executed successfully
            if ($returnValue === 0 && file_exists($audioPath)) {
                $response['success'] = true;
                $response['message'] = 'File processed successfully.';
                $response['imagePath'] = $uploadPath;
                $response['audioUrl'] = $audioPath;
            } else {
                $response['message'] = 'Error processing the image: ' . implode("\n", $output);
            }
        } else {
            $response['message'] = 'Failed to move uploaded file.';
        }
    }
} else {
    $response['message'] = 'No file was uploaded or request method is invalid.';
}

// Add a timestamp to prevent browser caching
if ($response['success']) {
    $response['audioUrl'] = $response['audioUrl'] . '?t=' . time();
}

// Always return JSON response
header('Content-Type: application/json');
echo json_encode($response);
exit;
?>
