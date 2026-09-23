<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\Response;
use App\Services\AuditLogService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function formResponses(Form $form, AuditLogService $audit): StreamedResponse
    {
        $audit->record('admin.export_csv', auth()->user(), $form, [
            'form_id' => $form->id,
        ]);

        $filename = 'form-'.$form->id.'-responses-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($form) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'response_id', 'choice', 'choice_label', 'coverage_weight', 'channel', 'phone', 'zalo_user_id',
                'school_external_id', 'school_name', 'class_name', 'class_form_status', 'voted_at',
            ]);

            Response::query()
                ->with(['classForm.classProfile.school'])
                ->where('status', 'valid')
                ->whereHas('classForm', fn ($q) => $q->where('form_id', $form->id))
                ->orderBy('id')
                ->chunk(500, function ($rows) use ($out, $form) {
                    foreach ($rows as $r) {
                        $school = $r->classForm?->classProfile?->school;
                        $choice = (string) $r->choice;
                        fputcsv($out, [
                            $r->id,
                            $choice,
                            $form->choiceLabel($choice),
                            $r->coverage_weight,
                            $r->channel,
                            $r->phone,
                            $r->zalo_user_id,
                            $school?->external_id,
                            $school?->name,
                            $r->classForm?->classProfile?->class_name,
                            $r->classForm?->status,
                            optional($r->created_at)?->format('Y-m-d H:i:s'),
                        ]);
                    }
                });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
