<?php
session_start();
include '../dbConfig.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
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

  $_SESSION['flash'] = ['title' => 'Success!', 'text' => 'Product added successfully.', 'icon' => 'success'];
  $stmt = $pdo->prepare("INSERT INTO products (name, category, price, image, added_by, date_added)
                         VALUES (?, ?, ?, ?, ?, NOW())");
  $stmt->execute([$name, $category, $price, $imagePath, $added_by]);
  header("Location: index.php");
  exit;
}

if (isset($_GET['delete'])) {
  $id = intval($_GET['delete']);
  $pdo->prepare("DELETE FROM products WHERE id=?")->execute([$id]);
  $_SESSION['flash'] = ['title' => 'Deleted!', 'text' => 'Product deleted successfully.', 'icon' => 'success'];
  header("Location: index.php");
  exit;
}

if (isset($_POST['checkout'])) {
  $items = $_POST['items'];
  $total = $_POST['total'];
  $order_type = $_POST['order_type'];
  $cashier = $_SESSION['user']['username'];

  $stmt = $pdo->prepare("INSERT INTO transactions (items, total, order_type, cashier, date_added)
                         VALUES (?, ?, ?, ?, NOW())");
  $stmt->execute([$items, $total, $order_type, $cashier]);

  echo json_encode(['status' => 'success']);
  exit;
}

$start = $_GET['start'] ?? '';
$end = $_GET['end'] ?? '';

$query = "SELECT * FROM transactions";
$params = [];

if (!empty($start) && !empty($end)) {
  $query .= " WHERE DATE(date_added) BETWEEN ? AND ?";
  $params = [$start, $end];
}

$query .= " ORDER BY date_added DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$products = $pdo->query("SELECT * FROM products ORDER BY date_added DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en" x-data="posApp()">
<head>
  <meta charset="UTF-8" />
  <title>Admin Dashboard - Haven & Crumb</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <link rel="stylesheet" href="../css/admin_index.css" />
</head>
<body>

<?php if (isset($_SESSION['flash'])): ?>
<script>
Swal.fire({
  title: "<?= $_SESSION['flash']['title'] ?>",
  text: "<?= $_SESSION['flash']['text'] ?>",
  icon: "<?= $_SESSION['flash']['icon'] ?>",
  timer: 1500,
  showConfirmButton: false
});
</script>
<?php unset($_SESSION['flash']); endif; ?>

<!-- WELCOME PAGE -->
<div id="welcomeScreen" class="screen" :class="{ 'active': currentScreen === 'welcome' }">
  <img src="../img/logo.png" style="width:300px;height:auto;margin:0 auto 20px;" />
  <h1>Welcome to Haven & Crumb</h1>
  <p style="max-width:600px;margin:0 auto;font-size:16px;font-style:italic;">
    At Haven & Crumb, we blend the warmth of artisan coffee with the aroma of freshly baked pastries. 
    Whether you’re starting your day or taking a cozy break, we invite you to relax, sip, and enjoy the comfort of our café atmosphere.
    <br /><br />Step inside and begin your Haven & Crumb experience.
  </p>
  <button id="startOrderBtn" style="margin-top:20px;" @click="goToOrderType()">Start Order</button>
</div>

<!-- ORDER TYPE PAGE -->
<div id="orderTypeScreen" class="screen" :class="{ 'active': currentScreen === 'orderType' }">
  <div>
    <h1>Choose Order Type</h1>
    <p>Select Order Type:</p>
    <div class="order-type-buttons">
      <button id="dineInBtn" @click="selectOrderType('Dine-In')">Dine-In</button>
      <button id="takeOutBtn" @click="selectOrderType('Take-Out')">Take-Out</button>
    </div>
  </div>
</div>

<!-- MAIN POS SYSTEM -->
<div id="mainPOS" class="screen container text-start" :class="{ 'active': currentScreen === 'mainPOS' }">
  <nav class="navbar navbar-light bg-white shadow-sm mb-3">
    <div class="container-fluid">
      <span class="navbar-brand">Haven & Crumb — Admin</span>
      <div class="d-flex align-items-center gap-3">
        <span class="text-muted"><?= htmlspecialchars($_SESSION['user']['username']) ?> (Admin)</span>
        <a href="logout.php" class="btn btn-outline-danger btn-sm">Logout</a>
      </div>
    </div>
  </nav>

  <div class="row">
    <!-- LEFT SIDE -->
    <div class="col-md-8">
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
                      <input type="number" class="form-control form-control-sm qty" min="1" value="1" x-ref="qty_<?= $p['id'] ?>" />
                      <button
                        class="btn btn-primary btn-sm addToCart"
                        @click="addToCart('<?= $p['id'] ?>', '<?= htmlspecialchars(addslashes($p['name'])) ?>', <?= $p['price'] ?>, $refs['qty_<?= $p['id'] ?>'].value)"
                      >Add to order</button>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- PRODUCT MANAGEMENT -->
      <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span>Products Management</span>
          <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">Add Product</button>
        </div>
        <div class="card-body">
          <table class="table table-sm align-middle">
            <thead>
              <tr><th>Img</th><th>Name</th><th>Category</th><th>Price</th><th>Added By</th><th>Date Added</th><th>Actions</th></tr>
            </thead>
            <tbody>
              <?php foreach ($products as $p): ?>
                <tr>
                  <td><img src="../<?= htmlspecialchars($p['image']) ?>" class="small-img" onerror="this.src='https://placehold.co/80x60?text=No+Img'"></td>
                  <td><?= htmlspecialchars($p['name']) ?></td>
                  <td><?= htmlspecialchars($p['category']) ?></td>
                  <td>₱<?= number_format($p['price'], 2) ?></td>
                  <td><?= htmlspecialchars($p['added_by']) ?></td>
                  <td><?= date('m/d/Y, g:i:s A', strtotime($p['date_added'])) ?></td>
                  <td>
                    <a href="?delete=<?= $p['id'] ?>" class="btn btn-danger btn-sm" @click.prevent="confirmDelete($event, '<?= $p['id'] ?>')">Delete</a>
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
      <!-- ORDER CART -->
      <div class="card mb-3">
        <div class="card-header"><strong>Ordered Items</strong></div>
        <div class="card-body">
          <div id="cartList" class="small text-muted" style="min-height:150px">
            <template x-if="cart.length === 0">
              <div class="small text-muted">Cart is empty</div>
            </template>
            <template x-for="(item, index) in cart" :key="item.id">
              <div class="d-flex justify-content-between mb-1">
                <div>
                  <strong x-text="item.name"></strong><br />
                  <small>₱<span x-text="item.price.toFixed(2)"></span> × <span x-text="item.qty"></span></small>
                </div>
                <div>
                  <button class="btn btn-sm btn-outline-secondary" @click="changeQty(index, -1)">-</button>
                  <button class="btn btn-sm btn-outline-secondary" @click="changeQty(index, 1)">+</button>
                  <button class="btn btn-sm btn-outline-danger" @click="removeItem(index)">x</button>
                </div>
              </div>
            </template>
          </div>
          <div class="d-flex justify-content-between mt-3">
            <strong>Total:</strong> <strong id="cartTotal">₱<span x-text="cartTotal.toFixed(2)"></span></strong>
          </div>
          <div class="mt-3">
            <input id="cashInput" class="form-control mb-2" placeholder="Enter amount here" type="number" min="0" step="0.01" x-model.number="cashInput" />
            <div class="d-grid gap-2">
              <button id="checkoutBtn" class="btn btn-success" @click="checkout()">Checkout</button>
              <button id="clearCartBtn" class="btn btn-outline-secondary" @click="clearCart()">Clear</button>
            </div>
          </div>
        </div>
      </div>

      <!-- REPORTS -->
      <div class="card">
        <div class="card-header"><strong>Reports</strong></div>
        <div class="card-body">
          <form class="mb-3 d-flex flex-column gap-2" method="GET">
            <input type="date" name="start" value="<?= $_GET['start'] ?? '' ?>" class="form-control form-control-sm" />
            <input type="date" name="end" value="<?= $_GET['end'] ?? '' ?>" class="form-control form-control-sm" />
            <div class="d-flex gap-2">
              <button class="btn btn-primary btn-sm">Filter</button>
              <button type="button" id="exportPDF" class="btn btn-outline-secondary btn-sm" @click.prevent="exportPDF()">Export PDF</button>
              <button type="button" id="viewReport" class="btn btn-outline-success btn-sm" @click.prevent="viewReport()">View</button>
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
<div class="modal fade" id="addProductModal" tabindex="-1">
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

<script>
function posApp() {
  return {
    currentScreen: 'welcome', 
    orderType: '',
    cart: [],
    cashInput: null,

    goToOrderType() {
      this.currentScreen = 'orderType';
    },
    selectOrderType(type) {
      this.orderType = type;
      this.currentScreen = 'mainPOS';
    },

    addToCart(id, name, price, qty) {
      qty = parseInt(qty);
      if (qty <= 0) return;
      let existing = this.cart.find(item => item.id === id);
      if (existing) {
        existing.qty += qty;
      } else {
        this.cart.push({ id, name, price, qty });
      }
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
      return this.cart.reduce((sum, item) => sum + item.price * item.qty, 0);
    },

    checkout() {
      if (!this.orderType) {
        Swal.fire('Please select order type first!', '', 'warning');
        return;
      }
      if (this.cart.length === 0) {
        Swal.fire('Empty', 'Add items first', 'warning');
        return;
      }
      const total = this.cartTotal;
      if (isNaN(this.cashInput) || this.cashInput < total) {
        Swal.fire('Insufficient', 'Not enough cash', 'error');
        return;
      }
      const change = (this.cashInput - total).toFixed(2);
      Swal.fire({
        title: 'Confirm Checkout?',
        text: `Order: ${this.orderType} | Total: ₱${total.toFixed(2)} | Change: ₱${change}`,
        icon: 'question',
        showCancelButton: true
      }).then(res => {
        if (res.isConfirmed) {
          const data = new FormData();
          data.append('checkout', '1');
          data.append('items', this.cart.map(i => `${i.name} (${i.qty})`).join(', '));
          data.append('total', total);
          data.append('order_type', this.orderType);
          fetch('index.php', { method: 'POST', body: data })
            .then(r => r.json())
            .then(res => {
              if (res.status === 'success') {
                Swal.fire('Success', `Change: ₱${change}`, 'success').then(() => location.reload());
              }
            });
        }
      });
    },

    confirmDelete(event, id) {
      event.preventDefault();
      Swal.fire({
        title: 'Delete this product?',
        text: 'This cannot be undone.',
        icon: 'warning',
        showCancelButton: true
      }).then(res => {
        if (res.isConfirmed) {
          window.location = `?delete=${id}`;
        }
      });
    },

    exportPDF() {
      const { jsPDF } = window.jspdf;
      const doc = new jsPDF();
      doc.text("Haven & Crumb - Admin Sales Report", 10, 10);
      let y = 20;
      document.querySelectorAll("#reportTable tbody tr").forEach(tr => {
        doc.text(tr.innerText, 10, y);
        y += 8;
        if (y > 280) {
          doc.addPage();
          y = 10;
        }
      });
      doc.save("Admin_Sales_Report.pdf");
    },

    viewReport() {
      const tableHTML = document.getElementById('reportTable').outerHTML;
      Swal.fire({
        title: 'Sales Report',
        html: `<div style='max-height:60vh;overflow:auto;'>${tableHTML}</div>`,
        width: '80%',
        showCloseButton: true
      });
    }
  };
}
</script>
</body>
</html>
