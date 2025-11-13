# Library Management System (Backend)

A Laravel-based backend for managing branches, users, books, and branch-specific API access.

---

## Features

* Branch and user management
* Role-based access (`super_admin`, `admin`)
* Branch-specific public IP access control
* API endpoints for books, users, and branches

---

## Requirements

* PHP >= 8.1
* Composer
* MySQL or PostgreSQL
* Laravel 10+

---

## Quick Start

1. **Clone the repository**

```bash
git clone https://github.com/yourusername/library-management-backend.git
cd library-management-backend
```

2. **Install PHP dependencies**

```bash
composer install
```

3. **Copy `.env` file and configure database**

```bash
cp .env.example .env
```

Update `.env` with your database credentials:

```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=potnum
DB_DATABASE=library_db
DB_USERNAME=root
DB_PASSWORD=secret
```

4. **Run migrations and seeders**

```bash
php artisan migrate --seed
```

5. **Run the Laravel server**

```bash
php artisan serve
```

Default URL: [http://127.0.0.1:8000](http://127.0.0.1:8000)

---

## Accessing the Admin

Seeded super admin account:

| Role        | Email                                                   | Password    |
| ----------- | ------------------------------------------------------- | ----------- |
| Super Admin | [superadmin@example.com](mailto:superadmin@example.com) | password123 |

> Passwords must be hashed using `Hash::make()` in seeders or JSON import.

---

## Branch IP Access

The system restricts API access based on the branch's **public IP**:

* Only requests from allowed branch IPs can access API endpoints.
* Unauthorized IPs receive a `403 Access Denied`.

---

## API Endpoints

* `GET /api/books` - List all books
* `GET /api/users` - List all users
* `GET /api/branches` - List all branches
* `POST /api/print-id` - Generate printable ID card

> Ensure API requests come from allowed branch IPs.

---

## Notes

* For production, configure `.env` with correct database, cache, and mail settings.
* Use HTTPS to secure API requests.
* Optional: Connect with a Vue.js frontend for UI interaction.

---

## License

MIT License
