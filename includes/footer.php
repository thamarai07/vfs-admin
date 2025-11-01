</main> <!-- Close main tag from header -->

        <!-- Footer -->
        <footer class="bg-white border-t border-gray-200 py-4 px-4 md:px-8 mt-8">
            <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                <p class="text-sm text-gray-600">
                    © <?= date('Y') ?> Vasugi Fruit Shop. All rights reserved.
                </p>
                <div class="flex items-center gap-4 text-sm text-gray-600">
                    <a href="#" class="hover:text-green-600 transition">Privacy Policy</a>
                    <span>•</span>
                    <a href="#" class="hover:text-green-600 transition">Terms of Service</a>
                    <span>•</span>
                    <a href="#" class="hover:text-green-600 transition">Support</a>
                </div>
            </div>
        </footer>
    </div> <!-- Close ml-64 div from header -->

    <!-- Success/Error Toast (Optional) -->
    <div id="toast" class="fixed bottom-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg hidden animate-slide-up">
        <div class="flex items-center gap-3">
            <i class="fas fa-check-circle text-xl"></i>
            <span id="toast-message">Action completed successfully!</span>
        </div>
    </div>

    <style>
        @keyframes slide-up {
            from { transform: translateY(100px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .animate-slide-up { animation: slide-up 0.3s ease-out; }
    </style>

    <script>
        // Toast notification function
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            const toastMessage = document.getElementById('toast-message');
            
            toast.classList.remove('hidden', 'bg-green-500', 'bg-red-500', 'bg-blue-500');
            
            if (type === 'error') {
                toast.classList.add('bg-red-500');
            } else if (type === 'info') {
                toast.classList.add('bg-blue-500');
            } else {
                toast.classList.add('bg-green-500');
            }
            
            toastMessage.textContent = message;
            
            setTimeout(() => {
                toast.classList.add('hidden');
            }, 3000);
        }

        // Confirm delete function
        function confirmDelete(message = 'Are you sure you want to delete this item?') {
            return confirm(message);
        }

        // Auto-hide alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert-dismissible');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>