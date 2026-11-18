<?php
declare(strict_types=1);

namespace KodVadAll\\Core;

use PDO;
use RuntimeException;
use Throwable;

final class Migrator
{
    public static function run(PDO $pdo, string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
            migration VARCHAR(190) NOT NULL PRIMARY KEY,
            applied_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $locked = (int)$pdo->query("SELECT GET_LOCK('kodvadall_migrations', 10)")->fetchColumn();
        if ($locked !== 1) {
            throw new RuntimeException('Veritabanı güncelleme kilidi alınamadı.');
        }

        try {
            $files = glob(rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.sql') ?: [];
            sort($files, SORT_STRING);

            $check = $pdo->prepare('SELECT 1 FROM schema_migrations WHERE migration = ? LIMIT 1');
            $mark = $pdo->prepare('INSERT IGNORE INTO schema_migrations (migration, applied_at) VALUES (?, NOW())');

            foreach ($files as $file) {
                $name = basename($file);
                $check->execute([$name]);
                if ($check->fetchColumn()) {
                    continue;
                }

                $sql = file_get_contents($file);
                if ($sql === false) {
                    throw new RuntimeException('Migration dosyası okunamadı: ' . $name);
                }

                $statements = preg_split('/;\\s*(?:\\r?\\n|$)/', trim($sql)) ?: [];
                foreach ($statements as $statement) {
                    $statement = trim($statement);
                    if ($statement !== '') {
                        $pdo->exec($statement);
                    }
                }
                $mark->execute([$name]);
            }
        } finally {
            $pdo->query("SELECT RELEASE_LOCK('kodvadall_migrations')");
        }
    }
}
