<?php
$page_title = "My Profile";
// Load the canonical application connection explicitly. The header uses
// require_once as well, so this remains safe and makes this page's dependency
// on the business_management database unambiguous.
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/header.php';
/** @var mysqli $conn */
$conn = getDatabaseConnection();
requireLogin();

$userId = $_SESSION['user_id'] ?? 0;
$message = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $firstName = mysqli_real_escape_string($conn, trim($_POST['first_name']));
        $lastName = mysqli_real_escape_string($conn, trim($_POST['last_name']));
        $email = mysqli_real_escape_string($conn, trim($_POST['email']));
        
        if (!empty($firstName) && !empty($lastName) && !empty($email)) {
            $query = "UPDATE users SET first_name = '$firstName', last_name = '$lastName', email = '$email' WHERE id = $userId";
            if (mysqli_query($conn, $query)) {
                $_SESSION['first_name'] = $firstName;
                $_SESSION['last_name'] = $lastName;
                $_SESSION['email'] = $email;
                $message = "Profile updated successfully.";
            } else {
                $error = "Error updating profile: " . mysqli_error($conn);
            }
        } else {
            $error = "All fields are required.";
        }
    }
    
    if (isset($_POST['change_password'])) {
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];
        
        if (!empty($currentPassword) && !empty($newPassword) && !empty($confirmPassword)) {
            if ($newPassword === $confirmPassword) {
                // Fetch hash from users table directly (which we added and synced!)
                $query = "SELECT password_hash FROM users WHERE id = $userId LIMIT 1";
                $result = mysqli_query($conn, $query);
                if ($result && mysqli_num_rows($result) > 0) {
                    $user = mysqli_fetch_assoc($result);
                    if (password_verify($currentPassword, $user['password_hash'])) {
                        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                        
                        // Update in both users and auth_credentials for maximum robustness
                        $updateQuery1 = "UPDATE users SET password_hash = '$newHash' WHERE id = $userId";
                        $updateQuery2 = "UPDATE auth_credentials SET password_hash = '$newHash', password_changed_at = NOW(6) WHERE user_id = $userId";
                        
                        if (mysqli_query($conn, $updateQuery1) && mysqli_query($conn, $updateQuery2)) {
                            $message = "Password changed successfully.";
                        } else {
                            $error = "Error updating password.";
                        }
                    } else {
                        $error = "Incorrect current password.";
                    }
                } else {
                    $error = "User credentials not found.";
                }
            } else {
                $error = "New passwords do not match.";
            }
        } else {
            $error = "All password fields are required.";
        }
    }

    if (isset($_POST['upload_avatar'])) {
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['avatar']['tmp_name'];
            $fileName = $_FILES['avatar']['name'];
            $fileSize = $_FILES['avatar']['size'];
            $fileNameCmps = explode(".", $fileName);
            $fileExtension = strtolower(end($fileNameCmps));
            
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
            if (in_array($fileExtension, $allowedExtensions)) {
                if ($fileSize < 5000000) {
                    $existingFiles = glob(__DIR__ . "/../../src/images/profile/user_" . $userId . ".*");
                    foreach ($existingFiles as $file) {
                        if (is_file($file)) {
                            unlink($file);
                        }
                    }
                    // Ensure destination directory exists
                    $avatarDir = __DIR__ . "/../../src/images/profile/";
                    if (!is_dir($avatarDir)) {
                        mkdir($avatarDir, 0777, true);
                    }
                    $newFileName = "user_" . $userId . "." . $fileExtension;
                    $dest_path = $avatarDir . $newFileName;
                    
                    if (move_uploaded_file($fileTmpPath, $dest_path)) {
                        $message = "Profile picture uploaded successfully.";
                    } else {
                        $error = "Error moving the uploaded file.";
                    }
                } else {
                    $error = "File is too large. Max size is 5MB.";
                }
            } else {
                $error = "Invalid file type. Allowed: JPG, PNG, GIF.";
            }
        } else {
            $error = "No file selected or upload error occurred.";
        }
    }
}

// Query user details with roles
$query = "SELECT u.first_name, u.last_name, u.email, u.created_at, r.name as role_name FROM users u 
          LEFT JOIN user_roles ur ON u.id = ur.user_id 
          LEFT JOIN roles r ON ur.role_id = r.id 
          WHERE u.id = $userId LIMIT 1";
$result = mysqli_query($conn, $query);
$userData = mysqli_fetch_assoc($result);

$avatarPath = "";
$avatarRelative = "";
$existingFiles = glob(__DIR__ . "/../../src/images/profile/user_" . $userId . ".*");
if (!empty($existingFiles)) {
    $avatarPath = $existingFiles[0];
    $avatarRelative = "../../src/images/profile/" . basename($existingFiles[0]);
}

$role_query = isset($_GET['role']) ? '?role=' . e($_GET['role']) : '';
?>

<style>
  .profile-grid {
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: 24px;
    margin-top: 24px;
    align-items: start;
  }
  @media (max-width: 992px) {
    .profile-grid {
      grid-template-columns: 1fr;
    }
  }
  .profile-card-left {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 32px 24px;
    text-align: center;
  }
  .profile-avatar-wrap {
    width: 120px;
    height: 120px;
    margin: 0 auto 20px;
    position: relative;
  }
  .profile-avatar-img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid var(--blue);
  }
  .profile-avatar-placeholder {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    background: var(--bg);
    color: var(--blue);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 44px;
    font-weight: 600;
    border: 3px solid var(--blue);
  }
  .profile-name-title {
    font-size: 18px;
    font-weight: 600;
    color: var(--text);
    margin-bottom: 6px;
  }
  .profile-role-badge {
    display: inline-block;
    padding: 3px 10px;
    background: var(--bg);
    color: var(--blue);
    border: 1px solid var(--border);
    border-radius: 99px;
    font-size: 11px;
    font-weight: 500;
    margin-bottom: 16px;
  }
  .profile-joined {
    font-size: 12px;
    color: var(--text3);
  }
  .profile-forms-right {
    display: flex;
    flex-direction: column;
    gap: 24px;
  }
  .form-section {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 24px;
  }
  .form-section-title {
    font-size: 15px;
    font-weight: 600;
    color: var(--text);
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border);
  }
  .form-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 16px;
  }
  @media (max-width: 768px) {
    .form-grid-2 {
      grid-template-columns: 1fr;
    }
  }
  .form-field {
    margin-bottom: 16px;
  }
  .form-field label {
    display: block;
    font-size: 12px;
    font-weight: 500;
    color: var(--text3);
    margin-bottom: 6px;
  }
  .form-field input[type="text"],
  .form-field input[type="email"],
  .form-field input[type="password"] {
    width: 100%;
    padding: 8px 12px;
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    color: var(--text);
    font-size: 13px;
    outline: none;
    transition: border-color 0.2s;
  }
  .form-field input[type="text"]:focus,
  .form-field input[type="email"]:focus,
  .form-field input[type="password"]:focus {
    border-color: var(--blue);
  }
  .profile-btn-primary {
    background: var(--blue);
    color: #fff;
    border: none;
    padding: 10px 20px;
    border-radius: var(--radius);
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: opacity 0.2s;
  }
  .profile-btn-primary:hover {
    opacity: 0.9;
  }
  .alert-box {
    padding: 12px 16px;
    border-radius: var(--radius);
    font-size: 13px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
  }
  .alert-box.success {
    background: rgba(16, 185, 129, 0.1);
    color: rgb(16, 185, 129);
    border: 1px solid rgba(16, 185, 129, 0.2);
  }
  .alert-box.error {
    background: rgba(239, 68, 68, 0.1);
    color: rgb(239, 68, 68);
    border: 1px solid rgba(239, 68, 68, 0.2);
  }
  .file-upload-input {
    display: none;
  }
  .file-upload-label {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 12px;
    border: 1px dashed var(--border);
    border-radius: var(--radius);
    font-size: 12px;
    cursor: pointer;
    color: var(--text);
    background: var(--bg);
    transition: background 0.2s;
    margin-top: 10px;
  }
  .file-upload-label:hover {
    background: var(--border);
  }
</style>

<div class="page-header">
  <div>
    <h1 class="page-title">
      <svg viewBox="0 0 24 24" style="width:20px; height:20px; fill:none; stroke:currentColor; stroke-width:2; margin-right:8px; vertical-align:middle;"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      My Profile
    </h1>
    <div class="page-sub">Manage your account details, password and profile picture</div>
  </div>
</div>

<?php if (!empty($message)): ?>
  <div class="alert-box success">
    <svg viewBox="0 0 24 24" style="width:18px; height:18px; fill:none; stroke:currentColor; stroke-width:2;"><polyline points="20 6 9 17 4 12"/></svg>
    <span><?php echo htmlspecialchars($message); ?></span>
  </div>
<?php endif; ?>

<?php if (!empty($error)): ?>
  <div class="alert-box error">
    <svg viewBox="0 0 24 24" style="width:18px; height:18px; fill:none; stroke:currentColor; stroke-width:2;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <span><?php echo htmlspecialchars($error); ?></span>
  </div>
<?php endif; ?>

<div class="profile-grid">
  
  <div class="profile-card-left">
    <div class="profile-avatar-wrap">
      <?php if (!empty($avatarRelative)): ?>
        <img src="<?php echo htmlspecialchars($avatarRelative); ?>" class="profile-avatar-img">
      <?php else: ?>
        <div class="profile-avatar-placeholder">
          <?php
          $initials = '';
          if (isset($userData['first_name'])) {
              $initials .= strtoupper(substr($userData['first_name'], 0, 1));
          }
          if (isset($userData['last_name'])) {
              $initials .= strtoupper(substr($userData['last_name'], 0, 1));
          }
          echo !empty($initials) ? $initials : 'U';
          ?>
        </div>
      <?php endif; ?>
    </div>
    
    <div class="profile-name-title">
      <?php echo htmlspecialchars(($userData['first_name'] ?? '') . ' ' . ($userData['last_name'] ?? '')); ?>
    </div>
    
    <div class="profile-role-badge">
      <?php echo htmlspecialchars($userData['role_name'] ?? 'User'); ?>
    </div>
    
    <div class="profile-joined">
      Member since: <?php echo htmlspecialchars(date('M d, Y', strtotime($userData['created_at']))); ?>
    </div>

    <form action="my_profile.php<?php echo $role_query; ?>" method="POST" enctype="multipart/form-data" id="avatarForm">
      <label for="avatar" class="file-upload-label">
        <svg viewBox="0 0 24 24" style="width:14px; height:14px; fill:none; stroke:currentColor; stroke-width:2;"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12"/></svg>
        Choose Photo
      </label>
      <input type="file" name="avatar" id="avatar" class="file-upload-input" onchange="document.getElementById('avatarForm').submit();">
      <input type="hidden" name="upload_avatar" value="1">
    </form>
  </div>

  <div class="profile-forms-right">
    
    <div class="form-section">
      <div class="form-section-title">Personal Details</div>
      <form action="my_profile.php<?php echo $role_query; ?>" method="POST">
        <div class="form-grid-2">
          <div class="form-field">
            <label for="first_name">First Name</label>
            <input type="text" name="first_name" id="first_name" value="<?php echo htmlspecialchars($userData['first_name'] ?? ''); ?>" required>
          </div>
          <div class="form-field">
            <label for="last_name">Last Name</label>
            <input type="text" name="last_name" id="last_name" value="<?php echo htmlspecialchars($userData['last_name'] ?? ''); ?>" required>
          </div>
        </div>
        <div class="form-field">
          <label for="email">Email Address</label>
          <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($userData['email'] ?? ''); ?>" required>
        </div>
        <button type="submit" name="update_profile" class="profile-btn-primary">Save Changes</button>
      </form>
    </div>

    <div class="form-section">
      <div class="form-section-title">Change Password</div>
      <form action="my_profile.php<?php echo $role_query; ?>" method="POST">
        <div class="form-field">
          <label for="current_password">Current Password</label>
          <input type="password" name="current_password" id="current_password" required>
        </div>
        <div class="form-grid-2">
          <div class="form-field">
            <label for="new_password">New Password</label>
            <input type="password" name="new_password" id="new_password" required>
          </div>
          <div class="form-field">
            <label for="confirm_password">Confirm New Password</label>
            <input type="password" name="confirm_password" id="confirm_password" required>
          </div>
        </div>
        <button type="submit" name="change_password" class="profile-btn-primary">Update Password</button>
      </form>
    </div>

  </div>

</div>

<div style="height: 50px;"></div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
