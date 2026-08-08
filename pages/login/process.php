<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';

$response = [
    'success' => false,
    'message' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if (empty($email) || empty($password)) {
        $response['message'] = 'Please enter both email and password.';
        echo json_encode($response);
        exit();
    }

    $email_escaped = mysqli_real_escape_string($conn, $email);
    
    $query  = "SELECT * FROM users WHERE email = '$email_escaped' LIMIT 1";
    $result = mysqli_query($conn, $query);

    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';

    if ($result && mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);

        if ($user['is_active'] == 0) {
            $response['message'] = 'Your account is deactivated. Please contact your administrator.';
            
            $user_id = $user['id'];
            $log_query = "INSERT INTO login (user_id, email, ip_address, status) 
                          VALUES ($user_id, '$email_escaped', '$ip_address', 'deactivated')";
            mysqli_query($conn, $log_query);
            
            echo json_encode($response);
            exit();
        }

        if (password_verify($password, $user['password_hash'])) {
            
            $user_id = $user['id'];
            $role_query = "SELECT r.name FROM roles r 
                           JOIN user_roles ur ON r.id = ur.role_id 
                           WHERE ur.user_id = $user_id";
            $role_result = mysqli_query($conn, $role_query);
            
            $roles = [];
            if ($role_result) {
                while ($role_row = mysqli_fetch_assoc($role_result)) {
                    $roles[] = $role_row['name'];
                }
            }

            session_regenerate_id(true);

            $_SESSION['user_id']    = $user['id'];
            $_SESSION['email']      = $user['email'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name']  = $user['last_name'];
            $_SESSION['roles']      = $roles;
            
            $log_query = "INSERT INTO login (user_id, email, ip_address, status) 
                          VALUES ($user_id, '$email_escaped', '$ip_address', 'success')";
            mysqli_query($conn, $log_query);

            $response['success'] = true;
        } else {
            $response['message'] = 'Invalid email or password.';
            
            $user_id = $user['id'];
            $log_query = "INSERT INTO login (user_id, email, ip_address, status) 
                          VALUES ($user_id, '$email_escaped', '$ip_address', 'invalid_password')";
            mysqli_query($conn, $log_query);
        }
    } else {
        $response['message'] = 'Invalid email or password.';
        
        $log_query = "INSERT INTO login (user_id, email, ip_address, status) 
                      VALUES (NULL, '$email_escaped', '$ip_address', 'invalid_email')";
        mysqli_query($conn, $log_query);
    }
} else {
    $response['message'] = 'Invalid request method.';
}

mysqli_close($conn);

echo json_encode($response);
exit();
?>
