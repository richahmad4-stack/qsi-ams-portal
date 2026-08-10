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

            $recordStatus = strtolower(trim((string) ($certificate['status'] ?? '')));
            $isExpired = ! empty($certificate['expiry_date']) && (string) $certificate['expiry_date'] < date('Y-m-d');
            $verificationStatus = $isExpired ? 'expired' : match ($recordStatus) {
                'active', 'valid', 'certified', 'issued' => 'valid',
                'suspended' => 'suspended',
                'withdrawn', 'cancelled' => 'withdrawn',
                'expired' => 'expired',
                default => 'not_valid',
            };
            $certificate['verification_status'] = $verificationStatus;
            $certificate['verification_message'] = match ($verificationStatus) {
                'valid' => 'This certificate is currently valid.',
                'suspended' => 'This certificate is suspended and must not be represented as valid.',
                'withdrawn' => 'This certificate has been withdrawn and is no longer valid.',
                'expired' => 'This certificate has expired and is no longer valid.',
                default => 'This record is not currently valid for certification claims.',
            };
        }

        return view('public/certificate_verify', [
            'title' => 'Certificate Verification',
            'certificate' => $certificate,
        ]);
    }
}
