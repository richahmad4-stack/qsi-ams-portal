<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use Config\Database;

class PublicCertificateController extends BaseController
{
    public function verify(string $slug)
    {
        $db = Database::connect();
        $certificate = $db->table('certificates')
            ->select('certificates.*, clients.company, clients.legal_name, standards.code AS standard_code, standards.name AS standard_name')
            ->join('clients', 'clients.id = certificates.client_id')
            ->join('standards', 'standards.id = certificates.standard_id')
            ->where('certificates.public_slug', $slug)
            ->get(1)
            ->getRowArray();

        if ($certificate !== null) {
            $db->table('certificate_public_events')->insert([
                'certificate_id' => (int) $certificate['id'],
                'search_term' => $slug,
                'ip_address' => $this->request->getIPAddress(),
                'user_agent' => substr((string) $this->request->getUserAgent(), 0, 500),
            ]);
        }

        return view('public/certificate_verify', [
            'title' => 'Certificate Verification',
            'certificate' => $certificate,
        ]);
    }
}
