<?php namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class User extends Migration {

    public function up() {
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => TRUE,
                'auto_increment' => TRUE,
            ],
            'username' => [
                'type' => 'VARCHAR',
                'constraint' => '100',
            ],
            'password' => [
                'type' => 'VARCHAR',
                'constraint' => '255', // Tambahkan constraint
            ],
            'salt' => [
                'type' => 'VARCHAR',
                'constraint' => '255', // Tambahkan constraint
            ],
            'avatar' => [
                'type' => 'TEXT',
                'null' => TRUE, // Null harus huruf kecil
            ],
            'role' => [
                'type' => 'VARCHAR', // TEXT tidak boleh ada constraint, ganti ke VARCHAR
                'constraint' => 1,
                'default' => 1,
            ],
            'created_by' => [
                'type' => 'INT',
                'constraint' => 11,
            ],
            'created_date' => [
                'type' => 'DATETIME',
            ],
            'updated_by' => [
                'type' => 'INT',
                'constraint' => 11,
                'null' => TRUE,
            ],
            'updated_date' => [
                'type' => 'DATETIME',
                'null' => TRUE,
            ]
        ]);

        $this->forge->addKey('id', TRUE);
        $this->forge->createTable('user');
    }

    public function down() {
        $this->forge->dropTable('user');
    }
}

?>
