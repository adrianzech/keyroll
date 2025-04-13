<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Constraint for validating the account update form.
 * Checks conditional requirements for current and new passwords.
 *
 * @Annotation
 * @Target({"CLASS", "ANNOTATION"}) // Keep doctrine annotation for compatibility if needed
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class ValidAccountUpdate extends Constraint
{
    public string $currentPasswordRequiredMessage = 'settings.flash.error.current_password_required';
    public string $currentPasswordIncorrectMessage = 'settings.flash.error.current_password_incorrect';
    public string $newPasswordRequiredMessage = 'settings.flash.error.new_password_required';
    public string $newPasswordBlankMessage = 'settings.flash.error.new_password_blank';

    public function getTargets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }
}
