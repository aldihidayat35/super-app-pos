<?php

namespace App\Support;

use Illuminate\Contracts\Session\Session;

final readonly class FlashNotifier
{
    public function __construct(private Session $session) {}

    public function success(string $message): void
    {
        $this->flash('success', $message);
    }

    public function error(string $message): void
    {
        $this->flash('danger', $message);
    }

    public function warning(string $message): void
    {
        $this->flash('warning', $message);
    }

    private function flash(string $type, string $message): void
    {
        $this->session->flash('notification', compact('type', 'message'));
    }
}
