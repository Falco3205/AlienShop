<?php
declare(strict_types=1);

namespace Alien\Controllers\Admin;

use Alien\Core\Request;
use Alien\Core\Response;
use Alien\Services\Backup;

final class BackupController extends AdminController
{
    public function index(): Response
    {
        Backup::cleanup();
        return $this->view('backup', [
            'title' => __('Backup'),
            'subtitle' => __('Una copia di sicurezza del tuo negozio, quando vuoi'),
            'zip' => Backup::zipAvailable(),
            'last' => setting('backup_last'),
        ], 'backup');
    }

    public function download(Request $req): Response
    {
        @set_time_limit(300);
        try {
            if ($req->str('type') === 'full' && Backup::zipAvailable()) {
                $path = Backup::fullArchive();
                $name = 'alienshop-backup-' . date('Ymd-His') . '.zip';
                $type = 'application/zip';
            } else {
                [$name, $path] = Backup::databaseFile();
                \Alien\Core\Settings::set('backup_last', now());
                $type = 'application/octet-stream';
            }
        } catch (\Throwable $e) {
            return $this->back('admin/backup', __('Backup non riuscito: %s', $e->getMessage()), 'error');
        }
        $body = (string)file_get_contents($path);
        @unlink($path);
        return new Response($body, 200, ['Content-Type' => $type, 'Content-Disposition' => 'attachment; filename="' . $name . '"', 'Cache-Control' => 'private, no-store']);
    }
}
