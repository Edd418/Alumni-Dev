<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PlatformController extends Controller
{
    private function searchQuery(Request $request): string
    {
        return trim((string) $request->query('q', ''));
    }

    private function selectedDetails(Request $request): array
    {
        return collect($request->input('details', []))
            ->flatten()
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeSearchValues(array $values): Collection
    {
        return collect($values)
            ->flatMap(function ($value) {
                if ($value instanceof Collection) {
                    return $value->all();
                }

                if (is_array($value)) {
                    return $value;
                }

                return [$value];
            })
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->values();
    }

    private function matchesSearch(array $values, string $search, array $selectedDetails = []): bool
    {
        $tokens = $this->normalizeSearchValues($values);

        if ($search !== '' && ! Str::contains(Str::lower($tokens->implode(' ')), Str::lower($search))) {
            return false;
        }

        if ($selectedDetails === []) {
            return true;
        }

        $normalizedTokens = $tokens->map(fn ($value) => Str::lower($value))->all();

        foreach ($selectedDetails as $detail) {
            if (! in_array(Str::lower($detail), $normalizedTokens, true)) {
                return false;
            }
        }

        return true;
    }

    private function paginateFilteredCollection(Collection $items, int $perPage, Request $request, string $pageName): LengthAwarePaginator
    {
        $currentPage = max(1, (int) $request->query($pageName, 1));

        return new LengthAwarePaginator(
            $items->forPage($currentPage, $perPage)->values(),
            $items->count(),
            $perPage,
            $currentPage,
            [
                'path' => $request->url(),
                'pageName' => $pageName,
            ]
        )->withQueryString();
    }

    public function projects(Request $request): View
    {
        $user = $request->user();
        $search = $this->searchQuery($request);
        $selectedDetails = $this->selectedDetails($request);
        $allProjects = Project::with(['users.profile', 'collaborators', 'profile'])
            ->visibleTo($user)
            ->withCount('collaborators')
            ->latest()
            ->get();

        $projects = $allProjects
            ->filter(fn ($project) => $this->matchesSearch([
                $project->title,
                $project->description,
                $project->profile?->bio,
                $project->profile?->details ?? [],
                $project->users?->pluck('name') ?? [],
            ], $search, $selectedDetails))
            ->values();

        $userProjects = $user->projects()
            ->with(['collaborators', 'profile'])
            ->withCount('collaborators')
            ->orderByDesc('projects.created_at')
            ->get()
            ->filter(fn ($project) => $this->matchesSearch([
                $project->title,
                $project->description,
                $project->profile?->bio,
                $project->profile?->details ?? [],
                $project->collaborators?->pluck('name') ?? [],
            ], $search, $selectedDetails))
            ->values();

        $detailOptions = $allProjects
            ->flatMap(fn ($project) => $project->profile?->details ?? [])
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        return view('platform.section', [
            'section' => 'projects',
            'pageTitle' => 'Projects',
            'sectionKicker' => 'Collaboration',
            'projects' => $projects,
            'userProjects' => $userProjects,
            'searchQuery' => $search,
            'selectedDetails' => $selectedDetails,
            'detailOptions' => $detailOptions,
        ]);
    }
}
