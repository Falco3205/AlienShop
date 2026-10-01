<?php
declare(strict_types=1);

namespace Alien\Controllers\Admin;

use Alien\Core\DB;
use Alien\Core\Request;
use Alien\Core\Response;
use Alien\Core\Settings;
use Alien\Services\Reviews;

final class ReviewsController extends AdminController
{
    public function index(Request $req): Response
    {
        $status = in_array($req->str('status'), ['pending', 'approved', 'rejected'], true) ? $req->str('status') : 'pending';
        $counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
        foreach (DB::all('SELECT status, COUNT(*) AS n FROM reviews GROUP BY status') as $r) {
            $counts[$r['status']] = (int)$r['n'];
        }
        return $this->view('reviews', [
            'title' => __('Recensioni'),
            'subtitle' => __('Approva o rifiuta quello che scrivono i clienti'),
            'status' => $status,
            'counts' => $counts,
            'rows' => DB::all('SELECT r.*, p.name AS product, p.slug FROM reviews r JOIN products p ON p.id = r.product_id WHERE r.status = ? ORDER BY r.id DESC LIMIT 100', [$status]),
        ], 'reviews');
    }

    public function action(Request $req, array $params): Response
    {
        $id = (int)$params['id'];
        $action = $req->str('action');
        if ($action === 'delete') {
            Reviews::delete($id);
        } else {
            Reviews::setStatus($id, $action === 'approve' ? 'approved' : 'rejected');
        }
        return $this->back('admin/reviews?status=' . $req->str('back', 'pending'));
    }

    public function settings(Request $req): Response
    {
        Settings::set('reviews_auto', $req->str('reviews_auto') === '1' ? 1 : 0);
        Settings::set('reviews_request_days', max(1, min(60, $req->int('reviews_request_days', 7))));
        return $this->back('admin/reviews', __('Impostazioni salvate.'));
    }
}
