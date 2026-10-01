<?php

namespace App\Http\Controllers;

use App\Models\JobPhoto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

// Every finished product photo across the jobs this user can see, for
// gathering portfolio and marketing material.
class PortfolioController extends Controller
{
    public function index(Request $request): View
    {
        $photos = $this->query($request)->with('job.customer')->paginate(48)->withQueryString();
        $years = JobPhoto::query()->selectRaw('created_at')->get()->map(fn ($p) => $p->created_at->year)->unique()->sortDesc()->values();

        return view('portfolio.index', [
            'photos' => $photos,
            'years' => $years,
            'departments' => collect(config('kretivco.departments'))->only($request->user()->seesAllDepartments() ? array_keys(config('kretivco.departments')) : $request->user()->visibleDepartments()),
        ]);
    }

    /** The filtered photos as one zip, for the design team. */
    public function download(Request $request): BinaryFileResponse
    {
        $photos = $this->query($request)->with('job')->limit(300)->get();
        abort_if($photos->isEmpty(), 404, 'No photos match these filters.');

        $zipPath = tempnam(sys_get_temp_dir(), 'portfolio');
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($photos as $photo) {
            $file = Storage::disk('public')->path($photo->path);
            if (is_file($file)) {
                $zip->addFile($file, "{$photo->job->job_id}/{$photo->id}.jpg");
            }
        }
        $zip->close();

        return response()->download($zipPath, 'Portfolio-'.now()->format('Ymd').'.zip')->deleteFileAfterSend();
    }

    /** @return Builder<JobPhoto> */
    private function query(Request $request): Builder
    {
        $user = $request->user();
        $query = JobPhoto::query()->latest()
            ->whereHas('job', function (Builder $q) use ($user, $request) {
                if (! $user->seesAllDepartments()) {
                    $q->whereIn('department', $user->visibleDepartments());
                }
                if (($dept = $request->query('department')) && array_key_exists($dept, config('kretivco.departments'))) {
                    $q->where('department', $dept);
                }
                if ($search = trim((string) $request->query('q'))) {
                    $q->where(fn ($w) => $w->where('job_id', 'like', "%{$search}%")->orWhere('job_type', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%")->orWhere('company', 'like', "%{$search}%")));
                }
            });

        if ($year = (int) $request->query('year')) {
            $query->whereBetween('created_at', [now()->setDate($year, 1, 1)->startOfDay(), now()->setDate($year, 12, 31)->endOfDay()]);
        }
        if ($request->boolean('marketing')) {
            $query->where('marketing_ok', true);
        }

        return $query;
    }
}
