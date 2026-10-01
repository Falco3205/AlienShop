<?php
declare(strict_types=1);

namespace Alien\Controllers\Admin;

use Alien\Core\Request;
use Alien\Core\Response;
use Alien\Services\Accounting;

final class AccountingController extends AdminController
{
    private function range(Request $req): array
    {
        $type = in_array($req->str('p'), ['month', 'quarter', 'year'], true) ? $req->str('p') : 'month';
        $default = match ($type) {
            'year' => date('Y'),
            'quarter' => date('Y') . '-Q' . (int)ceil((int)date('n') / 3),
            default => date('Y-m'),
        };
        $value = $req->str('v', $default) ?: $default;
        [$from, $to, $label] = Accounting::period($type, $value);
        return [$type, $value, $from, $to, $label];
    }

    public function index(Request $req): Response
    {
        [$type, $value, $from, $to, $label] = $this->range($req);
        $tab = in_array($req->str('tab'), ['overview', 'sales', 'purchases', 'vat', 'deadlines', 'suppliers'], true) ? $req->str('tab') : 'overview';
        $data = ['type' => $type, 'value' => $value, 'from' => $from, 'to' => $to, 'label' => $label, 'tab' => $tab, 'o' => Accounting::overview($from, $to)];
        if ($tab === 'overview') {
            $data['monthly'] = Accounting::monthly((int)substr($from, 0, 4));
        } elseif ($tab === 'deadlines') {
            $data['dl'] = Accounting::deadlines();
        } elseif ($tab === 'suppliers') {
            $data['suppliers'] = Accounting::suppliers($from, $to);
        }
        return $this->view('accounting', $data + ['title' => __('Contabilità'), 'subtitle' => __('Vendite, acquisti e IVA in un colpo d\'occhio')], 'accounting');
    }

    public function export(Request $req, array $params): Response
    {
        [, , $from, $to] = $this->range($req);
        $kind = $params['kind'];
        if ($kind === 'xml') {
            $file = Accounting::xmlArchive($from, $to);
            if ($file === null) {
                return $this->back('admin/accounting', __('L\'estensione PHP "zip" non è attiva.'), 'error');
            }
            $body = (string)file_get_contents($file);
            @unlink($file);
            return new Response($body, 200, ['Content-Type' => 'application/zip', 'Content-Disposition' => 'attachment; filename="fatture-xml-' . $from . '_' . $to . '.zip"']);
        }
        if (!in_array($kind, ['vendite', 'acquisti', 'iva'], true)) {
            return $this->back('admin/accounting', __('Formato non valido.'), 'error');
        }
        return Response::download(Accounting::csv($kind, $from, $to), "registro-$kind-$from-$to.csv");
    }
}
