<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class DocumentTemplateVersionSeeder extends Seeder
{
    public function run(): void
    {
        $templates = $this->db->table('document_templates')
            ->where('tenant_id', 1)
            ->get()
            ->getResultArray();

        foreach ($templates as $template) {
            $exists = $this->db->table('document_template_versions')
                ->where('document_template_id', (int) $template['id'])
                ->countAllResults();

            if ($exists > 0) {
                continue;
            }

            $body = '<h2>{{document_title}}</h2>'
                . '<p><strong>Client:</strong> {{client_name}}</p>'
                . '<p><strong>Scope:</strong> {{scope}}</p>'
                . '<p>This starter template is ready for replacement with the certification body approved format.</p>';

            $this->db->table('document_template_versions')->insert([
                'document_template_id' => (int) $template['id'],
                'version_number' => 1,
                'body_html' => $body,
                'header_html' => '<div>QSI AMS</div>',
                'footer_html' => '<div>Controlled document prepared by QSI AMS</div>',
                'created_by' => 1,
                'approved_by' => 1,
                'approved_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $this->db->table('document_templates')
                ->where('id', (int) $template['id'])
                ->update([
                    'active_version' => 1,
                    'status' => 'approved',
                ]);
        }
    }
}
