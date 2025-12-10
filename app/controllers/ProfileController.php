<?php
// app/controllers/ProfileController.php
class ProfileController extends Controller
{
    protected $userModel;

    public function __construct()
    {
        $this->userModel = new Profile();
    }

    public function index()
    {
        session_start();
        $userId = $_SESSION['user_id'] ?? null;
        $user = $userId ? $this->userModel->findById($userId) : null;
        $this->view('profile/index', ['user' => $user]);
    }

    public function comments()
    {
        session_start();
        $userId = $_SESSION['user_id'] ?? null;
        $comments = $this->userModel->getComments($userId);
        $this->view('profile/comments', ['comments' => $comments]);
    }

    public function likes()
    {
        session_start();
        $userId = $_SESSION['user_id'] ?? null;
        $likes = $this->userModel->getLikes($userId);
        $this->view('profile/likes', ['likes' => $likes]);
    }

    public function posts()
    {
        session_start();
        $userId = $_SESSION['user_id'] ?? null;
        $posts = $this->userModel->getPosts($userId);
        $this->view('profile/posts', ['posts' => $posts]);
    }

    public function deleted_posts()
    {
        session_start();
        $userId = $_SESSION['user_id'] ?? null;
        $posts = $this->userModel->getDeletedPosts($userId);
        $this->view('profile/deleted_posts', ['posts' => $posts]);
    }
}
