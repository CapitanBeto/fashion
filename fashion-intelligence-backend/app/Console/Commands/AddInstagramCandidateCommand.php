<?php

namespace App\Console\Commands;

use App\Models\InstagramCandidateAccount;
use Illuminate\Console\Command;

class AddInstagramCandidateCommand extends Command
{
    protected $signature = 'instagram:add-candidate
        {username : Instagram handle, without @}
        {--via= : Username of the account that led to discovering this one}
        {--reason= : Why this account is of interest}
        {--source= : Where this was found (URL, article title) — never fabricated}';

    protected $description = 'Add a candidate Instagram account for manual review (found via web search, never via Instagram itself)';

    public function handle(): int
    {
        $username = ltrim(trim($this->argument('username')), '@');

        $account = InstagramCandidateAccount::updateOrCreate(
            ['username' => $username],
            [
                'discovered_via' => $this->option('via') ? ltrim($this->option('via'), '@') : null,
                'reason'         => $this->option('reason'),
                'source_note'    => $this->option('source'),
                'status'         => 'pending',
            ]
        );

        $this->info("Candidate '{$username}' added (id={$account->id}).");

        return self::SUCCESS;
    }
}
