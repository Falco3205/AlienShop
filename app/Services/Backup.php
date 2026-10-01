<?php
declare(strict_types=1);

namespace Alien\Services;

use Alien\Core\Config;
use Alien\Core\DB;
use Alien\Core\Settings;

final class Backup
{
    public static function zipAvailable(): bool
    {
        return class_exists(\ZipArchive::class);
    }

    private static function tmpDir(): string
    {
        $dir = ROOT . '/storage/tmp';
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        return $dir;
    }

    public static function databaseFile(): array
    {
        if (DB::driver() === 'sqlite') {
            $tmp = self::tmpDir() . '/db-' . bin2hex(random_bytes(4)) . '.sqlite';
            DB::pdo()->exec("VACUUM INTO '" . str_replace("'", "''", $tmp) . "'");
            return ['alienshop-' . date('Ymd-His') . '.sqlite', $tmp];
        }
        $tmp = self::tmpDir() . '/db-' . bin2hex(random_bytes(4)) . '.sql';
        $fh = fopen($tmp, 'wb');
        $pdo = DB::pdo();
        fwrite($fh, "SET FOREIGN_KEY_CHECKS=0;\nSET NAMES utf8mb4;\n");
        foreach ($pdo->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN) as $table) {
            $create = $pdo->query('SHOW CREATE TABLE `' . $table . '`')->fetch(\PDO::FETCH_NUM);
            fwrite($fh, "DROP TABLE IF EXISTS `$table`;\n" . $create[1] . ";\n");
            $st = $pdo->query('SELECT * FROM `' . $table . '`');
            $batch = [];
            while ($row = $st->fetch(\PDO::FETCH_ASSOC)) {
                $batch[] = '(' . implode(',', array_map(static fn($v) => $v === null ? 'NULL' : $pdo->quote((string)$v), $row)) . ')';
                if (count($batch) >= 200) {
                    fwrite($fh, "INSERT INTO `$table` VALUES " . implode(',', $batch) . ";\n");
                    $batch = [];
                }
            }
            if ($batch) {
                fwrite($fh, "INSERT INTO `$table` VALUES " . implode(',', $batch) . ";\n");
            }
        }
        fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($fh);
        return ['alienshop-' . date('Ymd-His') . '.sql', $tmp];
    }

    public static function fullArchive(): string
    {
        [$dbName, $dbTmp] = self::databaseFile();
        $zipPath = self::tmpDir() . '/backup-' . bin2hex(random_bytes(4)) . '.zip';
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            @unlink($dbTmp);
            throw new \RuntimeException('Impossibile creare l\'archivio.');
        }
        $zip->addFile($dbTmp, 'database/' . $dbName);
        if (is_file(Config::file())) {
            $zip->addFile(Config::file(), 'config/config.php');
        }
        $base = ROOT . '/public/uploads';
        if (is_dir($base)) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if ($file->isFile() && $file->getFilename() !== '.htaccess') {
                    $zip->addFile($file->getPathname(), 'uploads/' . ltrim(substr($file->getPathname(), strlen($base)), '/\\'));
                }
            }
        }
        $zip->close();
        @unlink($dbTmp);
        Settings::set('backup_last', now());
        return $zipPath;
    }

    public static function restore(string $file): string
    {
        if (preg_match('/\.zip$/i', $file)) {
            $zip = new \ZipArchive();
            if ($zip->open($file) !== true) {
                throw new \RuntimeException('Archivio non valido.');
            }
            $dir = self::tmpDir() . '/restore-' . bin2hex(random_bytes(4));
            $zip->extractTo($dir);
            $zip->close();
            $db = glob($dir . '/database/*') ?: [];
            if (!$db) {
                throw new \RuntimeException('Database non trovato nell\'archivio.');
            }
            self::restoreDatabase($db[0]);
            if (is_dir($dir . '/uploads')) {
                self::copyDir($dir . '/uploads', ROOT . '/public/uploads');
            }
            self::removeDir($dir);
            return 'Database e immagini ripristinati.';
        }
        self::restoreDatabase($file);
        return 'Database ripristinato.';
    }

    private static function restoreDatabase(string $file): void
    {
        if (str_ends_with($file, '.sqlite')) {
            $target = (string)Config::get('db.path', ROOT . '/storage/db/alienshop.sqlite');
            if (DB::driver() !== 'sqlite') {
                throw new \RuntimeException('Questo backup è per SQLite.');
            }
            copy($file, $target);
            return;
        }
        $pdo = DB::pdo();
        $buffer = '';
        foreach (new \SplFileObject($file) as $line) {
            $buffer .= $line;
            if (preg_match('/;\s*$/', $buffer)) {
                $pdo->exec($buffer);
                $buffer = '';
            }
        }
    }

    private static function copyDir(string $from, string $to): void
    {
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($from, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST) as $f) {
            $dest = $to . substr($f->getPathname(), strlen($from));
            if ($f->isDir()) {
                @mkdir($dest, 0755, true);
            } else {
                @mkdir(dirname($dest), 0755, true);
                copy($f->getPathname(), $dest);
            }
        }
    }

    private static function removeDir(string $dir): void
    {
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }

    public static function cleanup(): void
    {
        foreach (glob(self::tmpDir() . '/*') ?: [] as $f) {
            if (is_file($f) && filemtime($f) < time() - 3600) {
                @unlink($f);
            }
        }
    }
}
