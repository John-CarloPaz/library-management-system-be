 # 📚 Library Management System (Backend)

 ### A Laravel 10 backend for managing library branches, users, acquisitions, procurements, and catalogue data with role-based access control.

## 🚀 Installation Guide (Recommended Order)

### This guide assumes a fresh machine or new developer setup.
Follow the steps exactly in order.

1. Install Laravel Dependencies (Before Cloning the Project)

Make sure your machine has the required software installed.

Install PHP (>= 8.1)

Download from: https://www.php.net/downloads

Check version:

```node
php -v
```

1.1 Install Composer
Download: https://getcomposer.org/download/

Check version:

```node
composer -V
```

1.2 Install Node.js & npm

Download from: https://nodejs.org

Check versions:

```node
node -v
npm -v
```

1.3 Install PostgreSQL

Download:
https://www.postgresql.org/download/

Verify installation:

```node 
psql --version
```

2. Clone the Repository

After dependencies are installed:

```git
git clone https://github.com/yourusername/library-management-backend.git
cd library-management-backend
```

3. Install Backend (PHP) Dependencies
composer install

4. Install Frontend (Node) Dependencies

If the project uses Vite, Vue, Tailwind, or other assets:

```php
 npm install
```

5. Copy Environment File
```node
cp .env.example .env
```

6. Configure Environment (.env)

Find the database section and update it:

```php
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=library_db
DB_USERNAME=postgres
DB_PASSWORD=yourpassword
```

Make sure this exact database exists in PostgreSQL:

CREATE DATABASE library_db;

7. Run Database Migrations & Seeders

This creates tables and inserts the initial super admin user.

```node 
php artisan migrate --seed
```

🔑 Default Seeded Account
Role	Email	Password
Super Admin	superadmin@example.com
	password123
    
8. Run the Laravel Server
```node
php artisan serve
```

App will run at:

🔗 http://127.0.0.1:8000

Optional: Run Frontend Dev Server (If Using Vite)
npm run dev

## 🌐 Branch IP Access Control

The backend restricts API access based on a branch's public IP.

Allowed IPs → Access granted

Unknown IPs → 403 Access Denied

Ensure the requesting device/server uses the correct public IP.

## 🔌 API Endpoints (Examples)
Method	Endpoint	Description
// Refer to the Postman Collection 

## 📝 Notes
Ensure .env is correctly configured before running migrations.
Always use HTTPS in production.
PostgreSQL is required—MySQL is not supported unless configured manually.
Works well with Vue.js, React, or any frontend framework.

## 📄 License
MIT License © 2025
