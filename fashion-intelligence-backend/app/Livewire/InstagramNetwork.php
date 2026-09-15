<?php

namespace App\Livewire;

use App\Models\InstagramCandidateAccount;
use Illuminate\Support\Facades\File;
use Livewire\Component;

class InstagramNetwork extends Component
{
    /** Absolute path to cuentas_aprobadas.txt, at the monorepo root. */
    protected function approvedFilePath(): string
    {
        return dirname(base_path()).DIRECTORY_SEPARATOR.'cuentas_aprobadas.txt';
    }

    public function approve(int $id): void
    {
        $account = InstagramCandidateAccount::findOrFail($id);
        $account->update(['status' => 'approved', 'approved_at' => now()]);
        $this->syncApprovedFile();
    }

    public function reject(int $id): void
    {
        $account = InstagramCandidateAccount::findOrFail($id);
        $account->update(['status' => 'rejected', 'approved_at' => null]);
        $this->syncApprovedFile();
    }

    public function unapprove(int $id): void
    {
        $account = InstagramCandidateAccount::findOrFail($id);
        $account->update(['status' => 'pending', 'approved_at' => null]);
        $this->syncApprovedFile();
    }

    public function approveAllPending(): void
    {
        $ids = InstagramCandidateAccount::where('status', 'pending')->pluck('id');
        InstagramCandidateAccount::whereIn('id', $ids)->update([
            'status' => 'approved',
            'approved_at' => now(),
        ]);
        $this->syncApprovedFile();
        $this->dispatch('notify', message: count($ids).' cuentas aprobadas.');
    }

    /**
     * Rewrites cuentas_aprobadas.txt from the current DB state (full
     * rewrite, not append) so the file never drifts out of sync or
     * duplicates entries across sessions.
     */
    protected function syncApprovedFile(): void
    {
        $approved = InstagramCandidateAccount::where('status', 'approved')
            ->orderBy('username')
            ->get(['username', 'discovered_via', 'reason']);

        $lines = $approved->map(function ($a) {
            $via = $a->discovered_via ? " (via @{$a->discovered_via})" : '';
            $reason = $a->reason ? " — {$a->reason}" : '';
            return "{$a->username}{$via}{$reason}";
        });

        File::put($this->approvedFilePath(), $lines->implode(PHP_EOL).PHP_EOL);
    }

    public function render()
    {
        $pending = InstagramCandidateAccount::where('status', 'pending')
            ->orderByDesc('created_at')
            ->get();

        $approved = InstagramCandidateAccount::where('status', 'approved')
            ->orderBy('username')
            ->get();

        $rejected = InstagramCandidateAccount::where('status', 'rejected')
            ->orderBy('username')
            ->get();

        return view('livewire.instagram-network', [
            'pending'  => $pending,
            'approved' => $approved,
            'rejected' => $rejected,
        ])->layout('layouts.app', ['title' => 'Instagram Network — Fashion Intelligence']);
    }
}
