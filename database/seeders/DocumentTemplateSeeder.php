<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Documents\WrittenDocumentKind;
use App\Models\Documents\DocumentTemplate;
use Illuminate\Database\Seeder;

/**
 * Ready-made document templates (one or two per kind), shipped with the clinic in every environment. Staff start from
 * them, copy and change them, or write their own. Safe to run again: matched by name.
 */
final class DocumentTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->templates() as [$kind, $name, $description, $body]) {
            DocumentTemplate::query()->updateOrCreate(
                ['name' => $name, 'is_system' => true],
                ['kind' => $kind, 'description' => $description, 'body' => $body, 'is_active' => true],
            );
        }
    }

    /**
     * @return list<array{0: WrittenDocumentKind, 1: string, 2: string, 3: string}>
     */
    private function templates(): array
    {
        return [
            [WrittenDocumentKind::MedicalReport, 'Medical report', 'Summary of the visit, findings, diagnosis and plan.',
                '<h2>Medical report</h2><p>Patient <strong>{{patient_name}}</strong> (CPR {{patient_cpr}}, file no. {{patient_file_no}}), {{patient_age}}-year-old {{patient_sex}}, was seen at {{clinic_name}} on <strong>{{visit_date}}</strong>.</p>'
                .'<h3>Presenting complaint</h3><p>{{visit_reason}}</p><h3>Findings</h3><p><em>Write the examination findings here.</em></p>'
                .'<h3>Diagnosis</h3><p>{{diagnosis}}</p><h3>Plan</h3><ul><li><em>Treatment</em></li><li><em>Follow-up</em></li></ul>'],
            [WrittenDocumentKind::AttendanceCertificate, 'Attendance certificate', 'Confirms the patient attended a visit or treatment session (for work or school).',
                '<h2 style="text-align: center">Attendance certificate</h2><p>To whom it may concern,</p>'
                .'<p>This is to certify that <strong>{{patient_name}}</strong> (CPR {{patient_cpr}}) attended {{clinic_name}} on <strong>{{visit_date}}</strong> (<strong>{{visit_time}}</strong>).</p>'
                .'<p>This certificate is issued at the patient\'s request.</p>'],
            [WrittenDocumentKind::FitnessCertificate, 'Fitness to work', 'States the patient is fit to return to work or school.',
                '<h2 style="text-align: center">Medical fitness certificate</h2><p>To whom it may concern,</p>'
                .'<p>I examined <strong>{{patient_name}}</strong> (CPR {{patient_cpr}}) on {{visit_date}}. In my opinion the patient is <strong>fit to resume normal duties</strong> from <u>{{today}}</u>.</p>'
                .'<p><em>Restrictions, if any:</em> none.</p>'],
            [WrittenDocumentKind::FitnessCertificate, 'Fitness for sports', 'Clears the patient for sports or physical activity.',
                '<h2>Fitness for sports</h2><p><strong>{{patient_name}}</strong>, {{patient_age}} years, was examined on {{visit_date}} and shows no medical contra-indication to <strong>sports and physical activity</strong>.</p>'],
            [WrittenDocumentKind::ReferralNote, 'Referral note', 'Asks a colleague or another clinic to see the patient.',
                '<p>Dear colleague,</p><p>Thank you for seeing <strong>{{patient_name}}</strong>, {{patient_age}}-year-old {{patient_sex}} (CPR {{patient_cpr}}), whom I saw on {{visit_date}}.</p>'
                .'<p><strong>Reason for referral:</strong> {{visit_reason}}</p><p><strong>Working diagnosis:</strong> {{diagnosis}}</p>'
                .'<p>I would be grateful for your opinion and management.</p><p>Kind regards,<br>{{author_name}}</p>'],
            [WrittenDocumentKind::GeneralLetter, 'Blank letter', 'An empty letter on the clinic letterhead.',
                '<p>To whom it may concern,</p><p></p><p>Kind regards,<br>{{author_name}}<br>{{author_title}}</p>'],
        ];
    }
}
