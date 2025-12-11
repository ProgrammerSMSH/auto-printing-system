<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePrintJobsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'job_id' => [
                'type' => 'VARCHAR',
                'constraint' => '50',
                'null' => false,
            ],
            'filename' => [
                'type' => 'VARCHAR',
                'constraint' => '255',
                'null' => false,
            ],
            'filepath' => [
                'type' => 'VARCHAR',
                'constraint' => '500',
                'null' => false,
            ],
            'file_size' => [
                'type' => 'INT',
                'constraint' => 11,
                'null' => false,
            ],
            'paper_size' => [
                'type' => 'VARCHAR',
                'constraint' => '20',
                'default' => 'A4',
                'null' => false,
            ],
            'color_mode' => [
                'type' => 'ENUM',
                'constraint' => ['color', 'grayscale'],
                'default' => 'grayscale',
                'null' => false,
            ],
            'page_range' => [
                'type' => 'VARCHAR',
                'constraint' => '50',
                'default' => 'all',
                'null' => true,
            ],
            'copies' => [
                'type' => 'INT',
                'constraint' => 3,
                'default' => 1,
                'null' => false,
            ],
            'printer_name' => [
                'type' => 'VARCHAR',
                'constraint' => '100',
                'default' => 'default',
                'null' => true,
            ],
            'status' => [
                'type' => 'TINYINT',
                'constraint' => 1,
                'default' => 1,
                'comment' => '1=Pending, 2=Processing, 3=Printed',
                'null' => false,
            ],
            'qr_code' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'uploaded_at' => [
                'type' => 'DATETIME',
                'null' => false,
                'default' => 'CURRENT_TIMESTAMP',
            ],
            'processed_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'completed_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'error_message' => [
                'type' => 'TEXT',
                'null' => true,
            ],
        ]);
        
        $this->forge->addKey('id', true);
        $this->forge->addKey('job_id', false, true);
        $this->forge->addKey('status', false);
        $this->forge->createTable('print_jobs');
    }

    public function down()
    {
        $this->forge->dropTable('print_jobs');
    }
}
