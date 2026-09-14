<?php

use App\Support\PasswordGenerator;

it('defaults to 12 characters', function () {
    expect(PasswordGenerator::generate())->toHaveLength(12);
});

it('honours an explicit length, with an 8-character floor', function () {
    expect(PasswordGenerator::generate(16))->toHaveLength(16)
        ->and(PasswordGenerator::generate(3))->toHaveLength(8);
});

it('always mixes uppercase, lowercase, digits and a symbol', function () {
    foreach (range(1, 100) as $i) {
        $password = PasswordGenerator::generate();

        expect($password)
            ->toMatch('/[A-Z]/')
            ->toMatch('/[a-z]/')
            ->toMatch('/[0-9]/')
            ->toMatch('/[!@#$%&*+=?]/');
    }
});

it('never contains visually ambiguous characters or a backtick', function () {
    foreach (range(1, 100) as $i) {
        expect(PasswordGenerator::generate())->not->toMatch('/[01OIl`]/');
    }
});

it('is not deterministic', function () {
    expect(PasswordGenerator::generate())->not->toBe(PasswordGenerator::generate());
});
