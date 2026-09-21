<?php

use App\Mail\ReminderEmail;

test('the reminder email renders without the optional requirement card when no training program is given', function () {
    $html = (new ReminderEmail(
        emailSubject: 'Reminder: Submit your requirement',
        body: '<p>Please submit your requirement.</p>',
        signature: 'TDI Team',
    ))->render();

    expect($html)->toContain('Reminder Notification');
    expect($html)->toContain('Please submit your requirement.');
    expect($html)->toContain('TDI Team');
    expect($html)->not->toContain('Requirement Overview');
});

test('the reminder email renders the optional requirement card when training program details are given', function () {
    $html = (new ReminderEmail(
        emailSubject: 'Reminder: Submit your TREAP',
        body: '<p>Please submit your TREAP.</p>',
        signature: 'TDI Team',
        trainingProgram: 'Values Orientation Workshop',
        dueDate: 'September 20, 2026',
        status: 'Overdue',
    ))->render();

    expect($html)->toContain('Requirement Overview');
    expect($html)->toContain('Values Orientation Workshop');
    expect($html)->toContain('September 20, 2026');
    expect($html)->toContain('Overdue');
});

test('the reminder email never references the removed TESDA logo image', function () {
    $html = (new ReminderEmail(
        emailSubject: 'Reminder',
        body: '<p>Body</p>',
        signature: 'TDI',
        trainingProgram: 'Sample Program',
        dueDate: 'January 1, 2027',
        status: 'Approved',
    ))->render();

    expect($html)->not->toContain('tesda-seal');
    expect($html)->not->toContain('<img');
});

test('a known status renders with its mapped accent color', function () {
    $html = (new ReminderEmail(
        emailSubject: 'Reminder',
        body: '<p>Body</p>',
        signature: 'TDI',
        trainingProgram: 'Sample Program',
        status: 'Approved',
    ))->render();

    // Approved maps to the green accent/text pairing.
    expect($html)->toContain('#059669');
    expect($html)->toContain('#047857');
});

test('an unrecognized status falls back to a neutral style instead of breaking', function () {
    $html = (new ReminderEmail(
        emailSubject: 'Reminder',
        body: '<p>Body</p>',
        signature: 'TDI',
        trainingProgram: 'Sample Program',
        status: 'Some Unexpected Value',
    ))->render();

    expect($html)->toContain('Some Unexpected Value');
    // Falls back to the neutral slate accent rather than any known status color.
    expect($html)->toContain('#94A3B8');
});

test('the requirement card defaults to Pending Submission styling when no status is given at all', function () {
    $html = (new ReminderEmail(
        emailSubject: 'Reminder',
        body: '<p>Body</p>',
        signature: 'TDI',
        trainingProgram: 'Sample Program',
    ))->render();

    expect($html)->toContain('Pending Submission');
});
