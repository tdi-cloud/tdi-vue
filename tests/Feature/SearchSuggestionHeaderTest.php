<?php

use App\Http\Middleware\AddSearchSuggestionHeader;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();

    Employee::forceCreate([
        'EMPCODE' => 'EMP-SSH-01', 'OFFICE/DIVISION' => 'Test Division', 'LASTNAME' => 'Bautista', 'FIRSTNAME' => 'Corazon',
        'MI' => 'D', 'POSITION' => 'HRMO', 'SG' => '10', 'PLANTILLA STATUS' => 'Permanent', 'SEX' => 'F',
        'REGION' => 'CO', 'OFFICE' => 'Test Office', 'LOCATION' => 'Main', 'SECTION' => 'Test', 'UNIT' => 'Test',
    ]);

    $this->actingAs(User::factory()->create(['empcode' => 'EMP-SSH-ADMIN', 'access' => 'admin']));
});

test('a type-ahead lookup with no matches suggests the closest employee name in a header', function () {
    $this->getJson(route('employees.search-signatory', ['q' => 'Bautsta']))
        ->assertOk()
        ->assertExactJson([])
        ->assertHeader(AddSearchSuggestionHeader::HEADER, 'bautista');
});

test('a type-ahead lookup that finds people adds no suggestion and keeps its response shape', function () {
    $response = $this->getJson(route('employees.search-signatory', ['q' => 'Bautista']))
        ->assertOk()
        ->assertHeaderMissing(AddSearchSuggestionHeader::HEADER);

    expect($response->json())->toBeArray()->not->toBeEmpty();
});

test('nothing close enough means no suggestion header', function () {
    $this->getJson(route('employees.search-signatory', ['q' => 'Xylophone']))
        ->assertOk()
        ->assertHeaderMissing(AddSearchSuggestionHeader::HEADER);
});
