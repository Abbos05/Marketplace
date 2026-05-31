<?php

use App\Support\ContactMasker;

test('email mask hides only local middle and keeps full domain', function () {
    expect(ContactMasker::email('admin@gmail.com'))->toBe('a***n@gmail.com');
    expect(ContactMasker::email('ivan.petrov@gmail.com'))->toBe('iv***ov@gmail.com');
});
