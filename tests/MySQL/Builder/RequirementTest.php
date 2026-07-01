<?php

declare(strict_types=1);

use Flowpack\QueryObjectBuilder\MySQL\Builder\Requirement;
use Flowpack\QueryObjectBuilder\MySQL\Builder\Target;

describe('Requirement', function () {
    it('is not satisfied by a target at or above the upper version bound', function () {
        // Half-open window [.., 8.0): 8.0 itself is out.
        expect(Requirement::mysql(ltVersion: '8.0')->satisfiedBy(Target::mysql('8.0')))->toBeFalse();
        expect(Requirement::mysql(ltVersion: '8.0')->satisfiedBy(Target::mysql('5.7')))->toBeTrue();
    });

    it('describes a version window', function () {
        expect(Requirement::mysql(gteVersion: '10.5', ltVersion: '13.0')->describe())->toBe('MySQL 10.5–13.0');
        expect(Requirement::mysql(ltVersion: '8.0')->describe())->toBe('MySQL < 8.0');
    });
});
