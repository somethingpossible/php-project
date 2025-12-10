<?php
namespace App\Controllers;

use App\Models\Note;

class HomeController
{
    public function index()
    {
        $notes = Note::all();
        view('home/index', ['notes' => $notes]);
    }

    public function new()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');
            if ($title === '') {
                flash('error', 'Title is required');
                redirect('/?route=home/new');
            }
            Note::create(['title' => $title, 'content' => $content]);
            flash('success', 'Note created');
            redirect('/?route=home/index');
        }

        view('home/new');
    }
}

?>
