-- The test suite runs against its own database so that RefreshDatabase
-- never truncates the data you are working with locally.
CREATE DATABASE IF NOT EXISTS wtg_db_testing
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

GRANT ALL PRIVILEGES ON wtg_db_testing.* TO 'dbuser'@'%';
FLUSH PRIVILEGES;
