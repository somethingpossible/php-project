<?php
// app/controllers/CheckController.php
class CheckController extends Controller
{
    public function phone()
    {
        $phone = $_GET['phone'] ?? '';
        // very simple check implementation
        $valid = preg_match('/^\+?[0-9\- ]{6,20}$/', $phone) === 1;
        $this->json(['valid' => $valid]);
    }
}
