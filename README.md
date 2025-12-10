# PHP MVC restructure

This commit introduces a minimal MVC structure to the repository so the project is organized into:

- public/ - the document root (public/index.php)
- app/Controllers, app/Models, app/Views - application code and templates
- config/ - configuration (database path)
- data/ - sqlite database file (created at runtime)
- scripts/init_db.php - script to initialize the database

How to run locally:
1. Point your webserver document root to the `public/` directory. Or run the built-in PHP server:

   php -S localhost:8000 -t public

2. Initialize the database (optional, will be auto-created on first request):

   php scripts/init_db.php

3. Open http://localhost:8000/?route=home/index

Notes:
- I intentionally used a small, file-based SQLite database for portability. If you prefer MySQL, you can adapt config/Config::pdo() accordingly.
