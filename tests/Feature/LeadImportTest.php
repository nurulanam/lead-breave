<?php

use App\Enums\DataSource;
use App\Enums\LeadEventType;
use App\Enums\LeadSource;
use App\Enums\Role;
use App\Enums\SocialPlatform;
use App\Filament\Pages\ImportLeads;
use App\Jobs\ImportLeadsCsv;
use App\Models\Lead;
use App\Models\LeadImport;
use App\Services\Leads\CsvLeadReader;
use App\Services\Leads\LeadImporter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * @param  list<list<string>>  $rows
 */
function csvFile(array $rows, string $separator = ','): string
{
    $path = tempnam(sys_get_temp_dir(), 'leads').'.csv';
    $handle = fopen($path, 'w');
    fwrite($handle, "\xEF\xBB\xBF");

    foreach ($rows as $row) {
        fputcsv($handle, $row, $separator, '"', '');
    }

    fclose($handle);

    return $path;
}

/**
 * @return array<string, string|null>
 */
function standardMapping(): array
{
    return [...array_fill_keys(array_keys(CsvLeadReader::FIELDS), null), 'name' => 'Company', 'website' => 'Website', 'email' => 'E-mail', 'phone' => 'Phone', 'city' => 'City', 'country' => 'Country', 'facebook' => 'Facebook'];
}

it('guesses which column is which from common header names', function () {
    $mapping = app(CsvLeadReader::class)->guessMapping(['Company Name', 'URL', 'E-mail Address', 'Tel', 'Zip Code', 'Notes']);

    expect($mapping)->toMatchArray(['name' => 'Company Name', 'website' => 'URL', 'email' => 'E-mail Address', 'phone' => 'Tel', 'postal_code' => 'Zip Code', 'city' => null]);
});

it('reads semicolon-separated files with a byte-order mark', function () {
    $path = csvFile([['Company', 'City'], ['Corner Bakery', 'Austin']], ';');

    expect(app(CsvLeadReader::class)->headers($path))->toBe(['Company', 'City'])
        ->and(iterator_to_array(app(CsvLeadReader::class)->rows($path), false)[0]['values'])->toBe(['Company' => 'Corner Bakery', 'City' => 'Austin']);
});

it('previews new, known and invalid rows without writing anything', function () {
    Lead::factory()->create(['name' => 'Known Plumbing', 'website' => 'https://known.example']);
    $path = csvFile([
        ['Company', 'Website', 'E-mail', 'Phone', 'City', 'Country', 'Facebook'],
        ['New Bakery', 'newbakery.example', 'hi@newbakery.example', '', 'Austin', 'USA', ''],
        ['Known Plumbing LLC', 'www.known.example', '', '', 'Austin', '', ''],
        ['', 'nameless.example', '', '', '', '', ''],
        ['Bad Email Co', '', 'not-an-email', '', '', '', ''],
        ['New Bakery again', 'https://newbakery.example', '', '', '', '', ''],
    ]);

    $preview = app(LeadImporter::class)->preview($path, standardMapping(), 'US');

    expect($preview)->toMatchArray(['total' => 5, 'new' => 1, 'duplicate' => 2, 'invalid' => 2])
        ->and($preview['rows'][1]['detail'])->toBe('Matches Known Plumbing')
        ->and($preview['rows'][4]['detail'])->toBe('Repeats an earlier row in this file')
        ->and(Lead::count())->toBe(1);
});

it('imports in chunks, creating new leads and filling gaps on known ones', function () {
    Storage::fake('local');
    $known = Lead::factory()->create(['name' => 'Known Plumbing', 'website' => 'https://known.example', 'phone' => null, 'phone_e164' => null, 'city' => null]);

    $rows = [['Company', 'Website', 'E-mail', 'Phone', 'City', 'Country', 'Facebook']];
    $rows[] = ['Known Plumbing (from file)', 'known.example', 'office@known.example', '512-555-0100', 'Austin', 'US', 'https://facebook.com/known'];

    foreach (range(1, 599) as $n) {
        $rows[] = ["Business {$n}", "business{$n}.example", "hello@business{$n}.example", '', 'Austin', 'United States', ''];
    }

    $source = csvFile($rows);
    Storage::disk('local')->put('imports/test.csv', file_get_contents($source));
    $import = LeadImport::factory()->create(['file_path' => 'imports/test.csv', 'original_name' => 'leads.csv', 'mapping' => standardMapping(), 'total_rows' => 600]);

    ImportLeadsCsv::dispatchSync($import->id);

    $import->refresh();
    $known->refresh();

    expect($import->status)->toBe(LeadImport::STATUS_COMPLETED)
        ->and($import->processed_rows)->toBe(600)
        ->and($import->created_count)->toBe(599)
        ->and($import->updated_count)->toBe(1)
        ->and(Lead::where('source', LeadSource::Csv)->count())->toBe(599)
        ->and($known->name)->toBe('Known Plumbing')
        ->and($known->city)->toBe('Austin')
        ->and($known->phone_e164)->toBe('+15125550100')
        ->and($known->primaryEmail->email)->toBe('office@known.example')
        ->and($known->primaryEmail->source)->toBe(DataSource::Csv)
        ->and($known->socials()->sole()->platform)->toBe(SocialPlatform::Facebook)
        ->and($known->events()->where('type', LeadEventType::Imported)->sole()->data['fields'])->toContain('city', 'phone');
});

it('records why rows were skipped', function () {
    Storage::fake('local');
    Storage::disk('local')->put('imports/bad.csv', file_get_contents(csvFile([['Company', 'Website', 'E-mail'], ['', 'x.example', ''], ['Good Co', '', 'nope']])));
    $import = LeadImport::factory()->create(['file_path' => 'imports/bad.csv', 'mapping' => standardMapping()]);

    ImportLeadsCsv::dispatchSync($import->id);

    expect($import->fresh()->errors)->toBe([
        ['row' => 2, 'reason' => 'No business name'],
        ['row' => 3, 'reason' => 'The email address is not valid'],
    ]);
});

it('asks for a CSV before moving on', function () {
    $this->actingAs(userWithRole(Role::Researcher));

    Livewire::test(ImportLeads::class)
        ->goToNextWizardStep()
        ->assertHasErrors(['csv' => 'required']);
});

it('walks through upload, mapping and review, then queues the import', function () {
    Queue::fake();
    Storage::fake('local');
    $this->actingAs(userWithRole(Role::Researcher));
    $file = UploadedFile::fake()->createWithContent('my-leads.csv', file_get_contents(csvFile([
        ['Company', 'Website', 'E-mail'],
        ['Corner Bakery', 'cornerbakery.example', 'hi@cornerbakery.example'],
    ])));

    Livewire::test(ImportLeads::class)
        ->set('csv', $file)
        ->fillForm(['default_country' => 'US'])
        ->goToNextWizardStep()
        ->assertHasNoFormErrors()
        ->assertSet('headers', ['Company', 'Website', 'E-mail'])
        ->assertSchemaStateSet(['mapping.name' => 'Company', 'mapping.website' => 'Website', 'mapping.email' => 'E-mail'])
        ->goToNextWizardStep()
        ->assertHasNoFormErrors()
        ->assertSee('New leads')
        ->call('import')
        ->assertNotified('Import started');

    $import = LeadImport::sole();

    expect($import->original_name)->toBe('my-leads.csv')
        ->and($import->total_rows)->toBe(1);

    Storage::disk('local')->assertExists($import->file_path);
    Queue::assertPushed(ImportLeadsCsv::class, fn (ImportLeadsCsv $job) => $job->importId === $import->id);
});
