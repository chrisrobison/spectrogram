<?php
// Configuration
$uploadDirectory = 'uploads/'; // Directory to store uploads
$audioDirectory  = 'audio/';   // Directory to store audio output
$allowedTypes    = ['image/jpeg', 'image/png', 'image/gif']; // Allowed file types
$maxFileSize     = 10 * 1024 * 1024; // 10MB max file size

// Create directories if they don't exist
if (!file_exists($uploadDirectory)) { mkdir($uploadDirectory, 0755, true); }
if (!file_exists($audioDirectory))  { mkdir($audioDirectory, 0755, true); }

// Initialize response
$response = [
    'success'   => false,
    'message'   => '',
    'imagePath' => '',
    'audioUrl'  => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
    $file = $_FILES['image'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $response['message'] = 'Upload failed with error code: ' . $file['error'];
    } elseif ($file['size'] > $maxFileSize) {
        $response['message'] = 'File is too large. Maximum size is ' . ($maxFileSize / 1024 / 1024) . 'MB.';
    } elseif (!in_array($file['type'], $allowedTypes)) {
        $response['message'] = 'Invalid file type. Allowed types: JPG, PNG, GIF.';
    } else {
        // Unique filename
        $filename   = uniqid() . '_' . basename($file['name']);
        $uploadPath = $uploadDirectory . $filename;

        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
            // Paths
            $baseName    = pathinfo($filename, PATHINFO_FILENAME);
            $ext         = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $rotatedPath = $uploadDirectory . $baseName . '_rot.' . $ext;
            $audioPath   = $audioDirectory . $baseName . '.wav';

            // Rotate image 90° clockwise (i.e., -90 degrees mathematically)
            $rotatedOk = false;

            if (extension_loaded('imagick')) {
                try {
                    $img = new Imagick($uploadPath);
                    // ensure alpha is preserved for PNG/GIF
                    if (in_array($ext, ['png', 'gif'])) {
                        $img->setImageBackgroundColor(new ImagickPixel('transparent'));
                        $img = $img->coalesceImages();
                        $frames = new Imagick();
                        foreach ($img as $frame) {
                            $frame->setImageBackgroundColor(new ImagickPixel('transparent'));
                            $frame->rotateImage(new ImagickPixel('transparent'), -90); // clockwise
                            $frames->addImage($frame);
                            $frames->setImageFormat($img->getImageFormat());
                        }
                        $rotatedOk = $frames->writeImages($rotatedPath, true);
                        $frames->clear(); $frames->destroy();
                    } else {
                        $img->setImageBackgroundColor(new ImagickPixel('black'));
                        $img->rotateImage(new ImagickPixel('black'), -90); // clockwise
                        $rotatedOk = $img->writeImage($rotatedPath);
                        $img->clear(); $img->destroy();
                    }
                } catch (Throwable $e) {
                    $rotatedOk = false;
                }
            }

            if (!$rotatedOk) {
                // Fallback to GD
                try {
                    switch ($ext) {
                        case 'jpeg':
                        case 'jpg':
                            $src = imagecreatefromjpeg($uploadPath);
                            $bg  = 0; // black
                            $rot = imagerotate($src, -90, $bg); // clockwise
                            imagejpeg($rot, $rotatedPath, 95);
                            imagedestroy($src); imagedestroy($rot);
                            $rotatedOk = file_exists($rotatedPath);
                            break;
                        case 'png':
                            $src = imagecreatefrompng($uploadPath);
                            imagesavealpha($src, true);
                            $bg  = imagecolorallocatealpha($src, 0, 0, 0, 127);
                            $rot = imagerotate($src, -90, $bg); // clockwise
                            imagesavealpha($rot, true);
                            imagepng($rot, $rotatedPath);
                            imagedestroy($src); imagedestroy($rot);
                            $rotatedOk = file_exists($rotatedPath);
                            break;
                        case 'gif':
                            // Simple GD rotate (non-animated)
                            $src = imagecreatefromgif($uploadPath);
                            $bg  = imagecolorallocate($src, 0, 0, 0);
                            $rot = imagerotate($src, -90, $bg); // clockwise
                            imagegif($rot, $rotatedPath);
                            imagedestroy($src); imagedestroy($rot);
                            $rotatedOk = file_exists($rotatedPath);
                            break;
                        default:
                            $rotatedOk = false;
                    }
                } catch (Throwable $e) {
                    $rotatedOk = false;
                }
            }

            // Use rotated image if available
            $inputForEnscribe = $rotatedOk ? $rotatedPath : $uploadPath;

            // enscribe
            $cmd = 'enscribe ' . escapeshellarg($inputForEnscribe) . ' ' . escapeshellarg($audioPath);
            $output = [];
            $returnValue = 0;
            exec($cmd, $output, $returnValue);

            if ($returnValue === 0 && file_exists($audioPath)) {
                $response['success']   = true;
                $response['message']   = 'File processed successfully.';
                $response['imagePath'] = $inputForEnscribe;
                $response['audioUrl']  = $audioPath . '?t=' . time();
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

header('Content-Type: application/json');
echo json_encode($response);
exit;
