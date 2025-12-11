<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;

class PrinterController extends ResourceController
{
    use ResponseTrait;

    public function list()
    {
        $printers = [
            [
                'name' => 'HP_LaserJet',
                'description' => 'HP LaserJet Pro M404n',
                'paper_sizes' => ['A4', 'Letter', 'Legal'],
                'color' => false,
                'status' => 'online'
            ],
            [
                'name' => 'EPSON_WF',
                'description' => 'Epson WorkForce Pro WF-4830',
                'paper_sizes' => ['A4', 'A3', 'Letter'],
                'color' => true,
                'status' => 'online'
            ],
            [
                'name' => 'Brother_HL',
                'description' => 'Brother HL-L2350DW',
                'paper_sizes' => ['A4', 'Letter'],
                'color' => false,
                'status' => 'offline'
            ]
        ];
        
        return $this->respond([
            'status' => 'success',
            'data' => $printers
        ]);
    }
}
