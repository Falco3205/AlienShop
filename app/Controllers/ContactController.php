<?php
declare(strict_types=1);

namespace Alien\Controllers;

use Alien\Core\Mailer;
use Alien\Core\RateLimit;
use Alien\Core\Request;
use Alien\Core\Response;
use Alien\Core\Session;
use Alien\Services\Orders;
use Alien\Services\Seo;

final class ContactController extends Controller
{
    public function form(Request $req): Response
    {
        Seo::set(['title' => __('Contatti')]);
        return $this->noStore($this->render('contact', ['sent' => isset($req->query['sent']), 'errors' => $this->errors()]));
    }

    public function send(Request $req): Response
    {
        if ($this->csrfFails($req)) {
            return $this->missing($req);
        }
        Session::start();
        $name = mb_substr($req->str('name'), 0, 120);
        $email = mb_strtolower($req->str('email'));
        $message = mb_substr($req->str('message'), 0, 4000);
        $spam = trim((string)($req->post['website'] ?? '')) !== '';
        $error = null;
        if (!$spam) {
            if ($name === '' || $message === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = __('Compila nome, email valida e messaggio.');
            } elseif (!RateLimit::hit('contact', 5, 3600)) {
                $error = __('Troppi messaggi inviati. Riprova più tardi.');
            }
        }
        if ($error) {
            $_SESSION['_errors'] = [$error];
            $_SESSION['_old'] = ['name' => $name, 'email' => $email, 'message' => $message];
            return Response::redirect('contact');
        }
        if (!$spam) {
            $to = (string)setting('store_email', '');
            if ($to !== '') {
                $body = '<p><strong>' . e($name) . '</strong> &lt;' . e($email) . '&gt;</p><p>' . nl2br(e($message)) . '</p>';
                Mailer::send($to, sprintf(__('Messaggio dal sito da %s'), $name), $body, [], $email);
            }
        }
        return Response::redirect('contact?sent=1');
    }

    private function errors(): array
    {
        Session::start();
        $errors = $_SESSION['_errors'] ?? [];
        unset($_SESSION['_errors']);
        return $errors;
    }

    public function trackForm(Request $req): Response
    {
        Seo::set(['title' => __('Traccia il tuo ordine')]);
        Seo::noindex();
        return $this->noStore($this->render('track', ['errors' => $this->errors()]));
    }

    public function track(Request $req): Response
    {
        if ($this->csrfFails($req)) {
            return $this->missing($req);
        }
        Session::start();
        $error = null;
        if (!RateLimit::hit('track', 10, 900)) {
            $error = __('Troppi tentativi. Riprova tra qualche minuto.');
        } else {
            $number = trim($req->str('number'));
            $order = $number !== '' ? Orders::findByNumber($number) : null;
            if ($order && mb_strtolower($order['email']) === mb_strtolower($req->str('email'))) {
                return Response::redirect('checkout/thank-you/' . $order['token']);
            }
            $error = __('Non abbiamo trovato nessun ordine con questi dati.');
        }
        $_SESSION['_errors'] = [$error];
        $_SESSION['_old'] = ['number' => $req->str('number'), 'email' => $req->str('email')];
        return Response::redirect('track');
    }
}
