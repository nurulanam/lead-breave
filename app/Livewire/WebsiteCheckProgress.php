<?php

namespace App\Livewire;

use App\Services\Website\CheckProgress;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Live progress for a lead's website check. Polls only while a check is
 * queued or running; when it finishes, tells the page to refresh so the new
 * status, emails and findings appear without a reload.
 */
class WebsiteCheckProgress extends Component
{
    #[Locked]
    public int $leadId;

    /** Whether this visitor saw the check running, so its result is worth showing. */
    public bool $watched = false;

    public bool $dismissed = false;

    public function mount(int $leadId): void
    {
        $this->leadId = $leadId;
        $this->watched = CheckProgress::isActive($leadId);
    }

    /**
     * Another component queued a check: start watching.
     */
    #[On('website-check-queued')]
    public function watch(): void
    {
        $this->watched = true;
        $this->dismissed = false;
    }

    /**
     * Called by the poll. Announces the moment the check finishes.
     */
    public function tick(): void
    {
        if ($this->watched && ! CheckProgress::isActive($this->leadId)) {
            $this->dispatch('lead-updated');
        }
    }

    public function dismiss(): void
    {
        $this->dismissed = true;
    }

    public function render(): View
    {
        $progress = CheckProgress::get($this->leadId);
        $active = in_array($progress['state'] ?? null, [CheckProgress::QUEUED, CheckProgress::RUNNING], true);

        if ($active) {
            $this->watched = true;
        }

        return view('livewire.website-check-progress', [
            'progress' => $progress,
            'active' => $active,
            'visible' => $progress !== null && ! $this->dismissed && ($active || $this->watched),
        ]);
    }
}
