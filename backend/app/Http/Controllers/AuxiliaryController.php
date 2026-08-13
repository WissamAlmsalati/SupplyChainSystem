<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class AuxiliaryController extends Controller
{
    // Equipment Assignments
    public function storeEquipment(Request $request)
    {
        $data = $request->all();
        $id = $data['id'] ?? ('eq-' . time());
        $data['id'] = $id;
        $data['created_at'] = now();
        $data['updated_at'] = now();

        DB::table('equipment_assignments')->insert($data);

        return response()->json(DB::table('equipment_assignments')->where('id', $id)->first(), 201);
    }

    public function updateEquipment(Request $request, $id)
    {
        $data = $request->all();
        $data['updated_at'] = now();

        DB::table('equipment_assignments')->where('id', $id)->update($data);

        return response()->json(DB::table('equipment_assignments')->where('id', $id)->first());
    }

    public function destroyEquipment($id)
    {
        DB::table('equipment_assignments')->where('id', $id)->delete();
        return response()->json(['success' => true]);
    }

    // Notes
    public function storeNote(Request $request)
    {
        $data = $request->all();
        $id = $data['id'] ?? ('not-' . time());
        $data['id'] = $id;
        $data['created_at'] = now();
        $data['updated_at'] = now();

        DB::table('employee_notes')->insert($data);

        return response()->json(DB::table('employee_notes')->where('id', $id)->first(), 201);
    }

    public function destroyNote($id)
    {
        DB::table('employee_notes')->where('id', $id)->delete();
        return response()->json(['success' => true]);
    }

    // Attachments
    public function storeAttachment(Request $request)
    {
        $data = $request->all();
        $id = $data['id'] ?? ('att-' . time());
        $data['id'] = $id;
        $data['created_at'] = now();
        $data['updated_at'] = now();

        // ponytail: mirror the Express file-upload behaviour for the Vite dev setup.
        if (!empty($data['file_base64']) && !empty($data['file_name'])) {
            $dir = base_path('../public/uploads');
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $cleanBase64 = preg_replace('/^data:.*?;base64,/', '', $data['file_base64']);
            $uniqueName = time() . '_' . preg_replace('/[^a-zA-Z0-9.\-_]/', '_', $data['file_name']);
            file_put_contents($dir . '/' . $uniqueName, base64_decode($cleanBase64));
            $data['file_url'] = '/uploads/' . $uniqueName;
        }
        unset($data['file_base64'], $data['file_name']);

        DB::table('employee_attachments')->insert($data);

        return response()->json(DB::table('employee_attachments')->where('id', $id)->first(), 201);
    }

    public function destroyAttachment($id)
    {
        DB::table('employee_attachments')->where('id', $id)->delete();
        return response()->json(['success' => true]);
    }

    // AI Diagnostics & Recommendations
    public function aiScan()
    {
        // Re-run diagnostics scanner on MySQL tables
        $employees = DB::table('employees')->get();
        $documents = DB::table('documents')->get();
        $certificates = DB::table('certificates')->get();
        $posRequiredCerts = DB::table('position_required_certificates')->get();
        $certTypes = DB::table('certificate_types')->get()->keyBy('id');

        $now = now();
        $recommendations = [];

        foreach ($employees as $emp) {
            if ($emp->passport_expiry_date) {
                $exp = \Carbon\Carbon::parse($emp->passport_expiry_date);
                $diffDays = $now->diffInDays($exp, false);

                if ($diffDays < 0) {
                    $recommendations[] = [
                        'id' => "rec-p-exp-{$emp->id}",
                        'employee_id' => $emp->id,
                        'category' => 'expiring_cert',
                        'reason' => "Passport for {$emp->first_name} {$emp->last_name} expired on {$emp->passport_expiry_date}.",
                        'impact' => 'HIGH: Employee cannot travel internationally or access oilfield sites requiring passport identification.',
                        'suggested_action' => 'Initiate passport renewal sequence with HR government relations officer.',
                        'status' => 'new',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
        }

        foreach ($recommendations as $rec) {
            DB::table('ai_recommendations')->updateOrInsert(
                ['id' => $rec['id']],
                $rec
            );
        }

        return response()->json(DB::table('ai_recommendations')->get());
    }

    public function updateAiRecommendationStatus(Request $request, $id)
    {
        $status = $request->input('status', 'applied');
        DB::table('ai_recommendations')->where('id', $id)->update([
            'status' => $status,
            'resolved_at' => $status === 'applied' ? now() : null,
            'updated_at' => now()
        ]);

        return response()->json(DB::table('ai_recommendations')->where('id', $id)->first());
    }

    // AI Chat proxying to Google Gemini API
    public function aiChat(Request $request)
    {
        $message = $request->input('message');
        $apiKey = env('GEMINI_API_KEY');

        if (!$apiKey) {
            return response()->json([
                'reply' => "Gemini API key is not configured on the backend server. Please set GEMINI_API_KEY in .env."
            ]);
        }

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json'
            ])->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$apiKey}", [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => "You are an intelligent AI assistant for TAQA Workforce Operations Management System. User query: " . $message]
                        ]
                    ]
                ]
            ]);

            if ($response->successful()) {
                $result = $response->json();
                $text = $result['candidates'][0]['content']['parts'][0]['text'] ?? "I could not process your query.";
                return response()->json(['reply' => $text]);
            }
        } catch (\Exception $e) {
            return response()->json(['reply' => "Error communicating with Gemini AI: " . $e->getMessage()]);
        }

        return response()->json(['reply' => "AI Service temporarily unavailable."]);
    }
}
