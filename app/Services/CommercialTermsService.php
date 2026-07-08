<?php

namespace App\Services;

class CommercialTermsService
{
    public function applyControlledText(array $payload): array
    {
        foreach ([
            'certification_process_obligations',
            'vat_invoice_terms',
            'stage1_activity',
            'stage2_activity',
            'certificate_issuance',
            'surveillance_activity',
            'important_note',
        ] as $key) {
            if ($this->shouldUseOfficialText($key, (string) ($payload[$key] ?? ''))) {
                $payload[$key] = $this->text($key);
            }
        }

        if ($this->shouldUseOfficialText('contact_line', (string) ($payload['contact_line'] ?? ''))) {
            $payload['contact_line'] = 'QSI_CERT TEAM +966569009021 info@qsi-cert.com';
        }

        return $payload;
    }

    public function shouldUseOfficialText(string $key, string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return true;
        }

        $oldSystemPrefixes = [
            'certification_process_obligations' => [
                'QSI-Cert delivers certification services',
            ],
            'vat_invoice_terms' => [
                'VAT will be applied according to applicable regulations.',
            ],
            'stage1_activity' => [
                'Stage 1 verifies documentation',
                'Stage 1 focuses on reviewing documentation',
            ],
            'stage2_activity' => [
                'Stage 2 verifies implementation',
                'Stage 2 evaluates implementation',
            ],
            'certificate_issuance' => [
                'Certificate issue is subject',
                'A Certificate of Registration valid for three years',
            ],
            'surveillance_activity' => [
                'Surveillance audits verify continued conformity',
                'Surveillance audits review changes',
            ],
            'important_note' => [
                'By signing this agreement, the Client confirms acceptance',
            ],
            'contact_line' => [
                'QSI_CERT TEAM',
            ],
        ];

        foreach ($oldSystemPrefixes[$key] ?? [] as $prefix) {
            if (str_starts_with($value, $prefix)) {
                return true;
            }
        }

        return false;
    }

    public function text(string $key): string
    {
        return match ($key) {
            'certification_process_obligations' => "At QSI-Cert, we adhere to accreditation requirements and the applicable standards to ensure compliance within the scope of certification. Compliance is verified through regular follow-up audits, which are essential for maintaining the validity of the certification. The certificate's validity is retained only when follow-up audits are successfully completed.\n\nQSI-Cert ensures due diligence in training and managing its auditors, with a strong emphasis on confidentiality and privacy. Measures are in place to safeguard all data and documentation collected during audits. Experienced auditors with relevant expertise are appointed to evaluate critical facts and assess their significance and impact on the certified organization.\n\nThe certification process involves a thorough review of your management system documentation and an on-site audit conducted at your registered office and relevant premises. If compliance with all applicable requirements is confirmed during the audit, QSI-Cert will issue certificates of international validity for the defined certification period.\n\nCertified organizations will have the right to use QSI-Cert's certification logo on promotional materials and printed documents, as per the guidelines specified in Business Condition (F36).\n\nQSI-Cert is committed to upholding the highest standards in its certification services, ensuring trust, transparency, and professional integrity.\n\nThe certified client agrees to the following requirements in relation to Unannounced Visits conducted by IAS as per AC477 or SAAC requirements:\n\nAccess to Site and Records\nThe certified client shall permit IAS assessors full access to the facility, including all areas relevant to the certified scope, as well as access to the management system documentation and all associated records during unannounced visits.\n\nAvailability of Last Audit Report\nThe certified client shall maintain and make readily available a copy of the most recent audit report issued by the certification body for review by IAS assessors upon request.\n\nEvidence of Certification Process\nThe certified client shall maintain and provide demonstrable evidence of effective implementation of the certification process, including but not limited to management review records, internal audit reports, previous audit reports, corrective actions and closure of nonconformities, and any other records required to demonstrate ongoing compliance with certification requirements.",
            'vat_invoice_terms' => "In accordance with the country's VAT regulations, VAT (%) will be applicable and payable by any taxable person to whom the services are provided. The audit price is the final price, inclusive of VAT. The taxable person and their business registration number must be recognized and registered in the respective country. Invoices will be sent electronically via email.\n\nIf the client decides to discontinue the certification during the certification cycle, the balance amount for the remaining contract (cycle) must be paid prior to cancellation.",
            'stage1_activity' => "The Stage 1 audit focuses on reviewing and evaluating the organization's documentation, internal audit processes, and management review procedures. It also includes an assessment of the organization's location and site-specific conditions to determine its readiness for Stage 2. During this audit, QSI's audit team will assess the organization's understanding of the key requirements of the applicable standard, specifically regarding the performance of significant processes, objectives, and operations within the management system. The team will gather necessary information on the scope of the management system, processes, locations, and any related statutory and regulatory compliance requirements.",
            'stage2_activity' => "Upon successful completion of Stage 1, the Stage 2 audit will evaluate the implementation and effectiveness of the organization's management system. This audit will focus on gathering evidence to verify the organization's compliance with all requirements of the applicable management system standard. The performance monitoring system, including key objectives, targets, and the organization's compliance with legal and other applicable requirements, will also be reviewed. The Stage 2 audit must be completed within 90 days from the end date of Stage 1. If this period lapses, the Stage 1 audit will need to be repeated.",
            'certificate_issuance' => 'A Certificate of Registration with a validity of three (3) years will be issued upon the successful completion of both Stage 1 and Stage 2 audits.',
            'surveillance_activity' => "During surveillance audits, QSI's audit team will assess any changes to the organization's management system, internal audit consistency, management review procedures, achievement of objectives, operational control, and ongoing compliance with legal and other applicable requirements. The team will also review the organization's response to audit findings from previous audits and verify adherence to the rules for using certification marks.\n\nThe first surveillance audit following initial certification must occur within 12 months from the certification decision date unless otherwise required by the specific certification scheme.",
            'important_note' => "By signing this agreement, you confirm your acceptance of the terms and conditions outlined in the following annexures:\n- F_27 Annexure 01 - Certification Agreement\n- F_27 Annexure 02 - Rules for Certification\n\nAdditionally, by signing this offer, the Client commits to providing all necessary information for the certification process. Should any changes occur during the certification period - such as modifications to the number of employees, branch offices, or significant alterations in the scope of certification - this offer must be updated via an amendment to the contract.\n\nBy signing this offer, you also acknowledge and accept the Business Conditions for subsequent Follow-up/Surveillance audits of the certified management system, as detailed in the attached Business Conditions (F_27).\n\nFor any clarification regarding the terms of this offer, please feel free to contact us at:",
            default => '',
        };
    }
}
