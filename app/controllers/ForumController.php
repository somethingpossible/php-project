<?php
// app/controllers/ForumController.php
class ForumController extends Controller
{
    protected $postModel;

    public function __construct()
    {
        $this->postModel = new Post();
    }

    public function index()
    {
        $posts = $this->postModel->getAll();
        $this->view('forum/index', ['posts' => $posts]);
    }

    public function post($id = null)
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $title = $_POST['title'] ?? '';
            $content = $_POST['content'] ?? '';
            $this->postModel->create($title, $content);
            $this->redirect('/forum');
            return;
        }
        $post = $id ? $this->postModel->find($id) : null;
        $this->view('forum/post', ['post' => $post]);
    }

    public function like($id)
    {
        $this->postModel->like($id);
        // if AJAX expected, return JSON
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            $this->json(['success' => true]);
        } else {
            $this->redirect('/forum');
        }
    }
}
