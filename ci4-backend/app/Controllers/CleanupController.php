<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use App\Models\PrintJobModel;

class CleanupController extends Controller
{
    public function index()
    {
        $model = new PrintJobModel();
        
        // Find completed jobs older than 7 days
        $cutoffDate = date('Y-m-d H:i:s', strtotime('-7 days'));
        $oldJobs = $model->where('status', 3)
                        ->where('completed_at <', $cutoffDate)
                        ->findAll();
        
        $deletedCount = 0;
        
        foreach ($oldJobs as $job) {
            // Delete file
            $filePath = WRITEPATH . $job['filepath'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            
            // Delete from database
            $model->delete($job['id']);
            $deletedCount++;
        }
        
        return $this->response->setJSON([
            'status' => 'success',
            'message' => "Cleaned up {$deletedCount} old print jobs"
        ]);
    }
}
