/**
 * Real-time Notification System for Fruit CRM
 * Features: Toast notifications, badge counters, sound alerts
 */

class NotificationSystem {
    constructor() {
      this.notifications = [];
      this.unreadCount = 0;
      this.soundEnabled = true;
      this.init();
    }
  
    init() {
      this.createNotificationContainer();
      this.createNotificationBell();
      this.startPolling();
      this.loadSavedNotifications();
    }
  
    // Create notification container
    createNotificationContainer() {
      const container = document.createElement('div');
      container.id = 'notification-container';
      container.style.cssText = `
        position: fixed;
        top: 80px;
        right: 20px;
        z-index: 10000;
        max-width: 400px;
        width: calc(100% - 40px);
      `;
      document.body.appendChild(container);
    }
  
    // Create notification bell icon in header
    createNotificationBell() {
      const bell = document.createElement('div');
      bell.id = 'notification-bell';
      bell.innerHTML = `
        <button class="notification-btn" onclick="notificationSystem.togglePanel()">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
          </svg>
          <span class="notification-badge" id="notification-badge" style="display:none;">0</span>
        </button>
        <div class="notification-panel" id="notification-panel" style="display:none;">
          <div class="panel-header">
            <h3>Notifications</h3>
            <button onclick="notificationSystem.clearAll()" class="clear-btn">Clear All</button>
          </div>
          <div class="panel-content" id="notification-list"></div>
        </div>
      `;
  
      // Add styles
      const style = document.createElement('style');
      style.textContent = `
        .notification-btn {
          position: relative;
          background: white;
          border: none;
          width: 44px;
          height: 44px;
          border-radius: 12px;
          cursor: pointer;
          display: flex;
          align-items: center;
          justify-content: center;
          color: #64748b;
          transition: all 0.3s;
          box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .notification-btn:hover {
          background: #f8fafc;
          color: #667eea;
          transform: translateY(-2px);
          box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        }
        .notification-badge {
          position: absolute;
          top: -5px;
          right: -5px;
          background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
          color: white;
          font-size: 11px;
          font-weight: 700;
          padding: 3px 7px;
          border-radius: 10px;
          min-width: 20px;
          text-align: center;
          animation: bounce 0.5s;
        }
        @keyframes bounce {
          0%, 100% { transform: scale(1); }
          50% { transform: scale(1.2); }
        }
        .notification-panel {
          position: absolute;
          top: 55px;
          right: 0;
          width: 380px;
          max-height: 500px;
          background: white;
          border-radius: 16px;
          box-shadow: 0 10px 40px rgba(0,0,0,0.15);
          overflow: hidden;
          animation: slideDown 0.3s ease;
        }
        @keyframes slideDown {
          from {
            opacity: 0;
            transform: translateY(-10px);
          }
          to {
            opacity: 1;
            transform: translateY(0);
          }
        }
        .panel-header {
          padding: 20px;
          border-bottom: 2px solid #f1f5f9;
          display: flex;
          justify-content: space-between;
          align-items: center;
        }
        .panel-header h3 {
          font-size: 18px;
          font-weight: 700;
          color: #0f172a;
          margin: 0;
        }
        .clear-btn {
          background: none;
          border: none;
          color: #667eea;
          font-size: 13px;
          font-weight: 600;
          cursor: pointer;
          padding: 6px 12px;
          border-radius: 8px;
          transition: all 0.3s;
        }
        .clear-btn:hover {
          background: #f1f5f9;
        }
        .panel-content {
          max-height: 400px;
          overflow-y: auto;
        }
        .notification-item {
          padding: 16px 20px;
          border-bottom: 1px solid #f1f5f9;
          cursor: pointer;
          transition: all 0.3s;
          display: flex;
          gap: 12px;
          align-items: flex-start;
        }
        .notification-item:hover {
          background: #f8fafc;
        }
        .notification-item.unread {
          background: #f0f9ff;
        }
        .notification-icon {
          width: 40px;
          height: 40px;
          border-radius: 10px;
          display: flex;
          align-items: center;
          justify-content: center;
          font-size: 20px;
          flex-shrink: 0;
        }
        .notification-content {
          flex: 1;
        }
        .notification-title {
          font-size: 14px;
          font-weight: 600;
          color: #0f172a;
          margin-bottom: 4px;
        }
        .notification-message {
          font-size: 13px;
          color: #64748b;
          line-height: 1.5;
          margin-bottom: 4px;
        }
        .notification-time {
          font-size: 12px;
          color: #94a3b8;
        }
        .empty-notifications {
          padding: 60px 20px;
          text-align: center;
          color: #94a3b8;
        }
        .empty-icon {
          font-size: 48px;
          margin-bottom: 12px;
          opacity: 0.5;
        }
  
        /* Toast Notification */
        .toast-notification {
          background: white;
          border-radius: 14px;
          padding: 16px 20px;
          box-shadow: 0 10px 40px rgba(0,0,0,0.2);
          display: flex;
          align-items: center;
          gap: 12px;
          margin-bottom: 12px;
          animation: slideInRight 0.3s ease;
          border-left: 4px solid var(--toast-color);
          max-width: 400px;
        }
        @keyframes slideInRight {
          from {
            opacity: 0;
            transform: translateX(100%);
          }
          to {
            opacity: 1;
            transform: translateX(0);
          }
        }
        .toast-icon {
          width: 44px;
          height: 44px;
          border-radius: 12px;
          display: flex;
          align-items: center;
          justify-content: center;
          font-size: 22px;
          background: var(--toast-bg);
          flex-shrink: 0;
        }
        .toast-content {
          flex: 1;
        }
        .toast-title {
          font-size: 14px;
          font-weight: 700;
          color: #0f172a;
          margin-bottom: 4px;
        }
        .toast-message {
          font-size: 13px;
          color: #64748b;
          line-height: 1.4;
        }
        .toast-close {
          background: none;
          border: none;
          color: #94a3b8;
          cursor: pointer;
          font-size: 20px;
          padding: 4px;
          line-height: 1;
          transition: all 0.3s;
        }
        .toast-close:hover {
          color: #64748b;
        }
      `;
      document.head.appendChild(style);
  
      // Add to header (you may need to adjust selector based on your header structure)
      const header = document.querySelector('.main-content header') || document.querySelector('header');
      if (header) {
        header.appendChild(bell);
      }
    }
  
    // Toggle notification panel
    togglePanel() {
      const panel = document.getElementById('notification-panel');
      const isVisible = panel.style.display !== 'none';
      panel.style.display = isVisible ? 'none' : 'block';
  
      if (!isVisible) {
        this.markAllAsRead();
        this.renderNotificationList();
      }
    }
  
    // Show toast notification
    showToast(title, message, type = 'info', duration = 5000) {
      const container = document.getElementById('notification-container');
      const toast = document.createElement('div');
      
      const colors = {
        success: { color: '#10b981', bg: 'rgba(16, 185, 129, 0.1)' },
        error: { color: '#ef4444', bg: 'rgba(239, 68, 68, 0.1)' },
        warning: { color: '#f59e0b', bg: 'rgba(245, 158, 11, 0.1)' },
        info: { color: '#3b82f6', bg: 'rgba(59, 130, 246, 0.1)' }
      };
  
      const icons = {
        success: '✓',
        error: '✕',
        warning: '⚠',
        info: 'ℹ'
      };
  
      toast.className = 'toast-notification';
      toast.style.setProperty('--toast-color', colors[type].color);
      toast.style.setProperty('--toast-bg', colors[type].bg);
      
      toast.innerHTML = `
        <div class="toast-icon">${icons[type]}</div>
        <div class="toast-content">
          <div class="toast-title">${title}</div>
          <div class="toast-message">${message}</div>
        </div>
        <button class="toast-close" onclick="this.parentElement.remove()">×</button>
      `;
  
      container.appendChild(toast);
  
      // Play sound
      if (this.soundEnabled) {
        this.playNotificationSound(type);
      }
  
      // Auto remove
      setTimeout(() => {
        toast.style.animation = 'slideInRight 0.3s ease reverse';
        setTimeout(() => toast.remove(), 300);
      }, duration);
    }
  
    // Add notification to list
    addNotification(notification) {
      notification.id = Date.now();
      notification.read = false;
      notification.timestamp = new Date().toISOString();
      
      this.notifications.unshift(notification);
      this.unreadCount++;
      this.updateBadge();
      this.saveNotifications();
      
      // Show toast
      this.showToast(notification.title, notification.message, notification.type);
    }
  
    // Render notification list
    renderNotificationList() {
      const listContainer = document.getElementById('notification-list');
      
      if (this.notifications.length === 0) {
        listContainer.innerHTML = `
          <div class="empty-notifications">
            <div class="empty-icon">🔔</div>
            <p>No notifications yet</p>
          </div>
        `;
        return;
      }
  
      listContainer.innerHTML = this.notifications.map(notif => {
        const timeAgo = this.getTimeAgo(notif.timestamp);
        const icons = {
          success: { icon: '✓', bg: 'rgba(16, 185, 129, 0.15)' },
          error: { icon: '✕', bg: 'rgba(239, 68, 68, 0.15)' },
          warning: { icon: '⚠', bg: 'rgba(245, 158, 11, 0.15)' },
          info: { icon: 'ℹ', bg: 'rgba(59, 130, 246, 0.15)' },
          order: { icon: '📦', bg: 'rgba(102, 126, 234, 0.15)' },
          stock: { icon: '⚠️', bg: 'rgba(245, 158, 11, 0.15)' },
          customer: { icon: '👤', bg: 'rgba(16, 185, 129, 0.15)' }
        };
  
        const iconData = icons[notif.type] || icons.info;
  
        return `
          <div class="notification-item ${notif.read ? '' : 'unread'}" onclick="notificationSystem.markAsRead(${notif.id})">
            <div class="notification-icon" style="background: ${iconData.bg};">
              ${iconData.icon}
            </div>
            <div class="notification-content">
              <div class="notification-title">${notif.title}</div>
              <div class="notification-message">${notif.message}</div>
              <div class="notification-time">${timeAgo}</div>
            </div>
          </div>
        `;
      }).join('');
    }
  
    // Mark notification as read
    markAsRead(id) {
      const notif = this.notifications.find(n => n.id === id);
      if (notif && !notif.read) {
        notif.read = true;
        this.unreadCount--;
        this.updateBadge();
        this.saveNotifications();
        this.renderNotificationList();
      }
    }
  
    // Mark all as read
    markAllAsRead() {
      this.notifications.forEach(n => n.read = true);
      this.unreadCount = 0;
      this.updateBadge();
      this.saveNotifications();
    }
  
    // Clear all notifications
    clearAll() {
      if (confirm('Are you sure you want to clear all notifications?')) {
        this.notifications = [];
        this.unreadCount = 0;
        this.updateBadge();
        this.saveNotifications();
        this.renderNotificationList();
      }
    }
  
    // Update badge counter
    updateBadge() {
      const badge = document.getElementById('notification-badge');
      if (badge) {
        if (this.unreadCount > 0) {
          badge.textContent = this.unreadCount > 99 ? '99+' : this.unreadCount;
          badge.style.display = 'block';
        } else {
          badge.style.display = 'none';
        }
      }
    }
  
    // Get time ago string
    getTimeAgo(timestamp) {
      const now = new Date();
      const time = new Date(timestamp);
      const diff = Math.floor((now - time) / 1000);
  
      if (diff < 60) return 'Just now';
      if (diff < 3600) return `${Math.floor(diff / 60)} mins ago`;
      if (diff < 86400) return `${Math.floor(diff / 3600)} hours ago`;
      if (diff < 604800) return `${Math.floor(diff / 86400)} days ago`;
      
      return time.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    }
  
    // Play notification sound
    playNotificationSound(type) {
      const audioContext = new (window.AudioContext || window.webkitAudioContext)();
      const oscillator = audioContext.createOscillator();
      const gainNode = audioContext.createGain();
  
      oscillator.connect(gainNode);
      gainNode.connect(audioContext.destination);
  
      const frequencies = {
        success: [523.25, 659.25],
        error: [329.63, 261.63],
        warning: [440, 493.88],
        info: [523.25, 587.33]
      };
  
      const freq = frequencies[type] || frequencies.info;
  
      oscillator.frequency.value = freq[0];
      gainNode.gain.value = 0.1;
      oscillator.start(audioContext.currentTime);
      
      setTimeout(() => {
        oscillator.frequency.value = freq[1];
      }, 100);
  
      oscillator.stop(audioContext.currentTime + 0.2);
    }
  
    // Save to localStorage
    saveNotifications() {
      try {
        localStorage.setItem('crm_notifications', JSON.stringify(this.notifications));
      } catch (e) {
        console.error('Failed to save notifications:', e);
      }
    }
  
    // Load from localStorage
    loadSavedNotifications() {
      try {
        const saved = localStorage.getItem('crm_notifications');
        if (saved) {
          this.notifications = JSON.parse(saved);
          this.unreadCount = this.notifications.filter(n => !n.read).length;
          this.updateBadge();
        }
      } catch (e) {
        console.error('Failed to load notifications:', e);
      }
    }
  
    // Start polling for new notifications
    startPolling() {
      // Check for new notifications every 30 seconds
      setInterval(() => {
        this.checkForNewNotifications();
      }, 30000);
    }
  
    // Check for new notifications via AJAX
    async checkForNewNotifications() {
      try {
        const response = await fetch('ajax_dashboard.php?action=check_notifications');
        const data = await response.json();
  
        if (data.success && data.notifications) {
          data.notifications.forEach(notif => {
            // Check if notification already exists
            const exists = this.notifications.find(n => n.serverId === notif.id);
            if (!exists) {
              this.addNotification({
                serverId: notif.id,
                title: notif.title,
                message: notif.message,
                type: notif.type
              });
            }
          });
        }
      } catch (e) {
        console.error('Failed to check notifications:', e);
      }
    }
  
    // Toggle sound
    toggleSound() {
      this.soundEnabled = !this.soundEnabled;
      localStorage.setItem('crm_notification_sound', this.soundEnabled);
      this.showToast(
        'Settings Updated',
        `Notification sounds ${this.soundEnabled ? 'enabled' : 'disabled'}`,
        'info',
        3000
      );
    }
  }
  
  // Initialize notification system
  const notificationSystem = new NotificationSystem();
  
  // Close panel when clicking outside
  document.addEventListener('click', function(e) {
    const panel = document.getElementById('notification-panel');
    const bell = document.getElementById('notification-bell');
    
    if (panel && bell && !bell.contains(e.target) && panel.style.display !== 'none') {
      panel.style.display = 'none';
    }
  });
  
  // Example usage functions (call these from your PHP/backend)
  window.notifyNewOrder = function(orderData) {
    notificationSystem.addNotification({
      title: 'New Order Received! 🎉',
      message: `Order #${orderData.id} from ${orderData.customer} - ₹${orderData.total}`,
      type: 'order'
    });
  };
  
  window.notifyLowStock = function(productName, stock) {
    notificationSystem.addNotification({
      title: 'Low Stock Alert! ⚠️',
      message: `${productName} is running low (${stock} units left)`,
      type: 'stock'
    });
  };
  
  window.notifyNewCustomer = function(customerName) {
    notificationSystem.addNotification({
      title: 'New Customer Registered! 👋',
      message: `${customerName} just joined your store`,
      type: 'customer'
    });
  };
  
  window.notifyOrderComplete = function(orderId) {
    notificationSystem.addNotification({
      title: 'Order Completed! ✅',
      message: `Order #${orderId} has been successfully completed`,
      type: 'success'
    });
  };
  
  // Quick helper for manual notifications
  window.notify = function(title, message, type = 'info') {
    notificationSystem.showToast(title, message, type);
  };