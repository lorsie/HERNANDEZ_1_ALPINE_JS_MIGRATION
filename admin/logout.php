
<?php
session_start();
session_unset();
session_destroy();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Logging Out</title>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<script>
Swal.fire({
  title: 'Logged Out',
  text: 'You have been successfully logged out.',
  icon: 'success',
  showConfirmButton: false,
  timer: 1500
}).then(() => {
  window.location.href = 'login.php'; 
});
</script>
</body>
</html>
