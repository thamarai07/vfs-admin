<?php
/**
 * Admin → Settings
 * Store-wide settings. Currently: the tax rate charged on new orders
 * (website + app). Saved in the `settings` table via config/store_settings.php.
 */
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/store_settings.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sentToken = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals($_SESSION['csrf_token'], $sentToken)) {
        $error = 'Your session expired. Please reload the page and try again.';
    } else {
        $newRate = normalizeTaxRatePercent($_POST['tax_rate_percent'] ?? '');
        if ($newRate === null) {
            $error = 'Enter a tax rate between 0 and ' . formatTaxRatePercent(STORE_MAX_TAX_RATE_PERCENT) . ' (up to 2 decimals).';
        } else {
            try {
                $oldRate = getTaxRatePercent($conn);
                saveTaxRatePercent($conn, $newRate);
                error_log(sprintf(
                    '[settings] tax rate changed %s%% -> %s%% by admin_id=%d',
                    formatTaxRatePercent($oldRate),
                    formatTaxRatePercent($newRate),
                    (int) $_SESSION['admin_id']
                ));
                header('Location: settings.php?msg=saved');
                exit;
            } catch (Throwable $e) {
                error_log('[settings] save failed: ' . $e->getMessage());
                $error = 'Could not save the setting. Please try again.';
            }
        }
    }
}

$currentRate = getTaxRatePercent($conn);
$formValue   = ($error !== '' && isset($_POST['tax_rate_percent']))
    ? (string) $_POST['tax_rate_percent']
    : formatTaxRatePercent($currentRate);

$pageTitle = 'Settings';
include __DIR__ . '/includes/header.php';
?>

<div class="max-w-2xl">
    <?php if (($_GET['msg'] ?? '') === 'saved' && $error === ''): ?>
        <div class="mb-6 p-4 rounded-lg bg-green-100 text-green-800">
            <i class="fas fa-check-circle mr-2"></i>
            Saved. New orders on the website and app now use <?= htmlspecialchars(formatTaxRatePercent($currentRate)) ?>% tax.
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="mb-6 p-4 rounded-lg bg-red-100 text-red-700">
            <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-md p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-1"><i class="fas fa-percent text-green-600 mr-2"></i>Tax on orders</h3>
        <p class="text-sm text-gray-600 mb-6">
            Percent added to the order subtotal at checkout, on the website and in the app.
            Enter <strong>0</strong> to charge no tax.
        </p>

        <form method="post" id="taxForm" class="space-y-5">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

            <div>
                <label for="tax_rate_percent" class="block text-sm font-semibold text-gray-700 mb-2">Tax rate (%)</label>
                <div class="flex items-center gap-3">
                    <input
                        type="number"
                        id="tax_rate_percent"
                        name="tax_rate_percent"
                        min="0"
                        max="<?= htmlspecialchars(formatTaxRatePercent(STORE_MAX_TAX_RATE_PERCENT)) ?>"
                        step="0.01"
                        required
                        value="<?= htmlspecialchars($formValue) ?>"
                        class="w-40 px-4 py-3 border rounded-lg focus:ring-2 focus:ring-green-500"
                    >
                    <span class="text-gray-600">Currently: <strong><?= htmlspecialchars(formatTaxRatePercent($currentRate)) ?>%</strong></span>
                </div>
                <p class="text-xs text-gray-500 mt-2" id="taxExample"></p>
            </div>

            <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 rounded text-sm text-yellow-800 space-y-1">
                <p><strong>Before you change this:</strong> confirm the correct GST rate for your products with your CA.</p>
                <p>The new rate applies to <strong>new orders only</strong>. Orders already placed keep the tax they were charged.</p>
                <p>A customer who already has the checkout open may be asked to review their cart once, because the total changed.</p>
            </div>

            <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold px-6 py-3 rounded-lg">
                <i class="fas fa-save mr-2"></i>Save
            </button>
        </form>
    </div>
</div>

<script>
(function () {
    var input = document.getElementById('tax_rate_percent');
    var example = document.getElementById('taxExample');
    var current = <?= json_encode($currentRate) ?>;

    function updateExample() {
        var rate = parseFloat(input.value);
        if (isNaN(rate)) { example.textContent = ''; return; }
        var tax = Math.round(1000 * rate) / 100; // ₹1000 × rate / 100, 2 decimals
        example.textContent = 'Example: on a ₹1000 order the tax will be ₹' + tax.toFixed(2) + '.';
    }
    input.addEventListener('input', updateExample);
    updateExample();

    document.getElementById('taxForm').addEventListener('submit', function (e) {
        var rate = parseFloat(input.value);
        if (rate !== current && !confirm('Change the tax on all new orders from ' + current + '% to ' + rate + '%?')) {
            e.preventDefault();
        }
    });
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
