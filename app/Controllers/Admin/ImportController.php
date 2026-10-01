<?php
declare(strict_types=1);

namespace Alien\Controllers\Admin;

use Alien\Core\Request;
use Alien\Core\Response;
use Alien\Import\Exporter;
use Alien\Import\ShopifyImporter;
use Alien\Import\WooImporter;

final class ImportController extends AdminController
{
    public function index(): Response
    {
        return $this->view('import/index', ['title' => __('Import / Export prodotti')], 'import');
    }

    public function import(Request $req): Response
    {
        $file = $_FILES['csv'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            return $this->back('admin/import', __('Carica un file CSV valido.'), 'error');
        }
        @set_time_limit(600);
        @ini_set('memory_limit', '512M');
        $download = $req->str('download_images') === '1';
        $update = $req->str('update_existing') === '1';
        $importer = $req->str('format') === 'shopify' ? new ShopifyImporter() : new WooImporter();
        try {
            $result = $importer->import($file['tmp_name'], $download, $update);
        } catch (\Throwable $e) {
            return $this->back('admin/import', $e->getMessage(), 'error');
        }
        if ($result->errors) {
            $_SESSION['import_errors'] = $result->errors;
        }
        return $this->back('admin/import', $result->summary(), $result->created + $result->updated > 0 ? 'success' : 'error');
    }

    public function export(Request $req, array $params): Response
    {
        $csv = match ($params['format']) {
            'woocommerce' => Exporter::woo(),
            'shopify' => Exporter::shopify(),
            default => null,
        };
        if ($csv === null) {
            return $this->back('admin/import', __('Formato non valido.'), 'error');
        }
        return Response::download($csv, 'products-' . $params['format'] . '-' . date('Ymd') . '.csv');
    }
}
