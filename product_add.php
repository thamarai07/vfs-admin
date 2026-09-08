<?php
session_start();
require_once "config/db.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// CSV TEMPLATE DOWNLOAD  (must run before any HTML output)
// ─────────────────────────────────────────────────────────────────────────────
if (isset($_GET['template'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="rooto_products_template.csv"');
    $out = fopen('php://output', 'w');
    // Header row — these are the columns the importer understands
    fputcsv($out, ['name', 'category', 'price_per_kg', 'stock', 'description', 'image']);
    // Example rows (delete these before importing your own data)
    fputcsv($out, ['Fresh Mango', 'Fruits', '120', '50', 'Sweet and juicy Alphonso mangoes', '']);
    fputcsv($out, ['Organic Spinach', 'Vegetables', '40', '30', 'Farm-fresh green spinach', '']);
    fclose($out);
    exit;
}

$error   = "";
$success = "";
$bulk    = null;  // bulk import result summary

// ─────────────────────────────────────────────────────────────────────────────
// BULK CSV IMPORT
// ─────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_upload'])) {
    if (!isset($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
        $error = "Please choose a CSV file to upload.";
    } else {
        $ext = strtolower(pathinfo($_FILES['csv']['name'], PATHINFO_EXTENSION));
        if ($ext !== 'csv') {
            $error = "Only .csv files are supported. In Excel or Google Sheets choose File → Save As / Download → CSV, then upload that file.";
        } else {
            $handle = fopen($_FILES['csv']['tmp_name'], 'r');
            if (!$handle) {
                $error = "Could not read the uploaded file. Please try again.";
            } else {
                // Detect the delimiter from the first line (comma / semicolon / tab).
                $firstLine = fgets($handle);
                $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', (string)$firstLine); // strip UTF-8 BOM
                $counts = [
                    ','  => substr_count($firstLine, ','),
                    ';'  => substr_count($firstLine, ';'),
                    "\t" => substr_count($firstLine, "\t"),
                ];
                arsort($counts);
                $delim = array_key_first($counts);
                rewind($handle);

                $header = fgetcsv($handle, 0, $delim);
                if ($header === false) {
                    $error = "The CSV file appears to be empty.";
                } else {
                    // Normalise header names: strip BOM, lowercase, trim, spaces/dashes → underscore.
                    $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$header[0]);
                    $norm = [];
                    foreach ($header as $i => $h) {
                        $key = strtolower(trim((string)$h));
                        $key = str_replace([' ', '-'], '_', $key);
                        if ($key !== '') $norm[$key] = $i;
                    }
                    // Resolve a column index from a list of accepted aliases.
                    $col = function ($aliases) use ($norm) {
                        foreach ((array)$aliases as $a) {
                            if (array_key_exists($a, $norm)) return $norm[$a];
                        }
                        return null;
                    };

                    $iName  = $col(['name', 'product_name', 'product']);
                    $iCat   = $col(['category', 'cat']);
                    $iPrice = $col(['price_per_kg', 'price', 'pricekg', 'price_kg']);
                    $iStock = $col(['stock', 'stock_kg', 'quantity', 'qty']);
                    $iDesc  = $col(['description', 'desc', 'details']);
                    $iImage = $col(['image', 'image_url', 'image_filename', 'img']);

                    if ($iName === null || $iPrice === null) {
                        $error = "Your CSV must contain at least a 'name' and a 'price_per_kg' column. Download the template for the exact format.";
                    } else {
                        // SAME insert shape as the single-product form (price = price_per_kg).
                        $insertStmt = $conn->prepare("
                            INSERT INTO products (name, category, price, price_per_kg, stock, image, description, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                        ");
                        // Skip products that already exist (by exact name) to avoid re-import duplicates.
                        $dupStmt = $conn->prepare("SELECT COUNT(*) FROM products WHERE LOWER(name) = LOWER(?)");

                        $added = 0; $skipped = 0; $dupes = 0; $rowErrors = [];
                        $rowNum = 1; // header is row 1

                        while (($row = fgetcsv($handle, 0, $delim)) !== false) {
                            $rowNum++;

                            // Ignore completely blank lines.
                            $nonEmpty = array_filter($row, fn($c) => trim((string)$c) !== '');
                            if (count($nonEmpty) === 0) continue;

                            $name     = trim((string)($row[$iName] ?? ''));
                            $priceRaw = trim((string)($row[$iPrice] ?? ''));
                            $price    = (float)$priceRaw;

                            if ($name === '') {
                                $skipped++; $rowErrors[] = "Row {$rowNum}: skipped — name is empty"; continue;
                            }
                            if ($price <= 0) {
                                $skipped++; $rowErrors[] = "Row {$rowNum} ({$name}): skipped — invalid price '{$priceRaw}'"; continue;
                            }

                            $dupStmt->execute([$name]);
                            if ((int)$dupStmt->fetchColumn() > 0) {
                                $dupes++; $rowErrors[] = "Row {$rowNum} ({$name}): skipped — already exists"; continue;
                            }

                            $category = $iCat   !== null ? trim((string)($row[$iCat]   ?? '')) : '';
                            $stock    = $iStock !== null ? (int)($row[$iStock] ?? 0)            : 0;
                            $desc     = $iDesc  !== null ? trim((string)($row[$iDesc]  ?? '')) : '';
                            $image    = $iImage !== null ? trim((string)($row[$iImage] ?? '')) : '';

                            try {
                                $insertStmt->execute([$name, $category, $price, $price, $stock, $image, $desc]);
                                $added++;
                            } catch (Exception $e) {
                                $skipped++;
                                $rowErrors[] = "Row {$rowNum} ({$name}): database error — " . $e->getMessage();
                            }
                        }

                        $bulk = ['added' => $added, 'skipped' => $skipped, 'dupes' => $dupes, 'errors' => $rowErrors];
                    }
                }
                if (is_resource($handle)) fclose($handle);
            }
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// SINGLE PRODUCT ADD  (unchanged — only runs when it's NOT a bulk upload)
// ─────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['bulk_upload'])) {
    $name = trim($_POST['name']);
    $category = trim($_POST['category']);
    $price_per_kg = (float)$_POST['price_per_kg'];
    $unit = in_array(($_POST['unit'] ?? 'kg'), ['kg','piece','dozen','bunch','pack'], true) ? $_POST['unit'] : 'kg';
    // Optional per-piece price. Blank => NULL => single-unit product exactly as before.
    $piece_price = (isset($_POST['piece_price']) && trim($_POST['piece_price']) !== '')
        ? (float)$_POST['piece_price'] : null;
    $stock = (int)$_POST['stock'];
    $description = trim($_POST['description']);

    // Handle image upload
    $imageName = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $filename = $_FILES['image']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (in_array($ext, $allowed)) {
            $imageName = 'product_' . time() . '.' . $ext;
            $uploadPath = 'assets/images/uploads/' . $imageName;

            if (!move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
                $error = "Failed to upload image";
            }
        } else {
            $error = "Invalid image format";
        }
    }

    if (empty($error)) {
        try {
            // `piece_price` is an additive column from the festival migration.
            // Fall back to the original insert shape if it isn't there yet.
            $hasPiecePrice = false;
            try {
                $hasPiecePrice = (bool)$conn->query("SHOW COLUMNS FROM products LIKE 'piece_price'")->fetch();
            } catch (Exception $e) { $hasPiecePrice = false; }

            if ($hasPiecePrice) {
                $stmt = $conn->prepare("
                    INSERT INTO products (name, category, price, price_per_kg, piece_price, unit, stock, image, description, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$name, $category, $price_per_kg, $price_per_kg, $piece_price, $unit, $stock, $imageName, $description]);
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO products (name, category, price, price_per_kg, unit, stock, image, description, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$name, $category, $price_per_kg, $price_per_kg, $unit, $stock, $imageName, $description]);
            }

            header("Location: products.php?msg=added");
            exit;
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Which tab to show first
$activeTab = (isset($_POST['bulk_upload']) || $bulk !== null) ? 'bulk' : 'single';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product - VFS Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="w-full max-w-2xl">
            <div class="bg-white rounded-2xl shadow-2xl p-8">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-3xl font-bold text-gray-800">Add Products</h2>
                    <a href="products.php" class="text-gray-600 hover:text-gray-800">
                        <i class="fas fa-times text-2xl"></i>
                    </a>
                </div>

                <!-- Tabs -->
                <div class="flex gap-2 mb-6 bg-gray-100 p-1 rounded-xl">
                    <button type="button" id="tab-single" onclick="showTab('single')"
                            class="flex-1 py-2.5 rounded-lg font-semibold transition">
                        <i class="fas fa-box mr-2"></i>Single Product
                    </button>
                    <button type="button" id="tab-bulk" onclick="showTab('bulk')"
                            class="flex-1 py-2.5 rounded-lg font-semibold transition">
                        <i class="fas fa-file-csv mr-2"></i>Bulk Upload (CSV)
                    </button>
                </div>

                <?php if ($error): ?>
                <div class="bg-red-100 text-red-700 p-4 rounded-lg mb-4">
                    <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?>
                </div>
                <?php endif; ?>

                <!-- Bulk import result -->
                <?php if ($bulk !== null): ?>
                <div class="bg-green-50 border border-green-200 text-gray-800 p-4 rounded-lg mb-4">
                    <p class="font-bold text-green-800 mb-1"><i class="fas fa-check-circle mr-2"></i>Import finished</p>
                    <p class="text-sm">
                        <span class="font-semibold text-green-700"><?= (int)$bulk['added'] ?></span> added,
                        <span class="font-semibold text-amber-700"><?= (int)$bulk['dupes'] ?></span> skipped (already exist),
                        <span class="font-semibold text-red-700"><?= (int)$bulk['skipped'] ?></span> skipped (errors).
                    </p>
                    <?php if (!empty($bulk['errors'])): ?>
                    <details class="mt-2">
                        <summary class="cursor-pointer text-sm text-gray-600">Show details (<?= count($bulk['errors']) ?>)</summary>
                        <ul class="mt-2 text-xs text-gray-600 list-disc list-inside max-h-48 overflow-y-auto space-y-1">
                            <?php foreach ($bulk['errors'] as $e): ?>
                            <li><?= htmlspecialchars($e) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </details>
                    <?php endif; ?>
                    <?php if ((int)$bulk['added'] > 0): ?>
                    <a href="products.php" class="inline-block mt-3 text-sm font-semibold text-green-700 hover:underline">
                        <i class="fas fa-arrow-right mr-1"></i>View products
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- ── Single product panel ── -->
                <div id="panel-single">
                    <form method="POST" enctype="multipart/form-data" class="space-y-4">
                        <!-- Product Name -->
                        <div>
                            <label class="block text-gray-700 font-semibold mb-2">
                                <i class="fas fa-box mr-2 text-green-600"></i>Product Name *
                            </label>
                            <input type="text" name="name" required
                                   class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-green-500"
                                   placeholder="e.g., Fresh Mango">
                        </div>

                        <!-- Category & Price -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-gray-700 font-semibold mb-2">
                                    <i class="fas fa-tag mr-2 text-blue-600"></i>Category
                                </label>
                                <input type="text" name="category"
                                       class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-green-500"
                                       placeholder="e.g., Tropical Fruits">
                            </div>

                            <div>
                                <label class="block text-gray-700 font-semibold mb-2">
                                    <i class="fas fa-rupee-sign mr-2 text-green-600"></i>Price per kg *
                                </label>
                                <input type="number" step="0.01" name="price_per_kg" required
                                       class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-green-500"
                                       placeholder="120.00">
                            </div>
                        </div>

                        <!-- Unit & optional piece price -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-gray-700 font-semibold mb-2">
                                    <i class="fas fa-ruler mr-2 text-indigo-600"></i>Primary Unit
                                </label>
                                <select name="unit" class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-green-500">
                                    <option value="kg">Per kg</option>
                                    <option value="piece">Per Piece</option>
                                    <option value="dozen">Per Dozen</option>
                                    <option value="bunch">Per Bunch</option>
                                    <option value="pack">Per Pack</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-gray-700 font-semibold mb-2">
                                    <i class="fas fa-rupee-sign mr-2 text-green-600"></i>Piece price (optional)
                                </label>
                                <input type="number" step="0.01" min="0" name="piece_price"
                                       class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-green-500"
                                       placeholder="Blank if not sold by piece">
                                <p class="text-xs text-gray-500 mt-1">Fill in to also sell by piece on festival pages.</p>
                            </div>
                        </div>

                        <!-- Stock -->
                        <div>
                            <label class="block text-gray-700 font-semibold mb-2">
                                <i class="fas fa-layer-group mr-2 text-purple-600"></i>Stock (kg) *
                            </label>
                            <input type="number" name="stock" required
                                   class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-green-500"
                                   placeholder="50">
                        </div>

                        <!-- Description -->
                        <div>
                            <label class="block text-gray-700 font-semibold mb-2">
                                <i class="fas fa-align-left mr-2 text-gray-600"></i>Description
                            </label>
                            <textarea name="description" rows="3"
                                      class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-green-500"
                                      placeholder="Product description..."></textarea>
                        </div>

                        <!-- Image Upload -->
                        <div>
                            <label class="block text-gray-700 font-semibold mb-2">
                                <i class="fas fa-image mr-2 text-orange-600"></i>Product Image
                            </label>
                            <input type="file" name="image" accept="image/*"
                                   class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-green-500">
                            <p class="text-xs text-gray-500 mt-1">Supported: JPG, PNG, GIF, WEBP</p>
                        </div>

                        <!-- Buttons -->
                        <div class="flex gap-4 pt-4">
                            <button type="submit"
                                    class="flex-1 bg-green-600 hover:bg-green-700 text-white font-bold py-3 rounded-lg transition">
                                <i class="fas fa-check mr-2"></i>Add Product
                            </button>
                            <a href="products.php"
                               class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-3 rounded-lg transition text-center">
                                <i class="fas fa-times mr-2"></i>Cancel
                            </a>
                        </div>
                    </form>
                </div>

                <!-- ── Bulk upload panel ── -->
                <div id="panel-bulk">
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4 text-sm text-blue-900">
                        <p class="font-semibold mb-2"><i class="fas fa-info-circle mr-2"></i>How bulk upload works</p>
                        <ol class="list-decimal list-inside space-y-1 text-blue-800">
                            <li>Download the template and fill in your products in Excel / Google Sheets.</li>
                            <li>Save / export the file as <b>CSV</b> (File → Save As / Download → CSV).</li>
                            <li>Upload it below — each row becomes a product.</li>
                        </ol>
                        <p class="mt-3">
                            Columns: <code class="bg-white px-1 rounded">name</code><span class="text-red-600">*</span>,
                            <code class="bg-white px-1 rounded">category</code>,
                            <code class="bg-white px-1 rounded">price_per_kg</code><span class="text-red-600">*</span>,
                            <code class="bg-white px-1 rounded">stock</code>,
                            <code class="bg-white px-1 rounded">description</code>,
                            <code class="bg-white px-1 rounded">image</code>
                        </p>
                        <p class="mt-1 text-xs text-blue-700">* required. <code>image</code> is the filename of an image already uploaded to <code>assets/images/uploads/</code> — leave blank to add it later. Duplicate names are skipped automatically.</p>
                        <a href="product_add.php?template=1"
                           class="inline-block mt-3 bg-white border border-blue-300 text-blue-700 font-semibold px-4 py-2 rounded-lg hover:bg-blue-100 transition">
                            <i class="fas fa-download mr-2"></i>Download CSV template
                        </a>
                    </div>

                    <form method="POST" enctype="multipart/form-data" class="space-y-4">
                        <input type="hidden" name="bulk_upload" value="1">
                        <div>
                            <label class="block text-gray-700 font-semibold mb-2">
                                <i class="fas fa-file-csv mr-2 text-green-600"></i>CSV File *
                            </label>
                            <input type="file" name="csv" accept=".csv" required
                                   class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-green-500">
                        </div>
                        <div class="flex gap-4 pt-2">
                            <button type="submit"
                                    class="flex-1 bg-green-600 hover:bg-green-700 text-white font-bold py-3 rounded-lg transition">
                                <i class="fas fa-upload mr-2"></i>Upload &amp; Import
                            </button>
                            <a href="products.php"
                               class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-3 rounded-lg transition text-center">
                                <i class="fas fa-times mr-2"></i>Cancel
                            </a>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>

    <script>
        const ACTIVE = "bg-white text-green-700 shadow";
        const INACTIVE = "text-gray-500 hover:text-gray-700";
        function showTab(tab) {
            document.getElementById('panel-single').style.display = tab === 'single' ? 'block' : 'none';
            document.getElementById('panel-bulk').style.display   = tab === 'bulk'   ? 'block' : 'none';
            document.getElementById('tab-single').className = 'flex-1 py-2.5 rounded-lg font-semibold transition ' + (tab === 'single' ? ACTIVE : INACTIVE);
            document.getElementById('tab-bulk').className   = 'flex-1 py-2.5 rounded-lg font-semibold transition ' + (tab === 'bulk'   ? ACTIVE : INACTIVE);
        }
        showTab(<?= json_encode($activeTab) ?>);
    </script>
</body>
</html>
