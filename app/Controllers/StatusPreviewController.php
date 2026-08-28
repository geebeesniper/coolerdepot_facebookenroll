<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;

class StatusPreviewController extends Controller
{
    public function index(): void
    {
        Auth::requireRole('admin');
        $this->render('admin/status_preview');
    }
}
