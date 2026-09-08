<?php
/**
 * Shared admin sidebar for the Festival CMS pages.
 * Mirrors the markup used by logo_master.php so the look stays identical.
 * $festivalNavActive (string) — current page basename for highlighting.
 */
$festivalNavActive = $festivalNavActive ?? basename($_SERVER['PHP_SELF']);
?>
<div class="fixed inset-y-0 left-0 w-64 bg-gradient-to-b from-gray-900 to-gray-800 text-white shadow-lg">
  <div class="p-6 border-b border-gray-700 flex items-center gap-3">
    <i class="fas fa-apple-alt text-3xl text-green-400"></i>
    <div>
      <h1 class="text-xl font-bold">Vasugi Fruits</h1>
      <p class="text-xs text-gray-400">Admin Panel</p>
    </div>
  </div>

  <nav class="p-4 space-y-2">
    <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-gray-700 transition">
      <i class="fas fa-home w-5"></i><span>Dashboard</span>
    </a>
    <a href="products.php" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-gray-700 transition">
      <i class="fas fa-box w-5"></i><span>Products</span>
    </a>
    <a href="orders.php" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-gray-700 transition">
      <i class="fas fa-shopping-cart w-5"></i><span>Orders</span>
    </a>
    <a href="festivals.php" class="<?= in_array($festivalNavActive, ['festivals.php','festival_add.php','festival_edit.php']) ? 'bg-green-600' : '' ?> flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-gray-700 transition">
      <i class="fas fa-gifts w-5"></i><span>Festivals</span>
    </a>
    <a href="logo_master.php" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-gray-700 transition">
      <i class="fas fa-image w-5"></i><span>Logo Master</span>
    </a>
  </nav>

  <div class="absolute bottom-0 left-0 right-0 p-4 border-t border-gray-700">
    <a href="logout.php" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-red-600 transition">
      <i class="fas fa-sign-out-alt w-5"></i><span>Logout</span>
    </a>
  </div>
</div>
