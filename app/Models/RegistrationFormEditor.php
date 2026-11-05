<?php

namespace App\Models;

use Database\Factories\RegistrationFormEditorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An admin the form's owner (or a superadmin) allowed to change a
 * registration form and review its registrations.
 */
class RegistrationFormEditor extends Model
{
    /** @use HasFactory<RegistrationFormEditorFactory> */
    use HasFactory;

    protected $fillable = [
        'registration_form_id',
        'user_id',
        'added_by_empcode',
        'added_by_name',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(RegistrationForm::class, 'registration_form_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
