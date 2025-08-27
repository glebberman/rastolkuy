# Deployment Guide - Legal Translator

## 🚀 Initial Deployment

### 1. Environment Setup

Copy `.env.example` to `.env` and configure:

```bash
cp .env.example .env
php artisan key:generate
```

### 2. Database Setup

```bash
# Run migrations
php artisan migrate

# Seed database (includes admin user)
php artisan db:seed
```

### 3. Admin User Configuration

Configure admin credentials in `.env`:

```env
APP_USER_ADMIN_EMAIL=admin@rastolkuy.ru
APP_USER_ADMIN_DEFAULT_PASSWORD='YourSecurePassword123!'
```

The admin user will be automatically created during seeding with these credentials.

## 👤 Admin User Management

### Default Admin Creation

The system automatically creates a default admin user during database seeding:

- **Email**: Configured via `APP_USER_ADMIN_EMAIL` (default: `admin@example.com`)
- **Password**: Configured via `APP_USER_ADMIN_DEFAULT_PASSWORD` (default: `password123`)
- **Role**: `admin` with full permissions

### Manual Admin Creation

You can create additional admin users using the artisan command:

```bash
# Interactive mode
php artisan admin:create

# With parameters
php artisan admin:create --email="admin@example.com" --password="SecurePassword123" --name="Administrator"

# Force update existing user
php artisan admin:create --email="existing@example.com" --password="NewPassword" --force
```

### Admin Permissions

The admin role includes the following permissions:
- `manage_users` - Manage user accounts
- `manage_documents` - Manage document processing
- `view_admin_panel` - Access admin interface
- `manage_settings` - Modify system settings
- `view_statistics` - View system analytics

## 🔧 Production Deployment

### 1. Environment Configuration

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# Admin Configuration
APP_USER_ADMIN_EMAIL=admin@your-domain.com
APP_USER_ADMIN_DEFAULT_PASSWORD='YourVerySecurePassword!'
```

### 2. Security Best Practices

1. **Change Default Password**: Always change the default admin password after first login
2. **Use Strong Passwords**: Minimum 12 characters with mixed case, numbers, and symbols
3. **Secure Email**: Use a dedicated admin email address
4. **Regular Updates**: Periodically update admin passwords

### 3. First Login

1. Navigate to `/login`
2. Use the configured admin email and password
3. **Immediately change the password** in user settings
4. Review and configure system settings

## 📊 Verification

### Check Admin User

```bash
# List all admin users
php artisan tinker
>>> User::role('admin')->get(['name', 'email'])

# Check user permissions
>>> User::where('email', 'admin@rastolkuy.ru')->first()->getAllPermissions()->pluck('name')
```

### Health Check

```bash
# Run system health checks
php artisan route:list | grep admin
php artisan permission:cache-reset
php artisan config:cache
```

## 🛠️ Troubleshooting

### Admin User Issues

1. **User not created**: Check seeder logs and database connection
2. **Permission issues**: Run `php artisan permission:cache-reset`
3. **Login problems**: Verify email/password and check Laravel logs
4. **Role assignment**: Ensure Spatie Permission tables are migrated

### Common Commands

```bash
# Reset admin password
php artisan admin:create --email="admin@rastolkuy.ru" --password="NewPassword" --force

# Clear caches
php artisan cache:clear
php artisan config:clear
php artisan permission:cache-reset

# View logs
tail -f storage/logs/laravel.log
```

## 📝 Notes

- Admin credentials are sensitive information - store securely
- Consider using environment-specific configurations
- Implement additional security measures for production (2FA, etc.)
- Monitor admin account activity for security auditing