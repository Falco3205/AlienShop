<?php
declare(strict_types=1);

function a_input(string $name, string $label, mixed $value = '', string $type = 'text', array $attrs = [], string $help = ''): string
{
    $a = '';
    foreach ($attrs as $k => $v) {
        $a .= ' ' . $k . ($v === true ? '' : '="' . e($v) . '"');
    }
    return '<div class="field"><label for="f_' . e($name) . '">' . e($label) . '</label><input id="f_' . e($name) . '" type="' . e($type) . '" name="' . e($name) . '" value="' . e($value) . '"' . $a . '>'
        . ($help !== '' ? '<div class="help">' . $help . '</div>' : '') . '</div>';
}

function a_textarea(string $name, string $label, mixed $value = '', int $rows = 4, string $help = '', bool $editor = false): string
{
    return '<div class="field"><label for="f_' . e($name) . '">' . e($label) . '</label><textarea id="f_' . e($name) . '" name="' . e($name) . '" rows="' . $rows . '"' . ($editor ? ' data-editor' : '') . '>' . e($value) . '</textarea>'
        . ($help !== '' ? '<div class="help">' . $help . '</div>' : '') . '</div>';
}

function a_select(string $name, string $label, array $options, mixed $value = '', string $help = ''): string
{
    $o = '';
    foreach ($options as $k => $l) {
        $o .= '<option value="' . e($k) . '"' . ((string)$k === (string)$value ? ' selected' : '') . '>' . e($l) . '</option>';
    }
    return '<div class="field"><label for="f_' . e($name) . '">' . e($label) . '</label><select id="f_' . e($name) . '" name="' . e($name) . '">' . $o . '</select>'
        . ($help !== '' ? '<div class="help">' . $help . '</div>' : '') . '</div>';
}

function a_check(string $name, string $label, mixed $value = false): string
{
    return '<div class="field"><label class="check"><input type="hidden" name="' . e($name) . '" value="0"><input type="checkbox" name="' . e($name) . '" value="1"' . ($value ? ' checked' : '') . '> ' . e($label) . '</label></div>';
}

function a_status(string $status): string
{
    $cls = ['paid' => 'ok', 'completed' => 'ok', 'active' => 'ok', 'processing' => 'info', 'shipped' => 'info', 'pending' => 'warn', 'unpaid' => 'warn', 'draft' => 'warn', 'cancelled' => 'bad', 'failed' => 'bad', 'refunded' => 'bad'];
    return '<span class="pill ' . ($cls[$status] ?? '') . '">' . e(__(\Alien\Services\Orders::STATUSES[$status] ?? \Alien\Services\Orders::PAYMENT_STATUSES[$status] ?? ucfirst($status))) . '</span>';
}

function a_dt(?string $d): string
{
    return $d ? date('d/m/Y H:i', strtotime($d)) : '';
}
