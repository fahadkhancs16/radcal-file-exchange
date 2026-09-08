<?php

use App\Support\Filename;

it('strips any path from a filename', function () {
    expect(Filename::clean('../../etc/passwd'))->toBe('passwd')
        ->and(Filename::clean('C:\\Users\\bob\\secret.xlsx'))->toBe('secret.xlsx');
});

it('collapses whitespace and trims dots', function () {
    expect(Filename::clean('  my   report .pdf .. '))->toBe('my report .pdf');
});

it('compares names case-insensitively', function () {
    expect(Filename::comparisonKey('Report.PDF'))->toBe(Filename::comparisonKey('report.pdf'));
});

it('generates a disk name that keeps only a safe extension', function () {
    $stored = Filename::storedName('weird name.Tar.Gz');

    expect($stored)->toEndWith('.gz')
        ->and($stored)->toMatch('/^[0-9a-f-]{36}\./');
});

it('falls back to "file" for an empty name', function () {
    expect(Filename::clean('   '))->toBe('file');
});
