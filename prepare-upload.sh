#!/bin/bash

# ========================================
# 🚀 GOLF SYSTEM - PREPARE FOR UPLOAD
# ========================================
# Script automatico per preparare upload su hosting condiviso

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}🏌️ Golf System - Upload Preparation Script${NC}"
echo "=================================================="

# Check if we're in Laravel root
if [ ! -f "artisan" ]; then
    echo -e "${RED}❌ Error: Run this script from Laravel root directory${NC}"
    exit 1
fi

# Configuration
UPLOAD_DIR="upload_package"
PUBLIC_DIR="$UPLOAD_DIR/public_html"
PRIVATE_DIR="$UPLOAD_DIR/private_folder"
DOMAIN_URL=""

# Get domain URL from user
echo -e "${YELLOW}🌐 Enter your domain URL (e.g., https://yourdomain.com):${NC}"
read -p "Domain: " DOMAIN_URL

if [ -z "$DOMAIN_URL" ]; then
    echo -e "${RED}❌ Domain URL is required${NC}"
    exit 1
fi

echo -e "${GREEN}✅ Using domain: $DOMAIN_URL${NC}"
echo

# Clean previous package
if [ -d "$UPLOAD_DIR" ]; then
    echo -e "${YELLOW}🧹 Cleaning previous upload package...${NC}"
    rm -rf "$UPLOAD_DIR"
fi

# Create directory structure
echo -e "${BLUE}📁 Creating upload structure...${NC}"
mkdir -p "$PUBLIC_DIR"
mkdir -p "$PRIVATE_DIR"

# Step 1: Optimize for production
echo -e "${BLUE}⚙️ Optimizing for production...${NC}"

# Install production dependencies
composer install --no-dev --optimize-autoloader --no-interaction
composer dump-autoload --optimize --classmap-authoritative

# Clear all caches
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

# Build production caches
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo -e "${GREEN}✅ Production optimization completed${NC}"

# Step 2: Copy public files
echo -e "${BLUE}📋 Copying public files...${NC}"
cp -r public/* "$PUBLIC_DIR/"

# Step 3: Copy private files
echo -e "${BLUE}📋 Copying private files...${NC}"
cp -r app "$PRIVATE_DIR/"
cp -r bootstrap "$PRIVATE_DIR/"
cp -r config "$PRIVATE_DIR/"
cp -r database "$PRIVATE_DIR/"
cp -r resources "$PRIVATE_DIR/"
cp -r routes "$PRIVATE_DIR/"
cp -r vendor "$PRIVATE_DIR/"
cp artisan "$PRIVATE_DIR/"

# Copy storage directory structure (but not contents)
mkdir -p "$PRIVATE_DIR/storage/app/public"
mkdir -p "$PRIVATE_DIR/storage/framework/cache"
mkdir -p "$PRIVATE_DIR/storage/framework/sessions"
mkdir -p "$PRIVATE_DIR/storage/framework/views"
mkdir -p "$PRIVATE_DIR/storage/logs"

# Create empty .gitkeep files for empty directories
touch "$PRIVATE_DIR/storage/app/public/.gitkeep"
touch "$PRIVATE_DIR/storage/framework/cache/.gitkeep"
touch "$PRIVATE_DIR/storage/framework/sessions/.gitkeep"
touch "$PRIVATE_DIR/storage/framework/views/.gitkeep"
touch "$PRIVATE_DIR/storage/logs/.gitkeep"

# Step 4: Create production .env
echo -e "${BLUE}🔧 Creating production .env file...${NC}"
cat > "$PRIVATE_DIR/.env" << EOF
APP_NAME="Golf Referee System"
APP_ENV=production
APP_KEY=base64:$(php artisan --no-ansi key:generate --show)
APP_DEBUG=false
APP_URL=$DOMAIN_URL

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error

# Database - MODIFY THESE WITH YOUR HOSTING CREDENTIALS
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120

MEMCACHED_HOST=127.0.0.1

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Mail Configuration - MODIFY WITH YOUR HOSTING MAIL SETTINGS
MAIL_MAILER=smtp
MAIL_HOST=your-hosting-smtp
MAIL_PORT=587
MAIL_USERNAME=your-email@yourdomain.com
MAIL_PASSWORD=your-email-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="Golf Referee System"

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_HOST=
PUSHER_PORT=443
PUSHER_SCHEME=https
PUSHER_APP_CLUSTER=mt1

VITE_APP_NAME="Golf Referee System"
VITE_PUSHER_APP_KEY="$\{PUSHER_APP_KEY}"
VITE_PUSHER_HOST="$\{PUSHER_HOST}"
VITE_PUSHER_PORT="$\{PUSHER_PORT}"
VITE_PUSHER_SCHEME="$\{PUSHER_SCHEME}"
VITE_PUSHER_APP_CLUSTER="$\{PUSHER_APP_CLUSTER}"
EOF

# Step 5: Modify index.php for hosting structure
echo -e "${BLUE}🔧 Modifying index.php for hosting structure...${NC}"
cat > "$PUBLIC_DIR/index.php" << 'EOF'
<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

/*
|--------------------------------------------------------------------------
| Check If The Application Is Under Maintenance
|--------------------------------------------------------------------------
|
| If the application is in maintenance / demo mode via the "down" command
| we will load this file so that any pre-rendered content can be shown
| instead of starting the framework, which could cause an exception.
|
*/

if (file_exists($maintenance = __DIR__.'/../private_folder/storage/framework/maintenance.php')) {
    require $maintenance;
}

/*
|--------------------------------------------------------------------------
| Register The Auto Loader
|--------------------------------------------------------------------------
|
| Composer provides a convenient, automatically generated class loader for
| this application. We just need to utilize it! We'll simply require it
| into the script here so we don't need to manually load our classes.
|
*/

require __DIR__.'/../private_folder/vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Run The Application
|--------------------------------------------------------------------------
|
| Once we have the application, we can handle the incoming request using
| the application's HTTP kernel. Then, we will send the response back
| to this client's browser, allowing them to enjoy our application.
|
*/

$app = require_once __DIR__.'/../private_folder/bootstrap/app.php';

$kernel = $app->make(Kernel::class);

$response = $kernel->handle(
    $request = Request::capture()
)->send();

$kernel->terminate($request, $response);
EOF

# Step 6: Create database setup route
echo -e "${BLUE}🗄️ Creating database setup route...${NC}"
cat > "$PRIVATE_DIR/routes/setup.php" << 'EOF'
<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| Database Setup Routes - FOR INITIAL SETUP ONLY!
|--------------------------------------------------------------------------
|
| These routes are for initial database setup on hosting without SSH.
| REMOVE THESE ROUTES AFTER INITIAL SETUP FOR SECURITY!
|
*/

Route::prefix('admin-setup')->group(function () {

    // Database migration and seeding
    Route::get('/migrate', function () {
        if (!app()->environment('production')) {
            return 'Only available in production environment';
        }

        try {
            // Run migrations
            Artisan::call('migrate:fresh', ['--force' => true]);

            return [
                'status' => 'success',
                'message' => 'Migrations completed',
                'output' => Artisan::output()
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    });

    Route::get('/seed', function () {
        if (!app()->environment('production')) {
            return 'Only available in production environment';
        }

        try {
            // Run seeders
            Artisan::call('db:seed', ['--force' => true]);

            return [
                'status' => 'success',
                'message' => 'Database seeded successfully',
                'output' => Artisan::output()
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    });

    Route::get('/full-setup', function () {
        if (!app()->environment('production')) {
            return 'Only available in production environment';
        }

        try {
            // Run full setup
            Artisan::call('migrate:fresh', ['--force' => true]);
            Artisan::call('db:seed', ['--force' => true]);

            return [
                'status' => 'success',
                'message' => 'Full database setup completed!',
                'next_steps' => [
                    '1. Test login with: superadmin@golf.it / password123',
                    '2. IMPORTANT: Remove these setup routes from routes/setup.php',
                    '3. Change default passwords',
                    '4. Configure email settings in admin panel'
                ]
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    });
});
EOF

# Include setup routes in web.php
echo "" >> "$PRIVATE_DIR/routes/web.php"
echo "// Database setup routes - REMOVE AFTER INITIAL SETUP!" >> "$PRIVATE_DIR/routes/web.php"
echo "require __DIR__.'/setup.php';" >> "$PRIVATE_DIR/routes/web.php"

# Step 7: Create upload instructions
echo -e "${BLUE}📝 Creating upload instructions...${NC}"
cat > "$UPLOAD_DIR/UPLOAD_INSTRUCTIONS.txt" << EOF
🚀 GOLF SYSTEM - UPLOAD INSTRUCTIONS
====================================

1. 📁 UPLOAD STRUCTURE:
   - Upload contents of 'public_html/' folder to your hosting's public_html (or www, public_html, htdocs)
   - Upload contents of 'private_folder/' folder OUTSIDE of public_html (one level up)

2. 🗄️ DATABASE SETUP:
   - Create MySQL database in your hosting control panel
   - Note: database name, username, password
   - Edit private_folder/.env file with your database credentials

3. 🔧 CONFIGURATION:
   - Edit private_folder/.env:
     * DB_DATABASE=your_database_name
     * DB_USERNAME=your_database_user
     * DB_PASSWORD=your_database_password
     * MAIL_HOST=your_hosting_smtp
     * MAIL_USERNAME=your_email
     * MAIL_PASSWORD=your_email_password

4. 🗄️ INITIAL DATABASE SETUP:
   Visit these URLs in your browser (ONE TIME ONLY):

   a) $DOMAIN_URL/admin-setup/migrate
      (Creates database tables)

   b) $DOMAIN_URL/admin-setup/seed
      (Adds initial data)

   OR use the combined setup:
   c) $DOMAIN_URL/admin-setup/full-setup
      (Does both migrate + seed)

5. 🔐 FIRST LOGIN:
   - URL: $DOMAIN_URL/login
   - Email: superadmin@golf.it
   - Password: password123

   IMPORTANT: Change this password immediately!

6. 🔒 SECURITY (IMPORTANT!):
   After successful setup, REMOVE the setup routes:
   - Delete file: private_folder/routes/setup.php
   - Remove the require line from private_folder/routes/web.php

7. 📂 FOLDER PERMISSIONS:
   Set these permissions via hosting control panel:
   - private_folder/storage/ = 755 (recursive)
   - private_folder/bootstrap/cache/ = 755

8. ✅ VERIFY INSTALLATION:
   - Visit $DOMAIN_URL
   - Should see Golf System welcome page
   - Login should work
   - Admin panel should be accessible

HOSTING FOLDER STRUCTURE SHOULD LOOK LIKE:
/
├── public_html/          (or www/, htdocs/)
│   ├── index.php
│   ├── css/
│   └── js/
├── private_folder/       (Laravel application)
│   ├── app/
│   ├── config/
│   ├── .env
│   └── ...

Need help? Check Laravel documentation or contact support.
EOF

# Step 8: Create .htaccess for security
cat > "$PRIVATE_DIR/.htaccess" << 'EOF'
# Deny access to private folder
<Files "*">
    Order Allow,Deny
    Deny from all
</Files>
EOF

# Create compressed package
echo -e "${BLUE}📦 Creating compressed package...${NC}"
cd "$UPLOAD_DIR"
zip -r "../golf-system-upload.zip" . -x "*.DS_Store" "*.git*"
cd ..

# Final summary
echo
echo -e "${GREEN}🎉 UPLOAD PACKAGE READY!${NC}"
echo "=============================="
echo -e "📁 Package location: ${YELLOW}$(pwd)/golf-system-upload.zip${NC}"
echo -e "📋 Instructions: ${YELLOW}$(pwd)/$UPLOAD_DIR/UPLOAD_INSTRUCTIONS.txt${NC}"
echo
echo -e "${BLUE}Next steps:${NC}"
echo "1. 📤 Upload the zip file to your hosting"
echo "2. 📂 Extract and place files as per instructions"
echo "3. 🔧 Configure .env file with your hosting details"
echo "4. 🗄️ Run database setup via browser"
echo "5. 🔒 Remove setup routes for security"
echo
echo -e "${YELLOW}⚠️  Don't forget to update database credentials in .env!${NC}"
echo -e "${RED}🔒 IMPORTANT: Remove setup routes after initial setup!${NC}"
