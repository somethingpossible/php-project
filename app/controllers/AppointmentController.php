<?php
// app/controllers/AppointmentController.php
class AppointmentController extends Controller
{
    protected $appointmentModel;

    public function __construct()
    {
        $this->appointmentModel = new Appointment();
    }

    public function index()
    {
        $items = $this->appointmentModel->getAll();
        $this->view('appointment/index', ['items' => $items]);
    }

    public function book()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = $_POST['name'] ?? '';
            $phone = $_POST['phone'] ?? '';
            $this->appointmentModel->book($name, $phone);
            $this->redirect('/appointment');
        }
    }
}
