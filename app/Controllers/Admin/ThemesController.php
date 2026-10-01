<?php
declare(strict_types=1);

namespace Alien\Controllers\Admin;

use Alien\Core\ImageProcessor;
use Alien\Core\Request;
use Alien\Core\Response;
use Alien\Core\Settings;
use Alien\Services\Themes;

final class ThemesController extends AdminController
{
    public function index(): Response
    {
        return $this->view('themes/index', [
            'title' => __('Temi'),
            'themes' => Themes::all(),
            'active' => \Alien\Core\View::theme(),
        ], 'themes');
    }

    public function activate(Request $req): Response
    {
        $ok = Themes::activate($req->str('theme'));
        return $this->back('admin/themes', $ok ? __('Tema attivato.') : __('Tema non valido.'), $ok ? 'success' : 'error');
    }

    public function customize(Request $req): Response
    {
        $text = ['hero_title', 'hero_subtitle', 'hero_cta', 'hero_link', 'announcement', 'custom_css'];
        foreach ($text as $k) {
            Settings::set($k, trim((string)($req->post[$k] ?? '')));
        }
        foreach (['theme_primary', 'theme_accent', 'theme_bg', 'theme_text'] as $k) {
            $v = (string)($req->post[$k] ?? '');
            Settings::set($k, !empty($req->post[$k . '_on']) && preg_match('/^#[0-9a-fA-F]{6}$/', $v) ? $v : '');
        }
        foreach (['logo', 'favicon', 'hero_image'] as $k) {
            if (!empty($req->post['remove_' . $k])) {
                ImageProcessor::delete((string)Settings::get($k));
                Settings::set($k, '');
            }
            if (!empty($_FILES[$k]['name'])) {
                $path = $k === 'favicon' && str_ends_with(strtolower($_FILES[$k]['name']), '.svg') ? null : ImageProcessor::fromUpload($_FILES[$k], 'branding');
                if ($path) {
                    Settings::set($k, $path);
                }
            }
        }
        Themes::build(\Alien\Core\View::theme());
        return $this->back('admin/themes', __('Personalizzazione salvata.'));
    }
}
