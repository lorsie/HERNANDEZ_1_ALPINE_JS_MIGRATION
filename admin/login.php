<?php
session_start();
include '../dbConfig.php';

// Prevent double-login
if (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin') {
  header("Location: index.php");
  exit;
}

$alert = ''; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = trim($_POST['username']);
  $password = trim($_POST['password']);

  $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND role = 'admin'");
  $stmt->execute([$username]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($user) {
    if ($user['suspended']) {
      $alert = "
      <script>
      Swal.fire({
        icon: 'error',
        title: 'Account Suspended',
        text: 'This admin account has been suspended by the Superadmin.',
        confirmButtonText: 'OK'
      });
      </script>";
    } elseif (password_verify($password, $user['password'])) {
      $_SESSION['user'] = [
        'id' => $user['id'],
        'username' => $user['username'],
        'role' => $user['role']
      ];
      $alert = "
      <script>
      Swal.fire({
        icon: 'success',
        title: 'Welcome {$user['username']}!',
        text: 'Login successful.',
        showConfirmButton: false,
        timer: 1500
      }).then(() => {
        window.location.href = 'index.php';
      });
      </script>";
    } else {
      $alert = "
      <script>
      Swal.fire({
        icon: 'error',
        title: 'Invalid Credentials',
        text: 'Incorrect username or password.'
      });
      </script>";
    }
  } else {
    $alert = "
    <script>
    Swal.fire({
      icon: 'error',
      title: 'Invalid Credentials',
      text: 'Incorrect username or password.'
    });
    </script>";
  }
}
?>
<!DOCTYPE html>
<html lang='en'>
<head>
  <meta charset='UTF-8'>
  <title>Admin Login — Whisk & Brew</title>
  <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css' rel='stylesheet'/>
  <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
    <link rel="stylesheet" href="../css/admin_login.css">
</head>
<body class='bg-light d-flex align-items-center justify-content-center vh-100'>

  <div class='card shadow p-4' style='width: 400px;'>
    <h4 class='text-center mb-3'>Admin Login</h4>

    <form method='POST'>
      <div class='mb-3'>
        <label class='form-label'>Username</label>
        <input type='text' name='username' class='form-control' required>
      </div>
      <div class='mb-3'>
        <label class='form-label'>Password</label>
        <input type='password' name='password' class='form-control' required>
      </div>
      <button class='btn btn-primary w-100' type='submit'>Login</button>
      <div class='text-center mt-3'>
        <a href='../superadmin/login.php'>Login as Superadmin</a>
      </div>
    </form>
  </div>

  <?= $alert ?? '' ?> 

</body>
</html>