<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\AuditLogPresenter;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request, AuditLogPresenter $presenter): View
    {
        $category = $request->string('category')->toString();
        $search = trim($request->string('q')->toString());

        $q = AuditLog::query()
            ->with(['actor', 'entity'])
            ->latest('id');

        if ($category !== '') {
            $actions = $presenter->actionsForCategory($category);
            if ($actions !== []) {
                $q->whereIn('action', $actions);
            }
        }

        if ($search !== '') {
            $q->where(function ($inner) use ($search) {
                $inner->where('action', 'like', "%{$search}%")
                    ->orWhere('payload', 'like', "%{$search}%")
                    ->orWhereHasMorph('actor', ['App\Models\User'], function ($actor) use ($search) {
                        $actor->where('email', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    })
                    ->orWhereHasMorph('actor', ['App\Models\Teacher'], function ($actor) use ($search) {
                        $actor->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    })
                    ->orWhereHasMorph('entity', ['App\Models\Form'], function ($entity) use ($search) {
                        $entity->where('title', 'like', "%{$search}%");
                    });
            });
        }

        $logs = $q->paginate(30)->withQueryString();
        $categoryOptions = $presenter->actionOptions();

        return view('admin.audit.index', compact('logs', 'categoryOptions', 'category', 'search', 'presenter'));
    }
}
