<?php

declare(strict_types=1);

use Filagora\AiForums\Search\ThreadVectorSync;
use Filagora\Forums\Services\ForumAccess;

it('does not build the forum access rules or its own sync while the application boots', function () {
    // The access rules are built from state that is not settled yet at boot, and building them early
    // once broke the staff preview of a host application.
    expect(app()->resolved(ForumAccess::class))->toBeFalse()
        ->and(app()->resolved(ThreadVectorSync::class))->toBeFalse();
});
