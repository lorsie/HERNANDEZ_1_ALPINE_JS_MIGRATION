<?php
session_start();
include '../dbConfig.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'superadmin') {
  header("Location: login.php");
  exit;
}

if (isset($_POST['add_product'])) {
  $name = trim($_POST['name']);
  $category = trim($_POST['category']);
  $price = floatval($_POST['price']);
  $added_by = $_SESSION['user']['username'];
  $imagePath = '';

  if (!empty($_FILES['image']['name'])) {
    $uploadDir = '../uploads/';
    if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);
    $fileName = time() . '_' . basename($_FILES['image']['name']);
    $target = $uploadDir . $fileName;
    if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
      $imagePath = 'uploads/' . $fileName;
    }
  }

  $stmt = $pdo->prepare("INSERT INTO products (name, category, price, image, added_by, date_added)
                         VALUES (?, ?, ?, ?, ?, NOW())");
  $stmt->execute([$name, $category, $price, $imagePath, $added_by]);
  $_SESSION['swal'] = ['Product Added', 'New product successfully added!', 'success'];
  header("Location: index.php");
  exit;
}

if (isset($_GET['delete'])) {
  $id = intval($_GET['delete']);
  $pdo->prepare("DELETE FROM products WHERE id=?")->execute([$id]);
  $_SESSION['swal'] = ['Deleted', 'Product deleted successfully!', 'success'];
  header("Location: index.php");
  exit;
}

if (isset($_POST['create_admin'])) {
  $username = trim($_POST['username']);
  $password = trim($_POST['password']);
  if ($username && $password) {
    $check = $pdo->prepare("SELECT * FROM users WHERE username=?");
    $check->execute([$username]);
    if ($check->fetch()) {
      $_SESSION['swal'] = ['Error', 'Username already exists!', 'error'];
    } else {
      $hashed = password_hash($password, PASSWORD_DEFAULT);
      $pdo->prepare("INSERT INTO users (username, password, role, suspended, date_added)
                     VALUES (?, ?, 'admin', 0, NOW())")->execute([$username, $hashed]);
      $_SESSION['swal'] = ['Admin Created', 'New admin account created!', 'success'];
    }
  }
  header("Location: index.php");
  exit;
}

if (isset($_GET['toggle'])) {
  $id = intval($_GET['toggle']);
  $user = $pdo->query("SELECT suspended FROM users WHERE id=$id")->fetch();
  if ($user) {
    $new = $user['suspended'] ? 0 : 1;
    $pdo->prepare("UPDATE users SET suspended=? WHERE id=?")->execute([$new, $id]);
    $_SESSION['swal'] = ['Updated', 'User suspension status changed.', 'success'];
  }
  header("Location: index.php");
  exit;
}

if (isset($_POST['checkout'])) {
  $items = $_POST['items'];
  $total = $_POST['total'];
  $order_type = $_POST['order_type'] ?? 'Dine-In';
  $cashier = $_SESSION['user']['username'];

  $stmt = $pdo->prepare("INSERT INTO transactions (items, total, order_type, cashier, date_added)
                         VALUES (?, ?, ?, ?, NOW())");
  $stmt->execute([$items, $total, $order_type, $cashier]);

  echo json_encode(['status' => 'success']);
  exit;
}

$where = "";
if (!empty($_GET['start']) && !empty($_GET['end'])) {
  $start = $_GET['start'] . " 00:00:00";
  $end = $_GET['end'] . " 23:59:59";
  $where = "WHERE date_added BETWEEN '$start' AND '$end'";
}

$products = $pdo->query("SELECT * FROM products ORDER BY date_added DESC")->fetchAll(PDO::FETCH_ASSOC);
$users = $pdo->query("SELECT * FROM users ORDER BY date_added DESC")->fetchAll(PDO::FETCH_ASSOC);
$transactions = $pdo->query("SELECT * FROM transactions $where ORDER BY date_added DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en" x-data="superadminDashboard()" x-init="init()">
<head>
  <meta charset="UTF-8" />
  <title>Superadmin Dashboard - Haven & Crumb</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <link rel="stylesheet" href="../css/superadmin_index.css" />
  <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body>

<nav class="navbar navbar-light bg-white shadow-sm mb-3">
  <div class="container-fluid">
    <span class="navbar-brand">Haven and Crumb — Superadmin</span>
    <div class="d-flex align-items-center gap-3">
      <span class="text-muted"><?= htmlspecialchars($_SESSION['user']['username']) ?> (Superadmin)</span>
      <a href="logout.php" class="btn btn-outline-danger btn-sm">Logout</a>
    </div>
  </div>
</nav>

<div class="container" >

  <div class="row">

    <div class="col-md-8">

      <div class="card mb-3">
        <div class="card-header"><strong>Order Type</strong></div>
        <div class="card-body text-center">
          <div class="btn-group" role="group">
            <button 
              id="dineInBtn" 
              class="btn" 
              :class="orderType === 'Dine-In' ? 'btn-primary' : 'btn-outline-primary'" 
              @click="setOrderType('Dine-In')"
              type="button"
            >Dine-In</button>
            <button 
              id="takeOutBtn" 
              class="btn" 
              :class="orderType === 'Take-Out' ? 'btn-primary' : 'btn-outline-primary'" 
              @click="setOrderType('Take-Out')"
              type="button"
            >Take-Out</button>
          </div>
        </div>
      </div>

      <!-- MENU -->
      <div class="card mb-3">
        <div class="card-header"><strong>Menu</strong></div>
        <div class="card-body">
          <div class="row g-3">
            <?php foreach ($products as $p): ?>
              <div class="col-md-6 col-lg-4">
                <div class="card">
                  <img src="../<?= htmlspecialchars($p['image']) ?>" class="product-img" onerror="this.src='https://placehold.co/300x200?text=No+Image'">
                  <div class="card-body">
                    <h5><?= htmlspecialchars($p['name']) ?></h5>
                    <p class="small text-muted"><?= htmlspecialchars($p['category']) ?> · Added by: <?= htmlspecialchars($p['added_by']) ?></p>
                    <p><strong>₱<?= number_format($p['price'], 2) ?></strong></p>
                    <div class="d-flex gap-2">
                      <input 
                        type="number" 
                        class="form-control form-control-sm qty" 
                        min="1" 
                        value="1" 
                        x-ref="qtyInput<?= $p['id'] ?>"
                      >
                      <button 
                        class="btn btn-primary btn-sm" 
                        type="button"
                        @click="addToCart('<?= htmlspecialchars($p['name']) ?>', <?= $p['price'] ?>, $refs['qtyInput<?= $p['id'] ?>'].value)"
                      >Add to order</button>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- PRODUCTS MANAGEMENT -->
      <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span>Products Management</span>
          <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">Add Product</button>
        </div>
        <div class="card-body">
          <table class="table table-sm align-middle">
            <thead><tr><th>Img</th><th>Name</th><th>Category</th><th>Price</th><th>Added By</th><th>Date Added</th><th>Actions</th></tr></thead>
            <tbody>
              <?php foreach ($products as $p): ?>
                <tr>
                  <td><img src="../<?= htmlspecialchars($p['image']) ?>" class="small-img" onerror="this.src='https://placehold.co/80x60?text=No+Img'"></td>
                  <td><?= htmlspecialchars($p['name']) ?></td>
                  <td><?= htmlspecialchars($p['category']) ?></td>
                  <td>₱<?= number_format($p['price'],2) ?></td>
                  <td><?= htmlspecialchars($p['added_by']) ?></td>
                  <td><?= date('m/d/Y, g:i:s A', strtotime($p['date_added'])) ?></td>
                  <td><a href="?delete=<?= $p['id'] ?>" class="btn btn-danger btn-sm delBtn" @click.prevent="confirmDeleteProduct('<?= htmlspecialchars($p['name']) ?>', <?= $p['id'] ?>)">Delete</a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- USER MANAGEMENT -->
      <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span>User Management (Superadmin)</span>
          <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addAdminModal">Create Admin</button>
        </div>
        <div class="card-body">
          <table class="table table-sm align-middle">
            <thead><tr><th>Username</th><th>Role</th><th>Suspended</th><th>Date Added</th><th>Actions</th></tr></thead>
            <tbody>
              <?php foreach ($users as $u): ?>
                <tr>
                  <td><?= htmlspecialchars($u['username']) ?></td>
                  <td><?= htmlspecialchars($u['role']) ?></td>
                  <td><?= $u['suspended'] ? 'Yes' : 'No' ?></td>
                  <td><?= date('m/d/Y, g:i:s A', strtotime($u['date_added'])) ?></td>
                  <td>
                    <?php if ($u['role'] == 'admin'): ?>
                      <a 
                        href="?toggle=<?= $u['id'] ?>" 
                        class="btn btn-sm" 
                        :class="{'btn-success': <?= $u['suspended'] ? 'true' : 'false' ?>, 'btn-danger': <?= $u['suspended'] ? 'false' : 'true' ?>}"
                        @click.prevent="toggleSuspend('<?= htmlspecialchars($u['username']) ?>', <?= $u['id'] ?>, <?= $u['suspended'] ? 'true' : 'false' ?>)"
                      >
                        <?= $u['suspended'] ? 'Unsuspend' : 'Suspend' ?>
                      </a>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>

    <!-- RIGHT SIDE -->
    <div class="col-md-4 order-panel">

      <div class="card mb-3">
        <div class="card-header"><strong>Ordered Items</strong></div>
        <div class="card-body">
          <template x-if="cart.length === 0">
            <div class="small text-muted" style="min-height:150px;">Cart is empty</div>
          </template>
          <template x-if="cart.length > 0">
            <div style="min-height:150px;">
              <template x-for="(item, index) in cart" :key="index">
                <div class="d-flex justify-content-between mb-1">
                  <div>
                    <strong x-text="item.name"></strong><br>
                    <small>₱<span x-text="item.price.toFixed(2)"></span> × <span x-text="item.qty"></span></small>
                  </div>
                  <div>
                    <button class="btn btn-sm btn-outline-secondary" type="button" @click="changeQty(index, -1)">-</button>
                    <button class="btn btn-sm btn-outline-secondary" type="button" @click="changeQty(index, 1)">+</button>
                    <button class="btn btn-sm btn-outline-danger" type="button" @click="removeItem(index)">x</button>
                  </div>
                </div>
              </template>
            </div>
          </template>
          <div class="d-flex justify-content-between mt-3">
            <strong>Total:</strong> <strong>₱<span x-text="cartTotal.toFixed(2)"></span></strong>
          </div>
          <div class="mt-3">
            <input 
              id="cashInput" 
              class="form-control mb-2" 
              placeholder="Enter amount here" 
              type="number" 
              min="0" 
              step="0.01" 
              x-model.number="cashInput"
            >
            <div class="d-grid gap-2">
              <button id="checkoutBtn" class="btn btn-success" type="button" @click="checkout()">Checkout</button>
              <button id="clearCartBtn" class="btn btn-outline-secondary" type="button" @click="clearCart()">Clear</button>
            </div>
          </div>
        </div>
      </div>

      <!-- REPORTS -->
      <div class="card">
        <div class="card-header"><strong>Reports</strong></div>
        <div class="card-body">
          <form class="mb-3 d-flex flex-column gap-2" method="GET" @submit.prevent="filterReport()">
            <input type="date" name="start" x-model="filters.start" class="form-control form-control-sm" />
            <input type="date" name="end" x-model="filters.end" class="form-control form-control-sm" />
            <div class="d-flex gap-2">
              <button class="btn btn-primary btn-sm" type="submit">Filter</button>
              <button type="button" id="exportPDF" class="btn btn-outline-secondary btn-sm" @click="exportPDF()">Export PDF</button>
              <button type="button" id="viewReport" class="btn btn-outline-success btn-sm" @click="viewReport()">View</button>
            </div>
          </form>
          <div style="max-height:200px; overflow:auto;">
            <table class="table table-sm" id="reportTable">
              <thead><tr><th>Date</th><th>Order Type</th><th>Items</th><th>Amount</th><th>Cashier</th></tr></thead>
              <tbody>
                <?php $total=0; foreach ($transactions as $t): $total += $t['total']; ?>
                  <tr>
                    <td><?= date('m/d/Y, g:i:s A', strtotime($t['date_added'])) ?></td>
                    <td><?= htmlspecialchars($t['order_type']) ?></td>
                    <td><?= htmlspecialchars($t['items']) ?></td>
                    <td>₱<?= number_format($t['total'],2) ?></td>
                    <td><?= htmlspecialchars($t['cashier']) ?></td>
                  </tr>
                <?php endforeach; ?>
                <tr class="table-info"><td colspan="3"><strong>TOTAL</strong></td><td colspan="2"><strong>₱<?= number_format($total,2) ?></strong></td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- ADD PRODUCT MODAL -->
<div class="modal fade" id="addProductModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" enctype="multipart/form-data" class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Add Product</h5></div>
      <div class="modal-body">
        <input name="name" class="form-control mb-2" placeholder="Name" required />
        <input name="category" class="form-control mb-2" placeholder="Category" required />
        <input name="price" type="number" class="form-control mb-2" placeholder="Price" required />
        <input name="image" type="file" class="form-control mb-2" accept="image/*" required />
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" name="add_product">Add</button>
      </div>
    </form>
  </div>
</div>

<!-- ADD ADMIN MODAL -->
<div class="modal fade" id="addAdminModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Create Admin</h5></div>
      <div class="modal-body">
        <input name="username" class="form-control mb-2" placeholder="Username" required />
        <input name="password" type="password" class="form-control mb-2" placeholder="Password" required />
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" name="create_admin">Create</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
  function superadminDashboard() {
    return {
      cart: [],
      orderType: 'Dine-In',
      cashInput: null,
      filters: {
        start: '<?= $_GET['start'] ?? '' ?>',
        end: '<?= $_GET['end'] ?? '' ?>',
      },

      init() {
        // Show SweetAlert on page load if set by PHP
        <?php if (!empty($_SESSION['swal'])): ?>
          Swal.fire('<?= $_SESSION['swal'][0] ?>','<?= $_SESSION['swal'][1] ?>','<?= $_SESSION['swal'][2] ?>');
          <?php unset($_SESSION['swal']); ?>
        <?php endif; ?>
      },

      setOrderType(type) {
        this.orderType = type;
      },

      addToCart(name, price, qty) {
        qty = parseInt(qty);
        if (isNaN(qty) || qty < 1) qty = 1;
        let existing = this.cart.find(i => i.name === name);
        if (existing) {
          existing.qty += qty;
        } else {
          this.cart.push({ name, price, qty });
        }
      },

      renderCart() {
        // Not needed in Alpine, cart is reactive and rendered automatically
      },

      changeQty(index, delta) {
        this.cart[index].qty += delta;
        if (this.cart[index].qty <= 0) {
          this.cart.splice(index, 1);
        }
      },

      removeItem(index) {
        this.cart.splice(index, 1);
      },

      clearCart() {
        this.cart = [];
        this.cashInput = null;
      },

      get cartTotal() {
        return this.cart.reduce((acc, cur) => acc + cur.price * cur.qty, 0);
      },

      checkout() {
        if (this.cart.length === 0) {
          Swal.fire('Empty', 'Add items first', 'warning');
          return;
        }
        if (this.cashInput === null || isNaN(this.cashInput) || this.cashInput < this.cartTotal) {
          Swal.fire('Insufficient', 'Not enough cash', 'error');
          return;
        }

        const change = (this.cashInput - this.cartTotal).toFixed(2);
        const itemsText = this.cart.map(i => `${i.name} (${i.qty})`).join(', ');
        const fd = new FormData();
        fd.append('checkout', 1);
        fd.append('items', itemsText);
        fd.append('total', this.cartTotal);
        fd.append('order_type', this.orderType);

        fetch('', { method: 'POST', body: fd })
          .then(res => res.json())
          .then(res => {
            if (res.status === 'success') {
              Swal.fire('Paid', `Change: ₱${change}<br>Order Type: <strong>${this.orderType}</strong>`, 'success');
              this.clearCart();
            }
          });
      },

      exportPDF() {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        doc.setFontSize(14);
        doc.text("Haven & Crumb - Sales Report", 10, 10);
        let y = 20;
        document.querySelectorAll("#reportTable tbody tr").forEach(tr => {
          doc.setFontSize(10);
          const rowText = Array.from(tr.children).map(td => td.innerText).join(" | ");
          doc.text(rowText, 10, y);
          y += 8;
          if (y > 280) {
            doc.addPage();
            y = 10;
          }
        });
        doc.save("Superadmin_Sales_Report.pdf");
      },

      viewReport() {
        const tableHTML = document.getElementById('reportTable').outerHTML;
        Swal.fire({
          title: 'Full Sales Report',
          html: `<div style="max-height:60vh; overflow:auto;">${tableHTML}</div>`,
          width: '80%',
          showCloseButton: true,
          confirmButtonText: 'Close',
          customClass: { popup: 'p-0' }
        });
      },

      filterReport() {
        // since this is GET form, just submit normally
        // but Alpine intercepts submit, so reload page with params:
        const params = new URLSearchParams();
        if(this.filters.start) params.append('start', this.filters.start);
        if(this.filters.end) params.append('end', this.filters.end);
        window.location.search = params.toString();
      },

      confirmDeleteProduct(name, id) {
        Swal.fire({
          title: `Delete "${name}"?`,
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Delete',
          cancelButtonText: 'Cancel'
        }).then(result => {
          if (result.isConfirmed) {
            // Redirect to delete URL
            window.location.href = `?delete=${id}`;
          }
        });
      },

      toggleSuspend(username, id, suspended) {
        const action = suspended ? 'Unsuspend' : 'Suspend';
        Swal.fire({
          title: `${action} ${username}?`,
          icon: 'question',
          showCancelButton: true,
          confirmButtonText: action,
          cancelButtonText: 'Cancel'
        }).then(result => {
          if (result.isConfirmed) {
            window.location.href = `?toggle=${id}`;
          }
        });
      }
    }
  }
</script>

</body>
</html>
