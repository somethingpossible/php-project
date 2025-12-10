<?php
class AuthController extends Controller
{
    protected $userModel;

    public function __construct()
    {
        $this->userModel = new User();
    }

    public function login()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';
            $user = $this->userModel->findByEmail($email);
            if ($user && password_verify($password, $user['password'])) {
                session_start();
                $_SESSION['user_id'] = $user['id'];
                $this->redirect('/profile');
            } else {
                $this->view('auth/login', ['error' => '登录失败，邮箱或密码错误', 'email' => $email]);
            }
            return;
        }
        $this->view('auth/login');
    }

    public function register()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';
            $name = $_POST['name'] ?? '';
            if ($this->userModel->findByEmail($email)) {
                $this->view('auth/register', ['error' => '邮箱已被注册']);
                return;
            }
            $this->userModel->create($name, $email, password_hash($password, PASSWORD_DEFAULT));
            $this->redirect('/login');
        }
        $this->view('auth/register');
    }

    public function logout()
    {
        session_start();
        session_destroy();
        $this->redirect('/login');
    }
}
