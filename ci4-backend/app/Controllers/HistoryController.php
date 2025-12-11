<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use App\Models\PrintJobModel;

class HistoryController extends Controller
{
    public function index()
    {
        $model = new PrintJobModel();
        
        $data = [
            'title' => 'Print History',
            'jobs' => $model->orderBy('uploaded_at', 'DESC')->findAll()
        ];
        
        return view('history', $data);
    }
}
