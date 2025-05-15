<?php

declare(strict_types=1);

namespace App\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

class RoleToStringTransformer implements DataTransformerInterface
{
    /**
     * Transforms an array of roles (model data) into a single string for the form.
     *
     * @param mixed $valueFromEntity Typically an array of roles from $user->getRoles().
     * @return string|null The primary role string for the dropdown.
     */
    public function transform(mixed $valueFromEntity): ?string
    {
        if ($valueFromEntity === null) {
            // If roles are null (e.g., new user not yet fully initialized with default roles in some scenarios)
            return 'ROLE_USER';
        }

        if (!is_array($valueFromEntity)) {
            // This is where your current exception is likely thrown.
            // The detailed message will help confirm what type is actually received.
            throw new TransformationFailedException(
                sprintf(
                    'The RoleToStringTransformer expected an array or null, but got "%s". Property path: roles. Check User::getRoles() or how the User entity is populated.',
                    get_debug_type($valueFromEntity)
                )
            );
        }

        // At this point, $valueFromEntity is confirmed to be an array.
        if (empty($valueFromEntity)) {
            // If the roles array is empty, default to ROLE_USER.
            // User::getRoles() usually ensures it's not empty and contains ROLE_USER.
            return 'ROLE_USER';
        }

        if (in_array('ROLE_ADMIN', $valueFromEntity, true)) {
            return 'ROLE_ADMIN';
        }

        // If ROLE_ADMIN is not present, and the array is not empty,
        // it should contain ROLE_USER (guaranteed by User::getRoles()).
        if (in_array('ROLE_USER', $valueFromEntity, true)) {
            return 'ROLE_USER';
        }

        // Fallback if the array, for some reason, doesn't contain expected roles,
        // though User::getRoles() should prevent this.
        return 'ROLE_USER';
    }

    /**
     * Transforms a single role string (form data) back into an array for the entity.
     *
     * @param mixed $singleRoleString The role string from the dropdown.
     * @return list<string> An array containing the primary role.
     */
    public function reverseTransform(mixed $singleRoleString): array
    {
        if ($singleRoleString === null || $singleRoleString === '') {
            // If form submission is empty for this field, default to ROLE_USER.
            return ['ROLE_USER'];
        }

        if (!is_string($singleRoleString)) {
            throw new TransformationFailedException(
                sprintf(
                    'The RoleToStringTransformer expected a string or null for reverse transformation, but got "%s".',
                    get_debug_type($singleRoleString)
                )
            );
        }

        // Return an array with the single selected role.
        // User::setRoles() expects an array. User::getRoles() will then ensure
        // ROLE_USER is present if ROLE_ADMIN was the primary role.
        return [$singleRoleString];
    }
}
