<?php

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Jobs\ImportLeadsCsv;
use App\Models\LeadImport;
use App\Services\Leads\CsvLeadReader;
use App\Services\Leads\LeadImporter;
use App\Settings\GeneralSettings;
use App\Support\Countries;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\View;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;
use UnitEnum;

/**
 * Upload a CSV, map its columns, review a dry run, then import in the background.
 *
 * @property-read Schema $form
 */
class ImportLeads extends Page
{
    protected string $view = 'filament.pages.import-leads';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static string|UnitEnum|null $navigationGroup = 'Prospecting';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Import CSV';

    protected static ?string $title = 'Import leads from a CSV';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /**
     * The uploaded CSV. Uploaded with Livewire directly rather than through a
     * form field, and validated when leaving the first step.
     */
    public ?TemporaryUploadedFile $csv = null;

    /** @var list<string> */
    public array $headers = [];

    /** @var array{total: int, new: int, duplicate: int, invalid: int, rows: list<array{line: int, name: string|null, outcome: string, detail: string}>}|null */
    public ?array $preview = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Permission::EditLeads->value) ?? false;
    }

    public function getSubheading(): string
    {
        return 'Bring in a list from a spreadsheet or another tool. Businesses you already have are updated, not duplicated.';
    }

    public function mount(): void
    {
        $this->form->fill(['default_country' => app(GeneralSettings::class)->default_country]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Wizard::make([
                    Step::make('Upload')
                        ->description('A CSV with a header row')
                        ->icon(Heroicon::OutlinedDocumentArrowUp)
                        ->schema([
                            View::make('filament.leads.import-dropzone'),
                            Select::make('default_country')
                                ->label('Country for rows without one')
                                ->options(Countries::options())
                                ->searchable()
                                ->required(),
                        ])
                        ->afterValidation(fn () => $this->readHeaders()),
                    Step::make('Match columns')
                        ->description('Tell us what each column holds')
                        ->icon(Heroicon::OutlinedArrowsRightLeft)
                        ->schema(fn (): array => [
                            Grid::make(['default' => 1, 'md' => 2])->schema(array_map(
                                fn (string $field, array $definition): Select => Select::make("mapping.{$field}")
                                    ->label($definition['label'])
                                    ->options(array_combine($this->headers, $this->headers) ?: [])
                                    ->placeholder('Not in this file')
                                    ->required($field === 'name'),
                                array_keys(CsvLeadReader::FIELDS),
                                CsvLeadReader::FIELDS,
                            )),
                        ])
                        ->afterValidation(fn () => $this->runPreview()),
                    Step::make('Review')
                        ->description('Check before importing')
                        ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                        ->schema([
                            View::make('filament.leads.import-preview')
                                ->viewData(fn (): array => ['preview' => $this->preview]),
                        ]),
                ])
                    ->submitAction(new HtmlString(view('filament.leads.import-submit')->render())),
            ]);
    }

    public function import(): void
    {
        $data = $this->form->getState();
        $file = $this->csv;

        if ($file === null || $this->preview === null) {
            return;
        }

        $path = $file->storeAs('imports', Str::uuid().'.csv', 'local');

        $import = LeadImport::query()->create([
            'user_id' => auth()->id(),
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mapping' => $data['mapping'],
            'default_country' => $data['default_country'],
            'total_rows' => $this->preview['total'],
        ]);

        ImportLeadsCsv::dispatch($import->id);

        Notification::make()
            ->title('Import started')
            ->body(number_format($this->preview['total']).' rows are being imported in the background. Progress shows below.')
            ->success()
            ->send();

        $this->reset('headers', 'preview', 'csv');
        $this->form->fill(['default_country' => $data['default_country']]);
    }

    /**
     * @return Collection<int, LeadImport>
     */
    public function recentImports(): Collection
    {
        return LeadImport::query()->with('user')->latest()->limit(10)->get();
    }

    public function hasRunningImport(): bool
    {
        return LeadImport::query()->whereIn('status', [LeadImport::STATUS_PENDING, LeadImport::STATUS_RUNNING])->exists();
    }

    private function readHeaders(): void
    {
        $this->validate(
            ['csv' => ['required', 'file', 'mimes:csv,txt', 'max:10240']],
            [
                'csv.required' => 'Choose a CSV file to import.',
                'csv.mimes' => 'That is not a CSV file. In Excel or Google Sheets, use "Download as CSV".',
                'csv.max' => 'The file is larger than 10 MB. Split it into smaller files.',
            ],
        );

        $file = $this->csv;

        try {
            $this->headers = $file ? app(CsvLeadReader::class)->headers($file->getRealPath()) : [];
        } catch (Throwable) {
            $this->headers = [];
        }

        if (array_filter($this->headers) === []) {
            Notification::make()->title('That file has no header row')->body('The first row should name each column, such as "Business name" and "Website".')->danger()->send();

            throw new Halt;
        }

        $this->data['mapping'] = app(CsvLeadReader::class)->guessMapping($this->headers);
    }

    private function runPreview(): void
    {
        $file = $this->csv;

        if ($file === null) {
            throw new Halt;
        }

        $this->preview = app(LeadImporter::class)->preview(
            $file->getRealPath(),
            $this->data['mapping'] ?? [],
            (string) ($this->data['default_country'] ?? 'US'),
        );

        if ($this->preview['total'] === 0) {
            Notification::make()->title('That file has no rows under its header')->danger()->send();

            throw new Halt;
        }
    }
}
