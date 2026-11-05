<?php

namespace App\Http\Controllers\Business;

use App\Domain\Operations\Actions\RecordAudit;
use App\Domain\QuickPlay\Actions\StartQuickPlay;
use App\Domain\QuickPlay\Enums\QuickPlayDisplayMode;
use App\Domain\QuickPlay\Enums\QuickPlayScope;
use App\Domain\QuickPlay\Models\QuickPlay;
use App\Http\Controllers\Business\Concerns\AuthorizesBusiness;
use App\Http\Requests\Business\StartBusinessQuickPlayRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;

class QuickPlayController extends \App\Http\Controllers\Admin\QuickPlayController
{
    use AuthorizesBusiness;

    protected string $portal = 'business';

    protected function quickPlaysQuery(): Builder
    {
        return QuickPlay::query()->where('business_id', $this->businessId());
    }

    protected function mediaQuery(): Builder
    {
        return $this->businessMediaQuery();
    }

    protected function devicesQuery(): Builder
    {
        return parent::devicesQuery()->where('business_id', $this->businessId());
    }

    protected function businessesQuery(): Builder
    {
        return parent::businessesQuery()->whereKey($this->businessId());
    }

    protected function locationsQuery(): Builder
    {
        return parent::locationsQuery()->where('business_id', $this->businessId());
    }

    protected function options(): array
    {
        $options = parent::options();
        $mode = QuickPlayDisplayMode::Business;
        $options['displayModes'] = [['value' => $mode->value, 'label' => $mode->label(), 'description' => $mode->description()]];
        $options['scopes'] = [['value' => QuickPlayScope::Devices->value, 'label' => 'Mis pantallas']];
        $options['businesses'] = [];
        $options['locations'] = [];
        $options['counts']['businesses'] = 0;
        $options['counts']['locations'] = 0;

        return $options;
    }

    public function send(StartBusinessQuickPlayRequest $request, StartQuickPlay $start, RecordAudit $audit): RedirectResponse
    {
        $media = $this->mediaQuery()->ready()->findOrFail($request->integer('media_asset_id'));
        $quickPlay = $start->handle(
            $request->user(), $media,
            QuickPlayDisplayMode::from($request->validated('display_mode')),
            QuickPlayScope::from($request->validated('scope')),
            $request->filled('duration') ? $request->integer('duration') : null,
            $request->safe()->only(['device_ids']),
            $this->business(),
        );
        $audit->handle('business.quick_play.sent', $quickPlay, [], [
            'business_id' => $this->businessId(), 'targets' => $quickPlay->targets_count,
        ]);

        return redirect()->route('business.quick-play.show', $quickPlay)
            ->with('success', 'Reproducción inmediata enviada a '.$quickPlay->targets_count.' pantalla(s).');
    }
}
