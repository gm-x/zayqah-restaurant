<?php
// Loaded only by the homepage and carousel management pages.
function carousel_ready(): bool {
    try { q('SELECT id FROM homepage_slides WHERE 1=0'); return true; }
    catch (PDOException $ex) {
        if ($ex->getCode()==='42S02' || (cfg('db_driver')==='sqlite' && str_contains($ex->getMessage(),'no such table'))) return false;
        throw $ex;
    }
}
function install_carousel(): void {
    if (cfg('db_driver')==='mysql') {
        db()->exec("CREATE TABLE IF NOT EXISTS homepage_slides (
            id INTEGER NOT NULL AUTO_INCREMENT PRIMARY KEY,
            photo_id VARCHAR(48) NOT NULL,
            caption VARCHAR(160) NOT NULL DEFAULT '',
            alt_text VARCHAR(180) NOT NULL DEFAULT '',
            sort_order INTEGER NOT NULL DEFAULT 0,
            active INTEGER NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX homepage_slides_visibility (active,sort_order,id),
            CONSTRAINT fk_homepage_slides_photo FOREIGN KEY (photo_id) REFERENCES files(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } else {
        db()->exec("CREATE TABLE IF NOT EXISTS homepage_slides (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            photo_id VARCHAR(48) NOT NULL REFERENCES files(id),
            caption VARCHAR(160) NOT NULL DEFAULT '',
            alt_text VARCHAR(180) NOT NULL DEFAULT '',
            sort_order INTEGER NOT NULL DEFAULT 0,
            active INTEGER NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        )");
        db()->exec('CREATE INDEX IF NOT EXISTS homepage_slides_visibility ON homepage_slides(active,sort_order,id)');
    }
}
