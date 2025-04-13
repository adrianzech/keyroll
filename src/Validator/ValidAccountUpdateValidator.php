<?php

declare(strict_types=1);

namespace App\Validator;

use App\Entity\User;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Validates the ValidAccountUpdate constraint.
 * Reduces complexity by delegating validation logic to private helper methods.
 */
class ValidAccountUpdateValidator extends ConstraintValidator
{
    public function __construct(
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        // 1. Initial Setup & Basic Guard Clauses
        if (!$constraint instanceof ValidAccountUpdate) {
            throw new UnexpectedTypeException($constraint, ValidAccountUpdate::class);
        }

        $form = $this->context->getRoot();
        if (!$form instanceof FormInterface) {
            throw new UnexpectedValueException($form, FormInterface::class);
        }

        $user = $form->getData();
        if (!$user instanceof User) {
            return; // Cannot validate if not a User
        }

        // 2. Get Field Values
        $currentPassword = $form->has('currentPassword') ? $form->get('currentPassword')->getData() : null;
        $plainPassword = $form->has('plainPassword') && $form->get('plainPassword')->has('first')
            ? $form->get('plainPassword')->get('first')->getData()
            : null;

        // 3. Delegate validation based on scenario
        $isPlainPasswordFilled = !empty($plainPassword);
        $isCurrentPasswordFilled = !empty($currentPassword);

        if ($isPlainPasswordFilled) {
            $this->validatePasswordChangeAttempt($constraint, $plainPassword, $currentPassword);
        } elseif ($isCurrentPasswordFilled) {
            // Only current password filled, but no new one.
            $this->addViolation($constraint->newPasswordRequiredMessage, 'plainPassword.first');
        }
        // Implicit else: Neither field filled - do nothing.
    }

    /**
     * Validates the scenario where a new password is provided.
     */
    private function validatePasswordChangeAttempt(
        ValidAccountUpdate $constraint,
        ?string $plainPassword, // Passed directly
        ?string $currentPassword, // Passed directly
    ): void {
        $isPlainPasswordNotBlank = !empty(trim((string) $plainPassword));
        $isCurrentPasswordFilled = !empty($currentPassword);

        // 2a: New password must not be blank
        if (!$isPlainPasswordNotBlank) {
            $this->addViolation($constraint->newPasswordBlankMessage, 'plainPassword.first');
            // Continue checking other potential issues
        }

        // 2b: Current password is required
        if (!$isCurrentPasswordFilled) {
            $this->addViolation($constraint->currentPasswordRequiredMessage, 'currentPassword');

            // Cannot validate current password if it wasn't provided, so stop this line of checks.
            return;
        }

        // 2c: Validate the provided current password
        // This is only reached if $isCurrentPasswordFilled was true.
        $violations = $this->validator->validate($currentPassword, new UserPassword());
        if (count($violations) > 0) {
            $this->addViolation($constraint->currentPasswordIncorrectMessage, 'currentPassword');
        }
    }

    /**
     * Helper method to add violations consistently.
     */
    private function addViolation(string $message, string $path): void
    {
        $this->context->buildViolation($message)
            ->atPath($path)
            ->addViolation();
    }
}
