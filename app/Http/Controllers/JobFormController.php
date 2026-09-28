<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Job;
use App\Models\JobForm;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

// Department forms on a job (config/job_forms.php): Creative Brief for
// KretivBrand, UAT / Go-Live Sign-off for KretivTech, Run Sheet for
// KretivEvent. Filled in over time, printed as a PDF for the client.
class JobFormController extends Controller
{
    public function edit(Job $job, string $key): View
    {
        $this->authorize('view', $job);
        $definition = $this->definition($job, $key);

        return view('jobs.forms.edit', [
            'job' => $job, 'key' => $key, 'def' => $definition,
            'form' => JobForm::where('job_id', $job->id)->where('form_key', $key)->first(),
        ]);
    }

    public function update(Request $request, Job $job, string $key): RedirectResponse
    {
        $this->authorize('update', $job);
        $definition = $this->definition($job, $key);
        $input = (array) $request->input('data', []);

        // Keep only the fields the form defines, trimmed to sensible lengths.
        $data = [];
        foreach ($definition['sections'] as $fields) {
            foreach ($fields as $name => $field) {
                $value = $input[$name] ?? null;
                $data[$name] = match ($field['type']) {
                    'checklist' => array_values(array_intersect($field['items'], (array) $value)),
                    'rows' => collect((array) $value)->map(fn ($row) => collect($field['columns'])->mapWithKeys(fn ($c, $i) => [$i => mb_substr(trim((string) ($row[$i] ?? '')), 0, 300)])->all())
                        ->filter(fn ($row) => implode('', $row) !== '')->values()->take(100)->all(),
                    'select' => in_array($value, $field['options'], true) ? $value : null,
                    default => mb_substr(trim((string) $value), 0, $field['type'] === 'textarea' ? 4000 : 300),
                };
            }
        }

        JobForm::updateOrCreate(['job_id' => $job->id, 'form_key' => $key], ['data' => $data, 'updated_by' => $request->user()->name]);
        ActivityLog::create([
            'job_id' => $job->id, 'job_code' => $job->job_id, 'user_id' => $request->user()->id, 'user_name' => $request->user()->name,
            'action' => 'edited', 'detail' => "updated the {$definition['label']}",
        ]);

        return $request->boolean('then_pdf')
            ? redirect()->route('jobs.forms.pdf', [$job, $key])
            : back()->with('success', "{$definition['label']} saved.");
    }

    public function pdf(Request $request, Job $job, string $key): Response
    {
        $this->authorize('view', $job);
        $definition = $this->definition($job, $key);
        $form = JobForm::where('job_id', $job->id)->where('form_key', $key)->first();
        $name = str_replace(' ', '_', preg_replace('/[^A-Za-z ]/', '', $definition['label'])).'_'.$job->job_id.'.pdf';

        return response(Pdf::loadView('jobs.forms.pdf', [
            'job' => $job, 'def' => $definition, 'data' => $form?->data ?? [], 'form' => $form, 'by' => $request->user()->shortName(),
        ])->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$name.'"',
        ]);
    }

    private function definition(Job $job, string $key): array
    {
        $definition = JobForm::definition($key);
        abort_unless($definition, 404);

        return $definition;
    }
}
