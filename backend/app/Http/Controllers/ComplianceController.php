<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ComplianceController extends Controller
{
    protected function uploadsDir(): string
    {
        // ponytail: store uploads in the Vite dev server's public folder so the
        // frontend can reach them at /uploads/... without extra proxy config.
        $dir = base_path('../public/uploads');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }

    protected function handleFileUpload(array &$data): void
    {
        if (!empty($data['file_base64']) && !empty($data['file_name'])) {
            $cleanBase64 = preg_replace('/^data:.*?;base64,/', '', $data['file_base64']);
            $uniqueName = time() . '_' . preg_replace('/[^a-zA-Z0-9.\-_]/', '_', $data['file_name']);
            file_put_contents($this->uploadsDir() . '/' . $uniqueName, base64_decode($cleanBase64));
            $data['file_url'] = '/uploads/' . $uniqueName;
        }
        unset($data['file_base64']);
    }

    protected function getExpiryStatus(?string $expiryDateStr, int $reminderDays): string
    {
        if (!$expiryDateStr) {
            return 'valid';
        }
        $expiry = strtotime($expiryDateStr);
        $now = strtotime(date('Y-m-d'));
        $daysRemaining = ceil(($expiry - $now) / 86400);
        if ($daysRemaining < 0) {
            return 'expired';
        }
        if ($daysRemaining <= $reminderDays) {
            return 'expiring_soon';
        }
        return 'valid';
    }

    // Documents
    public function storeDocument(Request $request)
    {
        $data = $request->all();
        $id = $data['id'] ?? ('doc-' . time());
        $data['id'] = $id;
        $data['created_at'] = now();
        $data['updated_at'] = now();

        $this->handleFileUpload($data);

        $reminderDays = (int) ($data['reminder_days'] ?? 90);
        $data['reminder_days'] = $reminderDays;
        if (empty($data['status'])) {
            $data['status'] = $this->getExpiryStatus($data['expiry_date'] ?? null, $reminderDays);
        }

        DB::table('documents')->insert($data);

        return response()->json(DB::table('documents')->where('id', $id)->first(), 201);
    }

    public function destroyDocument($id)
    {
        DB::table('documents')->where('id', $id)->delete();
        return response()->json(['success' => true]);
    }

    // Certificates
    public function storeCertificate(Request $request)
    {
        $data = $request->all();
        $id = $data['id'] ?? ('cert-' . time());
        $data['id'] = $id;
        $data['created_at'] = now();
        $data['updated_at'] = now();

        $this->handleFileUpload($data);

        $certType = null;
        if (!empty($data['certificate_type_id'])) {
            $certType = DB::table('certificate_types')->where('id', $data['certificate_type_id'])->first();
        }
        $reminderDays = $certType->default_reminder_days ?? 90;
        if (empty($data['status'])) {
            $data['status'] = $this->getExpiryStatus($data['expiry_date'] ?? null, $reminderDays);
        }

        DB::table('certificates')->insert($data);

        return response()->json(DB::table('certificates')->where('id', $id)->first(), 201);
    }

    public function destroyCertificate($id)
    {
        DB::table('certificates')->where('id', $id)->delete();
        return response()->json(['success' => true]);
    }

    // Position Required Certificates
    public function storePositionRequiredCertificate(Request $request)
    {
        $positionId = $request->input('position_id');
        $certificateTypeId = $request->input('certificate_type_id');

        DB::table('position_required_certificates')->updateOrInsert(
            ['position_id' => $positionId, 'certificate_type_id' => $certificateTypeId],
            ['created_at' => now(), 'updated_at' => now()]
        );

        return response()->json(['success' => true]);
    }

    public function destroyPositionRequiredCertificate(Request $request)
    {
        $positionId = $request->input('position_id');
        $certificateTypeId = $request->input('certificate_type_id');

        DB::table('position_required_certificates')
            ->where('position_id', $positionId)
            ->where('certificate_type_id', $certificateTypeId)
            ->delete();

        return response()->json(['success' => true]);
    }
}
