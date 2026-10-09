# Scholarship Management System (PHP + MySQL)

A simple full-stack app for maintaining Academic Years, Scholarship Programs,
and Requirements, with a login/signup page gating access. Built with plain
PHP and MySQL — no frameworks, no build step — so it drops straight into
XAMPP.

## Setup with XAMPP

1. Copy the whole `scholarship-php` folder into your XAMPP `htdocs` folder,
   e.g. `C:\xampp\htdocs\scholarship-php` (Windows) or
   `/Applications/XAMPP/htdocs/scholarship-php` (Mac).
2. Start **Apache** and **MySQL** from the XAMPP Control Panel.
3. Open `http://localhost/phpmyadmin`, click **Import**, and import
   `schema.sql`. This creates the `scholarship_db` database and its four
   tables (`users`, `academic_years`, `scholarship_programs`, `requirements`).
4. Open `http://localhost/scholarship-php/` in your browser. You'll land on
   the login page — click "Create one" to sign up, then you're in.

If your MySQL root user has a password set (most fresh XAMPP installs don't),
update `config/db.php`:
```php
define('DB_PASS', 'your_password_here');
```

## Project structure

```
schema.sql                 Run this once in phpMyAdmin to create the database
config/
  db.php                    Database connection settings (edit if needed)
includes/
  auth_check.php            Redirects to login.php if no session exists
  header.php / footer.php   Shared layout, nav tabs, flash messages
index.php                  Redirects to login or dashboard based on session
register.php / login.php / logout.php   Account pages
academic_year.php           Module 1: Academic Year (add/edit/delete)
scholarship_program.php     Module 2: Scholarship Program (linked to a year)
requirements.php            Module 3: Requirements (linked to a program)
css/style.css               All styling
```

## How it works

Each module page follows the same pattern:
- **GET** with no params — shows the add form and a table of existing records
- **GET `?edit=ID`** — pre-fills the form with that record for editing
- **GET `?delete=ID`** — deletes that record (after a JS confirm popup)
- **POST** — the form submits back to the same page, which inserts or
  updates the record, then redirects back (so refreshing the page never
  resubmits the form)

All database queries use **prepared statements** (`mysqli::prepare` +
`bind_param`) to prevent SQL injection. Passwords are hashed with
`password_hash()` / verified with `password_verify()` — never stored in
plain text.

## How the data links together

- A **Scholarship Program** must be attached to an existing **Academic Year**.
- A **Requirement** must be attached to an existing **Scholarship Program**.
- Deleting an Academic Year or Program that still has dependents attached is
  blocked with a clear message, so you don't end up with orphaned records.

## Merging this into your own login/signup pages

If you already have your own login/signup PHP pages:

1. Keep (or copy) the `users` table from `schema.sql`, or point at your
   existing users table — just make sure your login code sets
   `$_SESSION['user_id']` and `$_SESSION['full_name']` on success. That's the
   only thing `includes/auth_check.php` checks.
2. Drop `includes/auth_check.php` at the top of `academic_year.php`,
   `scholarship_program.php`, and `requirements.php` (it's already there) —
   no changes needed to those three files.
3. Copy `academic_years`, `scholarship_programs`, and `requirements` table
   definitions from `schema.sql` into your existing database.

## Notes on going further

- This uses PHP's default session handling, which is fine for a single
  server. If you deploy across multiple servers later, you'd want a shared
  session store.
- Consider adding CSRF tokens to the forms before deploying this publicly —
  it's a common next step once you're past the school-project stage.
