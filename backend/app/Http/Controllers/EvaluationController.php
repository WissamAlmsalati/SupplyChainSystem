<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EvaluationController extends Controller
{
    public function store(Request $request)
    {
        // ponytail: accept the flat payload the React frontend sends,
        // or the nested payload the Laravel backend was originally expecting.
        $data = $request->all();
        $evaluation = $data['evaluation'] ?? $data;
        $scores = $data['scores'] ?? [];

        if (isset($data['item_scores']) && is_array($data['item_scores'])) {
            foreach ($data['item_scores'] as $itemId => $score) {
                $scores[] = [
                    'template_item_id' => $itemId,
                    'score' => (int) $score,
                ];
            }
        }

        // Compute overall_score from the four category percentages if not provided.
        if (!isset($evaluation['overall_score']) && isset($evaluation['competency_score'])) {
            $evaluation['overall_score'] = round(
                ($evaluation['competency_score']
                 + $evaluation['hse_score']
                 + $evaluation['training_score']
                 + $evaluation['attitude_score']) / 4,
                1
            );
        }

        // Remove nested payload keys so the DB insert doesn't choke on unknown columns.
        unset($evaluation['item_scores'], $evaluation['scores']);

        $evalId = $evaluation['id'] ?? ('ev-' . time());
        $evaluation['id'] = $evalId;
        $evaluation['created_at'] = now();
        $evaluation['updated_at'] = now();

        DB::table('evaluations')->insert($evaluation);

        foreach ($scores as $scoreItem) {
            $scoreId = $scoreItem['id'] ?? ('es-' . uniqid());
            $scoreItem['id'] = $scoreId;
            $scoreItem['evaluation_id'] = $evalId;
            $scoreItem['created_at'] = now();
            $scoreItem['updated_at'] = now();
            DB::table('evaluation_scores')->insert($scoreItem);
        }

        return response()->json([
            'evaluation' => DB::table('evaluations')->where('id', $evalId)->first(),
            'scores' => DB::table('evaluation_scores')->where('evaluation_id', $evalId)->get()
        ], 201);
    }

    public function destroy($id)
    {
        DB::table('evaluations')->where('id', $id)->delete();
        DB::table('evaluation_scores')->where('evaluation_id', $id)->delete();

        return response()->json(['success' => true]);
    }
}
