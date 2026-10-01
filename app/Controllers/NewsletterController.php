<?php
declare(strict_types=1);

namespace Alien\Controllers;

use Alien\Core\Request;
use Alien\Core\Response;
use Alien\Services\Modules;
use Alien\Services\Newsletter;
use Alien\Services\Seo;

final class NewsletterController extends Controller
{
    private function message(string $title, string $text): Response
    {
        Seo::set(['title' => $title]);
        Seo::noindex();
        return $this->noStore($this->render('message', ['heading' => $title, 'text' => $text]));
    }

    public function subscribe(Request $req): Response
    {
        if (!Modules::on('newsletter')) {
            return $this->missing($req);
        }
        $error = trim((string)($req->post['website'] ?? '')) !== '' ? null : Newsletter::subscribe($req->str('email'), '', 'footer');
        $text = $error ?? __('Controlla la tua email: ti abbiamo inviato un link per confermare l\'iscrizione.');
        if ($req->isAjax()) {
            return Response::json(['ok' => $error === null, 'message' => $text], $error === null ? 200 : 422);
        }
        return $this->message($error === null ? __('Quasi fatto!') : __('Iscrizione non riuscita'), $text);
    }

    public function confirm(Request $req, array $params): Response
    {
        return Newsletter::confirm($params['token'])
            ? $this->message(__('Iscrizione confermata'), __('Grazie! Da ora riceverai le nostre novità.'))
            : $this->missing($req);
    }

    public function unsubscribe(Request $req, array $params): Response
    {
        return Newsletter::unsubscribe($params['token'])
            ? $this->message(__('Iscrizione annullata'), __('Non riceverai più la newsletter. Ci dispiace vederti andare.'))
            : $this->missing($req);
    }
}
