<?php
require_once 'wp-load.php';

// Simulate Elementor Record
class FakeRecord {
    public function get($key) {
        if ($key === 'form_settings') {
            return [
                'form_name' => 'Table 2750' // Test regex pattern
            ];
        }
        if ($key === 'fields') {
            return [
                'absen' => ['value' => 'Hadir', 'title' => 'Absen'],
                'nama' => ['value' => 'Test User', 'title' => 'Nama'],
                'kelas' => ['value' => '10A', 'title' => 'Kelas'],
            ];
        }
    }
}

$record = new FakeRecord();
WTB_Elementor_Form_Integration::on_elementor_form_submit($record, null);
echo "Done";
