<?php
declare(strict_types=1);

namespace Alien\Controllers\Admin;

use Alien\Core\DB;
use Alien\Core\Request;
use Alien\Core\Response;
use Alien\Core\Mailer;
use Alien\Services\Newsletter;

final class NewsletterController extends AdminController
{
    public function index(Request $req): Response
    {
        $status = in_array($req->str('status'), ['confirmed', 'pending', 'unsubscribed'], true) ? $req->str('status') : 'confirmed';
        return $this->view('newsletter', [
            'title' => __('Newsletter'),
            'subtitle' => __('Iscritti e campagne email'),
            'counts' => Newsletter::counts(),
            'status' => $status,
            'rows' => DB::all('SELECT * FROM subscribers WHERE status = ? ORDER BY id DESC LIMIT 200', [$status]),
            'queued' => (int)DB::val('SELECT COUNT(*) FROM mail_queue WHERE sent_at IS NULL'),
            'sent' => (int)DB::val('SELECT COUNT(*) FROM mail_queue WHERE sent_at IS NOT NULL'),
        ], 'newsletter');
    }

    public function send(Request $req): Response
    {
        $subject = $req->str('subject');
        $body = (string)($req->post['body'] ?? '');
        if ($subject === '' || trim(strip_tags($body)) === '') {
            return $this->back('admin/newsletter', __('Oggetto e testo sono obbligatori.'), 'error');
        }
        if ($req->str('test') !== '') {
            $ok = Mailer::send((string)\Alien\Core\Auth::user()['email'], '[TEST] ' . $subject, \Alien\Core\Str::sanitizeHtml($body));
            return $this->back('admin/newsletter', $ok ? __('Email di prova inviata.') : __('Invio non riuscito: controlla le impostazioni.'), $ok ? 'success' : 'error');
        }
        $n = Newsletter::campaign($subject, $body);
        return $this->back('admin/newsletter', __('Campagna messa in coda per %d iscritti: l\'invio avviene in automatico a gruppi.', $n));
    }

    public function export(): Response
    {
        return Response::download(Newsletter::csv(), 'newsletter-' . date('Ymd') . '.csv');
    }

    public function delete(Request $req, array $params): Response
    {
        DB::delete('subscribers', 'id = ?', [(int)$params['id']]);
        return $this->back('admin/newsletter', __('Iscritto eliminato.'));
    }
}
