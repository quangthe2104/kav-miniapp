<?php

namespace App\Console\Commands;

use App\Models\ClassForm;
use App\Models\Form;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CloseExpiredForms extends Command
{
    protected $signature = 'forms:close-expired';

    protected $description = 'Đóng các Form active đã quá ends_at và cascade đóng class_forms';

    public function handle(): int
    {
        $formIds = Form::query()
            ->where('status', 'active')
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', now())
            ->pluck('id');

        if ($formIds->isEmpty()) {
            $this->info('Closed 0 expired form(s); closed 0 class_form(s).');

            return self::SUCCESS;
        }

        [$formCount, $classFormCount] = DB::transaction(function () use ($formIds) {
            $forms = Form::query()
                ->whereIn('id', $formIds)
                ->where('status', 'active')
                ->update(['status' => 'closed']);

            $classForms = ClassForm::query()
                ->whereIn('form_id', $formIds)
                ->where('status', '!=', 'closed')
                ->update(['status' => 'closed']);

            return [$forms, $classForms];
        });

        $healed = ClassForm::query()
            ->where('status', '!=', 'closed')
            ->whereHas('form', fn ($q) => $q->where('status', '!=', 'active'))
            ->update(['status' => 'closed']);

        $this->info("Closed {$formCount} expired form(s); closed {$classFormCount} class_form(s); healed {$healed} stale class_form(s).");

        return self::SUCCESS;
    }
}
