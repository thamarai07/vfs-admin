<div class="header">
  <h2>Fruit Store CRM</h2>
  <div class="user-info">
    <span>👤 <?= htmlspecialchars($_SESSION['user']); ?></span>
  </div><div id="dialog-box" class="dialog-box">
  <div class="dialog-content">
    <span id="dialog-icon">✅</span>
    <p id="dialog-message">Action successful!</p>
  </div>
</div>

<script>
function showDialog(message, type = "success") {
  const box = document.getElementById('dialog-box');
  const msg = document.getElementById('dialog-message');
  const icon = document.getElementById('dialog-icon');

  msg.textContent = message;
  if (type === "success") {
    icon.textContent = "✅";
    box.style.background = "linear-gradient(135deg, #00c853, #b2ff59)";
  } else if (type === "error") {
    icon.textContent = "❌";
    box.style.background = "linear-gradient(135deg, #ff1744, #ff8a80)";
  } else {
    icon.textContent = "ℹ️";
    box.style.background = "linear-gradient(135deg, #2979ff, #82b1ff)";
  }

  box.classList.add('show');
  setTimeout(() => box.classList.remove('show'), 2500);
}
</script>


</div>


