<?php
declare(strict_types=1);

namespace Alien\Controllers\Admin;

use Alien\Core\Request;
use Alien\Core\Response;
use Alien\Core\Settings;
use Alien\Services\LegalTemplates;

final class LegalController extends AdminController
{
    private const TEXT = ['company', 'legal_form', 'address', 'vat', 'tax_code', 'email', 'pec', 'phone', 'registry', 'ship_countries', 'hosting'];
    private const NUM = ['withdrawal_days', 'refund_days', 'ship_min', 'ship_max', 'warranty_months', 'retention_years'];

    public function wizard(Request $req): Response
    {
        $step = max(1, min(4, $req->int('step', 1)));
        $profile = LegalTemplates::profile();

        if ($req->isPost()) {
            $action = $req->str('action');
            if ($action === 'save') {
                $in = $req->post;
                foreach (self::TEXT as $k) {
                    if (array_key_exists($k, $in)) {
                        $profile[$k] = mb_substr(trim((string)$in[$k]), 0, 300);
                    }
                }
                foreach (self::NUM as $k) {
                    if (array_key_exists($k, $in)) {
                        $profile[$k] = max(0, min(120, (int)$in[$k]));
                    }
                }
                foreach (['sells_to', 'return_shipping'] as $k) {
                    if (isset($in[$k])) {
                        $profile[$k] = in_array($in[$k], ['consumers', 'both', 'customer', 'seller'], true) ? $in[$k] : $profile[$k];
                    }
                }
                foreach (['excluded_custom', 'newsletter'] as $k) {
                    if (array_key_exists($k, $in)) {
                        $profile[$k] = $in[$k] === '1' ? 1 : 0;
                    }
                }
                if ((int)$req->int('from') === 1 && ($profile['company'] === '' || !filter_var($profile['email'], FILTER_VALIDATE_EMAIL))) {
                    Settings::set('legal_profile', json_encode($profile, json_flags()));
                    return $this->back('admin/legal?step=1', __('Inserisci almeno la ragione sociale e un\'email valida.'), 'error');
                }
                Settings::set('legal_profile', json_encode($profile, json_flags()));
                if ($req->int('from') === 3) {
                    Settings::set('analytics_consent', $req->str('analytics_consent') === '0' ? 0 : 1);
                }
                return Response::redirect('admin/legal?step=' . min(4, $req->int('from') + 1));
            }
            if ($action === 'publish') {
                $slugs = array_values(array_intersect(array_keys(LegalTemplates::PAGES), (array)($req->post['pages'] ?? [])));
                $n = LegalTemplates::publish($slugs, $profile, (string)Settings::get('locale', 'it'));
                return $this->back('admin/legal?step=4&done=1', __('%d pagine pubblicate.', $n));
            }
        }

        $locale = (string)Settings::get('locale', 'it');
        return $this->view('legal', [
            'title' => __('Pagine legali'),
            'subtitle' => __('Privacy, cookie, termini, resi: generati in pochi minuti, gratis'),
            'step' => $step,
            'p' => $profile,
            'tools' => LegalTemplates::tools(),
            'preview' => $step === 4 ? LegalTemplates::build($profile, $locale) : [],
            'done' => $req->str('done') === '1',
            'existing' => array_column(\Alien\Core\DB::all("SELECT slug FROM pages WHERE type = 'page'"), 'slug'),
        ], 'legal');
    }
}
