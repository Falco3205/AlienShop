<?php
declare(strict_types=1);

namespace Alien\Controllers\Admin;

use Alien\Core\Request;
use Alien\Core\Response;
use Alien\Core\Settings;
use Alien\Services\Analytics;

final class AnalyticsController extends AdminController
{
    public function wizard(Request $req): Response
    {
        $connected = Analytics::id() !== '';
        $step = $req->int('step', $connected && !isset($req->query['step']) ? 0 : 1);
        $step = max(0, min(4, $step));

        if ($req->isPost()) {
            $action = $req->str('action');
            if ($action === 'disconnect') {
                Settings::set('analytics_id', '');
                return $this->back('admin/analytics', __('Google Analytics scollegato.'));
            }
            if ($action === 'id') {
                $id = strtoupper(preg_replace('/\s+/', '', $req->str('analytics_id')) ?? '');
                if (!Analytics::validId($id)) {
                    return $this->back('admin/analytics?step=2', __('L\'ID non è valido. Deve avere il formato G-XXXXXXXXXX (lo trovi in Google Analytics, nei dettagli del flusso di dati).'), 'error');
                }
                Settings::set('analytics_id', $id);
                return Response::redirect('admin/analytics?step=3');
            }
            if ($action === 'options') {
                Settings::set('analytics_ecommerce', $req->str('analytics_ecommerce') === '1' ? 1 : 0);
                Settings::set('analytics_consent', $req->str('analytics_consent') === '1' ? 1 : 0);
                return Response::redirect('admin/analytics?step=4');
            }
        }

        $check = $step === 4 ? Analytics::check() : null;
        return $this->view('analytics', [
            'title' => __('Google Analytics'),
            'subtitle' => __('Scopri da dove arrivano i visitatori e cosa comprano'),
            'step' => $step,
            'connected' => $connected,
            'id' => Analytics::id(),
            'check' => $check,
        ], 'analytics');
    }
}
