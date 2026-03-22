# vfs-admin
# Basic call
GET /api/fetchbanner.php?device=desktop

# With location
GET /api/fetchbanner.php?device=mobile&city=Mumbai&pincode=400001

# With user segment
GET /api/fetchbanner.php?device=desktop&user_segment=premium

# Debug mode
GET /api/fetchbanner.php?device=desktop&debug=1

# Clear cache
GET /api/fetchbanner.php?action=clear_cache

# Include inactive (for preview)
GET /api/fetchbanner.php?device=desktop&include_inactive=1