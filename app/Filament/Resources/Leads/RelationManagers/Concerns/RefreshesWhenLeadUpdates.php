<?php

namespace App\Filament\Resources\Leads\RelationManagers\Concerns;

use Livewire\Attributes\On;

/**
 * Re-render the tab when the lead changes elsewhere on the page, such as a
 * website check finishing. The listener's round trip is the refresh: table
 * records are queried afresh on every request.
 */
trait RefreshesWhenLeadUpdates
{
    #[On('lead-updated')]
    public function refreshAfterLeadUpdate(): void {}
}
