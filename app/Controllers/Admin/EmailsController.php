<?php
declare(strict_types=1);

namespace Alien\Controllers\Admin;

use Alien\Core\Auth;
use Alien\Core\Mailer;
use Alien\Core\Request;
use Alien\Core\Response;
use Alien\Core\Settings;
use Alien\Services\EmailTemplates;

final class EmailsController extends AdminController
{
    public function index(): Response
    {
        $rows = [];
        foreach (EmailTemplates::definitions() as $id => $def) {
            $t = EmailTemplates::template($id);
            $rows[$id] = $def + ['enabled' => EmailTemplates::enabled($id), 'customized' => $t['custom_subject'] !== '' || $t['custom_body'] !== ''];
        }
        return $this->view('emails/index', [
            'title' => __('Email ai clienti'),
            'subtitle' => __('Personalizza i messaggi automatici del tuo negozio'),
            'rows' => $rows,
            'color' => EmailTemplates::color(),
            'footer' => (string)Settings::get('mail_footer', ''),
        ], 'emails');
    }

    public function design(Request $req): Response
    {
        $color = $req->str('mail_color');
        Settings::set('mail_color', preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : '');
        Settings::set('mail_footer', mb_substr(trim((string)($req->post['mail_footer'] ?? '')), 0, 500));
        return $this->back('admin/emails', __('Aspetto delle email salvato.'));
    }

    public function edit(Request $req, array $params): Response
    {
        $id = (string)$params['id'];
        $t = EmailTemplates::template($id);
        if (!$t) {
            return $this->back('admin/emails', __('Email non trovata.'), 'error');
        }
        if ($req->isPost()) {
            EmailTemplates::save($id, $req->str('subject'), (string)($req->post['body'] ?? ''), $req->str('enabled') === '1');
            return $this->back('admin/emails/' . $id, __('Email salvata.'));
        }
        return $this->view('emails/edit', [
            'title' => $t['label'],
            'subtitle' => $t['hint'],
            'id' => $id,
            't' => $t,
            'enabled' => EmailTemplates::enabled($id),
            'help' => EmailTemplates::variableHelp(),
        ], 'emails');
    }

    public function reset(Request $req, array $params): Response
    {
        EmailTemplates::reset((string)$params['id']);
        return $this->back('admin/emails/' . $params['id'], __('Ripristinato il testo originale.'));
    }

    public function preview(Request $req, array $params): Response
    {
        $id = (string)$params['id'];
        $r = EmailTemplates::render($id, EmailTemplates::sampleVars($id), ['subject' => $req->str('subject'), 'body' => (string)($req->post['body'] ?? '')]);
        if (!$r) {
            return new Response('', 404);
        }
        return new Response($r[1], 200, ['Content-Type' => 'text/html; charset=utf-8', 'Cache-Control' => 'private, no-store', 'X-Robots-Tag' => 'noindex']);
    }

    public function test(Request $req, array $params): Response
    {
        $id = (string)$params['id'];
        $r = EmailTemplates::render($id, EmailTemplates::sampleVars($id), ['subject' => $req->str('subject'), 'body' => (string)($req->post['body'] ?? '')]);
        $to = (string)(Auth::user()['email'] ?? '');
        $ok = $r && $to !== '' && Mailer::send($to, '[TEST] ' . $r[0], $r[1]);
        return $this->back('admin/emails/' . $id, $ok ? __('Email di prova inviata a %s.', $to) : __('Invio non riuscito: controlla le impostazioni.'), $ok ? 'success' : 'error');
    }
}
